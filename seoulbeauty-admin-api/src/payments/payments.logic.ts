import { AdminScope } from '../admin-auth/admin.guard';
import { Fail, parseDay, parsePositiveInt, Query, scopeClause } from '../bookings/bookings.logic';

export const PAY_PER_PAGE = 20;
export const MAX_PAGE = 100000;
export const SEARCH_MAX = 60;
/** One request never reads more than about a year of payments; a wider range is a 400 instead of a heavy query. */
export const MAX_RANGE_DAYS = 366;
/** Hospital and treatment tables show at most this many rows; one more is read to tell the caller the table was cut. */
export const TABLE_CAP = 500;
/** Bookings in these states have no treatment to wait for: money still held for them needs a refund or a decision, not more waiting. */
export const DEAD_BOOKING_STATUSES = ['cancelled', 'refunded', 'no_show'] as const;
/** Sandbox / mock payments never count as revenue. */
export const TEST_PROVIDERS = ['mock_booking_payment', 'inicis_test'] as const;
export const COUNTED_STATUSES = ['paid', 'partially_refunded', 'refunded'] as const;
export const METRICS = ['received', 'recognized', 'held'] as const;
export type Metric = (typeof METRICS)[number];

const s = (v: unknown): string => (v == null ? '' : String(v));
const escapeLike = (v: string): string => v.replace(/[\\%_]/g, (c) => '\\' + c);
const stripControl = (v: string): string => v.replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim();

/**
 * Money the hospital has received but may not take out yet is "held": the payment is real (and shown), but the booking is not
 * `completed` (the treatment has not been done). It moves to "recognized" when the booking becomes `completed`.
 * Both are what is left after refunds, so received - refunded = recognized + held.
 */
const NET = '(p.amount - p.refunded_amount)';
export const RECOGNIZED_SQL = `CASE WHEN b.status = 'completed' THEN ${NET} ELSE 0 END`;
export const HELD_SQL = `CASE WHEN COALESCE(b.status, '') <> 'completed' THEN ${NET} ELSE 0 END`;
export const METRIC_SQL: Record<Metric, string> = { received: 'p.amount', recognized: RECOGNIZED_SQL, held: HELD_SQL };

const BASE_SQL = `p.payment_type = 'booking' AND p.funds_recipient = 'hospital' AND p.provider NOT IN (${TEST_PROVIDERS.map(() => '?').join(', ')}) AND p.status IN (${COUNTED_STATUSES.map(() => '?').join(', ')})`;
const baseValues = (): unknown[] => [...TEST_PROVIDERS, ...COUNTED_STATUSES];
const FROM_SQL = 'FROM gangnamunni_platform_payments p LEFT JOIN gangnamunni_bookings b ON b.id = p.booking_id';
const STUCK_SQL = `CASE WHEN b.status IN (${DEAD_BOOKING_STATUSES.map((x) => `'${x}'`).join(', ')}) THEN ${NET} ELSE 0 END`;
const SUMS_SQL = `COUNT(*) AS payments, COALESCE(SUM(p.amount), 0) AS received, COALESCE(SUM(p.refunded_amount), 0) AS refunded,
  COALESCE(SUM(${RECOGNIZED_SQL}), 0) AS recognized, COALESCE(SUM(${HELD_SQL}), 0) AS held, COALESCE(SUM(${STUCK_SQL}), 0) AS stuck`;

/**
 * Fee and settlement (only when the fee switch is on; the same rule as the PHP revenue page and platform_payment_figures()).
 * A payment has a recorded fee when pay_method, fee_rate_bp and fee_amount were all stored at payment time; otherwise fee and settlement stay unknown (never guessed).
 * Settlement = (paid by card/transfer - fee) + points that covered part of the price; a part refund removes that part and its fee (rounded down); a full refund is 0.
 * Payout day = the Sunday strictly after the visit date of a completed booking; from that day the settlement is "received", before it "counted".
 */
export const FEE_RECORDED_SQL = "(p.pay_method IS NOT NULL AND p.pay_method <> '' AND p.fee_rate_bp IS NOT NULL AND p.fee_amount IS NOT NULL)";
/**
 * Points that covered part of a price (`points_used`, from the points schema). Read only with ADMIN_PAYMENTS_POINTS=1 (read once at start): the column does not
 * exist until the points system is deployed, and a missing column must not break the payments screen. Off = 0 points.
 */
