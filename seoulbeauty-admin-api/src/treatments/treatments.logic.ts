/** Pure parts of the treatment master page: the SQL statements, reading the rows and the shape of the answer. */
import { num, Query, str } from '../hospitals/hospitals.logic';

export type Row = Record<string, unknown>;

/** Values of the ENUM column gangnamunni_treatment_master.anesthesia_type. */
export const ANESTHESIA_TYPES = ['none', 'local', 'sedation', 'general'] as const;
export type AnesthesiaType = (typeof ANESTHESIA_TYPES)[number];
/** Languages of gangnamunni_treatment_aliases.lang. */
export const ALIAS_LANGS = ['ko', 'en', 'zh', 'ja'] as const;
/** How the home page draws the treatment category table (gangnamunni_settings.tc_table_mode). */
export const TABLE_MODES = ['excel', 'row', 'hybrid'] as const;
export type TableMode = (typeof TABLE_MODES)[number];
export const DEFAULT_TABLE_MODE: TableMode = 'excel';
export const TABLE_MODE_KEY = 'tc_table_mode';

export interface TreatmentAlias {
  id: number;
  alias: string;
  lang: string;
}
export interface TreatmentItem {
  id: number;
  categoryId: number | null;
  categoryName: string;
  sortOrder: number;
  name: string;
  hashtag: string;
  description: string;
  /** null when the column is missing or the value is blank */
  defaultImageUrl: string | null;
  durationMin: number;
  recoveryDays: number;
  anesthesiaType: AnesthesiaType;
  repeatCycleMonths: number;
  /** null = no limit (also when the column is missing) */
  revisitDays: number | null;
  revisitStrict: boolean;
  precautions: string;
  avgPriceMin: number;
  avgPriceMax: number;
  isActive: boolean;
  popularityScore: number;
  createdBy: string;
  createdAt: string | null;
  aliasCount: number;
  hospitalCount: number;
  aliases: TreatmentAlias[];
}
export interface TreatmentCategory {
  id: number;
  name: string;
  slug: string;
  icon: string;
  sortOrder: number;
  isActive: boolean;
  treatmentCount: number;
}
/** One pair that cannot be booked on the same day; a < b as stored. */
export interface TreatmentConflict {
  id: number;
  a: number;
  b: number;
}
/** Which optional columns / tables (PHP migrations) are present and readable. */
export interface TreatmentsSchema {
  revisit: boolean;
  defaultImage: boolean;
  conflicts: boolean;
}
export interface TreatmentsAnswer {
  stats: { total: number; active: number; inactive: number; categories: number };
  schema: TreatmentsSchema;
  tableMode: TableMode;
  categories: TreatmentCategory[];
  items: TreatmentItem[];
  conflicts: TreatmentConflict[];
}

/** Every category (inactive ones too) with the number of its treatments (inactive ones too), in the order of the PHP page. */
export const categoriesQuery = (): Query => ({
  sql: `SELECT c.id, c.name, c.slug, c.icon, c.sort_order, c.is_active,
    (SELECT COUNT(*) FROM gangnamunni_treatment_master t WHERE t.category_id = c.id) AS treatment_count
    FROM gangnamunni_treatment_categories c
    ORDER BY c.sort_order ASC, c.id ASC`,
  values: [],
});

/** Every treatment with its category name and the number of hospitals that have it (any assignment row), in the order of the PHP page. */
export const treatmentsQuery = (): Query => ({
  sql: `SELECT t.id, t.category_id, c.name AS category_name, t.sort_order, t.name, t.hashtag, t.description, t.duration_min, t.recovery_days,
    t.anesthesia_type, t.repeat_cycle_months, t.precautions, t.avg_price_min, t.avg_price_max, t.is_active, t.popularity_score, t.created_by, t.created_at,
    (SELECT COUNT(*) FROM gangnamunni_hospital_treatments ht WHERE ht.treatment_id = t.id) AS hospital_count
    FROM gangnamunni_treatment_master t
    LEFT JOIN gangnamunni_treatment_categories c ON c.id = t.category_id
    ORDER BY c.sort_order ASC, t.sort_order ASC, t.popularity_score DESC, t.id ASC`,
  values: [],
});

export const aliasesQuery = (): Query => ({
  sql: 'SELECT id, treatment_id, alias, lang FROM gangnamunni_treatment_aliases ORDER BY treatment_id ASC, lang ASC, id ASC',
  values: [],
});

/** Optional column (sql/treatment_master_default_image.sql). */
export const defaultImagesQuery = (): Query => ({ sql: 'SELECT id, default_image_url FROM gangnamunni_treatment_master', values: [] });

/** Optional columns (sql/treatment_master_revisit_20261021.sql). */
export const revisitQuery = (): Query => ({ sql: 'SELECT id, revisit_days, revisit_strict FROM gangnamunni_treatment_master', values: [] });

/** Optional table (sql/treatment_conflicts_20261023.sql). */
export const conflictsQuery = (): Query => ({ sql: 'SELECT id, tm_a_id, tm_b_id FROM gangnamunni_treatment_conflicts ORDER BY id ASC', values: [] });

