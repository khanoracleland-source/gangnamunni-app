import { HttpException, Inject, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { randomBytes } from 'node:crypto';
import { DB, DbConn, DbPool } from '../db/db.module';
import { PointsService } from '../points/points.service';
import { isPgProvider, isTestProvider, kstNow, paymentTierPoint } from './bookings.logic';
import { BookingMember } from './bookings.service';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;
const digits = (v: unknown) => s(v).replace(/[^0-9]/g, '');

export class PaymentRefused extends HttpException {
  constructor(message: string, code: string, status = 400) { super({ success: false, code, message }, status); }
}

/** 예약 결제 (booking_payment.php, booking_payment_mock_complete.php, booking_payment_success.php) — the parts that work without a PG contract. */
@Injectable()
export class BookingPaymentService {
  private readonly log = new Logger(BookingPaymentService.name);
  constructor(@Inject(DB) private readonly db: DbPool, private readonly points: PointsService) {}

  /** booking_payment_live_enabled() && methods && gateway: all server settings (env here). Toss itself still lives in PHP. */
  static liveAvailable(): boolean {
    const methods = (process.env.BOOKING_PAYMENT_METHODS || '').split(',').map((m) => m.trim().toLowerCase()).filter((m) => ['card', 'bank', 'naverpay', 'kakaopay', 'tosspay'].includes(m));
    return process.env.PG_PAYMENTS_ENABLED === '1' && methods.length > 0 && !!process.env.TOSS_CLIENT_KEY && !!process.env.TOSS_SECRET_KEY;
  }
  static previewEnabled(): boolean { return (process.env.BOOKING_PAYMENT_PREVIEW_ENABLED ?? '1') === '1'; }

  private static phone(v: unknown): string {
    const d = digits(v);
    if (d.length === 11) return `${d.slice(0, 3)}-${d.slice(3, 7)}-${d.slice(7)}`;
    if (d.length === 10) return `${d.slice(0, 3)}-${d.slice(3, 6)}-${d.slice(6)}`;
    return s(v);
  }
  private static koreanDate(date: unknown, time: unknown, short = false): string {
    const d = s(date).slice(0, 10), t = s(time).slice(0, 5);
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(d), tm = /^(\d{2}):(\d{2})$/.exec(t);
    if (!m || !tm) return `${d} ${t}`.trim();
    const dow = ['일', '월', '화', '수', '목', '금', '토'][new Date(Date.UTC(+m[1], +m[2] - 1, +m[3])).getUTCDay()];
    const h = Number(tm[1]);
    const day = short ? `${m[1].slice(2)}.${m[2]}.${m[3]}` : `${Number(m[2])}월 ${Number(m[3])}일`;
    return `${day} (${dow}) ${h < 12 ? '오전' : '오후'} ${h % 12 || 12}:${tm[2]}`;
  }

  private async own(m: BookingMember, id: number) {
    const [rows] = (await this.db.query(
      'SELECT b.*, h.name AS h_name, h.profile_image_url, h.address AS hospital_address FROM gangnamunni_bookings b LEFT JOIN gangnamunni_hospitals h ON h.id = b.hospital_id WHERE b.id = ? LIMIT 1', [id])) as any;
    const b = (rows as any[])[0];
    if (!b) return null;
    const mine = (m.id && s(b.mb_id) === m.id) || (!s(b.mb_id) && digits(m.phone) !== '' && digits(b.phone) === digits(m.phone));
    return mine ? b : null;
  }
  private async hasTable(name: string): Promise<boolean> {
    try { const [rows] = (await this.db.query('SHOW TABLES LIKE ?', [name])) as any; return (rows as any[]).length > 0; } catch { return false; }
  }
  private async order(id: number) {
    const [rows] = (await this.db.query("SELECT * FROM gangnamunni_platform_payments WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1", [id])) as any;
    return (rows as any[])[0] ?? null;
  }
  private async hasPreview(id: number): Promise<boolean> {
    try { const [rows] = (await this.db.query('SELECT booking_id FROM gangnamunni_booking_payment_previews WHERE booking_id = ? LIMIT 1', [id])) as any; return (rows as any[]).length > 0; } catch { return false; }
  }

  /** platform_payment_create_booking(): one pending order per booking, priced from the booking row (FOR UPDATE). */
  private async createOrder(id: number) {
    if (!this.db.getConnection) throw new Error('pool connection needed');
    const conn: DbConn = await this.db.getConnection();
    try {
      await conn.query('START TRANSACTION');
      try {
        const [bs] = (await conn.query('SELECT id, hospital_id, hospital_name, mb_id, name, phone, treatment, price FROM gangnamunni_bookings WHERE id = ? FOR UPDATE', [id])) as any;
        const b = (bs as any[])[0];
        if (!b || n(b.hospital_id) <= 0 || n(b.price) <= 0) throw new Error('Booking or server-side price is invalid.');
        const [ex] = (await conn.query("SELECT id, merchant_uid, status FROM gangnamunni_platform_payments WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1", [id])) as any;
        if ((ex as any[])[0]) { await conn.query('COMMIT'); return (ex as any[])[0]; }
        const merchantUid = `BK_${kstNow().replace(/[^0-9]/g, '')}_${id}_${randomBytes(6).toString('hex')}`;
        const [ins] = (await conn.query(
          `INSERT INTO gangnamunni_platform_payments (payment_type, funds_recipient, booking_id, hospital_id, hospital_name, patient_mb_id, patient_name, patient_phone, treatment_name, amount, currency, merchant_uid, status)
           VALUES ('booking', 'hospital', ?, ?, ?, ?, ?, ?, ?, ?, 'KRW', ?, 'pending')`,
          [id, n(b.hospital_id), s(b.hospital_name), s(b.mb_id), s(b.name), s(b.phone), s(b.treatment), n(b.price), merchantUid])) as any;
        await conn.query('COMMIT');
        return { id: n(ins.insertId), merchant_uid: merchantUid, status: 'pending' };
      } catch (e) { await conn.query('ROLLBACK').catch(() => undefined); throw e; }
    } finally { conn.release(); }
  }

  /** GET …/payment: booking_payment.php data. The order is created here (like the page did) when the booking is confirmed. */
  async info(m: BookingMember, id: number) {
    const b = await this.own(m, id);
    if (!b) throw new NotFoundException('예약을 찾을 수 없거나 접근 권한이 없습니다.');
    const status = s(b.status);
    const ledgerReady = await this.hasTable('gangnamunni_platform_payments');
    let order: any = null;
    let orderError = '';
    if (ledgerReady) {
      order = await this.order(id);
      if (!order && status === 'confirmed') {
        try { await this.createOrder(id); order = await this.order(id); } catch (e) { this.log.error(`order create failed (booking #${id}): ${(e as Error).message}`); orderError = '결제 주문을 만들지 못했습니다. 병원에 문의해주세요.'; }
      }
    }
    const orderStatus = s(order?.status);
    const isPaid = ['paid', 'partially_refunded'].includes(orderStatus);
    const amount = order ? n(order.amount) : n(b.price);
    const pointsApplied = n(order?.points_used);
    const gross = n(order?.gross_amount) > 0 ? n(order.gross_amount) : amount + pointsApplied;
    const expectedPoints = await this.expectedPoints(gross);
    const address = s(b.hospital_address).trim();
    const parts = address.split(/\s+/);
    const labels: Record<string, string> = { pending: '예약 요청 접수', hospital_checking: '병원 확인 중', confirmed: '결제 대기', completed: '시술완료', cancelled: '예약취소', no_show: '노쇼' };
    return {
      booking: {
        id, status, statusText: labels[status] ?? status, hospitalName: s(b.h_name) || s(b.hospital_name), hospitalArea: parts.length >= 2 ? `${parts[0]} ${parts[1]}` : address, hospitalAddress: address,
        treatment: s(b.treatment) || '-', bookerName: s(b.name) || m.name, phone: BookingPaymentService.phone(s(b.phone) || m.phone), dateText: BookingPaymentService.koreanDate(b.booking_date, b.booking_time),
        logo: s(b.profile_image_url).trim(),
      },
      ledgerReady, orderError, gross: Math.max(0, gross),
      order: order ? { status: orderStatus, provider: s(order.provider), amount, gross, pointsUsed: pointsApplied, isTest: isTestProvider(order.provider) } : null,
      isConfirmed: status === 'confirmed', isPaid, preview: await this.hasPreview(id),
      liveAvailable: BookingPaymentService.liveAvailable(), previewAvailable: BookingPaymentService.previewEnabled(),
      points: { v2: expectedPoints !== null, expected: expectedPoints ?? 0, useReady: false, max: 0, balance: 0 },
    };
  }

  private async expectedPoints(gross: number): Promise<number | null> {
    try {
      const cfg = await this.points.allSettings();
      if (!(n(cfg.enabled) === 1 && n(cfg.v2_schema_version) >= 1 && n(cfg.v2_policy_enabled) === 1)) return null;
      const v = (k: string, d: number) => (cfg[k] === undefined ? d : n(cfg[k]));
      return paymentTierPoint(gross, { t1Under: v('v2_pay_tier1_under', 50000), t1: v('v2_pay_tier1_point', 3000), t2Under: v('v2_pay_tier2_under', 100000), t2: v('v2_pay_tier2_point', 5000), t3: v('v2_pay_tier3_point', 10000) });
    } catch { return null; }
  }

  /** POST …/payment/preview: booking_payment_mock_complete.php — "결제 없이 계속하기", outside the financial ledger. */
  async preview(m: BookingMember, id: number) {
    const b = await this.own(m, id);
    if (!b) throw new PaymentRefused('예약을 찾을 수 없거나 접근 권한이 없습니다.', 'BOOKING_NOT_FOUND', 404);
    if (s(b.status) !== 'confirmed') throw new PaymentRefused('병원이 예약을 확정한 뒤에 결제할 수 있습니다.', 'BOOKING_NOT_CONFIRMED', 409);
    try {
      await this.createOrder(id);
      const order = await this.order(id);
      if (!order || !s(order.merchant_uid)) throw new Error('Payment order not found.');
      if (['paid', 'partially_refunded'].includes(s(order.status))) return { success: true, redirect: `/mypage/bookings/${id}/payment/success` };
      if (s(order.status) !== 'pending') throw new PaymentRefused('결제할 수 없는 주문 상태입니다.', 'INVALID_ORDER_STATE', 409);
      if (!BookingPaymentService.previewEnabled()) throw new PaymentRefused('결제 없이 계속하기가 비활성화되었습니다.', 'PREVIEW_DISABLED', 403);
      await this.recordPreview(id);
      return { success: true, redirect: `/mypage/bookings/${id}/payment/success` };
    } catch (e) {
      if (e instanceof PaymentRefused) throw e;
      this.log.error(`preview (booking #${id}): ${(e as Error).message}`);
      throw new PaymentRefused('임시 결제 완료 처리에 실패했습니다. 잠시 후 다시 시도해주세요.', 'MOCK_PAYMENT_FAILED', 500);
    }
  }

  /** booking_payment_record_preview(): the booking must still be confirmed and the order still pending (row-locked). */
  private async recordPreview(id: number) {
    if (!this.db.getConnection) throw new Error('pool connection needed');
    const conn: DbConn = await this.db.getConnection();
    try {
      await conn.query('START TRANSACTION');
      try {
        const [bs] = (await conn.query('SELECT status FROM gangnamunni_bookings WHERE id = ? FOR UPDATE', [id])) as any;
        if (s((bs as any[])[0]?.status) !== 'confirmed') throw new Error('Booking is no longer confirmed.');
        const [os] = (await conn.query("SELECT status FROM gangnamunni_platform_payments WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1", [id])) as any;
        if (s((os as any[])[0]?.status) !== 'pending') throw new Error('Order is no longer pending.');
        await conn.query('INSERT INTO gangnamunni_booking_payment_previews (booking_id) VALUES (?) ON DUPLICATE KEY UPDATE booking_id = booking_id', [id]);
        await conn.query('COMMIT');
      } catch (e) { await conn.query('ROLLBACK').catch(() => undefined); throw e; }
    } finally { conn.release(); }
  }

  /** GET …/payment/success: booking_payment_success.php. */
  async success(m: BookingMember, id: number) {
    const b = await this.own(m, id);
    if (!b) throw new NotFoundException('예약을 찾을 수 없거나 접근 권한이 없습니다.');
    let title = '결제 확인 중', description = '결제가 아직 확인되지 않았습니다. 결제 화면에서 상태를 다시 확인해주세요.', paidFinal = false;
    const order = (await this.hasTable('gangnamunni_platform_payments')) ? await this.order(id) : null;
    const preview = await this.hasPreview(id);
    if (order && ['paid', 'partially_refunded'].includes(s(order.status))) {
      paidFinal = true;
      if (isTestProvider(order.provider)) { title = '테스트 결제 완료'; description = '테스트 결과이며 실제 결제·정산에 포함되지 않습니다.'; }
      else { title = '예약이 확정되었어요!'; description = '결제가 완료되어 예약이 최종 확정되었습니다\n예약 내역에서 상세 정보를 확인할 수 있습니다'; }
    } else if (order && s(order.status) === 'refunded') { title = '환불 완료'; description = '환불된 결제입니다. 예약 내역을 확인해주세요.'; }
    else if (preview) { title = '결제 없이 계속했어요'; description = '실제 결제는 이루어지지 않았으며 결제·정산 내역에 포함되지 않습니다.'; }
    const treatment = s(b.treatment) || '-';
    return {
      title, description, paidFinal, preview, isPg: !!order && isPgProvider(order.provider),
      hospitalName: s(b.h_name) || s(b.hospital_name), treatment: [...treatment].length > 24 ? [...treatment].slice(0, 24).join('') + '...' : treatment,
      dateText: BookingPaymentService.koreanDate(b.booking_date, b.booking_time, true),
      booker: [s(b.name), digits(b.phone) || s(b.phone)].filter(Boolean).join(' / '), bookingNo: s(b.booking_no ?? b.reservation_no ?? b.id),
    };
  }
}
