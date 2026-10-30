import { createHash, createHmac, timingSafeEqual } from 'crypto';
import { isProvider, Provider, SocialProfile } from './social.logic';

/** PHP hash('adler32', $s) as 8 lowercase hex digits. */
export function adler32(input: string): string {
  let a = 1; let b = 0;
  for (const byte of Buffer.from(input, 'utf8')) { a = (a + byte) % 65521; b = (b + a) % 65521; }
  return (((b << 16) | a) >>> 0).toString(16).padStart(8, '0');
}
/** get_social_convert_id(): the Gnuboard mb_id of a social member (same value as the profile `sid` of Kakao / Apple). */
export const convertId = (identifier: string, provider: string): string => `${provider.toLowerCase()}_${adler32(createHash('md5').update(identifier).digest('hex'))}`;

/** social_relace_nick() followed by Gnuboard valid_mb_nick(): only Hangul, letters and digits survive (PHP would reject the sign-up instead). */
export function socialNick(displayName: string): string {
  return displayName.replace(/[^0-9a-zA-Z가-힣]/g, '');
}

/** The e-mail a social profile registers with (register_member.php): verified e-mail, else e-mail, else a Kakao placeholder. */
export function profileEmail(p: SocialProfile, provider: Provider): string {
  const email = (p.emailVerified || p.email || '').trim().toLowerCase();
  if (email) return email;
  return provider === 'kakao' ? `kakao_${convertId(p.identifier, provider)}@social.local` : '';
}

/** valid_mb_email(): a plain address of at most 100 characters. */
export const validEmail = (e: string): boolean => e.length <= 100 && /^[A-Za-z0-9._%+'-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/.test(e);

/** mb_id / mb_nick candidates: base, base1, base2 … (exist_mb_id_recursive / exist_mb_nick_recursive). */
export const candidates = (base: string, max = 50): string[] => Array.from({ length: max }, (_, i) => (i === 0 ? base : `${base}${i}`));

/** Signed, short-lived cookie that carries a social profile from the callback to the sign-up form (Google). */
export interface PendingPayload { p: Provider; i: string; n: string; e: string; ev: boolean; ph: string; u: string; x: number }
export const PENDING_COOKIE = 'sb_social_pending';
const mac = (secret: string, body: string): string => createHmac('sha256', `social-pending|${secret}`).update(body).digest('base64url');
export function signPending(secret: string, v: Omit<PendingPayload, 'x'>, now = Date.now()): string {
  const body = Buffer.from(JSON.stringify({ ...v, x: now + 900_000 } satisfies PendingPayload)).toString('base64url');
  return `${body}.${mac(secret, body)}`;
}
export function readPending(secret: string, cookie: string, now = Date.now()): PendingPayload | null {
  const i = cookie.lastIndexOf('.');
  if (i < 1) return null;
  const body = cookie.slice(0, i); const sig = cookie.slice(i + 1); const want = mac(secret, body);
  if (sig.length !== want.length || !timingSafeEqual(Buffer.from(sig), Buffer.from(want))) return null;
  try {
    const d = JSON.parse(Buffer.from(body, 'base64url').toString()) as PendingPayload;
    return d && isProvider(d.p) && typeof d.i === 'string' && d.i !== '' && typeof d.x === 'number' && d.x > now ? d : null;
  } catch { return null; }
}