const POINTS_ON = process.env.ADMIN_PAYMENTS_POINTS === '1';
const POINTS_SQL = POINTS_ON ? 'COALESCE(p.points_used, 0)' : '0';
const FEE_NET_SQL = 'GREATEST(0, CAST(p.fee_amount AS SIGNED) - ((CAST(p.refunded_amount AS SIGNED) * CAST(p.fee_rate_bp AS SIGNED)) DIV 10000))';
const FEE_ROW_SQL = `CASE WHEN p.status = 'refunded' THEN 0 ELSE ${FEE_NET_SQL} END`;
const SETTLE_ROW_SQL = `CASE WHEN p.status = 'refunded' THEN 0 ELSE (CAST(p.amount AS SIGNED) - CAST(p.refunded_amount AS SIGNED)) - ${FEE_NET_SQL} + CAST(${POINTS_SQL} AS SIGNED) END`;
export const PAYOUT_SQL = 'DATE_ADD(b.booking_date, INTERVAL (8 - DAYOFWEEK(b.booking_date)) DAY)';
/** One placeholder: today (Seoul, Y-m-d). */
export const FEE_SUMS_SQL = `COALESCE(SUM(CASE WHEN ${FEE_RECORDED_SQL} THEN ${FEE_ROW_SQL} ELSE 0 END), 0) AS fee,
  COALESCE(SUM(CASE WHEN ${FEE_RECORDED_SQL} THEN ${SETTLE_ROW_SQL} ELSE 0 END), 0) AS settlement,
  COALESCE(SUM(CASE WHEN ${FEE_RECORDED_SQL} AND p.status <> 'refunded' AND b.status = 'completed' AND ${PAYOUT_SQL} <= ? THEN ${SETTLE_ROW_SQL} ELSE 0 END), 0) AS settlement_received,
  COALESCE(SUM(CASE WHEN ${FEE_RECORDED_SQL} OR p.status = 'refunded' THEN 0 ELSE 1 END), 0) AS no_fee`;
const sumsSql = (today?: string): string => (today ? `${SUMS_SQL}, ${FEE_SUMS_SQL}` : SUMS_SQL);
const feeValues = (today?: string): unknown[] => (today ? [today] : []);

// ───────── Seoul calendar ─────────
export const seoulToday = (now: Date = new Date()): string => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Seoul', year: 'numeric', month: '2-digit', day: '2-digit' }).format(now);
const utc = (day: string): Date => { const [y, m, d] = day.split('-').map(Number); return new Date(Date.UTC(y, m - 1, d)); };
const fmt = (d: Date): string => d.toISOString().slice(0, 10);
export const addDays = (day: string, n: number): string => { const d = utc(day); d.setUTCDate(d.getUTCDate() + n); return fmt(d); };
/** Monday = 0 ... Sunday = 6. */
export const weekdayIndex = (day: string): number => (utc(day).getUTCDay() + 6) % 7;
export const daysInMonth = (year: number, month: number): number => new Date(Date.UTC(year, month, 0)).getUTCDate();
const monthStart = (day: string): string => `${day.slice(0, 7)}-01`;

export interface Periods {
  today: string;
  week: { thisStart: string; lastStart: string; lastEnd: string; elapsed: number };
  month: { thisStart: string; dayOfMonth: number; lastStart: string; lastEnd: string; lastDays: number };
}
/** Weeks run Monday to Sunday; the current one is partial, the last one is whole. Months likewise. */
export function periodsOf(today: string): Periods {
  const wd = weekdayIndex(today);
  const thisStart = addDays(today, -wd);
  const lastStart = addDays(thisStart, -7);
  const y = Number(today.slice(0, 4));
  const m = Number(today.slice(5, 7));
  const py = m === 1 ? y - 1 : y;
  const pm = m === 1 ? 12 : m - 1;
  const lastDays = daysInMonth(py, pm);
  const lastStartM = `${py}-${String(pm).padStart(2, '0')}-01`;
  return {
    today,
    week: { thisStart, lastStart, lastEnd: addDays(lastStart, 6), elapsed: wd + 1 },
    month: { thisStart: monthStart(today), dayOfMonth: Number(today.slice(8, 10)), lastStart: lastStartM, lastEnd: `${py}-${String(pm).padStart(2, '0')}-${String(lastDays).padStart(2, '0')}`, lastDays },
  };
}
export const percentChange = (now: number, before: number): number | null => (before > 0 ? Math.round(((now - before) / before) * 1000) / 10 : null);

// ───────── query strings ─────────
/** The list is narrowed by hospital, payment dates and the patient's name, nothing else. */
export interface PaymentFilters { hospitalId: number; from: string; to: string; q: string; page: number }
export interface RangeFilters { hospitalId: number; from: string; to: string }

