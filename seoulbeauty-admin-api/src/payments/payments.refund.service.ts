import { BadRequestException, ConflictException, HttpException, Inject, Injectable, Logger, NotFoundException, Optional, ServiceUnavailableException } from '@nestjs/common';
import { ModuleRef } from '@nestjs/core';
import { AdminScope, paymentsFeesEnabled } from '../admin-auth/admin.guard';
import { BookingNotifyService } from '../bookings/notify/booking-notify.service';
import { parsePositiveInt, scopeClause } from '../bookings/bookings.logic';
import { DB, Db, DB_RW, RwConnection, RwDbProvider } from '../db/db.module';
import { isMissingObject } from '../hospitals/hospitals.treatments.logic';
import { PAY_PER_PAGE, PaymentItem, toItem } from './payments.logic';
import {
  evidenceHash, InicisRefundConfig, inicisRefundConfig, maskTid, parseInicisAnswer, parseRefundBody, providerMatchesMode, refundBlock, refundableAmount, RefundBlock, refundForm,
  refundLockKey, refundUrl,
} from './payments.refund.logic';

export interface RefundActor { id: number; name: string }
export interface RefundHistoryItem { id: number; paymentId: number; bookingId: number; amount: number; actorName: string; status: string; reason: string; createdAt: string; patientName: string; hospitalName: string }
export interface PaymentDetail {
  item: PaymentItem;
  /** Price of the treatment (what the customer paid plus the points that covered part of it) and the points; points only when the points column is read. */
  grossAmount: number; pointsUsed: number; method: string; provider: string; tid: string;
  refund: { enabled: boolean; allowed: boolean; block: RefundBlock | null; amount: number };
  history: RefundHistoryItem[];
}

const s = (v: unknown): string => (v == null ? '' : String(v));
const rowsOf = (r: unknown): Record<string, unknown>[] => (Array.isArray(r) ? (r as Record<string, unknown>[]) : []);
const POINTS_ON = process.env.ADMIN_PAYMENTS_POINTS === '1';
const UNAVAILABLE_CODES = ['ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR', 'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST'];
const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040, 1054];

const detailColumns = (): string =>
  `p.id, p.booking_id, p.hospital_id, p.hospital_name, p.patient_name, p.patient_phone, p.treatment_name, p.amount, p.refunded_amount, p.status, p.paid_at, p.refunded_at,
   p.provider, p.provider_transaction_id, p.merchant_uid, b.status AS booking_status, b.booking_date, b.booking_time${paymentsFeesEnabled() ? ', p.pay_method' : ''}${POINTS_ON ? ', p.points_used' : ''}`;
const FROM = 'FROM gangnamunni_platform_payments p LEFT JOIN gangnamunni_bookings b ON b.id = p.booking_id';
const ONLY_HOSPITAL_BOOKINGS = "p.payment_type = 'booking' AND p.funds_recipient = 'hospital'";

const candidateOf = (r: Record<string, unknown>) => ({
  provider: s(r.provider), tid: s(r.provider_transaction_id), status: s(r.status), amount: Number(r.amount) || 0, refundedAmount: Number(r.refunded_amount) || 0,
  bookingStatus: s(r.booking_status), payMethod: s(r.pay_method),
});

/**
 * One payment's detail and the refund of a booking payment (full refund only) through the Inicis INIAPI.
 * The refund is recorded the way the PHP admin records it (request row, evidence event, payment row, booking row, audit row) under the same payment lock,
 * so the two admins can never refund the same payment at once. A refund the gateway answered clearly is final; one whose answer is unknown stays "pending" and
 * blocks further refunds of that payment until it is checked in the Inicis console.
 */
@Injectable()
export class PaymentRefundService {
  private readonly log = new Logger('PaymentRefund');
  constructor(
    @Inject(DB) private readonly ro: Db,
    @Optional() @Inject(DB_RW) private readonly rw: RwDbProvider | null,
    @Optional() private readonly modules: ModuleRef | null,
  ) {}

