/** Pure parts of the hospital sales card, the delete check, the delete and its archive record. */
import {
  BOOKING_CANCELLED_STATUSES, num, PAYMENT_PAID_STATUSES, PAYMENT_TEST_PROVIDERS,
} from '../dashboard/dashboard.logic';
import type { Query } from './hospitals.logic';

export { num };

/** gangnamunni_bookings.status values a hospital still has to act on (the patient's "upcoming" tab, see the patient API). */
export const BOOKING_OPEN_STATUSES = ['pending', 'hospital_checking', 'confirmed'];
/** gangnamunni_consultations.status values that are still alive: waiting, accepted, or failed contact that the patient can re-request. */
export const CONSULTATION_OPEN_STATUSES = ['pending', 'approved', 'contact_failed'];
/** gangnamunni_hospital_settlements.status: the only final one; draft, scheduled, processing, held and failed are all unresolved money. */
export const SETTLEMENT_FINAL_STATUSES = ['paid'];

export interface Money {
  paid: number;
  refunded: number;
  net: number;
}
export interface PeriodStats {
  from: string;
  to: string;
  bookings: { created: number; cancelled: number; active: number };
  sales: Money;
}
export interface HospitalSalesStats {
  week: PeriodStats;
  month: PeriodStats;
}

/** Same rows as the dashboard / PHP revenue page: real booking payments to the hospital where money was received. */
const REAL_PAYMENT_WHERE = `payment_type = 'booking' AND funds_recipient = 'hospital' AND provider NOT IN (?) AND status IN (?)`;
const realPaymentValues = (): unknown[] => [PAYMENT_TEST_PROVIDERS, PAYMENT_PAID_STATUSES];

/** Monday of this week / first day of this month, in the connection time zone (KST). */
export const WEEK_START_SQL = 'DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)';
export const MONTH_START_SQL = `DATE_FORMAT(CURDATE(), '%Y-%m-01')`;

/** Bookings created in [start of period, now] for one hospital. */
export function periodBookingsQuery(hospitalId: number, kind: 'week' | 'month'): Query {
  const start = kind === 'week' ? WEEK_START_SQL : MONTH_START_SQL;
  return {
    sql: `SELECT DATE_FORMAT(${start}, '%Y-%m-%d') AS from_date, DATE_FORMAT(CURDATE(), '%Y-%m-%d') AS to_date,
       COUNT(*) AS created, COALESCE(SUM(status IN (?)), 0) AS cancelled
     FROM gangnamunni_bookings
     WHERE hospital_id = ? AND created_at >= ${start} AND created_at <= NOW()`,
    values: [BOOKING_CANCELLED_STATUSES, hospitalId],
  };
}

/** Booking payments of one hospital by paid_at in [start of period, now]. */
export function periodSalesQuery(hospitalId: number, kind: 'week' | 'month'): Query {
  const start = kind === 'week' ? WEEK_START_SQL : MONTH_START_SQL;
  return {
    sql: `SELECT COALESCE(SUM(amount), 0) AS paid, COALESCE(SUM(refunded_amount), 0) AS refunded
     FROM gangnamunni_platform_payments
     WHERE hospital_id = ? AND ${REAL_PAYMENT_WHERE} AND paid_at >= ${start} AND paid_at <= NOW()`,
    values: [hospitalId, ...realPaymentValues()],
  };
}

type Row = Record<string, unknown> | undefined | null;

export const money = (r: Row): Money => {
  const paid = num(r?.paid);
  const refunded = num(r?.refunded);
  return { paid, refunded, net: paid - refunded };
};

export function buildPeriod(b: Row, p: Row): PeriodStats {
  const created = num(b?.created);
  const cancelled = Math.min(num(b?.cancelled), created);
  return { from: String(b?.from_date ?? ''), to: String(b?.to_date ?? ''), bookings: { created, cancelled, active: created - cancelled }, sales: money(p) };
}

/** Whole-life figures that are sealed into the archive. */
export function totalBookingsQuery(hospitalId: number): Query {
  return {
    sql: `SELECT COUNT(*) AS total, COALESCE(SUM(status IN (?)), 0) AS cancelled FROM gangnamunni_bookings WHERE hospital_id = ?`,
    values: [BOOKING_CANCELLED_STATUSES, hospitalId],
  };
}
export function totalSalesQuery(hospitalId: number): Query {
  return {
    sql: `SELECT COALESCE(SUM(amount), 0) AS paid, COALESCE(SUM(refunded_amount), 0) AS refunded
     FROM gangnamunni_platform_payments WHERE hospital_id = ? AND ${REAL_PAYMENT_WHERE}`,
    values: [hospitalId, ...realPaymentValues()],
  };
}

// ---- blockers ----

