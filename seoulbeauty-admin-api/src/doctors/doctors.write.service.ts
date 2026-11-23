import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { randomBytes } from 'crypto';
import { constants as fsc, promises as fs } from 'fs';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { buildDoctorPhotoPath, DoctorPhotoPath } from '../hospitals/hospitals.doctors.logic';
import { UploadedPhoto, UploadedPhotos } from '../hospitals/hospitals.doctors.upload';
import { checkImage, detectImageKind, ImageKind } from '../hospitals/hospitals.images.logic';
import { canSee, num, Query, Scope, seesNothing, str } from '../hospitals/hospitals.logic';
import { OrderRow, parseMoveBody, planMove } from '../hospitals/hospitals.order.logic';
import { FS_UNAVAILABLE, readHead, resolveUploadRoot, rethrowIfWriteUnavailable } from '../hospitals/hospitals.upload.shared';
import { DoctorDetail, orderColumnQuery } from './doctors.logic';
import {
  clearDoctorRefQuery, deleteDoctorQuery, deleteRelationRowsQuery, hasDoctorIdQuery, HISTORY_TABLES, lockDoctorOrderQuery, RELATION_TABLES, setDoctorOrderQuery,
} from './doctors.order.logic';
import { DoctorsService } from './doctors.service';
import {
  assignTreatmentsQuery, deactivateAllAssignmentsQuery, deactivateAssignmentsQuery, DoctorEdit, lockApprovedHospitalQuery, lockDoctorQuery, offeredTreatmentsQuery,
  parseEdit, removablePhoto, retireDoctorQuery, updateDoctorQuery,
} from './doctors.write.logic';

type Row = Record<string, unknown>;
type Run = (q: Query) => Promise<Row[]>;

interface PendingPhoto {
  tmp: string;
  kind: ImageKind;
}

