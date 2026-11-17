import { BadRequestException, ConflictException, ForbiddenException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { randomBytes } from 'crypto';
import { constants as fsc, promises as fs } from 'fs';
import { HOSPITAL_ROLES } from '../admin-auth/admin.guard';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { canSee, num, Query, Scope, seesNothing } from './hospitals.logic';
import { hospitalExistsQuery, isMissingObject } from './hospitals.treatments.logic';
import { HospitalsTreatmentsService } from './hospitals.treatments.service';
import {
  assignmentExistsQuery, buildImages, defaultImageQuery, moveApproved, buildTreatmentImagePath, canReviewImages, checkTreatmentImage, deleteImageQuery, imageRowsQuery, insertImageQuery, lockAssignmentForImagesQuery, lockImageOrderQuery,
  lockImageUrlQuery, nextSortQuery, parseCaption, parseImageUrl, parseMoveBody, parseReviewBody, removablePath, reviewerName, reviewImageQuery, setSortOrderQuery, StoredTreatmentImage,
  TreatmentImage, TreatmentImageKind,
} from './hospitals.treatments.images.logic';
import { UploadedTreatmentImages } from './hospitals.treatments.images.upload';
import { FS_UNAVAILABLE, readHead, resolveUploadRoot, rethrowIfWriteUnavailable } from './hospitals.upload.shared';

type Row = Record<string, unknown>;
type Run = (q: Query) => Promise<Row[]>;
type Exec = (q: Query) => Promise<{ affectedRows?: number; insertId?: number }>;

export interface ImageActor {
  id: number;
  role: string;
  name: string;
}
export interface ImagesAnswer {
  items: TreatmentImage[];
  canReview: boolean;
  defaultImageUrl: string | null;
}

/** A validated upload waiting to be stored. */
interface PendingFile {
  tmp: string;
  kind: TreatmentImageKind;
}

/**
 * Detail images of one treatment of a hospital: list, add (file or URL), delete, reorder and review.
 * Reads use the read-only pool, writes one transaction of the write pool (plus the file in ADMIN_UPLOAD_ROOT for an upload).
 */
@Injectable()
export class HospitalsTreatmentImagesService {
  private readonly log = new Logger('HospitalsTreatmentImages');

  constructor(
    @Inject(DB) private readonly ro: Db,
    @Inject(DB_RW) private readonly rw: RwDbProvider,
    private readonly treatments: HospitalsTreatmentsService,
  ) {}

  private readonly roRun: Run = async (q) => {
    const [rows] = (await this.ro.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  };

  /** The hospital is inside the scope and exists (404; with `approvedOnly` it must be approved too), and the assignment belongs to it (404). */
  private async assertVisible(scope: Scope, id: number, htId: number, approvedOnly = true): Promise<void> {
    if (id <= 0 || htId <= 0 || seesNothing(scope) || !canSee(scope, id)) throw new NotFoundException({ error: 'not_found' });
    if ((await this.roRun(hospitalExistsQuery(id, approvedOnly))).length === 0) throw new NotFoundException({ error: 'not_found' });
    if ((await this.roRun(assignmentExistsQuery(htId, id))).length === 0) throw new NotFoundException({ error: 'not_found' });
  }

  /** The list of images, re-read from the read-only pool; a missing table or grant is its own answer. */
  private async read(htId: number): Promise<TreatmentImage[]> {
    try {
      return buildImages(await this.roRun(imageRowsQuery(htId)));
    } catch (e) {
      if (!isMissingObject(e)) throw e;
      this.log.warn(`treatment images unavailable: ${(e as { code?: string; message?: string })?.code || (e as Error)?.message}`);
      throw new ServiceUnavailableException({ error: 'images_unavailable' });
    }
  }

  async list(scope: Scope, actor: ImageActor, id: number, htId: number): Promise<ImagesAnswer> {
    await this.assertVisible(scope, id, htId, false);
    return { items: await this.read(htId), canReview: canReviewImages(actor.role), defaultImageUrl: await this.defaultImage(htId, id) };
  }

  /** The treatment's standard picture; a missing column or grant (or anything unreadable of that kind) is silently null. */
  private async defaultImage(htId: number, hospitalId: number): Promise<string | null> {
    try {
      const url = String((await this.roRun(defaultImageQuery(htId, hospitalId)))[0]?.default_image_url ?? '').trim();
      return url === '' ? null : url;
    } catch (e) {
      if (!isMissingObject(e)) throw e;
      this.log.warn(`default treatment image unavailable: ${(e as { code?: string })?.code}`);
      return null;
    }
  }

  /** Visible (404) and the actor may manage treatments (403 forbidden_treatments). */
  private async allowManage(scope: Scope, actor: ImageActor, id: number, htId: number): Promise<void> {
    await this.assertVisible(scope, id, htId);
    if (!(await this.treatments.canManage(actor.role))) throw new ForbiddenException({ error: 'forbidden_treatments' });
  }

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  /** One transaction of the write pool: commit when `fn` returns, full rollback when it throws. */
  private async tx<T>(fn: (run: Run, exec: Exec) => Promise<T>): Promise<T> {
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
      const exec: Exec = async (q) => ((await conn.query(q.sql, q.values)) as any)[0] ?? {};
      const out = await fn(run, exec);
      await conn.commit();
      return out;
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    } finally {
      conn.release();
    }
  }

  private logAction(actor: ImageActor, hospital: number, action: string, ht: number, image: number): void {
    // neither the address nor the caption nor the reason is written
    this.log.log(`hospital treatments: admin=${actor.id} hospital=${hospital} image ${action} treatment=${ht} image=${image}`);
  }

  async add(scope: Scope, actor: ImageActor, id: number, htId: number, rawBody: unknown, files: UploadedTreatmentImages | undefined): Promise<{ item: TreatmentImage; items: TreatmentImage[] }> {
    const upload = files?.image?.[0];
    /** the file this request wrote into the upload root; removed again if anything fails */
    const written: string[] = [];
    try {
      await this.allowManage(scope, actor, id, htId);
      const body = (rawBody !== null && typeof rawBody === 'object' ? rawBody : {}) as Record<string, unknown>;
      // a file wins over the address (the address is not even looked at then)
      let url: string | null = null;
      if (!upload) {
        const parsed = parseImageUrl(body.imageUrl);
        if (!parsed.ok) throw new BadRequestException({ error: 'invalid_url' });
        if (parsed.url === null) throw new BadRequestException({ error: 'image_required' });
        url = parsed.url;
      }
      const caption = parseCaption(body.caption);
      if (!caption.ok) throw new BadRequestException({ error: caption.error });
      let pending: PendingFile | null = null;
      let root = '';
      if (upload) {
        root = await resolveUploadRoot(this.log);
        pending = await this.validate(upload.path, upload.originalname);
      }
      // decided here, never by the client: a hospital account's image waits for review, a super admin's is published at once
      const hospitalUploaded = HOSPITAL_ROLES.includes(actor.role);
      const imageId = await this.tx(async (run, exec) => {
        const owner = await run(lockAssignmentForImagesQuery(htId, id));
        if (owner.length === 0) throw new NotFoundException({ error: 'not_found' });
        const sort = num((await run(nextSortQuery(htId)))[0]?.next_sort);
        let stored = url ?? '';
        if (pending) stored = (await this.place(root, pending, written)).url;
        const res = await exec(insertImageQuery(htId, id, num(owner[0].treatment_id), stored, caption.caption, sort, hospitalUploaded ? 'pending' : 'approved', hospitalUploaded ? 'hospital' : 'admin'));
        const newId = num(res.insertId);
        if (newId <= 0) throw new Error('image row has no id');
        return newId;
      });
      this.logAction(actor, id, 'add', htId, imageId);
      const items = await this.read(htId);
      const item = items.find((i) => i.id === imageId);
      if (!item) throw new NotFoundException({ error: 'not_found' });
      return { item, items };
    } catch (e) {
      // only what this request wrote is removed; every other file stays
      await Promise.all(written.map((p) => fs.unlink(p).catch(() => undefined)));
      throw e;
    } finally {
      if (upload) await fs.unlink(upload.path).catch(() => undefined);
    }
  }

  /** Size, extension and real type of the uploaded file. */
  private async validate(tmp: string, originalName: string): Promise<PendingFile> {
    let head: Buffer;
    let size: number;
    try {
      ({ head, size } = await readHead(tmp));
    } catch {
      throw new BadRequestException({ error: 'upload_failed' });
    }
    const check = checkTreatmentImage(originalName, size, head);
    if (!check.ok) throw new BadRequestException({ error: check.error });
    return { tmp, kind: check.kind };
  }

  /** Copies the validated file into <root>/uploads/treatments/ (a name clash is retried with a new random part). */
  private async place(root: string, file: PendingFile, written: string[]): Promise<StoredTreatmentImage> {
    for (let attempt = 0; ; attempt++) {
      const target = buildTreatmentImagePath(root, Math.floor(Date.now() / 1000), randomBytes(4).toString('hex'), file.kind);
      try {
        try {
          const created = await fs.mkdir(target.dir, { recursive: true, mode: 0o755 });
          if (created !== undefined) await fs.chmod(target.dir, 0o755).catch(() => undefined);
        } catch (e) {
          // a file where the folder should be is a folder problem, not a name clash
          const code = (e as { code?: string })?.code || '';
          this.log.warn(`treatment image folder unusable: ${code || (e as Error)?.message}`);
          throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
        }
        written.push(target.absolute);
        await fs.copyFile(file.tmp, target.absolute, fsc.COPYFILE_EXCL);
        await fs.chmod(target.absolute, 0o644);
        return target;
      } catch (e) {
        if (e instanceof ServiceUnavailableException) throw e;
        const code = (e as { code?: string })?.code || '';
        if (code === 'EEXIST' && attempt < 3) {
          written.pop(); // the clashing file is not ours
          continue;
        }
        this.log.warn(`could not store a treatment image: ${code || (e as Error)?.message}`);
        if (FS_UNAVAILABLE.includes(code)) throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
        throw new BadRequestException({ error: 'upload_failed' });
      }
    }
  }

  async remove(scope: Scope, actor: ImageActor, id: number, htId: number, imageId: number): Promise<{ items: TreatmentImage[] }> {
    await this.allowManage(scope, actor, id, htId);
    if (imageId <= 0) throw new NotFoundException({ error: 'not_found' });
    const url = await this.tx(async (run, exec) => {
      if ((await run(lockAssignmentForImagesQuery(htId, id))).length === 0) throw new NotFoundException({ error: 'not_found' });
      const rows = await run(lockImageUrlQuery(imageId, htId));
      if (rows.length === 0) throw new NotFoundException({ error: 'not_found' });
      await exec(deleteImageQuery(imageId, htId));
      return String(rows[0].image_url ?? '');
    });
    this.logAction(actor, id, 'delete', htId, imageId);
    await this.unlinkStored(url);
    return { items: await this.read(htId) };
  }

  /** The stored file goes only after the row is gone, and a failure here never fails the request. */
  private async unlinkStored(url: string): Promise<void> {
    const target = removablePath(process.env.ADMIN_UPLOAD_ROOT || '', url);
    if (!target) return;
    try {
      await fs.unlink(target);
    } catch (e) {
      const code = (e as { code?: string })?.code || '';
      if (code !== 'ENOENT') this.log.warn(`could not remove a treatment image file: ${code || (e as Error)?.message}`);
    }
  }

  async move(scope: Scope, actor: ImageActor, id: number, htId: number, imageId: number, body: unknown): Promise<{ items: TreatmentImage[] }> {
    await this.allowManage(scope, actor, id, htId);
    const parsed = parseMoveBody(body);
    if (!parsed.ok) throw new BadRequestException(parsed.body);
    await this.tx(async (run, exec) => {
      if ((await run(lockAssignmentForImagesQuery(htId, id))).length === 0) throw new NotFoundException({ error: 'not_found' });
      const rows = (await run(lockImageOrderQuery(htId))).map((r) => ({ id: num(r.id), status: String(r.approval_status ?? '') }));
      const moved = moveApproved(rows, imageId, parsed.direction);
      if (!moved.ok) {
        if (moved.error === 'not_found') throw new NotFoundException({ error: 'not_found' });
        throw new ConflictException({ error: moved.error });
      }
      const order = moved.order;
      // the whole list is numbered again from 0, like the PHP page
      for (let i = 0; i < order.length; i++) await exec(setSortOrderQuery(i, order[i], htId));
    });
    this.logAction(actor, id, `move-${parsed.direction}`, htId, imageId);
    return { items: await this.read(htId) };
  }

  async review(scope: Scope, actor: ImageActor, id: number, htId: number, imageId: number, body: unknown): Promise<{ item: TreatmentImage; items: TreatmentImage[] }> {
    await this.assertVisible(scope, id, htId);
    if (!canReviewImages(actor.role)) throw new ForbiddenException({ error: 'forbidden_review' });
    const parsed = parseReviewBody(body);
    if (!parsed.ok) throw new BadRequestException(parsed.body);
    const approve = parsed.decision === 'approve';
    await this.tx(async (run, exec) => {
      if ((await run(lockAssignmentForImagesQuery(htId, id))).length === 0) throw new NotFoundException({ error: 'not_found' });
      const res = await exec(reviewImageQuery(approve ? 'approved' : 'rejected', approve ? null : parsed.reason, reviewerName(actor.name), imageId, htId));
      // 0 rows: already reviewed (or not there; the PHP page gives one answer for both)
      if (num(res.affectedRows) === 0) throw new ConflictException({ error: 'already_reviewed' });
    });
    this.logAction(actor, id, approve ? 'approve' : 'reject', htId, imageId);
    const items = await this.read(htId);
    const item = items.find((i) => i.id === imageId);
    if (!item) throw new NotFoundException({ error: 'not_found' });
    return { item, items };
  }
}