function parseRange(raw: Record<string, unknown>, today: string): RangeFilters | Fail {
  const out: RangeFilters = { hospitalId: 0, from: monthStart(today), to: today };
  if (s(raw.hospital_id).trim() !== '') {
    const id = parsePositiveInt(raw.hospital_id);
    if (id === null) return { error: 'invalid', field: 'hospital_id' };
    out.hospitalId = id;
  }
  if (s(raw.from).trim() !== '') { const d = parseDay(raw.from); if (!d) return { error: 'invalid', field: 'from' }; out.from = d; }
  if (s(raw.to).trim() !== '') { const d = parseDay(raw.to); if (!d) return { error: 'invalid', field: 'to' }; out.to = d; }
  if (out.from > out.to) return { error: 'invalid', field: 'to' };
  if ((utc(out.to).getTime() - utc(out.from).getTime()) / 86400000 >= MAX_RANGE_DAYS) return { error: 'range_too_long', field: 'to' };
  return out;
}
export const parseRangeQuery = parseRange;
/** The first TABLE_CAP rows and whether more existed. */
export const capped = <T>(rows: T[]): { rows: T[]; truncated: boolean } => ({ rows: rows.slice(0, TABLE_CAP), truncated: rows.length > TABLE_CAP });

/** Payment date range defaults to this month so far. A malformed date or hospital is a 400, never silently ignored. */
export function parsePaymentQuery(raw: Record<string, unknown>, today: string = seoulToday()): PaymentFilters | Fail {
  const r = parseRange(raw, today);
  if ('error' in r) return r;
  const out: PaymentFilters = { ...r, q: '', page: 1 };
  const q = stripControl(s(raw.q));
  if (q.length > SEARCH_MAX) return { error: 'invalid', field: 'q' };
  out.q = q;
  if (s(raw.page).trim() !== '') {
    const p = parsePositiveInt(raw.page);
    if (p === null || p > MAX_PAGE) return { error: 'invalid', field: 'page' };
    out.page = p;
  }
  return out;
}

export interface CompareFilters { hospitalId: number; metric: Metric }
export function parseCompareQuery(raw: Record<string, unknown>): CompareFilters | Fail {
  const out: CompareFilters = { hospitalId: 0, metric: 'received' };
  if (s(raw.hospital_id).trim() !== '') {
    const id = parsePositiveInt(raw.hospital_id);
    if (id === null) return { error: 'invalid', field: 'hospital_id' };
    out.hospitalId = id;
  }
  const m = s(raw.metric).trim();
  if (m !== '') { if (!(METRICS as readonly string[]).includes(m)) return { error: 'invalid', field: 'metric' }; out.metric = m as Metric; }
  return out;
}

const PAID_RANGE_SQL = 'p.paid_at >= ? AND p.paid_at < DATE_ADD(?, INTERVAL 1 DAY)';

function whereOf(scope: AdminScope, f: PaymentFilters): Query {
  const sc = scopeClause(scope, f.hospitalId, 'p.hospital_id');
  const parts = [BASE_SQL, sc.sql, PAID_RANGE_SQL];
  const values: unknown[] = [...baseValues(), ...sc.values, f.from, f.to];
  if (f.q) {
    // Only the patient's name (the phone number is masked in every answer, so it is not searched).
    parts.push("p.patient_name LIKE ? ESCAPE '\\\\'");
    values.push(`%${escapeLike(f.q)}%`);
  }
  return { sql: parts.join(' AND '), values };
}

const LIST_COLUMNS = `p.id, p.booking_id, p.hospital_id, p.hospital_name, p.patient_name, p.patient_phone, p.treatment_name, p.amount, p.refunded_amount,
  p.status, p.paid_at, p.refunded_at, b.status AS booking_status, b.booking_date, b.booking_time`;
const FEE_LIST_COLUMNS = `, p.pay_method, p.fee_rate_bp, p.fee_amount${POINTS_ON ? ', p.points_used' : ''}`;

