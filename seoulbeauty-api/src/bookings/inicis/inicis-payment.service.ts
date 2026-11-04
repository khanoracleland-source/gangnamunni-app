import { Inject, Injectable, Logger } from '@nestjs/common';
import { createHash, randomBytes } from 'node:crypto';
import { DbConn, DbPool } from '../../db/db.module';
import { BookingMember } from '../bookings.service';
import { kstNow } from '../bookings.logic';
import { BookingPaymentService, PaymentRefused } from '../booking-payment.service';
import { approve, InicisFetch, InicisUnknownOutcome, netCancel } from './inicis.client';
import { chkFake, InicisConfig, inicisAllowedFor, inicisConfig, inicisProvider, isIdc } from './inicis.config';
import { PAY_DB } from './pay-db';

export const INICIS_FETCH = Symbol('INICIS_FETCH');

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;
/** BK_<14 digits>_<booking id>_<12 hex>: what createOrder() issues. Anything else is never looked up. */
const MERCHANT_RE = /^BK_\d{14}_\d{1,9}_[0-9a-f]{12}$/;
const SDK_URL = 'https://paypro.inicis.com/std/payment/js/INIPayPro_v2.js';

function clipBytes(v: string, max: number): string {
  let out = '';
  for (const ch of v) { if (Buffer.byteLength(out + ch) > max) break; out += ch; }
  return out;
}

/** The booking_payment_lock() key, shared with PHP (booking_payment_lock) and BookingManageService.withLock. */
export const paymentLockKey = (merchant: string) => `payment:${createHash('sha256').update(merchant).digest('hex').slice(0, 48)}`;

/**
 * 이니시스 카드 결제 (INIPayPro). prepare() hands the browser the signed SDK parameters; the browser then returns the
 * authentication result to POST /v1/payments/inicis/return, which approves it server-side and moves the ledger order
 * to `paid` exactly as booking_payment_confirm() does in PHP (same lock, events, status history, attempts).
 */
@Injectable()
export class InicisPaymentService {
  private readonly log = new Logger(InicisPaymentService.name);
  constructor(
    @Inject(PAY_DB) private readonly pay: DbPool | null,
    private readonly payments: BookingPaymentService,
    /** The global fetch in production; a fake Inicis in tests. */
    @Inject(INICIS_FETCH) private readonly fetcher: InicisFetch,
  ) {}

  private base(cfg: InicisConfig | null): string {
    try { return new URL(cfg?.publicBase ?? '').pathname.replace(/\/+$/, '') || ''; } catch { return '/next'; }
  }

