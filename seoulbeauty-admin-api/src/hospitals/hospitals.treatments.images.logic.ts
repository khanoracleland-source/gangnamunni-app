/** Pure parts of the detail images of one hospital treatment: input checks, storage paths, SQL statements and the shape of the answers. */
import * as path from 'path';
import { SUPER_ADMIN_ROLE } from '../admin-auth/admin.guard';
import { detectImageKind } from './hospitals.images.logic';
import { dateOrNull, num, Query, str } from './hospitals.logic';

type Row = Record<string, unknown>;

export const MAX_TREATMENT_IMAGE_BYTES = 10 * 1024 * 1024;
export const MAX_URL_BYTES = 2048;
export const MAX_CAPTION = 200;
export const MAX_REASON = 1000;
export const MAX_REVIEWER = 100;
/** Folder (inside the upload root) and prefix of the stored value of image_url; the PHP admin uses the same. */
export const TREATMENT_IMAGE_DIR = 'uploads/treatments';
const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

export type TreatmentImageKind = 'jpg' | 'png' | 'gif' | 'webp';
export type ImageStatus = 'pending' | 'approved' | 'rejected';
export type ImageUploader = 'admin' | 'hospital';

const chars = (s: string): number => Array.from(s).length;

/** What the first bytes say it is: GIF ("GIF87a" / "GIF89a"), or JPEG / PNG / WebP like the other uploads; null when none. */
export function detectTreatmentImageKind(head: Buffer): TreatmentImageKind | null {
  if (head.length >= 6) {
    const sig = head.subarray(0, 6).toString('latin1');
    if (sig === 'GIF87a' || sig === 'GIF89a') return 'gif';
  }
  return detectImageKind(head);
}

const extensionOf = (name: unknown): string => {
  const s = typeof name === 'string' ? name : '';
  const i = s.lastIndexOf('.');
  return i < 0 ? '' : s.slice(i + 1).toLowerCase();
};

export type ImageCheck = { ok: true; kind: TreatmentImageKind } | { ok: false; error: 'invalid_image' | 'too_large' };

/** Size (<= 10 MB), extension whitelist and real type of the uploaded file. The extension must agree with the real type; the stored extension comes from the real type. */
export function checkTreatmentImage(originalName: unknown, size: number, head: Buffer): ImageCheck {
  if (!Number.isFinite(size) || size <= 0) return { ok: false, error: 'invalid_image' };
  if (size > MAX_TREATMENT_IMAGE_BYTES) return { ok: false, error: 'too_large' };
  const ext = extensionOf(originalName);
  if (!EXTENSIONS.includes(ext)) return { ok: false, error: 'invalid_image' };
  const kind = detectTreatmentImageKind(head);
  if (!kind) return { ok: false, error: 'invalid_image' };
  const claimed = ext === 'jpeg' ? 'jpg' : ext;
  return claimed === kind ? { ok: true, kind } : { ok: false, error: 'invalid_image' };
}