/** Newest payment first. */
export function listQuery(scope: AdminScope, f: PaymentFilters, withFees = false): Query {
  const w = whereOf(scope, f);
  return { sql: `SELECT ${LIST_COLUMNS}${withFees ? FEE_LIST_COLUMNS : ''} ${FROM_SQL} WHERE ${w.sql} ORDER BY p.paid_at DESC, p.id DESC LIMIT ? OFFSET ?`, values: [...w.values, PAY_PER_PAGE, (f.page - 1) * PAY_PER_PAGE] };
}
export function countQuery(scope: AdminScope, f: PaymentFilters): Query {
  const w = whereOf(scope, f);
  return { sql: `SELECT COUNT(*) AS cnt ${FROM_SQL} WHERE ${w.sql}`, values: w.values };
}
/** The panel above the list follows the hospital and the dates only (not the status or the search). */
export function summaryQuery(scope: AdminScope, f: RangeFilters, today?: string): Query {
  const sc = scopeClause(scope, f.hospitalId, 'p.hospital_id');
  return { sql: `SELECT ${sumsSql(today)} ${FROM_SQL} WHERE ${BASE_SQL} AND ${sc.sql} AND ${PAID_RANGE_SQL}`, values: [...feeValues(today), ...baseValues(), ...sc.values, f.from, f.to] };
}
/** Every hospital of the scope, also the ones without a payment in the dates. */
export function byHospitalQuery(scope: AdminScope, f: RangeFilters, today?: string): Query {
  const sc = scopeClause(scope, f.hospitalId, 'h.id');
  return {
    sql: `SELECT h.id AS hospital_id, h.name AS hospital_name, COALESCE(a.payments, 0) AS payments, COALESCE(a.received, 0) AS received, COALESCE(a.refunded, 0) AS refunded,
                 COALESCE(a.recognized, 0) AS recognized, COALESCE(a.held, 0) AS held, COALESCE(a.stuck, 0) AS stuck${today ? `,
                 COALESCE(a.fee, 0) AS fee, COALESCE(a.settlement, 0) AS settlement, COALESCE(a.settlement_received, 0) AS settlement_received, COALESCE(a.no_fee, 0) AS no_fee` : ''}
          FROM gangnamunni_hospitals h
          LEFT JOIN (SELECT p.hospital_id, ${sumsSql(today)} ${FROM_SQL} WHERE ${BASE_SQL} AND ${PAID_RANGE_SQL} GROUP BY p.hospital_id) a ON a.hospital_id = h.id
          WHERE ${sc.sql}
          ORDER BY (COALESCE(a.received, 0) - COALESCE(a.refunded, 0)) DESC, h.name ASC LIMIT ${TABLE_CAP + 1}`,
    values: [...feeValues(today), ...baseValues(), f.from, f.to, ...sc.values],
  };
}
export function byTreatmentQuery(scope: AdminScope, f: RangeFilters, today?: string): Query {
  const sc = scopeClause(scope, f.hospitalId, 'p.hospital_id');
  return {
    sql: `SELECT p.treatment_name AS treatment_name, ${sumsSql(today)} ${FROM_SQL} WHERE ${BASE_SQL} AND ${sc.sql} AND ${PAID_RANGE_SQL}
          GROUP BY p.treatment_name ORDER BY (SUM(p.amount) - SUM(p.refunded_amount)) DESC, p.treatment_name ASC LIMIT ${TABLE_CAP + 1}`,
    values: [...feeValues(today), ...baseValues(), ...sc.values, f.from, f.to],
  };
}
/** Per payment method (card / transfer / unknown) in the dates: paid after refunds, fee and settlement. Only with the fee switch. */
export function byMethodQuery(scope: AdminScope, f: RangeFilters, today: string): Query {
  const sc = scopeClause(scope, f.hospitalId, 'p.hospital_id');
  return {
    sql: `SELECT COALESCE(p.pay_method, '') AS method, ${SUMS_SQL}, ${FEE_SUMS_SQL} ${FROM_SQL} WHERE ${BASE_SQL} AND ${sc.sql} AND ${PAID_RANGE_SQL} GROUP BY COALESCE(p.pay_method, '') ORDER BY method ASC`,
    values: [today, ...baseValues(), ...sc.values, f.from, f.to],
  };
}
/** Settlement per payout Sunday (the hospital's week): only completed bookings with a recorded fee. Newest week first. Only with the fee switch. */
export const WEEKS_CAP = 60;
export function weeklyQuery(scope: AdminScope, f: RangeFilters): Query {
  const sc = scopeClause(scope, f.hospitalId, 'p.hospital_id');
  return {
    sql: `SELECT DATE_FORMAT(${PAYOUT_SQL}, '%Y-%m-%d') AS payout_date, COUNT(*) AS payments, COALESCE(SUM(${FEE_ROW_SQL}), 0) AS fee, COALESCE(SUM(${SETTLE_ROW_SQL}), 0) AS settlement ${FROM_SQL}
          WHERE ${BASE_SQL} AND ${sc.sql} AND ${PAID_RANGE_SQL} AND b.status = 'completed' AND b.booking_date IS NOT NULL AND p.status <> 'refunded' AND ${FEE_RECORDED_SQL}
          GROUP BY payout_date ORDER BY payout_date DESC LIMIT ${WEEKS_CAP}`,
    values: [...baseValues(), ...sc.values, f.from, f.to],
  };
}
/** Held (the hospital's word): treatment booked, payment still awaited. A current stock, not bound to the dates; no fee is counted for it. Only with the fee switch. */
export function orderedHeldQuery(scope: AdminScope, hospitalId: number): Query {
  const sc = scopeClause(scope, hospitalId, 'p.hospital_id');
  return {
    sql: `SELECT COUNT(*) AS cnt, COALESCE(SUM(p.amount), 0) AS amount FROM gangnamunni_platform_payments p
          WHERE p.payment_type = 'booking' AND p.funds_recipient = 'hospital' AND p.status = 'pending' AND (p.provider IS NULL OR p.provider NOT IN (${TEST_PROVIDERS.map(() => '?').join(', ')})) AND ${sc.sql}`,
    values: [...TEST_PROVIDERS, ...sc.values],
  };
}
// ───────── the three sections: 보류 (held) / 반영 (counted) / 입금 (received) ─────────
export const STAGES = ['held', 'counted', 'received'] as const;
export type Stage = (typeof STAGES)[number];
export const PERIODS = ['week', 'month'] as const;
export type Period = (typeof PERIODS)[number];
const LIVE_STATUSES = ['paid', 'partially_refunded'] as const;
/** Ledger rows of the three sections: real (not test) booking payments to the hospital that are still pending or live (never fully refunded). */
const STAGE_BASE_SQL = `p.payment_type = 'booking' AND p.funds_recipient = 'hospital' AND (p.provider IS NULL OR p.provider NOT IN (${TEST_PROVIDERS.map(() => '?').join(', ')})) AND p.status IN ('pending', 'paid', 'partially_refunded')`;
const stageBaseValues = (): unknown[] => [...TEST_PROVIDERS];
const RECEIVED_COND = `COALESCE(b.status = 'completed' AND ${PAYOUT_SQL} <= ?, 0)`;
/** held: ordered, payment awaited (dated by the visit day); counted: paid, not paid out yet (dated by the payment day); received: paid out (dated by the payout Sunday). */
const stageCond = (stage: Stage, today: string): Query => {
  if (stage === 'held') return { sql: "p.status = 'pending'", values: [] };
  const live = `p.status IN (${LIVE_STATUSES.map((x) => `'${x}'`).join(', ')})`;
  return stage === 'received' ? { sql: `${live} AND ${RECEIVED_COND}`, values: [today] } : { sql: `${live} AND NOT ${RECEIVED_COND}`, values: [today] };
};
const stageDateSql = (stage: Stage): string => (stage === 'held' ? 'b.booking_date' : stage === 'counted' ? 'DATE(p.paid_at)' : PAYOUT_SQL);
const STAGE_RANGE = (stage: Stage): string => `${stageDateSql(stage)} >= ? AND ${stageDateSql(stage)} <= ?`;

