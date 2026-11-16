import { Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db, DB_RW, RwDbProvider } from '../db/db.module';
import { ListQuery } from '../hospitals/hospitals.logic';
import { ackQuery, archiveCountQuery, archiveExistsQuery, archiveListQuery, archiveOneQuery, num, toArchiveDetail, toArchiveItem } from '../hospitals/hospitals.delete.logic';

/** The archive table is created by hand (deploy/sql); a missing table or grant reads as "unavailable", never as an empty archive. */
const MISSING_CODES = ['ER_NO_SUCH_TABLE', 'ER_BAD_FIELD_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR'];
const MISSING_ERRNO = [1146, 1054, 1142, 1143];

/** Records of deleted hospitals: sealed figures and snapshot. Read with the read-only pool; only the acknowledgement is written. */
@Injectable()
export class HospitalArchiveService {
  private readonly log = new Logger('HospitalArchive');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider) {}

  private async guarded<T>(fn: () => Promise<T>, error: string): Promise<T> {
    try {
      return await fn();
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      if (MISSING_CODES.includes(err?.code || '') || MISSING_ERRNO.includes(err?.errno || 0)) {
        this.log.warn(`archive table/column missing or not granted: ${err.message}`);
        throw new ServiceUnavailableException({ error });
      }
      throw e;
    }
  }

  private async rows(q: { sql: string; values: unknown[] }): Promise<Record<string, unknown>[]> {
    const [rows] = (await this.ro.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  }

  list(q: Pick<ListQuery, 'page' | 'perPage'>) {
    return this.guarded(async () => {
      const [items, count] = await Promise.all([this.rows(archiveListQuery(q.page, q.perPage)), this.rows(archiveCountQuery())]);
      return { items: items.map(toArchiveItem), total: num(count[0]?.total), dueCount: num(count[0]?.due) };
    }, 'archive_unavailable');
  }

  detail(id: number) {
    return this.guarded(async () => {
      const row = id > 0 ? (await this.rows(archiveOneQuery(id)))[0] : undefined;
      if (!row) throw new NotFoundException({ error: 'not_found' });
      return toArchiveDetail(row);
    }, 'archive_unavailable');
  }

  /** Marks the notice as seen; already seen is fine. */
  ack(id: number, adminName: string): Promise<{ ok: true }> {
    return this.guarded(async () => {
      if (id <= 0 || (await this.rows(archiveExistsQuery(id))).length === 0) throw new NotFoundException({ error: 'not_found' });
      const pool = this.rw.get();
      if (!pool) throw new ServiceUnavailableException({ error: 'write_unavailable' });
      const q = ackQuery(id, adminName.slice(0, 100));
      await pool.query(q.sql, q.values);
      this.log.log(`hospital archive ack: archive=${id}`);
      return { ok: true as const };
    }, 'write_unavailable');
  }
}
