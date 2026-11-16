/** Pure parts of /v1/hospitals: query normalisation, the SELECT statements and the mapping of their rows to the response. */

export interface Query {
  sql: string;
  values: unknown[];
}
/** Who is asking: every hospital, or only the listed ones (an empty list sees nothing). */
export interface Scope {
  scope: 'all' | 'own';
  hospitalIds: number[];
}
type Row = Record<string, unknown>;

export const PER_PAGE_CHOICES = [10, 20, 50];
export const DEFAULT_PER_PAGE = 20;
export const MAX_SEARCH_LENGTH = 100;
/** Keeps LIMIT offsets far inside the integer range whatever the client sends. */
export const MAX_PAGE = 100_000;
/** Category shown for a treatment without one (same label as the PHP admin page). */
export const NO_CATEGORY_LABEL = '기타';
export const AD_GRADES = ['S', 'A', 'B', 'C', 'D', 'E'];
/** Upper bound of the unreviewed changes returned for one hospital. */
export const MAX_REVISIONS = 200;

export interface ListQuery {
  search: string;
  treatmentId: number;
  active: 'all' | '1' | '0';
  page: number;
  perPage: number;
}

const one = (v: unknown): string => (typeof v === 'string' ? v : typeof v === 'number' ? String(v) : '');
/** A plain positive decimal integer ("12"), else 0. No signs, decimals, exponents or hex. */
export function positiveInt(v: unknown): number {
  const s = one(v).trim();
  if (!/^\d{1,10}$/.test(s)) return 0;
  const n = Number(s);
  return n > 0 && n <= 2_147_483_647 ? n : 0;
}

/** Query string of the list -> safe values. Anything unexpected falls back to the default instead of failing. */
export function normalizeListQuery(q: Record<string, unknown> | undefined | null): ListQuery {
  const src = q ?? {};
  const search = Array.from(one(src.search).trim()).slice(0, MAX_SEARCH_LENGTH).join('').trim();
  const active = one(src.active);
  const perPage = positiveInt(src.perPage);
  return {
    search,
    treatmentId: positiveInt(src.treatmentId),
    active: active === '1' || active === '0' ? active : 'all',
    page: Math.min(MAX_PAGE, Math.max(1, positiveInt(src.page))),
    perPage: PER_PAGE_CHOICES.includes(perPage) ? perPage : DEFAULT_PER_PAGE,
  };
}

/** Hospital id of /v1/hospitals/:id, or 0 when it is not a plain positive integer. */
export const parseHospitalId = (raw: unknown): number => (typeof raw === 'string' && /^[1-9]\d{0,9}$/.test(raw) ? positiveInt(raw) : 0);

/** True when the scope can never match a row: the database is not asked at all. */
export const seesNothing = (s: Scope): boolean => s.scope !== 'all' && s.hospitalIds.length === 0;
export const canSee = (s: Scope, hospitalId: number): boolean => s.scope === 'all' || s.hospitalIds.includes(hospitalId);

/** " AND <column> IN (?)" for a limited scope. Callers must rule out seesNothing() first (an empty IN list is invalid SQL). */
function scopeSql(s: Scope, column: string): Query {
  if (s.scope === 'all') return { sql: '', values: [] };
  if (s.hospitalIds.length === 0) throw new Error('empty scope must not reach the database');
  return { sql: ` AND ${column} IN (?)`, values: [s.hospitalIds] };
}

/** LIKE pattern that matches the text literally (%, _ and \ typed by the admin are not wildcards). */
export const likeContains = (text: string): string => `%${text.replace(/[\\%_]/g, (c) => `\\${c}`)}%`;

/** This page only deals with approved hospitals; pending / rejected applications are reviewed elsewhere. */
const APPROVED = `h.status = 'approved'`;

