import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, DbConn, DbPool } from '../db/db.module';
import { SessionBridgeService } from '../session-bridge/session-bridge.service';
import { RateWindow } from '../password-lost/password-lost.logic';
import { HttpException } from '@nestjs/common';
import { NAME_RE } from '../register/register.logic';
import { prohibitedMail } from '../register/register.logic';
import { PENDING_COOKIE, profileEmail, readPending, signPending, validEmail } from './social-register.logic';
import { SocialRegisterService } from './social-register.service';
import { kstYmd, safeAfterUrl } from '../login/login.logic';
import { googleProfile, isProvider, kakaoProfile, PHP_NAME, Provider, readState, signState, SocialProfile } from './social.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
export const STATE_COOKIE = 'sb_social_state';

export type CallbackResult =
  | { kind: 'redirect'; to: string; cookies: string[] }
  | { kind: 'php'; provider: Provider; url: string; cookies: string[] };

/**
 * Social sign-in for members that are ALREADY linked (g5_member_social_profiles). A profile that is not linked yet (a new member) is
 * handed back to the PHP flow, which still does the registration. The Gnuboard session is minted by PHP through the bridge with the
 * provider name (ss_social_provider).
 */
@Injectable()
export class SocialService {
  private readonly log = new Logger(SocialService.name);
  private readonly byIp = new RateWindow(30, 600_000);
  private readonly regIp = new RateWindow(10, 600_000);
  constructor(@Inject(DB) private readonly db: DbPool, private readonly bridge: SessionBridgeService, private readonly reg: SocialRegisterService) {}

  private secret(): string { return process.env.SESSION_BRIDGE_SECRET || ''; }
  private phpBase(): string { return (process.env.SOCIAL_PHP_BASE || 'https://hms.seoulbeauty.app').replace(/\/+$/, ''); }
  callbackUrl(p: Provider): string {
    const base = (process.env.SOCIAL_CALLBACK_BASE || 'https://hms.seoulbeauty.app/next/api/v1/auth/social').replace(/\/+$/, '');
    // Kakao's console rejects a redirect URI that contains "kakao": its callback has no provider in the path (the provider comes from the signed state cookie)
    return p === 'kakao' ? `${base}/callback` : `${base}/${p}/callback`;
  }
  /** Providers the login screen sends to Nest (SOCIAL_NEST_PROVIDERS="kakao,google"); empty = everything stays on PHP. */
  enabledProviders(): Provider[] {
    return (process.env.SOCIAL_NEST_PROVIDERS || '').split(',').map((x) => x.trim().toLowerCase()).filter(isProvider);
  }

  private async config(): Promise<Record<string, any>> {
    const [r] = (await this.db.query('SELECT cf_use_email_certify, cf_social_login_use, cf_social_servicelist, cf_kakao_rest_key, cf_kakao_client_secret, cf_google_clientid, cf_google_secret FROM g5_config LIMIT 1')) as any;
    return (r as any[])[0] ?? {};
  }
  private keys(cfg: Record<string, any>, p: Provider): { id: string; secret: string } | null {
    if (!(Number(cfg.cf_social_login_use) > 0)) return null;
    const list = s(cfg.cf_social_servicelist).split(',').map((x) => x.trim().toLowerCase());
    if (!list.includes(p)) return null;
    if (p === 'kakao') return s(cfg.cf_kakao_rest_key) ? { id: s(cfg.cf_kakao_rest_key), secret: s(cfg.cf_kakao_client_secret) !== s(cfg.cf_kakao_rest_key) ? s(cfg.cf_kakao_client_secret) : '' } : null;
    return s(cfg.cf_google_clientid) && s(cfg.cf_google_secret) ? { id: s(cfg.cf_google_clientid), secret: s(cfg.cf_google_secret) } : null;
  }

  private loginPage(error: string): string { return `/next/login?social_error=${encodeURIComponent(error)}`; }
  private phpFlow(p: Provider, url: string): string {
    return `${this.phpBase()}/auth/login.php?provider=${p}${url ? `&url=${encodeURIComponent(url)}` : ''}`;
  }

