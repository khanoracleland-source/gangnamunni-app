import { HttpException, Inject, Injectable, Logger } from '@nestjs/common';
import { DB, DbConn, DbPool } from '../db/db.module';
import { AuthService } from '../auth/auth.service';
import { NestSessionService } from '../auth/nest-session.service';
import { kstYmd } from '../login/login.logic';
import {
  ACCESS_SECONDS, BRIDGE_SECONDS, cookieValue, decryptPayload, deviceBinding, encryptPayload, EXCHANGE_SECONDS, isDeviceId, isToken, newToken,
  payloadKey, REFRESH_SECONDS, sameText, sha256,
} from './app-auth.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
/**
 * What goes into the log for an unexpected error: its class and driver code, never its message. A MySQL message can quote the value
 * that failed ("Duplicate entry '<token>' …"), and the bridge token is stored as is — PHP does not log these messages either.
 */
const errTag = (e: unknown): string => {
  const x = e as { name?: unknown; code?: unknown; errno?: unknown } | null;
  const code = typeof x?.code === 'string' && /^[A-Z0-9_]{1,40}$/.test(x.code) ? x.code : typeof x?.errno === 'number' ? String(x.errno) : '';
  return `${typeof x?.name === 'string' && /^[A-Za-z]{1,40}$/.test(x.name) ? x.name : 'Error'}${code ? ` ${code}` : ''}`;
};
/** 401: the credential itself is not good (the app then tries its refresh token, or shows the login). */
class Refused extends Error {}
export interface AppUser { mb_id: string; name: string; nickname: string; email: string; phone: string; level: number }

/**
 * The patient app's token API in Nest: the same contract as the PHP files api/app_auth_exchange|verify|refresh.php, api/app_logout.php,
 * api/app_auth_bootstrap.php and app_session_land.php, on the same sso_tokens rows. The only difference is what a signed-in WebView
 * is: here it is Nest's own session (sb_sid), so no PHP session is needed any more.
 */
@Injectable()
export class AppAuthService {
  private readonly log = new Logger(AppAuthService.name);
  /** bootstrap answers the same code for a minute (a lost answer can be asked again without issuing new tokens) */
  private readonly recentCodes = new Map<string, { code: string; until: number }>();
  constructor(@Inject(DB) private readonly db: DbPool, private readonly auth: AuthService, private readonly sessions: NestSessionService) {}

  enabled(): boolean { return process.env.APP_AUTH_NEST === '1'; }

  private async tx<T>(work: (conn: DbConn) => Promise<T>): Promise<T> {
    if (!this.db.getConnection) throw new Error('pool connection needed');
    const conn: DbConn = await this.db.getConnection();
    let done = false;
    try {
      await conn.query('START TRANSACTION');
      const out = await work(conn);
      await conn.query('COMMIT');
      done = true;
      return out;
    } finally {
      if (!done) await conn.query('ROLLBACK').catch(() => undefined);
      conn.release();
    }
  }

  /** PHP answers 401 for a refused credential and 503 "<what>_unavailable" for everything else: the app keeps its tokens on a 503. */
  private async guarded<T>(what: string, work: () => Promise<T>): Promise<T> {
    try {
      return await work();
    } catch (e) {
      if (e instanceof Refused) throw new HttpException({ success: false, error: e.message }, 401);
      if (e instanceof HttpException) throw e;
      this.log.error(`${what} failed: ${errTag(e)}`);
      throw new HttpException({ success: false, error: `${what}_unavailable` }, 503);
    }
  }

  /** app_auth_member(): a blocked / left / unverified member is refused like a wrong token. */
  private async member(q: Pick<DbConn, 'query'>, mbId: string): Promise<AppUser> {
    const [rows] = (await q.query('SELECT mb_id, mb_name, mb_nick, mb_email, mb_hp, mb_level, mb_intercept_date, mb_leave_date, mb_email_certify FROM g5_member WHERE mb_id = ? LIMIT 1', [mbId])) as any;
    const m = (rows as any[])[0];
    const [cfg] = (await q.query('SELECT cf_use_email_certify FROM g5_config LIMIT 1')) as any;
    const today = kstYmd();
    const blocked = !m || (s(m.mb_intercept_date) !== '' && s(m.mb_intercept_date) <= today) || (s(m.mb_leave_date) !== '' && s(m.mb_leave_date) <= today)
      || (Number((cfg as any[])[0]?.cf_use_email_certify) > 0 && !/[1-9]/.test(s(m.mb_email_certify)));
    if (blocked) throw new Refused('account_unavailable');
    return { mb_id: s(m.mb_id), name: s(m.mb_name), nickname: s(m.mb_nick), email: s(m.mb_email), phone: s(m.mb_hp), level: Number(m.mb_level) || 0 };
  }

