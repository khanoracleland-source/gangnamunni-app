import { BadRequestException, ConflictException, HttpException, Inject, Injectable, Logger, ServiceUnavailableException } from '@nestjs/common';
import { randomBytes } from 'crypto';
import { constants as fsc, promises as fs } from 'fs';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { buildDoctorPhotoPath, insertDoctorQuery, updateDoctorPhotoQuery } from '../hospitals/hospitals.doctors.logic';
import { ImageKind, insertImageQuery } from '../hospitals/hospitals.images.logic';
import { num, Query, str } from '../hospitals/hospitals.logic';
import { activeMasterQuery, buildUnits, insertAssignmentQuery, insertPriceQuery, unitsQuery } from '../hospitals/hospitals.treatments.logic';
import { buildTreatmentImagePath, insertImageQuery as insertTreatmentImageQuery, TreatmentImageKind } from '../hospitals/hospitals.treatments.images.logic';
import { FS_UNAVAILABLE, readHead, resolveUploadRoot, rethrowIfWriteUnavailable } from '../hospitals/hospitals.upload.shared';
import { phoneNormalize } from '../hospitals/hospitals.write.logic';
import {
  ApplyErrorBody, ApplyInput, buildApplyPath, devModeQuery, FieldKind, insertHospitalQuery, INTERIOR_MAX, judgeFile, kindOfField, LIMITS, optionCategoriesQuery,
  optionTreatmentsQuery, parseHead, parsePayload, phoneHoldersQuery, TREATMENT_IMAGES_MAX,
} from './hospital-apply.logic';
import { ApplyUpload } from './hospital-apply.upload';

type Row = Record<string, unknown>;
type Run = (q: Query) => Promise<Row[]>;

export interface ApplyAdmin {
  id: number;
  username: string;
}
export interface ApplyResult {
  id: number;
  warnings: { code: 'price_rows_skipped'; count: number }[];
}

/** A file that passed its checks: where it came from, the field it belongs to and the extension its real type gives. */
interface Accepted {
  upload: ApplyUpload;
  kind: FieldKind;
  ext: string;
}

const body = (b: ApplyErrorBody): BadRequestException => new BadRequestException(b);
const fieldError = (field: string): BadRequestException => body({ error: 'invalid_field', field });