export interface SectionFilters { stage: Stage; hospitalId: number; from: string; to: string; q: string; page: number }
export function parseSectionQuery(raw: Record<string, unknown>, today: string = seoulToday()): SectionFilters | Fail {
  const st = s(raw.stage).trim();
  if (!(STAGES as readonly string[]).includes(st)) return { error: 'invalid', field: 'stage' };
  // Without dates a section shows the whole current month (the board's month box), so the box and the list agree even for future visit days.
  const month = periodBounds('month', today);
  const f = parsePaymentQuery({ ...raw, from: s(raw.from).trim() === '' ? month.from : raw.from, to: s(raw.to).trim() === '' ? month.to : raw.to }, today);
  if ('error' in f) return f;
  return { ...f, stage: st as Stage };
}
/** `from` and `to` (both or neither) replace the week / month bounds with the dates the admin chose. */
export interface BoardFilters { hospitalId: number; period: Period; from?: string; to?: string }
export function parseBoardQuery(raw: Record<string, unknown>): BoardFilters | Fail {
  const out: BoardFilters = { hospitalId: 0, period: 'week' };
  if (s(raw.hospital_id).trim() !== '') {
    const id = parsePositiveInt(raw.hospital_id);
    if (id === null) return { error: 'invalid', field: 'hospital_id' };
    out.hospitalId = id;
  }
  const pr = s(raw.period).trim();
  if (pr !== '') { if (!(PERIODS as readonly string[]).includes(pr)) return { error: 'invalid', field: 'period' }; out.period = pr as Period; }
  if (s(raw.from).trim() !== '' || s(raw.to).trim() !== '') {
    const a = parseDay(raw.from);
    const b = parseDay(raw.to);
    if (!a) return { error: 'invalid', field: 'from' };
    if (!b || a > b) return { error: 'invalid', field: 'to' };
    if ((utc(b).getTime() - utc(a).getTime()) / 86400000 >= MAX_RANGE_DAYS) return { error: 'range_too_long', field: 'to' };
    out.from = a;
    out.to = b;
  }
  return out;
}
/** This week (Mon..Sun) or this month, whole, as Y-m-d bounds. */
export function periodBounds(period: Period, today: string): { from: string; to: string } {
  const p = periodsOf(today);
  if (period === 'week') return { from: p.week.thisStart, to: addDays(p.week.thisStart, 6) };
  const [y, m] = [Number(today.slice(0, 4)), Number(today.slice(5, 7))];
  return { from: p.month.thisStart, to: `${today.slice(0, 7)}-${String(daysInMonth(y, m)).padStart(2, '0')}` };
}

