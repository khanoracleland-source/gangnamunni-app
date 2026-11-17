import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { Query } from './hospitals.logic';
import { approveQuery, lockApplicationQuery, parseRejectBody, rejectQuery, ReviewOutcome } from './hospitals.review.logic';

export interface ReviewActor {
  id: number;
  /** already cut to the length of the reviewed_by column */
  name: string;
}

/** Database problems that mean "the write user cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040];

/** Approving or rejecting one hospital application: the status columns only, in one transaction of the write pool. */
@Injectable()
export class HospitalsReviewService {
  private readonly log = new Logger('HospitalsReview');

  constructor(@Inject(DB_RW) private readonly rw: RwDbProvider) {}

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  approve(actor: ReviewActor, id: number): Promise<{ ok: true; id: number; status: 'approved' }> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    return this.review(actor, id, 'approved', approveQuery(id, actor.name));
  }

  reject(actor: ReviewActor, id: number, body: unknown): Promise<{ ok: true; id: number; status: 'rejected' }> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const parsed = parseRejectBody(body);
    if (!parsed.ok) throw new BadRequestException({ error: 'reason_required', fields: parsed.fields });
    return this.review(actor, id, 'rejected', rejectQuery(id, parsed.reason, actor.name));
  }

  private async review<S extends ReviewOutcome>(actor: ReviewActor, id: number, status: S, update: Query): Promise<{ ok: true; id: number; status: S }> {
    let conn;
    try {
      conn = await this.pool().getConnection();
    } catch (e) {
      this.unavailable(e);
      throw e;
    }
    try {
      await conn.beginTransaction();
      const lock = lockApplicationQuery(id);
      const [rows] = (await conn.query(lock.sql, lock.values)) as any;
      const row = Array.isArray(rows) ? rows[0] : undefined;
      if (!row) throw new NotFoundException({ error: 'not_found' });
      if (row.status !== 'pending') throw new ConflictException({ error: 'already_reviewed' });
      const [res] = (await conn.query(update.sql, update.values)) as any;
      if (!res || res.affectedRows !== 1) throw new ConflictException({ error: 'already_reviewed' });
      await conn.commit();
      this.log.log(`hospital review: admin=${actor.id} hospital=${id} status=${status}`);
      return { ok: true, id, status };
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
