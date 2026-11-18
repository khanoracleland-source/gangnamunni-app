import { BadRequestException, ConflictException, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { RwConnection, RwDb, RwDbProvider } from '../db/db.module';
import { TreatmentItem, TreatmentsAnswer } from './treatments.logic';
import { isFieldError } from './treatments.write.logic';

export interface WriteActor {
  id: number;
  name: string;
}

/** Database problems that mean "the write user cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040];
/** A row that another table still points at (packages): MySQL 1451. */
const FK_ROW_REFERENCED = 1451;
const DUPLICATE = 1062;

/** Shared by the treatment writers: the write pool, its error mapping, transactions, the audit line. */
export abstract class TreatmentsRwBase {
  protected abstract readonly log: Logger;
  protected abstract readonly rw: RwDbProvider;

  protected pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  /** Database errors of the write user -> 503 write_unavailable; a referenced row -> 409 in_use; anything else is left to the 500 handler. */
  protected async guarded<T>(fn: () => Promise<T>): Promise<T> {
    try {
      return await fn();
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
        this.log.warn(`write database unavailable: ${err.message}`);
        throw new ServiceUnavailableException({ error: 'write_unavailable' });
      }
      if (err?.errno === FK_ROW_REFERENCED) throw new ConflictException({ error: 'in_use', hospitalCount: 0 });
      if (err?.errno === DUPLICATE) throw new ConflictException({ error: 'duplicate' });
      throw e;
    }
  }

  protected async tx(fn: (conn: RwConnection) => Promise<void>): Promise<void> {
    const conn = await this.pool().getConnection();
    try {
      await conn.beginTransaction();
      await fn(conn);
      await conn.commit();
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      throw e;
    } finally {
      conn.release();
    }
  }

  protected audit(actor: WriteActor, action: string, ids: number[]): void {
    this.log.log(`treatment write: admin=${actor.id} action=${action} ids=${ids.join(',')}`);
  }

  protected fail(r: unknown): never {
    if (isFieldError(r)) throw new BadRequestException(r);
    const e = (r as { error: string }).error;
    if (e === 'invalid_body') throw new BadRequestException({ error: e });
    throw new ServiceUnavailableException({ error: e });
  }

  protected item(snap: TreatmentsAnswer, id: number): TreatmentItem {
    const it = snap.items.find((i) => i.id === id);
    if (!it) throw new NotFoundException({ error: 'not_found' });
    return it;
  }
}
