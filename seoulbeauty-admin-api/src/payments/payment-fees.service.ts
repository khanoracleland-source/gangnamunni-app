import { BadRequestException, ConflictException, NotFoundException, Inject, Injectable, Logger, Optional, ServiceUnavailableException, UnprocessableEntityException } from '@nestjs/common';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { isMissingObject } from '../hospitals/hospitals.treatments.logic';
import { FEE_METHODS, FeeMethod, orderError, parseFeeBody, rateAt, removable } from './payment-fees.logic';
import { seoulToday } from './payments.logic';

export interface FeeRateItem { id: number; payMethod: FeeMethod; rateBp: number; effectiveFrom: string; createdByName: string; createdAt: string }
export interface FeeActor { id: number; username: string; role: string }

const LIST_LIMIT = 200;
const UNAVAILABLE_CODES = ['ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_NO_SUCH_TABLE', 'ER_CON_COUNT_ERROR', 'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST'];

/** Deadlock / lock wait: nothing was changed, the caller may simply try again. */
const isBusy = (e: { code?: string; errno?: number }): boolean => ['ER_LOCK_DEADLOCK', 'ER_LOCK_WAIT_TIMEOUT'].includes(e?.code || '') || [1205, 1213].includes(e?.errno || 0);

const toItem = (r: Record<string, unknown>): FeeRateItem => ({
  id: Number(r.id), payMethod: String(r.pay_method) as FeeMethod, rateBp: Number(r.rate_bp), effectiveFrom: String(r.effective_from).slice(0, 10),
  createdByName: String(r.created_by_name ?? ''), createdAt: String(r.created_at ?? ''),
});

/** Payment fee rates (gangnamunni_payment_fee_rates). Rows are only ever added: a change is a new row with a later start date. */
@Injectable()
export class PaymentFeesService {
  private readonly log = new Logger('PaymentFees');
  constructor(@Inject(DB) private readonly ro: Db, @Optional() @Inject(DB_RW) private readonly rw: RwDbProvider | null) {}

  async list(showAuthors = false) {
    let rows: Record<string, unknown>[] = [];
    try {
      const [r] = (await this.ro.query(
        'SELECT id, pay_method, rate_bp, effective_from, created_by_name, created_at FROM gangnamunni_payment_fee_rates ORDER BY effective_from DESC, id DESC LIMIT ?', [LIST_LIMIT])) as any;
      rows = Array.isArray(r) ? r : [];
    } catch (e) {
      if (isMissingObject(e)) { this.log.warn(`fee rate table unavailable: ${(e as { code?: string })?.code}`); throw new ServiceUnavailableException({ error: 'payments_unavailable' }); }
      throw e;
    }
    const items = rows.map(toItem).map((i) => (showAuthors ? i : { ...i, createdByName: '' })); // login names are for super admins only
    const today = seoulToday();
    const current = Object.fromEntries(FEE_METHODS.map((m) => {
      const bp = rateAt(items, m, today);
      const from = bp === null ? null : items.filter((i) => i.payMethod === m && i.effectiveFrom <= today).map((i) => i.effectiveFrom).sort().pop() ?? null;
      return [m, bp === null ? null : { rateBp: bp, effectiveFrom: from }];
    }));
    return { methods: FEE_METHODS, today, current, items };
  }