function listWhere(s: Scope, q: ListQuery): Query {
  const scope = scopeSql(s, 'h.id');
  let sql = `${APPROVED}${scope.sql}`;
  const values = [...scope.values];
  if (q.search) {
    sql += ' AND (h.name LIKE ? OR h.address LIKE ? OR h.phone LIKE ? OR h.email LIKE ? OR h.categories LIKE ?)';
    const p = likeContains(q.search);
    values.push(p, p, p, p, p);
  }
  // Actual active assignments, not the free-text treatment tags. EXISTS keeps one row per hospital.
  if (q.treatmentId > 0) {
    sql += ` AND EXISTS (SELECT 1 FROM gangnamunni_hospital_treatments ht
       WHERE ht.hospital_id = h.id AND ht.treatment_id = ? AND ht.is_active = 1 AND ht.status = 'active')`;
    values.push(q.treatmentId);
  }
  if (q.active === '1') sql += ' AND h.is_active = 1';
  if (q.active === '0') sql += ' AND COALESCE(h.is_active, 0) = 0';
  return { sql, values };
}

/** Counters above the list: approved hospitals inside the scope, ignoring the list filters. */
export function statsQuery(s: Scope): Query {
  const scope = scopeSql(s, 'h.id');
  return {
    sql: `SELECT COUNT(*) AS total,
       COALESCE(SUM(h.is_active = 1), 0) AS active,
       COALESCE(SUM(COALESCE(h.is_active, 0) = 0), 0) AS inactive,
       COALESCE(SUM(h.has_unreviewed_changes = 1), 0) AS unreviewed
     FROM gangnamunni_hospitals h
     WHERE ${APPROVED}${scope.sql}`,
    values: scope.values,
  };
}

