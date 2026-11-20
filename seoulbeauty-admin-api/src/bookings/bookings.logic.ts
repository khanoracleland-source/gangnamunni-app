import { AdminScope } from '../admin-auth/admin.guard';

export const STATUSES = ['pending', 'hospital_checking', 'confirmed', 'completed', 'cancelled', 'refunded', 'no_show'] as const;
export type BookingStatus = (typeof STATUSES)[number];
export const ACTIONS = ['checking', 'confirm', 'cancel', 'complete'] as const;
export type BookingAction = (typeof ACTIONS)[number];

export const PER_PAGE = 20;
export const MAX_PAGE = 100000;
export const SEARCH_MAX = 60;
export const CANCEL_REASON_MAX = 500;
/** One export never holds more rows than this; a wider filter is refused instead of exhausting the server. */
export const EXPORT_MAX_ROWS = 20000;
export const EXPORT_MAX_HOSPITALS = 200;

export const STATUS_LABELS: Record<string, string> = {
  pending: '대기중', hospital_checking: '병원 확인 중', confirmed: '확정', completed: '완료', cancelled: '취소', refunded: '환불완료', no_show: '노쇼',
};

/** The only moves the booking flow allows: one step forward, or a cancellation while the hospital is checking. */
const FLOW: Record<string, Partial<Record<BookingAction, BookingStatus>>> = {
  pending: { checking: 'hospital_checking' },
  hospital_checking: { confirm: 'confirmed', cancel: 'cancelled' },
  confirmed: { complete: 'completed' },
};

export const transitionOf = (status: string, action: BookingAction): BookingStatus | null => FLOW[status]?.[action] ?? null;
export const actionsFor = (status: string): BookingAction[] => Object.keys(FLOW[status] ?? {}) as BookingAction[];
/** Booking status that the action produces, whatever the current status is (used to name the notification event). */
export const targetOf = (action: BookingAction): BookingStatus => ({ checking: 'hospital_checking', confirm: 'confirmed', cancel: 'cancelled', complete: 'completed' } as const)[action];

export interface Fail { error: string; field?: string }
export const isFail = (v: unknown): v is Fail => !!v && typeof v === 'object' && typeof (v as Fail).error === 'string';

const s = (v: unknown): string => (v == null ? '' : String(v));
const stripControl = (v: string): string => v.replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim();

export function parsePositiveInt(raw: unknown): number | null {
  if (typeof raw === 'number') return Number.isSafeInteger(raw) && raw > 0 ? raw : null;
  const v = s(raw).trim();
  if (!/^[1-9]\d{0,14}$/.test(v)) return null;
  const n = Number(v);
  return Number.isSafeInteger(n) ? n : null;
}

/** YYYY-MM-DD that is a real calendar day, else null. */
export function parseDay(raw: unknown): string | null {
  const v = s(raw).trim();
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
  if (!m) return null;
  const d = new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])));
  return d.getUTCFullYear() === Number(m[1]) && d.getUTCMonth() === Number(m[2]) - 1 && d.getUTCDate() === Number(m[3]) ? v : null;
}

export interface ListFilters { hospitalId: number; status: string; from: string; to: string; q: string; page: number }

/** The list query string. An unknown status or a malformed date is a 400, never silently ignored. */
export function parseListQuery(raw: Record<string, unknown>): ListFilters | Fail {
  const out: ListFilters = { hospitalId: 0, status: '', from: '', to: '', q: '', page: 1 };
  if (s(raw.hospital_id).trim() !== '') {
    const id = parsePositiveInt(raw.hospital_id);
    if (id === null) return { error: 'invalid_query', field: 'hospital_id' };
    out.hospitalId = id;
  }
  if (s(raw.status).trim() !== '') {
    const st = s(raw.status).trim();
    if (!(STATUSES as readonly string[]).includes(st)) return { error: 'invalid_query', field: 'status' };
    out.status = st;
  }
  for (const [key, prop] of [['from', 'from'], ['to', 'to']] as const) {
    if (s(raw[key]).trim() === '') continue;
    const d = parseDay(raw[key]);
    if (!d) return { error: 'invalid_query', field: key };
    out[prop] = d;
  }
  if (out.from && out.to && out.from > out.to) return { error: 'invalid_query', field: 'to' };
  out.q = stripControl(s(raw.q)).slice(0, SEARCH_MAX);
  if (s(raw.page).trim() !== '') {
    const p = parsePositiveInt(raw.page);
    if (p === null || p > MAX_PAGE) return { error: 'invalid_query', field: 'page' };
    out.page = p;
  }
  return out;
}

