/** Pure parts of the writing treatment master routes: reading request bodies into checked values and the SQL they turn into. */
import { Query } from '../hospitals/hospitals.logic';
import { ALIAS_LANGS, ANESTHESIA_TYPES, AnesthesiaType, TreatmentItem, TreatmentsSchema } from './treatments.logic';

export const NAME_MAX = 200;
export const HASHTAG_MAX = 100;
export const TEXT_MAX = 5000;
export const IMAGE_URL_MAX = 500;
export const ALIAS_MAX = 100;
export const CREATED_BY_MAX = 100;
export const NUMBER_MAX = 1_000_000;
export const REVISIT_MAX = 3650;
export const BULK_MAX = 500;
export const ORDER_MAX = 1000;
export const CONFLICTS_MAX = 500;
export const BULK_ACTIONS = ['activate', 'deactivate'] as const;
export type BulkAction = (typeof BULK_ACTIONS)[number];

export type FieldReason = 'required' | 'too_long' | 'bad_format' | 'range';
export interface FieldError {
  error: 'invalid_field';
  field: string;
  reason: FieldReason;
}
export const fieldError = (field: string, reason: FieldReason): FieldError => ({ error: 'invalid_field', field, reason });
export const isFieldError = (v: unknown): v is FieldError => !!v && typeof v === 'object' && (v as FieldError).error === 'invalid_field';

export const asObject = (body: unknown): Record<string, unknown> | null =>
  body && typeof body === 'object' && !Array.isArray(body) ? (body as Record<string, unknown>) : null;

/** The hashtag the PHP page derives from a name: "#" plus the name without ASCII spaces, cut to the hashtag limit (a derived hashtag is never refused as too long). */
export const hashtagFromName = (name: string): string => {
  let out = '';
  // Whole characters only, and never more than the limit in the same unit the length check uses.
  for (const ch of `#${name.replace(/ /g, '')}`) {
    if (out.length + ch.length > HASHTAG_MAX) break;
    out += ch;
  }
  return out;
};

/** A sent hashtag: blank -> derived from the name; a missing "#" is added. */
export function normalizeHashtag(raw: string, name: string): string {
  const h = raw.trim();
  if (h === '') return hashtagFromName(name);
  return h.startsWith('#') ? h : `#${h}`;
}

/** An image address as the PHP page accepts it: an upload of this site or an absolute http(s) URL. */
export const isImageUrl = (s: string): boolean => {
  if (/^uploads\/treatments\/[A-Za-z0-9._-]+$/.test(s)) return true;
  try {
    const u = new URL(s);
    return (u.protocol === 'http:' || u.protocol === 'https:') && u.host !== '';
  } catch {
    return false;
  }
};

export const text = (v: unknown, field: string, max: number): string | FieldError => {
  if (v === null || v === undefined) return '';
  if (typeof v !== 'string') return fieldError(field, 'bad_format');
  const s = v.trim();
  return s.length > max ? fieldError(field, 'too_long') : s;
};
export const count = (v: unknown, field: string, max: number = NUMBER_MAX): number | FieldError => {
  if (v === null || v === undefined || v === '') return 0;
  if (typeof v !== 'number' || !Number.isInteger(v)) return fieldError(field, 'bad_format');
  return v < 0 || v > max ? fieldError(field, 'range') : v;
};

/** 0, null or blank = no limit; 1..3650 days; anything else is an error. */
export function parseRevisitDays(v: unknown): number | null | FieldError {
  if (v === null || v === undefined || v === '' || v === 0) return null;
  if (typeof v !== 'number' || !Number.isInteger(v)) return fieldError('revisitDays', 'bad_format');
  return v < 1 || v > REVISIT_MAX ? fieldError('revisitDays', 'range') : v;
}

/** Positive distinct integers, at most `max` of them; `self` is dropped. */
export function parseIdList(v: unknown, field: string, max: number, self = 0): number[] | FieldError {
  if (!Array.isArray(v)) return fieldError(field, 'bad_format');
  if (v.length > max) return fieldError(field, 'range');
  const out: number[] = [];
  for (const x of v) {
    if (typeof x !== 'number' || !Number.isInteger(x) || x <= 0) return fieldError(field, 'bad_format');
    if (x !== self && !out.includes(x)) out.push(x);
  }
  return out;
}

