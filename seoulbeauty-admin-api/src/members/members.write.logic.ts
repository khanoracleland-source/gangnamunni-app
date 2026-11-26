/** Pure parts of editing a member: what may be written and how it is validated. Only the e-mail and the password can be changed. */
import { pbkdf2Sync, randomBytes, timingSafeEqual } from 'crypto';

/** The only field of a member this API changes through an edit; anything else in the body is refused, never ignored. */
export const EDITABLE_FIELDS = ['email'] as const;
export const MAX_EMAIL_LENGTH = 254;
// No white space, '@' or control characters (NUL and the like) anywhere in the address.
const EMAIL_PATTERN = /^[^\s@\x00-\x1f\x7f]+@[^\s@\x00-\x1f\x7f]+\.[^\s@\x00-\x1f\x7f]+$/;

/** An e-mail as stored: trimmed and lower-cased. null when it is empty, too long or not an e-mail. */
export function cleanEmail(v: unknown): string | null {
  if (typeof v !== 'string') return null;
  const e = v.trim().toLowerCase();
  return e.length > 0 && e.length <= MAX_EMAIL_LENGTH && EMAIL_PATTERN.test(e) ? e : null;
}

export type EditPlan = { ok: true; email: string } | { ok: false; errors: Record<string, string> };

/** Validates the body of an edit: exactly the e-mail, nothing else. */
export function planEdit(body: unknown): EditPlan {
  if (!body || typeof body !== 'object' || Array.isArray(body)) return { ok: false, errors: { body: 'bad_format' } };
  const b = body as Record<string, unknown>;
  const found: [string, string][] = [];
  for (const key of Object.keys(b)) if (!(EDITABLE_FIELDS as readonly string[]).includes(key)) found.push([key, 'not_allowed']);
  if (!('email' in b)) found.push(['email', 'required']);
  else if (cleanEmail(b.email) === null) found.push(['email', 'bad_format']);
  // fromEntries defines every key as an own property, so a key named __proto__ is reported instead of silently dropped.
  if (found.length > 0) return { ok: false, errors: Object.fromEntries(found) };
  return { ok: true, email: cleanEmail(b.email) as string };
}

/** The words of the audit journal (one per kind of change). */
export type AuditAction = 'email_change' | 'password_reset' | 'block' | 'unblock' | 'leave';

// ---- password reset ---------------------------------------------------------------------------------------------

/** The same rule the app asks of a member's own password: 9-20 printable ASCII characters with a letter, a digit and a special character. */
export const PASSWORD_RE = /^(?=.*[A-Za-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s])[\x21-\x7E]{9,20}$/;
/** At most this many resets of one member within one hour. */
export const RESET_LIMIT_PER_HOUR = 3;

export type PasswordPlan = { ok: true; password: string } | { ok: false; errors: Record<string, string> };

/** Validates the body of a password reset: exactly the new password, nothing else. */
export function planPassword(body: unknown): PasswordPlan {
  if (!body || typeof body !== 'object' || Array.isArray(body)) return { ok: false, errors: { body: 'bad_format' } };
  const b = body as Record<string, unknown>;
  const found: [string, string][] = [];
  for (const key of Object.keys(b)) if (key !== 'password') found.push([key, 'not_allowed']);
  if (!('password' in b)) found.push(['password', 'required']);
  else if (typeof b.password !== 'string' || !PASSWORD_RE.test(b.password)) found.push(['password', 'bad_format']);
  if (found.length > 0) return { ok: false, errors: Object.fromEntries(found) };
  return { ok: true, password: b.password as string };
}

/**
 * The hash the site and the app write and read (Gnuboard create_hash): "sha256:12000:<salt>:<base64(pbkdf2)>". The salt is 24 random bytes
 * in base64 and the base64 TEXT (not the bytes) goes into PBKDF2; the derived key is 24 raw bytes.
 */
export function hashPassword(password: string, salt: string = randomBytes(24).toString('base64')): string {
  return `sha256:12000:${salt}:${pbkdf2Sync(password, salt, 12000, 24, 'sha256').toString('base64')}`;
}

/** Checks a password against such a hash (used by the tests; proves the format round-trips). */
export function matchesHash(password: string, hash: string): boolean {
  const p = hash.split(':');
  if (p.length < 4 || p[0] !== 'sha256') return false;
  const want = Buffer.from(p[3], 'base64');
  const got = pbkdf2Sync(password, p[2], Number(p[1]), want.length, 'sha256');
  return want.length === got.length && timingSafeEqual(want, got);
}

/** The states an admin can put a member in: blocked, active again (unblock) or left (soft: the row stays, the member can no longer sign in). */
export const STATUS_TARGETS = ['blocked', 'active', 'left'] as const;
export type StatusTarget = (typeof STATUS_TARGETS)[number];
export type StatusPlan = { ok: true; status: StatusTarget } | { ok: false; errors: Record<string, string> };

/** Validates the body of a status change: exactly {"status": "blocked" | "active" | "left"}. */
export function planStatus(body: unknown): StatusPlan {
  if (!body || typeof body !== 'object' || Array.isArray(body)) return { ok: false, errors: { body: 'bad_format' } };
  const b = body as Record<string, unknown>;
  const found: [string, string][] = [];
  for (const key of Object.keys(b)) if (key !== 'status') found.push([key, 'not_allowed']);
  if (!('status' in b)) found.push(['status', 'required']);
  else if (typeof b.status !== 'string' || !(STATUS_TARGETS as readonly string[]).includes(b.status)) found.push(['status', 'bad_format']);
  if (found.length > 0) return { ok: false, errors: Object.fromEntries(found) };
  return { ok: true, status: b.status as StatusTarget };
}
