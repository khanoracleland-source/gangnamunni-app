/** Pure rules of booking.php (hours, schedule, age, revisit, duplicate, no-show). No I/O: everything is passed in. */
import { kstNow } from '../bookings/bookings.logic';

export const BOOKING_WORD = '예약';
const DAY_KEYS = ['일', '월', '화', '수', '목', '금', '토'];
const OFFICE_KEYS = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
const RANGE = /^(\d{1,2}):(\d{2})\s*[~\-]\s*(\d{1,2}):(\d{2})$/u;
const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

export interface DayHours { open: number; close: number; biweekly: 'even' | 'odd' | null }
export interface Hours {
  weekly: (DayHours | null)[];
  hasWeekly: boolean;
  specialClosed: string[];
  specialOpen: Record<string, { open: number; close: number }>;
  noLunch: boolean;
  lunchStart: string;
  lunchEnd: string;
  lunchStartMin: number | null;
  lunchEndMin: number | null;
}

const str = (v: unknown) => (v == null ? '' : String(v)).trim();
const hm = (a: string, b: string) => Number(a) * 60 + Number(b);

/** gangnamunni_hospitals.business_hours (JSON text) -> the structure booking.php builds at the top of the file. */
export function parseHours(raw: unknown): Hours {
  let bh: any = {};
  try { bh = typeof raw === 'string' ? JSON.parse(raw || '{}') : raw ?? {}; } catch { bh = {}; }
  if (!bh || typeof bh !== 'object' || Array.isArray(bh)) bh = {};
  const specialClosed = (Array.isArray(bh.special_closed_dates) ? bh.special_closed_dates : []).filter((d: unknown) => typeof d === 'string' && DATE_RE.test(d));
  const specialOpen: Hours['specialOpen'] = {};
  const so = bh.special_open_dates && typeof bh.special_open_dates === 'object' && !Array.isArray(bh.special_open_dates) ? bh.special_open_dates : {};
  for (const [d, range] of Object.entries(so)) {
    if (!DATE_RE.test(d)) continue;
    const m = RANGE.exec(str(range));
    if (!m) continue;
    const open = hm(m[1], m[2]), close = hm(m[3], m[4]);
    if (open < close) specialOpen[d] = { open, close };
  }
  const weekly: (DayHours | null)[] = [];
  let hasWeekly = false;
  for (let i = 0; i < 7; i++) {
    let v: unknown = bh[DAY_KEYS[i]] ?? null;
    if (v === null && bh.officeHours && typeof bh.officeHours === 'object') {
      v = bh.officeHours[OFFICE_KEYS[i]] ?? bh.officeHours[OFFICE_KEYS[i].toLowerCase()] ?? null;
    }
    if (v !== null) hasWeekly = true;
    let text = str(v);
    let biweekly: 'even' | 'odd' | null = null;
    const bm = /^(.*)#biweekly:(even|odd)$/.exec(text);
    if (bm) { text = bm[1].trim(); biweekly = bm[2] as 'even' | 'odd'; }
    const m = RANGE.exec(text);
    if (m) {
      const open = hm(m[1], m[2]), close = hm(m[3], m[4]);
      weekly.push(open < close ? { open, close, biweekly } : null);
    } else weekly.push(null);
  }
  const noLunch = !!bh.no_lunch_break;
  let lunchStart = '', lunchEnd = '';
  if (!noLunch) {
    const lv = str(bh['점심'] ?? bh['점심시간'] ?? '');
    const lm = /^(\d{1,2}:\d{2})\s*[~\-]\s*(\d{1,2}:\d{2})$/u.exec(lv);
    if (lm) { lunchStart = lm[1]; lunchEnd = lm[2]; } else { lunchStart = str(bh.lunch_start ?? bh['점심시작'] ?? ''); lunchEnd = str(bh.lunch_end ?? bh['점심종료'] ?? ''); }
  }
  const mins = (v: string) => { const m = /^(\d{1,2}):(\d{2})$/.exec(v); return m ? hm(m[1], m[2]) : null; };
  return { weekly, hasWeekly, specialClosed, specialOpen, noLunch, lunchStart, lunchEnd, lunchStartMin: mins(lunchStart), lunchEndMin: mins(lunchEnd) };
}

/** Which occurrence of its weekday (0-based, resets every month) a date is: even/odd. */
export const monthParity = (day: number): 'even' | 'odd' => (Math.floor((day - 1) / 7) % 2 === 0 ? 'even' : 'odd');

