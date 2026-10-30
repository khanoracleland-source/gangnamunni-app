import { HttpException, Inject, Injectable, Logger } from '@nestjs/common';
import { createHash as sha } from 'crypto';
import { DB, DbPool } from '../db/db.module';
import { SessionBridgeService } from '../session-bridge/session-bridge.service';
import { RateWindow } from '../password-lost/password-lost.logic';
import { cookieValue, kstYmd, passwordMatches, safeAfterUrl } from './login.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const fail = (message: string, status: number, extra: Record<string, unknown> = {}): never => { throw new HttpException({ success: false, message, ...extra }, status); };
const WRONG = '이메일 또는 비밀번호가 올바르지 않습니다.';

/**
 * gangnamunni/auth/login.php (mode=email). Wrong passwords are counted per ip (10/15 min), ip+e-mail (5/15 min) and e-mail (20/h);
 * every refusal says the same thing. The password is checked here, the Gnuboard session (and the remember cookies, and the HMS/SSO
 * login hooks) is minted by PHP through the bridge. Counters live in memory (a restart clears them; app_login.php keeps its own).
 */
@Injectable()
export class LoginService {
  private readonly log = new Logger(LoginService.name);
  private readonly byIp = new RateWindow(10, 900_000);
  private readonly byIpEmail = new RateWindow(5, 900_000);
  private readonly byEmail = new RateWindow(20, 3_600_000);
  constructor(@Inject(DB) private readonly db: DbPool, private readonly bridge: SessionBridgeService) {}

  /** Which social buttons the login screen shows (same conditions as auth/login.php). */
  async options(): Promise<{ kakao: boolean; google: boolean; apple: boolean }> {
    const [r] = (await this.db.query('SELECT cf_social_login_use, cf_social_servicelist, cf_kakao_rest_key, cf_google_clientid, cf_google_secret, cf_apple_team_id, cf_apple_key_id, cf_apple_bundle_id, cf_apple_key_file FROM g5_config LIMIT 1')) as any;
    const c = (r as any[])[0] ?? {};
    const on = Number(c.cf_social_login_use) > 0;
    const list = s(c.cf_social_servicelist).split(',').map((x) => x.trim().toLowerCase());
    const has = (...k: string[]) => k.every((x) => s(c[x]) !== '');
    return {
      kakao: on && list.includes('kakao') && has('cf_kakao_rest_key'),
      google: on && list.includes('google') && has('cf_google_clientid', 'cf_google_secret'),
      apple: on && list.includes('apple') && has('cf_apple_team_id', 'cf_apple_key_id', 'cf_apple_bundle_id', 'cf_apple_key_file'),
    };
  }

  private audit(event: string, email: string, ip: string, details = ''): void {
    this.log.log(`[auth][${event}] account=${email ? sha('sha256').update(email).digest('hex').slice(0, 16) : '-'}; ip=${ip}${details ? `; ${details}` : ''}`);
  }
  private count(email: string, ip: string): void { this.byIp.add(ip); this.byIpEmail.add(`${ip}|${email}`); this.byEmail.add(email); }

  async login(b: { email?: unknown; password?: unknown; remember?: unknown; url?: unknown }, ip: string, cookie: string, host: string, userAgent: string): Promise<{ redirect: string | null; cookies: string[] }> {
    const email = s(b.email).trim().toLowerCase();
    const password = s(b.password); // not trimmed, like the web form and app_login.php
    if (!email || !password) fail('이메일과 비밀번호를 입력해주세요.', 400);
    const rates = [this.byIp.peek(ip), this.byIpEmail.peek(`${ip}|${email}`), this.byEmail.peek(email)];
    if (rates.some((r) => !r.allowed)) {
      const retry = Math.max(...rates.map((r) => r.retry));
      this.audit('rate_limited', email, ip, `retry=${retry}`);
      fail(`로그인 시도가 너무 많습니다. ${retry}초 후 다시 시도해주세요.`, 429);
    }
    if (!this.bridge.configured()) { this.log.error('SESSION_BRIDGE_SECRET is not set'); fail('지금은 로그인할 수 없습니다. 잠시 후 다시 시도해주세요.', 503); }

    const [rows] = (await this.db.query(
      'SELECT mb_id, mb_password, mb_intercept_date, mb_leave_date, mb_email_certify FROM g5_member WHERE LOWER(mb_email) = LOWER(?) LIMIT 1', [email])) as any;
    const mb = (rows as any[])[0];
    const hash = s(mb?.mb_password);
    // Hashes that are not Gnuboard PBKDF2 (bcrypt from HMS-created accounts, 1 old md5) cannot be verified by PHP login either: plain wrong password.
    if (!mb || !passwordMatches(password, hash)) {
      this.count(email, ip);
      this.audit('password_failed', email, ip);
      fail(WRONG, 401);
    }
    const today = kstYmd();
    const [cfgRows] = (await this.db.query('SELECT cf_use_email_certify FROM g5_config LIMIT 1')) as any;
    const useCertify = Number((cfgRows as any[])[0]?.cf_use_email_certify) > 0;
    const blocked = s(mb.mb_intercept_date) !== '' && s(mb.mb_intercept_date) <= today;
    const left = s(mb.mb_leave_date) !== '' && s(mb.mb_leave_date) <= today;
    const unverified = useCertify && !/[1-9]/.test(s(mb.mb_email_certify));
    if (blocked || left || unverified) {
      this.byEmail.add(email);
      this.audit(blocked ? 'account_blocked' : left ? 'account_deleted' : 'email_unverified', email, ip);
      fail(WRONG, 401);
    }

    const isApp = cookieValue(cookie, 'sb_patient_app') === '1';
    const remember = isApp || b.remember === true || s(b.remember) === '1';
    const minted = await this.bridge.mint(s(mb.mb_id), 'login', remember, cookie, userAgent);
    if (!minted.ok) { this.audit('bridge_failed', email, ip, minted.error ?? ''); fail('로그인 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 502); }
    this.audit('login_success', email, ip, remember ? 'remember=1' : 'remember=0');
    const after = safeAfterUrl(b.url, host, '');
    return { redirect: after || null, cookies: minted.setCookies };
  }
}
