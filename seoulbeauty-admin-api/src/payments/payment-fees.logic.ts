import { addDays, seoulToday } from './payments.logic';

export const FEE_METHODS = ['card', 'bank_transfer'] as const;
export type FeeMethod = (typeof FEE_METHODS)[number];
/** Basis points of one percent: 143 = 1.43%. */
export const MAX_RATE_BP = 10000;
export const FIRST_RATE_FLOOR = '2020-01-01';
/** A rate may be dated at most this far ahead: a wrong date can then be corrected (a not-yet-in-force row can be deleted) or waited out quickly. */
export const MAX_DAYS_AHEAD = 90;

export interface FeeRate { payMethod: FeeMethod; rateBp: number; effectiveFrom: string }

/** Fee of one payment: the amount paid by card / transfer times the rate, rounded DOWN to a whole won (PHP uses the same rule). */
export const feeOf = (paid: number, rateBp: number): number => Math.floor((Math.max(0, paid) * Math.max(0, rateBp)) / 10000);

/** The rate in force on `day` (Seoul date): the latest row of that method with effective_from <= day; null when none. */
export function rateAt(rows: FeeRate[], method: FeeMethod, day: string): number | null {
  let best: FeeRate | null = null;
  for (const r of rows) if (r.payMethod === method && r.effectiveFrom <= day && (!best || r.effectiveFrom > best.effectiveFrom)) best = r;
  return best ? best.rateBp : null;
}

const isRealDate = (v: string): boolean => /^\d{4}-\d{2}-\d{2}$/.test(v) && addDays(v, 0) === v && !Number.isNaN(Date.parse(`${v}T00:00:00Z`));

export type FeeBodyResult = { ok: true; plan: FeeRate } | { ok: false; errors: Record<string, string> };
const ALLOWED = ['payMethod', 'rateBp', 'effectiveFrom'];

/** Strict body check: only the three fields, an integer rateBp 0..10000, a real calendar date. The "after the latest rate" rule needs the database (see the service). */
export function parseFeeBody(raw: unknown, today: string = seoulToday()): FeeBodyResult {
  const errors: Record<string, string> = {};
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return { ok: false, errors: { body: 'bad_format' } };
  const b = raw as Record<string, unknown>;
  for (const k of Object.keys(b)) if (!ALLOWED.includes(k)) errors[k] = 'unknown_field';
  const method = b.payMethod;
  if (typeof method !== 'string' || !(FEE_METHODS as readonly string[]).includes(method)) errors.payMethod = 'bad_value';
  const rb = b.rateBp;
  const bp = typeof rb === 'number' ? rb : typeof rb === 'string' && /^\d{1,5}$/.test(rb) ? Number(rb) : NaN;
  if (!Number.isInteger(bp) || bp < 0 || bp > MAX_RATE_BP) errors.rateBp = 'bad_value';
  const from = b.effectiveFrom;
  if (typeof from !== 'string' || !isRealDate(from)) errors.effectiveFrom = 'bad_format';
  else if (from < FIRST_RATE_FLOOR || from > addDays(today, MAX_DAYS_AHEAD)) errors.effectiveFrom = 'out_of_range';
  if (Object.keys(errors).length) return { ok: false, errors };
  return { ok: true, plan: { payMethod: method as FeeMethod, rateBp: bp, effectiveFrom: from as string } };
}

/**
 * History rule: a new rate may not change what is already counted. If the method has a rate, the new one must start after the latest
 * start date AND from tomorrow on; the very first rate of a method may start on any allowed date (so existing payments are covered).
 */
export function orderError(latestFrom: string | null, effectiveFrom: string, today: string = seoulToday()): string | null {
  if (latestFrom === null) return null;
  if (effectiveFrom <= latestFrom) return 'not_after_latest';
  if (effectiveFrom <= today) return 'must_be_future';
  return null;
}

/** A fee rate id from the address: digits only, positive. */
export const parseRateId = (raw: unknown): number => (typeof raw === 'string' && /^[1-9]\d{0,17}$/.test(raw) ? Number(raw) : 0);
/** Only a rate that is not in force yet may be removed: what was ever used for a payment stays. */
export const removable = (effectiveFrom: string, today: string = seoulToday()): boolean => effectiveFrom > today;
