import { ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { hospitalOptionsQuery, toHospitalOption } from '../doctors/doctors.logic';
import { canSee, num, Query, Scope, seesNothing, str } from '../hospitals/hospitals.logic';
import {
  decideReviewQuery, Decision, emptyPage, lockReviewQuery, ModerationPage, ModerationQuery, PER_PAGE, PostItem, postCountsQuery, postListQuery, recalcHospitalQuery, reviewCountsQuery, reviewImagesQuery, reviewListQuery, ReviewItem, reviewRevision, safeImageUrl, toCounts,
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
}
