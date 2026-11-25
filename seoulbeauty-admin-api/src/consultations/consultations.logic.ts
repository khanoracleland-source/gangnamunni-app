import { AdminScope } from '../admin-auth/admin.guard';

export const STATUSES = ['pending', 'approved', 'completed', 'rejected', 'contact_failed'] as const;
export type ConsultationStatus = (typeof STATUSES)[number];

export const PER_PAGE = 20;
export const MAX_PAGE = 100000;
export const SEARCH_MAX = 60;
/** The list shows only the start of the free text; the whole text belongs to the detail. */
export const PREVIEW_MAX = 200;

export interface Fail { error: string; field?: string }
export const isFail = (v: unknown): v is Fail => !!v && typeof v === 'object' && typeof (v as Fail).error === 'string';
export interface Query { sql: string; values: unknown[] }

const s = (v: unknown): string => (v == null ? '' : String(v));
const stripControl = (v: string): string => v.replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim();
const escapeLike = (v: string): string => v.replace(/[\\%_]/g, (c) => '\\' + c);

export function parsePositiveInt(raw: unknown): number | null {
  if (typeof raw === 'number') return Number.isSafeInteger(raw) && raw > 0 ? raw : null;
  const v = s(raw).trim();
  if (!/^[1-9]\d{0,14}$/.test(v)) return null;
  const n = Number(v);
  return Number.isSafeInteger(n) ? n : null;
}

export interface ListFilters { hospitalId: number; status: string; q: string; page: number }

/** The list query string. An unknown status or a malformed number is a 400, never silently ignored. */
export function parseListQuery(raw: Record<string, unknown>): ListFilters | Fail {
  const out: ListFilters = { hospitalId: 0, status: '', q: '', page: 1 };
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
  out.q = stripControl(s(raw.q)).slice(0, SEARCH_MAX);
  if (s(raw.page).trim() !== '') {
    const p = parsePositiveInt(raw.page);
    if (p === null || p > MAX_PAGE) return { error: 'invalid_query', field: 'page' };
    out.page = p;
  }
  return out;
}

/** Hospital part of every query: an 'own' admin only ever sees its hospitals, and an admin without one sees nothing (never "all"). */
export function scopeClause(scope: AdminScope, hospitalId: number, column = 'c.hospital_id'): Query {
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
  if (f.status) { parts.push('c.status = ?'); values.push(f.status); }
  if (f.q) {
    const like = `%${escapeLike(f.q)}%`;
    parts.push("(c.name LIKE ? ESCAPE '\\\\' OR c.phone LIKE ? ESCAPE '\\\\' OR c.hospital_name LIKE ? ESCAPE '\\\\')");
    values.push(like, like, like);
  }
  return { sql: parts.join(' AND '), values };
}

const LIST_COLUMNS = 'c.id, c.hospital_id, c.hospital_name, c.consultation_type, c.name, c.phone, c.message, c.photo_urls, c.status, c.created_at';

