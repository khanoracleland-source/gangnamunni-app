import { isMarketing, isNight } from './notifications.logic';
import { parseSendBody } from './notifications.send.logic';
import type { NotificationType } from './notifications.logic';

export const MAX_PENDING = 50;
export const MIN_LEAD_MS = 60_000;
export const MAX_AHEAD_DAYS = 90;
const KST_MS = 9 * 3600_000;
/** Placeholders for the fields a planned send does not have yet (the request id is made when it runs; nobody previewed it). */
const PLACEHOLDER_REQUEST_ID = 'scheduled_placeholder_0';

export type NightPolicy = 'skip' | 'delay';
export interface SchedulePayload {
  target: string; type: NotificationType; eventId: number; memberId: string; memberIds: string[]; title: string; message: string; link: string; nightPolicy: NightPolicy;
}
export type ScheduleResult = { ok: true; payload: SchedulePayload; runAt: string; runAtMs: number } | { ok: false; error: string };

/** "2026-11-30T09:30" (or with a space) read as Korean time, to epoch ms; null when it is not a real minute. */
export function kstToMs(v: string): number | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})$/.exec(v);
  if (!m) return null;
  const [y, mo, d, h, mi] = m.slice(1).map(Number);
  const ms = Date.UTC(y, mo - 1, d, h, mi) - KST_MS;
  const back = new Date(ms + KST_MS);
  if (back.getUTCFullYear() !== y || back.getUTCMonth() !== mo - 1 || back.getUTCDate() !== d || back.getUTCHours() !== h || back.getUTCMinutes() !== mi) return null;
  return ms;
}

const p2 = (n: number): string => String(n).padStart(2, '0');
/** The DATETIME text of a moment, in Korean time (the write pool runs in KST). */
export function msToKst(ms: number): string {
  const d = new Date(ms + KST_MS);
  return `${d.getUTCFullYear()}-${p2(d.getUTCMonth() + 1)}-${p2(d.getUTCDate())} ${p2(d.getUTCHours())}:${p2(d.getUTCMinutes())}:00`;
}
/** DATETIME text read from the database (the pool returns a Date or a string, both KST wall time). */
export function dbToMs(v: unknown): number | null {
  if (v instanceof Date) return v.getTime();
  const m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(String(v ?? ''));
  return m ? kstToMs(`${m[1]}-${m[2]}-${m[3]} ${m[4]}:${m[5]}`) : null;
}

/** The next 08:00 KST at or after `now` (the end of the night window). */
export function nextMorning(now: number): number {
  const d = new Date(now + KST_MS);
  let day = Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate(), 8, 0) - KST_MS;
  if (day < now) day += 24 * 3600_000;
  return day;
}

/** Marketing pushes at night only reach members with 야간 푸시 on; the planner may ask to wait for the morning instead. */
export const shouldDelay = (type: NotificationType, policy: NightPolicy, now: number): boolean => policy === 'delay' && isMarketing(type) && isNight(now);

export function parseScheduleBody(body: unknown, now: number): ScheduleResult {
  const b = (body && typeof body === 'object' ? body : {}) as Record<string, unknown>;
  if (b.test === true) return { ok: false, error: 'test_not_schedulable' };
  const parsed = parseSendBody({ ...b, test: false, requestId: PLACEHOLDER_REQUEST_ID, expectedResolved: 0 });
  if (!parsed.ok) return parsed;
  const runAtMs = typeof b.runAt === 'string' ? kstToMs(b.runAt.trim()) : null;
  if (runAtMs === null) return { ok: false, error: 'bad_run_at' };
  if (runAtMs < now + MIN_LEAD_MS) return { ok: false, error: 'run_at_past' };
  if (runAtMs > now + MAX_AHEAD_DAYS * 24 * 3600_000) return { ok: false, error: 'run_at_too_far' };
  if (b.nightPolicy !== undefined && b.nightPolicy !== 'skip' && b.nightPolicy !== 'delay') return { ok: false, error: 'bad_night_policy' };
  const nightPolicy: NightPolicy = b.nightPolicy === 'delay' ? 'delay' : 'skip';
  const i = parsed.input;
  const a = i.audience;
  return {
    ok: true, runAtMs, runAt: msToKst(runAtMs),
    payload: { target: a.target, type: a.type, eventId: a.eventId, memberId: a.memberId, memberIds: a.memberIds, title: i.title, message: i.message, link: i.link, nightPolicy },
  };
}

/** The stored payload read back as a send body (the request id is the planned send's own, so a repeated run cannot send twice). */
export const requestIdOf = (id: number): string => `sched_${String(id).padStart(12, '0')}`;
export function payloadToBody(raw: unknown, id: number): Record<string, unknown> | null {
  let p: Partial<SchedulePayload>;
  try { p = JSON.parse(String(raw)); } catch { return null; }
  if (!p || typeof p !== 'object') return null;
  const body = { ...p, requestId: requestIdOf(id), expectedResolved: 0, test: false };
  return parseSendBody(body).ok ? body : null;
}

/** What the list shows of a planned send. */
export function summarize(row: Record<string, unknown>): Record<string, unknown> {
  let p: Partial<SchedulePayload> = {};
  try { p = JSON.parse(String(row.payload)); } catch { /* the row is still listed */ }
  const ms = dbToMs(row.run_at);
  return {
    id: Number(row.id), adminId: Number(row.admin_id), adminUsername: String(row.admin_username ?? ''), status: String(row.status),
    runAt: ms === null ? '' : msToKst(ms).slice(0, 16), sendId: row.send_id == null ? null : Number(row.send_id), error: String(row.error ?? ''),
    target: p.target ?? '', type: p.type ?? '', title: p.title ?? '', message: p.message ?? '', link: p.link ?? '', nightPolicy: p.nightPolicy === 'delay' ? 'delay' : 'skip',
    memberCount: Array.isArray(p.memberIds) ? p.memberIds.length : p.target === 'individual' ? 1 : null,
  };
}
