import { Injectable, Logger } from '@nestjs/common';
import { createHash } from 'node:crypto';

export interface SessionUser {
  id: string;
  /** g5_member.mb_no — used as gangnamunni_wishlist.member_id */
  no: number;
  name: string;
  nickname: string;
  level: number;
  phone: string;
}
export interface Session {
  loggedIn: boolean;
  user: SessionUser | null;
}

/**
 * Session lookup delegated to PHP (api/session_me.php): Gnuboard session, remember-login
 * and account intercept rules keep living in one place while pages migrate.
 * The browser's Cookie header is forwarded as-is; the result is cached briefly per cookie.
 */
@Injectable()
export class AuthService {
  private readonly log = new Logger('Auth');
  private readonly url = process.env.PHP_SESSION_URL || 'https://hms.seoulbeauty.app/api/session_me.php';
  /** Set when PHP_SESSION_URL points at loopback (http://127.0.0.1/...) so Apache picks the right vhost. */
  private readonly host = process.env.PHP_SESSION_HOST || '';
  private readonly ttlMs = Number(process.env.PHP_SESSION_CACHE_MS || 10_000);
  private readonly cache = new Map<string, { at: number; v: Session }>();

  /** Used by /health: can we reach the PHP session authority at all? */
  async ping(): Promise<boolean> {
    try {
      const res = await fetch(this.url, { headers: this.headers(''), redirect: 'manual', signal: AbortSignal.timeout(3000) });
      if (!res.ok) return false;
      const j = (await res.json()) as Partial<Session>;
      return typeof j?.loggedIn === 'boolean';
    } catch {
      return false;
    }
  }

  private headers(cookie: string): Record<string, string> {
    const h: Record<string, string> = { accept: 'application/json', 'user-agent': 'seoulbeauty-api' };
    if (cookie) h.cookie = cookie;
    if (this.host) h.host = this.host;
    return h;
  }

  /** `fresh` skips the cache (use before security-sensitive writes). */
  async fromCookie(cookie: string | undefined, fresh = false): Promise<Session> {
    if (!cookie || !cookie.trim()) return { loggedIn: false, user: null };
    const key = createHash('sha256').update(cookie).digest('hex');
    const hit = this.cache.get(key);
    const now = Date.now();
    if (!fresh && hit && now - hit.at < this.ttlMs) return hit.v;
    if (this.cache.size > 5000) this.cache.clear();
    try {
      const ctrl = new AbortController();
      const t = setTimeout(() => ctrl.abort(), 4000);
      const res = await fetch(this.url, { headers: this.headers(cookie), redirect: 'manual', signal: ctrl.signal });
      clearTimeout(t);
      if (!res.ok) throw new Error(`php session ${res.status}`);
      const j = (await res.json()) as Session;
      const v: Session = j && j.loggedIn && j.user ? { loggedIn: true, user: j.user } : { loggedIn: false, user: null };
      this.cache.set(key, { at: now, v });
      return v;
    } catch (e) {
      // Fail closed: an unreachable PHP means "not logged in" for this request (not cached).
      this.log.warn(`session lookup failed: ${(e as Error).message}`);
      return { loggedIn: false, user: null };
    }
  }
}
