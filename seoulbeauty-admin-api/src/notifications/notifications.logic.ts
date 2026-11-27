/**
 * Pure parts of the notifications page: who can be written to, which of the member's 설정 switches decides, the night window,
 * the allowed link targets and the SQL that finds the recipients. Read only; nothing here talks to the database or to FCM.
 */
import { likeContains, Query } from '../hospitals/hospitals.logic';

export const TARGETS = ['all', 'recent_7days', 'event_entrants', 'specific_event', 'individual', 'selected'] as const;
export type Target = (typeof TARGETS)[number];

export const TYPES = ['general', 'booking', 'community', 'review', 'event', 'raffle', 'system', 'promotion'] as const;
export type NotificationType = (typeof TYPES)[number];

export const MAX_TITLE = 200;
export const MAX_MESSAGE = 1000;
export const MAX_SELECTED = 500;
export const MAX_MEMBER_ID = 100;
/** Members per `IN (...)` query. */
export const CHUNK = 1000;

export const TYPE_LABELS: Record<NotificationType, string> = {
  general: '일반 알림', booking: '예약 알림', community: '커뮤니티', review: '포인트·후기',
  event: '이벤트', raffle: '응모/추첨', system: '시스템', promotion: '프로모션',
};

/** The switch of the member's 설정 page that decides a type. 'marketing' = 마케팅 푸시 (+ 야간 푸시 at night); 'none' = a service notice. */
export type Gate = { kind: 'toggle'; column: 'noti_booking' | 'noti_community' | 'noti_review' } | { kind: 'marketing' } | { kind: 'none' };

export function gateFor(type: NotificationType): Gate {
  switch (type) {
    case 'booking': return { kind: 'toggle', column: 'noti_booking' };
    case 'community': return { kind: 'toggle', column: 'noti_community' };
    case 'review': return { kind: 'toggle', column: 'noti_review' };
    case 'promotion':
    case 'event': return { kind: 'marketing' };
    default: return { kind: 'none' }; // raffle, general, system
  }
}

export const isMarketing = (type: NotificationType): boolean => gateFor(type).kind === 'marketing';

/** Android topic broadcast: only the general / system notices sent to everybody (a topic cannot honour anybody's 설정, so no other type uses it). */
export const usesTopic = (type: NotificationType, target: Target): boolean => target === 'all' && (type === 'general' || type === 'system');

// ---- night window (KST) -------------------------------------------------------------------------------------------
export const NIGHT_START = 21;
export const NIGHT_END = 8;

export function kstHour(now: number | Date = Date.now()): number {
  const ms = typeof now === 'number' ? now : now.getTime();
  return new Date(ms + 9 * 3600_000).getUTCHours();
}
/** 21:00 up to (not including) 08:00 KST. */
export const isNight = (now: number | Date = Date.now()): boolean => { const h = kstHour(now); return h >= NIGHT_START || h < NIGHT_END; };

// ---- the member's switches ----------------------------------------------------------------------------------------
export interface Prefs { noti_booking: number; noti_community: number; noti_review: number; mkt_push: number; mkt_night_push: number }
/** No row in 설정 = the page's defaults: the notice switches on, marketing off. */
export const DEFAULT_PREFS: Prefs = { noti_booking: 1, noti_community: 1, noti_review: 1, mkt_push: 0, mkt_night_push: 0 };

export function toPrefs(row: Record<string, unknown> | undefined): Prefs {
  if (!row) return { ...DEFAULT_PREFS };
  const f = (k: keyof Prefs): number => (row[k] === null || row[k] === undefined ? DEFAULT_PREFS[k] : Number(row[k]) === 1 ? 1 : 0);
  return { noti_booking: f('noti_booking'), noti_community: f('noti_community'), noti_review: f('noti_review'), mkt_push: f('mkt_push'), mkt_night_push: f('mkt_night_push') };
}