const sectionWhere = (scope: AdminScope, f: SectionFilters, today: string): Query => {
  const sc = scopeClause(scope, f.hospitalId, 'p.hospital_id');
  const c = stageCond(f.stage, today);
  const parts = [STAGE_BASE_SQL, c.sql, sc.sql, STAGE_RANGE(f.stage)];
  const values: unknown[] = [...stageBaseValues(), ...c.values, ...sc.values, f.from, f.to];
  if (f.q) { parts.push("p.patient_name LIKE ? ESCAPE '\\\\'"); values.push(`%${escapeLike(f.q)}%`); }
  return { sql: parts.join(' AND '), values };
};
/** One page of a section, newest date first. The columns are the list's plus the fee ones. */
export function sectionListQuery(scope: AdminScope, f: SectionFilters, today: string): Query {
  const w = sectionWhere(scope, f, today);
  return { sql: `SELECT ${LIST_COLUMNS}${FEE_LIST_COLUMNS} ${FROM_SQL} WHERE ${w.sql} ORDER BY ${stageDateSql(f.stage)} DESC, b.booking_time DESC, p.id DESC LIMIT ? OFFSET ?`, values: [...w.values, PAY_PER_PAGE, (f.page - 1) * PAY_PER_PAGE] };
}
/** Count, paid money and (for counted / received) fee and settlement of everything the filters match, not only the page. */
export function sectionTotalsQuery(scope: AdminScope, f: SectionFilters, today: string): Query {
  const w = sectionWhere(scope, f, today);
  return { sql: `SELECT COUNT(*) AS payments, COALESCE(SUM(p.amount), 0) AS received, COALESCE(SUM(p.refunded_amount), 0) AS refunded, 0 AS recognized, 0 AS held, 0 AS stuck, ${FEE_SUMS_SQL} ${FROM_SQL} WHERE ${w.sql}`, values: [today, ...w.values] };
}
/** The three boxes above the sections for one period (hospital scope only): count and money of each stage in the period. */
export function boardQuery(scope: AdminScope, f: BoardFilters, from: string, to: string, today: string): Query {
  const sc = scopeClause(scope, f.hospitalId, 'p.hospital_id');
  const select: string[] = [];
  const values: unknown[] = [];
  for (const stage of STAGES) {
    const c = stageCond(stage, today);
    const cond = `(${c.sql} AND ${STAGE_RANGE(stage)})`;
    const condValues = [...c.values, from, to];
    const money = stage === 'held' ? 'p.amount' : `CASE WHEN ${FEE_RECORDED_SQL} THEN ${SETTLE_ROW_SQL} ELSE 0 END`;
    select.push(`COALESCE(SUM(CASE WHEN ${cond} THEN 1 ELSE 0 END), 0) AS ${stage}_count`, `COALESCE(SUM(CASE WHEN ${cond} THEN ${money} ELSE 0 END), 0) AS ${stage}_amount`);
    values.push(...condValues, ...condValues);
    if (stage !== 'held') { select.push(`COALESCE(SUM(CASE WHEN ${cond} AND NOT ${FEE_RECORDED_SQL} THEN 1 ELSE 0 END), 0) AS ${stage}_no_fee`); values.push(...condValues); }
  }
  return { sql: `SELECT ${select.join(', ')} ${FROM_SQL} WHERE ${STAGE_BASE_SQL} AND ${sc.sql}`, values: [...values, ...stageBaseValues(), ...sc.values] };
}
export interface BoardBox { count: number; amount: number; noFee: number }
export function toBoard(r: Record<string, unknown> | undefined): Record<Stage, BoardBox> {
  const n = (k: string): number => Number(r?.[k]) || 0;
  return {
    held: { count: n('held_count'), amount: n('held_amount'), noFee: 0 },
    counted: { count: n('counted_count'), amount: n('counted_amount'), noFee: n('counted_no_fee') },
    received: { count: n('received_count'), amount: n('received_amount'), noFee: n('received_no_fee') },
  };
}

export function hospitalOptionsQuery(scope: AdminScope): Query {
  const sc = scopeClause(scope, 0, 'h.id');
  return { sql: `SELECT h.id, h.name FROM gangnamunni_hospitals h WHERE ${sc.sql} ORDER BY h.name ASC LIMIT 500`, values: sc.values };
}
/** Money per payment day over a date range, for the week and month comparison (one metric at a time). */
export function seriesQuery(scope: AdminScope, hospitalId: number, metric: Metric, from: string, to: string): Query {
  const sc = scopeClause(scope, hospitalId, 'p.hospital_id');
  return {
    sql: `SELECT DATE_FORMAT(p.paid_at, '%Y-%m-%d') AS pay_day, COALESCE(SUM(${METRIC_SQL[metric]}), 0) AS value ${FROM_SQL}
          WHERE ${BASE_SQL} AND ${sc.sql} AND ${PAID_RANGE_SQL} GROUP BY pay_day`,
    values: [...baseValues(), ...sc.values, from, to],
  };
}

