export interface Query {
  sql: string;
  values: unknown[];
}
type Row = Record<string, unknown>;

/** An account that has been switched off for this many days may be deleted for good (never earlier, never while active). */
export const DELETE_AFTER_DAYS = 1;
/** Shortest password the new panel accepts (the old PHP page accepts 6). */
export const MIN_PASSWORD_LENGTH = 8;
/** The only roles this page hands out; older roles are shown as they are but cannot be picked. */
export const ASSIGNABLE_ROLES = ['super_admin', 'hospital_admin'] as const;
/** Same keys as $NOTIFICATION_TYPES of the PHP panel; the labels live in the web app. */
export const NOTIFICATION_TYPES = [
  'hospital_request', 'ad_request', 'review_new', 'consultation_new', 'appointment_new', 'member_join', 'mail_incoming',
  'review_pending', 'hospital_pending', 'ad_pending', 'test_fail', 'login_fail', 'maintenance_toggle', 'admin_created', 'ai_key_error',
] as const;
/** What an account without its own choice receives ($DEFAULT_NOTIFICATIONS of the PHP panel). */
export const DEFAULT_NOTIFICATIONS: readonly string[] = [
  'hospital_request', 'ad_request', 'review_new', 'consultation_new', 'appointment_new', 'review_pending', 'hospital_pending', 'ad_pending',
  'test_fail', 'admin_created',
];
const MAX_ROWS = 500;

/** Who is asking: a super admin sees every account, anybody else only their own. */
export interface Viewer {
  id: number;
  superAdmin: boolean;
}

export interface AdminItem {
  id: number;
  username: string;
  name: string;
  email: string;
  phone: string;
  role: string;
  hospitalId: number | null;
  hospitalName: string;
  isActive: boolean;
  isSelf: boolean;
  lastLoginAt: string | null;
  /** Linked member (SSO). Super admin only: null for everybody else. */
  ssoMemberId: string | null;
  /** The notification types the account receives (its own choice, or the defaults). */
  notifications: string[];
  notificationsCustom: boolean;
  /** When the account was switched off; null while active, and for accounts switched off before this was recorded. */
  deactivatedAt: string | null;
  inactiveDays: number | null;
  /** Inactive for at least DELETE_AFTER_DAYS days: only then may it be deleted. */
  deleteReady: boolean;
}

export interface AdminList {
  scope: 'all' | 'self';
  deleteAfterDays: number;
  total: number;
  items: AdminItem[];
}

export interface AdminOptions {
  roles: string[];
  hospitals: { id: number; name: string }[];
  notificationTypes: string[];
  defaultNotifications: string[];
  minPasswordLength: number;
  deleteAfterDays: number;
}

export const num = (v: unknown): number => {
  const x = Number(v);
  return Number.isFinite(x) ? x : 0;
};
export const str = (v: unknown): string => (v === null || v === undefined ? '' : String(v));
const strOrNull = (v: unknown): string | null => (v === null || v === undefined || v === '' ? null : String(v));

/** "12" -> 12; anything that is not a plain positive integer -> 0 (the caller answers 404). */
export function parseAdminId(raw: unknown): number {
  const s = typeof raw === 'string' ? raw : '';
  if (!/^[1-9]\d{0,9}$/.test(s)) return 0;
  const n = Number(s);
  return Number.isSafeInteger(n) ? n : 0;
}

/** The password and the session counter are never selected. The member link (g5_mb_id) is asked apart: it is added by a migration. */
const COLUMNS = 'id, username, name, email, phone, role, hospital_id, hospital_name, is_active, last_login_at, permissions';

export const listQuery = (v: Viewer): Query =>
  v.superAdmin
    ? { sql: `SELECT ${COLUMNS} FROM gangnamunni_admins ORDER BY role ASC, id ASC LIMIT ${MAX_ROWS}`, values: [] }
    : { sql: `SELECT ${COLUMNS} FROM gangnamunni_admins WHERE id = ? LIMIT 1`, values: [v.id] };

export const detailQuery = (id: number): Query => ({ sql: `SELECT ${COLUMNS} FROM gangnamunni_admins WHERE id = ? LIMIT 1`, values: [id] });

/**
 * When the accounts were switched off. Asked apart because the column is added by a migration: while it is missing the
 * answer is simply "no record" and the page still works.
 */
export const inactivityQuery = (ids: number[]): Query => ({
  sql:
    'SELECT id, deactivated_at, TIMESTAMPDIFF(DAY, deactivated_at, NOW()) AS inactive_days, ' +
    '(deactivated_at IS NOT NULL AND deactivated_at <= NOW() - INTERVAL ? DAY) AS ready FROM gangnamunni_admins WHERE id IN (?)',
  values: [DELETE_AFTER_DAYS, ids],
});

/** Linked members (SSO), super admin only. Optional like the inactivity marks: without the column nobody has a link. */
export const ssoQuery = (ids: number[]): Query => ({ sql: 'SELECT id, g5_mb_id FROM gangnamunni_admins WHERE id IN (?)', values: [ids] });

export const hospitalOptionsQuery = (): Query => ({ sql: 'SELECT id, name FROM gangnamunni_hospitals ORDER BY name ASC, id ASC', values: [] });

/** permissions is JSON text; only a `notifications` list of known types counts. No such list = the defaults. */
export function readNotifications(raw: unknown): { list: string[]; custom: boolean } {
  let parsed: unknown = null;
  try {
    parsed = typeof raw === 'string' && raw !== '' ? JSON.parse(raw) : null;
  } catch {
    parsed = null;
  }
  const own = parsed && typeof parsed === 'object' ? (parsed as Row).notifications : undefined;
  if (!Array.isArray(own)) return { list: [...DEFAULT_NOTIFICATIONS], custom: false };
  const known = new Set<string>(NOTIFICATION_TYPES);
  return { list: NOTIFICATION_TYPES.filter((t) => own.includes(t) && known.has(t)), custom: true };
}

export function toItem(row: Row, viewer: Viewer, inactivity?: Row, sso?: Row): AdminItem {
  const id = num(row.id);
  const isActive = num(row.is_active) === 1;
  const n = readNotifications(row.permissions);
  const at = !isActive && inactivity ? strOrNull(inactivity.deactivated_at) : null;
  const hid = num(row.hospital_id);
  return {
    id,
    username: str(row.username),
    name: str(row.name),
    email: str(row.email),
    phone: str(row.phone),
    role: str(row.role),
    hospitalId: hid > 0 ? hid : null,
    hospitalName: str(row.hospital_name),
    isActive,
    isSelf: id === viewer.id,
    lastLoginAt: strOrNull(row.last_login_at),
    ssoMemberId: viewer.superAdmin && sso ? strOrNull(sso.g5_mb_id) : null,
    notifications: n.list,
    notificationsCustom: n.custom,
    deactivatedAt: at,
    inactiveDays: at && inactivity ? Math.max(0, num(inactivity.inactive_days)) : null,
    deleteReady: !isActive && at !== null && inactivity !== undefined && num(inactivity.ready) === 1,
  };
}

export function buildOptions(viewer: Viewer, hospitals: Row[]): AdminOptions {
  return {
    roles: viewer.superAdmin ? [...ASSIGNABLE_ROLES] : [],
    hospitals: viewer.superAdmin ? hospitals.map((h) => ({ id: num(h.id), name: str(h.name) })) : [],
    notificationTypes: [...NOTIFICATION_TYPES],
    defaultNotifications: [...DEFAULT_NOTIFICATIONS],
    minPasswordLength: MIN_PASSWORD_LENGTH,
    deleteAfterDays: DELETE_AFTER_DAYS,
  };
}