const utcDate = (iso: string) => new Date(`${iso}T00:00:00Z`);
export function validDate(iso: string): boolean {
  if (!DATE_RE.test(iso)) return false;
  const d = utcDate(iso);
  return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === iso;
}
export const dayOfWeek = (iso: string) => utcDate(iso).getUTCDay();
export const addDays = (iso: string, n: number) => new Date(utcDate(iso).getTime() + n * 86400000).toISOString().slice(0, 10);
export const daysBetween = (a: string, b: string) => Math.round((utcDate(b).getTime() - utcDate(a).getTime()) / 86400000);
/** '+1 year' like PHP's DateTimeImmutable (Feb 29 overflows to Mar 1). */
export function plusOneYear(iso: string): string {
  const d = utcDate(iso); d.setUTCFullYear(d.getUTCFullYear() + 1); return d.toISOString().slice(0, 10);
}

export function validTimeFormat(t: string): boolean { return /^(?:[01]\d|2[0-3]):(?:00|30)$/.test(t); }

/**
 * The date/time part of the POST validation chain, in the same order and with the same messages.
 * `now` is a KST 'YYYY-MM-DD HH:MM:SS'. Returns the error text or null.
 */
export function scheduleError(h: Hours, date: string, time: string, now = kstNow()): string | null {
  if (!validDate(date) || date > plusOneYear(now.slice(0, 10))) return '예약 날짜를 확인해주세요.';
  if (!validTimeFormat(time)) return '선택할 수 없는 예약 시간입니다.';
  const tm = hm(time.slice(0, 2), time.slice(3, 5));
  const notHours = '선택한 시간은 병원 진료시간이 아닙니다. 다른 시간을 선택해주세요.';
  const closed = '선택한 날짜는 휴무일입니다. 다른 날짜를 선택해주세요.';
  const dow = dayOfWeek(date);
  if (!h.hasWeekly && (tm < 540 || tm > 1080)) return '선택할 수 없는 예약 시간입니다.';
  if (h.specialClosed.includes(date)) return '선택한 날짜는 임시 휴무일입니다. 다른 날짜를 선택해주세요.';
  const so = h.specialOpen[date];
  if (so) {
    // booking.php stops at the special-open branch (no weekly/lunch checks); a past time is still refused here.
    if (tm < so.open || tm >= so.close) return notHours;
  } else if (h.hasWeekly) {
    const day = h.weekly[dow];
    if (!day) return closed;
    if (day.biweekly && day.biweekly !== monthParity(Number(date.slice(8, 10)))) return closed;
    if (tm < day.open || tm >= day.close) return notHours;
    if (!h.noLunch && h.lunchStartMin !== null && h.lunchEndMin !== null && tm >= h.lunchStartMin && tm < h.lunchEndMin) {
      return `점심시간에는 ${BOOKING_WORD}할 수 없습니다. 다른 시간을 선택해주세요.`;
    }
  } else if (!h.noLunch && h.lunchStartMin !== null && h.lunchEndMin !== null && tm >= h.lunchStartMin && tm < h.lunchEndMin) {
    return `점심시간에는 ${BOOKING_WORD}할 수 없습니다. 다른 시간을 선택해주세요.`;
  }
  if (`${date} ${time}:00` < now) return `지난 시간은 ${BOOKING_WORD}할 수 없습니다. 다른 시간을 선택해주세요.`;
  return null;
}

/** Whole years between a Gnuboard mb_birth ('YYYYMMDD' or with separators) and today; null when unknown or invalid. */
export function memberAge(birth: unknown, today = kstNow().slice(0, 10)): number | null {
  const digits = str(birth).replace(/\D+/g, '');
  if (digits.length !== 8 || digits === '00000000') return null;
  const iso = `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6, 8)}`;
  if (!validDate(iso) || iso > today) return null;
  let age = Number(today.slice(0, 4)) - Number(iso.slice(0, 4));
  if (today.slice(5) < iso.slice(5)) age--;
  return age;
}

export function intervalLabel(days: number): string {
  return days > 0 && days % 30 === 0 ? `${days / 30}개월` : `${days}일`;
}

const WD = ['일', '월', '화', '수', '목', '금', '토'];
export const monthDay = (iso: string) => `${Number(iso.slice(5, 7))}월 ${Number(iso.slice(8, 10))}일`;

export interface RevisitRule { name: string; days: number; strict: boolean }
export interface RevisitInfo { treatment: string; interval_label: string; days_since: number; available_from: string }

/** Names of treatments done in a past booking: treatments_json names, or (old rows) the names found inside the text. */
export function bookingNames(treatmentsJson: unknown): string[] {
  let items: any = null;
  try { items = JSON.parse(str(treatmentsJson)); } catch { items = null; }
  return Array.isArray(items) ? items.filter((i) => i && typeof i === 'object' && i.name).map((i) => str(i.name)) : [];
}

