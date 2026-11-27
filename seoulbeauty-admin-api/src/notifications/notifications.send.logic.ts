/** Pure parts of sending: the body of a send, the FCM messages, retry and the bounded parallel loop. Nothing here talks to the database or to FCM. */
import { buildMemberFcmMessage, normalizePlatform } from '../bookings/notify/notify.logic';
import { Audience, MAX_MESSAGE, MAX_TITLE, normalizeLink, NotificationType, parseAudience, Target } from './notifications.logic';

export const ANDROID_TOPIC = 'seoulbeauty_android_all';
export const SENDING = 'sending';

export interface SendInput { audience: Audience; title: string; message: string; link: string; requestId: string; test: boolean; expectedResolved: number | null }
export type SendInputResult = { ok: true; input: SendInput } | { ok: false; error: string };

const one = (v: unknown): string => (typeof v === 'string' ? v.trim() : '');
const len = (s: string): number => [...s].length;

/** The body of POST /v1/notifications/send. A real send must say how many recipients the admin saw in the preview; a test goes to one member only. */
export function parseSendBody(body: unknown): SendInputResult {
  const a = parseAudience(body);
  if (!a.ok) return a;
  const b = (body && typeof body === 'object' ? body : {}) as Record<string, unknown>;
  const title = one(b.title);
  if (title === '') return { ok: false, error: 'title_required' };
  if (len(title) > MAX_TITLE) return { ok: false, error: 'title_too_long' };
  const message = one(b.message);
  if (len(message) > MAX_MESSAGE) return { ok: false, error: 'message_too_long' };
  const link = normalizeLink(b.link);
  if (!link.ok) return { ok: false, error: 'bad_link' };
  const requestId = one(b.requestId);
  if (!/^[A-Za-z0-9_-]{16,64}$/.test(requestId)) return { ok: false, error: 'bad_request_id' };
  const test = b.test === true;
  if (test && a.audience.target !== 'individual') return { ok: false, error: 'test_needs_individual' };
  let expectedResolved: number | null = null;
  if (!test) {
    const n = typeof b.expectedResolved === 'number' ? b.expectedResolved : NaN;
    if (!Number.isInteger(n) || n < 0) return { ok: false, error: 'expected_required' };
    expectedResolved = n;
  }
  return { ok: true, input: { audience: a.audience, title, message, link: link.link, requestId, test, expectedResolved } };
}

/** The history label of an audience (as the PHP page wrote it). */
export function targetLabel(a: Audience, resolved: number): string {
  switch (a.target) {
    case 'all': return '전체 회원';
    case 'recent_7days': return '최근 7일 가입자';
    case 'event_entrants': return '이벤트 응모자';
    case 'specific_event': return `이벤트 #${a.eventId} 응모자`;
    case 'individual': return `개별 회원: ${a.memberId}`;
    default: return `선택 회원 ${resolved}명`;
  }
}

/** icon column of gangnamunni_user_notifications (the PHP type table; community and review are new). */
export const ICONS: Record<NotificationType, string> = {
  general: 'bi-bell', booking: 'bi-calendar-check', event: 'bi-calendar-event', raffle: 'bi-gift', system: 'bi-gear', promotion: 'bi-megaphone',
  community: 'bi-chat-dots', review: 'bi-star',
};

/** The link as stored and pushed: absolute, because the app opens it in its WebView. */
export const absoluteLink = (path: string, base: string): string => (path === '' ? '' : `${base.replace(/\/+$/, '')}${path}`);

/** What the app reads from a push (the same keys the PHP admin sent). */
export function pushData(link: string): Record<string, string> {
  const d: Record<string, string> = { type: 'member_notification', source: 'gangnamunni_admin', click_action: 'FLUTTER_NOTIFICATION_CLICK' };
  if (link) { d.url = link; d.webview_url = link; }
  return d;
}

