import { Inject, Injectable, Logger, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { Query } from '../hospitals/hospitals.logic';
import {
  countsQuery, Filter, listQuery, PER_PAGE, safeLink, totalQuery, unreadQuery,
} from './alerts.logic';

type Row = Record<string, unknown>;
const num = (v: unknown): number => Number(v ?? 0) || 0;
const text = (v: unknown): string => (v === null || v === undefined ? '' : String(v));
const notReady = (e: unknown): boolean =>
  ['ER_NO_SUCH_TABLE', 'ER_BAD_FIELD_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR'].includes(String((e as { code?: string })?.code));

/** The signed-in admin's own inbox. Every query is bound to that admin id, which comes from the session and never from the request. */
@Injectable()
export class AlertsService {
  private readonly log = new Logger('Alerts');
  constructor(@Inject(DB) private readonly db: Db) {}

  private async rows(q: Query): Promise<Row[]> {
    try {
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return Array.isArray(rows) ? rows : [];
    } catch (e) {
      if (notReady(e)) { this.log.warn(`inbox not ready: ${(e as Error).message}`); throw new ServiceUnavailableException({ error: 'not_ready' }); }
      throw e;
    }
  }

  async unread(adminId: number): Promise<{ unread: number }> {
    return { unread: num((await this.rows(unreadQuery(adminId)))[0]?.n) };
  }

  async list(adminId: number, f: Filter, page: number) {
    const [items, total, counts] = await Promise.all([this.rows(listQuery(adminId, f, page)), this.rows(totalQuery(adminId, f)), this.rows(countsQuery(adminId))]);
    const n = num(total[0]?.n);
    const c = counts[0] ?? {};
    const totalAll = num(c.total);
    const unread = num(c.unread);
    return {
      page, perPage: PER_PAGE, total: n, pages: Math.max(1, Math.ceil(n / PER_PAGE)),
      items: items.map((r) => ({ id: num(r.id), type: text(r.type), title: text(r.title), message: text(r.message), link: safeLink(r.link), icon: text(r.icon), read: num(r.is_read) === 1, createdAt: text(r.created_at) })),
      counts: { all: unread, activity: num(c.u_activity), pending: num(c.u_pending), system: num(c.u_system), unread, read: Math.max(0, totalAll - unread), total: totalAll },
    };
  }
}
