import { ASSIGNABLE_ROLES, DEFAULT_NOTIFICATIONS, MIN_PASSWORD_LENGTH, NOTIFICATION_TYPES } from './admins.logic';

export const MAX_NAME_LENGTH = 100;
export const MAX_PHONE_LENGTH = 30;

/** What an edit may change here: the account's own name, phone and notification choice. */
export interface EditPlan {
  name?: string;
  phone?: string;
  notifications?: string[];
  /** Super admin only. */
  role?: string;
  /** Super admin only; null clears it (a super admin has no hospital). */
  hospitalId?: number | null;
  /** Lower-cased; an empty text clears it. */
  email?: string;
  newPassword?: string;
  /** Not a change: proof asked from anybody who changes their own email or password (a super admin is not asked). */
  currentPassword?: string;
}
export type EditResult = { ok: true; plan: EditPlan } | { ok: false; errors: Record<string, string> };

const ALLOWED = ['name', 'phone', 'notifications', 'email', 'newPassword', 'currentPassword'];
const ALLOWED_SUPER = [...ALLOWED, 'role', 'hospitalId'];
// eslint-disable-next-line no-control-regex
const CONTROL = /[\u0000-\u001f\u007f]/;

export const USERNAME_PATTERN = /^[a-zA-Z0-9_]{3,30}$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
/** bcrypt only reads the first 72 bytes of a password; longer ones are refused instead of being silently cut. */
export const MAX_PASSWORD_BYTES = 72;
export const MAX_EMAIL_LENGTH = 254;

/** Why a new password is refused, or null when it is fine. */
export function passwordError(v: unknown): string | null {
  if (typeof v !== 'string') return 'bad_format';
  if (v === '') return 'required';
  if (v.includes('\u0000')) return 'bad_format';
  if ([...v].length < MIN_PASSWORD_LENGTH) return 'too_short';
  if (Buffer.byteLength(v, 'utf8') > MAX_PASSWORD_BYTES) return 'too_long';
  return null;
}

/** An email as stored: trimmed and lower-cased; '' (none) is allowed. null when it is not an email. */
export function cleanEmail(v: unknown): string | null {
  if (typeof v !== 'string') return null;
  const e = v.trim().toLowerCase();
  if (e === '') return '';
  return e.length <= MAX_EMAIL_LENGTH && EMAIL_PATTERN.test(e) ? e : null;
}

/**
 * Validates an edit body. Only the whitelisted keys are accepted: anything else (role, hospital, password, ...) is refused
 * instead of being ignored, so a client never believes a change was saved when it was not.
 */
export function planEdit(body: unknown, superAdmin = false): EditResult {
  const errors: Record<string, string> = {};
  if (!body || typeof body !== 'object' || Array.isArray(body)) return { ok: false, errors: { body: 'bad_format' } };
  const b = body as Record<string, unknown>;
  for (const key of Object.keys(b)) if (!(superAdmin ? ALLOWED_SUPER : ALLOWED).includes(key)) errors[key] = 'not_allowed';
  const plan: EditPlan = {};

  if ('name' in b) {
    const v = typeof b.name === 'string' ? b.name.trim() : null;
    if (v === null) errors.name = 'bad_format';
    else if (v === '') errors.name = 'required';
    else if ([...v].length > MAX_NAME_LENGTH) errors.name = 'too_long';
    else if (CONTROL.test(v)) errors.name = 'bad_format';
    else plan.name = v;
  }
  if ('phone' in b) {
    const v = typeof b.phone === 'string' ? b.phone.trim() : null;
    if (v === null) errors.phone = 'bad_format';
    else if ([...v].length > MAX_PHONE_LENGTH) errors.phone = 'too_long';
    else if (CONTROL.test(v)) errors.phone = 'bad_format';
    else plan.phone = v;
  }
  if ('notifications' in b) {
    const v = b.notifications;
    if (!Array.isArray(v) || v.length > 100 || v.some((x) => typeof x !== 'string')) errors.notifications = 'bad_format';
    else if (v.some((x) => !(NOTIFICATION_TYPES as readonly string[]).includes(x as string))) errors.notifications = 'unknown_type';
    else plan.notifications = NOTIFICATION_TYPES.filter((t) => v.includes(t));
  }

  if ('role' in b && superAdmin) {
    if (typeof b.role !== 'string') errors.role = 'bad_format';
    else if (!(ASSIGNABLE_ROLES as readonly string[]).includes(b.role)) errors.role = 'unknown_role';
    else plan.role = b.role;
  }
  if ('hospitalId' in b && superAdmin) {
    const v = b.hospitalId;
    if (v === null) plan.hospitalId = null;
    else if (typeof v === 'number' && Number.isSafeInteger(v) && v > 0) plan.hospitalId = v;
    else errors.hospitalId = 'bad_format';
  }

  if ('email' in b) {
    const e = cleanEmail(b.email);
    if (e === null) errors.email = 'bad_format';
    else plan.email = e;
  }
  if ('newPassword' in b) {
    const e = passwordError(b.newPassword);
    if (e) errors.newPassword = e;
    else plan.newPassword = b.newPassword as string;
  }
  if ('currentPassword' in b) {
    const v = b.currentPassword;
    if (typeof v !== 'string' || v === '' || Buffer.byteLength(v, 'utf8') > 1024) errors.currentPassword = v === '' ? 'required' : 'bad_format';
    else plan.currentPassword = v;
  }

  // The proof alone is not a change.
  const changes = Object.keys(plan).filter((k) => k !== 'currentPassword');
  if (Object.keys(errors).length === 0 && changes.length === 0) errors.body = 'empty';
  return Object.keys(errors).length > 0 ? { ok: false, errors } : { ok: true, plan };
}

