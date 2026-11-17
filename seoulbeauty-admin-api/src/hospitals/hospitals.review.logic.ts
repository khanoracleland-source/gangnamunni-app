import { Query } from './hospitals.logic';

export const REVIEW_REASON_MAX = 1000;
export const REVIEWER_NAME_MAX = 100;

export type ReviewOutcome = 'approved' | 'rejected';
export type ParsedReject = { ok: true; reason: string } | { ok: false; fields: Record<string, string> };

/** Body of a rejection: { reason } - a non-empty text of at most 1000 characters after trimming. */
export function parseRejectBody(body: unknown): ParsedReject {
  const raw = body && typeof body === 'object' && !Array.isArray(body) ? (body as Record<string, unknown>).reason : undefined;
  if (typeof raw !== 'string') return { ok: false, fields: { reason: raw === undefined || raw === null ? 'required' : 'bad_format' } };
  const reason = raw.trim();
  if (reason === '') return { ok: false, fields: { reason: 'required' } };
  if ([...reason].length > REVIEW_REASON_MAX) return { ok: false, fields: { reason: 'too_long' } };
  return { ok: true, reason };
}

/** Who reviewed: the admin's name (else the login name), cut to the 100 characters of the column. */
export const reviewerName = (name: string | null | undefined, username: string | null | undefined): string => [...String(name || username || '')].slice(0, REVIEWER_NAME_MAX).join('');

/** The application row, locked for the rest of the transaction. */
export const lockApplicationQuery = (id: number): Query => ({ sql: 'SELECT id, status FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE', values: [id] });

/** Only a still pending application changes; the status is checked again in the statement itself. */
export const approveQuery = (id: number, reviewer: string): Query => ({
  sql: `UPDATE gangnamunni_hospitals SET status = 'approved', is_active = 1, ad_grade = 'none', reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'`,
  values: [reviewer, id],
});

export const rejectQuery = (id: number, reason: string, reviewer: string): Query => ({
  sql: `UPDATE gangnamunni_hospitals SET status = 'rejected', is_active = 0, reject_reason = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'`,
  values: [reason, reason, reviewer, id],
});