  /** The member push of the bookings module (the same one the booking pages use); not found = no push, the refund itself is unaffected. */
  private notifier(): BookingNotifyService | null {
    try { return this.modules?.get(BookingNotifyService, { strict: false }) ?? null; } catch { return null; }
  }

  private async read(sql: string, values: unknown[]): Promise<Record<string, unknown>[]> {
    try {
      const [rows] = (await this.ro.query(sql, values)) as any;
      return rowsOf(rows);
    } catch (e) {
      if (isMissingObject(e)) { this.log.warn(`payments tables unavailable: ${(e as { code?: string })?.code}`); throw new ServiceUnavailableException({ error: 'payments_unavailable' }); }
      throw e;
    }
  }

  private refundEnabled(): boolean { return process.env.ADMIN_PAYMENTS_REFUND === '1' && inicisRefundConfig() !== null; }

  async detail(scope: AdminScope, rawId: string): Promise<PaymentDetail> {
    const id = parsePositiveInt(rawId);
    if (id === null) throw new NotFoundException({ error: 'not_found' });
    const sc = scopeClause(scope, 0, 'p.hospital_id');
    const rows = await this.read(`SELECT ${detailColumns()} ${FROM} WHERE p.id = ? AND ${ONLY_HOSPITAL_BOOKINGS} AND ${sc.sql} LIMIT 1`, [id, ...sc.values]);
    const r = rows[0];
    if (!r) throw new NotFoundException({ error: 'not_found' });
    const c = candidateOf(r);
    const block = refundBlock(c);
    const points = POINTS_ON ? Number(r.points_used) || 0 : 0;
    let history: RefundHistoryItem[] = [];
    try { history = await this.history(scope, { paymentId: id, page: 1 }).then((h) => h.items); } catch (e) { if (!(e instanceof ServiceUnavailableException)) throw e; }
    return {
      item: toItem(r), grossAmount: c.amount + points, pointsUsed: points, method: c.payMethod, provider: c.provider, tid: maskTid(c.tid),
      refund: { enabled: this.refundEnabled(), allowed: block === null, block, amount: block === null ? refundableAmount(c) : 0 },
      history,
    };
  }

  /** Newest first. With paymentId only that payment's rows; without, every refund in the scope (a page of PAY_PER_PAGE). */
  async history(scope: AdminScope, q: { paymentId?: number; page: number }): Promise<{ items: RefundHistoryItem[]; page: number; perPage: number; hasMore: boolean }> {
    const sc = scopeClause(scope, 0, 'p.hospital_id');
    const where = [ONLY_HOSPITAL_BOOKINGS, sc.sql];
    const values: unknown[] = [...sc.values];
    if (q.paymentId) { where.push('a.payment_id = ?'); values.push(q.paymentId); }
    const rows = await this.read(
      `SELECT a.id, a.payment_id, a.booking_id, a.amount, a.actor_name, a.status, a.created_at, p.patient_name, p.hospital_name,
              (SELECT r.reason FROM gangnamunni_payment_refund_requests r WHERE r.payment_id = a.payment_id AND r.status = 'succeeded'
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, r.completed_at, a.created_at)) ASC LIMIT 1) AS reason
         FROM gangnamunni_refund_audit a JOIN gangnamunni_platform_payments p ON p.id = a.payment_id
        WHERE ${where.join(' AND ')} ORDER BY a.created_at DESC, a.id DESC LIMIT ? OFFSET ?`,
      [...values, PAY_PER_PAGE + 1, (q.page - 1) * PAY_PER_PAGE]);
    const items = rows.slice(0, PAY_PER_PAGE).map((r) => ({
      id: Number(r.id), paymentId: Number(r.payment_id), bookingId: Number(r.booking_id) || 0, amount: Number(r.amount) || 0, actorName: s(r.actor_name), status: s(r.status),
      reason: s(r.reason), createdAt: s(r.created_at), patientName: s(r.patient_name), hospitalName: s(r.hospital_name),
    }));
    return { items, page: q.page, perPage: PAY_PER_PAGE, hasMore: rows.length > PAY_PER_PAGE };
  }