  /** POST …/payment/inicis/prepare: the signed parameters for INIPayPro.requestPayment(). Nothing is written. */
  async prepare(m: BookingMember, id: number, device: unknown) {
    const cfg = inicisConfig();
    if (!cfg || !BookingPaymentService.liveAvailable(process.env, m.id)) throw new PaymentRefused('결제 서비스를 이용할 수 없습니다.', 'PAYMENT_UNAVAILABLE', 503);
    const b = await this.payments.own(m, id);
    if (!b) throw new PaymentRefused('예약을 찾을 수 없거나 접근 권한이 없습니다.', 'BOOKING_NOT_FOUND', 404);
    if (s(b.status) !== 'confirmed') throw new PaymentRefused('병원이 예약을 확정한 뒤에 결제할 수 있습니다.', 'BOOKING_NOT_CONFIRMED', 409);
    let order = await this.payments.order(id);
    if (!order) { await this.payments.createOrder(id); order = await this.payments.order(id); }
    if (!order || s(order.status) !== 'pending') throw new PaymentRefused('결제할 수 없는 주문 상태입니다.', 'INVALID_ORDER_STATE', 409);
    if (n(order.points_used) > 0) throw new PaymentRefused('포인트를 사용한 결제는 아직 지원되지 않습니다.', 'POINTS_NOT_SUPPORTED', 409);
    const amount = n(order.amount);
    const oid = s(order.merchant_uid);
    if (!(amount > 0) || !MERCHANT_RE.test(oid) || Buffer.byteLength(oid) > 40) throw new PaymentRefused('결제 주문 정보가 올바르지 않습니다.', 'INVALID_ORDER', 409);
    const mobile = device === 'MOBILE';
    const timestamp = String(Date.now());
    const prefix = this.base(cfg);
    return {
      sdkUrl: SDK_URL,
      mode: cfg.mode,
      params: {
        P_MID: cfg.mid, P_OID: oid, P_PAY_TYPE: 'CARD', P_DEVICE_TYPE: mobile ? 'MOBILE' : 'WEB', P_IDCCODE: 'Y',
        P_AMT: String(amount), P_GOODS: clipBytes(s(b.treatment).trim() || '예약 결제', 40) || '예약 결제',
        P_UNAME: clipBytes((s(b.name) || m.name || '고객').trim(), 30) || '고객',
        P_NEXT_URL: `${cfg.publicBase}/api/v1/payments/inicis/return`,
        P_CLOSE_URL: `${cfg.publicBase}/mypage/bookings/${id}/payment`,
        P_CHARSET: 'UTF-8', P_NOTI: String(id), P_TIMESTAMP: timestamp, P_CHKFAKE: chkFake(amount, oid, timestamp, cfg.hashkey),
        // iOS card apps come back through the app's URL scheme (manual: P_RESERVED app_scheme).
        P_RESERVED: JSON.stringify(mobile ? { app_scheme: `${cfg.appScheme}://payment` } : {}),
      },
      closePath: `${prefix}/mypage/bookings/${id}/payment`,
    };
  }

  private async attempt(conn: DbConn, order: any, provider: string, key: string, amount: number, outcome: 'received' | 'paid' | 'refused' | 'rejected' | 'pending', reason: string | null) {
    try {
      await conn.query(
        `INSERT INTO gangnamunni_payment_attempts (payment_id, merchant_uid, provider, pg_payment_key, amount, outcome, reason) VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE outcome = VALUES(outcome), reason = VALUES(reason)`,
        [n(order.id), s(order.merchant_uid), provider, key.slice(0, 200), Math.max(0, amount), outcome, reason ? reason.slice(0, 100) : null]);
    } catch (e) { this.log.error(`attempt save failed (${s(order.merchant_uid)}): ${(e as Error).message}`); }
  }

  /** KST "YYYY-MM-DD HH:MM:SS" from P_APPL_DT (yyyymmdd) + P_APPL_TM (hhmmss); the current time if it is missing or in the future. */
  private paidAt(dt: string, tm: string): string {
    const m = /^(\d{4})(\d{2})(\d{2})$/.exec(dt), t = /^(\d{2})(\d{2})(\d{2})$/.exec(tm);
    const now = kstNow();
    if (!m || !t) return now;
    const v = `${m[1]}-${m[2]}-${m[3]} ${t[1]}:${t[2]}:${t[3]}`;
    return v > now ? now : v;
  }

  /**
   * POST /v1/payments/inicis/return (P_NEXT_URL). The browser posts the authentication result here cross-site, so there is no
   * session: the order is found by P_OID and everything is checked against the ledger. Returns the path to redirect to.
   */
  async handleReturn(body: Record<string, unknown>): Promise<string> {
    const cfg = inicisConfig();
    const prefix = this.base(cfg);
    const str = (k: string) => (typeof body?.[k] === 'string' ? (body[k] as string).slice(0, 500) : '');
    const oid = str('P_OID');
    const list = (code: string) => `${prefix}/mypage/bookings?pay_error=${code}`;
    if (!cfg || !this.pay?.getConnection || !MERCHANT_RE.test(oid)) return list('UNAVAILABLE');
    const provider = inicisProvider(cfg);
    const conn = await this.pay.getConnection();
    const lockKey = paymentLockKey(oid);
    let locked = false;
    try {
      const [lk] = (await conn.query('SELECT GET_LOCK(?, 5) AS acquired', [lockKey])) as any;
      locked = n((lk as any[])[0]?.acquired) === 1;
      if (!locked) return list('BUSY');
      return await this.confirm(conn, cfg, provider, oid, str, prefix);
    } catch (e) {
      this.log.error(`return failed (${oid}): ${(e as Error).message}`);
      return list('ERROR');
    } finally {
      if (locked) await conn.query('SELECT RELEASE_LOCK(?)', [lockKey]).catch(() => undefined);
      conn.release();
    }
  }

