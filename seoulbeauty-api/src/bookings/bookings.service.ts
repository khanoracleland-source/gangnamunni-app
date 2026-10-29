import { Inject, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import {
  bookingTab, cancelSteps, cardDate, dateTimeLabel, dday, detailDate, isPgProvider, paymentState, paymentTierPoint, requestSteps, reviewState,
} from './bookings.logic';

export interface BookingMember { id: string; phone: string; name?: string }

const n = (v: unknown) => Number(v ?? 0) || 0;
const s = (v: unknown) => (v == null ? '' : String(v));
const digits = (v: unknown) => s(v).replace(/[^0-9]/g, '');
const STATUS_LABELS: Record<string, string> = {
  pending: '예약대기', hospital_checking: '병원 확인 중', confirmed: '예약확정', completed: '시술완료', cancelled: '예약취소', no_show: '노쇼',
};

@Injectable()
export class BookingsService {
  private readonly log = new Logger(BookingsService.name);
  constructor(@Inject(DB) private readonly db: Db) {}

  private async safe<T>(label: string, run: () => Promise<T>, fallback: T): Promise<T> {
    try { return await run(); } catch (e) { this.log.error(`${label}: ${(e as Error).message}`); return fallback; }
  }

  /** The member's own booking: same mb_id, or an old row without mb_id whose phone is the member's phone. */
  private owns(m: BookingMember, row: any): boolean {
    if (m.id && s(row.mb_id) === m.id) return true;
    return !s(row.mb_id) && digits(m.phone) !== '' && digits(row.phone) === digits(m.phone);
  }

  private async payments(ids: number[]): Promise<Map<number, any>> {
    const map = new Map<number, any>();
    if (!ids.length) return map;
    const rows = await this.safe('payments', async () => ((await this.db.query(
      `SELECT booking_id, status, amount, provider FROM gangnamunni_platform_payments WHERE payment_type = 'booking' AND booking_id IN (${ids.map(() => '?').join(',')})`, ids)) as any)[0] as any[], [] as any[]);
    for (const r of rows) map.set(n(r.booking_id), r);
    const previews = await this.safe('payment previews', async () => ((await this.db.query(
      `SELECT booking_id FROM gangnamunni_booking_payment_previews WHERE booking_id IN (${ids.map(() => '?').join(',')})`, ids)) as any)[0] as any[], [] as any[]);
    for (const p of previews) { const r = map.get(n(p.booking_id)); if (r && s(r.status) === 'pending') r.status = 'preview'; }
    return map;
  }

  /** my_bookings.php (latest 50) split into the three tabs. */
  async list(m: BookingMember) {
    const phone = digits(m.phone);
    const [rows] = (await this.db.query(
      `SELECT b.*, h.profile_image_url AS hospital_image FROM gangnamunni_bookings b LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
       WHERE b.mb_id = ? OR ((b.mb_id IS NULL OR b.mb_id = '') AND ? <> '' AND REPLACE(REPLACE(b.phone, '-', ''), ' ', '') = ?)
       ORDER BY b.created_at DESC LIMIT 50`, [m.id, phone, phone])) as any;
    const bookings = rows as any[];
    const ids = bookings.map((b) => n(b.id));
    const pay = await this.payments(ids);
    const completedIds = bookings.filter((b) => s(b.status) === 'completed').map((b) => n(b.id));
    const reviews = new Map<number, any>();
    if (completedIds.length) {
      const rr = await this.safe('booking reviews', async () => ((await this.db.query(
        `SELECT booking_id, id, review_status, is_active FROM hospital_reviews WHERE booking_id IN (${completedIds.map(() => '?').join(',')}) ORDER BY id ASC`, completedIds)) as any)[0] as any[], [] as any[]);
      for (const r of rr) reviews.set(n(r.booking_id), r);
    }
    const base = (b: any) => ({
      id: n(b.id), hospitalId: n(b.hospital_id), hospitalName: s(b.hospital_name) || '-', imageUrl: b.hospital_image ? s(b.hospital_image) : null,
      treatment: s(b.treatment), price: n(b.price), dateText: cardDate(b.booking_date, b.booking_time),
    });
    const upcoming: any[] = [], completed: any[] = [], cancelled: any[] = [];
    for (const b of bookings) {
      const tab = bookingTab(b.status, b.booking_date, b.booking_time);
      if (tab === 'upcoming') {
        const ps = paymentState(b.status, pay.get(n(b.id)));
        const st = requestSteps(s(b.status), ps);
        const editsLeft = Math.max(0, 3 - n(b.edit_count));
        const action = s(b.status) === 'confirmed' ? (['payable', 'preview'].includes(ps) ? 'pay' : null) : 'change';
        upcoming.push({ ...base(b), status: s(b.status), chip: `${st.chip} · ${dday(b.booking_date)}`, steps: st.steps, activeStep: st.active, action, editsLeft, paid: ['paid', 'partially_refunded', 'test_paid'].includes(ps) });
      } else if (tab === 'cancelled') {
        const cs = cancelSteps(pay.get(n(b.id)));
        cancelled.push({ ...base(b), status: s(b.status), chip: cs.chip, refundComplete: cs.refundComplete, steps: cs.steps, activeStep: cs.active });
      } else {
        const isDone = s(b.status) === 'completed';
        const rv = isDone ? reviews.get(n(b.id)) : undefined;
        const rs = reviewState(rv);
        completed.push({ ...base(b), status: s(b.status), isDone, chip: isDone ? '시술완료' : '완료 확인중', review: isDone ? { state: rs.state, label: rs.label, reviewId: rv ? n(rv.id) : null } : null });
      }
    }
    const expired = bookings.find((b) => tab(b) === 'cancelled' && s(b.cancelled_by) === 'system' && !s(b.expire_notice_seen_at)
      && s(b.cancelled_at) !== '' && s(b.cancelled_at).slice(0, 19) >= new Date(Date.now() + 9 * 3600_000 - 30 * 86400_000).toISOString().slice(0, 19).replace('T', ' '));
    function tab(b: any) { return bookingTab(b.status, b.booking_date, b.booking_time); }
    const [showPushHint, deadlineHours] = await Promise.all([
      upcoming.length && m.id ? this.pushHint(m.id) : Promise.resolve(false),
      expired ? this.deadlineHours() : Promise.resolve(24),
    ]);
    return {
      upcoming, completed, cancelled, showPushHint,
      expiredNotice: expired ? { bookingId: n(expired.id), hospitalId: n(expired.hospital_id), treatment: s(expired.treatment), deadlineHours } : null,
    };
  }

  /** Hint to turn on push notifications: shown unless a token exists and both booking + push notifications are on. */
  private async pushHint(mbId: string): Promise<boolean> {
    const tokens = await this.safe('push tokens', async () => n((((await this.db.query('SELECT COUNT(*) AS c FROM g5_push_tokens WHERE mb_id = ? AND pt_use = 1', [mbId])) as any)[0] as any[])[0]?.c), 0);
    let booking = 1, push = 1;
    const pref = await this.safe('notification settings', async () => (((await this.db.query('SELECT noti_booking, noti_push FROM gangnamunni_notification_settings WHERE member_id = ? LIMIT 1', [mbId])) as any)[0] as any[])[0], undefined as any);
    if (pref) { booking = n(pref.noti_booking); push = n(pref.noti_push); }
    return !(tokens > 0 && booking === 1 && push === 1);
  }

  private async deadlineHours(): Promise<number> {
    const v = await this.safe('deadline hours', async () => s((((await this.db.query("SELECT setting_value FROM gangnamunni_settings WHERE setting_key = 'payment_deadline_hours' LIMIT 1")) as any)[0] as any[])[0]?.setting_value), '');
    return Math.max(1, Math.min(168, n(v) || 24));
  }

  /** my_bookings.php expire_seen: the member has read the auto-cancel notice (own system-cancelled booking only). */
  async expireSeen(m: BookingMember, id: number): Promise<{ success: boolean }> {
    if (!m.id) return { success: false };
    const [res] = (await this.db.query(
      "UPDATE gangnamunni_bookings SET expire_notice_seen_at = NOW() WHERE id = ? AND mb_id = ? AND cancelled_by = 'system' AND expire_notice_seen_at IS NULL", [id, m.id])) as any;
    return { success: n((res as any)?.affectedRows) > 0 };
  }

  /** booking_request_detail.php / booking_cancel_detail.php (chosen by the booking's status). */
  async detail(m: BookingMember, id: number) {
    const [rows] = (await this.db.query(
      'SELECT b.*, h.profile_image_url AS hospital_image FROM gangnamunni_bookings b LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id WHERE b.id = ? LIMIT 1', [id])) as any;
    const b = (rows as any[])[0];
    if (!b || !this.owns(m, b)) throw new NotFoundException('예약을 찾을 수 없습니다.'); // not yours looks the same as missing
    const status = s(b.status);
    const common = {
      id: n(b.id), hospitalId: n(b.hospital_id), hospitalName: s(b.hospital_name) || '-', treatment: s(b.treatment) || '-', bookerName: s(b.name) || '-', phone: s(b.phone) || '-',
      dateText: detailDate(b.booking_date, b.booking_time), price: n(b.price), status,
    };
    const payRow = await this.safe('payment row', async () => (((await this.db.query(
      "SELECT status, provider, amount, refunded_amount FROM gangnamunni_platform_payments WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1", [id])) as any)[0] as any[])[0], undefined as any);

    if (status === 'cancelled' || status === 'no_show') {
      const by = s(b.cancelled_by);
      const refundComplete = payRow
        ? (['pending', 'failed', 'refunded', 'cancelled'].includes(s(payRow.status)) || ['mock_booking_payment', 'toss_test', 'portone_test'].includes(s(payRow.provider)))
        : true;
      const paidView = !!payRow && isPgProvider(payRow.provider) && ['paid', 'partially_refunded', 'refunded'].includes(s(payRow.status));
      const refunded = n(payRow?.refunded_amount);
      return {
        kind: 'cancelled' as const, ...common,
        badge: by === 'hospital' ? '병원 취소' : by === 'system' ? '자동 취소' : refundComplete ? '취소 완료' : '취소 접수',
        reason: ['hospital', 'system'].includes(by) && s(b.cancel_reason).trim() ? s(b.cancel_reason) : null,
        paid: paidView ? {
          total: n(payRow.amount), cancelledAt: s(b.cancelled_at) ? dateTimeLabel(b.cancelled_at) : null, refunded: refunded > 0 ? refunded : null,
        } : null,
      };
    }

    const isActive = ['pending', 'hospital_checking', 'confirmed'].includes(status);
    const isCompleted = status === 'completed';
    let review: { state: string; label: string; reviewId: number | null } | null = null;
    if (isCompleted) {
      const rv = await this.safe('booking review', async () => (((await this.db.query(
        'SELECT id, review_status, is_active FROM hospital_reviews WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [id])) as any)[0] as any[])[0], undefined as any);
      const rs = reviewState(rv);
      review = { state: rs.state, label: rs.label || '후기 확인중', reviewId: rv ? n(rv.id) : null };
    }
    const total = payRow ? Math.max(0, n(payRow.amount)) : n(b.price);
    const summary = isActive && status === 'confirmed' ? await this.paySummary(id, n(b.price)) : null;
    const notifications = await this.history(id, s(b.mb_id));
    return {
      kind: 'request' as const, ...common,
      statusLabel: STATUS_LABELS[status] ?? status, isActive, isCompleted, dday: isActive ? dday(b.booking_date) : '',
      doctorName: !isActive ? (s(b.doctor_name).trim() || '-') : null,
      treatmentPrice: n(b.price), totalPayment: total, summary, review, notifications,
    };
  }

  /** 05-35 payment summary of a confirmed booking (+ expected points when the v2 policy is on). */
  private async paySummary(id: number, price: number) {
    let gross = price, points = 0, final = price;
    const row = await this.safe('pay summary', async () => (((await this.db.query(
      "SELECT amount, points_used, gross_amount FROM gangnamunni_platform_payments WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1", [id])) as any)[0] as any[])[0], undefined as any);
    if (row) {
      points = Math.max(0, n(row.points_used)); final = Math.max(0, n(row.amount)); gross = Math.max(0, n(row.gross_amount));
      if (gross <= 0) gross = final + points;
    }
    return { gross, points, final, expectedPoints: await this.expectedPoints(gross) };
  }

  private async expectedPoints(gross: number): Promise<number | null> {
    const cfg = await this.safe('point settings', async () => {
      const [r] = (await this.db.query('SELECT skey, sval FROM gangnamunni_point_settings')) as any;
      const map: Record<string, string> = {};
      for (const x of r as any[]) map[s(x.skey)] = s(x.sval);
      return map;
    }, null as Record<string, string> | null);
    if (!cfg) return null;
    const on = n(cfg.enabled) === 1 && n(cfg.v2_schema_version) >= 1 && n(cfg.v2_policy_enabled) === 1;
    if (!on) return null;
    const v = (k: string, d: number) => (cfg[k] === undefined ? d : n(cfg[k]));
    return paymentTierPoint(gross, { t1Under: v('v2_pay_tier1_under', 50000), t1: v('v2_pay_tier1_point', 3000), t2Under: v('v2_pay_tier2_under', 100000), t2: v('v2_pay_tier2_point', 5000), t3: v('v2_pay_tier3_point', 10000) });
  }

  /** 알림 이력 of this booking (latest 8). */
  private async history(id: number, mbId: string) {
    const rows = await this.safe('notification history', async () => ((await this.db.query(
      `SELECT created_at, notification_type, title, message, link, in_app_count, push_sent, push_failed, push_skipped, push_details
       FROM gangnamunni_notification_sends
       WHERE notification_type LIKE 'booking_%' AND (link LIKE ? OR push_details LIKE ? OR target_type = ?)
       ORDER BY created_at DESC LIMIT 8`, [`%booking_id=${id}%`, `%"booking_id":${id}%`, mbId ? `booking:${mbId}` : '__none__'])) as any)[0] as any[], [] as any[]);
    return rows.map((r) => {
      let event = '';
      try { const d = JSON.parse(s(r.push_details)); if (d && typeof d === 'object') event = s(d.event).trim(); } catch { /* not JSON */ }
      const at = s(r.created_at);
      return {
        at: at ? `${at.slice(0, 4)}.${at.slice(5, 7)}.${at.slice(8, 10)} ${at.slice(11, 16)}` : '-', event: event || null, title: s(r.title) || '-',
        body: s(r.message).replace(/\n\[push(?:_error)?\][\s\S]*$/, '').trim(),
        inApp: n(r.in_app_count), pushSent: n(r.push_sent), pushFailed: n(r.push_failed), pushSkipped: n(r.push_skipped) > 0,
      };
    });
  }
}
