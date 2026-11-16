/** Pure parts of the hospital image upload: file checks, storage paths and the shape of the answer. */
import * as path from 'path';
import { Query } from './hospitals.logic';

type Row = Record<string, unknown>;

export type ImageField = 'main' | 'interior';
export type ImageKind = 'jpg' | 'png' | 'webp';

/** Interior images per hospital (active ones, main and logo excluded). */
export const DETAIL_LIMIT = 10;
/** One main image plus the interior ones. */
export const MAX_FILES = 1 + DETAIL_LIMIT;
export const MAX_MAIN_BYTES = 5 * 1024 * 1024;
export const MAX_INTERIOR_BYTES = 10 * 1024 * 1024;

const RULES: Record<ImageField, { maxBytes: number; extensions: string[]; kinds: ImageKind[] }> = {
  main: { maxBytes: MAX_MAIN_BYTES, extensions: ['jpg', 'jpeg', 'png'], kinds: ['jpg', 'png'] },
  interior: { maxBytes: MAX_INTERIOR_BYTES, extensions: ['jpg', 'jpeg', 'png', 'webp'], kinds: ['jpg', 'png', 'webp'] },
};

/** What the first bytes of a file say it is (JPEG FF D8 FF, PNG 89 50 4E 47 0D 0A 1A 0A, WebP "RIFF"....."WEBP"), or null. */
export function detectImageKind(head: Buffer): ImageKind | null {
  if (head.length >= 3 && head[0] === 0xff && head[1] === 0xd8 && head[2] === 0xff) return 'jpg';
  if (head.length >= 8 && head.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return 'png';
  if (head.length >= 12 && head.subarray(0, 4).toString('latin1') === 'RIFF' && head.subarray(8, 12).toString('latin1') === 'WEBP') return 'webp';
  return null;
}

/** Lower-case extension of a client-sent file name (only used to compare with the real type; the name is never stored). */
export const extensionOf = (name: unknown): string => {
  const s = typeof name === 'string' ? name : '';
  const i = s.lastIndexOf('.');
  return i < 0 ? '' : s.slice(i + 1).toLowerCase();
};

export type ImageCheck = { ok: true; kind: ImageKind } | { ok: false; error: 'invalid_type' | 'too_large' };

/** Size, extension whitelist and real type of one file of the given field. The extension must agree with the real type. */
export function checkImage(field: ImageField, originalName: unknown, size: number, head: Buffer): ImageCheck {
  const rule = RULES[field];
  if (!Number.isFinite(size) || size <= 0) return { ok: false, error: 'invalid_type' };
  if (size > rule.maxBytes) return { ok: false, error: 'too_large' };
  const ext = extensionOf(originalName);
  if (!rule.extensions.includes(ext)) return { ok: false, error: 'invalid_type' };
  const kind = detectImageKind(head);
  if (!kind || !rule.kinds.includes(kind)) return { ok: false, error: 'invalid_type' };
  const claimed: ImageKind = ext === 'jpeg' ? 'jpg' : (ext as ImageKind);
  if (claimed !== kind) return { ok: false, error: 'invalid_type' };
  return { ok: true, kind };
}

export interface StoredPath {
  /** Value of hospital_images.image_url: relative to the PHP admin directory. */
  url: string;
  /** Absolute file path inside the upload root. */
  absolute: string;
  /** Absolute directory of the hospital. */
  dir: string;
}

/**
 * Where a new file goes: <root>/uploads/hospitals/<id>/<main|interior>_<32 hex>.<jpg|png|webp>.
 * Nothing in it comes from the client: the id is a validated integer, the name is random hex, the extension comes from the real type.
 * Throws when the id, the random part or the result would leave the root.
 */
export function buildStoredPath(root: string, hospitalId: number, field: ImageField, hex: string, kind: ImageKind): StoredPath {
  if (!Number.isInteger(hospitalId) || hospitalId <= 0) throw new Error('invalid hospital id');
  if (!/^[0-9a-f]{32}$/.test(hex)) throw new Error('invalid file token');
  const base = path.resolve(root);
  const rel = `uploads/hospitals/${hospitalId}`;
  const dir = path.resolve(base, rel);
  const absolute = path.resolve(dir, `${field}_${hex}.${kind}`);
  if (!dir.startsWith(base + path.sep) || !absolute.startsWith(dir + path.sep)) throw new Error('path leaves the upload root');
  return { url: `${rel}/${field}_${hex}.${kind}`, absolute, dir };
}

export interface ImageItem {
  id: number;
  url: string;
  type: string;
  sortOrder: number;
  isActive: boolean;
}
export interface ImagesResult {
  items: ImageItem[];
  mainUrl: string | null;
  detailCount: number;
  detailLimit: number;
}

const num = (v: unknown): number => {
  const x = Number(v);
  return Number.isFinite(x) ? x : 0;
};

/** Rows of hospital_images (ordered sort_order, id) -> items, the active main image and the number of active interior images. */
export function summarizeImages(rows: Row[]): ImagesResult {
  const items: ImageItem[] = rows.map((r) => ({
    id: num(r.id), url: String(r.image_url ?? ''), type: String(r.image_type ?? ''), sortOrder: num(r.sort_order), isActive: num(r.is_active) === 1,
  }));
  const main = items.find((i) => i.type === 'main' && i.isActive);
  const detailCount = items.filter((i) => i.isActive && i.type !== 'main' && i.type !== 'logo').length;
  return { items, mainUrl: main ? main.url : null, detailCount, detailLimit: DETAIL_LIMIT };
}

export const imageRowsQuery = (id: number): Query => ({
  sql: `SELECT id, image_url, image_type, sort_order, is_active FROM hospital_images WHERE hospital_id = ? ORDER BY sort_order ASC, id ASC`,
  values: [id],
});
export const lockHospitalQuery = (id: number): Query => ({ sql: `SELECT id, updated_at FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE`, values: [id] });
export const hospitalUpdatedAtQuery = (id: number): Query => ({ sql: `SELECT updated_at FROM gangnamunni_hospitals WHERE id = ? LIMIT 1`, values: [id] });
export const interiorCountQuery = (id: number): Query => ({
  sql: `SELECT COUNT(*) AS cnt FROM hospital_images WHERE hospital_id = ? AND is_active = 1 AND image_type NOT IN ('main','logo')`,
  values: [id],
});
export const findMainQuery = (id: number): Query => ({
  sql: `SELECT id FROM hospital_images WHERE hospital_id = ? AND image_type = 'main' ORDER BY is_active DESC, sort_order ASC, id ASC LIMIT 1`,
  values: [id],
});
export const updateMainQuery = (imageId: number, hospitalId: number, url: string): Query => ({
  sql: `UPDATE hospital_images SET image_url = ?, sort_order = 0, is_active = 1 WHERE id = ? AND hospital_id = ?`,
  values: [url, imageId, hospitalId],
});
export const insertImageQuery = (hospitalId: number, url: string, type: ImageField, sortOrder: number): Query => ({
  sql: `INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())`,
  values: [hospitalId, url, type, sortOrder],
});
export const disableOtherMainsQuery = (hospitalId: number, keepId: number): Query => ({
  sql: `UPDATE hospital_images SET is_active = 0 WHERE hospital_id = ? AND image_type = 'main' AND id <> ?`,
  values: [hospitalId, keepId],
});
export const syncMainUrlQuery = (hospitalId: number, url: string): Query => ({
  sql: `UPDATE gangnamunni_hospitals SET main_image_url = ?, updated_at = NOW() WHERE id = ?`,
  values: [url, hospitalId],
});
export const maxSortQuery = (hospitalId: number): Query => ({
  sql: `SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM hospital_images WHERE hospital_id = ?`,
  values: [hospitalId],
});
