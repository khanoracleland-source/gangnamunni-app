import { Query } from '../hospitals/hospitals.logic';
import { TABLE_MODES, TABLE_MODE_KEY, TableMode } from './treatments.logic';
import { asObject, count, FieldError, fieldError, isFieldError, parseIdList, text } from './treatments.write.logic';

export const CATEGORY_NAME_MAX = 100;
export const CATEGORY_ICON_MAX = 100;
export const CATEGORY_SLUG_MAX = 100;
export const CATEGORY_ORDER_MAX = 1000;
const SORT_MAX = 1_000_000;

/** The slug the PHP page derives from a name (it would keep a lone "_"; that is no slug here): lower case, spaces to "_", anything outside a-z 0-9 "_" dropped. */
export const slugFromName = (name: string): string => {
  const slug = name.toLowerCase().replace(/ /g, '_').replace(/[^a-z0-9_]/g, '').slice(0, CATEGORY_SLUG_MAX);
  // Only underscores left (a Korean name with spaces) is no slug either.
  return /[a-z0-9]/.test(slug) ? slug : '';
};

/** A name with no ASCII letters has no slug of its own: the PHP page then uses "cat_" plus the time. */
export const fallbackSlug = (nowMs: number): string => `cat_${Math.floor(nowMs / 1000)}`;

export interface CategoryDraft {
  name?: string;
  icon?: string;
  sortOrder?: number;
}

/** Create: the name is required. Patch: only the sent keys. */
export function parseCategoryBody(body: unknown, mode: 'create' | 'patch'): { draft: CategoryDraft } | FieldError | { error: 'invalid_body' } {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  const create = mode === 'create';
  const d: CategoryDraft = {};
  if (create || 'name' in b) {
    const n = text(b.name, 'name', CATEGORY_NAME_MAX);
    if (isFieldError(n)) return n;
    if (n === '') return fieldError('name', 'required');
    d.name = n;
  }
  if (create || 'icon' in b) {
    const i = text(b.icon, 'icon', CATEGORY_ICON_MAX);
    if (isFieldError(i)) return i;
    // eslint-disable-next-line no-control-regex
    if (/[\u0000-\u001f<>"'`]/.test(i)) return fieldError('icon', 'bad_format');
    d.icon = i;
  }
  if (create || 'sortOrder' in b) {
    const s = count(b.sortOrder, 'sortOrder', SORT_MAX);
    if (isFieldError(s)) return s;
    d.sortOrder = s;
  }
  return { draft: d };
}

export const parseCategoryOrder = (body: unknown): number[] | FieldError | { error: 'invalid_body' } => {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  const ids = parseIdList(b.ids, 'ids', CATEGORY_ORDER_MAX);
  if (isFieldError(ids)) return ids;
  return ids.length === 0 ? fieldError('ids', 'required') : ids;
};

export function parseTableModeBody(body: unknown): TableMode | FieldError | { error: 'invalid_body' } {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  if (b.mode === undefined || b.mode === null) return fieldError('mode', 'required');
  return typeof b.mode === 'string' && (TABLE_MODES as readonly string[]).includes(b.mode) ? (b.mode as TableMode) : fieldError('mode', 'bad_format');
}

export function parsePair(body: unknown): { a: number; b: number } | FieldError | { error: 'invalid_body' } {
  const o = asObject(body);
  if (!o) return { error: 'invalid_body' };
  for (const k of ['a', 'b'] as const) {
    const v = o[k];
    if (v === undefined || v === null) return fieldError(k, 'required');
    if (typeof v !== 'number' || !Number.isInteger(v) || v <= 0) return fieldError(k, 'bad_format');
  }
  if (o.a === o.b) return fieldError('b', 'bad_format');
  return { a: o.a as number, b: o.b as number };
}

// ───────── statements (parameterised, fixed columns) ─────────
export const insertCategoryQuery = (name: string, slug: string, icon: string, sortOrder: number): Query => ({
  sql: 'INSERT INTO gangnamunni_treatment_categories (name, slug, icon, sort_order, is_active) VALUES (?, ?, ?, ?, 1)',
  values: [name, slug, icon, sortOrder],
});
const CATEGORY_COLUMNS: [keyof CategoryDraft | 'slug', string][] = [['name', 'name'], ['slug', 'slug'], ['icon', 'icon'], ['sortOrder', 'sort_order']];
export function updateCategoryQuery(id: number, d: CategoryDraft & { slug?: string }): Query | null {
  const sets: string[] = [];
  const values: unknown[] = [];
  for (const [key, col] of CATEGORY_COLUMNS) {
    const v = (d as Record<string, unknown>)[key];
    if (v === undefined) continue;
    sets.push(`${col} = ?`);
    values.push(v);
  }
  if (sets.length === 0) return null;
  return { sql: `UPDATE gangnamunni_treatment_categories SET ${sets.join(', ')} WHERE id = ?`, values: [...values, id] };
}
export const categoryActiveQuery = (id: number, active: boolean): Query => ({ sql: 'UPDATE gangnamunni_treatment_categories SET is_active = ? WHERE id = ?', values: [active ? 1 : 0, id] });
export const categorySortQuery = (id: number, sortOrder: number): Query => ({ sql: 'UPDATE gangnamunni_treatment_categories SET sort_order = ? WHERE id = ?', values: [sortOrder, id] });
/** Deletes only a category that no treatment points at, in one statement (no gap between the check and the delete). */
export const deleteEmptyCategoryQuery = (id: number): Query => ({
  sql: 'DELETE FROM gangnamunni_treatment_categories WHERE id = ? AND NOT EXISTS (SELECT 1 FROM gangnamunni_treatment_master WHERE category_id = ?)',
  values: [id, id],
});
export const settingExistsQuery = (): Query => ({ sql: 'SELECT id FROM gangnamunni_settings WHERE setting_key = ? LIMIT 1', values: [TABLE_MODE_KEY] });
export const updateSettingQuery = (mode: TableMode): Query => ({ sql: 'UPDATE gangnamunni_settings SET setting_value = ? WHERE setting_key = ?', values: [mode, TABLE_MODE_KEY] });
export const insertSettingQuery = (mode: TableMode): Query => ({
  sql: 'INSERT INTO gangnamunni_settings (setting_key, setting_value, setting_group, description) VALUES (?, ?, ?, ?)',
  values: [TABLE_MODE_KEY, mode, 'home', '홈 시술 카테고리 테이블 표시 모드'],
});
export const insertConflictQuery = (a: number, b: number): Query => ({
  sql: 'INSERT IGNORE INTO gangnamunni_treatment_conflicts (tm_a_id, tm_b_id) VALUES (?, ?)',
  values: [Math.min(a, b), Math.max(a, b)],
});
export const deleteConflictQuery = (id: number): Query => ({ sql: 'DELETE FROM gangnamunni_treatment_conflicts WHERE id = ?', values: [id] });