/** Bookings that are not finished (any time: a past one that nobody closed is still unresolved). lastAt = the latest booking time. */
export function openBookingsQuery(hospitalId: number): Query {
  return {
    sql: `SELECT COUNT(*) AS cnt, MAX(TIMESTAMP(booking_date, COALESCE(booking_time, '00:00:00'))) AS last_at
     FROM gangnamunni_bookings WHERE hospital_id = ? AND status IN (?)`,
    values: [hospitalId, BOOKING_OPEN_STATUSES],
  };
}
export function openConsultationsQuery(hospitalId: number): Query {
  return { sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_consultations WHERE hospital_id = ? AND status IN (?)`, values: [hospitalId, CONSULTATION_OPEN_STATUSES] };
}
export function settlementsSummaryQuery(hospitalId: number): Query {
  return { sql: `SELECT COALESCE(SUM(status NOT IN (?)), 0) AS open_cnt FROM gangnamunni_hospital_settlements WHERE hospital_id = ?`, values: [SETTLEMENT_FINAL_STATUSES, hospitalId] };
}
/** The newest settlement: its end of period and its balance (same formula as the monthly job). */
export function lastSettlementQuery(hospitalId: number): Query {
  return {
    sql: `SELECT status, DATE_FORMAT(period_end, '%Y-%m-%d') AS period_end,
       CAST(treatment_gross AS SIGNED) - CAST(refunds AS SIGNED) - CAST(platform_fee AS SIGNED) + adjustments + carry_forward_amount AS balance
     FROM gangnamunni_hospital_settlements WHERE hospital_id = ? ORDER BY period_end DESC, id DESC LIMIT 1`,
    values: [hospitalId],
  };
}
/**
 * Real hospital payments that no settlement period covers (hospital_id, period_start <= paid_at < period_end + 1 day).
 * The monthly job only settles payments inside the period of a settlement row it created, so this also catches payments
 * made before the first settlement, in a month that never got one, and after the newest one. Settlements that exist but are
 * not final are counted separately.
 */
export function unsettledPaymentsQuery(hospitalId: number): Query {
  return {
    sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_platform_payments p
     WHERE p.hospital_id = ? AND ${REAL_PAYMENT_WHERE.replace(/\b(payment_type|funds_recipient|provider|status)\b/g, 'p.$1')}
       AND NOT EXISTS (SELECT 1 FROM gangnamunni_hospital_settlements s
         WHERE s.hospital_id = p.hospital_id AND p.paid_at >= s.period_start AND p.paid_at < DATE_ADD(s.period_end, INTERVAL 1 DAY))`,
    values: [hospitalId, ...realPaymentValues()],
  };
}
/**
 * Verified refunds (same rule as the monthly job: refund_verified events, real providers, by received_at) that no settlement
 * period covers; a refund of an old payment after the last period_end counts, whatever the payment date.
 */
