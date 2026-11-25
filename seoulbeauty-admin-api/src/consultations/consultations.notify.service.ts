import { Inject, Injectable, Logger } from '@nestjs/common';
import { buildMemberFcmMessage, koreanPhone } from '../bookings/notify/notify.logic';
import { FcmClient } from '../bookings/notify/fcm.client';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { NoticeEvent, noticePayload, noticePushData } from './consultations.logic';

const s = (v: unknown): string => (v == null ? '' : String(v));

/**
 * Tells the member about a change the hospital made to a consultation request: an in-app notice and a push.
 * Best effort and after the change is committed: a failure here is only logged and never fails the admin's request.
 */
@Injectable()
export class ConsultationNotifyService {
  private readonly log = new Logger('ConsultationNotify');
  /** The public site address: links are absolute because the app opens them in its WebView. */
  private readonly base = (process.env.PHP_PUBLIC_SITE_URL || 'https://seoulbeauty.app/').replace(/\/+$/, '') + '/';

  constructor(@Inject(DB) private readonly db: Db, @Inject(DB_RW) private readonly rw: RwDbProvider, private readonly fcm: FcmClient) {}

  /** Fire and forget: the admin's answer never waits for FCM. */
  later(consultationId: number, event: NoticeEvent): void {
    void this.notify(consultationId, event).catch((e) => this.log.error(`consultation #${consultationId} ${event}: ${(e as Error).message}`));
  }

  async notify(consultationId: number, event: NoticeEvent): Promise<{ reason: string; inApp: boolean; pushSent: number; pushFailed: number }> {
    const out = { reason: '', inApp: false, pushSent: 0, pushFailed: 0 };
    const [rows] = (await this.db.query('SELECT hospital_name, mb_id, phone FROM gangnamunni_consultations WHERE id = ? LIMIT 1', [consultationId])) as any;
    const row = (rows as any[])[0];
    if (!row) { out.reason = 'consultation_not_found'; return out; }
    const memberId = await this.memberId(s(row.mb_id).trim(), row.phone);
    if (!memberId) { out.reason = 'member_not_found'; return out; }
    const db = this.rw.get();
    if (!db) { out.reason = 'write_unavailable'; return out; }

    const p = noticePayload(event, row.hospital_name);
    const link = this.base + p.link;
    try {
      await db.query('INSERT INTO gangnamunni_user_notifications (member_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)', [memberId, 'general', p.title, p.body, link, 'bi-bell']);
      out.inApp = true;
    } catch (e) { this.log.error(`in-app row (consultation #${consultationId}): ${(e as Error).message}`); }
    await this.push(db, memberId, p.title, p.body, noticePushData(link), out);
    this.log.log(`consultation notice: consultation=${consultationId} event=${event} inApp=${out.inApp} pushSent=${out.pushSent} pushFailed=${out.pushFailed}`);
    return out;
  }

  /** The member who made the request; one made without signing in is matched by phone number (newest member first), like the booking notices. */
  private async memberId(direct: string, phoneRaw: unknown): Promise<string> {
    if (direct) return direct;
    const phone = koreanPhone(phoneRaw);
    if (!phone) return '';
    const intl = '82' + phone.slice(1);
    try {
      const [rows] = (await this.db.query(
        "SELECT mb_id FROM g5_member WHERE REPLACE(REPLACE(REPLACE(mb_hp, '-', ''), ' ', ''), '+', '') IN (?, ?) ORDER BY mb_datetime DESC LIMIT 1", [phone, intl])) as any;
      return s((rows as any[])[0]?.mb_id).trim();
    } catch { return ''; }
  }

  /** Every active device token of the member; tokens FCM reports as dead are switched off. */
  private async push(db: RwDb, memberId: string, title: string, body: string, data: Record<string, string>, out: { pushSent: number; pushFailed: number; reason: string }): Promise<void> {
    try {
      const [en] = (await this.db.query("SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = 'fcm_enabled' LIMIT 1")) as any;
      if ((en as any[])[0] && String((en as any[])[0].setting_value) !== '1') { out.reason = 'fcm_disabled'; return; }
      const [toks] = (await this.db.query("SELECT DISTINCT pt_token, pt_platform, pt_device_info FROM g5_push_tokens WHERE pt_use = 1 AND pt_token <> '' AND mb_id = ?", [memberId])) as any;
      const tokens = toks as any[];
      if (!tokens.length) { out.reason = 'no_device_tokens'; return; }
      const sess = await this.fcm.session();
      if (!sess) { out.pushFailed = tokens.length; out.reason = 'firebase_unavailable'; return; }
      for (const t of tokens) {
        const platform = s(t.pt_platform) || (/iPhone|iPad|iPod/i.test(s(t.pt_device_info)) ? 'ios' : 'android');
        const r = await this.fcm.sendMessage(sess, buildMemberFcmMessage(String(t.pt_token), platform, title, body, data));
        if (r.ok) out.pushSent++;
        else {
          out.pushFailed++;
          if (r.dead) await db.query('UPDATE g5_push_tokens SET pt_use = 0 WHERE pt_token = ?', [t.pt_token]).catch((e) => this.log.warn(`push token not switched off: ${(e as Error).message}`));
        }
      }
    } catch (e) { out.pushFailed++; this.log.error(`member push (${memberId}): ${(e as Error).message}`); }
  }
}