export interface CreatePlan {
  username: string;
  password: string;
  name: string;
  email: string;
  phone: string;
  role: string;
  hospitalId: number | null;
  notifications: string[];
}
export type CreateResult = { ok: true; plan: CreatePlan } | { ok: false; errors: Record<string, string> };

/** Validates the body of "add an admin": the same rules as the old panel, with a password of at least MIN_PASSWORD_LENGTH characters. */
export function planCreate(body: unknown): CreateResult {
  const errors: Record<string, string> = {};
  if (!body || typeof body !== 'object' || Array.isArray(body)) return { ok: false, errors: { body: 'bad_format' } };
  const b = body as Record<string, unknown>;
  for (const key of Object.keys(b)) if (!['username', 'password', 'name', 'email', 'phone', 'role', 'hospitalId', 'notifications'].includes(key)) errors[key] = 'not_allowed';

  const username = typeof b.username === 'string' ? b.username.trim() : null;
  if (username === null || username === '') errors.username = username === null && b.username !== undefined ? 'bad_format' : 'required';
  else if (!USERNAME_PATTERN.test(username)) errors.username = 'bad_format';

  const password = typeof b.password === 'string' ? b.password : null;
  const passwordProblem = b.password === undefined ? 'required' : passwordError(b.password);
  if (passwordProblem) errors.password = passwordProblem;

  const name = typeof b.name === 'string' ? b.name.trim() : null;
  if (name === null || name === '') errors.name = name === null && b.name !== undefined ? 'bad_format' : 'required';
  else if ([...name].length > MAX_NAME_LENGTH) errors.name = 'too_long';
  else if (CONTROL.test(name)) errors.name = 'bad_format';

  let email = '';
  if (b.email !== undefined && b.email !== null) {
    const e = cleanEmail(b.email);
    if (e === null) errors.email = 'bad_format';
    else email = e;
  }
  let phone = '';
  if (b.phone !== undefined && b.phone !== null) {
    const v = typeof b.phone === 'string' ? b.phone.trim() : null;
    if (v === null) errors.phone = 'bad_format';
    else if ([...v].length > MAX_PHONE_LENGTH) errors.phone = 'too_long';
    else if (CONTROL.test(v)) errors.phone = 'bad_format';
    else phone = v;
  }

  let role = 'hospital_admin';
  if (b.role !== undefined) {
    if (typeof b.role !== 'string') errors.role = 'bad_format';
    else if (!(ASSIGNABLE_ROLES as readonly string[]).includes(b.role)) errors.role = 'unknown_role';
    else role = b.role;
  }
  // A super admin has no hospital (an offered one is ignored, like the old panel); a hospital admin must have one.
  let hospitalId: number | null = null;
  if (role === 'hospital_admin' && !errors.role) {
    const v = b.hospitalId;
    if (v === undefined || v === null) errors.hospitalId = 'required';
    else if (typeof v === 'number' && Number.isSafeInteger(v) && v > 0) hospitalId = v;
    else errors.hospitalId = 'bad_format';
  }

  let notifications: string[] = [...DEFAULT_NOTIFICATIONS];
  if (b.notifications !== undefined) {
    const v = b.notifications;
    if (!Array.isArray(v) || v.length > 100 || v.some((x) => typeof x !== 'string')) errors.notifications = 'bad_format';
    else if (v.some((x) => !(NOTIFICATION_TYPES as readonly string[]).includes(x as string))) errors.notifications = 'unknown_type';
    else notifications = NOTIFICATION_TYPES.filter((t) => v.includes(t));
  }

  if (Object.keys(errors).length > 0) return { ok: false, errors };
  return { ok: true, plan: { username: username as string, password: password as string, name: name as string, email, phone, role, hospitalId, notifications } };
}