const escapeLike = (v: string): string => v.replace(/[\\%_]/g, (c) => '\\' + c);

export interface Query { sql: string; values: unknown[] }

/** Hospital part of every query: an 'own' admin only ever sees its hospitals, and an admin without one sees nothing (never "all"). */
export function scopeClause(scope: AdminScope, hospitalId: number, column = 'b.hospital_id'): { sql: string; values: unknown[] } {
  const parts: string[] = [];
  const values: unknown[] = [];
  if (scope.scope === 'own') {
    if (!scope.hospitalIds.length) return { sql: '1 = 0', values: [] };
    parts.push(`${column} IN (${scope.hospitalIds.map(() => '?').join(', ')})`);
    values.push(...scope.hospitalIds);
  }
  if (hospitalId > 0) {
    parts.push(`${column} = ?`);
    values.push(hospitalId);
  }
  return { sql: parts.length ? parts.join(' AND ') : '1 = 1', values };
}

function whereOf(scope: AdminScope, f: ListFilters): Query {
  const sc = scopeClause(scope, f.hospitalId);
  const parts = [sc.sql];
  const values = [...sc.values];
  if (f.status) { parts.push('b.status = ?'); values.push(f.status); }
  if (f.from) { parts.push('b.booking_date >= ?'); values.push(f.from); }
  if (f.to) { parts.push('b.booking_date <= ?'); values.push(f.to); }
  if (f.q) {
    const like = `%${escapeLike(f.q)}%`;
    parts.push("(b.name LIKE ? ESCAPE '\\\\' OR b.phone LIKE ? ESCAPE '\\\\' OR b.hospital_name LIKE ? ESCAPE '\\\\')");
    values.push(like, like, like);
  }
  return { sql: parts.join(' AND '), values };
}

const LIST_COLUMNS = 'b.id, b.hospital_id, b.hospital_name, b.name, b.phone, b.treatment, b.booking_date, b.booking_time, b.status, b.doctor_id, b.doctor_name, b.photo_urls, b.created_at';

