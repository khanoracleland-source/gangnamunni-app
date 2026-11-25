/** Pure parts of the doctors pages (all doctors of the panel, not of one hospital): list state, SQL and the shape of the answers. */
import { canSee, likeContains, num, positiveInt, Query, Scope, seesNothing, str } from '../hospitals/hospitals.logic';

type Row = Record<string, unknown>;

export const PER_PAGE_CHOICES = [10, 15, 20, 50];
export const DEFAULT_PER_PAGE = 15;
export const MAX_PAGE = 100_000;
export const MAX_SEARCH = 100;
export type DoctorStatus = 'all' | 'active' | 'inactive' | 'resigned';
/** What "inactive" is stored as (the column is an enum); any stored value other than active and resigned (the enum also has 'leave') counts as inactive. */
export const INACTIVE_STORED = 'vacation';
/** The three states the panel knows; every stored value other than active and resigned is an inactive doctor. */
export const statusOf = (stored: unknown): 'active' | 'inactive' | 'resigned' => {
  const v = str(stored);
  return v === 'active' ? 'active' : v === 'resigned' ? 'resigned' : 'inactive';
};

export interface DoctorListQuery {
  /** 0 = every hospital inside the scope. */
  hospitalId: number;
  search: string;
  status: DoctorStatus;
  page: number;
  perPage: number;
}

const one = (v: unknown): string => (typeof v === 'string' ? v : typeof v === 'number' ? String(v) : '');

/** The list state from the query string. Anything missing or invalid becomes the default. */
export function normalizeListQuery(q: Record<string, unknown> | undefined | null): DoctorListQuery {
  const src = q ?? {};
  const status = one(src.status);
  const perPage = positiveInt(src.perPage);
  return {
    hospitalId: positiveInt(src.hospitalId),
    search: one(src.search).trim().slice(0, MAX_SEARCH),
    status: status === 'active' || status === 'inactive' || status === 'resigned' ? status : 'all',
    page: Math.min(MAX_PAGE, Math.max(1, positiveInt(src.page))),
    perPage: PER_PAGE_CHOICES.includes(perPage) ? perPage : DEFAULT_PER_PAGE,
  };
}

/** Doctor id of /v1/doctors/:id, or 0 when it is not a plain positive integer. */
export const parseDoctorId = (raw: unknown): number => (typeof raw === 'string' && /^[1-9]\d{0,9}$/.test(raw) ? positiveInt(raw) : 0);

/** " AND <column> IN (?)" for a limited scope. Callers must rule out seesNothing() first (an empty IN list is invalid SQL). */
function scopeSql(s: Scope, column: string): Query {
  if (s.scope === 'all') return { sql: '', values: [] };
  if (s.hospitalIds.length === 0) throw new Error('empty scope must not reach the database');
  return { sql: ` AND ${column} IN (?)`, values: [s.hospitalIds] };
}

function listWhere(s: Scope, q: DoctorListQuery): Query {
  const scope = scopeSql(s, 'd.hospital_id');
  let sql = `1 = 1${scope.sql}`;
  const values: unknown[] = [...scope.values];
  if (q.hospitalId > 0) {
    sql += ' AND d.hospital_id = ?';
    values.push(q.hospitalId);
  }
  if (q.status === 'inactive') {
    sql += " AND (d.status IS NULL OR d.status NOT IN ('active', 'resigned'))";
  } else if (q.status !== 'all') {
    sql += ' AND d.status = ?';
    values.push(q.status);
  }
  if (q.search) {
    const like = likeContains(q.search);
    sql += ` AND (d.name LIKE ? OR d.specialty LIKE ? OR d.qualification LIKE ? OR h.name LIKE ?
      OR EXISTS (SELECT 1 FROM gangnamunni_doctor_treatments dt
                   JOIN gangnamunni_treatment_master tm ON tm.id = dt.treatment_id
                  WHERE dt.doctor_id = d.id AND dt.is_active = 1 AND tm.name LIKE ?))`;
    values.push(like, like, like, like, like);
  }
  return { sql, values };
}

