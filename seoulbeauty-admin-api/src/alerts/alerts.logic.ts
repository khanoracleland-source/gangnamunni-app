import { Query } from '../hospitals/hospitals.logic';

/** The admin inbox (table gangnamunni_notifications, one row per admin): types, tabs, filters and the SQL, all parametrised. */
export const ALERT_TYPES = [
  'hospital_request', 'ad_request', 'review_new', 'consultation_new', 'appointment_new', 'member_join', 'mail_incoming',
  'review_pending', 'hospital_pending', 'ad_pending',
  'test_fail', 'login_fail', 'maintenance_toggle', 'admin_created', 'ai_key_error',
] as const;
export type AlertType = (typeof ALERT_TYPES)[number];

export const TABS = ['all', 'activity', 'pending', 'system'] as const;
export type AlertTab = (typeof TABS)[number];

/** The types of each tab; "all" has no list (every row of the admin). */
export const TAB_TYPES: Record<Exclude<AlertTab, 'all'>, readonly AlertType[]> = {
  activity: ['hospital_request', 'ad_request', 'review_new', 'consultation_new', 'appointment_new', 'member_join', 'mail_incoming'],
  pending: ['review_pending', 'hospital_pending', 'ad_pending'],
  system: ['test_fail', 'login_fail', 'maintenance_toggle', 'admin_created', 'ai_key_error'],
};

export const PER_PAGE = 20;
export const DEFAULT_PURGE_DAYS = 30;
export const MAX_PURGE_DAYS = 3650;

export const tabOf = (raw: unknown): AlertTab => (TABS as readonly string[]).includes(raw as string) ? (raw as AlertTab) : 'all';
export const typeOf = (raw: unknown): AlertType | '' => (ALERT_TYPES as readonly string[]).includes(raw as string) ? (raw as AlertType) : '';
export const pageOf = (raw: unknown): number => { const n = Number(typeof raw === 'string' ? raw : ''); return Number.isInteger(n) && n >= 1 ? Math.min(n, 100_000) : 1; };
export const idOf = (raw: unknown): number => (typeof raw === 'string' && /^[1-9]\d{0,9}$/.test(raw) ? Number(raw) : 0);

/** A real calendar day as YYYY-MM-DD, or ''. */
export function dayOf(raw: unknown): string {
  if (typeof raw !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(raw)) return '';
  const d = new Date(`${raw}T00:00:00Z`);
  return Number.isNaN(d.getTime()) || d.toISOString().slice(0, 10) !== raw ? '' : raw;
}

/** Days to keep read alerts for: a whole number from 1 to 3650; anything else is invalid (null). */
export function purgeDaysOf(raw: unknown): number | null {
  if (raw === undefined || raw === null || raw === '') return DEFAULT_PURGE_DAYS;
  const n = typeof raw === 'number' ? raw : typeof raw === 'string' && /^\d{1,4}$/.test(raw) ? Number(raw) : NaN;
  return Number.isInteger(n) && n >= 1 && n <= MAX_PURGE_DAYS ? n : null;
}

/** A link that may be followed from the inbox: a relative path only (no scheme, no "//", no backslash or control characters, not "#"). */
export function safeLink(raw: unknown): string {
  if (typeof raw !== 'string') return '';
  const s = raw.trim();
  if (s === '' || s === '#' || s.length > 500) return '';
  if (/[\u0000-\u001f\u007f\\]/.test(s)) return '';
  if (s.startsWith('//') || /^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(s)) return '';
  // dot segments and encoded dots/slashes in the path can fold into "//host" in a browser ("/.//host", "/%2e//host")
  const path = s.split(/[?#]/, 1)[0];
  if (/(^|\/)\.{1,2}(\/|$)/.test(path) || /%2e|%2f|%5c/i.test(path)) return '';
  return s;
}

export interface Filter { tab: AlertTab; type: AlertType | ''; from: string; to: string }

export const filterOf = (q: Record<string, unknown>): Filter => ({ tab: tabOf(q.tab), type: typeOf(q.type), from: dayOf(q.from), to: dayOf(q.to) });

function where(adminId: number, f: Filter): { sql: string; values: unknown[] } {
  const parts = ['admin_id = ?'];
  const values: unknown[] = [adminId];
  if (f.tab !== 'all') { parts.push(`type IN (${TAB_TYPES[f.tab].map(() => '?').join(', ')})`); values.push(...TAB_TYPES[f.tab]); }
  if (f.type) { parts.push('type = ?'); values.push(f.type); }
  if (f.from) { parts.push('created_at >= ?'); values.push(`${f.from} 00:00:00`); }
  if (f.to) { parts.push('created_at < DATE_ADD(?, INTERVAL 1 DAY)'); values.push(`${f.to} 00:00:00`); }
  return { sql: parts.join(' AND '), values };
}

export function listQuery(adminId: number, f: Filter, page: number): Query {
  const w = where(adminId, f);
  return {
    sql: `SELECT id, type, title, message, link, icon, is_read, created_at FROM gangnamunni_notifications WHERE ${w.sql} ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?`,
    values: [...w.values, PER_PAGE, (page - 1) * PER_PAGE],
  };
}

export function totalQuery(adminId: number, f: Filter): Query {
  const w = where(adminId, f);
  return { sql: `SELECT COUNT(*) AS n FROM gangnamunni_notifications WHERE ${w.sql}`, values: w.values };
}

/** Unread per tab, total and read counts of the admin (one pass). */
export function countsQuery(adminId: number): Query {
  const inList = (t: readonly string[]) => `type IN (${t.map(() => '?').join(', ')})`;
  const sum = (t: readonly string[]) => `COALESCE(SUM(is_read = 0 AND ${inList(t)}), 0)`;
  return {
    sql: `SELECT COUNT(*) AS total, COALESCE(SUM(is_read = 0), 0) AS unread, ${sum(TAB_TYPES.activity)} AS u_activity, ${sum(TAB_TYPES.pending)} AS u_pending, ${sum(TAB_TYPES.system)} AS u_system FROM gangnamunni_notifications WHERE admin_id = ?`,
    values: [...TAB_TYPES.activity, ...TAB_TYPES.pending, ...TAB_TYPES.system, adminId],
  };
}

export const unreadQuery = (adminId: number): Query => ({ sql: 'SELECT COUNT(*) AS n FROM gangnamunni_notifications WHERE admin_id = ? AND is_read = 0', values: [adminId] });

export const markReadQuery = (adminId: number, id: number): Query => ({ sql: 'UPDATE gangnamunni_notifications SET is_read = 1 WHERE id = ? AND admin_id = ? AND is_read = 0', values: [id, adminId] });

export function markTabQuery(adminId: number, tab: AlertTab): Query {
  if (tab === 'all') return { sql: 'UPDATE gangnamunni_notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0', values: [adminId] };
  return { sql: `UPDATE gangnamunni_notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0 AND type IN (${TAB_TYPES[tab].map(() => '?').join(', ')})`, values: [adminId, ...TAB_TYPES[tab]] };
}

export const purgeQuery = (adminId: number, days: number): Query => ({ sql: 'DELETE FROM gangnamunni_notifications WHERE admin_id = ? AND is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', values: [adminId, days] });
