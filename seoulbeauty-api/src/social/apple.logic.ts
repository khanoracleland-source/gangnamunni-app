import { createHmac, createPrivateKey, createPublicKey, sign as cryptoSign, timingSafeEqual, verify as cryptoVerify } from 'crypto';
import { convertId } from './social-register.logic';
import type { SocialProfile } from './social.logic';

const b64 = (v: Buffer | string): string => Buffer.from(v).toString('base64url');
const str = (v: unknown): string => (v == null ? '' : String(v));

/** cf_apple_key_file is the .p8 text pasted into a textarea: tolerate CRLF and literal "\n". */
export const normalizePem = (v: string): string => v.replace(/\\n/g, '\n').replace(/\r/g, '').trim() + '\n';

/** Hybrid_Providers_Apple::generateClientSecret(): an ES256 JWT signed with the .p8 key. Made per request, valid 5 minutes. */
export function appleClientSecret(k: { teamId: string; keyId: string; clientId: string; keyPem: string }, now = Date.now()): string {
  const iat = Math.floor(now / 1000);
  const head = b64(JSON.stringify({ typ: 'JWT', alg: 'ES256', kid: k.keyId }));
  const body = b64(JSON.stringify({ iss: k.teamId, iat, exp: iat + 300, aud: 'https://appleid.apple.com', sub: k.clientId }));
  const sig = cryptoSign('sha256', Buffer.from(`${head}.${body}`), { key: createPrivateKey(normalizePem(k.keyPem)), dsaEncoding: 'ieee-p1363' });
  return `${head}.${body}.${b64(sig)}`;
}

export const jwtKid = (token: string): string => {
  try { return str(JSON.parse(Buffer.from(token.split('.')[0] ?? '', 'base64url').toString())?.kid); } catch { return ''; }
};

export interface AppleClaims { sub: string; email: string; emailVerified: boolean }
export type AppleVerify = { ok: true; claims: AppleClaims } | { ok: false; error: string };

/**
 * Hybrid_Providers_Apple::getUserProfile(): the id_token must be RS256-signed by one of Apple's published keys, issued by Apple for
 * this client, not expired, and carry the nonce this sign-in started with.
 */
export function verifyAppleIdToken(token: string, keys: any[], want: { clientId: string; nonce: string }, now = Date.now()): AppleVerify {
  const bad = (error: string): AppleVerify => ({ ok: false, error });
  const parts = token.split('.');
  if (parts.length !== 3) return bad('format');
  let head: any; let c: any;
  try { head = JSON.parse(Buffer.from(parts[0], 'base64url').toString()); c = JSON.parse(Buffer.from(parts[1], 'base64url').toString()); } catch { return bad('format'); }
  if (!head || !c || head.alg !== 'RS256') return bad('alg');
  const jwk = keys.find((k) => k && k.kty === 'RSA' && k.kid && k.kid === head.kid);
  if (!jwk) return bad('kid');
  let signed = false;
  try {
    signed = cryptoVerify('RSA-SHA256', Buffer.from(`${parts[0]}.${parts[1]}`), createPublicKey({ key: { kty: 'RSA', n: jwk.n, e: jwk.e }, format: 'jwk' }), Buffer.from(parts[2], 'base64url'));
  } catch { signed = false; }
  if (!signed) return bad('signature');
  if (c.iss !== 'https://appleid.apple.com') return bad('iss');
  if (c.aud !== want.clientId) return bad('aud');
  if (!(Number(c.exp) * 1000 > now)) return bad('exp');
  const nonce = typeof c.nonce === 'string' ? c.nonce : '';
  if (!want.nonce || nonce.length !== want.nonce.length || !timingSafeEqual(Buffer.from(nonce), Buffer.from(want.nonce))) return bad('nonce');
  const sub = str(c.sub);
  if (!sub) return bad('sub');
  const ev = c.email_verified;
  return { ok: true, claims: { sub, email: str(c.email), emailVerified: ev === true || ev === 'true' || ev === 1 || ev === '1' } };
}

/** sanitizeAppleName(): no control characters, at most 100 characters. */
export const sanitizeAppleName = (name: string): string => Array.from(name.replace(/[\x00-\x1F\x7F]/g, '').trim()).slice(0, 100).join('');

/** The `user` form field Apple posts on the FIRST sign-in only: {"name":{"firstName","lastName"}} → lastName + firstName (as PHP). */
export function appleUserName(userJson: string): string {
  try {
    const n = JSON.parse(userJson)?.name;
    if (!n || typeof n !== 'object') return '';
    return sanitizeAppleName(`${typeof n.lastName === 'string' ? n.lastName : ''}${typeof n.firstName === 'string' ? n.firstName : ''}`);
  } catch { return ''; }
}

/** The profile PHP builds: identifier = sub, name = the posted name or the Gnuboard id (`apple_<hash>`) when Apple sends none. */
export function appleProfile(c: AppleClaims, name: string): SocialProfile {
  const username = sanitizeAppleName(name) || convertId(c.sub, 'apple');
  return { identifier: c.sub, displayName: username, photoURL: '', profileURL: '', email: c.email, emailVerified: c.emailVerified ? c.email : '', username, phone: '', birthYear: null, birthMonth: null, birthDay: null, age: null };
}

/**
 * Apple answers with a cross-site POST (response_mode=form_post), which carries none of the site's SameSite=Lax cookies (state, session,
 * app marker). The POST only stores what Apple sent in this signed, 2-minute cookie and redirects to the GET callback, where every
 * cookie is present again and the normal checks run.
 */
export const APPLE_RELAY_COOKIE = 'sb_apple_cb';
export interface AppleRelay { c: string; s: string; e: string; n: string; x: number }
const mac = (secret: string, body: string): string => createHmac('sha256', `apple-relay|${secret}`).update(body).digest('base64url');
export function signAppleRelay(secret: string, v: Omit<AppleRelay, 'x'>, now = Date.now()): string {
  const body = b64(JSON.stringify({ ...v, x: now + 120_000 } satisfies AppleRelay));
  return `${body}.${mac(secret, body)}`;
}
export function readAppleRelay(secret: string, cookie: string, now = Date.now()): AppleRelay | null {
  const i = cookie.lastIndexOf('.');
  if (i < 1) return null;
  const body = cookie.slice(0, i); const sig = cookie.slice(i + 1); const want = mac(secret, body);
  if (sig.length !== want.length || !timingSafeEqual(Buffer.from(sig), Buffer.from(want))) return null;
  try {
    const d = JSON.parse(Buffer.from(body, 'base64url').toString()) as AppleRelay;
    return d && typeof d.c === 'string' && typeof d.s === 'string' && typeof d.e === 'string' && typeof d.n === 'string' && typeof d.x === 'number' && d.x > now ? d : null;
  } catch { return null; }
}
