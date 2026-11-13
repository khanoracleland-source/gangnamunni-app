/**
 * Input rules of the member device-token registry (g5_push_tokens), ported from PHP includes/push_token_registry.php
 * (seoulbeauty_normalize_push_token_input / seoulbeauty_register_push_token) so a row written here is the row PHP would write.
 */
export interface PushTokenInput { token: string; platform: 'android' | 'ios'; deviceId: string; deviceInfo: string }

const APPLE = /iPad|iPhone|iPod/;
const text = (v: unknown): string => (typeof v === 'string' || typeof v === 'number' ? String(v) : '');

/** PHP: trim, 20–500 bytes. Added here: printable ASCII only (an FCM / APNs token never has spaces, control or non-ASCII characters). */
export function cleanToken(v: unknown): string {
  const t = typeof v === 'string' ? v.trim() : '';
  if (t === '' || Buffer.byteLength(t) < 20 || Buffer.byteLength(t) > 500 || !/^[\x21-\x7e]+$/.test(t)) return '';
  return t;
}

/**
 * android | ios as sent; anything else falls back the way the PHP page script chose it (iPhone / iPad user agent → ios, otherwise
 * android, PHP's default). "web" is not accepted here: the endpoint is for the app's native token only.
 */
export function cleanPlatform(v: unknown, userAgent: string): 'android' | 'ios' {
  const p = text(v).trim().toLowerCase();
  if (p === 'android' || p === 'ios') return p;
  return APPLE.test(userAgent) ? 'ios' : 'android';
}

/** The device id is searched with LIKE inside pt_device_info, so only id-shaped values are kept (a UUID passes); anything else = none. */
export function cleanDeviceId(v: unknown): string {
  const d = text(v).trim();
  return /^[A-Za-z0-9._:-]{1,100}$/.test(d) ? d : '';
}

/** device_info as PHP reads it: an object (here also a JSON string of one); a plain string or anything else counts as empty. */
function infoObject(v: unknown): Record<string, unknown> {
  let o: unknown = v;
  if (typeof v === 'string' && v.trim().startsWith('{')) { try { o = JSON.parse(v); } catch { o = null; } }
  return o && typeof o === 'object' && !Array.isArray(o) ? (o as Record<string, unknown>) : {};
}

/** null = invalid token. pt_device_info is PHP's JSON, keys in PHP's order: device_id, app_version, platform, user_agent. */
export function normalizePushToken(body: unknown, userAgent: string): PushTokenInput | null {
  const b = body && typeof body === 'object' && !Array.isArray(body) ? (body as Record<string, unknown>) : {};
  const token = cleanToken(b.token);
  if (token === '') return null;
  const platform = cleanPlatform(b.platform, userAgent);
  const info = infoObject(b.device_info);
  const deviceId = cleanDeviceId(b.device_id ?? b.uuid);
  const deviceInfo = JSON.stringify({
    device_id: deviceId,
    app_version: text(b.app_version ?? b.version ?? info.app_version).slice(0, 100),
    platform,
    user_agent: (text(info.user_agent) || userAgent).slice(0, 500),
  });
  return { token, platform, deviceId, deviceInfo };
}

/** LIKE pattern PHP uses to find the other tokens of one device ('%"device_id":"<id>"%'), with the LIKE wildcards of the id escaped. */
export const deviceLike = (deviceId: string): string => `%"device_id":"${deviceId.replace(/[\\%_]/g, '\\$&')}"%`;

/** For logs: never the whole token. */
export const tokenTail = (token: string): string => `…${token.slice(-6)}`;
