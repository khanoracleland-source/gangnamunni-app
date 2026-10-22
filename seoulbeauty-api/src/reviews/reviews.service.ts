import { Inject, Injectable, NotFoundException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { jsonArray } from '../common/parse';

/** 'YYYY-MM-DD HH:MM:SS' (KST, from dateStrings) -> ISO-8601 with +09:00 offset. */
export function kstIso(v: unknown): string | null {
  if (v == null) return null;
  const s = String(v);
  return /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(s) ? s.slice(0, 19).replace(' ', 'T') + '+09:00' : s;
}

export function toReviewDto(r: Record<string, any>) {
  return {
    id: Number(r.id),
    hospitalId: Number(r.hospital_id),
    title: r.title ?? null,
    userName: r.user_name,
    userAvatar: r.user_avatar ?? null,
    rating: Number(r.rating ?? 0),
    content: r.content ?? null,
    treatments: r.treatments ? String(r.treatments).split(',').map((s: string) => s.trim()).filter(Boolean) : [],
    images: jsonArray(r.images),
    likeCount: Number(r.like_count ?? 0),
    commentCount: Number(r.comment_count ?? 0),
    verified: !!r.is_verified,
    createdAt: kstIso(r.created_at),
  };
}

@Injectable()
export class ReviewsService {
  constructor(@Inject(DB) private readonly db: Db) {}

  /** Only admin-approved, published reviews are public (same rule as detail.php). */
  async listForHospital(hospitalId: number, limit: number, offset: number) {
    // Same visibility rule as the hospital detail endpoint: hidden/demo/unapproved => 404.
    const [vis] = (await this.db.query(
      `SELECT 1 FROM gangnamunni_hospitals h WHERE h.id = ? AND h.is_active = 1 AND COALESCE(h.is_demo,0) = 0
         AND (h.status = 'approved' OR h.status IS NULL) LIMIT 1`,
      [hospitalId],
    )) as any;
    if (!vis[0]) throw new NotFoundException('hospital not found');
    const [rows] = (await this.db.query(
      `SELECT id, hospital_id, title, user_name, user_avatar, rating, content, treatments, images,
              like_count, comment_count, is_verified, created_at
       FROM hospital_reviews
       WHERE hospital_id = ? AND is_active = 1 AND review_status = 'published'
       ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?`,
      [hospitalId, limit, offset],
    )) as any;
    const [cnt] = (await this.db.query(
      "SELECT COUNT(*) AS total FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1 AND review_status = 'published'",
      [hospitalId],
    )) as any;
    return { items: (rows as any[]).map(toReviewDto), total: Number(cnt[0]?.total ?? 0), limit, offset };
  }
}
