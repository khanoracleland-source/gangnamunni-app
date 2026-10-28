import { Inject, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { jsonArray } from '../common/parse';
import { sanitizeImageUrl } from '../community/community.logic';
import { htmlToText, starCount } from './reviews.logic';

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
  private readonly log = new Logger(ReviewsService.name);
  constructor(@Inject(DB) private readonly db: Db) {}

  /** review_detail.php. Blocked authors (either direction) are a 404 for the viewer. */
  async detail(viewer: string | null, id: number) {
    const [rows] = (await this.db.query(
      `SELECT r.*, h.name AS hospital_name, h.profile_image_url AS hospital_image, h.address AS hospital_address,
              h.rating AS hospital_rating, h.review_count AS hospital_review_count
       FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
       WHERE r.id = ? AND r.is_active = 1 LIMIT 1`, [id])) as any;
    const r = (rows as any[])[0];
    if (!r) throw new NotFoundException('후기를 찾을 수 없습니다.');
    const author = String(r.user_id ?? '');
    if (viewer) {
      try {
        const [b] = (await this.db.query(
          'SELECT 1 FROM gangnamunni_blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1', [viewer, author, author, viewer])) as any;
        if ((b as any[]).length) throw new NotFoundException('후기를 찾을 수 없습니다.');
      } catch (e) {
        if (e instanceof NotFoundException) throw e;
        this.log.warn(`block lookup failed: ${(e as Error).message}`);
      }
    }
    const safe = async <T>(label: string, run: () => Promise<T>, fb: T): Promise<T> => {
      try { return await run(); } catch (e) { this.log.warn(`${label}: ${(e as Error).message}`); return fb; }
    };
    const [images, commentCount, liked] = await Promise.all([
      safe('review images', async () => ((await this.db.query('SELECT image_url, thumbnail_url FROM review_images WHERE review_id = ? ORDER BY image_order ASC', [id])) as any)[0] as any[], [] as any[]),
      safe('review comments', async () => Number((((await this.db.query('SELECT COUNT(*) AS c FROM review_comments WHERE review_id = ? AND is_active = 1', [id])) as any)[0] as any[])[0]?.c ?? 0), 0),
      viewer ? safe('review like', async () => (((await this.db.query('SELECT id FROM review_likes WHERE review_id = ? AND user_id = ? LIMIT 1', [id, viewer])) as any)[0] as any[]).length > 0, false) : Promise.resolve(false),
    ]);
    const content = r.editor_type === 'quill' && r.editor_content ? htmlToText(r.editor_content) : String(r.content ?? '').trim();
    return {
      id: Number(r.id), hospitalId: r.hospital_id ? Number(r.hospital_id) : null,
      treatments: r.treatments ? String(r.treatments).split(',').map((x: string) => x.trim()).filter(Boolean) : [],
      rating: starCount(r.rating), ratingValue: Number(Number(r.rating ?? 0).toFixed(1)),
      title: String(r.title ?? ''), content,
      images: (images as any[]).map((i) => { const full = sanitizeImageUrl(i.image_url); const thumb = sanitizeImageUrl(i.thumbnail_url); return { src: thumb || full, full }; }).filter((i) => i.src !== ''),
      author: { name: String(r.user_name ?? ''), avatar: sanitizeImageUrl(r.user_avatar) || null },
      likeCount: Number(r.like_count ?? 0), commentCount, viewCount: Number(r.view_count ?? 0), liked,
      isMine: !!viewer && viewer === author,
      hospital: r.hospital_id ? {
        id: Number(r.hospital_id), name: String(r.hospital_name ?? ''), address: String(r.hospital_address ?? ''),
        imageUrl: sanitizeImageUrl(r.hospital_image) || null,
        rating: Number(r.hospital_rating) > 0 ? Number(Number(r.hospital_rating).toFixed(1)) : null, reviewCount: Number(r.hospital_review_count ?? 0),
      } : null,
    };
  }

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
