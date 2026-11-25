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
  /** question texts in order; present only when the actor may manage and the questions switch is on */
  questions?: string[];
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
  /** state of the prices switch */
  pricesEnabled: boolean;
  /** state of the questions switch (false also when the question texts could not be read) */
  questionsEnabled: boolean;
  /** state of the images switch (the gallery of the detail images) */
  imagesEnabled: boolean;
  /** units for the price form; only filled when the actor may manage and the prices switch is on */
  priceUnits: string[];
  /** body part groups for the price form (from the body part tables); empty = the form uses its own built-in list */
  priceBodyParts: BodyPartGroupItem[];
  available: AvailableItem[];
}

// ---- price options (replace all options of one assignment) ----

export const MAX_PRICE_OPTIONS = 50;
export const MAX_OPTION_NAME = 100;
export const MAX_BODY_PART = 100;
export const MAX_BODY_PARTS_JOINED = 500;
export const MAX_UNIT = 20;
export const MAX_DOSAGE = 1_000_000;
export const MAX_PRICE = 2_000_000_000;
/** Body parts are stored in one column, joined with this separator. */
export const BODY_PART_SEPARATOR = '||';
export const DEFAULT_PRICE_UNITS = ['Box', 'cc', 'IU', 'kj', 'ml', '기타', '라인', '병', '샷', '시린지', '회'];

export type PriceField = 'optionName' | 'bodyParts' | 'dosageValue' | 'dosageUnit' | 'regularPrice' | 'price';
export type PriceErrorCode = 'no_options' | 'too_many' | 'invalid_field' | 'price_order' | 'multiple_representative' | 'duplicate_option';
export interface PriceError {
  error: PriceErrorCode;
  index?: number;
  field?: PriceField;
}
export interface PriceOptionInput {
  optionName: string;
  /** body parts joined with "||" */
  bodyPart: string;
  dosageValue: number;
  dosageUnit: string;
  regularPrice: number;
  price: number;
  isRepresentative: boolean;
}
export type PricesParsed = { ok: true; options: PriceOptionInput[] } | { ok: false; body: PriceError };

const isHangul = (ch: string): boolean => {
  const c = ch.codePointAt(0) ?? 0;
  return (c >= 0x1100 && c <= 0x11ff) || (c >= 0x3130 && c <= 0x318f) || (c >= 0xac00 && c <= 0xd7a3);
};

/** Unit order of the PHP page: names starting with a Latin letter or digit first, Korean after; case-insensitive byte order inside a group, exact bytes break ties. */
export function sortUnits(units: string[]): string[] {
  const group = (u: string): number => (u === '' ? 0 : isHangul(Array.from(u)[0]) ? 1 : 0);
  const bytes = (u: string): Buffer => Buffer.from(u, 'utf8');
  return [...units].sort((a, b) => group(a) - group(b) || Buffer.compare(bytes(a.toLowerCase()), bytes(b.toLowerCase())) || Buffer.compare(bytes(a), bytes(b)));
}

/** Names from the units table (rows already in table order) -> the sorted list; no usable row -> the standard list. */
export function buildUnits(rows: Row[]): string[] {
  const names: string[] = [];
  for (const r of rows) {
    const n = str(r.name).trim();
    if (n !== '' && !names.includes(n)) names.push(n);
  }
  return sortUnits(names.length > 0 ? names : DEFAULT_PRICE_UNITS);
}

const fail = (error: PriceErrorCode, index?: number, field?: PriceField): PricesParsed => ({
  ok: false,
  body: { error, ...(index !== undefined ? { index } : {}), ...(field ? { field } : {}) },
});

const blankNumber = (v: unknown): boolean => v === undefined || v === null || v === '' || v === 0;
const blankText = (v: unknown): boolean => v === undefined || v === null || (typeof v === 'string' && v.trim() === '');

/** A row with nothing filled in is skipped (the form keeps an empty row). */
function isBlankRow(o: Record<string, unknown>): boolean {
  const parts = o.bodyParts;
  const noParts = parts === undefined || parts === null || (Array.isArray(parts) && parts.every((p) => blankText(p)));
  return blankText(o.optionName) && noParts && blankNumber(o.dosageValue) && blankText(o.dosageUnit) && blankNumber(o.regularPrice) && blankNumber(o.price);
}

const wholePrice = (v: unknown): number | null => (typeof v === 'number' && Number.isInteger(v) && v >= 0 && v <= MAX_PRICE ? v : null);

/**
 * { options: [...] } -> the rows to store. Rules of the PHP page: blank rows are skipped; a filled row needs a dosage > 0 and a unit; at least one option;
 * prices are whole won (both optional); list price may not be below the sale price; one representative at most; no two options with the same name, body parts, dosage and unit.
 * Errors carry the position in the sent list (`index`) and the field, so the form can mark the row.
 */