/** Lists for the form, the duplicate-phone question and the one request that registers a hospital (all of it in one transaction). */
@Injectable()
export class HospitalApplyService {
  private readonly log = new Logger('HospitalApply');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider) {}

  private readonly roRun: Run = async (q) => {
    const [rows] = (await this.ro.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  };

  /** "dev mode" setting of the PHP admin: hospitals made while it is on are demo hospitals. A missing row or grant means off. */
  private async devMode(): Promise<boolean> {
    try {
      const rows = await this.roRun(devModeQuery());
      return rows.length > 0 && str(rows[0].setting_value) === 'on';
    } catch (e) {
      this.log.warn(`dev mode setting unreadable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
      return false;
    }
  }

  async options() {
    const [cats, treatments, devMode] = await Promise.all([this.roRun(optionCategoriesQuery()), this.roRun(optionTreatmentsQuery()), this.devMode()]);
    let unitRows: Row[] = [];
    try {
      unitRows = await this.roRun(unitsQuery());
    } catch (e) {
      // the unit table is optional: the standard list is used without it
      this.log.warn(`units unreadable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
    }
    const items = treatments.map((r) => ({ id: num(r.id), name: str(r.name), categoryId: num(r.category_id) }));
    const used = new Set(items.map((t) => t.categoryId));
    return {
      categories: cats.map((r) => ({ id: num(r.id), name: str(r.name) })).filter((c) => used.has(c.id)),
      treatments: items,
      units: buildUnits(unitRows),
      devMode,
      limits: LIMITS,
    };
  }

  /** Is this phone number (in any formatting) used by a hospital? */
  async phoneCheck(raw: unknown): Promise<{ duplicate: boolean; hospitalName?: string }> {
    const phone = typeof raw === 'string' ? raw.trim() : '';
    const needle = phoneNormalize(phone);
    if (needle === '' || Array.from(phone).length > 20) throw fieldError('phone');
    const holder = (await this.roRun(phoneHoldersQuery())).find((r) => phoneNormalize(r.phone) === needle);
    return holder ? { duplicate: true, hospitalName: str(holder.name) } : { duplicate: false };
  }

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  /** Sorts the received files by field and checks size, extension and real type of each; a refused file stops the request and names its field. */
  private async accept(files: ApplyUpload[], input: ApplyInput): Promise<Map<string, Accepted[]>> {
    const keptDoctors = new Set(input.doctors.map((d) => d.index));
    const treatmentIds = new Set(input.treatments.map((t) => t.treatmentId));
    const byField = new Map<string, ApplyUpload[]>();
    for (const f of files) byField.set(f.fieldname, [...(byField.get(f.fieldname) ?? []), f]);

    const out = new Map<string, Accepted[]>();
    for (const [name, list] of byField) {
      const kind = kindOfField(name);
      if (kind === 'doctorPhoto' && !keptDoctors.has(Number(name.split('_')[1]))) continue; // a row without a name is left out with its photo
      if (kind === 'treatmentImages' && !treatmentIds.has(Number(name.split('_')[1]))) throw fieldError(name);
      const max = kind === 'interiorImages' ? INTERIOR_MAX : kind === 'treatmentImages' ? TREATMENT_IMAGES_MAX : 1;
      if (list.length > max) throw body({ error: 'too_many', field: name });
      const accepted: Accepted[] = [];
      for (const upload of list) {
        let head: Buffer;
        let size: number;
        try {
          ({ head, size } = await readHead(upload.path));
        } catch {
          throw fieldError(name);
        }
        const verdict = judgeFile(kind, upload.originalname, size, head);
        if (!verdict.ok) {
          if (verdict.error === 'file_too_large') throw new HttpException({ error: 'file_too_large', field: name }, 413);
          throw new HttpException({ error: 'bad_file_type', field: name }, 415);
        }
        accepted.push({ upload, kind, ext: verdict.ext });
      }
      out.set(name, accepted);
    }
    return out;
  }

  /** Copies a checked file to its place (the folder is made when missing; an existing file is never overwritten). */
  private async place(tmp: string, target: { dir: string; absolute: string }, field: string, written: string[]): Promise<void> {
    try {
      const created = await fs.mkdir(target.dir, { recursive: true, mode: 0o755 });
      if (created !== undefined) await fs.chmod(target.dir, 0o755).catch(() => undefined);
      // listed only once the copy succeeded: a name that already existed must never be removed by this request's cleanup
      await fs.copyFile(tmp, target.absolute, fsc.COPYFILE_EXCL);
      written.push(target.absolute);
      await fs.chmod(target.absolute, 0o644);
    } catch (e) {
      const code = (e as { code?: string })?.code || '';
      this.log.warn(`could not store an uploaded file: ${code || (e as Error)?.message}`);
      if (FS_UNAVAILABLE.includes(code)) throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
      throw body({ error: 'invalid_field', field });
    }
  }

  async create(admin: ApplyAdmin, rawPayload: unknown, files: ApplyUpload[] | undefined): Promise<ApplyResult> {
    const uploads = files ?? [];
    const tmpFiles = uploads.map((f) => f.path);
    /** files this request wrote into the upload root; removed again if anything fails */
    const written: string[] = [];
    try {
      // 1. the name, 2. the phone number (it must not belong to another hospital), 3. the e-mail and the rest of the form: the order of the PHP page
      const head = parseHead(rawPayload);
      if (!head.ok) throw body(head.body);
      if (head.value.phone !== '') {
        const dup = await this.phoneCheck(head.value.phone);
        if (dup.duplicate) throw new ConflictException({ error: 'duplicate_phone', hospitalName: dup.hospitalName ?? '' });
      }
      const parsed = parsePayload(rawPayload);
      if (!parsed.ok) throw body(parsed.body);
      const input = parsed.value;

      // 3. the treatments must exist in the master list and be switched on
      if (input.treatments.length > 0) {
        const ids = input.treatments.map((t) => t.treatmentId);
        const found = new Set((await this.roRun(activeMasterQuery(ids))).map((r) => num(r.id)));
        const missing = input.treatments.findIndex((t) => !found.has(t.treatmentId));
        if (missing >= 0) throw body({ error: 'invalid_field', field: 'treatments', index: missing });
      }

      // 4. the files: type, size, count
      const accepted = await this.accept(uploads, input);
      const used = [...accepted.values()].reduce((n, l) => n + l.length, 0);
      const root = used > 0 ? await resolveUploadRoot(this.log) : '';
      const pool = this.pool();
      const isDemo = (await this.devMode()) ? 1 : 0;

      // 5. the files of the hospital itself get their place first (they need no hospital id)
      const one = (field: string): Accepted | undefined => accepted.get(field)?.[0];
      const hex = (): string => randomBytes(16).toString('hex');
      const putApply = async (a: Accepted | undefined, prefix: 'cert_business' | 'cert_medical' | 'profile' | 'interior', field: string): Promise<string> => {
        if (!a) return '';
        const target = buildApplyPath(root, admin.id, prefix, hex(), a.ext);
        await this.place(a.upload.path, target, field, written);
        return target.url;
      };
      const certBusiness = await putApply(one('certBusiness'), 'cert_business', 'certBusiness');
      const certMedical = await putApply(one('certMedical'), 'cert_medical', 'certMedical');
      const profileUrl = await putApply(one('profileImage'), 'profile', 'profileImage');
      const interiorUrls: string[] = [];
      for (const a of accepted.get('interiorImages') ?? []) interiorUrls.push(await putApply(a, 'interior', 'interiorImages'));

      // 6. one transaction for every row
      let conn;
      try {
        conn = await pool.getConnection();
      } catch (e) {
        rethrowIfWriteUnavailable(e, this.log);
        throw e;
      }
      try {
        await conn.beginTransaction();
        const exec = async (q: Query): Promise<any> => ((await conn.query(q.sql, q.values)) as any)[0];
        const by = admin.username.slice(0, 100);
        const hospitalId = num((await exec(insertHospitalQuery({ input, appliedBy: by, reviewedBy: by, profileUrl, certBusiness, certMedical, isDemo })))?.insertId);
        if (hospitalId <= 0) throw new Error('hospital row has no id');

        if (profileUrl !== '') await exec(insertImageQuery(hospitalId, profileUrl, 'main', 0));
        for (let i = 0; i < interiorUrls.length; i += 1) await exec(insertImageQuery(hospitalId, interiorUrls[i], 'interior', i + 1));

        for (const d of input.doctors) {
          const doctorId = num((await exec(insertDoctorQuery(hospitalId, { ...d, photo: null })))?.insertId);
          if (doctorId <= 0) throw new Error('doctor row has no id');
          const photo = one(`doctorPhoto_${d.index}`);
          if (!photo) continue;
          const target = buildDoctorPhotoPath(root, hospitalId, doctorId, hex(), photo.ext as ImageKind);
          await this.place(photo.upload.path, target, `doctorPhoto_${d.index}`, written);
          await exec(updateDoctorPhotoQuery(doctorId, hospitalId, target.url));
        }

        for (const t of input.treatments) {
          const htId = num((await exec(insertAssignmentQuery(hospitalId, t.treatmentId, 'hospital')))?.insertId);
          if (htId <= 0) throw new Error('assignment row has no id');
          for (let i = 0; i < t.prices.length; i += 1) {
            const p = t.prices[i];
            await exec(insertPriceQuery(htId, { optionName: p.optionName, bodyPart: '', dosageValue: p.dosageValue, dosageUnit: p.dosageUnit, regularPrice: 0, price: p.price, isRepresentative: false }, i));
          }
          const images = accepted.get(`treatmentImages_${t.treatmentId}`) ?? [];
          for (let i = 0; i < images.length; i += 1) {
            const target = buildTreatmentImagePath(root, Math.floor(Date.now() / 1000), randomBytes(4).toString('hex'), images[i].ext as TreatmentImageKind);
            await this.place(images[i].upload.path, target, `treatmentImages_${t.treatmentId}`, written);
            await exec(insertTreatmentImageQuery(htId, hospitalId, t.treatmentId, target.url, '', i, 'pending', 'hospital'));
          }
        }

        await conn.commit();
        this.log.log(`hospital registered: id=${hospitalId} admin=${admin.id} doctors=${input.doctors.length} treatments=${input.treatments.length} files=${used}`);
        return { id: hospitalId, warnings: input.skippedPriceRows > 0 ? [{ code: 'price_rows_skipped', count: input.skippedPriceRows }] : [] };
      } catch (e) {
        await conn.rollback().catch(() => undefined);
        rethrowIfWriteUnavailable(e, this.log);
        throw e;
      } finally {
        conn.release();
      }
    } catch (e) {
      // only what this request wrote is removed; every other existing file stays
      await Promise.all(written.map((p) => fs.unlink(p).catch(() => undefined)));
      throw e;
    } finally {
      await Promise.all(tmpFiles.map((p) => fs.unlink(p).catch(() => undefined)));
    }
  }
}
