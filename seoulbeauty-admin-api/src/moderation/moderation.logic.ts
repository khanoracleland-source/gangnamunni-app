import { createHash } from 'crypto';
import { reportDetailText } from '../common/report-detail';
import { likeContains, MAX_PAGE, MAX_SEARCH_LENGTH, num, parseHospitalId, positiveInt, Query, Scope, str } from '../hospitals/hospitals.logic';

export const PER_PAGE = 20;
export const MODERATION_STATES = ['pending', 'approved', 'rejected'] as const;
export type ModerationState = (typeof MODERATION_STATES)[number];

export interface ModerationQuery {
  status: ModerationState;
  search: string;
  hospitalId: number;
  page: number;
  /** Community posts only: just the posts somebody reported. */
  reported: boolean;
}

export function normalizeModerationQuery(q: Record<string, unknown> | undefined | null): ModerationQuery {
  const get = (k: string): string => (typeof q?.[k] === 'string' ? (q[k] as string) : '');
  const status = (MODERATION_STATES as readonly string[]).includes(get('status')) ? (get('status') as ModerationState) : 'pending';
  const search = Array.from(get('search').trim()).slice(0, MAX_SEARCH_LENGTH).join('');
  const page = Math.min(MAX_PAGE, Math.max(1, positiveInt(get('page'))));
  return { status, search, hospitalId: parseHospitalId(get('hospitalId')), page, reported: get('reported') === '1' };
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
  if (q.reported) sql += " AND EXISTS (SELECT 1 FROM gangnamunni_content_reports r WHERE r.content_type = 'community' AND r.content_id = p.id)";
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

/** Reports about the posts of one page (the newest few per post) and the real number per post. A post nobody reported has neither. */
export const POST_REPORTS_PER_POST = 5;
export const postReportCountsQuery = (ids: number[]): Query => ({
  sql: `SELECT content_id, COUNT(*) AS cnt FROM gangnamunni_content_reports WHERE content_type = 'community' AND content_id IN (${ids.map(() => '?').join(', ')}) GROUP BY content_id`,
  values: ids,
});
export const postReportsQuery = (ids: number[]): Query => ({
  sql: `SELECT id, content_id, reporter_name, reason, reason_detail, status, created_at FROM gangnamunni_content_reports
         WHERE content_type = 'community' AND content_id IN (${ids.map(() => '?').join(', ')}) ORDER BY created_at DESC, id DESC LIMIT 500`,
  values: ids,
});
export interface PostReport { id: number; reporter: string; reason: string; detail: string; status: string; createdAt: string }
export function groupPostReports(counts: Row[], reports: Row[]): Map<number, { reportCount: number; reports: PostReport[] }> {
  const out = new Map<number, { reportCount: number; reports: PostReport[] }>();
  for (const c of counts) out.set(num(c.content_id), { reportCount: num(c.cnt), reports: [] });
  for (const r of reports) {
    const entry = out.get(num(r.content_id));
    if (!entry || entry.reports.length >= POST_REPORTS_PER_POST) continue;
    entry.reports.push({
      id: num(r.id), reporter: str(r.reporter_name), reason: str(r.reason), detail: reportDetailText(r.reason_detail), status: str(r.status), createdAt: str(r.created_at),
    });
  }
  return out;
}

/** The pictures column holds a JSON list. The driver hands it over as text or, for a column the server marks as JSON, already as a list; null when it is neither. */
export function photoList(raw: unknown): unknown[] | null {
  if (Array.isArray(raw)) return raw;
  try {
    const v = JSON.parse(str(raw) || '[]');
    return Array.isArray(v) ? v : null;
  } catch {
    return null;
  }
}

export function photosOf(raw: unknown): string[] {
  const v = photoList(raw);
  return v ? v.map((x) => safeImageUrl(x)).filter((x) => x !== '').slice(0, 12) : [];
}

const photosText = (raw: unknown): string => (Array.isArray(raw) ? JSON.stringify(raw) : str(raw));

export const postRevision = (r: Row): string => sha([str(r.category), str(r.title), str(r.content), photosText(r.photos)]);

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

// ---- editing --------------------------------------------------------------------------------------------------------

export const TITLE_MAX = 100;
export const CONTENT_MAX = 2000;
export const TREATMENTS_MAX = 10;
export const TREATMENT_NAME_MAX = 50;
export const REMOVE_IMAGES_MAX = 12;

export type FieldErrors = Record<string, string>;
const CONTROL = /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/;
const length = (v: string): number => Array.from(v).length;

/** A text field: a string, trimmed, line breaks as "\n", no control characters, not empty. The length limit is checked later against the stored value. */
function cleanText(raw: unknown, name: string, errors: FieldErrors): string {
  if (typeof raw !== 'string') {
    errors[name] = 'required';
    return '';
  }
  const v = raw.replace(/\r\n?/g, '\n').trim();
  if (v === '') errors[name] = 'required';
  else if (CONTROL.test(v)) errors[name] = 'bad_format';
  return v;
}

function stringList(raw: unknown, name: string, max: number, errors: FieldErrors): string[] {
  if (raw === undefined) return [];
  if (!Array.isArray(raw) || raw.some((x) => typeof x !== 'string')) {
    errors[name] = 'invalid';
    return [];
  }
  if (raw.length > max) errors[name] = 'too_many';
  return raw as string[];
}

export interface ReviewEdit {
  title: string;
  content: string;
  rating: number;
  treatments: string[];
  removeImages: string[];
}
export interface PostEdit {
  category: string;
  title: string;
  content: string;
  removeImages: string[];
}

export function parseReviewEdit(body: unknown): { value: ReviewEdit; errors: FieldErrors } {
  const b = (body && typeof body === 'object' ? body : {}) as Record<string, unknown>;
  const errors: FieldErrors = {};
  const title = cleanText(b.title, 'title', errors);
  const content = cleanText(b.content, 'content', errors);
  const rating = typeof b.rating === 'number' && Number.isInteger(b.rating) && b.rating >= 1 && b.rating <= 5 ? b.rating : 0;
  if (rating === 0) errors.rating = 'range';
  const treatments: string[] = [];
  for (const x of stringList(b.treatments, 'treatments', TREATMENTS_MAX, errors)) {
    const name = x.trim();
    if (name === '') continue;
    // the column keeps the names in one comma separated string, so a comma inside a name would split it
    if (name.includes(',') || CONTROL.test(name)) errors.treatments = 'bad_format';
    else if (length(name) > TREATMENT_NAME_MAX) errors.treatments = 'too_long';
    if (!treatments.includes(name)) treatments.push(name);
  }
  const removeImages = stringList(b.removeImages, 'removeImages', REMOVE_IMAGES_MAX, errors);
  return { value: { title, content, rating, treatments, removeImages }, errors };
}

export function parsePostEdit(body: unknown): { value: PostEdit; errors: FieldErrors } {
  const b = (body && typeof body === 'object' ? body : {}) as Record<string, unknown>;
  const errors: FieldErrors = {};
  const category = cleanText(b.category, 'category', errors);
  const title = cleanText(b.title, 'title', errors);
  const content = cleanText(b.content, 'content', errors);
  const removeImages = stringList(b.removeImages, 'removeImages', REMOVE_IMAGES_MAX, errors);
  return { value: { category, title, content, removeImages }, errors };
}

/** Too long is judged against what is stored: a value that was already there and is unchanged is never refused for its length. */
export function lengthErrors(next: { title: string; content: string }, current: { title: string; content: string }): FieldErrors {
  const errors: FieldErrors = {};
  // the submitted text has its line breaks and edges normalised, so the stored one is compared the same way
  const same = (x: string): string => x.replace(/\r\n?/g, '\n').trim();
  if (next.title !== same(current.title) && length(next.title) > TITLE_MAX) errors.title = 'too_long';
  if (next.content !== same(current.content) && length(next.content) > CONTENT_MAX) errors.content = 'too_long';
  return errors;
}

export const treatmentList = (stored: unknown): string[] => str(stored).split(',').map((x) => x.trim()).filter(Boolean);

/** Names a review of this hospital may carry: its own active treatments. */
export const hospitalTreatmentNamesQuery = (hospitalId: number): Query => ({
  sql: `SELECT DISTINCT tm.name FROM gangnamunni_hospital_treatments ht JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
         WHERE ht.hospital_id = ? AND ht.is_active = 1 AND ht.status = 'active' ORDER BY tm.name ASC LIMIT 500`,
  values: [hospitalId],
});

export const postCategoriesQuery = (): Query => ({
  sql: "SELECT DISTINCT category FROM gangnamunni_community_posts WHERE category <> '' ORDER BY category ASC LIMIT 100",
  values: [],
});

export const updateReviewQuery = (id: number, e: ReviewEdit): Query => ({
  sql: 'UPDATE hospital_reviews SET title = ?, content = ?, rating = ?, treatments = ?, updated_at = NOW() WHERE id = ? LIMIT 1',
  values: [e.title, e.content, e.rating, e.treatments.join(', '), id],
});

export const deleteReviewImagesByUrlQuery = (reviewId: number, urls: string[]): Query => ({
  sql: 'DELETE FROM review_images WHERE review_id = ? AND image_url IN (?)',
  values: [reviewId, urls],
});

/** The stored list with the removed pictures taken out; an entry matches by its stored text or by its safe address. */
export function remainingPhotos(raw: unknown, remove: string[]): string[] | null {
  const v = photoList(raw);
  if (!v) return null;
  const gone = new Set(remove);
  return v.filter((x): x is string => typeof x === 'string').filter((x) => !gone.has(x) && !gone.has(safeImageUrl(x)));
}

export const updatePostQuery = (id: number, e: PostEdit, photos: string[] | null): Query =>
  photos === null
    ? { sql: 'UPDATE gangnamunni_community_posts SET category = ?, title = ?, content = ? WHERE id = ? LIMIT 1', values: [e.category, e.title, e.content, id] }
    : { sql: 'UPDATE gangnamunni_community_posts SET category = ?, title = ?, content = ?, photos = ? WHERE id = ? LIMIT 1', values: [e.category, e.title, e.content, JSON.stringify(photos), id] };

// ---- deleting -------------------------------------------------------------------------------------------------------

/**
 * What hangs on a review, removed before the review itself. The picture rows come first and are required; the others belong to features that may not exist
 * on every server, so a missing table is skipped (and only a missing table).
 */
export const reviewDeleteSteps = (id: number): { required: boolean; q: Query }[] => [
  { required: true, q: { sql: 'DELETE FROM review_images WHERE review_id = ?', values: [id] } },
  { required: false, q: { sql: 'DELETE FROM review_ai_logs WHERE review_id = ?', values: [id] } },
  { required: false, q: { sql: 'DELETE FROM review_reports WHERE review_id = ?', values: [id] } },
  { required: false, q: { sql: 'DELETE FROM review_likes WHERE review_id = ?', values: [id] } },
  { required: false, q: { sql: 'DELETE FROM review_comments WHERE review_id = ?', values: [id] } },
  { required: false, q: { sql: "DELETE FROM gangnamunni_content_reports WHERE content_type = 'review' AND content_id = ?", values: [id] } },
];
export const deleteReviewQuery = (id: number): Query => ({ sql: 'DELETE FROM hospital_reviews WHERE id = ? LIMIT 1', values: [id] });

/** What hangs on a community post: threads and likes of its comments, the comments, the post's likes and the reports about it. */
export const postDeleteSteps = (id: number): { required: boolean; q: Query }[] => [
  { required: false, q: { sql: 'DELETE t FROM gangnamunni_community_comment_threads t JOIN gangnamunni_community_comments c ON c.id = t.comment_id WHERE c.post_id = ?', values: [id] } },
  { required: false, q: { sql: 'DELETE cl FROM gangnamunni_community_comment_likes cl JOIN gangnamunni_community_comments c ON c.id = cl.comment_id WHERE c.post_id = ?', values: [id] } },
  { required: false, q: { sql: 'DELETE FROM gangnamunni_community_comments WHERE post_id = ?', values: [id] } },
  { required: false, q: { sql: 'DELETE FROM gangnamunni_community_likes WHERE post_id = ?', values: [id] } },
  { required: false, q: { sql: "DELETE FROM gangnamunni_content_reports WHERE content_type = 'community' AND content_id = ?", values: [id] } },
];
export const deletePostQuery = (id: number): Query => ({ sql: 'DELETE FROM gangnamunni_community_posts WHERE id = ? LIMIT 1', values: [id] });
