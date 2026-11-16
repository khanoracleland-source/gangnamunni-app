import { BadRequestException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { randomBytes } from 'crypto';
import { constants as fsc, promises as fs } from 'fs';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import {
  buildDoctorPhotoPath, DoctorInput, DoctorPhotoPath, doctorRowsQuery, DoctorsResult, insertDoctorQuery, parseDoctors, summarizeDoctors, updateDoctorPhotoQuery,
} from './hospitals.doctors.logic';
import { UploadedPhoto, UploadedPhotos } from './hospitals.doctors.upload';
import { checkImage, detectImageKind, ImageKind, lockHospitalQuery } from './hospitals.images.logic';
import { canSee, num, Query, Scope, seesNothing } from './hospitals.logic';
import { FS_UNAVAILABLE, readHead, resolveUploadRoot, rethrowIfWriteUnavailable } from './hospitals.upload.shared';

type Row = Record<string, unknown>;
type Run = (q: Query) => Promise<Row[]>;

export interface AddDoctorsResult extends DoctorsResult {
  added: number;
}

/** A validated photo waiting to be stored, with the doctor it belongs to (position in the submitted list). */
interface PendingPhoto {
  tmp: string;
  kind: ImageKind;
}

/** Listing and adding doctors of a hospital. Reads use the read-only pool, adding one transaction of the write pool plus photo files in ADMIN_UPLOAD_ROOT. */
@Injectable()
export class HospitalsDoctorsService {
  private readonly log = new Logger('HospitalsDoctors');

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

  async list(scope: Scope, id: number): Promise<DoctorsResult> {
    if (!(await this.visibleApproved(scope, id))) throw new NotFoundException({ error: 'not_found' });
    return summarizeDoctors(await this.roRun(doctorRowsQuery(id)));
  }

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  /** Size, extension and real type of one photo; the error names the doctor it belongs to. */
  private async validate(d: DoctorInput, f: UploadedPhoto): Promise<PendingPhoto> {
    let head: Buffer;
    let size: number;
    try {
      ({ head, size } = await readHead(f.path));
    } catch {
      throw new BadRequestException({ error: 'upload_failed', index: d.index, field: 'photo' });
    }
    const check = checkImage('doctor', f.originalname, size, head);
    if (!check.ok) throw new BadRequestException({ error: check.error, index: d.index, field: 'photo' });
    // the detection is repeated on purpose: the stored extension always comes from the real type, never from the name
    if (detectImageKind(head) !== check.kind) throw new BadRequestException({ error: 'invalid_type', index: d.index, field: 'photo' });
    return { tmp: f.path, kind: check.kind };
  }

  async add(scope: Scope, id: number, rawDoctors: unknown, files: UploadedPhotos | undefined): Promise<AddDoctorsResult> {
    const photos = files?.photo ?? [];
    const tmpFiles = photos.map((f) => f.path);
    /** photo files this request wrote into the upload root; removed again if anything fails */
    const written: string[] = [];
    try {
      if (!(await this.visibleApproved(scope, id))) throw new NotFoundException({ error: 'not_found' });
      const parsed = parseDoctors(rawDoctors, photos.length);
      if (!parsed.ok) throw new BadRequestException(parsed.body);
      const doctors = parsed.doctors;
      // photos nobody refers to (the row had no name) are ignored; only the used ones are checked
      const pending = new Map<number, PendingPhoto>();
      if (doctors.some((d) => d.photo !== null)) {
        const root = await resolveUploadRoot(this.log);
        for (const d of doctors) if (d.photo !== null) pending.set(d.index, await this.validate(d, photos[d.photo]));
        return await this.store(id, root, doctors, pending, written);
      }
      return await this.store(id, '', doctors, pending, written);
    } catch (e) {
      // only what this request wrote is removed; every other existing file stays
      await Promise.all(written.map((p) => fs.unlink(p).catch(() => undefined)));
      throw e;
    } finally {
      await Promise.all(tmpFiles.map((p) => fs.unlink(p).catch(() => undefined)));
    }
  }

  /** One transaction: lock the hospital, insert every doctor, write and link the photos, read the new list. */
  private async store(id: number, root: string, doctors: DoctorInput[], pending: Map<number, PendingPhoto>, written: string[]): Promise<AddDoctorsResult> {
    const pool = this.pool();
    let conn;
    try {
      conn = await pool.getConnection();
    } catch (e) {
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    }
    try {
      await conn.beginTransaction();
      const run: Run = async (q) => {
        const [rows] = (await conn.query(q.sql, q.values)) as any;
        return Array.isArray(rows) ? rows : [];
      };
      const exec = async (q: Query): Promise<any> => ((await conn.query(q.sql, q.values)) as any)[0];

      if ((await run(lockHospitalQuery(id))).length === 0) throw new NotFoundException({ error: 'not_found' });
      for (const d of doctors) {
        const doctorId = num((await exec(insertDoctorQuery(id, d)))?.insertId);
        if (doctorId <= 0) throw new Error('doctor row has no id');
        const photo = pending.get(d.index);
        if (!photo) continue;
        const target: DoctorPhotoPath = buildDoctorPhotoPath(root, id, doctorId, randomBytes(16).toString('hex'), photo.kind);
        try {
          const created = await fs.mkdir(target.dir, { recursive: true, mode: 0o755 });
          if (created !== undefined) await fs.chmod(target.dir, 0o755).catch(() => undefined);
          written.push(target.absolute);
          await fs.copyFile(photo.tmp, target.absolute, fsc.COPYFILE_EXCL);
          await fs.chmod(target.absolute, 0o644);
        } catch (e) {
          const code = (e as { code?: string })?.code || '';
          this.log.warn(`could not store a doctor photo: ${code || (e as Error)?.message}`);
          if (FS_UNAVAILABLE.includes(code)) throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
          throw new BadRequestException({ error: 'upload_failed', index: d.index, field: 'photo' });
        }
        await exec(updateDoctorPhotoQuery(doctorId, id, target.url));
      }
      const summary = summarizeDoctors(await run(doctorRowsQuery(id)));
      await conn.commit();
      this.log.log(`hospital doctors: hospital=${id} added=${doctors.length}`);
      return { ...summary, added: doctors.length };
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    } finally {
      conn.release();
    }
  }
}