export function countQuery(s: Scope, q: ListQuery): Query {
  const w = listWhere(s, q);
  return { sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_hospitals h WHERE ${w.sql}`, values: w.values };
}

export function listQuery(s: Scope, q: ListQuery): Query {
  const w = listWhere(s, q);
  return {
    sql: `SELECT h.id, h.name, h.address, h.phone, h.categories, h.ad_grade, h.is_active, h.rating, h.review_count,
       h.show_rating, h.has_unreviewed_changes, h.created_at
     FROM gangnamunni_hospitals h
     WHERE ${w.sql}
     ORDER BY h.created_at DESC, h.id DESC
     LIMIT ?, ?`,
    values: [...w.values, (q.page - 1) * q.perPage, q.perPage],
  };
}

/** Treatments that at least one approved hospital inside the scope actively offers, in category order. */
export function treatmentOptionsQuery(s: Scope): Query {
  const scope = scopeSql(s, 'ht.hospital_id');
  return {
    sql: `SELECT tm.id, tm.name, tc.name AS category_name
     FROM gangnamunni_treatment_master tm
     LEFT JOIN gangnamunni_treatment_categories tc ON tc.id = tm.category_id
     WHERE EXISTS (SELECT 1 FROM gangnamunni_hospital_treatments ht
         JOIN gangnamunni_hospitals h ON h.id = ht.hospital_id
         WHERE ht.treatment_id = tm.id AND ht.is_active = 1 AND ht.status = 'active' AND ${APPROVED}${scope.sql})
     ORDER BY tc.sort_order, tc.id, tm.name, tm.id`,
    values: scope.values,
  };
}

/**
 * One approved hospital inside the scope. The two certificate columns are only tested for presence in SQL: the stored
 * file paths never leave the database. Columns holding keys or other secrets are not selected at all.
 */
export function detailQuery(s: Scope, id: number): Query {
  const scope = scopeSql(s, 'h.id');
  return {
    sql: `SELECT h.id, h.name, h.owner_name, h.business_number, h.introduction, h.address, h.phone, h.email,
       h.contact_name, h.contact_phone, h.website, h.sns_url,
       h.categories, h.badges, h.treatment_tags, h.subway_names, h.business_hours, h.latitude, h.longitude,
       h.status, h.is_active, h.ad_grade, h.rating, h.review_count, h.show_rating, h.has_unreviewed_changes,
       h.main_image_url, h.profile_image_url, h.applied_by, h.applied_at, h.created_at, h.updated_at,
       (h.cert_business IS NOT NULL AND h.cert_business <> '') AS has_cert_business,
       (h.cert_medical IS NOT NULL AND h.cert_medical <> '') AS has_cert_medical
     FROM gangnamunni_hospitals h
     WHERE h.id = ? AND ${APPROVED}${scope.sql}
     LIMIT 1`,
    values: [id, ...scope.values],
  };
}

/** Columns added by optional migrations, read separately so a database without them still serves the page. */
export function detailOptionalQuery(id: number): Query {
  return { sql: `SELECT h.kakao_channel_url FROM gangnamunni_hospitals h WHERE h.id = ? LIMIT 1`, values: [id] };
}

export function imagesQuery(id: number): Query {
  return {
    sql: `SELECT id, image_url, image_type, sort_order, is_active FROM hospital_images WHERE hospital_id = ? ORDER BY sort_order ASC, id ASC`,
    values: [id],
  };
}

export function doctorsQuery(id: number): Query {
  return {
    sql: `SELECT id, name, specialty, experience, qualification, bio, photo_url, status FROM gangnamunni_doctors WHERE hospital_id = ? ORDER BY id ASC`,
    values: [id],
  };
}

/** Changes made by the applicant that no admin has confirmed yet, newest first. */
export function revisionsQuery(id: number): Query {
  return {
    sql: `SELECT id, field_name, old_value, new_value, changed_at FROM gangnamunni_hospital_revisions
     WHERE hospital_id = ? AND reviewed = 0 ORDER BY changed_at DESC, id DESC LIMIT ?`,
    values: [id, MAX_REVISIONS],
  };
}

// ---- rows -> response ----

export const num = (v: unknown): number => {
  const x = Number(v);
  return Number.isFinite(x) ? x : 0;
};
const str = (v: unknown): string => (v === null || v === undefined ? '' : String(v));
const flag = (v: unknown): boolean => num(v) === 1;
/** A flag that counts as on when the column is NULL (show_rating, hospital_images.is_active). */
const flagDefaultOn = (v: unknown): boolean => (v === null || v === undefined ? true : num(v) === 1);
const numOrNull = (v: unknown): number | null => {
  if (v === null || v === undefined || v === '') return null;
  const x = Number(v);
  return Number.isFinite(x) ? x : null;
};
/** "YYYY-MM-DD HH:MM:SS" as stored, or null for NULL / zero dates. */
export const dateOrNull = (v: unknown): string | null => {
  const s = str(v).trim();
  return !s || s.startsWith('0000-00-00') ? null : s;
};
export const adGrade = (v: unknown): string => (AD_GRADES.includes(str(v)) ? str(v) : 'none');

/** A JSON column arrives as text (MariaDB) or already decoded (MySQL). Text that is not JSON is returned as it is. */
function decoded(v: unknown): unknown {
  if (typeof v !== 'string') return v;
  const s = v.trim();
  if (!s) return null;
  try {
    return JSON.parse(s);
  } catch {
    return s;
  }
}

/** Tag-like column -> string[]: a JSON array, or legacy comma separated text. Empty and non-text entries are dropped. */
export function parseStringList(v: unknown): string[] {
  const d = decoded(v);
  const parts: unknown[] = Array.isArray(d) ? d : typeof d === 'string' ? d.split(',') : [];
  return parts
    .filter((x) => typeof x === 'string' || typeof x === 'number')
    .map((x) => String(x).trim())
    .filter((x) => x !== '');
}

/** business_hours -> plain object; anything else (NULL, text, array, broken JSON) is {}. */
export function parseObject(v: unknown): Record<string, unknown> {
  const d = decoded(v);
  return d && typeof d === 'object' && !Array.isArray(d) ? (d as Record<string, unknown>) : {};
}

export interface HospitalStats {
  total: number;
  active: number;
  inactive: number;
  unreviewed: number;
}
export const buildStats = (r: Row | null | undefined): HospitalStats => ({
  total: num(r?.total),
  active: num(r?.active),
  inactive: num(r?.inactive),
  unreviewed: num(r?.unreviewed),
});

export function toListItem(r: Row) {
  return {
    id: num(r.id),
    name: str(r.name),
    address: str(r.address),
    phone: str(r.phone),
    categories: parseStringList(r.categories),
    adGrade: adGrade(r.ad_grade),
    isActive: flag(r.is_active),
    rating: num(r.rating),
    reviewCount: num(r.review_count),
    showRating: flagDefaultOn(r.show_rating),
    hasUnreviewedChanges: flag(r.has_unreviewed_changes),
    createdAt: dateOrNull(r.created_at),
  };
}
export type HospitalListItem = ReturnType<typeof toListItem>;

export interface HospitalList {
  scope: 'all' | 'own';
  stats: HospitalStats;
  total: number;
  page: number;
  perPage: number;
  items: HospitalListItem[];
}
export const emptyList = (s: Scope, q: ListQuery): HospitalList => ({
  scope: s.scope, stats: buildStats(null), total: 0, page: q.page, perPage: q.perPage, items: [],
});

export interface TreatmentGroup {
  category: string;
  items: { id: number; name: string }[];
}
/** Rows ordered by category -> groups in that order. */
export function groupTreatments(rows: Row[]): TreatmentGroup[] {
  const groups = new Map<string, TreatmentGroup>();
  for (const r of rows) {
    const category = str(r.category_name).trim() || NO_CATEGORY_LABEL;
    let g = groups.get(category);
    if (!g) groups.set(category, (g = { category, items: [] }));
    g.items.push({ id: num(r.id), name: str(r.name) });
  }
  return [...groups.values()];
}

export interface DetailRows {
  hospital: Row;
  optional?: Row | null;
  images: Row[];
  doctors: Row[];
  revisions: Row[];
}

export function buildDetail(d: DetailRows) {
  const h = d.hospital;
  return {
    hospital: {
      id: num(h.id),
      name: str(h.name),
      ownerName: str(h.owner_name),
      businessNumber: str(h.business_number),
      introduction: str(h.introduction),
      address: str(h.address),
      phone: str(h.phone),
      email: str(h.email),
      contactName: str(h.contact_name),
      contactPhone: str(h.contact_phone),
      website: str(h.website),
      snsUrl: str(h.sns_url),
      kakaoChannelUrl: str(d.optional?.kakao_channel_url),
      categories: parseStringList(h.categories),
      badges: parseStringList(h.badges),
      treatmentTags: parseStringList(h.treatment_tags),
      subwayNames: parseStringList(h.subway_names),
      businessHours: parseObject(h.business_hours),
      latitude: numOrNull(h.latitude),
      longitude: numOrNull(h.longitude),
      status: str(h.status),
      isActive: flag(h.is_active),
      adGrade: adGrade(h.ad_grade),
      rating: num(h.rating),
      reviewCount: num(h.review_count),
      showRating: flagDefaultOn(h.show_rating),
      hasUnreviewedChanges: flag(h.has_unreviewed_changes),
      mainImageUrl: str(h.main_image_url),
      profileImageUrl: str(h.profile_image_url),
      appliedBy: str(h.applied_by),
      appliedAt: dateOrNull(h.applied_at),
      createdAt: dateOrNull(h.created_at),
      updatedAt: dateOrNull(h.updated_at),
    },
    // Presence only: the files are opened through the PHP admin, which checks the right to see them.
    documents: { business: flag(h.has_cert_business), medical: flag(h.has_cert_medical) },
    images: d.images.map((r) => ({
      id: num(r.id), url: str(r.image_url), type: str(r.image_type), sortOrder: num(r.sort_order), isActive: flagDefaultOn(r.is_active),
    })),
    doctors: d.doctors.map((r) => ({
      id: num(r.id), name: str(r.name), specialty: str(r.specialty), experience: Math.max(0, Math.trunc(num(r.experience))), qualification: str(r.qualification),
      bio: str(r.bio), photoUrl: str(r.photo_url), status: str(r.status),
    })),
    revisions: d.revisions.map((r) => ({
      id: num(r.id), field: str(r.field_name), oldValue: str(r.old_value), newValue: str(r.new_value), changedAt: dateOrNull(r.changed_at),
    })),
  };
}
export type HospitalDetail = ReturnType<typeof buildDetail>;