// ───────── answers ─────────
/** `stuck` is the part of `held` whose booking is cancelled / refunded / no-show: it will not be released by a treatment. */
export interface Sums { payments: number; received: number; refunded: number; recognized: number; held: number; stuck: number }
export const toSums = (r: Record<string, unknown> | undefined): Sums => ({
  payments: Number(r?.payments) || 0, received: Number(r?.received) || 0, refunded: Number(r?.refunded) || 0, recognized: Number(r?.recognized) || 0, held: Number(r?.held) || 0, stuck: Number(r?.stuck) || 0,
});
/** Present only with the fee switch. fee / settlement are null for a payment whose fee was not recorded. */
export interface FeeSums { fee: number; settlement: number; settlementCounted: number; settlementReceived: number; noFee: number }
export const toFeeSums = (r: Record<string, unknown> | undefined): FeeSums => {
  const settlement = Number(r?.settlement) || 0;
  const received = Number(r?.settlement_received) || 0;
  return { fee: Number(r?.fee) || 0, settlement, settlementCounted: settlement - received, settlementReceived: received, noFee: Number(r?.no_fee) || 0 };
};
export const PAY_METHODS = ['card', 'bank_transfer'] as const;
export type SettlementState = 'counted' | 'received' | 'refunded' | 'none';
export interface Figures { state: SettlementState; fee: number | null; settlement: number | null; payoutDate: string | null; paidNet: number }
/** The Sunday strictly after a Y-m-d date; null when it is not a real date. Same as platform_payment_next_sunday(). */
export function nextSunday(day: string): string | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(day);
  if (!m) return null;
  const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
  if (d.getUTCFullYear() !== +m[1] || d.getUTCMonth() !== +m[2] - 1 || d.getUTCDate() !== +m[3]) return null;
  const dow = d.getUTCDay();
  d.setUTCDate(d.getUTCDate() + (dow === 0 ? 7 : 7 - dow));
  return fmt(d);
}
export interface FigureRow { status: string; amount: number; refundedAmount: number; pointsUsed: number; payMethod: string; feeRateBp: number | null; feeAmount: number | null; bookingStatus: string; visitDate: string }
/** One payment seen by the hospital; the port of platform_payment_figures() (the same shared examples are tested on both sides). */
export function figuresOf(r: FigureRow, today: string): Figures {
  const amount = Math.max(0, r.amount);
  const refunded = Math.min(amount, Math.max(0, r.refundedAmount));
  const out: Figures = { state: 'none', fee: null, settlement: null, payoutDate: null, paidNet: 0 };
  if (r.status === 'refunded') return { ...out, state: 'refunded', fee: 0, settlement: 0 };
  if (r.status !== 'paid' && r.status !== 'partially_refunded') return out;
  out.paidNet = amount - refunded;
  out.payoutDate = r.bookingStatus === 'completed' ? nextSunday(r.visitDate) : null;
  out.state = out.payoutDate !== null && out.payoutDate <= today ? 'received' : 'counted';
  if (r.payMethod === '' || r.feeRateBp === null || r.feeAmount === null) return out;
  const feeNet = Math.max(0, r.feeAmount - Math.floor((refunded * Math.max(0, r.feeRateBp)) / 10000));
  out.fee = feeNet;
  out.settlement = out.paidNet - feeNet + Math.max(0, r.pointsUsed);
  return out;
}
export interface PaymentItem {
  id: number; bookingId: number; hospitalId: number; hospitalName: string; patientName: string; patientPhone: string; treatmentName: string;
  amount: number; refundedAmount: number; status: string; paidAt: string; refundedAt: string; bookingStatus: string; visitDate: string; visitTime: string;
  /** held = real money, treatment not done yet; recognized = treatment done; refunded = fully returned. */
  stage: 'held' | 'recognized' | 'refunded';
  /** Held, but the booking is cancelled / refunded / no-show: the money will not be released by a treatment. */
  needsAttention: boolean;
  /** Only with the fee switch: card / bank_transfer / '' (unknown), the rate stored with the payment (basis points) and the figures. */
  payMethod?: string; feeRateBp?: number | null; fee?: number | null; settlement?: number | null; settlementState?: SettlementState; payoutDate?: string | null;
}
/** 010-1234-5678 -> 010-****-5678; anything else keeps only its last four characters. */
export function maskPhone(v: string): string {
  const t = v.trim();
  const m = /^(\d{2,3})-?(\d{3,4})-?(\d{4})$/.exec(t);
  if (m) return `${m[1]}-****-${m[3]}`;
  return t.length > 4 ? `****${t.slice(-4)}` : '****';
}
export function toItem(r: Record<string, unknown>, today?: string): PaymentItem {
  const status = s(r.status);
  const booking = s(r.booking_status);
  const item: PaymentItem = {
    id: Number(r.id), bookingId: Number(r.booking_id) || 0, hospitalId: Number(r.hospital_id) || 0, hospitalName: s(r.hospital_name), patientName: s(r.patient_name),
    patientPhone: maskPhone(s(r.patient_phone)), treatmentName: s(r.treatment_name), amount: Number(r.amount) || 0, refundedAmount: Number(r.refunded_amount) || 0, status,
    paidAt: s(r.paid_at), refundedAt: s(r.refunded_at), bookingStatus: booking, visitDate: s(r.booking_date).slice(0, 10), visitTime: s(r.booking_time).slice(0, 5),
    stage: status === 'refunded' ? 'refunded' : booking === 'completed' ? 'recognized' : 'held',
    needsAttention: status !== 'refunded' && (DEAD_BOOKING_STATUSES as readonly string[]).includes(booking),
  };
  if (today) {
    const num = (v: unknown): number | null => (v == null || v === '' ? null : Number(v));
    const method = s(r.pay_method);
    const g = figuresOf({ status, amount: item.amount, refundedAmount: item.refundedAmount, pointsUsed: Number(r.points_used) || 0, payMethod: method, feeRateBp: num(r.fee_rate_bp), feeAmount: num(r.fee_amount), bookingStatus: booking, visitDate: item.visitDate }, today);
    Object.assign(item, { payMethod: method, feeRateBp: num(r.fee_rate_bp), fee: g.fee, settlement: g.settlement, settlementState: g.state, payoutDate: g.payoutDate });
  }
  return item;
}

