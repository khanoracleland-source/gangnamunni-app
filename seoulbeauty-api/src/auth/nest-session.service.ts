import { Inject, Injectable, Logger } from '@nestjs/common';
import { createHash, randomBytes } from 'node:crypto';
import { DB, Db } from '../db/db.module';
import { kstYmd } from '../login/login.logic';

export const NEST_SESSION_COOKIE = 'sb_sid';
/** not "remember me": the cookie ends with the browser, the row after three idle hours (PHP session.gc_maxlifetime) */
export const IDLE_SECONDS = 3 * 3600;
export const REMEMBER_WEB_SECONDS = 31 * 86400;
export const REMEMBER_APP_SECONDS = 120 * 86400;
const TOKEN_RE = /^[a-f0-9]{64}$/;
const s = (v: unknown) => (v == null ? '' : String(v));

export interface NestSessionUser { id: string; no: number; name: string; nickname: string; level: number; phone: string }
export type NestSessionState = { kind: 'none' } | { kind: 'invalid' } | { kind: 'ok'; user: NestSessionUser };

/**
 * Every well-formed sb_sid of the request, in the order sent. There can be more than one: the same name set host-only and for the
 * shared domain are two cookies to a browser, and an old one must not hide the one that is valid.
 */
export function readSessionCookies(cookie: string | undefined): string[] {
  const out: string[] = [];
  for (const m of (cookie || '').matchAll(/(?:^|;\s*)sb_sid=([^;]*)/g)) {
    const v = m[1].trim();
    if (TOKEN_RE.test(v) && !out.includes(v) && out.length < 4) out.push(v);
  }
  return out;
}
export const readSessionCookie = (cookie: string | undefined): string => readSessionCookies(cookie)[0] ?? '';
export const hashToken = (token: string): string => createHash('sha256').update(token).digest('hex');
/** The session cookie of the PHP answer tells which Domain the site's sign-in cookies use (host-only when it has none). */
export function cookieDomainOf(setCookies: string[]): string {
  for (const c of setCookies) { const m = /;\s*domain=([^;]+)/i.exec(c); if (m && /^\.?[a-z0-9.-]+$/i.test(m[1].trim())) return m[1].trim(); }
  return '';
}
/** hms.seoulbeauty.app -> [configured domain, ".seoulbeauty.app"]: the Domain values a cookie of this site may carry. */
export function clearDomains(host: string, configured = process.env.NEST_SESSION_COOKIE_DOMAIN ?? ''): string[] {
  const out = new Set<string>();
  if (/^\.?[a-z0-9.-]+$/i.test(configured)) out.add(configured);
  const h = host.toLowerCase().replace(/:\d+$/, '');
  const parts = /^[a-z0-9.-]+$/.test(h) && !/^\d+(\.\d+){3}$/.test(h) ? h.split('.').filter(Boolean) : [];
  if (parts.length >= 2) out.add(`.${parts.slice(-2).join('.')}`);
  return [...out];
}
export function sessionCookie(token: string, maxAge: number | null, domain: string): string {
  return `${NEST_SESSION_COOKIE}=${token}; Path=/${domain ? `; Domain=${domain}` : ''}${maxAge === null ? '' : `; Max-Age=${maxAge}`}; HttpOnly; Secure; SameSite=Lax`;
}

/**
 * Sign-in sessions kept by Nest itself (table sb_sessions, cookie sb_sid) so that a signed-in request no longer asks PHP who it is.
 * Switch: NEST_SESSIONS=1. Off = nothing is created and nothing is read (AuthService asks PHP exactly as before).
 * A session ends when it expires, when the member's mb_cred_epoch moves (password change, 탈퇴, revoke-all), when the member is
 * blocked or has left, on POST /v1/auth/logout, and on a PHP logout (extend/sb_nest_session.extend.php deletes the row).
 */
@Injectable()
export class NestSessionService {
  private readonly log = new Logger(NestSessionService.name);
  private readonly ttlMs = Number(process.env.PHP_SESSION_CACHE_MS || 10_000);
  private readonly cache = new Map<string, { at: number; v: NestSessionState }>();
  constructor(@Inject(DB) private readonly db: Db) {}

