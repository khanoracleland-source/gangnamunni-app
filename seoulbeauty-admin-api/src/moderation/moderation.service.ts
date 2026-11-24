import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { hospitalOptionsQuery, toHospitalOption } from '../doctors/doctors.logic';
import { canSee, num, Query, Scope, seesNothing, str } from '../hospitals/hospitals.logic';
import {
  decidePostQuery, decideReviewQuery, Decision, deletePostQuery, deleteReviewImagesByUrlQuery, deleteReviewQuery, emptyPage, hospitalTreatmentNamesQuery, lengthErrors, lockPostQuery, parsePostEdit, parseReviewEdit, PostEdit, postCategoriesQuery, postDeleteSteps, remainingPhotos, ReviewEdit, reviewDeleteSteps, treatmentList, updatePostQuery, updateReviewQuery, lockReviewQuery, ModerationPage, ModerationQuery, PER_PAGE, PostItem, postCountsQuery, postListQuery, postRevision, recalcHospitalQuery, reviewCountsQuery, reviewImagesQuery, reviewListQuery, ReviewItem, reviewRevision, safeImageUrl, toCounts,
  toPage, toPostItem, toReviewItem,
} from './moderation.logic';

type Row = Record<string, unknown>;

/** Reads the reviews and community posts waiting for (or already given) a decision (read-only pool) and records a review decision (write pool, one transaction). */
@Injectable()
export class ModerationService {
  private readonly log = new Logger('Moderation');

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

  async hospitals(scope: Scope) {
    if (seesNothing(scope)) return { items: [] };
    return { items: (await this.rows(hospitalOptionsQuery(scope))).map(toHospitalOption) };
  }

  async reviews(scope: Scope, q: ModerationQuery): Promise<ModerationPage<ReviewItem>> {
    if (seesNothing(scope) || (q.hospitalId > 0 && !canSee(scope, q.hospitalId))) return emptyPage<ReviewItem>(q);
    const [counts, list] = await Promise.all([this.rows(reviewCountsQuery(scope, q)), this.rows(reviewListQuery(scope, q))]);
    const page = list.slice(0, PER_PAGE);
    const pics = page.length ? await this.rows(reviewImagesQuery(page.map((r) => num(r.id)))) : [];
    const byReview = new Map<number, string[]>();
    for (const p of pics) {
      const url = safeImageUrl(p.image_url);
      if (url === '') continue;
      const id = num(p.review_id);
      byReview.set(id, [...(byReview.get(id) ?? []), url].slice(0, 12));
    }
    return toPage(q, toCounts(counts[0]), list.map((r) => toReviewItem(r, byReview.get(num(r.id)) ?? [])));
  }

  /** Community posts: the caller has already been checked to be a whole-platform admin (controller). */
  async posts(q: ModerationQuery): Promise<ModerationPage<PostItem>> {
    const [counts, list] = await Promise.all([this.rows(postCountsQuery(q)), this.rows(postListQuery(q))]);
    return toPage(q, toCounts(counts[0]), list.map(toPostItem));
  }

