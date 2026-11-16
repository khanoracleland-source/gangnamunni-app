/** Pure parts of assigning treatments to a hospital: checking the request bodies, the SQL statements and the shape of the answers. */
import { HOSPITAL_ROLES, SUPER_ADMIN_ROLE } from '../admin-auth/admin.guard';
import { num, Query, str } from './hospitals.logic';

type Row = Record<string, unknown>;

/** Treatments per assign request. */
export const TREATMENT_ASSIGN_LIMIT = 300;
export const MAX_MIN_AGE = 120;
/** Key of the PHP settings table that says who may manage the treatments of a hospital. */
export const MGMT_SETTING_KEY = 'treatment_mgmt_mode';
export const DEFAULT_MGMT_MODE = 'both';
const MAX_ID = 2_147_483_647;

/**
 * Who may assign, remove, promote or limit treatments (same rule as the PHP page):
 * a super admin always; a hospital account when the mode is hospital_only or both; any other admin when it is admin_only or both.
 */
export function canManageTreatments(role: string, mode: string): boolean {
  if (role === SUPER_ADMIN_ROLE) return true;
  const m = mode.trim();
  return HOSPITAL_ROLES.includes(role) ? m === 'hospital_only' || m === 'both' : m === 'admin_only' || m === 'both';
}

export type AssignParsed = { ok: true; ids: number[] } | { ok: false; body: { error: 'no_treatments' } };

/** { treatmentIds: number[] } -> distinct positive integer ids in the order sent. Anything else (also more than 300 entries) is "no_treatments". */
export function parseAssignBody(body: unknown): AssignParsed {
  const fail: AssignParsed = { ok: false, body: { error: 'no_treatments' } };
  if (body === null || typeof body !== 'object' || Array.isArray(body)) return fail;
  const list = (body as Record<string, unknown>).treatmentIds;
  if (!Array.isArray(list) || list.length === 0 || list.length > TREATMENT_ASSIGN_LIMIT) return fail;
  const ids: number[] = [];
  for (const v of list) {
    if (typeof v !== 'number' || !Number.isInteger(v) || v <= 0 || v > MAX_ID) return fail;
    if (!ids.includes(v)) ids.push(v);
  }
  return { ok: true, ids };
}

export interface PatchInput {
  isPromoted?: 0 | 1;
  /** present (also as null = no limit) only when the request carried the field */
  minAge?: number | null;
}
export type PatchParsed = { ok: true; patch: PatchInput } | { ok: false; body: { error: 'invalid_field'; field?: 'isPromoted' | 'minAge' } };

/** { isPromoted?: boolean, minAge?: number | null }: at least one field; minAge null or 0 means no limit, otherwise an integer 1..120. */
export function parsePatchBody(body: unknown): PatchParsed {
  if (body === null || typeof body !== 'object' || Array.isArray(body)) return { ok: false, body: { error: 'invalid_field' } };
  const b = body as Record<string, unknown>;
  const patch: PatchInput = {};
  if (b.isPromoted !== undefined) {
    if (typeof b.isPromoted !== 'boolean') return { ok: false, body: { error: 'invalid_field', field: 'isPromoted' } };
    patch.isPromoted = b.isPromoted ? 1 : 0;
  }
  if (b.minAge !== undefined) {
    const v = b.minAge;
    if (v === null) patch.minAge = null;
    else if (typeof v === 'number' && Number.isInteger(v) && v >= 0 && v <= MAX_MIN_AGE) patch.minAge = v === 0 ? null : v;
    else return { ok: false, body: { error: 'invalid_field', field: 'minAge' } };
  }
  if (patch.isPromoted === undefined && patch.minAge === undefined) return { ok: false, body: { error: 'invalid_field' } };
  return { ok: true, patch };
}

const placeholders = (n: number): string => Array.from({ length: n }, () => '?').join(',');

/** Database problems that only mean "this optional table / column is not there (or not readable)". */
const MISSING_CODES = ['ER_NO_SUCH_TABLE', 'ER_BAD_FIELD_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR'];
const MISSING_ERRNO = [1146, 1054, 1143, 1142];
export function isMissingObject(e: unknown): boolean {
  const err = e as { code?: string; errno?: number };
  return MISSING_CODES.includes(err?.code || '') || MISSING_ERRNO.includes(err?.errno || 0);
}
/** The same, and the message names the min_age column. */
export function isMinAgeProblem(e: unknown): boolean {
  return isMissingObject(e) && String((e as { message?: string })?.message || '').includes('min_age');
}

// ---- reads (read-only pool) ----

