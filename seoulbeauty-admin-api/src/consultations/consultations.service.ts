import { BadRequestException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { promises as fs, constants as fsc } from 'fs';
import * as path from 'path';
import { AdminScope } from '../admin-auth/admin.guard';
import { DB, Db } from '../db/db.module';
import { isMissingObject } from '../hospitals/hospitals.treatments.logic';
import { ConsultationDetail, ConsultationItem, countQuery, detailQuery, eventsQuery, historyQuery, hospitalOptionsQuery, isFail, listQuery, parseId, parseListQuery, parsePhotoIndex, photoPath, photosQuery, PER_PAGE, Query, Stats, statsQuery, toDetail, toItem, toStats } from './consultations.logic';

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

  /** One application with its full text, the hospital's message and the progress record. The history parts are optional (older database: left empty). */
  async detail(scope: AdminScope, rawId: unknown): Promise<ConsultationDetail> {
    const id = parseId(rawId);
    if (id === null) throw new NotFoundException({ error: 'not_found' });
    const core = (await this.run(detailQuery(scope, id)))[0];
    if (!core) throw new NotFoundException({ error: 'not_found' });
    const history = await this.optional(historyQuery(scope, id), (r) => r[0] ?? null);
    const events = await this.optional(eventsQuery(id), (r) => r);
    return toDetail(core, history, events);
  }

  /** The file of one photo: only for an application inside the admin's scope, only a plain image file inside that application's folder. */
  async photo(scope: AdminScope, rawId: unknown, rawIndex: unknown): Promise<{ file: string; type: string; name: string; size: number }> {
    const id = parseId(rawId);
    const index = parsePhotoIndex(rawIndex);
    if (id === null || index === null) throw new NotFoundException({ error: 'not_found' });
    const row = (await this.run(photosQuery(scope, id)))[0];
    const info = row ? photoPath(id, row.photo_urls, index) : null;
    if (!info) throw new NotFoundException({ error: 'not_found' });
    const root = await this.uploadRoot();
    try {
      const base = await fs.realpath(path.join(root, 'uploads', 'consultations', String(id)));
      const file = await fs.realpath(path.join(root, info.rel));
      const st = await fs.stat(file);
      if (!file.startsWith(base + path.sep) || !st.isFile()) throw new Error('outside');
      return { file, type: info.type, name: info.name, size: st.size };
    } catch {
      throw new NotFoundException({ error: 'not_found' });
    }
  }

  /** Reading needs the folder only, not write rights. */
  private async uploadRoot(): Promise<string> {
    const root = process.env.ADMIN_UPLOAD_ROOT || '';
    try {
      if (!root || !path.isAbsolute(root)) throw new Error('ADMIN_UPLOAD_ROOT is empty or not an absolute path');
      if (!(await fs.stat(root)).isDirectory()) throw new Error('ADMIN_UPLOAD_ROOT is not a directory');
      await fs.access(root, fsc.R_OK | fsc.X_OK);
      return path.resolve(root);
    } catch (e) {
      this.log.warn(`upload root unusable: ${(e as { code?: string; message?: string })?.code || (e as Error)?.message}`);
      throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
    }
  }

  /** A read of something the older database may not have: a missing column / table / grant gives null, nothing else is hidden. */
  private async optional<T>(q: Query, pick: (rows: Record<string, unknown>[]) => T): Promise<T | null> {
    try {
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return pick(rowsOf(rows));
    } catch (e) {
      if (isMissingObject(e)) return null;
      throw e;
    }
  }
}
