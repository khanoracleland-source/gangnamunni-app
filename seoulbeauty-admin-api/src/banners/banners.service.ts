import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { randomBytes } from 'crypto';
import { constants as fsc, promises as fs } from 'fs';
import { hospitalOptionsQuery } from '../doctors/doctors.logic';
import { toHospitalOption } from '../doctors/doctors.logic';
import { FS_UNAVAILABLE, readHead, resolveUploadRoot } from '../hospitals/hospitals.upload.shared';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { Query } from '../hospitals/hospitals.logic';
import { buildBannerImagePath, checkBannerImage, removableBannerPath } from './banners.image';
import { UploadedBannerImage, UploadedBannerImages } from './banners.upload';
import {
  BannerForm, BannerPage, BannerQuery, FieldErrors, bannerCountsQuery, hospitalExistsQuery, imageUsersQuery, insertBannerQuery, parseBannerForm, parseRevision, reviewerName, updateBannerQuery, bannerListQuery, bannerRevision, deleteBannerQuery, deleteHomeKeyQuery, homeKey, lockBannerQuery, ScrollSettings, SCROLL_ENABLED_KEY, SCROLL_INTERVAL_KEY,
  scrollQuery, toBannerCounts, toBannerPage, toScroll, upsertSettingQuery, upsertThemeQuery,
} from './banners.logic';

type Row = Record<string, unknown>;
const str = (v: unknown): string => (typeof v === 'string' ? v : v == null ? '' : String(v));