export const mgmtModeQuery = (): Query => ({ sql: 'SELECT `value` FROM settings WHERE `key` = ? LIMIT 1', values: [MGMT_SETTING_KEY] });
export const hospitalExistsQuery = (id: number, approvedOnly: boolean): Query => ({
  sql: `SELECT id FROM gangnamunni_hospitals WHERE id = ?${approvedOnly ? " AND status = 'approved'" : ''} LIMIT 1`,
  values: [id],
});
/** Active assignments of a hospital in the PHP order (category, treatment sort order, name). */
export const assignedQuery = (hospitalId: number, withMinAge: boolean): Query => ({
  sql: `SELECT ht.id, ht.treatment_id, ht.is_promoted, ht.note, ht.registered_by${withMinAge ? ', ht.min_age' : ''}, tm.name AS treatment_name, tm.hashtag, tc.id AS category_id, tc.name AS category_name
    FROM gangnamunni_hospital_treatments ht
    LEFT JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
    LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
    WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1
    ORDER BY tc.sort_order ASC, tm.sort_order ASC, tm.name ASC, ht.id ASC`,
  values: [hospitalId],
});
export const priceOptionsQuery = (htIds: number[]): Query => ({
  sql: `SELECT hospital_treatment_id, option_name, body_part, dosage_value, dosage_unit, regular_price, price, is_representative
    FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id IN (${placeholders(htIds.length)}) ORDER BY sort_order ASC, id ASC`,
  values: htIds,
});
export const imageCountsQuery = (htIds: number[]): Query => ({
  sql: `SELECT hospital_treatment_id, COUNT(*) AS cnt, COALESCE(SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END), 0) AS pending
    FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id IN (${placeholders(htIds.length)}) GROUP BY hospital_treatment_id`,
  values: htIds,
});
export const questionCountsQuery = (htIds: number[]): Query => ({
  sql: `SELECT hospital_treatment_id, COUNT(*) AS cnt FROM gangnamunni_treatment_questions WHERE hospital_treatment_id IN (${placeholders(htIds.length)}) GROUP BY hospital_treatment_id`,
  values: htIds,
});
/** Treatments the hospital does not have yet (active master rows minus its active / pending assignments). */
export const availableQuery = (hospitalId: number): Query => ({
  sql: `SELECT tm.id, tm.name, tm.hashtag, tc.id AS category_id, tc.name AS category_name
    FROM gangnamunni_treatment_master tm
    LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
    WHERE tm.is_active = 1
      AND NOT EXISTS (SELECT 1 FROM gangnamunni_hospital_treatments ht WHERE ht.hospital_id = ? AND ht.treatment_id = tm.id AND ht.is_active = 1 AND ht.status IN ('active','pending'))
    ORDER BY tc.sort_order ASC, tm.name ASC, tm.id ASC`,
  values: [hospitalId],
});

// ---- writes (write pool, inside one transaction) ----

export const activeMasterQuery = (ids: number[]): Query => ({
  sql: `SELECT id FROM gangnamunni_treatment_master WHERE is_active = 1 AND id IN (${placeholders(ids.length)})`,
  values: ids,
});
export const existingAssignmentsQuery = (hospitalId: number, ids: number[]): Query => ({
  sql: `SELECT id, treatment_id, status, is_active FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND treatment_id IN (${placeholders(ids.length)}) ORDER BY id ASC`,
  values: [hospitalId, ...ids],
});
export const insertAssignmentQuery = (hospitalId: number, treatmentId: number, registeredBy: 'admin' | 'hospital'): Query => ({
  sql: `INSERT INTO gangnamunni_hospital_treatments (hospital_id, treatment_id, price_min, price_max, is_promoted, note, status, registered_by, is_active, created_at, updated_at)
    VALUES (?, ?, 0, 0, 0, '', 'active', ?, 1, NOW(), NOW())`,
  values: [hospitalId, treatmentId, registeredBy],
});
export const reactivateQuery = (hospitalId: number, htId: number): Query => ({
  sql: `UPDATE gangnamunni_hospital_treatments SET status = 'active', is_active = 1, reject_reason = NULL, is_promoted = 0, price_min = 0, price_max = 0, dosage_value = NULL, dosage_unit = NULL, updated_at = NOW() WHERE id = ? AND hospital_id = ?`,
  values: [htId, hospitalId],
});
export const lockAssignmentQuery = (hospitalId: number, htId: number): Query => ({
  sql: `SELECT id, is_active FROM gangnamunni_hospital_treatments WHERE id = ? AND hospital_id = ? LIMIT 1 FOR UPDATE`,
  values: [htId, hospitalId],
});
export const deletePriceOptionsQuery = (htId: number): Query => ({ sql: `DELETE FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id = ?`, values: [htId] });
export const deleteImagesQuery = (htId: number): Query => ({ sql: `DELETE FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ?`, values: [htId] });
export const removeAssignmentQuery = (hospitalId: number, htId: number): Query => ({
  sql: `UPDATE gangnamunni_hospital_treatments SET is_active = 0, is_promoted = 0, price_min = 0, price_max = 0, dosage_value = NULL, dosage_unit = NULL, updated_at = NOW() WHERE id = ? AND hospital_id = ? AND is_active = 1`,
  values: [htId, hospitalId],
});
export const setPromotedQuery = (hospitalId: number, htId: number, promoted: 0 | 1): Query => ({
  sql: `UPDATE gangnamunni_hospital_treatments SET is_promoted = ?, updated_at = NOW() WHERE id = ? AND hospital_id = ?`,
  values: [promoted, htId, hospitalId],
});
export const setMinAgeQuery = (hospitalId: number, htId: number, minAge: number | null): Query => ({
  sql: `UPDATE gangnamunni_hospital_treatments SET min_age = ?, updated_at = NOW() WHERE id = ? AND hospital_id = ?`,
  values: [minAge, htId, hospitalId],
});