/** `imageUrl` field: absent / blank -> null (no URL); otherwise a http(s) address of at most 2048 bytes without white space; anything else -> invalid. */
export function parseImageUrl(raw: unknown): { ok: true; url: string | null } | { ok: false } {
  if (raw === undefined || raw === null) return { ok: true, url: null };
  if (typeof raw !== 'string') return { ok: false };
  const url = raw.trim();
  if (url === '') return { ok: true, url: null };
  if (Buffer.byteLength(url, 'utf8') > MAX_URL_BYTES || /[\s\u0000-\u001f\u007f]/.test(url)) return { ok: false };
  // the address must really spell scheme://host (the URL parser alone would accept "https:///a.jpg" or "https:\\host")
  if (!/^https?:\/\/[^/\\?#@]/i.test(url)) return { ok: false };
  try {
    const u = new URL(url);
    if ((u.protocol !== 'http:' && u.protocol !== 'https:') || u.hostname === '') return { ok: false };
  } catch {
    return { ok: false };
  }
  return { ok: true, url };
}

export type CaptionParsed = { ok: true; caption: string } | { ok: false; error: 'caption_too_long' | 'upload_failed' };
/** `caption` field: trimmed, at most 200 characters (counted as code points; the PHP page does not cut, the column is VARCHAR(200)). A repeated / non-text field is an upload problem. */
export function parseCaption(raw: unknown): CaptionParsed {
  if (raw === undefined || raw === null) return { ok: true, caption: '' };
  if (typeof raw !== 'string') return { ok: false, error: 'upload_failed' };
  const caption = raw.trim();
  return chars(caption) > MAX_CAPTION ? { ok: false, error: 'caption_too_long' } : { ok: true, caption };
}

export interface StoredTreatmentImage {
  /** Value of image_url: relative to the PHP admin directory. */
  url: string;
  absolute: string;
  dir: string;
}

/**
 * Where an uploaded file goes: <root>/uploads/treatments/treat_<unix seconds>_<8 hex>.<jpg|png|gif|webp>.
 * Nothing in it comes from the client: the time is a validated integer, the name part random hex, the extension comes from the real type.
 */
export function buildTreatmentImagePath(root: string, unixSeconds: number, hex: string, kind: TreatmentImageKind): StoredTreatmentImage {
  if (!Number.isInteger(unixSeconds) || unixSeconds <= 0) throw new Error('invalid time');
  if (!/^[0-9a-f]{8}$/.test(hex)) throw new Error('invalid file token');
  const base = path.resolve(root);
  const dir = path.resolve(base, TREATMENT_IMAGE_DIR);
  const file = `treat_${unixSeconds}_${hex}.${kind}`;
  const absolute = path.resolve(dir, file);
  if (!dir.startsWith(base + path.sep) || !absolute.startsWith(dir + path.sep)) throw new Error('path leaves the upload root');
  return { url: `${TREATMENT_IMAGE_DIR}/${file}`, absolute, dir };
}

/** Absolute path of a stored upload to remove, or null when the value is not one of ours (other folder, "..", absolute URL) or the root is unusable. */
export function removablePath(root: string, storedUrl: string): string | null {
  if (!root || !path.isAbsolute(root)) return null;
  if (!storedUrl.startsWith(`${TREATMENT_IMAGE_DIR}/`) || storedUrl.includes('..') || storedUrl.includes('\\') || storedUrl.includes('\0')) return null;
  const dir = path.resolve(root, TREATMENT_IMAGE_DIR);
  const absolute = path.resolve(root, storedUrl);
  return absolute.startsWith(dir + path.sep) ? absolute : null;
}

export type InvalidField = { error: 'invalid_field'; field: 'direction' | 'decision' | 'reason' };
export type MoveParsed = { ok: true; direction: 'up' | 'down' } | { ok: false; body: InvalidField };
export function parseMoveBody(body: unknown): MoveParsed {
  const d = body !== null && typeof body === 'object' && !Array.isArray(body) ? (body as Record<string, unknown>).direction : undefined;
  return d === 'up' || d === 'down' ? { ok: true, direction: d } : { ok: false, body: { error: 'invalid_field', field: 'direction' } };
}

export type ReviewParsed =
  | { ok: true; decision: 'approve' | 'reject'; reason: string }
  | { ok: false; body: InvalidField | { error: 'reason_required' | 'reason_too_long' } };
/** { decision: 'approve' | 'reject', reason?: string }: a rejection needs a reason (trimmed, 1..1000 characters); on approval a given reason is only length-checked and not stored. */
export function parseReviewBody(body: unknown): ReviewParsed {
  const b = body !== null && typeof body === 'object' && !Array.isArray(body) ? (body as Record<string, unknown>) : {};
  if (b.decision !== 'approve' && b.decision !== 'reject') return { ok: false, body: { error: 'invalid_field', field: 'decision' } };
  if (b.reason !== undefined && b.reason !== null && typeof b.reason !== 'string') return { ok: false, body: { error: 'invalid_field', field: 'reason' } };
  const reason = typeof b.reason === 'string' ? b.reason.trim() : '';
  if (b.decision === 'reject' && reason === '') return { ok: false, body: { error: 'reason_required' } };
  if (chars(reason) > MAX_REASON) return { ok: false, body: { error: 'reason_too_long' } };
  return { ok: true, decision: b.decision, reason };
}

/** Name stored in reviewed_by: at most 100 characters. */
export const reviewerName = (name: string): string => Array.from(name.trim() || 'super_admin').slice(0, MAX_REVIEWER).join('');

export const canReviewImages = (role: string): boolean => role === SUPER_ADMIN_ROLE;

// ---- answer shape ----

export interface TreatmentImage {
  id: number;
  url: string;
  caption: string;
  status: ImageStatus;
  uploadedBy: ImageUploader;
  rejectReason: string | null;
  reviewedAt: string | null;
  reviewedBy: string | null;
  sortOrder: number;
}

/** Rows of gangnamunni_hospital_treatment_images -> items in the order of the rows (a missing / unknown status counts as approved, like the PHP page). */
export function buildImages(rows: Row[]): TreatmentImage[] {
  return rows.map((r) => {
    const status = str(r.approval_status);
    const reason = str(r.reject_reason).trim();
    const by = str(r.reviewed_by).trim();
    return {
      id: num(r.id),
      url: str(r.image_url),
      caption: str(r.caption),
      status: status === 'pending' || status === 'rejected' ? status : 'approved',
      uploadedBy: str(r.uploaded_by) === 'hospital' ? 'hospital' : 'admin',
      rejectReason: reason === '' ? null : reason,
      reviewedAt: dateOrNull(r.reviewed_at),
      reviewedBy: by === '' ? null : by,
      sortOrder: num(r.sort_order),
    };
  });
}

// ---- reads (read-only pool) ----

export const assignmentExistsQuery = (htId: number, hospitalId: number): Query => ({
  sql: `SELECT id FROM gangnamunni_hospital_treatments WHERE id = ? AND hospital_id = ? LIMIT 1`,
  values: [htId, hospitalId],
});
export const imageRowsQuery = (htId: number): Query => ({
  sql: `SELECT id, image_url, caption, sort_order, approval_status, uploaded_by, reject_reason, reviewed_at, reviewed_by
    FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ? ORDER BY sort_order ASC, id ASC`,
  values: [htId],
});

// ---- writes (write pool, inside one transaction) ----

/** The assignment row is locked (not changed); it also gives the treatment id stored with a new image. */
export const lockAssignmentForImagesQuery = (htId: number, hospitalId: number): Query => ({
  sql: `SELECT id, treatment_id FROM gangnamunni_hospital_treatments WHERE id = ? AND hospital_id = ? LIMIT 1 FOR UPDATE`,
  values: [htId, hospitalId],
});
export const nextSortQuery = (htId: number): Query => ({
  sql: `SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ?`,
  values: [htId],
});
export const insertImageQuery = (htId: number, hospitalId: number, treatmentId: number, url: string, caption: string, sortOrder: number, status: ImageStatus, by: ImageUploader): Query => ({
  sql: `INSERT INTO gangnamunni_hospital_treatment_images (hospital_treatment_id, hospital_id, treatment_id, image_url, caption, sort_order, approval_status, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
  values: [htId, hospitalId, treatmentId, url, caption, sortOrder, status, by],
});
export const lockImageUrlQuery = (imageId: number, htId: number): Query => ({
  sql: `SELECT image_url FROM gangnamunni_hospital_treatment_images WHERE id = ? AND hospital_treatment_id = ? LIMIT 1 FOR UPDATE`,
  values: [imageId, htId],
});
export const deleteImageQuery = (imageId: number, htId: number): Query => ({
  sql: `DELETE FROM gangnamunni_hospital_treatment_images WHERE id = ? AND hospital_treatment_id = ?`,
  values: [imageId, htId],
});
export const lockImageOrderQuery = (htId: number): Query => ({
  sql: `SELECT id, approval_status FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ? ORDER BY sort_order ASC, id ASC FOR UPDATE`,
  values: [htId],
});
export const setSortOrderQuery = (sortOrder: number, imageId: number, htId: number): Query => ({
  sql: `UPDATE gangnamunni_hospital_treatment_images SET sort_order = ? WHERE id = ? AND hospital_treatment_id = ?`,
  values: [sortOrder, imageId, htId],
});
/** Only a pending image can be reviewed; the number of changed rows tells whether it was. */
export const reviewImageQuery = (status: 'approved' | 'rejected', reason: string | null, reviewer: string, imageId: number, htId: number): Query => ({
  sql: `UPDATE gangnamunni_hospital_treatment_images SET approval_status = ?, reject_reason = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND hospital_treatment_id = ? AND approval_status = 'pending'`,
  values: [status, reason, reviewer, imageId, htId],
});

export type MoveResult = { ok: true; order: number[] } | { ok: false; error: 'not_found' | 'not_approved' | 'cannot_move' };

/**
 * Moves an approved picture one place among the approved ones only (the order the patient sees). The approved pictures are put, in their new order,
 * into the places the approved ones held; pending / rejected pictures keep their relative places. `rows` are ordered by (sort_order, id).
 */
export function moveApproved(rows: { id: number; status: string }[], imageId: number, direction: 'up' | 'down'): MoveResult {
  const row = rows.find((r) => r.id === imageId);
  if (!row) return { ok: false, error: 'not_found' };
  if (row.status !== 'approved') return { ok: false, error: 'not_approved' };
  const approved = rows.filter((r) => r.status === 'approved').map((r) => r.id);
  const at = approved.indexOf(imageId);
  const to = direction === 'up' ? at - 1 : at + 1;
  if (to < 0 || to >= approved.length) return { ok: false, error: 'cannot_move' };
  [approved[at], approved[to]] = [approved[to], approved[at]];
  let next = 0;
  return { ok: true, order: rows.map((r) => (r.status === 'approved' ? approved[next++] : r.id)) };
}

/** Standard picture of the treatment (master table), shown to the patient when no approved picture exists; blank = null. */
export const defaultImageQuery = (htId: number, hospitalId: number): Query => ({
  sql: `SELECT tm.default_image_url FROM gangnamunni_hospital_treatments ht JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
    WHERE ht.id = ? AND ht.hospital_id = ? AND tm.is_active = 1 LIMIT 1`,
  values: [htId, hospitalId],
});