export interface Split {
  /** members that get the in-app row */
  inApp: string[];
  /** members that get a push */
  push: string[];
  skippedInApp: number;
  /** push not sent because of the member's switch (type switch, or 마케팅 푸시 off) */
  skippedPush: number;
  /** marketing push held back only because it is night and 야간 푸시 is off (the in-app row is still written) */
  nightSkipped: number;
  /** the members counted in `nightSkipped` */
  nightSkippedIds: string[];
}

/**
 * Who gets what. Toggle types: both channels follow the switch. Marketing types (promotion, event): the in-app row goes to everybody,
 * the push needs 마케팅 푸시 and, at night, 야간 푸시 too. Notice types: everybody, whatever the old noti_* columns say.
 */
export function splitRecipients(memberIds: string[], prefs: Map<string, Prefs>, type: NotificationType, night: boolean): Split {
  const gate = gateFor(type);
  const out: Split = { inApp: [], push: [], skippedInApp: 0, skippedPush: 0, nightSkipped: 0, nightSkippedIds: [] };
  for (const id of memberIds) {
    const p = prefs.get(id) ?? DEFAULT_PREFS;
    if (gate.kind === 'toggle') {
      if (p[gate.column] === 1) { out.inApp.push(id); out.push.push(id); } else { out.skippedInApp++; out.skippedPush++; }
    } else if (gate.kind === 'marketing') {
      out.inApp.push(id);
      if (p.mkt_push !== 1) out.skippedPush++;
      else if (night && p.mkt_night_push !== 1) { out.nightSkipped++; out.nightSkippedIds.push(id); }
      else out.push.push(id);
    } else {
      out.inApp.push(id); out.push.push(id);
    }
  }
  return out;
}

// ---- link targets -------------------------------------------------------------------------------------------------
export interface LinkTarget { kind: 'page' | 'hospital' | 'event'; path: string; label: string }

/** Fixed pages of the member site (seoulbeauty-web). Only these and the id patterns below can be a link. */
export const STATIC_LINKS: LinkTarget[] = [
  { kind: 'page', path: '/next', label: '홈' },
  { kind: 'page', path: '/next/hospitals', label: '병원 찾기' },
  { kind: 'page', path: '/next/events', label: '이벤트' },
  { kind: 'page', path: '/next/community', label: '커뮤니티' },
  { kind: 'page', path: '/next/mypage', label: '마이페이지' },
  { kind: 'page', path: '/next/mypage/notifications', label: '알림' },
  { kind: 'page', path: '/next/mypage/bookings', label: '예약 내역' },
  { kind: 'page', path: '/next/mypage/points', label: '포인트' },
  { kind: 'page', path: '/next/mypage/wishlist', label: '찜한 병원' },
  { kind: 'page', path: '/next/faq', label: '자주 묻는 질문' },
];
const STATIC_PATHS = new Set(STATIC_LINKS.map((l) => l.path));
const ID_LINK = /^\/next\/(hospitals|events|community|reviews)\/[1-9]\d{0,9}$/;

/** '' (no link) or an allowed `/next/...` path; anything else (other host, query, fragment, other path) is invalid. */
export function normalizeLink(raw: unknown): { ok: true; link: string } | { ok: false } {
  if (raw === undefined || raw === null || raw === '') return { ok: true, link: '' };
  if (typeof raw !== 'string') return { ok: false };
  const v = raw.trim();
  if (v === '') return { ok: true, link: '' };
  return STATIC_PATHS.has(v) || ID_LINK.test(v) ? { ok: true, link: v } : { ok: false };
}

export function searchStaticLinks(q: string): LinkTarget[] {
  const needle = q.trim().toLowerCase();
  if (!needle) return STATIC_LINKS;
  return STATIC_LINKS.filter((l) => l.label.toLowerCase().includes(needle) || l.path.toLowerCase().includes(needle));
}

