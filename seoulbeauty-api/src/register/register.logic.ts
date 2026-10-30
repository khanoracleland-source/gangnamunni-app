import { createHash as nodeHash, randomInt, timingSafeEqual } from 'crypto';
import { escapeHtml } from '../password-lost/password-lost.logic';

export const PASSWORD_RE = /^(?=.*[A-Za-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s])[\x21-\x7E]{9,20}$/;
export const NAME_RE = /^[\p{L}\p{M} .'-]{2,50}$/u;
export const PHONE_RE = /^01[0-9]{8,9}$/;

/** "today" in Korea (the site runs on KST; the container may not). */
export function kstToday(now = new Date()): { y: number; m: number; d: number } {
  const k = new Date(now.getTime() + 9 * 3600_000);
  return { y: k.getUTCFullYear(), m: k.getUTCMonth() + 1, d: k.getUTCDate() };
}

/** Same rules as register.php: real calendar date, not in the future, not older than 120 years. Returns the age in whole years. */
export function parseBirth(v: string, now = new Date()): { ymd: string; age: number } | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
  if (!m) return null;
  const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])];
  const dt = new Date(Date.UTC(y, mo - 1, d));
  if (dt.getUTCFullYear() !== y || dt.getUTCMonth() !== mo - 1 || dt.getUTCDate() !== d) return null;
  const t = kstToday(now);
  const cmp = (t.y - y) * 10000 + (t.m - mo) * 100 + (t.d - d);
  if (cmp < 0) return null;
  let age = t.y - y;
  if (t.m * 100 + t.d < mo * 100 + d) age -= 1;
  if (y * 10000 + mo * 100 + d < (t.y - 120) * 10000 + t.m * 100 + t.d) return null;
  return { ymd: `${m[1]}${m[2]}${m[3]}`, age };
}

/** g5_config.cf_prohibit_email: one domain per line. */
export function prohibitedMail(email: string, list: string): string {
  const domain = email.slice(email.lastIndexOf('@') + 1).toLowerCase();
  const set = list.split('\n').map((x) => x.trim().toLowerCase()).filter(Boolean);
  return set.includes(domain) ? `${domain} 메일은 사용할 수 없습니다.` : '';
}

export const newMbId = (email: string): string => 'sb_' + nodeHash('md5').update(email + Date.now() + Math.random()).digest('hex').slice(0, 10);

/** reserve_mb_nick(): the nickname must not hit cf_prohibit_id. */
export function nickReserved(nick: string, prohibit: string): boolean {
  return prohibit.split(',').map((x) => x.trim().toLowerCase()).filter(Boolean).some((w) => nick.toLowerCase().includes(w));
}
export function makeNick(name: string, mbId: string, prohibit: string): string {
  const base = Array.from(name.replace(/[^\p{L}\p{N}]/gu, '')).slice(0, 24).join('') || 'user';
  const nick = base + mbId.slice(-8);
  return nickReserved(nick, prohibit) ? 'member' + nodeHash('sha256').update(mbId).digest('hex').slice(0, 10) : nick;
}

export const newCode = (): string => String(randomInt(0, 1_000_000)).padStart(6, '0');
export const sameCode = (a: string, b: string): boolean => a.length === b.length && timingSafeEqual(Buffer.from(a), Buffer.from(b));

export function codeMail(code: string): { subject: string; html: string } {
  return {
    subject: '[서울미녀] 회원가입 이메일 인증 코드',
    html: "<div style='font-family:sans-serif;font-size:15px;color:#1a1a1a;line-height:1.7'>회원가입 인증 코드입니다.<br><br>"
      + `<div style='font-size:28px;font-weight:800;letter-spacing:4px;color:#AC1830'>${escapeHtml(code)}</div><br>10분 이내에 입력해주세요.</div>`,
  };
}

interface Otp { code: string; email: string; expire: number; attempts: number }
/**
 * The e-mail code store (PHP kept it in the PHP session). Keyed by e-mail: a correct code is consumed at once and leaves a 30-minute
 * "verified" mark for the same address, so a later failed field check never lets the code itself be replayed.
 */
export class OtpStore {
  private readonly codes = new Map<string, Otp>();
  private readonly verified = new Map<string, { code: string; at: number }>();
  issue(email: string, code: string, now = Date.now()): void { this.verified.delete(email); this.codes.set(email, { code, email, expire: now + 600_000, attempts: 0 }); this.trim(); }
  drop(email: string): void { this.codes.delete(email); }
  /** '' = passed, otherwise the message shown to the member */
  check(email: string, code: string, now = Date.now()): string {
    // The mark keeps the code it was earned with: it is only good for whoever still holds that code (the mailbox owner).
    const ok = this.verified.get(email);
    if (ok && now <= ok.at + 1_800_000 && sameCode(ok.code, code)) return '';
    const o = this.codes.get(email);
    if (!o) return '이메일 인증을 먼저 진행해주세요.';
    if (now > o.expire) return '인증코드가 만료되었습니다. 다시 발송해주세요.';
    if (!sameCode(o.code, code)) {
      o.attempts += 1;
      if (o.attempts >= 5) { this.codes.delete(email); return '인증코드 입력 횟수를 초과했습니다. 새 코드를 발송해주세요.'; }
      return '인증코드가 올바르지 않습니다.';
    }
    this.codes.delete(email);
    this.verified.set(email, { code, at: now });
    return '';
  }
  clear(email: string): void { this.codes.delete(email); this.verified.delete(email); }
  private trim(): void { if (this.codes.size > 20_000) this.codes.clear(); if (this.verified.size > 20_000) this.verified.clear(); }
}
