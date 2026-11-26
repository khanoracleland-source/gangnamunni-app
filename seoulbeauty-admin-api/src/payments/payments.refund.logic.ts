import { createHash } from 'crypto';

/** Reasons an admin can pick; the text is sent to the gateway as the cancel message and stored with the request. */
export const REFUND_REASONS = ['고객 변심', '병원 사정', '시술 불가', '기타'] as const;
/** A refund request id is made by the browser (32 random bytes, hex): the same id sent twice never refunds twice. */
export const REQUEST_ID_RE = /^[a-f0-9]{64}$/;
/** Payments that went through the Inicis gateway (the sandbox one included); everything else has nothing to cancel there. */
export const REFUND_PROVIDERS = ['inicis', 'inicis_test'] as const;
export const REFUNDABLE_STATUSES = ['paid', 'partially_refunded'] as const;

export type RefundBlock = 'not_pg' | 'no_tid' | 'not_paid' | 'method_unsupported' | 'booking_not_cancelled' | 'nothing_left';

export interface RefundCandidate {
  provider: string; tid: string; status: string; amount: number; refundedAmount: number; bookingStatus: string; payMethod: string;
}

/** What the customer actually paid and has not got back yet. Points that covered part of the price are not part of it. */
export const refundableAmount = (c: Pick<RefundCandidate, 'amount' | 'refundedAmount'>): number => Math.max(0, c.amount - c.refundedAmount);

/** Why a payment cannot be refunded here, or null when it can. The booking must already be cancelled (the admin cancels first, then refunds). */
export function refundBlock(c: RefundCandidate): RefundBlock | null {
  if (!(REFUND_PROVIDERS as readonly string[]).includes(c.provider)) return 'not_pg';
  if (!c.tid || c.tid.length > 40) return 'no_tid';
  if (!(REFUNDABLE_STATUSES as readonly string[]).includes(c.status)) return 'not_paid';
  if (c.payMethod === 'bank_transfer') return 'method_unsupported';
  if (c.bookingStatus !== 'cancelled') return 'booking_not_cancelled';
  if (refundableAmount(c) <= 0) return 'nothing_left';
  return null;
}

export type RefundBody = { ok: true; requestId: string; reason: string } | { ok: false; errors: Record<string, string> };
export function parseRefundBody(body: unknown): RefundBody {
  const b = body && typeof body === 'object' ? (body as Record<string, unknown>) : {};
  const errors: Record<string, string> = {};
  const requestId = typeof b.requestId === 'string' ? b.requestId : '';
  const reason = typeof b.reason === 'string' ? b.reason : '';
  if (!REQUEST_ID_RE.test(requestId)) errors.requestId = 'invalid';
  if (!(REFUND_REASONS as readonly string[]).includes(reason)) errors.reason = 'invalid';
  return Object.keys(errors).length ? { ok: false, errors } : { ok: true, requestId, reason };
}

/** "INIpayTest0123456789" -> "INIp…6789": enough to find the payment in the Inicis console, not enough to use it. */
export const maskTid = (tid: string): string => (tid.length <= 8 ? (tid ? '…' + tid.slice(-2) : '') : `${tid.slice(0, 4)}…${tid.slice(-4)}`);

// ───────── Inicis INIAPI refund ─────────
export interface InicisRefundConfig { mid: string; apiKey: string; clientIp: string; mode: 'live' | 'test'; timeoutMs: number }

/** null = refunds are not configured. clientIp is the server address registered at Inicis for INIAPI. Secrets come only from the environment. */
export function inicisRefundConfig(env: NodeJS.ProcessEnv = process.env): InicisRefundConfig | null {
  const mid = (env.INICIS_MID ?? '').trim();
  const apiKey = (env.INICIS_INIAPI_KEY ?? '').trim();
  const clientIp = (env.INICIS_CLIENT_IP ?? '').trim();
  if (!mid || !apiKey || !/^(\d{1,3}\.){3}\d{1,3}$/.test(clientIp)) return null;
  const mode = (env.INICIS_MODE ?? 'test').trim() === 'live' ? 'live' : 'test';
  const timeoutMs = Math.min(30_000, Math.max(3_000, Number(env.INICIS_TIMEOUT_MS) || 10_000));
  return { mid, apiKey, clientIp, mode, timeoutMs };
}

/** A live payment is cancelled only against the live gateway and a sandbox payment only against the sandbox one, never the other way round. */
export const providerMatchesMode = (provider: string, mode: 'live' | 'test'): boolean => (provider === 'inicis') === (mode === 'live');

export const refundUrl = (mode: 'live' | 'test'): string => `https://${mode === 'live' ? 'iniapi' : 'stginiapi'}.inicis.com/api/v1/refund`;

/** yyyyMMddHHmmss in Seoul time. */
export function seoulStamp(now: Date = new Date()): string {
  const p = Object.fromEntries(new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Seoul', hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' }).formatToParts(now).map((x) => [x.type, x.value]));
  return `${p.year}${p.month}${p.day}${p.hour}${p.minute}${p.second}`;
}

/** The message goes to Inicis in at most 80 bytes of UTF-8, never cutting a character in half. */
export function cutBytes(v: string, max: number): string {
  let out = '';
  let n = 0;
  for (const ch of v) { const b = Buffer.byteLength(ch, 'utf8'); if (n + b > max) break; out += ch; n += b; }
  return out;
}

/** hashData = SHA512(INIAPIKey + type + paymethod + timestamp + clientIp + mid + tid). */
export function refundForm(cfg: InicisRefundConfig, tid: string, reason: string, timestamp: string = seoulStamp()): Record<string, string> {
  const type = 'Refund';
  const paymethod = 'Card';
  const hashData = createHash('sha512').update(cfg.apiKey + type + paymethod + timestamp + cfg.clientIp + cfg.mid + tid).digest('hex');
  return { type, paymethod, timestamp, clientIp: cfg.clientIp, mid: cfg.mid, tid, msg: cutBytes(reason.trim() || 'refund', 80), hashData };
}

export type InicisAnswer = { kind: 'ok'; code: string; cancelledAt: string } | { kind: 'rejected'; code: string } | { kind: 'unknown'; why: string };
/** Codes that mean "this transaction is already cancelled" (to be filled in after the first live test, as in the PHP admin). */
export const ALREADY_CANCELLED_CODES: readonly string[] = [];

/** "00" = cancelled. A clear refusal is final; a broken or missing answer is "unknown": the card may or may not have been refunded. */
export function parseInicisAnswer(httpStatus: number, text: string): InicisAnswer {
  let body: Record<string, unknown> | null = null;
  try { const j = JSON.parse(text); body = j && typeof j === 'object' ? (j as Record<string, unknown>) : null; } catch { body = null; }
  if (httpStatus !== 200 || !body || typeof body.resultCode !== 'string') return { kind: 'unknown', why: `HTTP ${httpStatus}` };
  const code = body.resultCode;
  if (code === '00' || ALREADY_CANCELLED_CODES.includes(code)) return { kind: 'ok', code, cancelledAt: `${String(body.cancelDate ?? '')}${String(body.cancelTime ?? '')}`.slice(0, 14) };
  return { kind: 'rejected', code: code.replace(/[^A-Za-z0-9]/g, '').slice(0, 20) };
}

export const refundLockKey = (merchantUid: string): string => 'payment:' + createHash('sha256').update(merchantUid).digest('hex').slice(0, 48);
export const evidenceHash = (tid: string, amount: number, a: { code: string; cancelledAt: string }): string => createHash('sha256').update(`${tid}:${amount}:${a.code}:${a.cancelledAt}`).digest('hex');