  async refund(scope: AdminScope, actor: RefundActor, rawId: string, body: unknown): Promise<{ ok: true; refunded: number; status: string }> {
    const id = parsePositiveInt(rawId);
    if (id === null) throw new NotFoundException({ error: 'not_found' });
    const parsed = parseRefundBody(body);
    if (!parsed.ok) throw new BadRequestException({ error: 'validation', errors: parsed.errors });
    const cfg = inicisRefundConfig();
    const pool = this.rw?.get() ?? null;
    if (!cfg) { this.log.warn('refund is switched on but the Inicis refund settings (INICIS_MID, INICIS_INIAPI_KEY, INICIS_CLIENT_IP) are incomplete'); throw new ServiceUnavailableException({ error: 'refund_unavailable' }); }
    if (!pool) { this.log.warn('write pool not configured (DB_RW_USER is empty)'); throw new ServiceUnavailableException({ error: 'write_unavailable' }); }
    const conn = await pool.getConnection();
    let locked = '';
    try {
      const first = await this.load(conn, scope, id, false);
      locked = refundLockKey(s(first.merchant_uid));
      const [got] = (await conn.query('SELECT GET_LOCK(?, 5) AS acquired', [locked])) as any;
      if (Number(rowsOf(got)[0]?.acquired) !== 1) { locked = ''; throw new ConflictException({ error: 'busy' }); }
      return await this.run(conn, cfg, scope, actor, id, parsed.requestId, parsed.reason);
    } catch (e) {
      if (e instanceof HttpException) throw e;
      const err = e as { code?: string; errno?: number; message?: string };
      if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) { this.log.warn(`write database unavailable: ${err.message}`); throw new ServiceUnavailableException({ error: 'write_unavailable' }); }
      throw e;
    } finally {
      if (locked) await conn.query('SELECT RELEASE_LOCK(?)', [locked]).catch(() => undefined);
      conn.release();
    }
  }

  private async load(conn: RwConnection, scope: AdminScope, id: number, forUpdate: boolean): Promise<Record<string, unknown>> {
    const sc = scopeClause(scope, 0, 'p.hospital_id');
    const [rows] = (await conn.query(`SELECT ${detailColumns()} ${FROM} WHERE p.id = ? AND ${ONLY_HOSPITAL_BOOKINGS} AND ${sc.sql} LIMIT 1${forUpdate ? ' FOR UPDATE' : ''}`, [id, ...sc.values])) as any;
    const r = rowsOf(rows)[0];
    if (!r) throw new NotFoundException({ error: 'not_found' });
    return r;
  }

  private async run(conn: RwConnection, cfg: InicisRefundConfig, scope: AdminScope, actor: RefundActor, id: number, requestId: string, reason: string) {
    const row = await this.load(conn, scope, id, false);
    const c = candidateOf(row);
    const block = refundBlock(c);
    if (block === null && !providerMatchesMode(c.provider, cfg.mode)) throw new ConflictException({ error: 'refund_blocked', reason: 'mode_mismatch' });
    const amount = refundableAmount(c);

    const [prevRows] = (await conn.query('SELECT payment_id, amount, status FROM gangnamunni_payment_refund_requests WHERE request_id = ?', [requestId])) as any;
    const prev = rowsOf(prevRows)[0];
    if (prev) {
      if (Number(prev.payment_id) !== id) throw new ConflictException({ error: 'request_conflict' });
      if (s(prev.status) === 'succeeded') return { ok: true as const, refunded: Number(prev.amount) || 0, status: 'refunded' }; // same request sent again: already done
      if (s(prev.status) === 'failed') throw new ConflictException({ error: 'request_failed' }); // the gateway refused it: a new request id is needed
    }
    if (block) throw new ConflictException({ error: 'refund_blocked', reason: block });
    if (prev && Number(prev.amount) !== amount) throw new ConflictException({ error: 'request_conflict' });
    if (!prev) {
      const [open] = (await conn.query("SELECT request_id FROM gangnamunni_payment_refund_requests WHERE payment_id = ? AND status = 'pending' LIMIT 1", [id])) as any;
      if (rowsOf(open).length) throw new ConflictException({ error: 'reconcile_required' }); // an earlier answer is still unknown: check the Inicis console first
      await conn.query('INSERT INTO gangnamunni_payment_refund_requests (request_id, payment_id, amount, refunded_before, actor_id, reason) VALUES (?, ?, ?, ?, ?, ?)', [requestId, id, amount, c.refundedAmount, actor.id, reason]);
    }

    let answer;
    try {
      const res = await fetch(refundUrl(cfg.mode), {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8', Accept: '*/*' },
        body: new URLSearchParams(refundForm(cfg, c.tid, reason)).toString(), signal: AbortSignal.timeout(cfg.timeoutMs), redirect: 'error',
      });
      answer = parseInicisAnswer(res.status, await res.text());
    } catch (e) {
      answer = { kind: 'unknown' as const, why: (e as Error).message };
    }
    if (answer.kind === 'unknown') {
      this.log.error(`REFUND_OUTCOME_UNKNOWN payment=${id} admin=${actor.id} request=${requestId.slice(0, 12)}: ${answer.why}. The card may be refunded: check the Inicis console before anything else`);
      throw new HttpException({ error: 'outcome_unknown' }, 502);
    }
    if (answer.kind === 'rejected') {
      await conn.query("UPDATE gangnamunni_payment_refund_requests SET status = 'failed', completed_at = NOW() WHERE request_id = ?", [requestId]);
      this.log.warn(`refund rejected by Inicis: payment=${id} admin=${actor.id} code=${answer.code}`);
      throw new HttpException({ error: 'rejected', code: answer.code }, 422);
    }

    // The gateway has returned the money: from here the ledger must follow, or someone must fix it by hand (it is logged loudly).
    try {
      await conn.beginTransaction();
      const fresh = await this.load(conn, scope, id, true);
      const f = candidateOf(fresh);
      const newRefunded = f.refundedAmount + amount;
      if (!['paid', 'partially_refunded'].includes(f.status) || f.refundedAmount !== c.refundedAmount || newRefunded > f.amount) throw new Error('payment changed during the refund');
      const status = newRefunded === f.amount ? 'refunded' : 'partially_refunded';
      await conn.query(
        "INSERT INTO gangnamunni_payment_events (payment_id, provider, provider_event_id, event_type, amount, payload_sha256, provider_occurred_at) VALUES (?, ?, ?, 'refund_verified', ?, ?, NOW())",
        [id, f.provider, `refund:${f.tid}:${newRefunded}`, amount, evidenceHash(f.tid, amount, answer)]);
      await conn.query('UPDATE gangnamunni_platform_payments SET refunded_amount = ?, refunded_at = NOW(), status = ? WHERE id = ?', [newRefunded, status, id]);
      await conn.query("UPDATE gangnamunni_payment_refund_requests SET status = 'succeeded', completed_at = NOW() WHERE request_id = ?", [requestId]);
      const bookingId = Number(fresh.booking_id) || 0;
      if (status === 'refunded' && bookingId > 0) {
        await conn.query("UPDATE gangnamunni_bookings SET status = IF(status = 'cancelled', 'refunded', status), refund_status = 'refunded', updated_at = NOW() WHERE id = ? AND refund_status <> 'refunded'", [bookingId]);
      }
      await conn.query("INSERT INTO gangnamunni_refund_audit (booking_id, payment_id, amount, actor_type, actor_id, actor_name, status) VALUES (?, ?, ?, 'admin', ?, ?, 'completed')", [bookingId, id, amount, actor.id, actor.name.slice(0, 191)]);
      await conn.commit();
      this.log.log(`refund done: payment=${id} booking=${bookingId} amount=${amount} admin=${actor.id}`);
      if (bookingId > 0) this.notifier()?.later(bookingId, 'refunded');
      return { ok: true as const, refunded: amount, status };
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      this.log.error(`REFUND_SENT_LEDGER_FAILED payment=${id} amount=${amount} admin=${actor.id} request=${requestId.slice(0, 12)}: ${(e as Error).message}. Inicis returned the money, the ledger is NOT updated: fix it by hand`);
      throw new HttpException({ error: 'ledger_failed' }, 500);
    }
  }
}
