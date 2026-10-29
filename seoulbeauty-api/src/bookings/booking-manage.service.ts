import { HttpException, Inject, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { createHash } from 'node:crypto';
import { DB, DbConn, DbPool } from '../db/db.module';
import { BookingService, dateStr } from '../booking/booking.service';
import { cancelDeadline, noshowCancelPreview } from '../booking/booking.logic';
import { PointsService } from '../points/points.service';
import { detailDate, isPgProvider, kstNow } from './bookings.logic';
import { BookingMember } from './bookings.service';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;
const digits = (v: unknown) => s(v).replace(/[^0-9]/g, '');
const ACTIVE = ['pending', 'hospital_checking', 'confirmed'];
export const MAX_EDITS = 3;
export const CANCEL_REASONS = ['일정 변경', '개인 사정', '다른 병원 예약', '금액 / 시술 변경', '기타'];

/** api/cancel_booking.php and api/edit_booking.php answer {success:false, message} with 200; here it is a 422 with the same text. */
export class ManageRefused extends HttpException {
  constructor(message: string, status = 422) { super({ success: false, message }, status); }
}

/** 예약 취소 (booking_cancel_request.php + api/cancel_booking.php) and 예약 변경 (booking_change.php + api/edit_booking.php). */
@Injectable()
export class BookingManageService {
  private readonly log = new Logger(BookingManageService.name);
  constructor(@Inject(DB) private readonly db: DbPool, private readonly booking: BookingService, private readonly points: PointsService) {}

  private async hasCol(table: string, col: string): Promise<boolean> {
    try { const [rows] = (await this.db.query(`SHOW COLUMNS FROM ${table} LIKE ?`, [col])) as any; return (rows as any[]).length > 0; } catch { return false; }
  }
  private async hasTable(name: string): Promise<boolean> {
    try { const [rows] = (await this.db.query('SHOW TABLES LIKE ?', [name])) as any; return (rows as any[]).length > 0; } catch { return false; }
  }

  /** booking_payment_owned(): own mb_id, or an old row without mb_id whose phone is the member's phone. */
  private async own(m: BookingMember, id: number) {
    const [rows] = (await this.db.query('SELECT * FROM gangnamunni_bookings WHERE id = ? LIMIT 1', [id])) as any;
    const b = (rows as any[])[0];
    if (!b) return null;
    const mine = (m.id && s(b.mb_id) === m.id) || (!s(b.mb_id) && digits(m.phone) !== '' && digits(b.phone) === digits(m.phone));
    return mine ? b : null;
  }

  private async order(id: number) {
    if (!(await this.hasTable('gangnamunni_platform_payments'))) return null;
    const [rows] = (await this.db.query("SELECT * FROM gangnamunni_platform_payments WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1", [id])) as any;
    return (rows as any[])[0] ?? null;
  }

  /** booking_cancel_window_closed(): a paid PG booking cannot be cancelled in the app after 17:00 the day before. */
  private windowClosed(order: any, bookingDate: string, now = kstNow()): boolean {
    const deadline = cancelDeadline(bookingDate);
    if (!deadline || now <= deadline) return false;
    return !!order && isPgProvider(order.provider) && ['paid', 'partially_refunded'].includes(s(order.status));
  }

  private async cancelWarn(mbId: string) {
    const c = await this.booking.noshowCollect(mbId);
    return c ? noshowCancelPreview(c.limit, c.months, c.withCancel, c.rows) : null;
  }

  /** GET …/cancel-info: what booking_cancel_request.php shows before the member confirms. */
  async cancelInfo(m: BookingMember, id: number) {
    const b = await this.own(m, id);
    if (!b) throw new NotFoundException('예약을 찾을 수 없습니다.');
    if (!['pending', 'confirmed'].includes(s(b.status))) throw new ManageRefused('이미 처리된 예약입니다.', 409);
    const order = await this.order(id);
    const paidPg = !!order && isPgProvider(order.provider) && ['paid', 'partially_refunded'].includes(s(order.status));
    const amount = paidPg ? Math.max(0, n(order.amount) - n(order.refunded_amount)) : 0;
    return {
      id, dateText: detailDate(b.booking_date, b.booking_time), treatment: s(b.treatment) || '-', hospitalName: s(b.hospital_name) || '-',
      doctorName: s(b.doctor_name) || '-', bookerName: s(b.name) || '-', phone: s(b.phone) || '-',
      amount, refund: amount, paymentStatus: paidPg ? s(order.status) : '',
      windowClosed: this.windowClosed(order, dateStr(b.booking_date)),
      cancelWarn: s(b.mb_id) ? await this.cancelWarn(s(b.mb_id)) : null,
      now: kstNow(), reasons: CANCEL_REASONS,
    };
  }

  /** booking_payment_lock_for_booking(): GET_LOCK on the order's merchant_uid, so a payment approval cannot interleave. */
  private async withLock(order: any, work: (conn: DbConn) => Promise<void>) {
    if (!this.db.getConnection) throw new Error('pool connection needed');
    const conn = await this.db.getConnection();
    const key = order && s(order.merchant_uid) ? `payment:${createHash('sha256').update(s(order.merchant_uid)).digest('hex').slice(0, 48)}` : '';
    let locked = false;
    try {
      if (key) {
        const [lk] = (await conn.query('SELECT GET_LOCK(?, 5) AS acquired', [key])) as any;
        locked = n((lk as any[])[0]?.acquired) === 1;
        if (!locked) throw new ManageRefused('처리 중인 결제가 있어요. 잠시 후 다시 시도해주세요.', 409);
      }
      await work(conn);
    } finally {
      if (locked) await conn.query('SELECT RELEASE_LOCK(?)', [key]).catch(() => undefined);
      conn.release();
    }
  }

  /**
   * points_checkout_release_for_booking(): a pending order with points on hold gets them back.
   * Only the hold without a ledger refund is handled here; a hold that moved points (v2 point use) is left for the payment port (B) and logged.
   */
  private async releaseHold(id: number, reason: string) {
    try {
      const st = await this.points.allSettings();
      if (n(st.v2_schema_version) < 1) return;
      const order = await this.order(id);
      if (!order || s(order.status) !== 'pending') return;
      const [hs] = (await this.db.query("SELECT * FROM gangnamunni_point_payment_holds WHERE payment_id = ? AND status IN ('held','captured') ORDER BY id DESC LIMIT 1", [n(order.id)])) as any;
      const hold = (hs as any[])[0];
      if (!hold || s(hold.status) !== 'held') return;
      if (n(hold.points) > 0 && n(hold.use_point_id) > 0) { this.log.error(`booking #${id}: point hold #${hold.id} with ${hold.points}P needs the payment port (B); left as is (${reason})`); return; }
      await this.db.query("UPDATE gangnamunni_point_payment_holds SET status = 'released' WHERE id = ?", [n(hold.id)]);
      await this.db.query("UPDATE gangnamunni_platform_payments SET amount = COALESCE(gross_amount, amount), points_used = 0 WHERE id = ? AND status = 'pending'", [n(order.id)]);
    } catch (e) { this.log.error(`point hold release failed (booking #${id}): ${(e as Error).message}`); }
  }

  /** POST …/cancel: api/cancel_booking.php. */
  async cancel(m: BookingMember, id: number, reasonRaw: unknown) {
    const b = await this.own(m, id);
    if (!b) throw new ManageRefused('본인 예약만 취소할 수 있습니다.', 403);
    const status = s(b.status);
    if (['cancelled', 'completed', 'no_show'].includes(status)) throw new ManageRefused(`이미 ${status === 'cancelled' ? '취소' : '완료'}된 예약입니다.`);
    const reason = [...s(typeof reasonRaw === 'string' ? reasonRaw : '').trim()].slice(0, 255).join('');
    const order = await this.order(id);
    const withCancelledBy = await this.hasCol('gangnamunni_bookings', 'cancelled_by');
    const bookingDate = dateStr(b.booking_date);
    const hmsId = n(b.hms_appointment_id);

    await this.withLock(order, async (conn) => {
      const [cur] = (await conn.query('SELECT status FROM gangnamunni_bookings WHERE id = ?', [id])) as any;
      if (!ACTIVE.includes(s((cur as any[])[0]?.status))) throw new ManageRefused('이미 처리된 예약입니다. 페이지를 새로고침해주세요.');
      if (this.windowClosed(await this.order(id), bookingDate)) throw new ManageRefused('방문 하루 전 오후 5시가 지나 앱에서 취소할 수 없어요. 병원에 직접 문의해 주세요.');
      await conn.query('START TRANSACTION');
      try {
        const [res] = (await conn.query(
          `UPDATE gangnamunni_bookings SET status = 'cancelled', cancel_reason = ?, cancelled_at = NOW(), updated_at = NOW()${withCancelledBy ? ", cancelled_by = 'member'" : ''}
           WHERE id = ? AND status IN ('pending','hospital_checking','confirmed')`, [reason, id])) as any;
        if (n((res as any).affectedRows) !== 1) throw new Error('booking_update_failed');
        if (hmsId) await conn.query('UPDATE appointments SET is_completed = 2, updated_at = NOW() WHERE id = ?', [hmsId]);
        await conn.query('COMMIT');
      } catch (e) { await conn.query('ROLLBACK').catch(() => undefined); throw e; }
    });

    let refundStatus = 'not_applicable';
    let message = '';
    if (order && isPgProvider(order.provider) && ['paid', 'partially_refunded'].includes(s(order.status))) {
      refundStatus = 'not_refunded';
      message = '예약이 취소되었습니다. 환불은 관리자 확인 후 처리됩니다.';
    }
    await this.db.query('UPDATE gangnamunni_bookings SET refund_status = ?, updated_at = NOW() WHERE id = ?', [refundStatus, id]).catch((e) => this.log.error(`refund status (booking #${id}): ${(e as Error).message}`));
    await this.releaseHold(id, '예약 취소 포인트 복구');
    // points_cancel_booking(): the booking earn is revoked whether it came from the v1 or the v2 policy
    if (s(b.mb_id)) {
      try { await this.points.revokeEarn('booking', id, '예약 취소 회수'); } catch (e) { this.log.error(`point clawback (booking #${id}): ${(e as Error).message}`); }
    }
    return { success: true, message: message || '예약이 취소되었습니다.', refund_status: refundStatus };
  }

  /** GET …/change-info: what booking_change.php preselects in the booking screen. */
  async changeInfo(m: BookingMember, id: number) {
    const b = await this.own(m, id);
    if (!b) throw new NotFoundException('예약을 찾을 수 없습니다.');
    const editCount = n(b.edit_count);
    if (!ACTIVE.includes(s(b.status)) || editCount >= MAX_EDITS) throw new ManageRefused('변경할 수 없는 예약입니다.', 409);
    const today = kstNow().slice(0, 10);
    let date = dateStr(b.booking_date), time = s(b.booking_time).slice(0, 5);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || date < today) { date = today; time = ''; }
    const [name, option] = s(b.treatment).split(' · ', 2);
    return { id, hospitalId: n(b.hospital_id), date, time, treatment: s(name).trim(), optionName: s(option).trim(), phone: s(b.phone), editsLeft: MAX_EDITS - editCount, treatmentText: s(b.treatment) };
  }

  /** POST …/change: api/edit_booking.php (date/time, optionally the treatment text). */
  async change(m: BookingMember, id: number, input: { date?: unknown; time?: unknown; treatment?: unknown }) {
    const date = s(typeof input.date === 'string' ? input.date : '').trim();
    const time = s(typeof input.time === 'string' ? input.time : '').trim();
    const newTreatment = [...s(typeof input.treatment === 'string' ? input.treatment : '').trim()].slice(0, 100).join('');
    if (!date || !time) throw new ManageRefused('필수 항목을 입력해주세요.');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || date < kstNow().slice(0, 10)) throw new ManageRefused('유효한 미래 날짜를 선택해주세요.');
    if (!/^\d{2}:\d{2}$/.test(time)) throw new ManageRefused('유효한 시간을 선택해주세요.');
    if (!(await this.hasCol('gangnamunni_bookings', 'edit_count'))) throw new ManageRefused('예약 기능 업데이트가 필요합니다. 관리자에게 문의해주세요.', 503);
    const b = await this.own(m, id);
    if (!b) throw new ManageRefused('로그인 후 본인 예약만 수정할 수 있습니다.', 403);
    if (!ACTIVE.includes(s(b.status))) throw new ManageRefused('수정할 수 없는 상태의 예약입니다.');
    const editCount = n(b.edit_count);
    if (editCount >= MAX_EDITS) throw new ManageRefused(`예약 변경 가능 횟수(${MAX_EDITS}회)를 모두 사용했습니다.`);
    const old = `${dateStr(b.booking_date)} ${s(b.booking_time)}`;
    const treatmentChanged = newTreatment !== '' && newTreatment !== s(b.treatment);
    let memo = `변경: ${old} → ${date} ${time}`;
    if (treatmentChanged) memo += ` / 시술: ${s(b.treatment)} → ${newTreatment}`;
    const order = await this.order(id);
    let wasConfirmed = false;

    await this.withLock(order, async (conn) => {
      const [cur] = (await conn.query('SELECT status FROM gangnamunni_bookings WHERE id = ?', [id])) as any;
      const st = s((cur as any[])[0]?.status);
      if (!ACTIVE.includes(st)) throw new ManageRefused('수정할 수 없는 상태의 예약입니다.');
      const fresh = await this.order(id); // re-read under the lock: a payment approval may have finished meanwhile
      if (fresh && ['paid', 'partially_refunded'].includes(s(fresh.status))) throw new ManageRefused('이미 결제된 예약은 일정을 변경할 수 없어요. 취소 후 다시 예약해주세요.');
      wasConfirmed = st === 'confirmed';
      const [res] = (await conn.query(
        `UPDATE gangnamunni_bookings SET status = IF(status = 'confirmed', 'hospital_checking', status), booking_date = ?, booking_time = ?${treatmentChanged ? ', treatment = ?' : ''},
           edit_count = edit_count + 1, memo = CONCAT(IFNULL(memo, ''), '\\n[', NOW(), '] ', ?), updated_at = NOW() WHERE id = ? AND status IN ('pending','hospital_checking','confirmed')`,
        treatmentChanged ? [date, time, newTreatment, memo, id] : [date, time, memo, id])) as any;
      if (n((res as any).affectedRows) !== 1) throw new ManageRefused('수정 처리 중 오류가 발생했습니다.', 500);
    });

    if (wasConfirmed) await this.releaseHold(id, '예약 변경 포인트 복구');
    const hmsId = n(b.hms_appointment_id);
    if (hmsId) await this.db.query('UPDATE appointments SET opd_date = ? WHERE id = ?', [`${date} ${time}:00`, hmsId]).catch((e) => this.log.error(`HMS opd_date (booking #${id}): ${(e as Error).message}`));
    const editsLeft = Math.max(0, MAX_EDITS - (editCount + 1));
    return { success: true, message: `예약이 변경되었습니다. (변경 가능 ${editsLeft}회 남음)`, new_date: date, new_time: time, edits_left: editsLeft };
  }
}
