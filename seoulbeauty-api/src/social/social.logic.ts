import { createHash, createHmac, randomBytes, timingSafeEqual } from 'crypto';

export type Provider = 'kakao' | 'google';
export const PROVIDERS: Provider[] = ['kakao', 'google'];
export const PHP_NAME: Record<Provider, 'Kakao' | 'Google'> = { kakao: 'Kakao', google: 'Google' };
export const isProvider = (v: string): v is Provider => (PROVIDERS as string[]).includes(v);

export interface SocialProfile {
  identifier: string; displayName: string; photoURL: string; profileURL: string; email: string; emailVerified: string;
  username: string; phone: string; birthYear: number | null; birthMonth: number | null; birthDay: number | null; age: string | null;
}
const str = (v: unknown): string => (v == null ? '' : String(v));
const empty = (): SocialProfile => ({ identifier: '', displayName: '', photoURL: '', profileURL: '', email: '', emailVerified: '', username: '', phone: '', birthYear: null, birthMonth: null, birthDay: null, age: null });

/** Hybrid_Providers_Kakao::getUserProfile() (v2/user/me). */
export function kakaoProfile(d: any): SocialProfile | null {
  if (!d || d.id == null) return null;
  const acc = d.kakao_account && typeof d.kakao_account === 'object' ? d.kakao_account : {};
  const prof = acc.profile && typeof acc.profile === 'object' ? acc.profile : {};
  const props = d.properties && typeof d.properties === 'object' ? d.properties : {};
  const p = empty();
  p.identifier = str(d.id);
  p.displayName = str(prof.nickname) || str(props.nickname) || 'Kakao User';
  p.photoURL = str(prof.thumbnail_image_url) || str(props.thumbnail_image);
  const email = str(acc.email);
  if (email) { p.email = email; if (acc.is_email_verified) p.emailVerified = email; }
  p.username = str(acc.name) || p.displayName;
  p.phone = str(acc.phone_number);
  p.birthYear = acc.birthyear ? Number(acc.birthyear) : null;
  const bd = str(acc.birthday).replace(/[^0-9]/g, '');
  if (bd.length === 4) { p.birthMonth = Number(bd.slice(0, 2)); p.birthDay = Number(bd.slice(2, 4)); }
  p.age = acc.age_range ? str(acc.age_range) : null;
  return p;
}

/** Hybrid_Providers_Google::getUserProfile() (oauth2/v3/userinfo): identifier is `sub`. */
export function googleProfile(d: any): SocialProfile | null {
  if (!d || !d.sub || d.error) return null;
  const p = empty();
  p.identifier = str(d.sub);
  p.displayName = str(d.name);
  p.photoURL = str(d.picture);
  p.profileURL = str(d.profile);
  p.email = str(d.email);
  p.emailVerified = d.email_verified === true || d.email_verified === 'true' ? p.email : '';
  return p;
}

/** social_normalize_kakao_phone() */
export function normalizeKakaoPhone(v: string): string {
  let p = v.trim().replace(/[^0-9+]/g, '');
  if (p.startsWith('+82')) p = '0' + p.slice(3);
  return /^(?:01[016789][0-9]{7,8}|[+]?[0-9]{8,15})$/.test(p) ? p : '';
}

/** social_sync_kakao_member_profile(): only consented, valid fields; a login never erases existing data. */
export function kakaoMemberUpdates(p: SocialProfile, nowYear: number): Record<string, string> {
  const u: Record<string, string> = {};
  if (p.username.trim()) u.mb_name = p.username.trim();
  const phone = p.phone.trim() ? normalizeKakaoPhone(p.phone) : '';
  if (phone) u.mb_hp = phone;
  const y = p.birthYear ?? 0; const m = p.birthMonth ?? 0; const d = p.birthDay ?? 0;
  if (y >= 1900 && y <= nowYear && m >= 1 && m <= 12 && d >= 1) {
    const dt = new Date(Date.UTC(y, m - 1, d));
    if (dt.getUTCFullYear() === y && dt.getUTCMonth() === m - 1 && dt.getUTCDate() === d) u.mb_birth = `${y}${String(m).padStart(2, '0')}${String(d).padStart(2, '0')}`;
  }
  if (p.age && /^(?:[0-9]{1,3}~[0-9]{1,3}|[0-9]{1,3}~)$/.test(p.age)) u.mb_2 = p.age;
  return u;
}

export const objectSha = (p: SocialProfile): string => createHash('sha1').update(JSON.stringify(p)).digest('hex');

/** OAuth state kept in a signed, short-lived cookie: nothing to store on the server and it survives a restart. */
export interface StatePayload { p: Provider; s: string; u: string; e: number }
export function signState(secret: string, p: Provider, url: string, now = Date.now()): { value: string; state: string } {
  const state = randomBytes(24).toString('hex');
  const body = Buffer.from(JSON.stringify({ p, s: state, u: url, e: now + 600_000 } satisfies StatePayload)).toString('base64url');
  return { value: `${body}.${mac(secret, body)}`, state };
}
const mac = (secret: string, body: string): string => createHmac('sha256', `social-state|${secret}`).update(body).digest('base64url');
export function readState(secret: string, cookie: string, now = Date.now()): StatePayload | null {
  const i = cookie.lastIndexOf('.');
  if (i < 1) return null;
  const body = cookie.slice(0, i); const sig = cookie.slice(i + 1);
  const want = mac(secret, body);
  if (sig.length !== want.length || !timingSafeEqual(Buffer.from(sig), Buffer.from(want))) return null;
  try {
    const d = JSON.parse(Buffer.from(body, 'base64url').toString()) as StatePayload;
    return d && isProvider(d.p) && typeof d.s === 'string' && typeof d.e === 'number' && d.e > now ? d : null;
  } catch { return null; }
}
