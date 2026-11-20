import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db, DB_RW, RwDbProvider } from '../../db/db.module';
import { FcmClient } from './fcm.client';
import { BookingEvent, BookingRow, buildMemberFcmMessage, koreanPhone, memberPushData, payload } from './notify.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;

export interface NotifyContext { doctorName?: string; deadlineHours?: number; amount?: number }
export interface NotifyResult { memberId: string; reason: string; inApp: boolean; pushSent: number; pushFailed: number; pushSkipped: boolean; pushError: string }

/**
 * Tells the member about a change the hospital made to the booking: an in-app message and a push. One switch decides both
 * (설정 > 예약 · 결제 알림, noti_booking): off = neither. Best effort: a failure here never fails the admin's request,
 * because the booking change is already committed when this runs.
 */
@Injectable()
export class BookingNotifyService {
  private readonly log = new Logger(BookingNotifyService.name);
  /** The public site address: links are absolute because the app opens them in its WebView. */
  private readonly base = (process.env.PHP_PUBLIC_SITE_URL || 'https://seoulbeauty.app/').replace(/\/+$/, '') + '/';
  constructor(@Inject(DB) private readonly db: Db, @Inject(DB_RW) private readonly rw: RwDbProvider, private readonly fcm: FcmClient) {}

  /** Fire and forget: the admin's answer never waits for FCM. */
  later(bookingId: number, event: BookingEvent, ctx: NotifyContext = {}): void {
    void this.booking(bookingId, event, ctx).catch((e) => this.log.error(`booking #${bookingId} ${event}: ${(e as Error).message}`));
  }

  async booking(bookingId: number, event: BookingEvent, ctx: NotifyContext = {}): Promise<NotifyResult> {
    const out: NotifyResult = { memberId: '', reason: '', inApp: false, pushSent: 0, pushFailed: 0, pushSkipped: false, pushError: '' };
    const b = await this.row(bookingId);
    if (!b) { out.reason = 'booking_not_found'; return out; }
    const memberId = await this.memberId(b);
    out.memberId = memberId;
    if (!memberId) { out.reason = 'member_not_found'; return out; }
    if (!(await this.allowed(memberId))) { out.reason = 'booking_notifications_disabled'; return out; }

    const p = payload(b, event, ctx);
    p.link = this.base + p.link;
    const db = this.rw.get();
    if (!db) { out.reason = 'write_unavailable'; return out; }
    try {
      await db.query('INSERT INTO gangnamunni_user_notifications (member_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)',
        [memberId, 'booking', p.title, p.body, p.link, 'bi-calendar-check']);
      out.inApp = true;
    } catch (e) { this.log.error(`in-app row (booking #${bookingId}): ${(e as Error).message}`); }

    await this.push(db, memberId, p.title, p.body, memberPushData(p.link, bookingId, event), out);
    await this.logSend(db, b, memberId, event, p.title, p.body, p.link, out);
    return out;
  }

  private async row(id: number): Promise<BookingRow | null> {
    const [rows] = (await this.db.query('SELECT id, hospital_id, hospital_name, treatment, booking_date, booking_time, price, mb_id, phone, doctor_name FROM gangnamunni_bookings WHERE id = ? LIMIT 1', [id])) as any;
    return ((rows as any[])[0] as BookingRow) ?? null;
  }

  /** The booking's member; a booking made without signing in is matched by phone number (newest member first). */
  private async memberId(b: BookingRow): Promise<string> {
    const direct = s(b.mb_id).trim();
    if (direct) return direct;
    const phone = koreanPhone(b.phone);
    if (!phone) return '';
    const intl = '82' + phone.slice(1);
    try {
      const [rows] = (await this.db.query(
        "SELECT mb_id FROM g5_member WHERE REPLACE(REPLACE(REPLACE(mb_hp, '-', ''), ' ', ''), '+', '') IN (?, ?) ORDER BY mb_datetime DESC LIMIT 1", [phone, intl])) as any;
      return s((rows as any[])[0]?.mb_id).trim();
    } catch { return ''; }
  }

  /** The member's switch; a missing row or column reads as on (the default). */
  private async allowed(memberId: string): Promise<boolean> {
    try {
      const [rows] = (await this.db.query('SELECT noti_booking FROM gangnamunni_notification_settings WHERE member_id = ? LIMIT 1', [memberId])) as any;
      const r = (rows as any[])[0];
      if (!r || r.noti_booking == null) return true;
      return n(r.noti_booking) === 1;
    } catch (e) { this.log.warn(`notification settings (${memberId}): ${(e as Error).message}`); return true; }
  }

  /** Every active device token of the member; tokens FCM reports as dead are switched off. */
  private async push(db: NonNullable<ReturnType<RwDbProvider['get']>>, memberId: string, title: string, body: string, data: Record<string, string>, out: NotifyResult): Promise<void> {
    try {
      const [en] = (await this.db.query("SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = 'fcm_enabled' LIMIT 1")) as any;
      if ((en as any[])[0] && String((en as any[])[0].setting_value) !== '1') { out.pushSkipped = true; out.pushError = 'FCM disabled'; return; }
      const [toks] = (await this.db.query("SELECT DISTINCT pt_token, pt_platform, pt_device_info FROM g5_push_tokens WHERE pt_use = 1 AND pt_token <> '' AND mb_id = ?", [memberId])) as any;
      const tokens = toks as any[];
      if (!tokens.length) { out.pushSkipped = true; out.pushError = 'No active device tokens are registered'; return; }
      const sess = await this.fcm.session();
      if (!sess) { out.pushFailed = tokens.length; out.pushError = 'Firebase credentials unavailable'; return; }
      for (const t of tokens) {
        const platform = s(t.pt_platform) || (/iPhone|iPad|iPod/i.test(s(t.pt_device_info)) ? 'ios' : 'android');
        const r = await this.fcm.sendMessage(sess, buildMemberFcmMessage(String(t.pt_token), platform, title, body, data));
        if (r.ok) out.pushSent++;
        else {
          out.pushFailed++;
          if (r.dead) await db.query('UPDATE g5_push_tokens SET pt_use = 0 WHERE pt_token = ?', [t.pt_token]).catch((e) => this.log.warn(`push token not switched off: ${(e as Error).message}`));
        }
      }
    } catch (e) { out.pushFailed++; out.pushError = (e as Error).message; this.log.error(`member push (${memberId}): ${out.pushError}`); }
  }

  /** The row the booking's notification history reads. */
  private async logSend(db: NonNullable<ReturnType<RwDbProvider['get']>>, b: BookingRow, memberId: string, event: string, title: string, body: string, link: string, r: NotifyResult): Promise<void> {
    const details = JSON.stringify({ booking_id: n(b.id), event, member_id: memberId, in_app_created: r.inApp, push_sent: r.pushSent, push_failed: r.pushFailed, push_skipped: r.pushSkipped, push_error: r.pushError });
    try {
      await db.query(
        `INSERT INTO gangnamunni_notification_sends (admin_id, admin_username, target_type, target_count, notification_type, title, message, link, resolved_count, in_app_count, push_sent, push_failed, push_skipped, push_error, push_details)
         VALUES (0, 'system', ?, 1, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)`,
        [`booking:${memberId}`, `booking_${event}`, title, body, link, r.inApp ? 1 : 0, r.pushSent, r.pushFailed, r.pushSkipped ? 1 : 0, r.pushError, details]);
    } catch (e) { this.log.warn(`send log (booking #${n(b.id)}): ${(e as Error).message}`); }
  }
}