/** The checked values of a treatment body; only the keys that were sent are present (a create fills every column). */
export interface TreatmentDraft {
  categoryId?: number;
  name?: string;
  hashtag?: string;
  sortOrder?: number;
  description?: string;
  precautions?: string;
  durationMin?: number;
  recoveryDays?: number;
  repeatCycleMonths?: number;
  avgPriceMin?: number;
  avgPriceMax?: number;
  anesthesiaType?: AnesthesiaType;
  /** '' = no image */
  defaultImageUrl?: string;
  revisitDays?: number | null;
  revisitStrict?: boolean;
  conflictIds?: number[];
}
export type Unavailable = 'default_image_unavailable' | 'revisit_unavailable' | 'conflicts_unavailable';

/**
 * Reads a create or patch body. On create every column gets a value (defaults like the PHP form); on patch only the
 * sent keys are returned, with the current row deciding the hashtag and the price range check.
 */
export function parseTreatmentBody(
  body: unknown,
  mode: 'create' | 'patch',
  schema: TreatmentsSchema,
  current: TreatmentItem | null,
  selfId = 0,
): { draft: TreatmentDraft } | FieldError | { error: 'invalid_body' } | { error: Unavailable } {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  const create = mode === 'create';
  const has = (k: string) => create || k in b;
  const d: TreatmentDraft = {};

  if (has('name')) {
    const n = text(b.name, 'name', NAME_MAX);
    if (isFieldError(n)) return n;
    if (n === '') return fieldError('name', 'required');
    d.name = n;
  }
  if (has('categoryId')) {
    const c = b.categoryId;
    if (c === undefined || c === null) return fieldError('categoryId', 'required');
    if (typeof c !== 'number' || !Number.isInteger(c) || c <= 0) return fieldError('categoryId', 'bad_format');
    d.categoryId = c;
  }
  const nameForHashtag = d.name ?? current?.name ?? '';
  if (has('hashtag')) {
    const h = text(b.hashtag, 'hashtag', HASHTAG_MAX);
    if (isFieldError(h)) return h;
    d.hashtag = normalizeHashtag(h, nameForHashtag);
    if (d.hashtag.length > HASHTAG_MAX) return fieldError('hashtag', 'too_long');
  }
  for (const [key, max] of [['description', TEXT_MAX], ['precautions', TEXT_MAX]] as const) {
    if (!has(key)) continue;
    const t = text(b[key], key, max);
    if (isFieldError(t)) return t;
    d[key] = t;
  }
  for (const key of ['sortOrder', 'durationMin', 'recoveryDays', 'repeatCycleMonths', 'avgPriceMin', 'avgPriceMax'] as const) {
    if (!has(key)) continue;
    const n = count(b[key], key);
    if (isFieldError(n)) return n;
    d[key] = n;
  }
  const min = d.avgPriceMin ?? current?.avgPriceMin ?? 0;
  const max = d.avgPriceMax ?? current?.avgPriceMax ?? 0;
  if ((has('avgPriceMin') || has('avgPriceMax')) && max > 0 && min > max) return fieldError('avgPriceMax', 'range');
  if (has('anesthesiaType')) {
    const a = b.anesthesiaType;
    if (a === undefined || a === null || a === '') d.anesthesiaType = 'none';
    else if (typeof a !== 'string' || !(ANESTHESIA_TYPES as readonly string[]).includes(a)) return fieldError('anesthesiaType', 'bad_format');
    else d.anesthesiaType = a as AnesthesiaType;
  }
  if ('defaultImageUrl' in b) {
    const u = text(b.defaultImageUrl, 'defaultImageUrl', IMAGE_URL_MAX);
    if (isFieldError(u)) return u;
    if (u !== '' && !isImageUrl(u)) return fieldError('defaultImageUrl', 'bad_format');
    if (!schema.defaultImage) {
      if (u !== '') return { error: 'default_image_unavailable' };
    } else d.defaultImageUrl = u;
  } else if (create && schema.defaultImage) d.defaultImageUrl = '';
  if ('revisitDays' in b || 'revisitStrict' in b) {
    const days = parseRevisitDays(b.revisitDays);
    if (isFieldError(days)) return days;
    const strict = b.revisitStrict;
    if (strict !== undefined && strict !== null && typeof strict !== 'boolean') return fieldError('revisitStrict', 'bad_format');
    if (!schema.revisit) {
      if (days !== null) return { error: 'revisit_unavailable' };
    } else {
      d.revisitDays = days;
      d.revisitStrict = days !== null && strict === true;
    }
  } else if (create && schema.revisit) {
    d.revisitDays = null;
    d.revisitStrict = false;
  }
  if ('conflictIds' in b) {
    const ids = parseIdList(b.conflictIds, 'conflictIds', CONFLICTS_MAX, selfId);
    if (isFieldError(ids)) return ids;
    if (!schema.conflicts) return { error: 'conflicts_unavailable' };
    d.conflictIds = ids;
  }
  return { draft: d };
}

