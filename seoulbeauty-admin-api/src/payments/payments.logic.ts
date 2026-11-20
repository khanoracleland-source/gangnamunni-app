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
export const LIST_STATUSES = ['held', 'recognized', 'partial', 'refunded'] as const;
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
export interface PaymentFilters { hospitalId: number; status: string; from: string; to: string; q: string; page: number }
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

/** Payment date range defaults to this month so far. An unknown status or a malformed date is a 400, never silently ignored. */
export function parsePaymentQuery(raw: Record<string, unknown>, today: string = seoulToday()): PaymentFilters | Fail {
  const r = parseRange(raw, today);
  if ('error' in r) return r;
  const out: PaymentFilters = { ...r, status: '', q: '', page: 1 };
  const st = s(raw.status).trim();
  if (st !== '') { if (!(LIST_STATUSES as readonly string[]).includes(st)) return { error: 'invalid', field: 'status' }; out.status = st; }
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
  if (f.status === 'held') parts.push(`p.status IN ('paid', 'partially_refunded') AND COALESCE(b.status, '') <> 'completed'`);
  else if (f.status === 'recognized') parts.push(`p.status IN ('paid', 'partially_refunded') AND b.status = 'completed'`);
  else if (f.status === 'partial') parts.push(`p.status = 'partially_refunded'`);
  else if (f.status === 'refunded') parts.push(`p.status = 'refunded'`);
  if (f.q) {
    if (/^\d{4}$/.test(f.q)) {
      // Phone numbers are masked in every answer, so they are searchable only by their last four digits.
      parts.push("RIGHT(REGEXP_REPLACE(p.patient_phone, '[^0-9]', ''), 4) = ?");
      values.push(f.q);
    } else {
      const like = `%${escapeLike(f.q)}%`;
      parts.push("(p.patient_name LIKE ? ESCAPE '\\\\' OR p.treatment_name LIKE ? ESCAPE '\\\\' OR p.hospital_name LIKE ? ESCAPE '\\\\')");
      values.push(like, like, like);
    }
  }
  return { sql: parts.join(' AND '), values };
}

const LIST_COLUMNS = `p.id, p.booking_id, p.hospital_id, p.hospital_name, p.patient_name, p.patient_phone, p.treatment_name, p.amount, p.refunded_amount,
  p.status, p.paid_at, p.refunded_at, b.status AS booking_status, b.booking_date, b.booking_time`;

/** Newest payment first. */
export function listQuery(scope: AdminScope, f: PaymentFilters): Query {
  const w = whereOf(scope, f);
  return { sql: `SELECT ${LIST_COLUMNS} ${FROM_SQL} WHERE ${w.sql} ORDER BY p.paid_at DESC, p.id DESC LIMIT ? OFFSET ?`, values: [...w.values, PAY_PER_PAGE, (f.page - 1) * PAY_PER_PAGE] };
}
export function countQuery(scope: AdminScope, f: PaymentFilters): Query {
  const w = whereOf(scope, f);
  return { sql: `SELECT COUNT(*) AS cnt ${FROM_SQL} WHERE ${w.sql}`, values: w.values };
}
/** The panel above the list follows the hospital and the dates only (not the status or the search). */
export function summaryQuery(scope: AdminScope, f: RangeFilters): Query {
  const sc = scopeClause(scope, f.hospitalId, 'p.hospital_id');
  return { sql: `SELECT ${SUMS_SQL} ${FROM_SQL} WHERE ${BASE_SQL} AND ${sc.sql} AND ${PAID_RANGE_SQL}`, values: [...baseValues(), ...sc.values, f.from, f.to] };
}
/** Every hospital of the scope, also the ones without a payment in the dates. */
export function byHospitalQuery(scope: AdminScope, f: RangeFilters): Query {
  const sc = scopeClause(scope, f.hospitalId, 'h.id');
  return {
    sql: `SELECT h.id AS hospital_id, h.name AS hospital_name, COALESCE(a.payments, 0) AS payments, COALESCE(a.received, 0) AS received, COALESCE(a.refunded, 0) AS refunded,
                 COALESCE(a.recognized, 0) AS recognized, COALESCE(a.held, 0) AS held, COALESCE(a.stuck, 0) AS stuck
          FROM gangnamunni_hospitals h
          LEFT JOIN (SELECT p.hospital_id, ${SUMS_SQL} ${FROM_SQL} WHERE ${BASE_SQL} AND ${PAID_RANGE_SQL} GROUP BY p.hospital_id) a ON a.hospital_id = h.id
          WHERE ${sc.sql}
          ORDER BY (COALESCE(a.received, 0) - COALESCE(a.refunded, 0)) DESC, h.name ASC LIMIT ${TABLE_CAP + 1}`,
    values: [...baseValues(), f.from, f.to, ...sc.values],
  };
}
export function byTreatmentQuery(scope: AdminScope, f: RangeFilters): Query {
  const sc = scopeClause(scope, f.hospitalId, 'p.hospital_id');
  return {
    sql: `SELECT p.treatment_name AS treatment_name, ${SUMS_SQL} ${FROM_SQL} WHERE ${BASE_SQL} AND ${sc.sql} AND ${PAID_RANGE_SQL}
          GROUP BY p.treatment_name ORDER BY (SUM(p.amount) - SUM(p.refunded_amount)) DESC, p.treatment_name ASC LIMIT ${TABLE_CAP + 1}`,
    values: [...baseValues(), ...sc.values, f.from, f.to],
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
export interface PaymentItem {
  id: number; bookingId: number; hospitalId: number; hospitalName: string; patientName: string; patientPhone: string; treatmentName: string;
  amount: number; refundedAmount: number; status: string; paidAt: string; refundedAt: string; bookingStatus: string; visitDate: string; visitTime: string;
  /** held = real money, treatment not done yet; recognized = treatment done; refunded = fully returned. */
  stage: 'held' | 'recognized' | 'refunded';
  /** Held, but the booking is cancelled / refunded / no-show: the money will not be released by a treatment. */
  needsAttention: boolean;
}
/** 010-1234-5678 -> 010-****-5678; anything else keeps only its last four characters. */
export function maskPhone(v: string): string {
  const t = v.trim();
  const m = /^(\d{2,3})-?(\d{3,4})-?(\d{4})$/.exec(t);
  if (m) return `${m[1]}-****-${m[3]}`;
  return t.length > 4 ? `****${t.slice(-4)}` : '****';
}
export function toItem(r: Record<string, unknown>): PaymentItem {
  const status = s(r.status);
  const booking = s(r.booking_status);
  return {
    id: Number(r.id), bookingId: Number(r.booking_id) || 0, hospitalId: Number(r.hospital_id) || 0, hospitalName: s(r.hospital_name), patientName: s(r.patient_name),
    patientPhone: maskPhone(s(r.patient_phone)), treatmentName: s(r.treatment_name), amount: Number(r.amount) || 0, refundedAmount: Number(r.refunded_amount) || 0, status,
    paidAt: s(r.paid_at), refundedAt: s(r.refunded_at), bookingStatus: booking, visitDate: s(r.booking_date).slice(0, 10), visitTime: s(r.booking_time).slice(0, 5),
    stage: status === 'refunded' ? 'refunded' : booking === 'completed' ? 'recognized' : 'held',
    needsAttention: status !== 'refunded' && (DEAD_BOOKING_STATUSES as readonly string[]).includes(booking),
  };
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
