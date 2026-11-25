import { BadRequestException, Inject, Injectable, Logger, ServiceUnavailableException } from '@nestjs/common';
import { AdminScope } from '../admin-auth/admin.guard';
import { DB, Db } from '../db/db.module';
import { isMissingObject } from '../hospitals/hospitals.treatments.logic';
import { ConsultationItem, countQuery, hospitalOptionsQuery, isFail, listQuery, parseListQuery, PER_PAGE, Query, Stats, statsQuery, toItem, toStats } from './consultations.logic';

export interface ListAnswer { items: ConsultationItem[]; total: number; page: number; perPage: number; totalPages: number; stats: Stats }
export interface HospitalOption { id: number; name: string; consultationCount: number; pendingCount: number }

const rowsOf = (r: unknown): Record<string, unknown>[] => (Array.isArray(r) ? (r as Record<string, unknown>[]) : []);

/** Reading consultation requests: the list with its counts and the hospital choices. Everything is cut to the admin's scope. */
@Injectable()
export class ConsultationsService {
  private readonly log = new Logger('Consultations');
  constructor(@Inject(DB) private readonly db: Db) {}

  private async run(q: Query): Promise<Record<string, unknown>[]> {
    try {
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return rowsOf(rows);
    } catch (e) {
      if (isMissingObject(e)) {
        this.log.warn(`consultations table unavailable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
        throw new ServiceUnavailableException({ error: 'consultations_unavailable' });
      }
      throw e;
    }
  }

  async list(scope: AdminScope, raw: Record<string, unknown>): Promise<ListAnswer> {
    const f = parseListQuery(raw);
    if (isFail(f)) throw new BadRequestException(f);
    const [countRows, statRows] = await Promise.all([this.run(countQuery(scope, f)), this.run(statsQuery(scope, f.hospitalId))]);
    const total = Number(countRows[0]?.cnt) || 0;
    const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    const page = Math.min(f.page, totalPages);
    const rows = await this.run(listQuery(scope, { ...f, page }));
    return { items: rows.map(toItem), total, page, perPage: PER_PAGE, totalPages, stats: toStats(statRows) };
  }

  async hospitals(scope: AdminScope): Promise<{ items: HospitalOption[] }> {
    const rows = await this.run(hospitalOptionsQuery(scope));
    return { items: rows.map((r) => ({ id: Number(r.id), name: String(r.name ?? ''), consultationCount: Number(r.cnt) || 0, pendingCount: Number(r.pending_count) || 0 })) };
  }
}
