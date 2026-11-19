import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { AdminItem, AdminList, AdminOptions, buildOptions, DELETE_AFTER_DAYS, detailQuery, hospitalOptionsQuery, inactivityQuery, listQuery, num, Query, ssoQuery, toItem, Viewer } from './admins.logic';

type Row = Record<string, unknown>;

/** Read-only access to gangnamunni_admins. A super admin sees every account, anybody else only their own. */
@Injectable()
export class AdminsService {
  private readonly log = new Logger('Admins');

  constructor(@Inject(DB) private readonly db: Db) {}

  /**
   * Rows of a SELECT. With `optional` a table or column that is not installed, or not granted to the read-only user,
   * reads as "no rows" and is logged. Every other error, and every error of a required query, is thrown.
   */
  private async rows(q: Query, optional = false): Promise<Row[]> {
    try {
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return Array.isArray(rows) ? rows : [];
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      const tolerated =
        optional &&
        (['ER_NO_SUCH_TABLE', 'ER_BAD_FIELD_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR'].includes(err?.code || '') ||
          [1146, 1054, 1142, 1143].includes(err?.errno || 0));
      if (!tolerated) throw e;
      this.log.warn(`optional table/column missing or not granted, reported as empty: ${err.message}`);
      return [];
    }
  }

  private async items(rows: Row[], viewer: Viewer): Promise<AdminItem[]> {
    const inactiveIds = rows.filter((r) => num(r.is_active) !== 1).map((r) => num(r.id));
    const marks = new Map<number, Row>();
    if (inactiveIds.length > 0) for (const m of await this.rows(inactivityQuery(inactiveIds), true)) marks.set(num(m.id), m);
    const links = new Map<number, Row>();
    if (viewer.superAdmin && rows.length > 0) for (const l of await this.rows(ssoQuery(rows.map((r) => num(r.id))), true)) links.set(num(l.id), l);
    return rows.map((r) => toItem(r, viewer, marks.get(num(r.id)), links.get(num(r.id))));
  }

  async list(viewer: Viewer): Promise<AdminList> {
    const items = await this.items(await this.rows(listQuery(viewer)), viewer);
    return { scope: viewer.superAdmin ? 'all' : 'self', deleteAfterDays: DELETE_AFTER_DAYS, total: items.length, items };
  }

  async options(viewer: Viewer): Promise<AdminOptions> {
    return buildOptions(viewer, viewer.superAdmin ? await this.rows(hospitalOptionsQuery(), true) : []);
  }

  /** null = no such account. The caller has already refused another account to a non-super admin. */
  async detail(viewer: Viewer, id: number): Promise<AdminItem | null> {
    if (id <= 0) return null;
    const found = (await this.rows(detailQuery(id)))[0];
    return found ? (await this.items([found], viewer))[0] : null;
  }
}