  private async issueAccess(conn: DbConn, mbId: string, binding: string, ip: string): Promise<{ token: string; expires_at: number }> {
    const token = newToken();
    await conn.query("DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_access' AND user_agent = ?", [mbId, binding]);
    await conn.query("INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, user_agent, expires_at, created_at) VALUES (?, 'patient_app_access', ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())", [sha256(token), mbId, ip, binding, ACCESS_SECONDS]);
    return { token, expires_at: Math.floor(Date.now() / 1000) + ACCESS_SECONDS };
  }

  /** stored as is (the WebView presents it as the sb_sso_token cookie); one per member and device */
  private async issueBridge(conn: DbConn, mbId: string, binding: string, ip: string): Promise<string> {
    const token = newToken();
    await conn.query("DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_bridge' AND user_agent = ?", [mbId, binding]);
    await conn.query("INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, user_agent, expires_at, created_at) VALUES (?, 'patient_app_bridge', ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())", [token, mbId, ip, binding, BRIDGE_SECONDS]);
    return token;
  }

  /** create_remember_login_token(… is_patient_app …): a new 120-day refresh token; the member's expired ones are swept */
  private async issueRefresh(conn: DbConn, mbId: string, binding: string, ip: string): Promise<string> {
    const token = newToken();
    await conn.query("INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, user_agent, expires_at, created_at) VALUES (?, 'patient_app_remember', ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())", [sha256(token), mbId, ip, binding, REFRESH_SECONDS]);
    await conn.query("DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_remember' AND expires_at <= NOW()", [mbId]);
    return token;
  }

  /** app_issue_exchange_code(): the 90-second code a signed-in WebView hands to the app. */
  async issueExchangeCode(mbId: string, ip: string): Promise<string> {
    const code = newToken();
    await this.db.query("DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_exchange' AND expires_at <= NOW()", [mbId]);
    await this.db.query("INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, expires_at, created_at) VALUES (?, 'patient_app_exchange', ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())", [sha256(code), mbId, ip, EXCHANGE_SECONDS]);
    return code;
  }

  /** api/app_auth_exchange.php: code + device -> the token bundle. The same device presenting the same code again gets the same bundle. */
  exchange(code: string, deviceId: string, ip: string) {
    return this.guarded('exchange', async () => {
      payloadKey(); // a missing key must stop the exchange before a code is claimed
      await this.db.query("UPDATE sso_tokens SET payload = NULL WHERE user_type = 'patient_app_exchange' AND expires_at <= NOW() AND payload IS NOT NULL");
      const binding = deviceBinding(deviceId), hash = sha256(code);
      return this.tx(async (conn) => {
        // the first device to present the code claims it (atomic: WHERE user_agent IS NULL); another device is refused below
        await conn.query("UPDATE sso_tokens SET user_agent = ? WHERE token = ? AND user_type = 'patient_app_exchange' AND expires_at > NOW() AND user_agent IS NULL", [binding, hash]);
        const [rows] = (await conn.query("SELECT g5_mb_id, payload FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_exchange' AND user_agent = ? AND expires_at > NOW() LIMIT 1 FOR UPDATE", [hash, binding])) as any;
        const row = (rows as any[])[0];
        const mbId = s(row?.g5_mb_id);
        if (!mbId) throw new Refused('exchange_invalid');
        const user = await this.member(conn, mbId);
        let payload: Record<string, unknown>;
        if (row.payload != null) payload = decryptPayload(code, row.payload);
        else {
          const refresh = await this.issueRefresh(conn, mbId, binding, ip);
          const access = await this.issueAccess(conn, mbId, binding, ip);
          const bridge = await this.issueBridge(conn, mbId, binding, ip);
          payload = { access_token: access.token, access_expires_at: access.expires_at, refresh_token: refresh, refresh_expires_at: Math.floor(Date.now() / 1000) + REFRESH_SECONDS, session_bridge_token: bridge };
          const [up] = (await conn.query("UPDATE sso_tokens SET payload = ? WHERE token = ? AND user_type = 'patient_app_exchange' AND payload IS NULL AND expires_at > NOW()", [encryptPayload(code, payload), hash])) as any;
          if (Number(up?.affectedRows) !== 1) throw new Error('Exchange payload was not persisted');
        }
        return { success: true, ...payload, user };
      });
    });
  }

