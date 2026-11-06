import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { FcmClient } from '../admin-notify/fcm.client';
import { BookingEvent, BookingRow, buildMemberFcmMessage, koreanPhone, memberPushData, payload, switchFor } from './member-notify.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;

export interface NotifyResult { memberId: string; reason: string; inApp: boolean; pushSent: number; pushFailed: number; pushSkipped: boolean; pushError: string }

/**
 * notify_booking_member() (includes/booking_notifications.php) for the events Nest raises itself: 예약 접수, 결제 완료,
 * 본인 취소, 변경. One switch decides everything: 설정 > 예약 · 결제 알림 (noti_booking) — off = neither the in-app row
 * nor the push (방문 전 알림 follows noti_visit instead). Best effort: nothing here can fail the member's request.
 */
@Injectable()
export class MemberNotifyService {
  private readonly log = new Logger(MemberNotifyService.name);
  /** SITE_FULL_URL of the public PHP site: the links are absolute, like the PHP-sent ones (the app opens them in its WebView). */
  private readonly base = (process.env.PHP_PUBLIC_SITE_URL || 'https://seoulbeauty.app/').replace(/\/+$/, '') + '/';
  constructor(@Inject(DB) private readonly db: Db, private readonly fcm: FcmClient) {}

  /** Fire-and-forget wrapper: the member's request never waits for FCM. */
  bookingLater(bookingId: number, event: BookingEvent, ctx: { doctorName?: string; deadlineHours?: number; amount?: number; recheck?: boolean } = {}): void {
    void this.booking(bookingId, event, ctx).catch((e) => this.log.error(`booking #${bookingId} ${event}: ${(e as Error).message}`));
  }

  async booking(bookingId: number, event: BookingEvent, ctx: { doctorName?: string; deadlineHours?: number; amount?: number; recheck?: boolean } = {}): Promise<NotifyResult> {
    const out: NotifyResult = { memberId: '', reason: '', inApp: false, pushSent: 0, pushFailed: 0, pushSkipped: false, pushError: '' };
    const b = await this.row(bookingId);
    if (!b) { out.reason = 'booking_not_found'; return out; }
    const memberId = await this.memberId(b);
    out.memberId = memberId;
    if (!memberId) { out.reason = 'member_not_found'; return out; }

    const sw = switchFor(event);
    if (!(await this.allowed(memberId, sw))) { out.reason = `${sw === 'noti_visit' ? 'visit' : 'booking'}_notifications_disabled`; return out; }

    const p = payload(b, event, ctx);
    p.link = this.base + p.link;
    try {
      await this.db.query('INSERT INTO gangnamunni_user_notifications (member_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)',
        [memberId, 'booking', p.title, p.body, p.link, 'bi-calendar-check']);
      out.inApp = true;
    } catch (e) { this.log.error(`in-app row (booking #${bookingId}): ${(e as Error).message}`); }

    await this.push(memberId, p.title, p.body, memberPushData(p.link, bookingId, event), out);
    await this.logSend(b, memberId, event, p.title, p.body, p.link, out);
    return out;
  }

  private async row(id: number): Promise<BookingRow | null> {
    const [rows] = (await this.db.query('SELECT id, hospital_id, hospital_name, treatment, booking_date, booking_time, price, mb_id, phone FROM gangnamunni_bookings WHERE id = ? LIMIT 1', [id])) as any;
    return ((rows as any[])[0] as BookingRow) ?? null;
  }

  /** booking_notification_find_member_id(): mb_id, else the newest member with the booking's phone. */
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

  /** 설정 page switch; a missing row or column reads as on (the default). */
  private async allowed(memberId: string, col: 'noti_booking' | 'noti_visit'): Promise<boolean> {
    try {
      const [rows] = (await this.db.query('SELECT * FROM gangnamunni_notification_settings WHERE member_id = ? LIMIT 1', [memberId])) as any;
      const r = (rows as any[])[0];
      if (!r || r[col] == null) return true;
      return n(r[col]) === 1;
    } catch (e) { this.log.warn(`notification settings (${memberId}): ${(e as Error).message}`); return true; }
  }

  /** send_push_to_members(): every active device token of the member; dead tokens are switched off. */
  private async push(memberId: string, title: string, body: string, data: Record<string, string>, out: NotifyResult): Promise<void> {
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
          if (r.dead) await this.db.query('UPDATE g5_push_tokens SET pt_use = 0 WHERE pt_token = ?', [t.pt_token]).catch(() => undefined);
        }
      }
    } catch (e) { out.pushFailed++; out.pushError = (e as Error).message; this.log.error(`member push (${memberId}): ${out.pushError}`); }
  }

  /** booking_notification_log_send(): the row the booking detail's 알림 history reads. */
  private async logSend(b: BookingRow, memberId: string, event: string, title: string, body: string, link: string, r: NotifyResult): Promise<void> {
    const details = JSON.stringify({ booking_id: n(b.id), event, member_id: memberId, in_app_created: r.inApp, push_sent: r.pushSent, push_failed: r.pushFailed, push_skipped: r.pushSkipped, push_error: r.pushError });
    try {
      await this.db.query(
        `INSERT INTO gangnamunni_notification_sends (admin_id, admin_username, target_type, target_count, notification_type, title, message, link, resolved_count, in_app_count, push_sent, push_failed, push_skipped, push_error, push_details)
         VALUES (0, 'system', ?, 1, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)`,
        [`booking:${memberId}`, `booking_${event}`, title, body, link, r.inApp ? 1 : 0, r.pushSent, r.pushFailed, r.pushSkipped ? 1 : 0, r.pushError, details]);
    } catch (e) { this.log.warn(`send log (booking #${n(b.id)}): ${(e as Error).message}`); }
  }
}
