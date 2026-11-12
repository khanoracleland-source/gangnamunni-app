import { Injectable, Logger, Optional } from '@nestjs/common';
import { createHmac } from 'crypto';
import { phpFetch } from '../common/php-fetch';
import { cookieDomainOf, NestSessionService } from '../auth/nest-session.service';

export interface BridgeResult { ok: boolean; setCookies: string[]; error?: string }

/**
 * api/session_login.php: only PHP can create the Gnuboard session. Nest calls it on loopback with the browser's own Cookie header
 * (so the session id is regenerated, not replaced) and hands the Set-Cookie headers of the answer back to the caller to relay.
 * The URL is derived from PHP_SESSION_URL and must therefore be a loopback URL (the PHP side refuses any other caller).
 */
@Injectable()
export class SessionBridgeService {
  private readonly log = new Logger(SessionBridgeService.name);
  constructor(@Optional() private readonly sessions?: NestSessionService) {}

  private url(): string {
    const base = process.env.PHP_SESSION_URL || 'https://hms.seoulbeauty.app/api/session_me.php';
    return base.replace(/session_me\.php(\?.*)?$/, 'session_login.php');
  }

  /** false when SESSION_BRIDGE_SECRET is missing: callers must not create accounts they cannot sign in. */
  configured(): boolean { return (process.env.SESSION_BRIDGE_SECRET || '').length >= 32; }

  async mint(mbId: string, mode: 'register' | 'login', remember: boolean, cookie: string, userAgent = '', social: '' | 'Kakao' | 'Google' | 'Apple' = ''): Promise<BridgeResult> {
    const secret = process.env.SESSION_BRIDGE_SECRET || '';
    if (secret.length < 32) return { ok: false, setCookies: [], error: 'SESSION_BRIDGE_SECRET not set' };
    const ts = Math.floor(Date.now() / 1000);
    const rem = remember ? 1 : 0;
    const sig = createHmac('sha256', secret).update(`${mbId}|${mode}|${rem}|${ts}${social ? `|${social}` : ''}`).digest('hex');
    const headers: Record<string, string> = { 'Content-Type': 'application/json', Cookie: cookie || '', 'User-Agent': userAgent || 'seoulbeauty-api' };
    if (process.env.PHP_SESSION_HOST) headers.Host = process.env.PHP_SESSION_HOST;
    try {
      const res = await phpFetch(this.url(), {
        method: 'POST', headers, signal: AbortSignal.timeout(15_000),
        body: JSON.stringify({ mb_id: mbId, mode, remember: rem, ts, sig, ...(social ? { social } : {}) }),
      });
      const setCookies = typeof (res.headers as any).getSetCookie === 'function' ? (res.headers as any).getSetCookie() as string[] : [];
      const body = (await res.json().catch(() => ({}))) as { ok?: boolean; error?: string };
      if (!res.ok || !body.ok) { this.log.error(`bridge ${res.status} ${body.error ?? ''}`); return { ok: false, setCookies: [], error: body.error ?? `http ${res.status}` }; }
      // Every sign-in of the site passes here (e-mail login, sign-up, Kakao / Google / Apple): Nest's own session is opened next to the
      // PHP one (NEST_SESSIONS=1). Its cookie goes last, with the Domain the PHP session cookie uses, so both are sent to the same hosts.
      const own = await this.sessions?.create(mbId, {
        remember: remember && mode === 'login', app: /(?:^|;\s*)sb_patient_app=1(?:;|$)/.test(cookie || ''), userAgent, source: social ? social.toLowerCase() : mode,
        domain: process.env.NEST_SESSION_COOKIE_DOMAIN ?? cookieDomainOf(setCookies), replaces: cookie,
      });
      return { ok: true, setCookies: own ? [...setCookies, own] : setCookies };
    } catch (e) {
      this.log.error(`bridge failed: ${(e as Error).message}`);
      return { ok: false, setCookies: [], error: (e as Error).message };
    }
  }
}
