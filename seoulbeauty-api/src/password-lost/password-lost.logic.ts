import { createHash as sha, createHmac, pbkdf2Sync, randomBytes, randomInt, timingSafeEqual } from 'crypto';

/** gnu_secure_temporary_password(): one of each group, filled from all groups, then shuffled. */
const GROUPS = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%*-_'];
export function temporaryPassword(length = 12): string {
  const chars = GROUPS.map((g) => g[randomInt(0, g.length)]);
  const all = GROUPS.join('');
  while (chars.length < length) chars.push(all[randomInt(0, all.length)]);
  for (let i = chars.length - 1; i > 0; i--) { const j = randomInt(0, i + 1); [chars[i], chars[j]] = [chars[j], chars[i]]; }
  return chars.join('');
}

/**
 * Gnuboard create_hash() (lib/pbkdf2.compat.php): "sha256:12000:<salt>:<base64(pbkdf2)>". The salt is 24 random bytes in base64 and the
 * base64 TEXT (not the bytes) is what goes into PBKDF2; the derived key is 24 raw bytes.
 */
export function createHash(password: string, salt = randomBytes(24).toString('base64')): string {
  const key = pbkdf2Sync(password, salt, 12000, 24, 'sha256');
  return `sha256:12000:${salt}:${key.toString('base64')}`;
}
/** validate_password(): used by the tests (and proves the format round-trips). */
export function validateHash(password: string, hash: string): boolean {
  const p = hash.split(':');
  if (p.length < 4 || p[0] !== 'sha256') return false;
  const want = Buffer.from(p[3], 'base64');
  const got = pbkdf2Sync(password, p[2], Number(p[1]), want.length, 'sha256');
  return want.length === got.length && timingSafeEqual(want, got);
}

export const escapeHtml = (v: string): string => v.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string));

export function resetMail(brand: string, name: string, tempPassword: string, link = ''): { subject: string; html: string } {
  const open = link ? `<a href='${escapeHtml(link)}' style='display:inline-block;padding:12px 20px;background:#230306;color:#fff;border-radius:8px;text-decoration:none;font-weight:700'>비밀번호 재설정하기</a><br><br>` : '';
  return {
    subject: `[${brand}] 비밀번호 재설정 안내`,
    html: "<div style='font-family:sans-serif;font-size:15px;color:#1a1a1a;line-height:1.7;max-width:560px'>"
      + `${escapeHtml(name)}님, 비밀번호 재설정을 요청하셨습니다.<br><br>`
      + `아래 임시 비밀번호를 비밀번호 재설정 화면에 입력한 뒤, 새 비밀번호를 설정해주세요. (${RESET_MARKER_TTL_MIN}분간 유효)<br><br>`
      + "<div style='padding:16px 20px;background:#f7f7f7;border-radius:8px'>"
      + `임시 비밀번호 : <strong style='font-size:20px;color:#AC1830;letter-spacing:2px'>${escapeHtml(tempPassword)}</strong></div><br>`
      + open
      + "<span style='font-size:13px;color:#999'>본인이 요청하지 않으셨더라도 기존 비밀번호는 이미 위 임시 비밀번호로 바뀌었습니다. 위 방법으로 새 비밀번호를 설정해주세요.</span></div>",
  };
}

/** mb_lost_certify marks "a reset request is open until <ms>" (the legacy token column is otherwise unused by the Next/Nest flow). */
export const RESET_MARKER_TTL_MIN = 30;
export const resetMarker = (now = Date.now()): string => `pwreset:${now + RESET_MARKER_TTL_MIN * 60_000}`;
export function resetMarkerValid(v: string, now = Date.now()): boolean {
  const m = /^pwreset:(\d{10,})$/.exec(v || '');
  return !!m && Number(m[1]) > now;
}

/** gnu_reset_rate_hit(): sliding window per key; records the hit only when allowed. */
export class RateWindow {
  private readonly hits = new Map<string, number[]>();
  constructor(private readonly limit: number, private readonly windowMs: number) {}
  /** Looks without recording (login: only wrong passwords count). */
  peek(key: string, now = Date.now()): { allowed: boolean; retry: number } {
    const h = (this.hits.get(key) ?? []).filter((t) => t > now - this.windowMs);
    const retry = h.length >= this.limit ? Math.max(1, Math.ceil((this.windowMs - (now - h[0])) / 1000)) : 0;
    return { allowed: retry === 0, retry };
  }
  /** Hits currently inside the window (no recording). */
  size(key: string, now = Date.now()): number {
    return (this.hits.get(key) ?? []).filter((t) => t > now - this.windowMs).length;
  }
  /** Records one hit unconditionally. */
  add(key: string, now = Date.now()): void {
    const h = (this.hits.get(key) ?? []).filter((t) => t > now - this.windowMs);
    h.push(now);
    this.hits.set(key, h);
    if (this.hits.size > 20_000) this.hits.clear();
  }
  hit(key: string, now = Date.now()): { allowed: boolean; retry: number } {
    const h = (this.hits.get(key) ?? []).filter((t) => t > now - this.windowMs);
    const retry = h.length >= this.limit ? Math.max(1, Math.ceil((this.windowMs - (now - h[0])) / 1000)) : 0;
    if (!retry) h.push(now);
    this.hits.set(key, h);
    if (this.hits.size > 20_000) this.hits.clear();
    return { allowed: retry === 0, retry };
  }
}

/**
 * Short-lived reset ticket handed out after the mailed temporary password was typed correctly. Stateless: HMAC over
 * {member, expiry, fingerprint of the current hash}, so it stops working the moment the password changes (single use).
 */
export const RESET_TICKET_TTL_MS = 15 * 60_000;
export const hashFingerprint = (hash: string): string => sha('sha256').update(hash).digest('hex').slice(0, 16);
export function signResetTicket(secret: string, mbId: string, hash: string, now = Date.now()): string {
  const body = Buffer.from(JSON.stringify({ m: mbId, e: now + RESET_TICKET_TTL_MS, f: hashFingerprint(hash) })).toString('base64url');
  return `${body}.${createHmac('sha256', secret).update(body).digest('base64url')}`;
}
export function readResetTicket(secret: string, ticket: string, now = Date.now()): { mbId: string; fp: string } | null {
  const [body, mac] = String(ticket ?? '').split('.');
  if (!body || !mac) return null;
  const want = createHmac('sha256', secret).update(body).digest();
  const got = Buffer.from(mac, 'base64url');
  if (want.length !== got.length || !timingSafeEqual(want, got)) return null;
  try {
    const d = JSON.parse(Buffer.from(body, 'base64url').toString('utf8'));
    if (typeof d.m !== 'string' || typeof d.f !== 'string' || typeof d.e !== 'number' || d.e < now) return null;
    return { mbId: d.m, fp: d.f };
  } catch { return null; }
}

/**
 * TESTING ONLY: PASSWORD_LOST_RATE_LIMIT=off lifts the reset-mail and verify limits. It works ONLY when NODE_ENV is explicitly
 * "development" or "test"; any other value, or none at all (pm2 / a hand-started node), ignores it, so a forgotten flag in .env
 * can never open the endpoint to mail floods / password resets on a live service.
 */
export const resetLimitsFlagSet = (env: NodeJS.ProcessEnv = process.env): boolean => env.PASSWORD_LOST_RATE_LIMIT === 'off';
export const resetLimitsOff = (env: NodeJS.ProcessEnv = process.env): boolean =>
  resetLimitsFlagSet(env) && (env.NODE_ENV === 'development' || env.NODE_ENV === 'test');