  enabled(): boolean { return process.env.NEST_SESSIONS === '1'; }

  /** A new session for a member who has just signed in. null = not created (off, or the table is missing): the PHP session still works. */
  async create(mbId: string, o: { remember: boolean; app: boolean; userAgent?: string; source: string; domain?: string; replaces?: string; device?: string }): Promise<string | null> {
    if (!this.enabled() || !/^[0-9a-zA-Z_]{3,20}$/.test(mbId)) return null;
    try {
      const [rows] = (await this.db.query('SELECT mb_cred_epoch FROM g5_member WHERE mb_id = ? LIMIT 1', [mbId])) as any;
      const mb = (rows as any[])[0];
      if (!mb) return null;
      // the app wipes its WebView cookies at every start and is given a new session: the one this device had is of no use to anyone
      if (o.device) await this.dropDevice(mbId, o.device);
      const token = randomBytes(32).toString('hex');
      const life = o.remember ? (o.app ? REMEMBER_APP_SECONDS : REMEMBER_WEB_SECONDS) : IDLE_SECONDS;
      await this.db.query(
        'INSERT INTO sb_sessions (token_hash, mb_id, cred_epoch, remember, source, user_agent, created_at, last_seen_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))',
        [hashToken(token), mbId, Number(mb.mb_cred_epoch) || 0, o.remember ? 1 : 0, o.source.slice(0, 20), (o.device || s(o.userAgent)).slice(0, 255), life]);
      // the session this browser had before (another member, or the same one signing in again) and a little housekeeping
      for (const old of readSessionCookies(o.replaces)) { await this.db.query('DELETE FROM sb_sessions WHERE token_hash = ?', [hashToken(old)]).catch(() => undefined); this.cache.delete(hashToken(old)); }
      await this.db.query('DELETE FROM sb_sessions WHERE expires_at < NOW() LIMIT 200').catch(() => undefined);
      return sessionCookie(token, o.remember ? life : null, o.domain ?? process.env.NEST_SESSION_COOKIE_DOMAIN ?? '');
    } catch (e) {
      this.log.error(`session create failed: ${(e as Error).message}`);
      return null;
    }
  }

  /** The app session(s) of one device (user_agent holds the device binding for source "app"): at its next start, and at its logout. */
  async dropDevice(mbId: string, device: string): Promise<void> {
    if (!/^patient-app-device:[a-f0-9]{64}$/.test(device)) return;
    await this.db.query("DELETE FROM sb_sessions WHERE mb_id = ? AND source = 'app' AND user_agent = ?", [mbId, device]).catch((e) => this.log.error(`device session delete failed: ${(e as Error).message}`));
    this.cache.clear(); // the rows are gone by device, not by token: nothing cached may outlive them
  }

  /** none = no sb_sid cookie (or switched off); invalid = a cookie that no longer opens anything; ok = the signed-in member. */
  async verify(cookie: string | undefined, fresh = false): Promise<NestSessionState> {
    if (!this.enabled()) return { kind: 'none' };
    const tokens = readSessionCookies(cookie);
    if (!tokens.length) return { kind: 'none' };
    for (const token of tokens) {
      const v = await this.verifyOne(hashToken(token), fresh);
      if (v.kind !== 'invalid') return v; // the member, or "none" after a database error
    }
    return { kind: 'invalid' };
  }

  private async verifyOne(key: string, fresh: boolean): Promise<NestSessionState> {
    const hit = this.cache.get(key);
    const now = Date.now();
    if (!fresh && hit && now - hit.at < this.ttlMs) return hit.v;
    if (this.cache.size > 5000) this.cache.clear();
    let v: NestSessionState;
    try {
      v = await this.load(key);
    } catch (e) {
      // not cached: a database hiccup must not sign anybody out for ten seconds, the caller falls back to PHP for this request
      this.log.warn(`session lookup failed: ${(e as Error).message}`);
      return { kind: 'none' };
    }
    this.cache.set(key, { at: now, v });
    return v;
  }

