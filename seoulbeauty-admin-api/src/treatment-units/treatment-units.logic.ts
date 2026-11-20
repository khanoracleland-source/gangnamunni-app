/** Pure parts of /v1/treatment-units: the checked unit name, the SQL and the order of the list. */
import { Query } from '../hospitals/hospitals.logic';
import { MAX_UNIT, sortUnits } from '../hospitals/hospitals.treatments.logic';
import { FieldError, asObject, fieldError } from '../treatments/treatments.write.logic';

export interface UnitItem {
  id: number;
  name: string;
}
export interface UnitsAnswer {
  /** false when the units table is missing or not readable: the page then shows how to create it */
  tableReady: boolean;
  items: UnitItem[];
}
export type AddStatus = 'created' | 'restored' | 'exists';
export interface AddAnswer {
  unit: UnitItem;
  status: AddStatus;
}
export interface UnitRecord extends UnitItem {
  isActive: boolean;
}

const TABLE = 'gangnamunni_treatment_units';

export const listQuery = (): Query => ({ sql: `SELECT id, name FROM ${TABLE} WHERE is_active = 1 ORDER BY id ASC`, values: [] });
/** The unique key is case-insensitive (utf8mb4_unicode_ci), so "box" finds "Box"; an inactive row is found too. */
export const findByNameQuery = (name: string): Query => ({ sql: `SELECT id, name, is_active FROM ${TABLE} WHERE name = ? LIMIT 1`, values: [name] });
export const findByIdQuery = (id: number): Query => ({ sql: `SELECT id, name, is_active FROM ${TABLE} WHERE id = ? LIMIT 1`, values: [id] });
export const insertQuery = (name: string): Query => ({ sql: `INSERT INTO ${TABLE} (name, is_active) VALUES (?, 1)`, values: [name] });
export const activateQuery = (id: number): Query => ({ sql: `UPDATE ${TABLE} SET is_active = 1 WHERE id = ?`, values: [id] });
/** Soft delete only: prices that already carry the unit keep their text. */
export const deactivateQuery = (id: number): Query => ({ sql: `UPDATE ${TABLE} SET is_active = 0 WHERE id = ?`, values: [id] });

/** Same rule as the PHP page: trimmed, 1..20 whole characters, no control characters. */
export function parseUnitName(body: unknown): { name: string } | FieldError | { error: 'invalid_body' } {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  if (typeof b.name !== 'string') return fieldError('name', b.name === undefined || b.name === null ? 'required' : 'bad_format');
  const name = b.name.trim();
  if (name === '') return fieldError('name', 'required');
  if (Array.from(name).length > MAX_UNIT) return fieldError('name', 'too_long');
  // eslint-disable-next-line no-control-regex
  if (/[\u0000-\u001f\u007f]/.test(name)) return fieldError('name', 'bad_format');
  return { name };
}

/** Rows (id, name) of the table -> units in the order of the PHP page: Latin and digits first, then Korean. */
export function buildItems(rows: Array<Record<string, unknown>>): UnitItem[] {
  const items: UnitItem[] = [];
  for (const r of rows) {
    const id = Number(r.id);
    const name = typeof r.name === 'string' ? r.name : '';
    if (Number.isInteger(id) && id > 0 && name !== '') items.push({ id, name });
  }
  const byName = new Map<string, UnitItem[]>();
  for (const it of items) byName.set(it.name, [...(byName.get(it.name) ?? []), it]);
  return sortUnits(items.map((i) => i.name)).map((n) => (byName.get(n) as UnitItem[]).shift() as UnitItem);
}

export const toRecord = (r: Record<string, unknown> | undefined): UnitRecord | null => {
  if (!r) return null;
  const id = Number(r.id);
  return Number.isInteger(id) && id > 0 ? { id, name: String(r.name ?? ''), isActive: Number(r.is_active) === 1 } : null;
};
