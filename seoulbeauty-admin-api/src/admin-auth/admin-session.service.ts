import { Injectable, Logger, ServiceUnavailableException } from '@nestjs/common';
import { createHash } from 'node:crypto';
import { phpFetch } from '../common/php-fetch';

export interface AdminUser {
  id: number;
  username: string;
  name: string;
  role: string;
  /** null for platform accounts; the hospital an account is bound to otherwise */
  hospitalId: number | null;
}
export interface AdminSession {
  loggedIn: boolean;
  admin: AdminUser | null;
}

const ANONYMOUS: AdminSession = { loggedIn: false, admin: null };
/** Upper bound of the per-cookie cache: a revoked or signed-out admin is seen as such within this time. */
export const MAX_SESSION_CACHE_MS = 5000;

/** Strict reading of the PHP answer; anything that is not exactly one of the two documented shapes is rejected (null). */
export function parseAdminSession(j: unknown): AdminSession | null {
  if (!j || typeof j !== 'object') return null;
  const o = j as { loggedIn?: unknown; admin?: any };
  if (o.loggedIn === false) return ANONYMOUS;
  if (o.loggedIn !== true || !o.admin || typeof o.admin !== 'object') return null;
  const id = Number(o.admin.id);
  const role = typeof o.admin.role === 'string' ? o.admin.role : '';
  if (!Number.isInteger(id) || id <= 0 || !role) return null;
  const hid = Number(o.admin.hospitalId);
  return {
    loggedIn: true,
    admin: {
      id,
      username: String(o.admin.username ?? ''),
      name: String(o.admin.name ?? ''),
      role,
      hospitalId: Number.isInteger(hid) && hid > 0 ? hid : null,
    },
  };
}

/**
 * Which admin is signed in. The PHP admin login stays the only authority: the browser's Cookie header is forwarded as-is to
 * api/admin_session_me.php, which opens the same session the PHP admin pages use and re-checks the account on every call.
 * The answer is cached per cookie for at most 5 s. When PHP cannot be asked the request fails with 503 (fail closed): an
 * unreachable authority must never look like "signed in", and saying "signed out" would bounce the admin to the login page.
 */
@Injectable()
export class AdminSessionService {
  private readonly log = new Logger('AdminSession');
  private readonly url = process.env.ADMIN_PHP_SESSION_URL || 'https://seoulbeauty.app/hairwang/gangnamunni/api/admin_session_me.php';
  /** Set when ADMIN_PHP_SESSION_URL points at loopback (http://127.0.0.1/...) so Apache picks the right vhost. */
  private readonly host = process.env.ADMIN_PHP_SESSION_HOST || '';
  private readonly ttlMs = Math.min(MAX_SESSION_CACHE_MS, Math.max(0, Number(process.env.ADMIN_PHP_SESSION_CACHE_MS ?? MAX_SESSION_CACHE_MS) || 0));
  private readonly cache = new Map<string, { at: number; v: AdminSession }>();

  private headers(cookie: string): Record<string, string> {
    const h: Record<string, string> = { accept: 'application/json', 'user-agent': 'seoulbeauty-admin-api', cookie };
    if (this.host) {
      h.host = this.host;
      // The site redirects plain-HTTP requests for its public host to HTTPS; this loopback hop stands in for the TLS one.
      h['x-forwarded-proto'] = 'https';
    }
    return h;
  }

  async fromCookie(cookie: string | undefined): Promise<AdminSession> {
    // Without a cookie nobody can be signed in: do not bother PHP.
    if (!cookie || !cookie.trim()) return ANONYMOUS;
    const key = createHash('sha256').update(cookie).digest('hex');
    const now = Date.now();
    const hit = this.cache.get(key);
    if (hit && now - hit.at < this.ttlMs) return hit.v;
    if (this.cache.size > 1000) this.cache.clear();
    let v: AdminSession | null;
    try {
      const res = await phpFetch(this.url, { headers: this.headers(cookie), signal: AbortSignal.timeout(4000) });
      if (!res.ok) throw new Error(`status ${res.status}`);
      v = parseAdminSession(await res.json());
      if (!v) throw new Error('unexpected body');
    } catch (e) {
      // Not cached: the next request asks again.
      this.log.warn(`admin session lookup failed: ${(e as Error).message}`);
      throw new ServiceUnavailableException({ error: 'session_unavailable' });
    }
    this.cache.set(key, { at: now, v });
    return v;
  }
}