/** A device token's platform: the saved one, but a token saved as android from an iPhone is ios (as the PHP admin decided). */
export function inferPlatform(saved: unknown, deviceInfo: unknown): 'android' | 'ios' | 'web' {
  if (/iPhone|iPad|iPod/i.test(typeof deviceInfo === 'string' ? deviceInfo : '')) return 'ios';
  return normalizePlatform(saved);
}

export const tokenMessage = (token: string, platform: string, title: string, body: string, data: Record<string, string>) => buildMemberFcmMessage(token, platform, title, body, data);

/** Android topic message of a general / system notice sent to everybody. */
export function topicMessage(title: string, body: string, data: Record<string, string>, topic = ANDROID_TOPIC) {
  return { message: { topic, notification: { title, body }, android: { priority: 'high', notification: { sound: 'default', channel_id: 'notification' } }, data } };
}

export type Sent = { ok: true } | { ok: false; dead: boolean; status?: number; code?: string };

/** No answer, rate limit or a server error: worth trying again. A dead token or a rejected message is not. */
export const retryable = (r: Sent): boolean => !r.ok && !r.dead && (r.status === 0 || r.status === 429 || (r.status ?? 0) >= 500);

/** APNs refused the Firebase key: every other iOS token would fail the same way, so the iOS sends stop. */
export const isApnsAuthError = (platform: string, r: Sent): boolean => !r.ok && platform === 'ios' && r.status === 401 && r.code === 'THIRD_PARTY_AUTH_ERROR';

export const RETRY_DELAYS_MS = [300, 1000];

export async function sendWithRetry(send: () => Promise<Sent>, sleep: (ms: number) => Promise<void>, delays: number[] = RETRY_DELAYS_MS): Promise<Sent & { attempts: number }> {
  let attempts = 1;
  let r = await send();
  for (const d of delays) {
    if (!retryable(r)) break;
    await sleep(d);
    attempts++;
    r = await send();
  }
  return { ...r, attempts };
}

/** At most `concurrency` workers at a time; `stop()` is looked at before each item. Returns how many items were handled (the rest were not started). */
export async function runPool<T>(items: T[], concurrency: number, worker: (item: T, index: number) => Promise<void>, stop: () => boolean = () => false): Promise<number> {
  let next = 0;
  let handled = 0;
  const runner = async () => {
    for (;;) {
      if (stop()) return;
      const i = next++;
      if (i >= items.length) return;
      handled++;
      await worker(items[i], i);
    }
  };
  await Promise.all(Array.from({ length: Math.max(1, Math.min(concurrency, items.length)) }, runner));
  return handled;
}

export interface TokenOutcome { token: string; memberId: string; platform: string; status: 'sent' | 'failed' | 'skipped'; code: string }
export interface MemberPush { status: 'sent' | 'failed' | 'skipped'; platform: string; code: string }

/** One line per member: sent if any device got it; else failed (first error) if any was tried; else skipped. */
export function memberPushStatus(outcomes: TokenOutcome[]): Map<string, MemberPush> {
  const out = new Map<string, MemberPush>();
  for (const o of outcomes) {
    const cur = out.get(o.memberId);
    if (!cur || o.status === 'sent' || (o.status === 'failed' && cur.status === 'skipped')) out.set(o.memberId, { status: o.status, platform: o.platform, code: o.status === 'sent' ? '' : o.code });
  }
  return out;
}

export interface Tally { sent: number; failed: number; skipped: number; platforms: Record<string, { tokens: number; sent: number; failed: number; skipped: number }> }
export function tally(outcomes: TokenOutcome[]): Tally {
  const t: Tally = { sent: 0, failed: 0, skipped: 0, platforms: {} };
  for (const o of outcomes) {
    const p = (t.platforms[o.platform] ??= { tokens: 0, sent: 0, failed: 0, skipped: 0 });
    p.tokens++;
    p[o.status]++;
    t[o.status]++;
  }
  return t;
}

export const numEnv = (name: string, def: number, min: number, max: number): number => {
  const n = Number(process.env[name]);
  return Number.isFinite(n) && n > 0 ? Math.min(max, Math.max(min, Math.floor(n))) : def;
};

export type { Audience, Target };