  /**
   * A browser that PHP says is signed in but that has no Nest session (the app restores its sign-in in PHP at every start; sessions
   * older than NEST_SESSIONS): open one, so the following requests no longer ask PHP. Never "remember": it lives as long as the PHP
   * session it was taken from would (three idle hours), and the next app start simply adopts again.
   */
  async adopt(mbId: string, cookie: string | undefined, userAgent = ''): Promise<string | null> {
    // NEST_SESSION_ADOPT=1 switches this on by itself. A browser that has just signed out here carries sb_noadopt for a minute: its
    // PHP session is still alive until the PHP logout that follows, and must not be turned back into a Nest session in between.
    if (!this.enabled() || process.env.NEST_SESSION_ADOPT !== '1' || /(?:^|;\s*)sb_noadopt=1(?:;|$)/.test(cookie || '')) return null;
    if ((await this.verify(cookie)).kind === 'ok') return null;
    const made = await this.create(mbId, { remember: false, app: false, userAgent, source: 'adopt', replaces: cookie });
    if (made) this.log.log(`[auth][session_adopted] account=${createHash('sha256').update(mbId).digest('hex').slice(0, 16)}`);
    return made;
  }

  private async load(key: string): Promise<NestSessionState> {
    // two primary-key reads, no JOIN (the two tables do not share a collation)
    const [srows] = (await this.db.query('SELECT mb_id, cred_epoch, remember, TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS left_s FROM sb_sessions WHERE token_hash = ? LIMIT 1', [key])) as any;
    const ses = (srows as any[])[0];
    if (!ses) return { kind: 'invalid' };
    const drop = async (): Promise<NestSessionState> => { await this.db.query('DELETE FROM sb_sessions WHERE token_hash = ?', [key]).catch(() => undefined); return { kind: 'invalid' }; };
    if (Number(ses.left_s) <= 0) return drop();
    const [mrows] = (await this.db.query('SELECT mb_no, mb_id, mb_name, mb_nick, mb_level, mb_hp, mb_cred_epoch, mb_leave_date, mb_intercept_date FROM g5_member WHERE mb_id = ? LIMIT 1', [s(ses.mb_id)])) as any;
    const mb = (mrows as any[])[0];
    const today = kstYmd();
    if (!mb || (Number(mb.mb_cred_epoch) || 0) !== (Number(ses.cred_epoch) || 0)
      || (s(mb.mb_leave_date) !== '' && s(mb.mb_leave_date) <= today) || (s(mb.mb_intercept_date) !== '' && s(mb.mb_intercept_date) <= today)) return drop();
    // sliding three hours for a session without "remember me"; written at most every half hour
    if (!Number(ses.remember) && Number(ses.left_s) < IDLE_SECONDS - 1800) {
      await this.db.query('UPDATE sb_sessions SET last_seen_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE token_hash = ?', [IDLE_SECONDS, key]).catch(() => undefined);
    }
    return { kind: 'ok', user: { id: s(mb.mb_id), no: Number(mb.mb_no) || 0, name: s(mb.mb_name), nickname: s(mb.mb_nick), level: Number(mb.mb_level) || 0, phone: s(mb.mb_hp) } };
  }

  /** The member's cached state is stale (nickname changed, signed out …). */
  forget(cookie: string | undefined): void {
    for (const token of readSessionCookies(cookie)) this.cache.delete(hashToken(token));
  }

  /** Sign-out: the row goes, and the answer carries the header that removes the cookie (also when there was nothing to remove). */
  async destroy(cookie: string | undefined, host = ''): Promise<string[]> {
    for (const token of readSessionCookies(cookie)) {
      this.cache.delete(hashToken(token));
      await this.db.query('DELETE FROM sb_sessions WHERE token_hash = ?', [hashToken(token)]).catch((e) => this.log.error(`session delete failed: ${(e as Error).message}`));
    }
    // the cookie was set host-only or for the site's shared domain (whatever the PHP session cookie uses): clear every form it can have
    const out = [`${NEST_SESSION_COOKIE}=; Path=/; Max-Age=0; HttpOnly; Secure; SameSite=Lax`, 'sb_noadopt=1; Path=/; Max-Age=60; HttpOnly; Secure; SameSite=Lax'];
    for (const d of clearDomains(host)) out.push(`${NEST_SESSION_COOKIE}=; Path=/; Domain=${d}; Max-Age=0; HttpOnly; Secure; SameSite=Lax`);
    return out;
  }
}