// ---- who is written to --------------------------------------------------------------------------------------------
export interface Audience { target: Target; eventId: number; memberId: string; memberIds: string[]; type: NotificationType }
export type AudienceResult = { ok: true; audience: Audience } | { ok: false; error: string };

const one = (v: unknown): string => (typeof v === 'string' ? v.trim() : typeof v === 'number' ? String(v) : '');

/** The audience part of a preview / send body. Unknown values never become defaults silently: they answer an error. */
export function parseAudience(body: unknown): AudienceResult {
  const b = (body && typeof body === 'object' ? body : {}) as Record<string, unknown>;
  const target = one(b.target) as Target;
  if (!TARGETS.includes(target)) return { ok: false, error: 'bad_target' };
  const type = one(b.type) as NotificationType;
  if (!TYPES.includes(type)) return { ok: false, error: 'bad_type' };
  const eventId = /^[1-9]\d{0,9}$/.test(one(b.eventId)) ? Number(one(b.eventId)) : 0;
  if (target === 'specific_event' && eventId <= 0) return { ok: false, error: 'event_required' };
  const memberId = one(b.memberId);
  if (target === 'individual' && (memberId === '' || memberId.length > MAX_MEMBER_ID)) return { ok: false, error: 'member_required' };
  let memberIds: string[] = [];
  if (target === 'selected') {
    if (!Array.isArray(b.memberIds)) return { ok: false, error: 'members_required' };
    memberIds = [...new Set(b.memberIds.map(one).filter((s) => s !== '' && s.length <= MAX_MEMBER_ID))];
    if (memberIds.length === 0) return { ok: false, error: 'members_required' };
    if (memberIds.length > MAX_SELECTED) return { ok: false, error: 'too_many_members' };
  }
  return { ok: true, audience: { target, eventId, memberId, memberIds, type } };
}

/** Members that have left are never written to (their push tokens are not cleaned on leaving). */
const ACTIVE = `(m.mb_leave_date IS NULL OR m.mb_leave_date = '')`;

/** The member ids of an audience. `entriesHaveMemberId`: gangnamunni_event_entries has a member_id column (else entries match by e-mail). */
export function recipientsQuery(a: Audience, entriesHaveMemberId: boolean): Query {
  switch (a.target) {
    case 'all':
      return { sql: `SELECT m.mb_id FROM g5_member m WHERE m.mb_id <> '' AND ${ACTIVE}`, values: [] };
    case 'recent_7days':
      return { sql: `SELECT m.mb_id FROM g5_member m WHERE m.mb_id <> '' AND ${ACTIVE} AND m.mb_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)`, values: [] };
    case 'event_entrants':
    case 'specific_event': {
      const byEvent = a.target === 'specific_event';
      const join = entriesHaveMemberId ? 'm.mb_id = e.member_id' : `m.mb_email = e.user_email AND e.user_email <> ''`;
      return {
        sql: `SELECT DISTINCT m.mb_id FROM gangnamunni_event_entries e INNER JOIN g5_member m ON ${join} WHERE m.mb_id <> '' AND ${ACTIVE}${byEvent ? ' AND e.event_id = ?' : ''}`,
        values: byEvent ? [a.eventId] : [],
      };
    }
    case 'individual':
      return { sql: `SELECT m.mb_id FROM g5_member m WHERE m.mb_id = ? AND ${ACTIVE} LIMIT 1`, values: [a.memberId] };
    default: {
      const ph = a.memberIds.map(() => '?').join(',');
      return { sql: `SELECT m.mb_id FROM g5_member m WHERE m.mb_id <> '' AND ${ACTIVE} AND m.mb_id IN (${ph})`, values: [...a.memberIds] };
    }
  }
}

export const chunk = <T>(items: T[], size = CHUNK): T[][] => {
  const out: T[][] = [];
  for (let i = 0; i < items.length; i += size) out.push(items.slice(i, i + size));
  return out;
};