/** Newest application first, like the PHP list. */
export function listQuery(scope: AdminScope, f: ListFilters): Query {
  const w = whereOf(scope, f);
  return { sql: `SELECT ${LIST_COLUMNS} FROM gangnamunni_consultations c WHERE ${w.sql} ORDER BY c.created_at DESC, c.id DESC LIMIT ? OFFSET ?`, values: [...w.values, PER_PAGE, (f.page - 1) * PER_PAGE] };
}
export function countQuery(scope: AdminScope, f: ListFilters): Query {
  const w = whereOf(scope, f);
  return { sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_consultations c WHERE ${w.sql}`, values: w.values };
}
/** The counts above the list follow the hospital choice only (not the status or the search), like the PHP page. */
export function statsQuery(scope: AdminScope, hospitalId: number): Query {
  const sc = scopeClause(scope, hospitalId);
  return {
    sql: `SELECT c.status AS status, COUNT(*) AS cnt, MIN(CASE WHEN c.status = 'pending' THEN c.created_at END) AS oldest FROM gangnamunni_consultations c WHERE ${sc.sql} GROUP BY c.status`,
    values: sc.values,
  };
}
/** The hospitals an admin may filter by, with how many applications wait for an answer. */
export function hospitalOptionsQuery(scope: AdminScope): Query {
  const sc = scopeClause(scope, 0, 'h.id');
  return {
    sql: `SELECT h.id, h.name, COUNT(c.id) AS cnt, SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) AS pending_count
          FROM gangnamunni_hospitals h LEFT JOIN gangnamunni_consultations c ON c.hospital_id = h.id
          WHERE ${sc.sql} GROUP BY h.id, h.name ORDER BY h.name ASC, h.id ASC`,
    values: sc.values,
  };
}

const TAG_LINE = /^\[(?:관심 시술|상담 방법|연락 가능 시간|방문 희망 일시)\][^\r\n]*(?:\r\n|\r|\n|$)/u;

/** The application form puts the chosen items in front of the text as "[관심 시술] …" lines. They come out as tags; the rest is the member's own text. */
export function splitMessage(raw: unknown): { tags: string[]; text: string } {
  let rest = s(raw);
  const tags: string[] = [];
  for (let m = TAG_LINE.exec(rest); m; m = TAG_LINE.exec(rest)) {
    tags.push(m[0].trim());
    rest = rest.slice(m[0].length);
  }
  return { tags, text: tags.length ? rest.trimStart() : rest };
}

export function photoCount(raw: unknown): number {
  try {
    const list = JSON.parse(s(raw));
    return Array.isArray(list) ? list.length : 0;
  } catch {
    return 0;
  }
}

export interface ConsultationItem {
  id: number; hospitalId: number; hospitalName: string; type: string; name: string; phone: string;
  tags: string[]; preview: string; photoCount: number; status: string; createdAt: string;
}
export function toItem(r: Record<string, unknown>): ConsultationItem {
  const { tags, text } = splitMessage(r.message);
  return {
    id: Number(r.id), hospitalId: Number(r.hospital_id) || 0, hospitalName: s(r.hospital_name), type: s(r.consultation_type), name: s(r.name), phone: s(r.phone),
    tags, preview: text.length > PREVIEW_MAX ? text.slice(0, PREVIEW_MAX) : text, photoCount: photoCount(r.photo_urls), status: s(r.status), createdAt: s(r.created_at),
  };
}

export interface Stats { total: number; pending: number; approved: number; completed: number; rejected: number; contact_failed: number; oldestPendingAt: string | null }
export function toStats(rows: Record<string, unknown>[]): Stats {
  const out: Stats = { total: 0, pending: 0, approved: 0, completed: 0, rejected: 0, contact_failed: 0, oldestPendingAt: null };
  for (const r of rows) {
    const n = Number(r.cnt) || 0;
    out.total += n;
    const st = s(r.status);
    if ((STATUSES as readonly string[]).includes(st)) out[st as ConsultationStatus] += n;
    if (st === 'pending' && r.oldest != null) out.oldestPendingAt = s(r.oldest);
  }
  return out;
}

// ---- detail and photos ----

const DETAIL_COLUMNS = LIST_COLUMNS;
/** Columns of the later history migration; a database without them still answers the rest. */
const HISTORY_COLUMNS = 'c.hospital_message, c.hospital_message_at, c.callback_slot';

export function parseId(raw: unknown): number | null {
  return parsePositiveInt(raw);
}
export function parsePhotoIndex(raw: unknown): number | null {
  const v = s(raw).trim();
  return /^[0-9]$/.test(v) ? Number(v) : null;
}

const byId = (cols: string, scope: AdminScope, id: number): Query => {
  const sc = scopeClause(scope, 0);
  return { sql: `SELECT ${cols} FROM gangnamunni_consultations c WHERE c.id = ? AND ${sc.sql} LIMIT 1`, values: [id, ...sc.values] };
};
export const detailQuery = (scope: AdminScope, id: number): Query => byId(DETAIL_COLUMNS, scope, id);
export const historyQuery = (scope: AdminScope, id: number): Query => byId(HISTORY_COLUMNS, scope, id);
export const photosQuery = (scope: AdminScope, id: number): Query => byId('c.id, c.photo_urls', scope, id);
/** Called only after the application itself was read inside the admin's scope. */
export const eventsQuery = (id: number): Query => ({
  sql: 'SELECT e.kind, e.created_at FROM gangnamunni_consultation_events e WHERE e.consultation_id = ? ORDER BY e.created_at ASC, e.id ASC LIMIT 100',
  values: [id],
});

export const EVENT_KINDS = ['no_answer', 'rerequest', 'completed'] as const;

export interface ConsultationDetail extends Omit<ConsultationItem, 'preview'> {
  text: string;
  /** false when the history columns / table are not there (older database). */
  historySupported: boolean;
  hospitalMessage: string;
  hospitalMessageAt: string | null;
  callbackSlot: string;
  events: { kind: string; createdAt: string }[];
}

export function toDetail(core: Record<string, unknown>, history: Record<string, unknown> | null, events: Record<string, unknown>[] | null): ConsultationDetail {
  const { tags, text } = splitMessage(core.message);
  return {
    id: Number(core.id), hospitalId: Number(core.hospital_id) || 0, hospitalName: s(core.hospital_name), type: s(core.consultation_type), name: s(core.name), phone: s(core.phone),
    tags, text, photoCount: photoCount(core.photo_urls), status: s(core.status), createdAt: s(core.created_at),
    historySupported: history !== null,
    hospitalMessage: s(history?.hospital_message),
    hospitalMessageAt: history?.hospital_message_at == null ? null : s(history.hospital_message_at),
    callbackSlot: s(history?.callback_slot),
    events: (events ?? []).filter((e) => (EVENT_KINDS as readonly string[]).includes(s(e.kind))).map((e) => ({ kind: s(e.kind), createdAt: s(e.created_at) })),
  };
}

const PHOTO_TYPES: Record<string, string> = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp' };

/** The stored path of one photo, only when it is a plain file name inside this application's own folder; anything else is "no photo". */
export function photoPath(id: number, rawUrls: unknown, index: number): { rel: string; name: string; type: string } | null {
  let list: unknown;
  try { list = JSON.parse(s(rawUrls)); } catch { return null; }
  if (!Array.isArray(list)) return null;
  const rel = s(list[index]);
  const m = new RegExp(`^uploads/consultations/${id}/([A-Za-z0-9_\\-]+\\.(jpg|jpeg|png|gif|webp))$`).exec(rel);
  return m ? { rel, name: m[1], type: PHOTO_TYPES[m[2].toLowerCase()] } : null;
}