  /** api/app_auth_verify.php: a live access token -> a fresh one-time bridge token for the WebView. */
  verify(accessToken: string, deviceId: string, ip: string) {
    return this.guarded('verify', () => this.tx(async (conn) => {
      const binding = deviceBinding(deviceId);
      const [rows] = isToken(accessToken)
        ? ((await conn.query("SELECT g5_mb_id FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_access' AND user_agent = ? AND expires_at > NOW() LIMIT 1 FOR UPDATE", [sha256(accessToken), binding])) as any)
        : [[]];
      const mbId = s((rows as any[])[0]?.g5_mb_id);
      if (!mbId) throw new Refused('access_token_invalid');
      const user = await this.member(conn, mbId);
      return { success: true, session_bridge_token: await this.issueBridge(conn, mbId, binding, ip), user };
    }));
  }

  /** api/app_auth_refresh.php: the refresh token is renewed in place (a lost answer can retry with the same token). */
  refresh(mbId: string, refreshToken: string, deviceId: string, ip: string) {
    return this.guarded('refresh', () => this.tx(async (conn) => {
      if (!isToken(refreshToken)) throw new Refused('refresh_invalid');
      const binding = deviceBinding(deviceId), hash = sha256(refreshToken);
      const [rows] = (await conn.query("SELECT token FROM sso_tokens WHERE token = ? AND g5_mb_id = ? AND user_type = 'patient_app_remember' AND user_agent = ? AND expires_at > NOW() FOR UPDATE", [hash, mbId, binding])) as any;
      if (!(rows as any[]).length) throw new Refused('refresh_invalid');
      await conn.query('UPDATE sso_tokens SET expires_at = GREATEST(expires_at, DATE_ADD(NOW(), INTERVAL ? SECOND)) WHERE token = ?', [REFRESH_SECONDS, hash]);
      const user = await this.member(conn, mbId);
      const access = await this.issueAccess(conn, mbId, binding, ip);
      const bridge = await this.issueBridge(conn, mbId, binding, ip);
      return { success: true, access_token: access.token, access_expires_at: access.expires_at, refresh_token: refreshToken, refresh_expires_at: Math.floor(Date.now() / 1000) + REFRESH_SECONDS, session_bridge_token: bridge, user };
    }));
  }

  /** api/app_logout.php (the native part): this device's tokens go, once the caller has shown one of them. */
  async logout(b: { mbId: string; deviceId: string; accessToken: string; refreshToken: string }): Promise<{ success: boolean; error?: string; message?: string }> {
    try {
      if (b.mbId !== '' && isDeviceId(b.deviceId)) {
        const binding = deviceBinding(b.deviceId);
        let own = false;
        if (isToken(b.accessToken)) {
          const [r] = (await this.db.query("SELECT g5_mb_id FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_access' AND user_agent = ? AND expires_at > NOW() LIMIT 1", [sha256(b.accessToken), binding])) as any;
          own = s((r as any[])[0]?.g5_mb_id) === b.mbId;
        }
        if (!own && isToken(b.refreshToken)) {
          const [r] = (await this.db.query("SELECT token FROM sso_tokens WHERE token = ? AND g5_mb_id = ? AND user_type = 'patient_app_remember' AND user_agent = ? AND expires_at > NOW() LIMIT 1", [sha256(b.refreshToken), b.mbId, binding])) as any;
          own = (r as any[]).length > 0;
        }
        if (own) {
          await this.tx(async (conn) => {
            await conn.query("DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_exchange' AND (user_agent = ? OR user_agent IS NULL)", [b.mbId, binding]);
            await conn.query("DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_remember' AND user_agent = ?", [b.mbId, binding]);
            await conn.query("DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type IN ('patient_app_access', 'patient_app_bridge') AND user_agent = ?", [b.mbId, binding]);
          });
          await this.sessions.dropDevice(b.mbId, binding);
          this.recentCodes.delete(`${b.mbId}|${binding}`);
        }
      }
      return { success: true, message: '로그아웃 완료' };
    } catch (e) {
      this.log.error(`logout cleanup failed: ${errTag(e)}`);
      throw new HttpException({ success: false, error: 'logout_unavailable' }, 503);
    }
  }