// ---- answer shapes ----

/** Dosage as the PHP page prints it: two decimals with trailing zeros (and a trailing dot) removed. */
export function formatDosage(v: unknown): string {
  const x = Number(v);
  const fixed = (Number.isFinite(x) ? x : 0).toFixed(2);
  return fixed.replace(/0+$/, '').replace(/\.$/, '');
}

export interface PriceOption {
  optionName: string;
  bodyPart: string;
  dosageValue: string;
  dosageUnit: string;
  regularPrice: number;
  price: number;
  isRepresentative: boolean;
}
export interface TreatmentItem {
  id: number;
  treatmentId: number;
  name: string;
  hashtag: string;
  categoryId: number | null;
  categoryName: string | null;
  isPromoted: boolean;
  note: string;
  registeredBy: 'admin' | 'hospital';
  minAge: number | null;
  priceOptions: PriceOption[];
  imageCount: number;
  pendingImageCount: number;
  questionCount: number;
}
export interface AvailableItem {
  id: number;
  name: string;
  hashtag: string;
  categoryId: number | null;
  categoryName: string | null;
}

const idOrNull = (v: unknown): number | null => (v === null || v === undefined || v === '' ? null : num(v));
const textOrNull = (v: unknown): string | null => (v === null || v === undefined ? null : str(v));

export interface ItemParts {
  rows: Row[];
  prices: Row[];
  images: Row[];
  questions: Row[];
}

/** Assignment rows plus the optional parts (anything missing is empty / 0) -> items in the order of `rows`. */
export function buildItems(p: ItemParts): TreatmentItem[] {
  const prices = new Map<number, PriceOption[]>();
  for (const r of p.prices) {
    const k = num(r.hospital_treatment_id);
    const list = prices.get(k) ?? [];
    list.push({
      optionName: str(r.option_name),
      bodyPart: str(r.body_part),
      dosageValue: formatDosage(r.dosage_value),
      dosageUnit: str(r.dosage_unit),
      regularPrice: Math.trunc(num(r.regular_price)),
      price: Math.trunc(num(r.price)),
      isRepresentative: num(r.is_representative) === 1,
    });
    prices.set(k, list);
  }
  const images = new Map<number, { cnt: number; pending: number }>();
  for (const r of p.images) images.set(num(r.hospital_treatment_id), { cnt: num(r.cnt), pending: num(r.pending) });
  const questions = new Map<number, number>();
  for (const r of p.questions) questions.set(num(r.hospital_treatment_id), num(r.cnt));
  return p.rows.map((r) => {
    const id = num(r.id);
    const age = idOrNull(r.min_age);
    return {
      id,
      treatmentId: num(r.treatment_id),
      name: str(r.treatment_name),
      hashtag: str(r.hashtag),
      categoryId: idOrNull(r.category_id),
      categoryName: textOrNull(r.category_name),
      isPromoted: num(r.is_promoted) === 1,
      note: str(r.note),
      registeredBy: str(r.registered_by) === 'hospital' ? 'hospital' : 'admin',
      minAge: age !== null && age > 0 ? age : null,
      priceOptions: prices.get(id) ?? [],
      imageCount: images.get(id)?.cnt ?? 0,
      pendingImageCount: images.get(id)?.pending ?? 0,
      questionCount: questions.get(id) ?? 0,
    };
  });
}

export const buildAvailable = (rows: Row[]): AvailableItem[] =>
  rows.map((r) => ({ id: num(r.id), name: str(r.name), hashtag: str(r.hashtag), categoryId: idOrNull(r.category_id), categoryName: textOrNull(r.category_name) }));

export interface TreatmentsView {
  items: TreatmentItem[];
  count: number;
  promotedCount: number;
  canManage: boolean;
  minAgeSupported: boolean;
  removeEnabled: boolean;
  available: AvailableItem[];
}