  /**
   * Approves or rejects one pending review. The caller sends the revision it read, so a review edited in between is never decided blind (409 changed).
   * A hospital account reaches only reviews of its own hospital that belong to a booking; anything else is "not found".
   */
  async decideReview(scope: Scope, id: number, decision: Decision, revision: string) {
    if (id <= 0 || seesNothing(scope)) throw new NotFoundException({ error: 'not_found' });
    const conn = await this.pool().getConnection();
    try {
      await conn.beginTransaction();
      const lock = lockReviewQuery(id);
      const [found] = (await conn.query(lock.sql, lock.values)) as any;
      const row: Row | undefined = Array.isArray(found) ? found[0] : undefined;
      const hospitalId = num(row?.hospital_id);
      const inScope = !!row && canSee(scope, hospitalId) && (scope.scope === 'all' || num(row.booking_id) > 0);
      if (!row || !inScope) {
        await conn.rollback();
        throw new NotFoundException({ error: 'not_found' });
      }
      if (str(row.review_status) !== 'pending') {
        await conn.rollback();
        throw new ConflictException({ error: 'already_done' });
      }
      if (reviewRevision(row) !== revision) {
        await conn.rollback();
        throw new ConflictException({ error: 'changed' });
      }
      const upd = decideReviewQuery(id, decision);
      await conn.query(upd.sql, upd.values);
      const recalc = recalcHospitalQuery(hospitalId);
      await conn.query(recalc.sql, recalc.values);
      await conn.commit();
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
    return { id, state: decision === 'approve' ? 'approved' : 'rejected' };
  }

  /** Same for a community post (whole-platform admins only; the controller enforces it). */
  async decidePost(id: number, decision: Decision, revision: string) {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const conn = await this.pool().getConnection();
    try {
      await conn.beginTransaction();
      const lock = lockPostQuery(id);
      const [found] = (await conn.query(lock.sql, lock.values)) as any;
      const row: Row | undefined = Array.isArray(found) ? found[0] : undefined;
      if (!row) throw new NotFoundException({ error: 'not_found' });
      if (str(row.status) !== 'pending') throw new ConflictException({ error: 'already_done' });
      if (postRevision(row) !== revision) throw new ConflictException({ error: 'changed' });
      const upd = decidePostQuery(id, decision);
      await conn.query(upd.sql, upd.values);
      await conn.commit();
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
    return { id, state: decision === 'approve' ? 'approved' : 'rejected' };
  }
  // ---- editing and deleting ----------------------------------------------------------------------------------------

  /** One transaction on the write pool: anything thrown inside (404, 409, 400, a database error) rolls everything back. */
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

  /** The locked review row; a hospital account reaches only its own hospital's reviews that belong to a booking, anything else is "not found". */
  private async lockedReview(conn: any, scope: Scope, id: number): Promise<Row> {
    const lock = lockReviewQuery(id);
    const [found] = (await conn.query(lock.sql, lock.values)) as any;
    const row: Row | undefined = Array.isArray(found) ? found[0] : undefined;
    if (!row || !canSee(scope, num(row.hospital_id)) || (scope.scope !== 'all' && num(row.booking_id) <= 0)) throw new NotFoundException({ error: 'not_found' });
    return row;
  }

  private async lockedPost(conn: any, id: number): Promise<Row> {
    const lock = lockPostQuery(id);
    const [found] = (await conn.query(lock.sql, lock.values)) as any;
    const row: Row | undefined = Array.isArray(found) ? found[0] : undefined;
    if (!row) throw new NotFoundException({ error: 'not_found' });
    return row;
  }

  /** Runs the cleanup statements; a table a server does not have is skipped (only for the optional steps), a permission or other error stops and rolls back. */
  private async runSteps(conn: any, steps: { required: boolean; q: Query }[]): Promise<void> {
    for (const step of steps) {
      try {
        await conn.query(step.q.sql, step.q.values);
      } catch (e: any) {
        if (!step.required && (e?.errno === 1146 || e?.code === 'ER_NO_SUCH_TABLE')) continue;
        this.log.error(`cleanup failed: ${String(e?.message ?? e).slice(0, 200)}`);
        throw e;
      }
    }
  }

  private async recalc(conn: any, hospitalId: number): Promise<void> {
    const recalc = recalcHospitalQuery(hospitalId);
    await conn.query(recalc.sql, recalc.values);
  }

  /** The names a review of this hospital may carry (the picker of the edit form). */
  async reviewTreatmentOptions(scope: Scope, hospitalId: number) {
    if (hospitalId <= 0 || seesNothing(scope) || !canSee(scope, hospitalId)) return { items: [] as string[] };
    return { items: (await this.rows(hospitalTreatmentNamesQuery(hospitalId))).map((r) => str(r.name)).filter(Boolean) };
  }

  /** The categories community posts use today (the select of the edit form). */
  async postCategories() {
    return { items: (await this.rows(postCategoriesQuery())).map((r) => str(r.category)).filter(Boolean) };
  }

  /**
   * Edits the text, rating and treatments of one review and removes the listed pictures from it (the files stay). The author, the state and the booking never change.
   * New treatments must be ones the hospital offers; names already on the review may stay. A changed rating of a shown review refreshes the hospital's figures.
   */
  async editReview(scope: Scope, id: number, edit: ReviewEdit, revision: string) {
    if (id <= 0 || seesNothing(scope)) throw new NotFoundException({ error: 'not_found' });
    return this.tx(async (conn) => {
      const row = await this.lockedReview(conn, scope, id);
      if (reviewRevision(row) !== revision) throw new ConflictException({ error: 'changed' });
      const fields = lengthErrors(edit, { title: str(row.title), content: str(row.content) });
      const hospitalId = num(row.hospital_id);
      const current = treatmentList(row.treatments);
      const added = edit.treatments.filter((x) => !current.includes(x));
      if (added.length > 0) {
        const allowed = new Set((await this.rows(hospitalTreatmentNamesQuery(hospitalId))).map((r) => str(r.name)));
        if (added.some((x) => !allowed.has(x))) fields.treatments = 'unknown';
      }
      if (Object.keys(fields).length > 0) throw new BadRequestException({ error: 'invalid', fields });
      const upd = updateReviewQuery(id, edit);
      await conn.query(upd.sql, upd.values);
      if (edit.removeImages.length > 0) {
        const del = deleteReviewImagesByUrlQuery(id, edit.removeImages);
        await conn.query(del.sql, del.values);
      }
      if (num(row.is_active) === 1 && edit.rating !== num(row.rating)) await this.recalc(conn, hospitalId);
      return { id };
    });
  }

  /** Same for a community post (whole-platform admins only; the controller enforces it): category, title, text and removing pictures. */
  async editPost(id: number, edit: PostEdit, revision: string) {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    return this.tx(async (conn) => {
      const row = await this.lockedPost(conn, id);
      if (postRevision(row) !== revision) throw new ConflictException({ error: 'changed' });
      const fields = lengthErrors(edit, { title: str(row.title), content: str(row.content) });
      if (edit.category !== str(row.category)) {
        const known = new Set((await this.rows(postCategoriesQuery())).map((r) => str(r.category)));
        if (!known.has(edit.category)) fields.category = 'unknown';
      }
      let photos: string[] | null = null;
      if (edit.removeImages.length > 0) {
        photos = remainingPhotos(row.photos, edit.removeImages);
        if (photos === null) fields.removeImages = 'invalid';
      }
      if (Object.keys(fields).length > 0) throw new BadRequestException({ error: 'invalid', fields });
      const upd = updatePostQuery(id, edit, photos);
      await conn.query(upd.sql, upd.values);
      return { id };
    });
  }

  /** Deletes one review for good with everything that hangs on it, then refreshes the hospital's figures. Points are not touched here. */
  async deleteReview(scope: Scope, id: number, revision: string) {
    if (id <= 0 || seesNothing(scope)) throw new NotFoundException({ error: 'not_found' });
    return this.tx(async (conn) => {
      const row = await this.lockedReview(conn, scope, id);
      if (reviewRevision(row) !== revision) throw new ConflictException({ error: 'changed' });
      await this.runSteps(conn, reviewDeleteSteps(id));
      const del = deleteReviewQuery(id);
      await conn.query(del.sql, del.values);
      await this.recalc(conn, num(row.hospital_id));
      return { id };
    });
  }

  /** Same for a community post (whole-platform admins only; the controller enforces it). */
  async deletePost(id: number, revision: string) {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    return this.tx(async (conn) => {
      const row = await this.lockedPost(conn, id);
      if (postRevision(row) !== revision) throw new ConflictException({ error: 'changed' });
      await this.runSteps(conn, postDeleteSteps(id));
      const del = deletePostQuery(id);
      await conn.query(del.sql, del.values);
      return { id };
    });
  }
}