  /** GET …/:provider/start → { to: IdP authorize URL, cookie } or an error redirect. */
  async start(provider: string, urlParam: unknown, host: string): Promise<{ to: string; cookies: string[] }> {
    if (!isProvider(provider) || !this.enabledProviders().includes(provider) || this.secret().length < 32) return { to: this.loginPage('unavailable'), cookies: [] };
    const cfg = await this.config();
    const k = this.keys(cfg, provider);
    if (!k) return { to: this.loginPage('unavailable'), cookies: [] };
    const url = safeAfterUrl(urlParam, host, '');
    const st = signState(this.secret(), provider, url);
    const q = new URLSearchParams({ client_id: k.id, redirect_uri: this.callbackUrl(provider), response_type: 'code', state: st.state });
    let base: string;
    if (provider === 'kakao') { base = 'https://kauth.kakao.com/oauth/authorize'; q.set('scope', 'profile_nickname,account_email,name,phone_number,birthyear,birthday'); }
    else { base = 'https://accounts.google.com/o/oauth2/v2/auth'; q.set('scope', 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email'); }
    return { to: `${base}?${q.toString()}`, cookies: [`${STATE_COOKIE}=${st.value}; Max-Age=600; Path=/; HttpOnly; Secure; SameSite=Lax`] };
  }

  private async fetchJson(url: string, init: RequestInit): Promise<any> {
    const res = await fetch(url, { ...init, signal: AbortSignal.timeout(15_000) });
    return res.json().catch(() => ({}));
  }
  private async profileFor(provider: Provider, k: { id: string; secret: string }, code: string): Promise<SocialProfile | null> {
    const form = new URLSearchParams({ grant_type: 'authorization_code', client_id: k.id, redirect_uri: this.callbackUrl(provider), code });
    if (k.secret) form.set('client_secret', k.secret); // Kakao: no secret configured = none sent (like Hybrid_Providers_Kakao)
    const tokenUrl = provider === 'kakao' ? 'https://kauth.kakao.com/oauth/token' : 'https://oauth2.googleapis.com/token';
    const tok = await this.fetchJson(tokenUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' }, body: form.toString() });
    const access = s(tok?.access_token);
    if (!access) { this.log.error(`[${provider}] token request failed: ${s(tok?.error)} ${s(tok?.error_description)}`.trim()); return null; }
    if (provider === 'kakao') {
      const keys = JSON.stringify(['kakao_account.profile', 'kakao_account.email', 'kakao_account.name', 'kakao_account.phone_number', 'kakao_account.birthyear', 'kakao_account.birthday']);
      const d = await this.fetchJson('https://kapi.kakao.com/v2/user/me', { method: 'POST', headers: { Authorization: `Bearer ${access}`, 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' }, body: new URLSearchParams({ property_keys: keys }).toString() });
      return kakaoProfile(d);
    }
    return googleProfile(await this.fetchJson('https://www.googleapis.com/oauth2/v3/userinfo', { headers: { Authorization: `Bearer ${access}`, Accept: 'application/json' } }));
  }

  async callback(provider: string, q: { code?: string; state?: string; error?: string }, stateCookie: string, ip: string, cookie: string, userAgent: string, host: string): Promise<CallbackResult> {
    const clear = [`${STATE_COOKIE}=; Max-Age=0; Path=/; HttpOnly; Secure; SameSite=Lax`];
    const fail = (e: string): CallbackResult => ({ kind: 'redirect', to: this.loginPage(e), cookies: clear });
    if (provider === '') return fail('state'); // provider-less callback and no readable state cookie (expired / other browser / in-app WebView)
    if (!isProvider(provider) || !this.enabledProviders().includes(provider) || this.secret().length < 32) return fail('unavailable');
    if (!this.byIp.hit(ip).allowed) return fail('too_many');
    const st = readState(this.secret(), stateCookie, Date.now());
    if (!st || st.p !== provider || !q.state || q.state !== st.s) return fail('state');
    if (q.error || !q.code) return fail('denied');
    const cfg = await this.config();
    const k = this.keys(cfg, provider);
    if (!k) return fail('unavailable');

    let profile: SocialProfile | null;
    try { profile = await this.profileFor(provider, k, q.code); } catch (e) { this.log.error(`[${provider}] ${(e as Error).message}`); profile = null; }
    if (!profile || !profile.identifier) return fail('failed');

    const [rows] = (await this.db.query(
      'SELECT mp.mb_id, m.mb_intercept_date, m.mb_leave_date, m.mb_email_certify FROM g5_member_social_profiles mp JOIN g5_member m ON m.mb_id = mp.mb_id WHERE mp.provider = ? AND mp.identifier = ? AND mp.mb_id <> \'\' ORDER BY mp.mb_id DESC LIMIT 1',
      [provider, profile.identifier])) as any;
    const hit = (rows as any[])[0];
    const ctx = { ip, cookie, userAgent, host };
    if (hit) return this.signIn(provider, hit, profile, st.u, cfg, ctx);
    // not linked yet: a new member. Nest registers it (SOCIAL_NEST_REGISTER=1), otherwise the PHP flow does (it repeats the OAuth step)
    if (process.env.SOCIAL_NEST_REGISTER !== '1') return { kind: 'php', provider, url: st.u, cookies: clear };
    return this.unlinked(provider, profile, st.u, cfg, ctx);
  }


  /** Block / leave dates, e-mail certification, then the session through the PHP bridge. */
  private async signIn(provider: Provider, m: any, profile: SocialProfile, url: string, cfg: Record<string, any>, ctx: { ip: string; cookie: string; userAgent: string; host: string }): Promise<CallbackResult> {
    const clear = [`${STATE_COOKIE}=; Max-Age=0; Path=/; HttpOnly; Secure; SameSite=Lax`];
    const fail = (e: string): CallbackResult => ({ kind: 'redirect', to: this.loginPage(e), cookies: clear });
    const today = kstYmd();
    if ((s(m.mb_intercept_date) !== '' && s(m.mb_intercept_date) <= today) || (s(m.mb_leave_date) !== '' && s(m.mb_leave_date) <= today)) {
      this.log.log(`[auth][social_blocked] provider=${provider}; ip=${ctx.ip}`);
      return fail('blocked');
    }
    // same rule as the e-mail login and PHP login_check.php: with e-mail certification on, an unverified member cannot sign in
    if (Number(cfg.cf_use_email_certify) > 0 && !/[1-9]/.test(s(m.mb_email_certify))) return fail('unverified');
    if (!this.bridge.configured()) return fail('unavailable');
    const isApp = /(?:^|;\s*)sb_patient_app=1(?:;|$)/.test(ctx.cookie);
    const minted = await this.bridge.mint(s(m.mb_id), 'login', isApp, ctx.cookie, ctx.userAgent, PHP_NAME[provider]);
    if (!minted.ok) { this.log.error(`[${provider}] bridge failed: ${minted.error}`); return fail('failed'); }
    await this.reg.saveProfile(s(m.mb_id), provider, profile).catch((e) => this.log.error(`[${provider}] profile save failed: ${(e as Error).message}`));
    this.log.log(`[auth][social_login_success] provider=${provider}; ip=${ctx.ip}`);
    const to = safeAfterUrl(url, ctx.host, '') || '/next'; // no ?url= : the Next home page (the Flutter app and PHP pages always pass one)
    return { kind: 'redirect', to, cookies: [...clear, ...minted.setCookies] };
  }

  /** A social profile that is not linked to a member: Kakao registers at once (consent screen = consent), Google asks for the terms first. */
  private async unlinked(provider: Provider, profile: SocialProfile, url: string, cfg: Record<string, any>, ctx: { ip: string; cookie: string; userAgent: string; host: string }): Promise<CallbackResult> {
    const clear = [`${STATE_COOKIE}=; Max-Age=0; Path=/; HttpOnly; Secure; SameSite=Lax`];
    const fail = (e: string): CallbackResult => ({ kind: 'redirect', to: this.loginPage(e), cookies: clear });
    if (!this.bridge.configured()) return fail('unavailable'); // an account that cannot be signed in must not be created
    if (!(await this.reg.joinCheck(provider, profile.identifier))) return fail('exists');

    if (provider === 'kakao') {
      const m = await this.reg.linkableByVerifiedEmail(profile);
      if (m) {
        await this.reg.saveProfile(m.mb_id, provider, profile).catch((e) => this.log.error(`[kakao] link failed: ${(e as Error).message}`));
        return this.signIn(provider, m, profile, url, cfg, ctx);
      }
      const email = profileEmail(profile, provider);
      const name = (profile.username || profile.displayName || '').replace(/[\x00-\x1F\x7F<>]/g, '').trim().slice(0, 50) || 'Kakao';
      const r = await this.reg.createMember(provider, profile, { name, email, ip: ctx.ip }, cfg);
      if (!r.ok) { this.log.warn(`[kakao] sign-up refused: ${r.error}`); return fail(r.error === 'email_exists' ? 'email_exists' : 'failed'); }
      return this.afterSignUp(provider, r.mbId, profile, ctx, clear);
    }

    // Google (and later Apple without a name): carry the profile to the sign-up form in a signed cookie
    const value = signPending(this.secret(), { p: provider, i: profile.identifier, n: profile.username || profile.displayName, e: profileEmail(profile, provider), ev: profile.emailVerified !== '', ph: profile.photoURL.slice(0, 200), u: url });
    return { kind: 'redirect', to: '/next/register/social', cookies: [...clear, `${PENDING_COOKIE}=${value}; Max-Age=900; Path=/; HttpOnly; Secure; SameSite=Lax`] };
  }

  /** After the row exists: link the profile, then the session + sign-up bonus + HMS sync through the bridge (register mode). */
  private async afterSignUp(provider: Provider, mbId: string, profile: SocialProfile, ctx: { ip: string; cookie: string; userAgent: string }, clear: string[]): Promise<CallbackResult> {
    await this.reg.saveProfile(mbId, provider, profile).catch((e) => this.log.error(`[${provider}] profile save failed for ${mbId}: ${(e as Error).message}`));
    const minted = await this.bridge.mint(mbId, 'register', false, ctx.cookie, ctx.userAgent, PHP_NAME[provider]);
    this.log.log(`[auth][social_register_success] provider=${provider}; ip=${ctx.ip}${minted.ok ? '' : '; session=failed'}`);
    if (!minted.ok) return { kind: 'redirect', to: this.loginPage('failed'), cookies: clear };
    return { kind: 'redirect', to: '/index.php', cookies: [...clear, ...minted.setCookies] }; // the PHP home shows the welcome-point notice
  }

  /** GET …/social/pending: what the sign-up form shows (never the profile identifier). */
  pending(pendingCookie: string): { pending: boolean; provider?: string; name?: string; email?: string } {
    const d = readPending(this.secret(), pendingCookie, Date.now());
    return d ? { pending: true, provider: d.p, name: d.n, email: d.e.endsWith('@social.local') ? '' : d.e } : { pending: false };
  }

  /** POST …/social/register: the Google sign-up form. */
  async registerFromPending(b: { name?: unknown; email?: unknown; agreeTerms?: unknown; agreePrivacy?: unknown }, pendingCookie: string, ip: string, cookie: string, userAgent: string): Promise<{ loggedIn: boolean; cookies: string[] }> {
    const bad = (message: string, status = 400): never => { throw new HttpException({ success: false, message }, status); };
    const clearPending = `${PENDING_COOKIE}=; Max-Age=0; Path=/; HttpOnly; Secure; SameSite=Lax`;
    if (!this.regIp.hit(ip).allowed) bad('잠시 후 다시 시도해주세요.', 429);
    const d = readPending(this.secret(), pendingCookie, Date.now());
    if (!d) bad('로그인 시간이 만료되었습니다. 다시 시도해주세요.', 410);
    const yes = (v: unknown) => v === true || s(v) === '1';
    if (!yes(b.agreeTerms) || !yes(b.agreePrivacy)) bad('회원가입 약관과 개인정보 수집·이용에 동의해주세요.');
    const name = s(b.name).replace(/[\x00-\x1F\x7F<>]/g, '').trim();
    const email = s(b.email).trim().toLowerCase();
    if (!NAME_RE.test(name)) bad('이름을 2~50자의 올바른 형식으로 입력해주세요.');
    if (!validEmail(email)) bad('올바른 이메일 주소를 입력해주세요.');
    const cfg = await this.reg.config();
    const policy = prohibitedMail(email, s(cfg.cf_prohibit_email));
    if (policy) bad(policy);
    if (!this.bridge.configured()) bad('지금은 회원가입을 처리할 수 없습니다. 잠시 후 다시 시도해주세요.', 503);
    const provider = d!.p;
    if (!(await this.reg.joinCheck(provider, d!.i))) bad('이미 연결 또는 가입된 소셜 계정입니다. 로그인 후 계정 연결을 이용해주세요.', 409);
    const profile: SocialProfile = { identifier: d!.i, displayName: d!.n, photoURL: d!.ph, profileURL: '', email: d!.e, emailVerified: d!.ev ? d!.e : '', username: d!.n, phone: '', birthYear: null, birthMonth: null, birthDay: null, age: null };
    const r = await this.reg.createMember(provider, profile, { name, email, ip }, cfg);
    if (!r.ok) {
      if (r.error === 'email_exists') bad('이미 가입된 이메일입니다. 다른 이메일을 입력하거나 이메일로 로그인해주세요.', 409);
      if (r.error === 'prohibited_email' || r.error === 'invalid_email') bad(r.message || '올바른 이메일 주소를 입력해주세요.');
      bad('가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', r.error === 'busy' ? 503 : 500);
    }
    const created = r as { ok: true; mbId: string };
    await this.reg.saveProfile(created.mbId, provider, profile).catch((e) => this.log.error(`[${provider}] profile save failed for ${created.mbId}: ${(e as Error).message}`));
    const minted = await this.bridge.mint(created.mbId, 'register', false, cookie, userAgent, PHP_NAME[provider]);
    this.log.log(`[auth][social_register_success] provider=${provider}; ip=${ip}${minted.ok ? '' : '; session=failed'}`);
    return { loggedIn: minted.ok, cookies: [clearPending, ...minted.setCookies] };
  }

  /** Provider of a pending sign-in, read from the signed state cookie (for the provider-less Kakao callback). */
  providerFromState(stateCookie: string): string {
    return readState(this.secret(), stateCookie, Date.now())?.p ?? '';
  }

  phpRedirect(p: Provider, url: string): string { return this.phpFlow(p, url); }
}
