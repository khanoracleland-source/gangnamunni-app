import { ConflictException, Logger, ServiceUnavailableException } from '@nestjs/common';
import { RwConnection, RwDb, RwDbProvider } from '../db/db.module';

export interface WriteActor { id: number; name: string }

/** Database problems that mean "the write user cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040, 1054];
const DUPLICATE = 1062;

/** Shared by the booking writers: the write pool, its error mapping, one transaction, the audit line. */
export abstract class BookingsRwBase {
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

  /** Database errors of the write user -> 503 write_unavailable; a duplicate key -> 409; anything else is left to the 500 handler. */
  protected async guarded<T>(fn: () => Promise<T>): Promise<T> {
    try {
      return await fn();
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
        this.log.warn(`write database unavailable: ${err.message}`);
        throw new ServiceUnavailableException({ error: 'write_unavailable' });
      }
      if (err?.errno === DUPLICATE) throw new ConflictException({ error: 'duplicate' });
      throw e;
    }
  }

  protected async tx<T>(fn: (conn: RwConnection) => Promise<T>): Promise<T> {
    const conn = await this.pool().getConnection();
    try {
      await conn.beginTransaction();
      const out = await fn(conn);
      await conn.commit();
      return out;
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      throw e;
    } finally {
      conn.release();
    }
  }

  protected audit(actor: WriteActor, action: string, bookingId: number): void {
    this.log.log(`booking write: admin=${actor.id} action=${action} booking=${bookingId}`);
  }
}