  async create(actor: FeeActor, body: unknown): Promise<{ item: FeeRateItem }> {
    const today = seoulToday();
    const parsed = parseFeeBody(body, today);
    if (!parsed.ok) throw new BadRequestException({ error: 'validation', errors: parsed.errors });
    const plan = parsed.plan;
    const pool: RwDb | null = this.rw?.get() ?? null;
    if (!pool) { this.log.warn('write pool not configured (DB_RW_USER is empty)'); throw new ServiceUnavailableException({ error: 'write_unavailable' }); }
    let conn;
    try {
      conn = await pool.getConnection();
      await conn.beginTransaction();
      const [last] = (await conn.query('SELECT effective_from FROM gangnamunni_payment_fee_rates WHERE pay_method = ? ORDER BY effective_from DESC LIMIT 1 FOR UPDATE', [plan.payMethod])) as any;
      const latest = Array.isArray(last) && last[0] ? String(last[0].effective_from).slice(0, 10) : null;
      const why = orderError(latest, plan.effectiveFrom, today);
      if (why) { await conn.rollback(); throw new UnprocessableEntityException({ error: 'validation', errors: { effectiveFrom: why } }); }
      const [ins] = (await conn.query(
        'INSERT INTO gangnamunni_payment_fee_rates (pay_method, rate_bp, effective_from, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)',
        [plan.payMethod, plan.rateBp, plan.effectiveFrom, actor.id, actor.username.slice(0, 191)])) as any;
      await conn.commit();
      // Audit: the row itself (who, when) stays in the table; the log carries the same facts. Rates are not secret.
      this.log.log(`fee rate added: admin=${actor.id} role=${actor.role} method=${plan.payMethod} rate_bp=${plan.rateBp} from=${plan.effectiveFrom}`);
      return { item: { id: Number(ins?.insertId) || 0, payMethod: plan.payMethod, rateBp: plan.rateBp, effectiveFrom: plan.effectiveFrom, createdByName: actor.username, createdAt: '' } };
    } catch (e) {
      if (conn) await conn.rollback().catch(() => undefined);
      const err = e as { code?: string; errno?: number; message?: string };
      if (e instanceof UnprocessableEntityException || e instanceof BadRequestException) throw e;
      if (err?.code === 'ER_DUP_ENTRY' || err?.errno === 1062) throw new ConflictException({ error: 'conflict' });
      if (UNAVAILABLE_CODES.includes(err?.code || '')) { this.log.warn(`write database unavailable: ${err.message}`); throw new ServiceUnavailableException({ error: 'write_unavailable' }); }
      if (isBusy(err)) { this.log.warn(`fee rate write busy: ${err.message}`); throw new ServiceUnavailableException({ error: 'busy' }); }
      throw e;
    } finally {
      conn?.release();
    }
  }
  /** Removes a rate that has not come into force yet (a mistaken date or value). Rates already in force are never removed. */
  async remove(actor: FeeActor, id: number): Promise<{ ok: true }> {
    const today = seoulToday();
    const pool: RwDb | null = this.rw?.get() ?? null;
    if (!pool) { this.log.warn('write pool not configured (DB_RW_USER is empty)'); throw new ServiceUnavailableException({ error: 'write_unavailable' }); }
    let conn;
    try {
      conn = await pool.getConnection();
      await conn.beginTransaction();
      const [found] = (await conn.query('SELECT id, pay_method, rate_bp, effective_from FROM gangnamunni_payment_fee_rates WHERE id = ? FOR UPDATE', [id])) as any;
      const row = Array.isArray(found) ? found[0] : undefined;
      if (!row) throw new NotFoundException({ error: 'not_found' });
      if (!removable(String(row.effective_from).slice(0, 10), today)) throw new UnprocessableEntityException({ error: 'validation', errors: { id: 'already_in_force' } });
      const [del] = (await conn.query('DELETE FROM gangnamunni_payment_fee_rates WHERE id = ? AND effective_from > ?', [id, today])) as any;
      if (Number(del?.affectedRows) !== 1) throw new UnprocessableEntityException({ error: 'validation', errors: { id: 'already_in_force' } });
      await conn.commit();
      this.log.log(`fee rate removed: admin=${actor.id} role=${actor.role} id=${id} method=${row.pay_method} rate_bp=${row.rate_bp} from=${String(row.effective_from).slice(0, 10)}`);
      return { ok: true };
    } catch (e) {
      if (conn) await conn.rollback().catch(() => undefined);
      const err = e as { code?: string; errno?: number; message?: string };
      if (e instanceof UnprocessableEntityException || e instanceof NotFoundException) throw e;
      if (UNAVAILABLE_CODES.includes(err?.code || '')) { this.log.warn(`write database unavailable: ${err.message}`); throw new ServiceUnavailableException({ error: 'write_unavailable' }); }
      if (isBusy(err)) { this.log.warn(`fee rate write busy: ${err.message}`); throw new ServiceUnavailableException({ error: 'busy' }); }
      throw e;
    } finally {
      conn?.release();
    }
  }
}