export function unsettledRefundsQuery(hospitalId: number): Query {
  return {
    sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_payment_events e JOIN gangnamunni_platform_payments p ON p.id = e.payment_id
     WHERE p.hospital_id = ? AND e.event_type = 'refund_verified' AND p.provider NOT IN (?)
       AND NOT EXISTS (SELECT 1 FROM gangnamunni_hospital_settlements s
         WHERE s.hospital_id = p.hospital_id AND e.received_at >= s.period_start AND e.received_at < DATE_ADD(s.period_end, INTERVAL 1 DAY))`,
    values: [hospitalId, PAYMENT_TEST_PROVIDERS],
  };
}

export type Blocker =
  | { code: 'upcoming_bookings'; count: number; lastAt: string | null }
  | { code: 'open_consultations'; count: number; lastAt: string | null }
  | { code: 'unsettled'; settlements: number; unsettledPayments: number };

export interface BlockerRows {
  bookings: Row;
  consultations: Row;
  settlements: Row;
  last: Row;
  payments: Row;
  refunds?: Row;
}

export interface BlockerResult {
  blockers: Blocker[];
  /** latest time of an open booking; only when nothing but time stands in the way (no money blocker) */
  deletableAfter: string | null;
}

export function buildBlockers(r: BlockerRows): BlockerResult {
  const blockers: Blocker[] = [];
  const bookings = num(r.bookings?.cnt);
  const bookingLast = r.bookings?.last_at ? String(r.bookings.last_at) : null;
  if (bookings > 0) blockers.push({ code: 'upcoming_bookings', count: bookings, lastAt: bookingLast });
  const consultations = num(r.consultations?.cnt);
  // A consultation has no date that ends it: someone has to close it, so there is no "after" time.
  if (consultations > 0) blockers.push({ code: 'open_consultations', count: consultations, lastAt: null });
  // Unresolved settlements, plus a newest settlement that is final but ended with a negative balance (it is carried forward).
  const lastIsFinalNegative = r.last && SETTLEMENT_FINAL_STATUSES.includes(String(r.last.status)) && num(r.last.balance) < 0 ? 1 : 0;
  const settlements = num(r.settlements?.open_cnt) + lastIsFinalNegative;
  const unsettledPayments = num(r.payments?.cnt) + num(r.refunds?.cnt);
  const money = settlements > 0 || unsettledPayments > 0;
  if (money) blockers.push({ code: 'unsettled', settlements, unsettledPayments });
  const lasts = blockers.flatMap((b) => ('lastAt' in b && b.lastAt ? [b.lastAt] : []));
  return { blockers, deletableAfter: !money && lasts.length > 0 ? lasts.sort().at(-1)! : null };
}

// ---- snapshot ----

/** Columns copied into the archive. Never the API key, certificates or any document path. */
export const SNAPSHOT_HOSPITAL_COLUMNS = [
  'id', 'name', 'owner_name', 'business_number', 'introduction', 'address', 'phone', 'email', 'contact_name', 'contact_phone', 'website', 'sns_url',
  'categories', 'badges', 'treatment_tags', 'subway_names', 'business_hours', 'latitude', 'longitude', 'status', 'is_active', 'ad_grade', 'rating',
  'review_count', 'show_rating', 'main_image_url', 'profile_image_url', 'applied_by', 'applied_at', 'created_at', 'updated_at',
];

export const hospitalRowQuery = (id: number, lock: boolean): Query => ({
  sql: `SELECT ${SNAPSHOT_HOSPITAL_COLUMNS.join(', ')} FROM gangnamunni_hospitals WHERE id = ? AND status = 'approved' LIMIT 1${lock ? ' FOR UPDATE' : ''}`,
  values: [id],
});
export const snapshotDoctorsQuery = (id: number): Query => ({ sql: `SELECT id, name, specialty FROM gangnamunni_doctors WHERE hospital_id = ? ORDER BY id ASC`, values: [id] });
export const snapshotImagesQuery = (id: number): Query => ({ sql: `SELECT id, image_type, image_url FROM hospital_images WHERE hospital_id = ? ORDER BY id ASC`, values: [id] });
export const treatmentCountQuery = (id: number): Query => ({ sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_hospital_treatments WHERE hospital_id = ?`, values: [id] });
export const imageCountQuery = (id: number): Query => ({ sql: `SELECT COUNT(*) AS cnt FROM hospital_images WHERE hospital_id = ?`, values: [id] });
export const doctorCountQuery = (id: number): Query => ({ sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_doctors WHERE hospital_id = ?`, values: [id] });

export interface Snapshot {
  version: 1;
  hospital: Record<string, unknown>;
  doctors: { id: number; name: string; position: string }[];
  images: { id: number; type: string; url: string }[];
  treatmentsCount: number;
}

const text = (v: unknown): string => (v == null ? '' : String(v));

/** Builds the sealed snapshot from rows; only the whitelisted hospital columns are copied. */
export function buildSnapshot(hospital: Record<string, unknown>, doctors: Row[], images: Row[], treatmentsCount: number): Snapshot {
  const h: Record<string, unknown> = {};
  for (const c of SNAPSHOT_HOSPITAL_COLUMNS) if (c in hospital) h[c] = hospital[c] ?? null;
  return {
    version: 1,
    hospital: h,
    doctors: doctors.map((d) => ({ id: num(d?.id), name: text(d?.name), position: text(d?.specialty) })),
    images: images.map((i) => ({ id: num(i?.id), type: text(i?.image_type), url: text(i?.image_url) })),
    treatmentsCount,
  };
}

// ---- delete request ----

export type ParsedDelete = { ok: true; confirmName: string; expectedUpdatedAt: string | null } | { ok: false; fields: Record<string, string> };

/** Body of POST /v1/hospitals/:id/delete. */
export function parseDeleteBody(body: unknown): ParsedDelete {
  const b = body && typeof body === 'object' && !Array.isArray(body) ? (body as Record<string, unknown>) : {};
  const fields: Record<string, string> = {};
  if (typeof b.confirmName !== 'string') fields.confirmName = b.confirmName === undefined ? 'required' : 'bad_format';
  let expected: string | null = null;
  if (!('expectedUpdatedAt' in b)) fields.expectedUpdatedAt = 'required';
  else if (b.expectedUpdatedAt === null) expected = null;
  else if (typeof b.expectedUpdatedAt === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(b.expectedUpdatedAt)) expected = b.expectedUpdatedAt;
  else fields.expectedUpdatedAt = 'bad_format';
  if (Object.keys(fields).length > 0) return { ok: false, fields };
  return { ok: true, confirmName: b.confirmName as string, expectedUpdatedAt: expected };
}

/** Compared in Unicode NFC, so a name typed with decomposed Hangul (macOS) still matches. */
export const nameMatches = (typed: string, stored: unknown): boolean => typed !== '' && typed.normalize('NFC') === text(stored).normalize('NFC');

// ---- archive ----

export const ARCHIVE_COLUMNS = `id, hospital_id, hospital_name, snapshot, bookings_total, bookings_cancelled, sales_paid, sales_refunded, sales_net,
  deleted_by_admin_id, deleted_by_name, deleted_at, notice_acked_at, notice_acked_by`;

/** deleted_at + 1 month has passed and nobody acknowledged the notice yet. */
export const NOTICE_DUE_SQL = `(notice_acked_at IS NULL AND deleted_at + INTERVAL 1 MONTH <= NOW())`;

export const insertArchiveQuery = (a: {
  hospitalId: number; name: string; snapshot: string; bookingsTotal: number; bookingsCancelled: number; paid: number; refunded: number; net: number;
  adminId: number; adminName: string;
}): Query => ({
  sql: `INSERT INTO gangnamunni_hospital_deletions
     (hospital_id, hospital_name, snapshot, bookings_total, bookings_cancelled, sales_paid, sales_refunded, sales_net, deleted_by_admin_id, deleted_by_name, deleted_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
  values: [a.hospitalId, a.name, a.snapshot, a.bookingsTotal, a.bookingsCancelled, a.paid, a.refunded, a.net, a.adminId, a.adminName],
});

export const archiveListQuery = (page: number, perPage: number): Query => ({
  sql: `SELECT id, hospital_id, hospital_name, bookings_total, bookings_cancelled, sales_net, deleted_by_name, deleted_at, notice_acked_at,
       ${NOTICE_DUE_SQL} AS notice_due
     FROM gangnamunni_hospital_deletions ORDER BY deleted_at DESC, id DESC LIMIT ? OFFSET ?`,
  values: [perPage, (page - 1) * perPage],
});
export const archiveCountQuery = (): Query => ({
  sql: `SELECT COUNT(*) AS total, COALESCE(SUM(${NOTICE_DUE_SQL}), 0) AS due FROM gangnamunni_hospital_deletions`,
  values: [],
});
export const archiveOneQuery = (id: number): Query => ({
  sql: `SELECT id, hospital_id, hospital_name, snapshot, bookings_total, bookings_cancelled, sales_paid, sales_refunded, sales_net, deleted_by_name,
       deleted_at, notice_acked_at, ${NOTICE_DUE_SQL} AS notice_due
     FROM gangnamunni_hospital_deletions WHERE id = ? LIMIT 1`,
  values: [id],
});
export const ackQuery = (id: number, adminName: string): Query => ({
  sql: `UPDATE gangnamunni_hospital_deletions SET notice_acked_at = NOW(), notice_acked_by = ? WHERE id = ? AND notice_acked_at IS NULL`,
  values: [adminName, id],
});
export const archiveExistsQuery = (id: number): Query => ({ sql: `SELECT id FROM gangnamunni_hospital_deletions WHERE id = ? LIMIT 1`, values: [id] });

export function toArchiveItem(r: Record<string, unknown>) {
  return {
    id: num(r.id), hospitalId: num(r.hospital_id), name: text(r.hospital_name), deletedAt: text(r.deleted_at), deletedByName: text(r.deleted_by_name),
    bookingsTotal: num(r.bookings_total), bookingsCancelled: num(r.bookings_cancelled), salesNet: num(r.sales_net),
    noticeDue: num(r.notice_due) === 1, noticeAckedAt: r.notice_acked_at == null ? null : text(r.notice_acked_at),
  };
}

/** Snapshot text -> response shape; an unreadable value never fails the page. */
export function parseSnapshot(raw: unknown) {
  let s: Partial<Snapshot> = {};
  try {
    const v = JSON.parse(text(raw));
    if (v && typeof v === 'object') s = v as Partial<Snapshot>;
  } catch {
    s = {};
  }
  const images = Array.isArray(s.images) ? s.images : [];
  return {
    hospital: s.hospital && typeof s.hospital === 'object' ? s.hospital : {},
    doctors: Array.isArray(s.doctors) ? s.doctors : [],
    images,
    imagesCount: images.length,
    treatmentsCount: num(s.treatmentsCount),
  };
}

export function toArchiveDetail(r: Record<string, unknown>) {
  return { ...toArchiveItem(r), salesPaid: num(r.sales_paid), salesRefunded: num(r.sales_refunded), snapshot: parseSnapshot(r.snapshot) };
}
