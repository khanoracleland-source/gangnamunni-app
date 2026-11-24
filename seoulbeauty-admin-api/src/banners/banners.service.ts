import { ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { Query } from '../hospitals/hospitals.logic';
import {
  BannerPage, BannerQuery, bannerCountsQuery, bannerListQuery, bannerRevision, deleteBannerQuery, deleteHomeKeyQuery, homeKey, lockBannerQuery, ScrollSettings, SCROLL_ENABLED_KEY, SCROLL_INTERVAL_KEY,
  scrollQuery, toBannerCounts, toBannerPage, toScroll, upsertSettingQuery, upsertThemeQuery,
} from './banners.logic';

type Row = Record<string, unknown>;

/** The advertising banners of the home page and my page (super admin only): reads on the read-only pool, every change in one transaction on the write pool. */
@Injectable()
export class BannersService {
  private readonly log = new Logger('Banners');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider) {}

  private async rows(q: Query): Promise<Row[]> {
    const [rows] = (await this.ro.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  }

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  private async tx<T>(fn: (conn: any) => Promise<T>): Promise<T> {
    const conn = await this.pool().getConnection();
    try {
      await conn.beginTransaction();
      const out = await fn(conn);
      await conn.commit();
      return out;
    } catch (e) {
      try {
        await conn.rollback();
      } catch {
        /* already rolled back */
      }
      throw e;
    } finally {
      conn.release();
    }
  }

  async list(q: BannerQuery): Promise<BannerPage> {
    const [counts, list] = await Promise.all([this.rows(bannerCountsQuery(q)), this.rows(bannerListQuery(q))]);
    return toBannerPage(q, toBannerCounts(counts[0]), list);
  }

  async scroll(): Promise<ScrollSettings> {
    return toScroll(await this.rows(scrollQuery()));
  }

  async saveScroll(adminId: number, value: ScrollSettings): Promise<ScrollSettings> {
    await this.tx(async (conn) => {
      const a = upsertThemeQuery(SCROLL_ENABLED_KEY, value.enabled ? '1' : '0');
      const b = upsertThemeQuery(SCROLL_INTERVAL_KEY, String(value.intervalSeconds * 1000));
      await conn.query(a.sql, a.values);
      await conn.query(b.sql, b.values);
    });
    this.log.log(`banner scroll settings changed by admin ${adminId}`);
    return value;
  }

  private async locked(conn: any, id: number): Promise<Row> {
    const lock = lockBannerQuery(id);
    const [found] = (await conn.query(lock.sql, lock.values)) as any;
    const row: Row | undefined = Array.isArray(found) ? found[0] : undefined;
    if (!row) throw new NotFoundException({ error: 'not_found' });
    return row;
  }

  /** Shows or hides one banner on the home page (the key the site reads). Only an approved banner can be shown. */
  async setHome(adminId: number, id: number, enabled: boolean) {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const out = await this.tx(async (conn) => {
      const row = await this.locked(conn, id);
      if (enabled && String(row.status) !== 'approved') throw new ConflictException({ error: 'not_approved' });
      const up = upsertSettingQuery(homeKey(id), enabled ? '1' : '0');
      await conn.query(up.sql, up.values);
      return { id, homeEnabled: enabled };
    });
    this.log.log(`banner ${id} home ${enabled ? 'on' : 'off'} by admin ${adminId}`);
    return out;
  }

  /** Deletes one banner for good (its row and the home switch key). The caller sends the revision it read, so a banner edited in between is never removed blind. */
  async remove(adminId: number, id: number, revision: string) {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const out = await this.tx(async (conn) => {
      const row = await this.locked(conn, id);
      if (bannerRevision(row) !== revision) throw new ConflictException({ error: 'changed' });
      const del = deleteBannerQuery(id);
      await conn.query(del.sql, del.values);
      const key = deleteHomeKeyQuery(id);
      await conn.query(key.sql, key.values);
      return { id };
    });
    this.log.log(`banner ${id} deleted by admin ${adminId}`);
    return out;
  }
}
