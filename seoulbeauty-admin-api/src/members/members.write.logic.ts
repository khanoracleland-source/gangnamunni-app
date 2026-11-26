/** Pure parts of editing a member: what may be written and how it is validated. Only the e-mail is editable. */

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
export type AuditAction = 'email_change';