  private async confirm(conn: DbConn, cfg: InicisConfig, provider: string, oid: string, str: (k: string) => string, prefix: string): Promise<string> {
    const [os] = (await conn.query("SELECT * FROM gangnamunni_platform_payments WHERE merchant_uid = ? AND payment_type = 'booking' LIMIT 1", [oid])) as any;
    const order = (os as any[])[0];
    if (!order) return `${prefix}/mypage/bookings?pay_error=UNKNOWN_ORDER`;
    const bookingId = n(order.booking_id);
    const payPage = (code: string) => `${prefix}/mypage/bookings/${bookingId}/payment?pay_error=${code}`;
    const success = `${prefix}/mypage/bookings/${bookingId}/payment/success`;
    const authTid = str('P_AUTH_TID');
    const status = str('P_STATUS');
    const key = authTid || `noauth:${randomBytes(8).toString('hex')}`;
    const amtRaw = str('P_AMT');
    const amount = n(order.amount);

    if (['paid', 'partially_refunded'].includes(s(order.status))) return success; // reload / double submit
    await this.attempt(conn, order, provider, key, /^\d+$/.test(amtRaw) ? Number(amtRaw) : 0, 'received', null);
    if (status !== '00') {
      await this.attempt(conn, order, provider, key, amount, 'rejected', `AUTH_${status || 'NONE'}`);
      return payPage('AUTH_FAILED');
    }
    const refuse = async (reason: string) => { await this.attempt(conn, order, provider, key, amount, 'refused', reason); return payPage(reason); };
    const idc = str('P_IDCNAME');
    if (!inicisAllowedFor(cfg, s(order.patient_mb_id))) return refuse('UNAVAILABLE');
    if (s(order.status) !== 'pending') return refuse('ORDER_STATE');
    if (!/^\d+$/.test(amtRaw) || Number(amtRaw) !== amount || amount <= 0) return refuse('AMOUNT_MISMATCH');
    if (str('P_MID') !== cfg.mid) return refuse('MID_MISMATCH');
    if (!isIdc(idc) || !authTid) return refuse('AUTH_INVALID');
    if (n(order.points_used) > 0) return refuse('POINTS_NOT_SUPPORTED');
    const [bs] = (await conn.query('SELECT status FROM gangnamunni_bookings WHERE id = ? LIMIT 1', [bookingId])) as any;
    if (s((bs as any[])[0]?.status) !== 'confirmed') return refuse('BOOKING_NOT_CONFIRMED');

    const cancel = async (reason: string) => {
      try { await netCancel(cfg, { idc, authTid, amt: amount, oid, reason }, this.fetcher); return true; }
      catch (e) { this.log.error(`[inicis][NET_CANCEL_FAILED] ${oid} auth=${authTid}: ${(e as Error).message}: check the Inicis admin and refund by hand`); return false; }
    };
    let res: Record<string, string>;
    try { res = await approve(cfg, { idc, authTid, amt: amount }, this.fetcher); }
    catch (e) {
      // Unknown outcome (timeout / network): Inicis may have approved, so net-cancel it.
      this.log.error(`approval unresolved (${oid}): ${(e as InicisUnknownOutcome).message}`);
      await cancel('approval result unknown');
      await this.attempt(conn, order, provider, key, amount, 'pending', 'UNRESOLVED');
      return payPage('UNRESOLVED');
    }
    if (res.P_STATUS !== '00') {
      await this.attempt(conn, order, provider, key, amount, 'rejected', `APPROVE_${res.P_STATUS}`);
      return payPage('REJECTED');
    }
    const tid = s(res.P_APPL_TID);
    if (res.P_OID !== oid || res.P_AMT !== String(amount) || res.P_MID !== cfg.mid || !tid || tid.length > 191) {
      this.log.error(`approval result mismatch (${oid}): oid=${res.P_OID} amt=${res.P_AMT}`);
      await cancel('approval result mismatch');
      await this.attempt(conn, order, provider, key, amount, 'refused', 'RESULT_MISMATCH');
      return payPage('RESULT_MISMATCH');
    }
    try {
      await this.finalize(conn, order, provider, tid, amount, this.paidAt(s(res.P_APPL_DT), s(res.P_APPL_TM)), res);
    } catch (e) {
      this.log.error(`[inicis][FINALIZE_FAILED] ${oid} tid=${tid}: ${(e as Error).message}`);
      const cancelled = await cancel('ledger update failed');
      await this.attempt(conn, order, provider, key, amount, 'pending', cancelled ? 'FINALIZE_FAILED' : 'FINALIZE_FAILED_UNCANCELLED');
      // Not cancelled = the card is charged but the ledger has no record: tell the user NOT to pay again.
      return payPage(cancelled ? 'FINALIZE_FAILED' : 'FINALIZE_FAILED_UNCANCELLED');
    }
    await this.attempt(conn, order, provider, key, amount, 'paid', 'CONFIRMED');
    return success;
  }