export function parsePricesBody(body: unknown): PricesParsed {
  if (body === null || typeof body !== 'object' || Array.isArray(body)) return fail('no_options');
  const list = (body as Record<string, unknown>).options;
  if (!Array.isArray(list) || list.length === 0) return fail('no_options');
  if (list.length > MAX_PRICE_OPTIONS) return fail('too_many');
  const out: PriceOptionInput[] = [];
  const seen = new Set<string>();
  let representatives = 0;
  for (let i = 0; i < list.length; i++) {
    const o = list[i];
    if (o === null || typeof o !== 'object' || Array.isArray(o)) return fail('invalid_field', i);
    const r = o as Record<string, unknown>;
    if (isBlankRow(r)) continue;
    if (r.optionName !== undefined && r.optionName !== null && typeof r.optionName !== 'string') return fail('invalid_field', i, 'optionName');
    const optionName = ((r.optionName as string | null | undefined) ?? '').trim();
    if (Array.from(optionName).length > MAX_OPTION_NAME) return fail('invalid_field', i, 'optionName');
    let parts: string[] = [];
    if (r.bodyParts !== undefined && r.bodyParts !== null) {
      if (!Array.isArray(r.bodyParts)) return fail('invalid_field', i, 'bodyParts');
      for (const p of r.bodyParts) {
        const t = typeof p === 'string' ? p.trim() : '';
        if (t === '' || Array.from(t).length > MAX_BODY_PART || t.includes(BODY_PART_SEPARATOR)) return fail('invalid_field', i, 'bodyParts');
        parts.push(t);
      }
    }
    const bodyPart = parts.join(BODY_PART_SEPARATOR);
    if (Array.from(bodyPart).length > MAX_BODY_PARTS_JOINED) return fail('invalid_field', i, 'bodyParts');
    const dv = r.dosageValue;
    if (typeof dv !== 'number' || !Number.isFinite(dv) || dv <= 0 || dv > MAX_DOSAGE || Math.abs(dv * 100 - Math.round(dv * 100)) > 1e-6) return fail('invalid_field', i, 'dosageValue');
    const dosageValue = Math.round(dv * 100) / 100;
    if (dosageValue <= 0) return fail('invalid_field', i, 'dosageValue');
    const dosageUnit = typeof r.dosageUnit === 'string' ? r.dosageUnit.trim() : '';
    if (dosageUnit === '' || Array.from(dosageUnit).length > MAX_UNIT) return fail('invalid_field', i, 'dosageUnit');
    const regularPrice = blankNumber(r.regularPrice) ? 0 : wholePrice(r.regularPrice);
    if (regularPrice === null) return fail('invalid_field', i, 'regularPrice');
    const price = blankNumber(r.price) ? 0 : wholePrice(r.price);
    if (price === null) return fail('invalid_field', i, 'price');
    if (r.isRepresentative !== undefined && r.isRepresentative !== null && typeof r.isRepresentative !== 'boolean') return fail('invalid_field', i);
    if (regularPrice > 0 && price > 0 && regularPrice < price) return fail('price_order', i);
    out.push({ optionName, bodyPart, dosageValue, dosageUnit, regularPrice, price, isRepresentative: r.isRepresentative === true });
    if (r.isRepresentative === true) representatives += 1;
    const key = [optionName.toLowerCase(), bodyPart.toLowerCase(), dosageValue, dosageUnit.toLowerCase()].join('|');
    if (seen.has(key)) return fail('duplicate_option', i);
    seen.add(key);
  }
  if (out.length === 0) return fail('no_options');
  if (representatives > 1) return fail('multiple_representative');
  return { ok: true, options: out };
}

export interface BodyPartGroupItem { label: string; parts: string[] }

/** Active groups and parts in table order. */
export const bodyPartsQuery = (): Query => ({
  sql: `SELECT g.id AS gid, g.name AS group_name, p.name AS part_name
        FROM gangnamunni_body_part_groups g JOIN gangnamunni_body_parts p ON p.group_id = g.id
        WHERE g.is_active = 1 AND p.is_active = 1 ORDER BY g.sort_order ASC, g.id ASC, p.sort_order ASC, p.id ASC`,
  values: [],
});

