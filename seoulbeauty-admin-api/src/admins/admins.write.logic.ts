import { NOTIFICATION_TYPES } from './admins.logic';

export const MAX_NAME_LENGTH = 100;
export const MAX_PHONE_LENGTH = 30;

/** What an edit may change here: the account's own name, phone and notification choice. */
export interface EditPlan {
  name?: string;
  phone?: string;
  notifications?: string[];
}
export type EditResult = { ok: true; plan: EditPlan } | { ok: false; errors: Record<string, string> };

const ALLOWED = ['name', 'phone', 'notifications'];
// eslint-disable-next-line no-control-regex
const CONTROL = /[\u0000-\u001f\u007f]/;

/**
 * Validates an edit body. Only the whitelisted keys are accepted: anything else (role, hospital, password, ...) is refused
 * instead of being ignored, so a client never believes a change was saved when it was not.
 */
export function planEdit(body: unknown): EditResult {
  const errors: Record<string, string> = {};
  if (!body || typeof body !== 'object' || Array.isArray(body)) return { ok: false, errors: { body: 'bad_format' } };
  const b = body as Record<string, unknown>;
  for (const key of Object.keys(b)) if (!ALLOWED.includes(key)) errors[key] = 'not_allowed';
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

  if (Object.keys(errors).length === 0 && Object.keys(plan).length === 0) errors.body = 'empty';
  return Object.keys(errors).length > 0 ? { ok: false, errors } : { ok: true, plan };
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