type Images = { image: string; mypage: string };

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
      return { id, old: [str(row.image_url), str(row.mypage_image_url)] };
    });
    await this.cleanup(out.old);
    this.log.log(`banner ${id} deleted by admin ${adminId}`);
    return { id };
  }

  /** Kasalxona tanlash ro'yxati (faqat tasdiqlangan kasalxonalar). */
  async hospitals() {
    const q = hospitalOptionsQuery({ scope: 'all', hospitalIds: [] });
    return { items: (await this.rows(q)).map(toHospitalOption) };
  }

  /** Deletes the files of pictures that no banner uses any more. Best effort: a failure only goes to the log. */
  private async cleanup(urls: string[]): Promise<void> {
    try {
      const own = Array.from(new Set(urls.filter((u) => u)));
      if (own.length === 0) return;
      const root = await resolveUploadRoot(this.log);
      for (const url of own) {
        const file = removableBannerPath(root, url);
        if (!file) continue;
        const q = imageUsersQuery(url);
        const [rows] = (await this.pool().query(q.sql, q.values)) as any;
        if (Number(Array.isArray(rows) ? rows[0]?.c : 1) > 0) continue;
        await fs.unlink(file).catch(() => undefined);
      }
    } catch (e) {
      this.log.warn(`banner picture cleanup skipped: ${(e as Error)?.message}`);
    }
  }

  /** Checks one uploaded picture: size, extension and real type. */
  private async checked(file: UploadedBannerImage | undefined) {
    if (!file) return null;
    let head: { head: Buffer; size: number };
    try {
      head = await readHead(file.path);
    } catch {
      throw new BadRequestException({ error: 'upload_failed' });
    }
    return checkBannerImage(file.originalname, head.size, head.head);
  }

  /** Copies a checked temporary file to the picture folder under a new random name. */
  private async place(root: string, tmp: string, kind: 'jpg' | 'png' | 'gif' | 'webp', written: string[]): Promise<string> {
    for (let attempt = 0; ; attempt++) {
      const target = buildBannerImagePath(root, randomBytes(16).toString('hex'), kind);
      try {
        try {
          const created = await fs.mkdir(target.dir, { recursive: true, mode: 0o755 });
          if (created !== undefined) await fs.chmod(target.dir, 0o755).catch(() => undefined);
        } catch (e) {
          this.log.warn(`banner folder unusable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
          throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
        }
        written.push(target.absolute);
        await fs.copyFile(tmp, target.absolute, fsc.COPYFILE_EXCL);
        await fs.chmod(target.absolute, 0o644);
        return target.url;
      } catch (e) {
        if (e instanceof ServiceUnavailableException) throw e;
        const code = (e as { code?: string })?.code || '';
        if (code === 'EEXIST' && attempt < 3) {
          written.pop();
          continue;
        }
        this.log.warn(`could not store a banner picture: ${code || (e as Error)?.message}`);
        if (FS_UNAVAILABLE.includes(code)) throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
        throw new BadRequestException({ error: 'upload_failed' });
      }
    }
  }

  /** Validates the form and the pictures, stores new pictures and runs `write` in one transaction; a failure removes what this request wrote. */
  private async save(
    body: unknown,
    files: UploadedBannerImages | undefined,
    write: (conn: any, form: BannerForm, img: Images) => Promise<{ id: number; old: string[] }>,
  ): Promise<{ id: number; old: string[] }> {
    const tmps = [...(files?.image || []), ...(files?.mypageImage || [])].map((f) => f.path);
    const written: string[] = [];
    try {
      const { value: form, errors } = parseBannerForm(body);
      const fields: FieldErrors = { ...errors };
      const home = files?.image?.[0];
      const my = files?.mypageImage?.[0];
      const kinds: Record<string, string> = {};
      for (const [name, file] of [['image', home], ['mypageImage', my]] as const) {
        const c = await this.checked(file);
        if (c && !c.ok) fields[name] = c.error;
        else if (c) kinds[name] = c.kind;
      }
      if (!home && form.imageUrl === '' && !fields.image && !fields.imageUrl) fields.image = 'required';
      if (fields.imageUrl && !fields.image) fields.image = fields.imageUrl;
      if (fields.mypageImageUrl && !fields.mypageImage) fields.mypageImage = fields.mypageImageUrl;
      delete fields.imageUrl;
      delete fields.mypageImageUrl;
      if (form.hospitalId !== null && !fields.hospitalId) {
        const q = hospitalExistsQuery(form.hospitalId);
        if ((await this.rows(q)).length === 0) fields.hospitalId = 'unknown';
      }
      if (Object.keys(fields).length > 0) throw new BadRequestException({ error: 'invalid', fields });

      let image = form.imageUrl;
      let mypage = form.mypageImageUrl;
      if (home || my) {
        const root = await resolveUploadRoot(this.log);
        if (home) image = await this.place(root, home.path, kinds.image as any, written);
        if (my) mypage = await this.place(root, my.path, kinds.mypageImage as any, written);
      }
      return await this.tx((conn) => write(conn, form, { image, mypage }));
    } catch (e) {
      for (const f of written) await fs.unlink(f).catch(() => undefined);
      throw e;
    } finally {
      for (const t of tmps) await fs.unlink(t).catch(() => undefined);
    }
  }

  /** Creates a banner: approved and active at once. */
  async create(adminId: number, adminName: string, body: unknown, files?: UploadedBannerImages) {
    const out = await this.save(body, files, async (conn, form, img) => {
      const q = insertBannerQuery(form, img.image, img.mypage, reviewerName(adminName));
      const [res] = (await conn.query(q.sql, q.values)) as any;
      return { id: Number(res?.insertId) || 0, old: [] };
    });
    this.log.log(`banner ${out.id} created by admin ${adminId}`);
    return { id: out.id };
  }

  /** Edits a banner. The caller sends the revision it read; a banner changed in between is never overwritten. */
  async edit(adminId: number, id: number, body: unknown, files?: UploadedBannerImages) {
    const revision = parseRevision(body);
    if (id <= 0 || !revision) {
      for (const f of [...(files?.image || []), ...(files?.mypageImage || [])]) await fs.unlink(f.path).catch(() => undefined);
      if (id <= 0) throw new NotFoundException({ error: 'not_found' });
      throw new BadRequestException({ error: 'invalid', fields: { revision: 'required' } });
    }
    const out = await this.save(body, files, async (conn, form, img) => {
      const row = await this.locked(conn, id);
      if (bannerRevision(row) !== revision) throw new ConflictException({ error: 'changed' });
      const q = updateBannerQuery(id, form, img.image, img.mypage);
      await conn.query(q.sql, q.values);
      const before = [str(row.image_url), str(row.mypage_image_url)];
      return { id, old: before.filter((u) => u !== img.image && u !== img.mypage) };
    });
    await this.cleanup(out.old);
    this.log.log(`banner ${id} edited by admin ${adminId}`);
    return { id };
  }
}
