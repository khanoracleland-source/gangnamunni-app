import { createHash } from 'crypto';
import { likeContains, MAX_PAGE, MAX_SEARCH_LENGTH, num, parseHospitalId, positiveInt, Query, Scope, str } from '../hospitals/hospitals.logic';

export const PER_PAGE = 20;
export const MODERATION_STATES = ['pending', 'approved', 'rejected'] as const;
export type ModerationState = (typeof MODERATION_STATES)[number];

export interface ModerationQuery {
  status: ModerationState;
  search: string;
  hospitalId: number;
  page: number;
}

export function normalizeModerationQuery(q: Record<string, unknown> | undefined | null): ModerationQuery {
  const get = (k: string): string => (typeof q?.[k] === 'string' ? (q[k] as string) : '');
  const status = (MODERATION_STATES as readonly string[]).includes(get('status')) ? (get('status') as ModerationState) : 'pending';
  const search = Array.from(get('search').trim()).slice(0, MAX_SEARCH_LENGTH).join('');
  const page = Math.min(MAX_PAGE, Math.max(1, positiveInt(get('page'))));
  return { status, search, hospitalId: parseHospitalId(get('hospitalId')), page };
}

/** Id of /v1/moderation/<kind>/:id, or 0 when it is not a plain positive integer. */
export const parseItemId = (raw: unknown): number => (typeof raw === 'string' && /^[1-9]\d{0,9}$/.test(raw) ? positiveInt(raw) : 0);

/** The revision the page was read with; any other shape is refused. */
export const parseRevision = (body: unknown): string | null => {
  const v = body && typeof body === 'object' ? (body as Record<string, unknown>).revision : undefined;
  return typeof v === 'string' && /^[a-f0-9]{64}$/.test(v) ? v : null;
};

const sha = (parts: unknown[]): string => createHash('sha256').update(JSON.stringify(parts)).digest('hex');