/** Doctors of one hospital are listed together, in the order the app shows them: placed ones first, then the rest by name. */
export function listQuery(s: Scope, q: DoctorListQuery, withOrder: boolean): Query {
  const w = listWhere(s, q);
  return {
    sql: `SELECT d.id, d.hospital_id, h.name AS hospital_name, d.name, d.specialty, d.qualification, d.photo_url, d.status,
       ${withOrder ? 'd.display_order' : 'NULL AS display_order'}
     FROM gangnamunni_doctors d
     LEFT JOIN gangnamunni_hospitals h ON h.id = d.hospital_id
     WHERE ${w.sql}
     ORDER BY d.hospital_id ASC, ${withOrder ? 'CASE WHEN d.display_order IS NULL THEN 1 ELSE 0 END ASC, d.display_order ASC, ' : ''}d.name COLLATE utf8mb4_unicode_ci ASC, d.id ASC
     LIMIT ?, ?`,
    values: [...w.values, (q.page - 1) * q.perPage, q.perPage],
  };
}

export function countQuery(s: Scope, q: DoctorListQuery): Query {
  const w = listWhere(s, q);
  return {
    sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_doctors d LEFT JOIN gangnamunni_hospitals h ON h.id = d.hospital_id WHERE ${w.sql}`,
    values: w.values,
  };
}

/** Totals inside the scope; not affected by the filters. */
export function statsQuery(s: Scope): Query {
  const scope = scopeSql(s, 'hospital_id');
  return {
    sql: `SELECT COUNT(*) AS total, COUNT(DISTINCT CASE WHEN hospital_id > 0 THEN hospital_id END) AS hospitals, SUM(status = 'active') AS active
            FROM gangnamunni_doctors WHERE 1 = 1${scope.sql}`,
    values: scope.values,
  };
}

/** Names of the treatments each listed doctor is assigned to. */
export const treatmentNamesQuery = (doctorIds: number[]): Query => ({
  sql: `SELECT dt.doctor_id, tm.name, dt.speciality_level
          FROM gangnamunni_doctor_treatments dt
          JOIN gangnamunni_treatment_master tm ON tm.id = dt.treatment_id
         WHERE dt.is_active = 1 AND dt.doctor_id IN (?)
         ORDER BY tm.name`,
  values: [doctorIds],
});

export function detailQuery(s: Scope, id: number, withOrder: boolean): Query {
  const scope = scopeSql(s, 'd.hospital_id');
  return {
    sql: `SELECT d.id, d.hospital_id, h.name AS hospital_name, d.name, d.specialty, d.experience, d.qualification, d.bio, d.photo_url, d.status,
       ${withOrder ? 'd.display_order' : 'NULL AS display_order'}
     FROM gangnamunni_doctors d
     LEFT JOIN gangnamunni_hospitals h ON h.id = d.hospital_id
     WHERE d.id = ?${scope.sql}
     LIMIT 1`,
    values: [id, ...scope.values],
  };
}

/** Treatments the doctor is assigned to at his hospital. */
export const assignedTreatmentsQuery = (doctorId: number, hospitalId: number): Query => ({
  sql: `SELECT tm.id, tm.name, tc.name AS category_name, dt.speciality_level, dt.case_count, dt.certification
          FROM gangnamunni_doctor_treatments dt
          JOIN gangnamunni_treatment_master tm ON tm.id = dt.treatment_id
     LEFT JOIN gangnamunni_treatment_categories tc ON tc.id = tm.category_id
         WHERE dt.doctor_id = ? AND dt.hospital_id = ? AND dt.is_active = 1
         ORDER BY COALESCE(tc.sort_order, 999999), tm.name`,
  values: [doctorId, hospitalId],
});

/** The treatments a hospital actively offers: the only ones a doctor of that hospital may be assigned to. */
export const hospitalTreatmentOptionsQuery = (hospitalId: number): Query => ({
  sql: `SELECT DISTINCT tm.id, tm.name, tc.name AS category_name, COALESCE(tc.sort_order, 999999) AS category_sort
          FROM gangnamunni_hospital_treatments ht
          JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
     LEFT JOIN gangnamunni_treatment_categories tc ON tc.id = tm.category_id
         WHERE ht.hospital_id = ? AND ht.is_active = 1 AND ht.status = 'active'
         ORDER BY category_sort, tm.name`,
  values: [hospitalId],
});

/** Approved hospitals inside the scope: the filter and the form choices. */
export function hospitalOptionsQuery(s: Scope): Query {
  const scope = scopeSql(s, 'id');
  return { sql: `SELECT id, name FROM gangnamunni_hospitals WHERE status = 'approved'${scope.sql} ORDER BY name ASC, id ASC`, values: scope.values };
}

/** Does the doctors table have the display order column yet (the old page adds it the first time it is opened)? */
export const orderColumnQuery = (): Query => ({
  sql: `SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_doctors' AND COLUMN_NAME = 'display_order' LIMIT 1`,
  values: [],
});

