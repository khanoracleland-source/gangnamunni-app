import { createCipheriv, createDecipheriv, createHash, randomBytes, timingSafeEqual } from 'node:crypto';

/**
 * The patient app's native sign-in tokens, exactly as the PHP side keeps them (gangnamunni/includes/app_native_auth.php), so that
 * a token issued by PHP is accepted here and the other way round while both app versions are in use. Everything lives in sso_tokens:
 *   patient_app_exchange  90 s code (SHA-256 stored), claimed by the first device that presents it; its answer is kept encrypted
 *   patient_app_access    1 h access token (SHA-256 stored), one per member and device
 *   patient_app_remember  120 d refresh token (SHA-256 stored), renewed in place
 *   patient_app_bridge    5 min one-time token (stored as is) that opens the WebView session
 * A device is bound through user_agent = "patient-app-device:" + SHA-256(lower-case device id).
 */
export const ACCESS_SECONDS = 3600;
export const EXCHANGE_SECONDS = 90;
export const REFRESH_SECONDS = 86400 * 120;
export const BRIDGE_SECONDS = 300;

const HEX64 = /^[a-f0-9]{64}$/i;
export const isToken = (v: unknown): v is string => typeof v === 'string' && HEX64.test(v);
export const isDeviceId = isToken;
export const sha256 = (v: string): string => createHash('sha256').update(v).digest('hex');
export const deviceBinding = (deviceId: string): string => `patient-app-device:${sha256(deviceId.toLowerCase())}`;
export const newToken = (): string => randomBytes(32).toString('hex');
export const str = (o: Record<string, unknown> | null | undefined, k: string): string => (o && typeof o[k] === 'string' ? (o[k] as string).trim() : '');

export function sameText(a: string, b: string): boolean {
  const x = Buffer.from(a), y = Buffer.from(b);
  return x.length === y.length && timingSafeEqual(x, y);
}

/** APP_NATIVE_AUTH_KEY: base64 of 32 bytes, the same value PHP has. Missing or malformed = the exchange cannot run (503), never a fallback. */
export function payloadKey(raw: string | undefined = process.env.APP_NATIVE_AUTH_KEY): Buffer {
  const b64 = (raw || '').trim();
  const key = /^[A-Za-z0-9+/]+={0,2}$/.test(b64) ? Buffer.from(b64, 'base64') : Buffer.alloc(0);
  if (key.length !== 32) throw new Error('APP_NATIVE_AUTH_KEY must be a base64-encoded 32-byte key');
  return key;
}

/** app_encrypt_exchange_payload(): "v1:" + base64(nonce 12 | tag 16 | ciphertext), AES-256-GCM, AAD = the hex SHA-256 of the code. */
export function encryptPayload(code: string, payload: Record<string, unknown>, key = payloadKey()): string {
  const nonce = randomBytes(12);
  const c = createCipheriv('aes-256-gcm', key, nonce, { authTagLength: 16 });
  c.setAAD(Buffer.from(sha256(code)));
  const ct = Buffer.concat([c.update(JSON.stringify(payload), 'utf8'), c.final()]);
  return `v1:${Buffer.concat([nonce, c.getAuthTag(), ct]).toString('base64')}`;
}

export function decryptPayload(code: string, stored: unknown, key = payloadKey()): Record<string, unknown> {
  if (typeof stored !== 'string' || !stored.startsWith('v1:')) throw new Error('Unsupported exchange payload');
  const body = stored.slice(3);
  const bytes = /^[A-Za-z0-9+/]+={0,2}$/.test(body) ? Buffer.from(body, 'base64') : Buffer.alloc(0);
  if (bytes.length < 29) throw new Error('Invalid exchange payload');
  const d = createDecipheriv('aes-256-gcm', key, bytes.subarray(0, 12), { authTagLength: 16 });
  d.setAAD(Buffer.from(sha256(code)));
  d.setAuthTag(bytes.subarray(12, 28));
  const plain = Buffer.concat([d.update(bytes.subarray(28)), d.final()]).toString('utf8'); // throws when the tag does not match
  const v = JSON.parse(plain);
  if (!v || typeof v !== 'object' || Array.isArray(v)) throw new Error('Invalid exchange payload data');
  return v as Record<string, unknown>;
}

/** app_session_land.php: same-site paths only (no scheme or host, no "//", no control characters or backslash); anything else is "/". */
export function safeLandUrl(url: unknown): string {
  const u = typeof url === 'string' ? url.trim() : '';
  return u !== '' && u[0] === '/' && !u.startsWith('//') && !/[\x00-\x1F\x7F\\]/.test(u) ? u : '/';
}

export function cookieValue(cookie: string | undefined, name: string): string {
  const m = new RegExp(`(?:^|;\\s*)${name.replace(/[^A-Za-z0-9_]/g, '')}=([^;]*)`).exec(cookie || '');
  return m ? m[1].trim() : '';
}