/** Only a plain https/http address or a path on this site is ever shown as a picture. */
export const safeImageUrl = (v: unknown): string => {
  const s = str(v).trim();
  return s !== '' && s.length <= 500 && /^(?:\/(?!\/)|https?:\/\/)[^\s"'<>\\]+$/i.test(s) ? s : '';
};

// ---- reviews (hospital_reviews) -------------------------------------------------------------------------------------

/**
 * A pending review waits for a decision. An approved one is published and active; a rejected one stays "published" with the active flag off
 * (that is how the old panel stores a rejection, and the app shows only active ones).
 */
export const REVIEW_STATE_SQL: Record<ModerationState, string> = {
  pending: "r.review_status = 'pending'",
  approved: "r.review_status = 'published' AND r.is_active = 1",
  rejected: "r.review_status = 'published' AND r.is_active = 0",
};

/**
 * A whole-platform admin sees every review. A hospital account sees only the reviews written for a treatment booked at its own hospital
 * (booking_id set); an empty scope must be ruled out by the caller.
 */
export function reviewScopeSql(s: Scope): Query {
  if (s.scope === 'all') return { sql: '', values: [] };
  if (s.hospitalIds.length === 0) throw new Error('empty scope must not reach the database');
  return { sql: ' AND r.hospital_id IN (?) AND COALESCE(r.booking_id, 0) > 0', values: [s.hospitalIds] };
}

function reviewWhere(s: Scope, q: ModerationQuery, withStatus: boolean): Query {
  const scope = reviewScopeSql(s);
  let sql = `1 = 1${scope.sql}`;
  const values: unknown[] = [...scope.values];
  if (q.hospitalId > 0) {
    sql += ' AND r.hospital_id = ?';
    values.push(q.hospitalId);
  }
  if (withStatus) sql += ` AND ${REVIEW_STATE_SQL[q.status]}`;
  if (q.search) {
    const like = likeContains(q.search);
    sql += ' AND (r.title LIKE ? OR r.content LIKE ? OR r.user_name LIKE ? OR r.treatments LIKE ? OR h.name LIKE ?)';
    values.push(like, like, like, like, like);
  }
  return { sql, values };
}

export function reviewCountsQuery(s: Scope, q: ModerationQuery): Query {
  const w = reviewWhere(s, q, false);
  return {
    sql: `SELECT SUM(CASE WHEN ${REVIEW_STATE_SQL.pending} THEN 1 ELSE 0 END) AS pending,
                 SUM(CASE WHEN ${REVIEW_STATE_SQL.approved} THEN 1 ELSE 0 END) AS approved,
                 SUM(CASE WHEN ${REVIEW_STATE_SQL.rejected} THEN 1 ELSE 0 END) AS rejected
            FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON h.id = r.hospital_id WHERE ${w.sql}`,
    values: w.values,
  };
}

export function reviewListQuery(s: Scope, q: ModerationQuery): Query {
  const w = reviewWhere(s, q, true);
  return {
    sql: `SELECT r.id, r.hospital_id, h.name AS hospital_name, r.user_name, r.title, r.content, r.rating, r.treatments, r.doctor_name, r.booking_id,
                 r.review_status, r.is_active, r.created_at, r.updated_at
            FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON h.id = r.hospital_id
           WHERE ${w.sql} ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?`,
    values: [...w.values, PER_PAGE + 1, (q.page - 1) * PER_PAGE],
  };
}

export const reviewImagesQuery = (ids: number[]): Query => ({
  sql: 'SELECT review_id, image_url FROM review_images WHERE review_id IN (?) ORDER BY review_id ASC, image_order ASC, id ASC',
  values: [ids],
});

type Row = Record<string, unknown>;

export const reviewRevision = (r: Row): string => sha([num(r.hospital_id), str(r.title), str(r.content), num(r.rating), str(r.treatments), str(r.doctor_name), str(r.updated_at)]);

export function reviewState(r: Row): ModerationState {
  if (str(r.review_status) === 'pending') return 'pending';
  return num(r.is_active) === 1 ? 'approved' : 'rejected';
}

export function toReviewItem(r: Row, images: string[]) {
  return {
    id: num(r.id),
    hospitalId: num(r.hospital_id),
    hospitalName: str(r.hospital_name),
    author: str(r.user_name),
    title: str(r.title),
    content: str(r.content),
    rating: Math.min(5, Math.max(0, num(r.rating))),
    treatments: str(r.treatments).split(',').map((x) => x.trim()).filter(Boolean),
    doctorName: str(r.doctor_name),
    hasBooking: num(r.booking_id) > 0,
    state: reviewState(r),
    createdAt: str(r.created_at),
    images,
    revision: reviewRevision(r),
  };
}
export type ReviewItem = ReturnType<typeof toReviewItem>;

export interface Counts {
  pending: number;
  approved: number;
  rejected: number;
}
export const toCounts = (r: Row | undefined): Counts => ({ pending: num(r?.pending), approved: num(r?.approved), rejected: num(r?.rejected) });

export interface ModerationPage<T> {
  status: ModerationState;
  page: number;
  perPage: number;
  hasMore: boolean;
  counts: Counts;
  items: T[];
}

export function toPage<T>(q: ModerationQuery, counts: Counts, rows: T[]): ModerationPage<T> {
  return { status: q.status, page: q.page, perPage: PER_PAGE, hasMore: rows.length > PER_PAGE, counts, items: rows.slice(0, PER_PAGE) };
}

export const emptyPage = <T>(q: ModerationQuery): ModerationPage<T> => toPage<T>(q, { pending: 0, approved: 0, rejected: 0 }, []);

// ---- community posts (gangnamunni_community_posts) ------------------------------------------------------------------

export const POST_STATE_VALUE: Record<ModerationState, string> = { pending: 'pending', approved: 'active', rejected: 'rejected' };

function postWhere(q: ModerationQuery, withStatus: boolean): Query {
  let sql = '1 = 1';
  const values: unknown[] = [];
  if (withStatus) {
    sql += ' AND p.status = ?';
    values.push(POST_STATE_VALUE[q.status]);
  }
  if (q.search) {
    const like = likeContains(q.search);
    sql += ' AND (p.title LIKE ? OR p.content LIKE ? OR p.author_name LIKE ? OR p.category LIKE ?)';
    values.push(like, like, like, like);
  }
  return { sql, values };
}

export function postCountsQuery(q: ModerationQuery): Query {
  const w = postWhere(q, false);
  return {
    sql: `SELECT SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) AS pending,
                 SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) AS approved,
                 SUM(CASE WHEN p.status = 'rejected' THEN 1 ELSE 0 END) AS rejected
            FROM gangnamunni_community_posts p WHERE ${w.sql}`,
    values: w.values,
  };
}

export function postListQuery(q: ModerationQuery): Query {
  const w = postWhere(q, true);
  return {
    sql: `SELECT p.id, p.category, p.author_name, p.title, p.content, p.photos, p.status, p.created_at
            FROM gangnamunni_community_posts p WHERE ${w.sql} ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?`,
    values: [...w.values, PER_PAGE + 1, (q.page - 1) * PER_PAGE],
  };
}

export function photosOf(raw: unknown): string[] {
  try {
    const v = JSON.parse(str(raw) || '[]');
    return Array.isArray(v) ? v.map(safeImageUrl).filter((x) => x !== '').slice(0, 12) : [];
  } catch {
    return [];
  }
}

export const postRevision = (r: Row): string => sha([str(r.category), str(r.title), str(r.content), str(r.photos)]);

export function toPostItem(r: Row) {
  const status = str(r.status);
  return {
    id: num(r.id),
    category: str(r.category),
    author: str(r.author_name),
    title: str(r.title),
    content: str(r.content),
    images: photosOf(r.photos),
    state: (status === 'active' ? 'approved' : status === 'rejected' ? 'rejected' : 'pending') as ModerationState,
    createdAt: str(r.created_at),
    revision: postRevision(r),
  };
}
export type PostItem = ReturnType<typeof toPostItem>;

// ---- decisions ------------------------------------------------------------------------------------------------------

export type Decision = 'approve' | 'reject';

export const lockReviewQuery = (id: number): Query => ({
  sql: 'SELECT id, hospital_id, booking_id, review_status, is_active, title, content, rating, treatments, doctor_name, updated_at FROM hospital_reviews WHERE id = ? LIMIT 1 FOR UPDATE',
  values: [id],
});

export const decideReviewQuery = (id: number, decision: Decision): Query => ({
  sql: "UPDATE hospital_reviews SET review_status = 'published', is_active = ?, updated_at = NOW() WHERE id = ? AND review_status = 'pending' LIMIT 1",
  values: [decision === 'approve' ? 1 : 0, id],
});

/** Same figures the old panel keeps on the hospital row: the number of active reviews and their average. */
export const recalcHospitalQuery = (hospitalId: number): Query => ({
  sql: `UPDATE gangnamunni_hospitals SET
          review_count = (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1),
          rating = (SELECT COALESCE(AVG(LEAST(5, GREATEST(1, rating))), 0) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1)
        WHERE id = ?`,
  values: [hospitalId, hospitalId, hospitalId],
});

export const lockPostQuery = (id: number): Query => ({
  sql: 'SELECT id, category, title, content, photos, status FROM gangnamunni_community_posts WHERE id = ? LIMIT 1 FOR UPDATE',
  values: [id],
});

export const decidePostQuery = (id: number, decision: Decision): Query => ({
  sql: "UPDATE gangnamunni_community_posts SET status = ? WHERE id = ? AND status = 'pending' LIMIT 1",
  values: [decision === 'approve' ? 'active' : 'rejected', id],
});