  /** platform_payment_mark_verified(): one transaction, the order row locked, evidence row + status history written with the update. */
  private async finalize(conn: DbConn, order: any, provider: string, tid: string, amount: number, paidAt: string, res: Record<string, string>) {
    const hash = createHash('sha256').update(JSON.stringify({ oid: s(order.merchant_uid), tid, amt: amount, dt: s(res.P_APPL_DT), tm: s(res.P_APPL_TM), type: s(res.P_TYPE) })).digest('hex');
    await conn.query('START TRANSACTION');
    try {
      const [ps] = (await conn.query('SELECT id, amount, status FROM gangnamunni_platform_payments WHERE id = ? FOR UPDATE', [n(order.id)])) as any;
      const p = (ps as any[])[0];
      if (!p || n(p.amount) !== amount || s(p.status) !== 'pending') throw new Error('Order is no longer pending or the amount changed.');
      await conn.query(
        "INSERT INTO gangnamunni_payment_events (payment_id, provider, provider_event_id, event_type, amount, payload_sha256, provider_occurred_at) VALUES (?, ?, ?, 'payment_verified', ?, ?, ?)",
        [n(order.id), provider, `paid:${tid}`, amount, hash, paidAt]);
      const [upd] = (await conn.query(
        "UPDATE gangnamunni_platform_payments SET provider = ?, provider_transaction_id = ?, verified_amount = ?, verified_at = NOW(), verification_source = 'server_query', status = 'paid', paid_at = ? WHERE id = ? AND status = 'pending'",
        [provider, tid, amount, paidAt, n(order.id)])) as any;
      if (n((upd as any).affectedRows) !== 1) throw new Error('Payment verification update failed.');
      await conn.query(
        "INSERT INTO gangnamunni_payment_status_history (payment_id, old_status, new_status, actor_type, actor_id, reason) VALUES (?, 'pending', 'paid', 'provider', ?, 'Server-to-server verification')",
        [n(order.id), provider]);
      await conn.query('COMMIT');
    } catch (e) { await conn.query('ROLLBACK').catch(() => undefined); throw e; }
  }
}
