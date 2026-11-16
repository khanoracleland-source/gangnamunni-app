import { BadRequestException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { randomBytes } from 'crypto';
import { constants as fsc, promises as fs } from 'fs';
import * as path from 'path';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { canSee, Query, Scope, seesNothing } from './hospitals.logic';
import {
  buildStoredPath, checkImage, detectImageKind, DETAIL_LIMIT, disableOtherMainsQuery, findMainQuery, hospitalUpdatedAtQuery, ImageField, ImageKind, imageRowsQuery,
  ImagesResult, insertImageQuery, interiorCountQuery, lockHospitalQuery, maxSortQuery, StoredPath, summarizeImages, syncMainUrlQuery, updateMainQuery,
} from './hospitals.images.logic';
import { UploadedImage, UploadedImages } from './hospitals.images.upload';

type Row = Record<string, unknown>;
type Run = (q: Query) => Promise<Row[]>;

export interface UploadResult extends ImagesResult {
  added: number;
  updatedAt: string | null;
}

/** Database problems that mean "the write user cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR', 'ER_NO_SUCH_TABLE',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040, 1146];
/** File system problems that mean "the upload folder cannot be used" rather than a bad file. */
const FS_UNAVAILABLE = ['EACCES', 'EPERM', 'EROFS', 'ENOENT', 'ENOTDIR'];

const num = (v: unknown): number => {
  const x = Number(v);
  return Number.isFinite(x) ? x : 0;
};

/** One validated file waiting to be stored. */
interface Pending {
  field: ImageField;
  tmp: string;
  kind: ImageKind;
}

/** Listing and adding hospital images. Reads use the read-only pool, an upload one transaction of the write pool plus files in ADMIN_UPLOAD_ROOT. */
@Injectable()
export class HospitalsImagesService {
  private readonly log = new Logger('HospitalsImages');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider) {}

  private readonly roRun: Run = async (q) => {
    const [rows] = (await this.ro.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  };

  /** An approved hospital inside the scope? */
  private async visibleApproved(scope: Scope, id: number): Promise<boolean> {
    if (id <= 0 || seesNothing(scope) || !canSee(scope, id)) return false;
    const rows = await this.roRun({ sql: `SELECT id FROM gangnamunni_hospitals WHERE id = ? AND status = 'approved' LIMIT 1`, values: [id] });
    return rows.length > 0;
  }

  async list(scope: Scope, id: number): Promise<ImagesResult> {
    if (!(await this.visibleApproved(scope, id))) throw new NotFoundException({ error: 'not_found' });
    return summarizeImages(await this.roRun(imageRowsQuery(id)));
  }

  /** The upload root when it is an absolute path to a folder this process can write to; otherwise 503 (the reason goes to the log only). */
  private async uploadRoot(): Promise<string> {
    const root = process.env.ADMIN_UPLOAD_ROOT || '';
    try {
      if (!root || !path.isAbsolute(root)) throw new Error('ADMIN_UPLOAD_ROOT is empty or not an absolute path');
      const st = await fs.stat(root);
      if (!st.isDirectory()) throw new Error('ADMIN_UPLOAD_ROOT is not a directory');
      await fs.access(root, fsc.W_OK | fsc.X_OK);
      return path.resolve(root);
    } catch (e) {
      this.log.warn(`upload root unusable: ${(e as { code?: string; message?: string })?.code || (e as Error)?.message}`);
      throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
    }
  }

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  /** Reads the first bytes of a stored temporary file and checks size, extension and real type. */
  private async validate(field: ImageField, f: UploadedImage): Promise<Pending> {
    let head = Buffer.alloc(0);
    let size = Number(f.size);
    try {
      const fh = await fs.open(f.path, 'r');
      try {
        const buf = Buffer.alloc(16);
        const { bytesRead } = await fh.read(buf, 0, 16, 0);
        head = buf.subarray(0, bytesRead);
        size = (await fh.stat()).size;
      } finally {
        await fh.close();
      }
    } catch {
      throw new BadRequestException({ error: 'upload_failed', field });
    }
    const check = checkImage(field, f.originalname, size, head);
    if (!check.ok) throw new BadRequestException({ error: check.error, field });
    // the detection is repeated on purpose: the stored extension always comes from the real type, never from the name
    if (detectImageKind(head) !== check.kind) throw new BadRequestException({ error: 'invalid_type', field });
    return { field, tmp: f.path, kind: check.kind };
  }

  async upload(scope: Scope, id: number, files: UploadedImages | undefined): Promise<UploadResult> {
    const mains = files?.main ?? [];
    const interiors = files?.interior ?? [];
    const tmpFiles = [...mains, ...interiors].map((f) => f.path);
    /** files this request wrote into the upload root; removed again if anything fails */
    const written: string[] = [];
    try {
      if (!(await this.visibleApproved(scope, id))) throw new NotFoundException({ error: 'not_found' });
      if (mains.length + interiors.length === 0) throw new BadRequestException({ error: 'no_files' });
      if (mains.length > 1) throw new BadRequestException({ error: 'too_many', field: 'main' });
      if (interiors.length > DETAIL_LIMIT) throw new BadRequestException({ error: 'too_many', field: 'interior', remaining: DETAIL_LIMIT });
      const root = await this.uploadRoot();
      const pending: Pending[] = [];
      for (const f of mains) pending.push(await this.validate('main', f));
      for (const f of interiors) pending.push(await this.validate('interior', f));

      const pool = this.pool();
      let conn;
      try {
        conn = await pool.getConnection();
      } catch (e) {
        this.unavailable(e);
        throw e;
      }
      try {
        await conn.beginTransaction();
        const run: Run = async (q) => {
          const [rows] = (await conn.query(q.sql, q.values)) as any;
          return Array.isArray(rows) ? rows : [];
        };
        const exec = async (q: Query): Promise<any> => ((await conn.query(q.sql, q.values)) as any)[0];

        // The hospital row is locked first, so two uploads of one hospital run one after the other and the limit cannot be passed.
        if ((await run(lockHospitalQuery(id))).length === 0) throw new NotFoundException({ error: 'not_found' });
        const existing = num((await run(interiorCountQuery(id)))[0]?.cnt);
        if (existing + interiors.length > DETAIL_LIMIT) {
          throw new BadRequestException({ error: 'too_many', field: 'interior', remaining: Math.max(0, DETAIL_LIMIT - existing) });
        }

        const store = async (p: Pending): Promise<StoredPath> => {
          const target = buildStoredPath(root, id, p.field, randomBytes(16).toString('hex'), p.kind);
          try {
            const created = await fs.mkdir(target.dir, { recursive: true, mode: 0o755 });
            if (created !== undefined) await fs.chmod(target.dir, 0o755).catch(() => undefined);
            written.push(target.absolute);
            await fs.copyFile(p.tmp, target.absolute, fsc.COPYFILE_EXCL);
            await fs.chmod(target.absolute, 0o644);
          } catch (e) {
            const code = (e as { code?: string })?.code || '';
            this.log.warn(`could not store an image: ${code || (e as Error)?.message}`);
            if (FS_UNAVAILABLE.includes(code)) throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
            throw new BadRequestException({ error: 'upload_failed', field: p.field });
          }
          return target;
        };

        const mainPending = pending.find((p) => p.field === 'main');
        if (mainPending) {
          const target = await store(mainPending);
          let mainId = num((await run(findMainQuery(id)))[0]?.id);
          if (mainId > 0) await exec(updateMainQuery(mainId, id, target.url));
          else mainId = num((await exec(insertImageQuery(id, target.url, 'main', 0)))?.insertId);
          if (mainId <= 0) throw new Error('main image row has no id');
          await exec(disableOtherMainsQuery(id, mainId));
          await exec(syncMainUrlQuery(id, target.url));
        }
        const interiorPending = pending.filter((p) => p.field === 'interior');
        if (interiorPending.length > 0) {
          let sort = num((await run(maxSortQuery(id)))[0]?.max_sort);
          for (const p of interiorPending) {
            const target = await store(p);
            sort += 1;
            await exec(insertImageQuery(id, target.url, 'interior', sort));
          }
        }
        const summary = summarizeImages(await run(imageRowsQuery(id)));
        const at = (await run(hospitalUpdatedAtQuery(id)))[0]?.updated_at;
        await conn.commit();
        this.log.log(`hospital images: hospital=${id} added=${pending.length}`);
        return { ...summary, added: pending.length, updatedAt: at == null ? null : String(at) };
      } catch (e) {
        await conn.rollback().catch(() => undefined);
        this.unavailable(e);
        throw e;
      } finally {
        conn.release();
      }
    } catch (e) {
      // only what this request wrote is removed; the previous main file and every other existing file stay
      await Promise.all(written.map((p) => fs.unlink(p).catch(() => undefined)));
      throw e;
    } finally {
      await Promise.all(tmpFiles.map((p) => fs.unlink(p).catch(() => undefined)));
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
