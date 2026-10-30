import { pbkdf2Sync, randomBytes, randomInt, timingSafeEqual } from 'crypto';

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

export function resetMail(brand: string, name: string, tempPassword: string): { subject: string; html: string } {
  return {
    subject: `[${brand}] 비밀번호 재설정 안내`,
    html: "<div style='font-family:sans-serif;font-size:15px;color:#1a1a1a;line-height:1.7;max-width:560px'>"
      + `${escapeHtml(name)}님, 비밀번호 재설정을 요청하셨습니다.<br><br>`
      + '아래 임시 비밀번호로 바로 로그인해주세요.<br><br>'
      + "<div style='padding:16px 20px;background:#f7f7f7;border-radius:8px'>"
      + `임시 비밀번호 : <strong style='font-size:20px;color:#AC1830;letter-spacing:2px'>${escapeHtml(tempPassword)}</strong></div><br>`
      + "<span style='font-size:13px;color:#999'>로그인 후 계정 설정에서 비밀번호를 다시 변경해주세요.</span></div>",
  };
}

/** gnu_reset_rate_hit(): sliding window per key; records the hit only when allowed. */
export class RateWindow {
  private readonly hits = new Map<string, number[]>();
  constructor(private readonly limit: number, private readonly windowMs: number) {}
  hit(key: string, now = Date.now()): { allowed: boolean; retry: number } {
    const h = (this.hits.get(key) ?? []).filter((t) => t > now - this.windowMs);
    const retry = h.length >= this.limit ? Math.max(1, Math.ceil((this.windowMs - (now - h[0])) / 1000)) : 0;
    if (!retry) h.push(now);
    this.hits.set(key, h);
    if (this.hits.size > 20_000) this.hits.clear();
    return { allowed: retry === 0, retry };
  }
}
