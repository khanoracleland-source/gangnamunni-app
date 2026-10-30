import { validateHash } from '../password-lost/password-lost.logic';

/** gnu_safe_after_url(): a same-site path, or an absolute URL on the request's own host; anything else falls back. */
export function safeAfterUrl(candidate: unknown, host: string, fallback: string): string {
  const c = typeof candidate === 'string' ? candidate.trim() : '';
  if (!c || c.includes('\\') || /[\x00-\x1F\x7F]/.test(c)) return fallback;
  if (c.startsWith('/') && !c.startsWith('//')) return c;
  try {
    const u = new URL(c);
    if (u.protocol !== 'http:' && u.protocol !== 'https:') return fallback;
    const reqHost = host.toLowerCase().replace(/:\d+$/, '');
    return reqHost && u.hostname.toLowerCase() === reqHost ? c : fallback;
  } catch { return fallback; }
}

/** Gnuboard stores dates as YYYYMMDD; "today" is Korean time. */
export function kstYmd(now = new Date()): string {
  const k = new Date(now.getTime() + 9 * 3600_000);
  return `${k.getUTCFullYear()}${String(k.getUTCMonth() + 1).padStart(2, '0')}${String(k.getUTCDate()).padStart(2, '0')}`;
}

export function cookieValue(header: string, name: string): string {
  for (const part of header.split(';')) {
    const i = part.indexOf('=');
    if (i > 0 && part.slice(0, i).trim() === name) return part.slice(i + 1).trim();
  }
  return '';
}

/** Only Gnuboard PBKDF2 hashes ("sha256:…") are checked here; legacy MySQL PASSWORD() hashes still need the PHP login to upgrade them. */
export const isPbkdf2Hash = (h: string): boolean => /^sha256:\d+:[^:]+:[^:]+$/.test(h);
export const passwordMatches = (password: string, hash: string): boolean => isPbkdf2Hash(hash) && validateHash(password, hash);
