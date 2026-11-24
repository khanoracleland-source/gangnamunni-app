import { Inject, Injectable } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { hospitalOptionsQuery, toHospitalOption } from '../doctors/doctors.logic';
import { canSee, num, Query, Scope, seesNothing } from '../hospitals/hospitals.logic';
import { emptyPage, ModerationPage, ModerationQuery, PER_PAGE, reviewCountsQuery, reviewImagesQuery, reviewListQuery, ReviewItem, safeImageUrl, toCounts, toPage, toReviewItem } from './moderation.logic';

type Row = Record<string, unknown>;

/** Read-only access to the reviews waiting for (or already given) a decision (read-only pool). */
@Injectable()
export class ModerationService {
  constructor(@Inject(DB) private readonly ro: Db) {}

  private async rows(q: Query): Promise<Row[]> {
    const [rows] = (await this.ro.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
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
}