/** booking_revisit_status(): `done` = completed bookings newest first (booking_date, treatment, treatments_json). */
export function revisitStatus(rules: RevisitRule[], done: { booking_date: string; treatment: string; treatments_json: unknown }[], today = kstNow().slice(0, 10)) {
  const out: { strict: RevisitInfo | null; warn: RevisitInfo | null } = { strict: null, warn: null };
  if (!rules.length) return out;
  const last: Record<string, string> = {};
  for (const row of done) {
    const names = bookingNames(row.treatments_json);
    for (const r of rules) {
      if (last[r.name]) continue;
      const hit = names.includes(r.name) || (!names.length && r.name !== '' && str(row.treatment).includes(r.name));
      if (hit) last[r.name] = String(row.booking_date).slice(0, 10);
    }
  }
  let strictLatest = '';
  for (const r of rules) {
    const ld = last[r.name];
    if (!ld || !validDate(ld)) continue;
    const since = daysBetween(ld, today);
    if (since >= r.days) continue;
    const avail = addDays(ld, r.days);
    const info: RevisitInfo = { treatment: r.name, interval_label: intervalLabel(r.days), days_since: since, available_from: monthDay(avail) };
    if (r.strict) { if (!strictLatest || avail > strictLatest) { strictLatest = avail; out.strict = info; } }
    else if (!out.warn) out.warn = info;
  }
  return out;
}

const STATUS_LABEL: Record<string, string> = { pending: '예약 요청 접수', hospital_checking: '병원 확인 중', confirmed: '예약 확정' };
export interface ActiveRow { id: number; hospital_name: string; treatment: string; treatments_json: unknown; booking_date: string; booking_time: string; status: string }

/** booking_find_active_same_treatment(): first not-yet-passed active booking that shares a treatment name. */
export function findActiveSame(rows: ActiveRow[], names: string[], now = kstNow()) {
  if (!names.length) return null;
  for (const row of rows) {
    const date = String(row.booking_date).slice(0, 10);
    const time = String(row.booking_time).slice(0, 5);
    if (`${date} ${time}:00` <= now) continue;
    const existing = bookingNames(row.treatments_json);
    let same = names.some((n) => existing.includes(n));
    if (!same && !existing.length) same = names.some((n) => n !== '' && str(row.treatment).includes(n));
    if (!same) continue;
    const h = Number(time.slice(0, 2));
    return {
      id: Number(row.id),
      treatment: str(row.treatment).slice(0, 100),
      hospital_name: str(row.hospital_name),
      schedule: `${monthDay(date)} (${WD[dayOfWeek(date)]}) ${h < 12 ? '오전' : '오후'} ${h % 12 || 12}:${time.slice(3, 5)}`,
      status: STATUS_LABEL[row.status] ?? '',
    };
  }
  return null;
}

/** First day the member may book again: the Nth newest event leaves the `months` window (end-of-month corrected like SQL). */
export function noshowAvailableFrom(pivot: string, months: number): string | null {
  if (!validDate(pivot)) return null;
  const [y, m, d] = pivot.split('-').map(Number);
  const t = new Date(Date.UTC(y, m - 1 + months, d));
  if (t.getUTCDate() < d) return new Date(Date.UTC(y, m - 1 + months + 1, 1)).toISOString().slice(0, 10);
  return t.toISOString().slice(0, 10);
}

export interface NoshowRow { booking_date: string; booking_time: string; hospital_name: string; status: string; ev: string }
export function noshowStatus(limit: number, months: number, withCancel: boolean, rows: NoshowRow[]) {
  if (rows.length < limit) return null;
  const avail = noshowAvailableFrom(String(rows[limit - 1].ev).slice(0, 10), months);
  if (!avail) return null;
  let hasCancel = false;
  const items = rows.slice(0, 5).filter((r) => validDate(String(r.booking_date).slice(0, 10))).map((r) => {
    const d = String(r.booking_date).slice(0, 10);
    const kind = r.status === 'no_show' ? 'no_show' : 'cancel';
    if (kind === 'cancel') hasCancel = true;
    return { when: `${d.slice(5, 7)}.${d.slice(8, 10)} (${WD[dayOfWeek(d)]}) ${String(r.booking_time).slice(0, 5)}`, hospital: str(r.hospital_name), kind };
  });
  return { count: rows.length, months, available_from: monthDay(avail), has_cancel: hasCancel || withCancel, items };
}