/** Column per draft key, in a fixed order (the whitelist of what this API writes). */
const COLUMNS: [keyof TreatmentDraft, string][] = [
  ['categoryId', 'category_id'],
  ['sortOrder', 'sort_order'],
  ['name', 'name'],
  ['hashtag', 'hashtag'],
  ['description', 'description'],
  ['defaultImageUrl', 'default_image_url'],
  ['durationMin', 'duration_min'],
  ['recoveryDays', 'recovery_days'],
  ['anesthesiaType', 'anesthesia_type'],
  ['repeatCycleMonths', 'repeat_cycle_months'],
  ['precautions', 'precautions'],
  ['avgPriceMin', 'avg_price_min'],
  ['avgPriceMax', 'avg_price_max'],
  ['revisitDays', 'revisit_days'],
  ['revisitStrict', 'revisit_strict'],
];
const columnValue = (key: keyof TreatmentDraft, d: TreatmentDraft): unknown => {
  const v = d[key];
  return key === 'revisitStrict' ? (v ? 1 : 0) : v;
};

export function insertQuery(d: TreatmentDraft, createdBy: string): Query {
  const cols = COLUMNS.filter(([k]) => d[k] !== undefined);
  const names = [...cols.map(([, c]) => c), 'is_active', 'created_by'];
  const values = [...cols.map(([k]) => columnValue(k, d)), 1, createdBy];
  return { sql: `INSERT INTO gangnamunni_treatment_master (${names.join(', ')}) VALUES (${names.map(() => '?').join(', ')})`, values };
}

/** null when nothing in the draft is a column (only conflictIds were sent). */
export function updateQuery(id: number, d: TreatmentDraft): Query | null {
  const cols = COLUMNS.filter(([k]) => d[k] !== undefined);
  if (cols.length === 0) return null;
  return {
    sql: `UPDATE gangnamunni_treatment_master SET ${cols.map(([, c]) => `${c} = ?`).join(', ')} WHERE id = ?`,
    values: [...cols.map(([k]) => columnValue(k, d)), id],
  };
}