  /**
   * api/app_auth_bootstrap.php: a signed-in WebView of the app asks for an exchange code (after a login, an upgrade, or when the
   * first exchange was missed). "Signed in" is whatever AuthService says (Nest's sb_sid first); no long-lived token reaches JS.
   */
  async bootstrap(cookie: string | undefined, ip: string): Promise<{ success: true; code: string; device_id: string }> {
    const deviceId = cookieValue(cookie, 'sb_app_device');
    const session = await this.auth.fromCookie(cookie, true);
    if (!session.loggedIn || !session.user || cookieValue(cookie, 'sb_patient_app') !== '1' || !isDeviceId(deviceId)) throw new HttpException({ success: false, error: 'login_required' }, 401);
    try {
      const key = `${session.user.id}|${deviceBinding(deviceId)}`;
      const now = Date.now();
      const hit = this.recentCodes.get(key);
      if (hit && hit.until > now) return { success: true, code: hit.code, device_id: deviceId };
      if (this.recentCodes.size > 5000) this.recentCodes.clear();
      const code = await this.issueExchangeCode(session.user.id, ip);
      this.recentCodes.set(key, { code, until: now + 60_000 });
      return { success: true, code, device_id: deviceId };
    } catch (e) {
      this.log.error(`bootstrap failed: ${errTag(e)}`);
      throw new HttpException({ success: false, error: 'bootstrap_unavailable' }, 503);
    }
  }

  /**
   * app_session_land.php: at a cold start the app puts its one-time bridge token into the sb_sso_token cookie and opens this URL.
   * The token is consumed (device-bound) and the WebView gets Nest's session; a token that is no good just lands signed out.
   * Returns the Set-Cookie headers to send; the caller redirects to the requested page either way.
   */
  async land(cookie: string | undefined, userAgent: string): Promise<string[]> {
    const clearToken = 'sb_sso_token=; Path=/; Max-Age=0; HttpOnly; Secure; SameSite=Lax';
    const token = cookieValue(cookie, 'sb_sso_token'), deviceId = cookieValue(cookie, 'sb_app_device');
    if (!isToken(token)) return [];
    if (!isDeviceId(deviceId)) return [clearToken];
    try {
      const binding = deviceBinding(deviceId);
      const mbId = await this.tx(async (conn) => {
        const [rows] = (await conn.query("SELECT g5_mb_id, user_agent FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_bridge' AND expires_at > NOW() LIMIT 1 FOR UPDATE", [token])) as any;
        const row = (rows as any[])[0];
        if (!row || !s(row.g5_mb_id) || !sameText(s(row.user_agent), binding)) return '';
        const [del] = (await conn.query("DELETE FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_bridge'", [token])) as any;
        if (Number(del?.affectedRows) !== 1) throw new Error('Bridge already consumed');
        await this.member(conn, s(row.g5_mb_id));
        return s(row.g5_mb_id);
      });
      if (!mbId) return [clearToken];
      const own = await this.sessions.create(mbId, { remember: true, app: true, userAgent, source: 'app', replaces: cookie, device: binding });
      if (own) this.log.log('[auth][app_session_restored]');
      return own ? [clearToken, own] : [clearToken];
    } catch (e) {
      if (!(e instanceof Refused)) this.log.error(`land failed: ${errTag(e)}`);
      return [clearToken];
    }
  }
}
