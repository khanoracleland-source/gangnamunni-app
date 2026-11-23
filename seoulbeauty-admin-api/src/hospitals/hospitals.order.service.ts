import { BadRequestException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { lockOrderQuery, OrderRow, parseMoveBody, planMove, setOrderQuery } from './hospitals.order.logic';

/** Database problems that mean "the write user cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040];

/** Changing where one hospital stands in the app's default order: one transaction of the write pool that renumbers the display order 1..N. */
@Injectable()
export class HospitalsOrderService {
  private readonly log = new Logger('HospitalsOrder');

  constructor(@Inject(DB_RW) private readonly rw: RwDbProvider) {}

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  async move(adminId: number, id: number, body: unknown): Promise<{ ok: true; id: number; position: number; total: number; changed: number }> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const parsed = parseMoveBody(body);
    if (!parsed.ok) throw new BadRequestException({ error: 'invalid', fields: parsed.fields });
    let conn;
    try {
      conn = await this.pool().getConnection();
    } catch (e) {
      this.unavailable(e);
      throw e;
    }
    try {
      await conn.beginTransaction();
      const lock = lockOrderQuery();
      const [found] = (await conn.query(lock.sql, lock.values)) as any;
      const rows: OrderRow[] = (Array.isArray(found) ? found : []).map((r: any) => ({ id: Number(r.id), order: r.display_order === null || r.display_order === undefined ? null : Number(r.display_order) }));
      const plan = planMove(rows, id, parsed.move);
      if (!plan.found) throw new NotFoundException({ error: 'not_found' });
      if (plan.outOfRange) throw new BadRequestException({ error: 'invalid', fields: { position: 'out_of_range' } });
      for (const u of plan.updates) {
        const q = setOrderQuery(u.id, u.position);
        await conn.query(q.sql, q.values);
      }
      await conn.commit();
      if (plan.updates.length > 0) this.log.log(`hospital order: admin=${adminId} hospital=${id} position=${plan.position} changed=${plan.updates.length}`);
      return { ok: true, id, position: plan.position, total: plan.total, changed: plan.updates.length };
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      this.unavailable(e);
      throw e;
    } finally {
      conn.release();
    }
  }

  /** Database errors of the write user -> 503 write_unavailable; anything else is left to the caller. */
  private unavailable(e: unknown): void {
    const err = e as { code?: string; errno?: number; message?: string };
    if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
      this.log.warn(`write database unavailable: ${err.message}`);
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
  }
}