export const activeQuery = (id: number, active: boolean): Query => ({ sql: 'UPDATE gangnamunni_treatment_master SET is_active = ? WHERE id = ?', values: [active ? 1 : 0, id] });
export const activeManyQuery = (ids: number[], active: boolean): Query => ({ sql: 'UPDATE gangnamunni_treatment_master SET is_active = ? WHERE id IN (?)', values: [active ? 1 : 0, ids] });
export const deleteAliasesQuery = (ids: number[]): Query => ({ sql: 'DELETE FROM gangnamunni_treatment_aliases WHERE treatment_id IN (?)', values: [ids] });
export const deleteConflictsOfQuery = (ids: number[]): Query => ({ sql: 'DELETE FROM gangnamunni_treatment_conflicts WHERE tm_a_id IN (?) OR tm_b_id IN (?)', values: [ids, ids] });
export const deleteTreatmentsQuery = (ids: number[]): Query => ({ sql: 'DELETE FROM gangnamunni_treatment_master WHERE id IN (?)', values: [ids] });
export const sortOrderQuery = (id: number, sortOrder: number): Query => ({ sql: 'UPDATE gangnamunni_treatment_master SET sort_order = ? WHERE id = ?', values: [sortOrder, id] });
export const allActiveIdsQuery = (): Query => ({ sql: 'SELECT id FROM gangnamunni_treatment_master WHERE is_active = 1', values: [] });
export const categoryExistsQuery = (id: number): Query => ({ sql: 'SELECT id FROM gangnamunni_treatment_categories WHERE id = ? LIMIT 1', values: [id] });
export const insertAliasQuery = (treatmentId: number, alias: string, lang: string): Query => ({ sql: 'INSERT INTO gangnamunni_treatment_aliases (treatment_id, alias, lang) VALUES (?, ?, ?)', values: [treatmentId, alias, lang] });
export const deleteAliasQuery = (treatmentId: number, aliasId: number): Query => ({ sql: 'DELETE FROM gangnamunni_treatment_aliases WHERE id = ? AND treatment_id = ?', values: [aliasId, treatmentId] });

/** The conflict rows of one treatment with the active treatments go, then the picked pairs come back (a < b). */
export const replaceConflictsQueries = (id: number, activeIds: number[], picked: number[]): Query[] => {
  const qs: Query[] = [];
  if (activeIds.length > 0) {
    qs.push({ sql: 'DELETE FROM gangnamunni_treatment_conflicts WHERE (tm_a_id = ? AND tm_b_id IN (?)) OR (tm_b_id = ? AND tm_a_id IN (?))', values: [id, activeIds, id, activeIds] });
  }
  for (const p of picked) qs.push({ sql: 'INSERT IGNORE INTO gangnamunni_treatment_conflicts (tm_a_id, tm_b_id) VALUES (?, ?)', values: [Math.min(id, p), Math.max(id, p)] });
  return qs;
};

export function parseActive(body: unknown): boolean | FieldError | { error: 'invalid_body' } {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  if (b.active === undefined) return fieldError('active', 'required');
  return typeof b.active === 'boolean' ? b.active : fieldError('active', 'bad_format');
}

export function parseBulk(body: unknown): { ids: number[]; action: BulkAction } | FieldError | { error: 'invalid_body' } {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  const ids = parseIdList(b.ids, 'ids', BULK_MAX);
  if (isFieldError(ids)) return ids;
  if (ids.length === 0) return fieldError('ids', 'required');
  if (typeof b.action !== 'string' || !(BULK_ACTIONS as readonly string[]).includes(b.action)) return fieldError('action', 'bad_format');
  return { ids, action: b.action as BulkAction };
}

export function parseOrder(body: unknown): number[] | FieldError | { error: 'invalid_body' } {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  const ids = parseIdList(b.ids, 'ids', ORDER_MAX);
  if (isFieldError(ids)) return ids;
  return ids.length === 0 ? fieldError('ids', 'required') : ids;
}

export function parseAlias(body: unknown): { alias: string; lang: string } | FieldError | { error: 'invalid_body' } {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  const a = text(b.alias, 'alias', ALIAS_MAX);
  if (isFieldError(a)) return a;
  if (a === '') return fieldError('alias', 'required');
  const lang = b.lang === undefined || b.lang === null ? 'ko' : b.lang;
  if (typeof lang !== 'string' || !(ALIAS_LANGS as readonly string[]).includes(lang)) return fieldError('lang', 'bad_format');
  return { alias: a, lang };
}

/** The name the row is signed with: the admin's display name, or "admin" like the PHP page when it is blank. */
export const createdByOf = (name: unknown): string => {
  const s = typeof name === 'string' ? name.trim() : '';
  return (s === '' ? 'admin' : s).slice(0, CREATED_BY_MAX);
};

export const defaultImageQuery = (id: number, url: string): Query => ({ sql: 'UPDATE gangnamunni_treatment_master SET default_image_url = ? WHERE id = ?', values: [url, id] });