export interface DayValue { date: string; value: number }
export interface WeekCompare {
  thisWeek: { start: string; days: DayValue[] };
  lastWeek: { start: string; end: string; days: DayValue[] };
  totals: { thisToDate: number; lastSameDays: number; lastFull: number };
  changeSameDays: number | null;
  changeFull: number | null;
}
/** Both weeks day by day (Mon..Sun); this week only up to today. Compared on the same number of days and against the whole last week. */
export function weekCompare(rows: { day: string; value: number }[], p: Periods): WeekCompare {
  const byDay = new Map(rows.map((r) => [r.day, r.value]));
  const days = (start: string, n: number): DayValue[] => Array.from({ length: n }, (_, i) => { const date = addDays(start, i); return { date, value: byDay.get(date) ?? 0 }; });
  const thisDays = days(p.week.thisStart, p.week.elapsed);
  const lastDays = days(p.week.lastStart, 7);
  const sum = (l: DayValue[]): number => l.reduce((a, d) => a + d.value, 0);
  const thisToDate = sum(thisDays);
  const lastSameDays = sum(lastDays.slice(0, p.week.elapsed));
  const lastFull = sum(lastDays);
  return {
    thisWeek: { start: p.week.thisStart, days: thisDays }, lastWeek: { start: p.week.lastStart, end: p.week.lastEnd, days: lastDays },
    totals: { thisToDate, lastSameDays, lastFull }, changeSameDays: percentChange(thisToDate, lastSameDays), changeFull: percentChange(thisToDate, lastFull),
  };
}
export interface MonthCompare {
  thisMonth: { start: string; cumulative: { day: number; value: number }[] };
  lastMonth: { start: string; end: string; cumulative: { day: number; value: number }[] };
  totals: { thisToDate: number; lastSameDay: number; lastFull: number };
  changeSameDay: number | null;
  changeFull: number | null;
}
/** Running total per day of the month. The same-day figure of a shorter last month is its whole total. */
export function monthCompare(rows: { day: string; value: number }[], p: Periods): MonthCompare {
  const byDay = new Map(rows.map((r) => [r.day, r.value]));
  const run = (start: string, n: number): { day: number; value: number }[] => {
    let acc = 0;
    return Array.from({ length: n }, (_, i) => { acc += byDay.get(addDays(start, i)) ?? 0; return { day: i + 1, value: acc }; });
  };
  const cur = run(p.month.thisStart, p.month.dayOfMonth);
  const prev = run(p.month.lastStart, p.month.lastDays);
  const thisToDate = cur[cur.length - 1]?.value ?? 0;
  const lastSameDay = prev[Math.min(p.month.dayOfMonth, prev.length) - 1]?.value ?? 0;
  const lastFull = prev[prev.length - 1]?.value ?? 0;
  return {
    thisMonth: { start: p.month.thisStart, cumulative: cur }, lastMonth: { start: p.month.lastStart, end: p.month.lastEnd, cumulative: prev },
    totals: { thisToDate, lastSameDay, lastFull }, changeSameDay: percentChange(thisToDate, lastSameDay), changeFull: percentChange(thisToDate, lastFull),
  };
}