export const tableModeQuery = (): Query => ({ sql: 'SELECT setting_value FROM gangnamunni_settings WHERE setting_key = ?', values: [TABLE_MODE_KEY] });

/** A stored mode, or the default (excel) for anything else, like the home page. */
export function parseTableMode(v: unknown): TableMode {
  const s = str(v).trim();
  return (TABLE_MODES as readonly string[]).includes(s) ? (s as TableMode) : DEFAULT_TABLE_MODE;
}

/** A stored anesthesia type, or none for anything else. */
export function parseAnesthesiaType(v: unknown): AnesthesiaType {
  const s = str(v).trim();
  return (ANESTHESIA_TYPES as readonly string[]).includes(s) ? (s as AnesthesiaType) : 'none';
}

/** Blank or NULL -> null, otherwise the trimmed address as stored. */
export function readImageUrl(v: unknown): string | null {
  const s = str(v).trim();
  return s === '' ? null : s;
}

/** NULL or 0 -> null (no limit), otherwise the number of days. */
export function readRevisitDays(v: unknown): number | null {
  if (v === null || v === undefined) return null;
  const n = num(v);
  return n > 0 ? n : null;
}

export function buildCategories(rows: Row[]): TreatmentCategory[] {
  return rows.map((r) => ({
    id: num(r.id),
    name: str(r.name),
    slug: str(r.slug),
    icon: str(r.icon),
    sortOrder: num(r.sort_order),
    isActive: num(r.is_active) === 1,
    treatmentCount: num(r.treatment_count),
  }));
}

export function buildConflicts(rows: Row[]): TreatmentConflict[] {
  return rows.map((r) => ({ id: num(r.id), a: num(r.tm_a_id), b: num(r.tm_b_id) }));
}

export interface AnswerInput {
  categories: Row[];
  treatments: Row[];
  aliases: Row[];
  /** null when the column is not there (or not readable) */
  defaultImages: Row[] | null;
  /** null when the columns are not there (or not readable) */
  revisit: Row[] | null;
  /** null when the table is not there (or not readable) */
  conflicts: Row[] | null;
  /** the stored setting value, or null when there is none (or it cannot be read) */
  tableMode: unknown;
}

/** Joins the rows of the separate statements into the answer of GET /v1/treatments. */
export function buildAnswer(input: AnswerInput): TreatmentsAnswer {
  const aliasesByTreatment = new Map<number, TreatmentAlias[]>();
  for (const r of input.aliases) {
    const tid = num(r.treatment_id);
    const list = aliasesByTreatment.get(tid) ?? [];
    list.push({ id: num(r.id), alias: str(r.alias), lang: str(r.lang) });
    aliasesByTreatment.set(tid, list);
  }
  const imageById = new Map<number, string | null>();
  for (const r of input.defaultImages ?? []) imageById.set(num(r.id), readImageUrl(r.default_image_url));
  const revisitById = new Map<number, { days: number | null; strict: boolean }>();
  for (const r of input.revisit ?? []) {
    const days = readRevisitDays(r.revisit_days);
    revisitById.set(num(r.id), { days, strict: days !== null && num(r.revisit_strict) === 1 });
  }

  const items: TreatmentItem[] = input.treatments.map((r) => {
    const id = num(r.id);
    const aliases = aliasesByTreatment.get(id) ?? [];
    const rv = revisitById.get(id);
    return {
      id,
      categoryId: r.category_id === null || r.category_id === undefined ? null : num(r.category_id),
      categoryName: str(r.category_name),
      sortOrder: num(r.sort_order),
      name: str(r.name),
      hashtag: str(r.hashtag),
      description: str(r.description),
      defaultImageUrl: imageById.get(id) ?? null,
      durationMin: num(r.duration_min),
      recoveryDays: num(r.recovery_days),
      anesthesiaType: parseAnesthesiaType(r.anesthesia_type),
      repeatCycleMonths: num(r.repeat_cycle_months),
      revisitDays: rv ? rv.days : null,
      revisitStrict: rv ? rv.strict : false,
      precautions: str(r.precautions),
      avgPriceMin: num(r.avg_price_min),
      avgPriceMax: num(r.avg_price_max),
      isActive: num(r.is_active) === 1,
      popularityScore: num(r.popularity_score),
      createdBy: str(r.created_by),
      createdAt: r.created_at === null || r.created_at === undefined || str(r.created_at) === '' ? null : str(r.created_at),
      aliasCount: aliases.length,
      hospitalCount: num(r.hospital_count),
      aliases,
    };
  });
  const categories = buildCategories(input.categories);
  const active = items.filter((i) => i.isActive).length;
  return {
    stats: { total: items.length, active, inactive: items.length - active, categories: categories.filter((c) => c.isActive).length },
    schema: { revisit: input.revisit !== null, defaultImage: input.defaultImages !== null, conflicts: input.conflicts !== null },
    tableMode: parseTableMode(input.tableMode),
    categories,
    items,
    conflicts: buildConflicts(input.conflicts ?? []),
  };
}