export type Level = 'basic' | 'advanced' | 'expert';
export interface LevelCounts {
  basic: number;
  advanced: number;
  expert: number;
}
/** A stored level the panel does not know counts as basic. */
export const levelOf = (stored: unknown): Level => {
  const v = str(stored);
  return v === 'expert' || v === 'advanced' ? v : 'basic';
};

export interface DoctorListItem {
  id: number;
  name: string;
  hospitalId: number;
  hospitalName: string;
  specialty: string;
  qualification: string;
  photoUrl: string | null;
  status: 'active' | 'inactive' | 'resigned';
  displayOrder: number | null;
  treatments: string[];
  /** How many of the listed treatments are at each level. */
  levelCounts: LevelCounts;
}

const placeOrNull = (v: unknown): number | null => (v === null || v === undefined || num(v) < 1 ? null : Math.trunc(num(v)));
const photoOrNull = (v: unknown): string | null => (str(v).trim() === '' ? null : str(v));

export const toListItem = (r: Row, treatments: string[] = [], levelCounts: LevelCounts = { basic: 0, advanced: 0, expert: 0 }): DoctorListItem => ({
  id: num(r.id),
  name: str(r.name),
  hospitalId: num(r.hospital_id),
  hospitalName: str(r.hospital_name),
  specialty: str(r.specialty).trim(),
  qualification: str(r.qualification),
  photoUrl: photoOrNull(r.photo_url),
  status: statusOf(r.status),
  displayOrder: placeOrNull(r.display_order),
  treatments,
  levelCounts,
});

export interface DoctorList {
  scope: Scope['scope'];
  stats: { total: number; hospitals: number; active: number };
  total: number;
  page: number;
  perPage: number;
  items: DoctorListItem[];
}

export const emptyList = (s: Scope, q: DoctorListQuery): DoctorList => ({
  scope: s.scope, stats: { total: 0, hospitals: 0, active: 0 }, total: 0, page: q.page, perPage: q.perPage, items: [],
});

export function buildList(s: Scope, q: DoctorListQuery, stats: Row | undefined, total: number, rows: Row[], names: Row[]): DoctorList {
  const byDoctor = new Map<number, string[]>();
  const levels = new Map<number, LevelCounts>();
  for (const n of names) {
    const id = num(n.doctor_id);
    byDoctor.set(id, [...(byDoctor.get(id) ?? []), str(n.name)]);
    const counts = levels.get(id) ?? { basic: 0, advanced: 0, expert: 0 };
    counts[levelOf(n.speciality_level)] += 1;
    levels.set(id, counts);
  }
  return {
    scope: s.scope,
    stats: { total: num(stats?.total), hospitals: num(stats?.hospitals), active: num(stats?.active) },
    total,
    page: q.page,
    perPage: q.perPage,
    items: rows.map((r) => toListItem(r, byDoctor.get(num(r.id)) ?? [], levels.get(num(r.id)))),
  };
}

export interface DoctorDetail extends Omit<DoctorListItem, 'treatments' | 'levelCounts'> {
  experience: number;
  bio: string;
  treatments: { id: number; name: string; category: string; level: Level; caseCount: number; certification: string }[];
}

export function buildDetail(r: Row, assigned: Row[]): DoctorDetail {
  const base = toListItem(r);
  return {
    id: base.id,
    name: base.name,
    hospitalId: base.hospitalId,
    hospitalName: base.hospitalName,
    specialty: base.specialty,
    qualification: base.qualification,
    photoUrl: base.photoUrl,
    status: base.status,
    displayOrder: base.displayOrder,
    experience: Math.max(0, Math.trunc(num(r.experience))),
    bio: str(r.bio),
    treatments: assigned.map((a) => ({
      id: num(a.id),
      name: str(a.name),
      category: str(a.category_name),
      level: levelOf(a.speciality_level),
      caseCount: Math.max(0, Math.trunc(num(a.case_count))),
      certification: str(a.certification),
    })),
  };
}

export const toTreatmentOption = (r: Row) => ({ id: num(r.id), name: str(r.name), category: str(r.category_name) });
export const toHospitalOption = (r: Row) => ({ id: num(r.id), name: str(r.name) });

export { canSee, seesNothing };