export const prefsQuery = (ids: string[]): Query => ({
  sql: `SELECT member_id, noti_booking, noti_community, noti_review, mkt_push, mkt_night_push FROM gangnamunni_notification_settings WHERE member_id IN (${ids.map(() => '?').join(',')})`,
  values: ids,
});

export const tokenCountQuery = (ids: string[]): Query => ({
  sql: `SELECT COUNT(DISTINCT mb_id) AS members_with_tokens, COUNT(DISTINCT pt_token) AS tokens FROM g5_push_tokens WHERE pt_use = 1 AND pt_token <> '' AND mb_id IN (${ids.map(() => '?').join(',')})`,
  values: ids,
});

// ---- preview ------------------------------------------------------------------------------------------------------
export interface Preview {
  resolved: number;
  inApp: number;
  pushMembers: number;
  pushWithTokens: number;
  pushTokens: number;
  skippedInApp: number;
  skippedPush: number;
  nightSkipped: number;
  marketing: boolean;
  night: boolean;
  topic: boolean;
  settingsAvailable: boolean;
}

export function buildPreview(a: Audience, resolved: number, split: Split, tokens: { members: number; tokens: number }, night: boolean, settingsAvailable: boolean): Preview {
  return {
    resolved, inApp: split.inApp.length, pushMembers: split.push.length, pushWithTokens: tokens.members, pushTokens: tokens.tokens,
    skippedInApp: split.skippedInApp, skippedPush: split.skippedPush, nightSkipped: split.nightSkipped,
    marketing: isMarketing(a.type), night, topic: usesTopic(a.type, a.target), settingsAvailable,
  };
}

// ---- history ------------------------------------------------------------------------------------------------------
export const HISTORY_PER_PAGE = 20;
export const ITEMS_PER_PAGE = 50;
export const ITEM_STATUSES = ['sent', 'failed', 'skipped'] as const;

export const pageOf = (raw: unknown): number => { const n = Number(typeof raw === 'string' ? raw : ''); return Number.isInteger(n) && n >= 1 ? Math.min(n, 100_000) : 1; };
export const idOf = (raw: unknown): number => (typeof raw === 'string' && /^[1-9]\d{0,9}$/.test(raw) ? Number(raw) : 0);

export function parsePushDetails(raw: unknown): unknown {
  if (typeof raw !== 'string' || raw === '') return null;
  try { return JSON.parse(raw); } catch { return null; }
}

// ---- token diagnostics --------------------------------------------------------------------------------------------
export function deviceIdFromInfo(info: unknown): string {
  const s = typeof info === 'string' ? info : '';
  try { const j = JSON.parse(s); if (j && typeof j === 'object' && typeof (j as any).device_id === 'string') return (j as any).device_id.trim(); } catch { /* not JSON */ }
  const m = /"device_id"\s*:\s*"([^"]+)"/.exec(s);
  return m ? m[1].trim() : '';
}

export interface DuplicateToken { memberId: string; platform: string; deviceId: string; count: number; lastSeen: string }

/** Several active tokens of one member on one device (same device_id): the newest ones first, at most 10. */
export function duplicateTokens(rows: Record<string, unknown>[]): DuplicateToken[] {
  const buckets = new Map<string, DuplicateToken>();
  for (const r of rows) {
    const deviceId = deviceIdFromInfo(r.pt_device_info);
    if (!deviceId) continue;
    const key = `${r.mb_id}\n${r.pt_platform}\n${deviceId}`;
    const b = buckets.get(key) ?? { memberId: String(r.mb_id), platform: String(r.pt_platform), deviceId, count: 0, lastSeen: String(r.pt_datetime ?? '') };
    b.count++;
    buckets.set(key, b);
  }
  return [...buckets.values()].filter((b) => b.count > 1).sort((x, y) => y.count - x.count || y.lastSeen.localeCompare(x.lastSeen)).slice(0, 10);
}

export const linkSearchPattern = (q: string): string => likeContains(q.trim().slice(0, 50));