/** Newest application first (접수일시), like the PHP list. */
export function listQuery(scope: AdminScope, f: ListFilters): Query {
  const w = whereOf(scope, f);
  return { sql: `SELECT ${LIST_COLUMNS} FROM gangnamunni_bookings b WHERE ${w.sql} ORDER BY b.created_at DESC, b.id DESC LIMIT ? OFFSET ?`, values: [...w.values, PER_PAGE, (f.page - 1) * PER_PAGE] };
}
export function countQuery(scope: AdminScope, f: ListFilters): Query {
  const w = whereOf(scope, f);
  return { sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_bookings b WHERE ${w.sql}`, values: w.values };
}
/** The cards above the list follow the hospital choice only (not the status, date or search), like the PHP page. */
export function statsQuery(scope: AdminScope, hospitalId: number): Query {
  const sc = scopeClause(scope, hospitalId);
  return { sql: `SELECT b.status AS status, COUNT(*) AS cnt, SUM(CASE WHEN b.booking_date = CURDATE() THEN 1 ELSE 0 END) AS today FROM gangnamunni_bookings b WHERE ${sc.sql} GROUP BY b.status`, values: sc.values };
}
export function hospitalOptionsQuery(scope: AdminScope): Query {
  const sc = scopeClause(scope, 0, 'h.id');
  return {
    sql: `SELECT h.id, h.name, COUNT(b.id) AS booking_count,
                 SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                 SUM(CASE WHEN b.status = 'hospital_checking' THEN 1 ELSE 0 END) AS checking_count,
                 SUM(CASE WHEN b.booking_date = CURDATE() THEN 1 ELSE 0 END) AS today_count
          FROM gangnamunni_hospitals h LEFT JOIN gangnamunni_bookings b ON b.hospital_id = h.id
          WHERE ${sc.sql} GROUP BY h.id, h.name ORDER BY h.name ASC`,
    values: sc.values,
  };
}
export function detailQuery(scope: AdminScope, id: number): Query {
  const sc = scopeClause(scope, 0);
  return {
    sql: `SELECT b.id, b.hospital_id, b.hospital_name, b.name, b.phone, b.treatment, b.booking_date, b.booking_time, b.status, b.doctor_id, b.doctor_name,
                 b.memo, b.cancel_reason, b.photo_urls, b.created_at FROM gangnamunni_bookings b WHERE b.id = ? AND ${sc.sql} LIMIT 1`,
    values: [id, ...sc.values],
  };
}
export const answersQuery = (id: number): Query => ({ sql: 'SELECT question, answer FROM gangnamunni_booking_answers WHERE booking_id = ? ORDER BY id ASC', values: [id] });
export const doctorsQuery = (hospitalId: number): Query => ({ sql: "SELECT id, name FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active' ORDER BY name ASC, id ASC", values: [hospitalId] });

/** Only addresses a browser may load as an image: http(s) or a path on this site. Anything else (javascript:, data:) is dropped. */
export function parsePhotoUrls(raw: unknown): string[] {
  let list: unknown;
  try { list = JSON.parse(s(raw)); } catch { return []; }
  if (!Array.isArray(list)) return [];
  return list.map((u) => s(u).trim()).filter((u) => u.length > 0 && u.length <= 500 && !u.includes('\\') && /^(https?:\/\/|\/(?!\/))/i.test(u)).slice(0, 10);
}

export interface BookingItem {
  id: number; hospitalId: number; hospitalName: string; name: string; phone: string; treatment: string; bookingDate: string; bookingTime: string;
  status: string; doctorId: number; doctorName: string; photoCount: number; createdAt: string; actions: BookingAction[];
}
export const toItem = (r: Record<string, unknown>): BookingItem => ({
  id: Number(r.id), hospitalId: Number(r.hospital_id) || 0, hospitalName: s(r.hospital_name), name: s(r.name), phone: s(r.phone), treatment: s(r.treatment),
  bookingDate: s(r.booking_date).slice(0, 10), bookingTime: s(r.booking_time).slice(0, 5), status: s(r.status), doctorId: Number(r.doctor_id) || 0,
  doctorName: s(r.doctor_name).trim(), photoCount: parsePhotoUrls(r.photo_urls).length, createdAt: s(r.created_at), actions: actionsFor(s(r.status)),
});

export type Stats = Record<'total' | 'pending' | 'hospital_checking' | 'confirmed' | 'completed' | 'cancelled' | 'refunded' | 'no_show' | 'today', number>;
export function toStats(rows: Record<string, unknown>[]): Stats {
  const out: Stats = { total: 0, pending: 0, hospital_checking: 0, confirmed: 0, completed: 0, cancelled: 0, refunded: 0, no_show: 0, today: 0 };
  for (const r of rows) {
    const n = Number(r.cnt) || 0;
    out.total += n;
    out.today += Number(r.today) || 0;
    const st = s(r.status);
    if (st in out && st !== 'total' && st !== 'today') (out as Record<string, number>)[st] += n;
  }
  return out;
}

export interface ExportFilters { hospitalIds: number[]; status: string; from: string; to: string }
/** Hospitals (empty = every hospital the admin may see), a required date range on the visit day (예약일), and an optional status. */
export function parseExportQuery(raw: Record<string, unknown>): ExportFilters | Fail {
  const out: ExportFilters = { hospitalIds: [], status: '', from: '', to: '' };
  const ids = s(raw.hospital_ids).trim();
  if (ids !== '') {
    const parts = ids.split(',').map((p) => p.trim());
    if (parts.length > EXPORT_MAX_HOSPITALS) return { error: 'invalid_query', field: 'hospital_ids' };
    for (const p of parts) {
      const id = parsePositiveInt(p);
      if (id === null) return { error: 'invalid_query', field: 'hospital_ids' };
      if (!out.hospitalIds.includes(id)) out.hospitalIds.push(id);
    }
  }
  const from = parseDay(raw.from);
  const to = parseDay(raw.to);
  if (!from) return { error: 'invalid_query', field: 'from' };
  if (!to) return { error: 'invalid_query', field: 'to' };
  if (from > to) return { error: 'invalid_query', field: 'to' };
  out.from = from;
  out.to = to;
  if (s(raw.status).trim() !== '') {
    const st = s(raw.status).trim();
    if (!(STATUSES as readonly string[]).includes(st)) return { error: 'invalid_query', field: 'status' };
    out.status = st;
  }
  return out;
}

function exportWhere(scope: AdminScope, f: ExportFilters): Query {
  const sc = scopeClause(scope, 0);
  const parts = [sc.sql, 'b.booking_date >= ?', 'b.booking_date <= ?'];
  const values: unknown[] = [...sc.values, f.from, f.to];
  if (f.hospitalIds.length) { parts.push(`b.hospital_id IN (${f.hospitalIds.map(() => '?').join(', ')})`); values.push(...f.hospitalIds); }
  if (f.status) { parts.push('b.status = ?'); values.push(f.status); }
  return { sql: parts.join(' AND '), values };
}
export function exportQuery(scope: AdminScope, f: ExportFilters): Query {
  const w = exportWhere(scope, f);
  return {
    sql: `SELECT b.id, b.hospital_id, b.hospital_name, b.name, b.phone, b.treatment, b.booking_date, b.booking_time, b.status, b.doctor_name, b.price, b.memo, b.cancel_reason, b.created_at
          FROM gangnamunni_bookings b WHERE ${w.sql} ORDER BY b.hospital_name ASC, b.created_at DESC, b.id DESC LIMIT ${EXPORT_MAX_ROWS + 1}`,
    values: w.values,
  };
}

export const EXPORT_HEADERS = ['예약 ID', '병원', '예약자', '전화번호', '시술', '예약일', '예약시간', '상태', '담당의사', '금액', '메모', '취소 사유', '접수일시'];
export const exportRow = (r: Record<string, unknown>): (string | number)[] => [
  Number(r.id), s(r.hospital_name), s(r.name), s(r.phone), s(r.treatment), s(r.booking_date).slice(0, 10), s(r.booking_time).slice(0, 5),
  STATUS_LABELS[s(r.status)] ?? s(r.status), s(r.doctor_name), Number(r.price) || 0, s(r.memo), s(r.cancel_reason), s(r.created_at),
];

/** One sheet per hospital, named by the hospital: Excel forbids []:*?/\ and more than 31 characters, and two sheets may not share a name. */
export function sheetName(raw: string, hospitalId: number, taken: Set<string>): string {
  let base = stripControl(raw).replace(/[\[\]:*?/\\]/g, ' ').replace(/^'+|'+$/g, '').replace(/\s+/g, ' ').trim().slice(0, 31) || `병원#${hospitalId}`;
  let name = base;
  let n = 2;
  while (taken.has(name.toLowerCase())) {
    const suffix = `#${hospitalId}${n > 2 ? `-${n}` : ''}`;
    name = base.slice(0, 31 - suffix.length) + suffix;
    n++;
  }
  taken.add(name.toLowerCase());
  return name;
}

export function exportFileName(f: ExportFilters): string {
  return `bookings_${f.from.replace(/-/g, '')}_${f.to.replace(/-/g, '')}.xlsx`;
}

export interface StatusBody { action: BookingAction; cancelReason: string }
export function parseStatusBody(body: unknown): StatusBody | Fail {
  if (!body || typeof body !== 'object' || Array.isArray(body)) return { error: 'invalid_body' };
  const b = body as Record<string, unknown>;
  const action = s(b.action);
  if (!(ACTIONS as readonly string[]).includes(action)) return { error: 'invalid_body', field: 'action' };
  const reason = b.cancelReason == null ? '' : typeof b.cancelReason === 'string' ? b.cancelReason : null;
  if (reason === null) return { error: 'invalid_body', field: 'cancelReason' };
  const cleaned = reason.replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/g, '').trim();
  if (Array.from(cleaned).length > CANCEL_REASON_MAX) return { error: 'invalid_body', field: 'cancelReason' };
  return { action: action as BookingAction, cancelReason: action === 'cancel' ? cleaned : '' };
}

/** doctorId 0 clears the assignment. */
export function parseAssignBody(body: unknown): { doctorId: number } | Fail {
  if (!body || typeof body !== 'object' || Array.isArray(body)) return { error: 'invalid_body' };
  const raw = (body as Record<string, unknown>).doctorId;
  if (raw === 0 || raw === '0') return { doctorId: 0 };
  const id = parsePositiveInt(raw);
  return id === null ? { error: 'invalid_body', field: 'doctorId' } : { doctorId: id };
}

/** Payment deadline in hours from 사이트 설정; anything outside 1..168 reads as the default 24. */
export function deadlineHours(raw: unknown): number {
  const n = Number(raw);
  return Number.isInteger(n) && n >= 1 && n <= 168 ? n : 24;
}

/** BK_<KST time>_<booking id>_<12 hex>, the same shape as the PHP order. */
export function merchantUid(bookingId: number, hex: string, now: Date = new Date()): string {
  const kst = new Date(now.getTime() + 9 * 3600 * 1000).toISOString().replace(/\D/g, '').slice(0, 14);
  return `BK_${kst}_${bookingId}_${hex}`;
}