/** Rows (already in order) -> groups with their parts; a name the price rules refuse (empty, over 100 characters, containing the separator) or a repeat is left out. */
export function buildBodyParts(rows: Row[]): BodyPartGroupItem[] {
  const groups = new Map<number, BodyPartGroupItem>();
  const seen = new Set<string>();
  for (const r of rows) {
    const part = str(r.part_name).trim();
    const label = str(r.group_name).trim();
    if (part === '' || label === '' || Array.from(part).length > MAX_BODY_PART || part.includes('||') || seen.has(part)) continue;
    seen.add(part);
    const gid = Number(r.gid);
    const g = groups.get(gid) ?? { label, parts: [] };
    g.parts.push(part);
    groups.set(gid, g);
  }
  return [...groups.values()];
}

export const unitsQuery = (): Query => ({ sql: 'SELECT name FROM gangnamunni_treatment_units WHERE is_active = 1 ORDER BY id ASC', values: [] });
export const insertPriceQuery = (htId: number, o: PriceOptionInput, sortOrder: number): Query => ({
  sql: `INSERT INTO gangnamunni_hospital_treatment_prices (hospital_treatment_id, option_name, body_part, dosage_value, dosage_unit, regular_price, price, is_representative, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
  values: [htId, o.optionName, o.bodyPart, o.dosageValue, o.dosageUnit, o.regularPrice, o.price, o.isRepresentative ? 1 : 0, sortOrder],
});
/** The assignment's own summary columns are cleared once it has detailed price options (the PHP page does the same). */
export const clearPriceSummaryQuery = (hospitalId: number, htId: number): Query => ({
  sql: `UPDATE gangnamunni_hospital_treatments SET price_min = 0, price_max = 0, dosage_value = NULL, dosage_unit = NULL, updated_at = NOW() WHERE id = ? AND hospital_id = ?`,
  values: [htId, hospitalId],
});

// ---- "before treatment" questions (replace all questions of one assignment) ----

export const MAX_QUESTIONS = 10;
export const MAX_QUESTION_LENGTH = 200;

export type QuestionErrorCode = 'invalid_body' | 'invalid_field' | 'too_long' | 'too_many';
export interface QuestionError {
  error: QuestionErrorCode;
  index?: number;
}
export type QuestionsParsed = { ok: true; questions: string[] } | { ok: false; body: QuestionError };

/** Trim and collapse every run of white space into one space. */
export const normalizeQuestion = (q: string): string => q.replace(/\s+/gu, ' ').trim();

/**
 * { questions: string[] } -> the questions to store. Each one is trimmed with inner white space collapsed; empty ones are skipped and exact repeats dropped.
 * A question longer than 200 characters (code points) or more than 10 distinct questions is rejected, not cut. `index` is the position in the sent list.
 * An empty list is allowed (it removes every question).
 */
export function parseQuestionsBody(body: unknown): QuestionsParsed {
  if (body === null || typeof body !== 'object' || Array.isArray(body)) return { ok: false, body: { error: 'invalid_body' } };
  const list = (body as Record<string, unknown>).questions;
  if (!Array.isArray(list)) return { ok: false, body: { error: 'invalid_body' } };
  const out: string[] = [];
  for (let i = 0; i < list.length; i++) {
    if (typeof list[i] !== 'string') return { ok: false, body: { error: 'invalid_field', index: i } };
    const q = normalizeQuestion(list[i] as string);
    if (q === '') continue;
    if (Array.from(q).length > MAX_QUESTION_LENGTH) return { ok: false, body: { error: 'too_long', index: i } };
    if (!out.includes(q)) out.push(q);
  }
  if (out.length > MAX_QUESTIONS) return { ok: false, body: { error: 'too_many' } };
  return { ok: true, questions: out };
}

export const questionTextsQuery = (htIds: number[]): Query => ({
  sql: `SELECT hospital_treatment_id, question FROM gangnamunni_treatment_questions WHERE hospital_treatment_id IN (${placeholders(htIds.length)}) ORDER BY sort_order ASC, id ASC`,
  values: htIds,
});
export const deleteQuestionsQuery = (htId: number): Query => ({ sql: `DELETE FROM gangnamunni_treatment_questions WHERE hospital_treatment_id = ?`, values: [htId] });
export const insertQuestionQuery = (htId: number, question: string, sortOrder: number): Query => ({
  sql: `INSERT INTO gangnamunni_treatment_questions (hospital_treatment_id, question, sort_order) VALUES (?, ?, ?)`,
  values: [htId, question, sortOrder],
});

/** Adds `questions` (rows already in order) to the items; an item without rows gets []. */
export function attachQuestions(items: TreatmentItem[], rows: Row[]): TreatmentItem[] {
  const byHt = new Map<number, string[]>();
  for (const r of rows) {
    const k = num(r.hospital_treatment_id);
    const list = byHt.get(k) ?? [];
    list.push(str(r.question));
    byHt.set(k, list);
  }
  return items.map((i) => ({ ...i, questions: byHt.get(i.id) ?? [] }));
}
