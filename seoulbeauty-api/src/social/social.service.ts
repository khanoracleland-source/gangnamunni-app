import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, DbConn, DbPool } from '../db/db.module';
import { SessionBridgeService } from '../session-bridge/session-bridge.service';
import { RateWindow } from '../password-lost/password-lost.logic';
import { kstYmd, safeAfterUrl } from '../login/login.logic';
import { googleProfile, isProvider, kakaoMemberUpdates, kakaoProfile, objectSha, PHP_NAME, Provider, readState, signState, SocialProfile } from './social.logic';

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
  constructor(@Inject(DB) private readonly db: DbPool, private readonly bridge: SessionBridgeService) {}

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
    // not linked yet: the PHP flow registers the member (it repeats the OAuth step, which is still registered at the provider)
    if (!hit) return { kind: 'php', provider, url: st.u, cookies: clear };
    const today = kstYmd();
    if ((s(hit.mb_intercept_date) !== '' && s(hit.mb_intercept_date) <= today) || (s(hit.mb_leave_date) !== '' && s(hit.mb_leave_date) <= today)) {
      this.log.log(`[auth][social_blocked] provider=${provider}; ip=${ip}`);
      return fail('blocked');
    }
    // same rule as the e-mail login and PHP login_check.php: with e-mail certification on, an unverified member cannot sign in
    if (Number(cfg.cf_use_email_certify) > 0 && !/[1-9]/.test(s(hit.mb_email_certify))) return fail('unverified');
    if (!this.bridge.configured()) return fail('unavailable');
    const isApp = /(?:^|;\s*)sb_patient_app=1(?:;|$)/.test(cookie);
    const minted = await this.bridge.mint(s(hit.mb_id), 'login', isApp, cookie, userAgent, PHP_NAME[provider]);
    if (!minted.ok) { this.log.error(`[${provider}] bridge failed: ${minted.error}`); return fail('failed'); }
    await this.saveProfile(s(hit.mb_id), provider, profile).catch((e) => this.log.error(`[${provider}] profile save failed: ${(e as Error).message}`));
    this.log.log(`[auth][social_login_success] provider=${provider}; ip=${ip}`);
    const to = safeAfterUrl(st.u, host, '') || '/next'; // no ?url= : the Next home page (the Flutter app and PHP pages always pass one)
    return { kind: 'redirect', to, cookies: [...clear, ...minted.setCookies] };
  }

  /** social_user_profile_replace() + (Kakao) social_sync_kakao_member_profile(). Best effort: the member is already signed in. */
  private async saveProfile(mbId: string, provider: Provider, p: SocialProfile): Promise<void> {
    if (!this.db.getConnection) return;
    const conn: DbConn = await this.db.getConnection();
    try {
      await conn.query('DELETE FROM g5_member_social_profiles WHERE provider = ? AND identifier = ? AND mb_id <> ?', [provider, p.identifier, mbId]);
      const [ex] = (await conn.query('SELECT mp_no FROM g5_member_social_profiles WHERE mb_id = ? AND provider = ? AND identifier = ? LIMIT 1', [mbId, provider, p.identifier])) as any;
      const row = (ex as any[])[0];
      const vals = [objectSha(p), p.profileURL.slice(0, 255), p.photoURL.slice(0, 255), p.displayName.slice(0, 150)];
      if (row) await conn.query('UPDATE g5_member_social_profiles SET object_sha = ?, profileurl = ?, photourl = ?, displayname = ?, mp_latest_day = NOW() WHERE mp_no = ?', [...vals, row.mp_no]);
      else await conn.query('INSERT INTO g5_member_social_profiles (mb_id, provider, identifier, object_sha, profileurl, photourl, displayname, mp_register_day, mp_latest_day) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [mbId, provider, p.identifier, ...vals]);
      if (provider === 'kakao') {
        const u = kakaoMemberUpdates(p, new Date().getFullYear());
        const cols = Object.keys(u);
        if (cols.length) await conn.query(`UPDATE g5_member SET ${cols.map((c) => `${c} = ?`).join(', ')} WHERE mb_id = ?`, [...cols.map((c) => u[c]), mbId]);
      }
    } finally { conn.release(); }
  }

  /** Provider of a pending sign-in, read from the signed state cookie (for the provider-less Kakao callback). */
  providerFromState(stateCookie: string): string {
    return readState(this.secret(), stateCookie, Date.now())?.p ?? '';
  }

  phpRedirect(p: Provider, url: string): string { return this.phpFlow(p, url); }
}