/** { "confirm": "<login name>" } -> the text, or null when it is missing or not a string. */
export function parseConfirm(body: unknown): string | null {
  const v = body && typeof body === 'object' ? (body as Record<string, unknown>).confirm : undefined;
  return typeof v === 'string' ? v : null;
}

export interface DeleteTarget {
  id: number;
  isActive: boolean;
  /** null: switched off before the time was recorded, or never switched off. */
  deactivatedAt: string | null;
  /** The database's own answer: switched off for at least the required days. */
  ready: boolean;
  inactiveDays: number | null;
}
export type DeletePlan =
  | { ok: true }
  | { ok: false; error: 'not_found' | 'self_forbidden' | 'still_active' | 'no_inactive_record' | 'not_inactive_long_enough'; daysLeft?: number };

/** An account may be deleted only after it has been switched off for `afterDays` days; never your own. */
export function planDelete(actorId: number, target: DeleteTarget | undefined, afterDays: number): DeletePlan {
  if (!target) return { ok: false, error: 'not_found' };
  if (target.id === actorId) return { ok: false, error: 'self_forbidden' };
  if (target.isActive) return { ok: false, error: 'still_active' };
  if (target.deactivatedAt === null) return { ok: false, error: 'no_inactive_record' };
  if (!target.ready) return { ok: false, error: 'not_inactive_long_enough', daysLeft: Math.max(1, afterDays - (target.inactiveDays ?? 0)) };
  return { ok: true };
}

/** The stored permissions text: like the old panel it holds the notification list and nothing else. */
export const permissionsJson = (notifications: string[]): string => JSON.stringify({ notifications });

/** { "active": true|false } -> the boolean, or null when it is not exactly that. */
export function parseActive(body: unknown): boolean | null {
  const v = body && typeof body === 'object' ? (body as Record<string, unknown>).active : undefined;
  return typeof v === 'boolean' ? v : null;
}

export interface ActiveTarget {
  id: number;
  role: string;
  isActive: boolean;
}
export type ActivePlan = { ok: true; noChange: boolean } | { ok: false; error: 'not_found' | 'self_forbidden' | 'last_super_admin' };

/**
 * Whether the active state of `target` may be set to `active`. `activeSupers` are the active super admins as locked by the
 * caller (the target included when it is one). Nobody changes their own state; the last active super admin stays active.
 */
export function planActive(actorId: number, target: ActiveTarget | undefined, activeSupers: number[], active: boolean): ActivePlan {
  if (!target) return { ok: false, error: 'not_found' };
  if (target.id === actorId) return { ok: false, error: 'self_forbidden' };
  if (!active && target.role === 'super_admin' && target.isActive && activeSupers.filter((id) => id !== target.id).length === 0) {
    return { ok: false, error: 'last_super_admin' };
  }
  return { ok: true, noChange: target.isActive === active };
}

/** Wrong "current password" answers per account: after `max` misses within `windowMs` the account is locked out of the check until the window ends. */
export class AttemptLimiter {
  private readonly misses = new Map<number, { count: number; since: number }>();
  constructor(private readonly max = 5, private readonly windowMs = 15 * 60 * 1000) {}

  /** Seconds to wait, or 0 when a try is allowed. */
  waitSeconds(id: number, now = Date.now()): number {
    const m = this.misses.get(id);
    if (!m) return 0;
    if (now - m.since >= this.windowMs) {
      this.misses.delete(id);
      return 0;
    }
    return m.count >= this.max ? Math.ceil((m.since + this.windowMs - now) / 1000) : 0;
  }
  miss(id: number, now = Date.now()): void {
    const m = this.misses.get(id);
    if (!m || now - m.since >= this.windowMs) this.misses.set(id, { count: 1, since: now });
    else m.count += 1;
    // Keeps the map small: entries whose window has passed are dropped.
    if (this.misses.size > 1000) for (const [k, v] of this.misses) if (now - v.since >= this.windowMs) this.misses.delete(k);
  }
  clear(id: number): void {
    this.misses.delete(id);
  }
}