/** Editing and retiring a doctor: one transaction of the write pool (plus the photo file in ADMIN_UPLOAD_ROOT). The answer is read back through the read-only pool. */
@Injectable()
export class DoctorsWriteService {
  private readonly log = new Logger('DoctorsWrite');
  private orderColumn: boolean | null = null;

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider, private readonly doctors: DoctorsService) {}

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  private async hasOrder(): Promise<boolean> {
    if (this.orderColumn) return true;
    try {
      const [rows] = (await this.ro.query(orderColumnQuery().sql, [])) as any;
      this.orderColumn = Array.isArray(rows) && rows.length > 0;
    } catch {
      this.orderColumn = false;
    }
    return this.orderColumn;
  }

  private async validatePhoto(f: UploadedPhoto): Promise<PendingPhoto> {
    let head: Buffer;
    let size: number;
    try {
      ({ head, size } = await readHead(f.path));
    } catch {
      throw new BadRequestException({ error: 'upload_failed', field: 'photo' });
    }
    const check = checkImage('doctor', f.originalname, size, head);
    if (!check.ok) throw new BadRequestException({ error: check.error, field: 'photo' });
    if (detectImageKind(head) !== check.kind) throw new BadRequestException({ error: 'invalid_type', field: 'photo' });
    return { tmp: f.path, kind: check.kind };
  }

  /** A doctor inside the scope (read pool): the specialty stored now is needed to check the form. null = not found or outside the scope. */
  private async currentOf(scope: Scope, id: number): Promise<Row | null> {
    if (id <= 0 || seesNothing(scope)) return null;
    const [rows] = (await this.ro.query(`SELECT id, hospital_id, specialty, status FROM gangnamunni_doctors WHERE id = ? LIMIT 1`, [id])) as any;
    const row: Row | undefined = Array.isArray(rows) ? rows[0] : undefined;
    return row && canSee(scope, num(row.hospital_id)) ? row : null;
  }

  async edit(scope: Scope, id: number, rawDoctor: unknown, files: UploadedPhotos | undefined): Promise<DoctorDetail> {
    const photos = files?.photo ?? [];
    const tmpFiles = photos.map((f) => f.path);
    const written: string[] = [];
    try {
      const current = await this.currentOf(scope, id);
      if (!current) throw new NotFoundException({ error: 'not_found' });
      const parsed = parseEdit(rawDoctor, str(current.specialty));
      if (!parsed.ok) throw new BadRequestException(parsed.body);
      const edit = parsed.edit;
      // the new hospital must be visible too: a hospital account can never move a doctor away
      if (!canSee(scope, edit.hospitalId)) throw new NotFoundException({ error: 'not_found' });
      let photo: PendingPhoto | null = null;
      let root = '';
      if (photos.length) {
        root = await resolveUploadRoot(this.log);
        photo = await this.validatePhoto(photos[0]);
      }
      const oldPhoto = await this.store(scope, id, edit, root, photo, written);
      // committed: the new file is now referenced by the row and must survive whatever happens to the read-back below
      written.length = 0;
      if (oldPhoto && root) {
        const abs = removablePhoto(root, oldPhoto, id);
        if (abs) await fs.unlink(abs).catch(() => undefined);
      }
      const detail = await this.doctors.detail(scope, id);
      if (!detail) throw new NotFoundException({ error: 'not_found' });
      return detail;
    } catch (e) {
      await Promise.all(written.map((p) => fs.unlink(p).catch(() => undefined)));
      throw e;
    } finally {
      await Promise.all(tmpFiles.map((p) => fs.unlink(p).catch(() => undefined)));
    }
  }

  private async connect() {
    try {
      return await this.pool().getConnection();
    } catch (e) {
      if (e instanceof ServiceUnavailableException) throw e;
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    }
  }

  /** Returns the photo address that was replaced (to be removed after the commit), or '' when nothing was replaced. */
  private async store(scope: Scope, id: number, edit: DoctorEdit, root: string, photo: PendingPhoto | null, written: string[]): Promise<string> {
    const withOrder = await this.hasOrder();
    const conn = await this.connect();
    try {
      await conn.beginTransaction();
      const run: Run = async (q) => {
        const [rows] = (await conn.query(q.sql, q.values)) as any;
        return Array.isArray(rows) ? rows : [];
      };
      const locked = (await run(lockDoctorQuery(id)))[0];
      // the scope was checked on the read pool before; it is checked again on the locked row (the doctor may have been moved meanwhile)
      if (!locked || !canSee(scope, num(locked.hospital_id))) throw new NotFoundException({ error: 'not_found' });
      const oldHospital = num(locked.hospital_id);
      if (str(locked.status) === 'resigned') throw new ConflictException({ error: 'doctor_resigned' });
      if ((await run(lockApprovedHospitalQuery(edit.hospitalId))).length === 0) throw new BadRequestException({ error: 'invalid_field', field: 'hospitalId' });

      let photoUrl: string | null = null;
      if (photo) {
        const target: DoctorPhotoPath = buildDoctorPhotoPath(root, edit.hospitalId, id, randomBytes(16).toString('hex'), photo.kind);
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
          throw new BadRequestException({ error: 'upload_failed', field: 'photo' });
        }
        photoUrl = target.url;
      }

      await run(updateDoctorQuery(id, edit, photoUrl, withOrder && oldHospital !== edit.hospitalId));

      let keep: number[] = [];
      if (edit.treatmentIds.length) {
        keep = (await run(offeredTreatmentsQuery(edit.hospitalId, edit.treatmentIds))).map((r) => num(r.treatment_id)).filter((n) => n > 0);
      }
      await run(deactivateAssignmentsQuery(id, keep));
      if (keep.length) await run(assignTreatmentsQuery(id, edit.hospitalId, keep));

      await conn.commit();
      this.log.log(`doctor edited: id=${id} hospital=${edit.hospitalId}${oldHospital !== edit.hospitalId ? ` (was ${oldHospital})` : ''} treatments=${keep.length}${photoUrl ? ' photo=new' : ''}`);
      return photoUrl ? str(locked.photo_url) : '';
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    } finally {
      conn.release();
    }
  }

  /** Retire: status resigned, out of the display order, every assignment switched off. Retiring a retired doctor changes nothing. */
  async retire(scope: Scope, id: number): Promise<DoctorDetail> {
    if (!(await this.currentOf(scope, id))) throw new NotFoundException({ error: 'not_found' });
    const withOrder = await this.hasOrder();
    const conn = await this.connect();
    try {
      await conn.beginTransaction();
      const run: Run = async (q) => {
        const [rows] = (await conn.query(q.sql, q.values)) as any;
        return Array.isArray(rows) ? rows : [];
      };
      const locked = (await run(lockDoctorQuery(id)))[0];
      if (!locked || !canSee(scope, num(locked.hospital_id))) throw new NotFoundException({ error: 'not_found' });
      if (str(locked.status) !== 'resigned') {
        await run(retireDoctorQuery(id, withOrder));
        await run(deactivateAllAssignmentsQuery(id));
      }
      await conn.commit();
      this.log.log(`doctor retired: id=${id}`);
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    } finally {
      conn.release();
    }
    const detail = await this.doctors.detail(scope, id);
    if (!detail) throw new NotFoundException({ error: 'not_found' });
    return detail;
  }
  /** Moves one active doctor inside the order of the hospital and renumbers that hospital's active doctors 1..N. */
  async move(scope: Scope, adminId: number, id: number, body: unknown): Promise<{ ok: true; id: number; position: number; total: number; changed: number }> {
    const parsed = parseMoveBody(body);
    const current = await this.currentOf(scope, id);
    if (!current) throw new NotFoundException({ error: 'not_found' });
    if (!parsed.ok) throw new BadRequestException({ error: 'invalid', fields: parsed.fields });
    if (!(await this.hasOrder())) throw new ConflictException({ error: 'order_unavailable' });
    const conn = await this.connect();
    try {
      await conn.beginTransaction();
      const run: Run = async (q) => {
        const [rows] = (await conn.query(q.sql, q.values)) as any;
        return Array.isArray(rows) ? rows : [];
      };
      // lock order: the whole set of the hospital first (the doctor's row is part of it), then the row itself; every move takes the locks in this order, so two moves never deadlock
      const hospitalId = num(current.hospital_id);
      const rows: OrderRow[] = (await run(lockDoctorOrderQuery(hospitalId))).map((r) => ({
        id: num(r.id),
        order: r.display_order === null || r.display_order === undefined ? null : num(r.display_order),
      }));
      const locked = (await run(lockDoctorQuery(id)))[0];
      if (!locked || !canSee(scope, num(locked.hospital_id))) throw new NotFoundException({ error: 'not_found' });
      // moved to another hospital or retired meanwhile: the set that was locked is not the one this doctor belongs to
      if (num(locked.hospital_id) !== hospitalId) throw new ConflictException({ error: 'busy' });
      if (str(locked.status) !== 'active') throw new ConflictException({ error: 'doctor_resigned' });
      const plan = planMove(rows, id, parsed.move);
      if (!plan.found) throw new NotFoundException({ error: 'not_found' });
      if (plan.outOfRange) throw new BadRequestException({ error: 'invalid', fields: { position: 'out_of_range' } });
      for (const u of plan.updates) await run(setDoctorOrderQuery(u.id, u.position));
      await conn.commit();
      if (plan.updates.length > 0) this.log.log(`doctor order: admin=${adminId} doctor=${id} position=${plan.position} changed=${plan.updates.length}`);
      return { ok: true, id, position: plan.position, total: plan.total, changed: plan.updates.length };
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      if ([1213, 1205].includes((e as { errno?: number })?.errno || 0)) throw new ConflictException({ error: 'busy' });
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    } finally {
      conn.release();
    }
  }

  /**
   * Permanent delete of a RETIRED doctor: bookings and reviews keep their rows (the doctor reference is cleared), assignments and schedules go with the doctor.
   * If something else still points at the doctor the database refuses and nothing changes (409 has_references).
   */
  async remove(scope: Scope, adminId: number, id: number): Promise<{ ok: true; id: number; name: string }> {
    const current = await this.currentOf(scope, id);
    if (!current) throw new NotFoundException({ error: 'not_found' });
    const conn = await this.connect();
    let photoUrl = '';
    let name = '';
    try {
      await conn.beginTransaction();
      const run: Run = async (q) => {
        const [rows] = (await conn.query(q.sql, q.values)) as any;
        return Array.isArray(rows) ? rows : [];
      };
      const exec = async (q: Query): Promise<any> => ((await conn.query(q.sql, q.values)) as any)[0];
      const locked = (await run(lockDoctorQuery(id)))[0];
      if (!locked || !canSee(scope, num(locked.hospital_id))) throw new NotFoundException({ error: 'not_found' });
      if (str(locked.status) !== 'resigned') throw new ConflictException({ error: 'not_resigned' });
      photoUrl = str(locked.photo_url);
      name = str(locked.name);
      for (const t of HISTORY_TABLES) if ((await run(hasDoctorIdQuery(t))).length) await exec(clearDoctorRefQuery(t, id));
      for (const t of RELATION_TABLES) if ((await run(hasDoctorIdQuery(t))).length) await exec(deleteRelationRowsQuery(t, id));
      const res = await exec(deleteDoctorQuery(id));
      if (num(res?.affectedRows) !== 1) throw new NotFoundException({ error: 'not_found' });
      await conn.commit();
      this.log.log(`doctor deleted: admin=${adminId} doctor=${id}`);
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      if ((e as { errno?: number })?.errno === 1451 || (e as { code?: string })?.code === 'ER_ROW_IS_REFERENCED_2') throw new ConflictException({ error: 'has_references' });
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    } finally {
      conn.release();
    }
    // the picture goes only after the commit, and only when this panel or the old page wrote it; a missing upload folder never undoes the delete
    if (photoUrl) {
      try {
        const root = await resolveUploadRoot(this.log);
        const abs = removablePhoto(root, photoUrl, id);
        if (abs) await fs.unlink(abs).catch(() => undefined);
      } catch {
        /* uploads_unavailable already logged */
      }
    }
    return { ok: true, id, name };
  }
}
