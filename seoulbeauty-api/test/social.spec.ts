import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { SessionBridgeService } from '../src/session-bridge/session-bridge.service';
import { SocialModule } from '../src/social/social.module';
import { MailService } from '../src/mail/mail.service';
import { googleProfile, kakaoMemberUpdates, kakaoProfile, normalizeKakaoPhone, readState, signState } from '../src/social/social.logic';
import { createPublicKey, generateKeyPairSync, sign as cryptoSign, verify as cryptoVerify } from 'crypto';
import { APPLE_RELAY_COOKIE, appleClientSecret, appleProfile, appleUserName, readAppleRelay, signAppleRelay, verifyAppleIdToken } from '../src/social/apple.logic';
import { adler32, candidates, convertId, profileEmail, readPending, signPending, socialNick, validEmail } from '../src/social/social-register.logic';

const SECRET = 'x'.repeat(40);

// Apple test material: our own .p8-style EC key (client secret) and an RSA key that plays Apple's signing key
const appleEc = generateKeyPairSync('ec', { namedCurve: 'P-256' });
const APPLE_PEM = appleEc.privateKey.export({ type: 'pkcs8', format: 'pem' }) as string;
const appleRsa = generateKeyPairSync('rsa', { modulusLength: 2048 });
const APPLE_JWK = { ...(appleRsa.publicKey.export({ format: 'jwk' }) as any), kid: 'K1', alg: 'RS256', use: 'sig' };
const b64u = (v: any) => Buffer.from(typeof v === 'string' ? v : JSON.stringify(v)).toString('base64url');
function idToken(claims: Record<string, any>, kid = 'K1', key = appleRsa.privateKey): string {
  const head = b64u({ alg: 'RS256', kid }); const body = b64u({ iss: 'https://appleid.apple.com', aud: 'app.web', exp: Math.floor(Date.now() / 1000) + 600, ...claims });
  return `${head}.${body}.${cryptoSign('RSA-SHA256', Buffer.from(`${head}.${body}`), key).toString('base64url')}`;
}

describe('social logic', () => {
  it('kakao profile and member updates', () => {
    const p = kakaoProfile({ id: 123, kakao_account: { profile: { nickname: 'Nick' }, email: 'a@b.com', is_email_verified: true, name: '홍길동', phone_number: '+82 10-1234-5678', birthyear: '1990', birthday: '0229' } })!;
    expect(p.identifier).toBe('123');
    expect(p.birthMonth).toBe(2);
    const u = kakaoMemberUpdates(p, 2026);
    expect(u.mb_name).toBe('홍길동');
    expect(u.mb_hp).toBe('01012345678');
    expect(u.mb_birth).toBeUndefined(); // 1990-02-29 does not exist
    expect(kakaoProfile({})).toBeNull();
    expect(normalizeKakaoPhone('abc')).toBe('');
  });
  it('google profile uses sub', () => {
    expect(googleProfile({ sub: '9', name: 'N', email: 'e@x.com', email_verified: true })!.identifier).toBe('9');
    expect(googleProfile({ id: '9' })).toBeNull();
  });
  it('state cookie is signed and expires', () => {
    const { value } = signState(SECRET, 'kakao', '/a', 1000);
    expect(readState(SECRET, value, 2000)?.p).toBe('kakao');
    expect(readState(SECRET, value, 2_000_000)).toBeNull();
    expect(readState('y'.repeat(40), value, 2000)).toBeNull();
    expect(readState(SECRET, value.slice(0, -2) + 'AA', 2000)).toBeNull();
    expect(readState(SECRET, 'junk', 2000)).toBeNull();
  });
});

describe('social sign-up logic', () => {
  it('adler32 matches the standard vector and convertId has the Gnuboard shape', () => {
    expect(adler32('Wikipedia')).toBe('11e60398');
    expect(convertId('abc', 'Google')).toMatch(/^google_[0-9a-f]{8}$/);
    expect(convertId('abc', 'Kakao')).toBe(convertId('abc', 'kakao'));
  });
  it('nick, e-mail and candidates', () => {
    expect(socialNick('홍 길-동_1 😀')).toBe('홍길동1');
    expect(profileEmail({ identifier: '1', displayName: '', photoURL: '', profileURL: '', email: 'A@B.com', emailVerified: '', username: '', phone: '', birthYear: null, birthMonth: null, birthDay: null, age: null }, 'google')).toBe('a@b.com');
    expect(profileEmail({ identifier: '1', displayName: '', photoURL: '', profileURL: '', email: '', emailVerified: '', username: '', phone: '', birthYear: null, birthMonth: null, birthDay: null, age: null }, 'kakao')).toMatch(/^kakao_kakao_[0-9a-f]{8}@social\.local$/);
    expect(validEmail('a@b.co')).toBe(true); expect(validEmail('a@b')).toBe(false);
    expect(candidates('x', 3)).toEqual(['x', 'x1', 'x2']);
  });
  it('pending cookie is signed and expires', () => {
    const v = signPending(SECRET, { p: 'google', i: 'sub1', n: 'N', e: 'a@b.com', ev: true, ph: '', u: '' }, 1000);
    expect(readPending(SECRET, v, 2000)?.i).toBe('sub1');
    expect(readPending(SECRET, v, 2_000_000)).toBeNull();
    expect(readPending('y'.repeat(40), v, 2000)).toBeNull();
    expect(readPending(SECRET, v.slice(0, -2) + 'AA', 2000)).toBeNull();
  });
});

describe('social controller', () => {
  let app: INestApplication;
  let socialRows: any[] = []; let memberByEmail: any = null; let inserted: any[] = []; let emailTaken = false; let linked: any; let certify = 0; let kakaoSecret = 'cs'; let tokenBodies: string[] = []; let calls: { sql: string; args: any[] }[] = []; let bridge: any[] = []; let bridgeOk = true;
  const realFetch = global.fetch;
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/GET_LOCK/.test(sql)) return [[{ got: 1 }]];
    if (/FROM g5_member_social_profiles WHERE provider = \? AND identifier = \?/.test(sql)) return [socialRows];
    if (/FROM g5_member WHERE LOWER\(mb_email\) = \? LIMIT 1/.test(sql) && /mb_intercept_date/.test(sql)) return [memberByEmail ? [memberByEmail] : []];
    if (/FROM g5_member WHERE LOWER\(mb_email\) = \? LIMIT 1/.test(sql)) return [emailTaken ? [{ mb_no: 1 }] : []];
    if (/FROM gangnamunni_hospitals/.test(sql)) return [[]];
    if (/FROM g5_member_social_profiles WHERE mb_id = \? AND provider = 'kakao'/.test(sql)) return [[]];
    if (/INSERT INTO g5_member \(/.test(sql)) { inserted.push({ sql, args }); return [{ affectedRows: 1 }]; }
    if (/FROM g5_config/.test(sql)) return [[{ cf_register_level: 2, cf_prohibit_id: 'admin,root', cf_prohibit_email: '', cf_title: 'T', cf_email_mb_super_admin: 0, cf_admin_email: '', cf_use_email_certify: certify, cf_social_login_use: 1, cf_social_servicelist: 'kakao,google,Apple', cf_kakao_rest_key: 'rk', cf_kakao_client_secret: kakaoSecret, cf_google_clientid: 'gid', cf_google_secret: 'gs', cf_apple_team_id: 'TEAM', cf_apple_key_id: 'KEYID', cf_apple_bundle_id: 'app.web', cf_apple_key_file: APPLE_PEM.replace(/\n/g, '\r\n') }]];
    if (/FROM g5_member_social_profiles mp JOIN/.test(sql)) return [linked ? [linked] : []];
    return [[]];
  };
  const stateFor = (p: 'kakao' | 'google' | 'apple', url = '/next/mypage') => signState(SECRET, p, url);
  const mockFetch = (profile: any) => {
    global.fetch = (async (u: any, init?: any) => {
      const url = String(u);
      if (/token/.test(url)) tokenBodies.push(String(init?.body));
      const body = /token/.test(url) ? { access_token: 'tok' } : profile;
      return { json: async () => body } as any;
    }) as any;
  };
  beforeAll(async () => {
    process.env.SESSION_BRIDGE_SECRET = SECRET; process.env.SOCIAL_NEST_PROVIDERS = 'kakao,google';
    process.env.SOCIAL_CALLBACK_BASE = 'https://h.test/next/api/v1/auth/social'; process.env.SOCIAL_PHP_BASE = 'https://h.test';
    const conn = { query: async (sql: string, args: any[]) => respond(sql, args), release: () => undefined };
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args), getConnection: async () => conn } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, SocialModule] })
      .overrideProvider(MailService).useValue({ send: async () => ({ success: true }) })
      .overrideProvider(SessionBridgeService).useValue({ configured: () => true, mint: async (...a: any[]) => { bridge.push(a); return bridgeOk ? { ok: true, setCookies: ['PHPSESSID=abc; path=/'] } : { ok: false, setCookies: [], error: 'x' }; } })
      .compile();
    app = mod.createNestApplication();
    (app.getHttpAdapter().getInstance() as any).set('trust proxy', 1);
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => { calls = []; bridge = []; tokenBodies = []; certify = 0; kakaoSecret = 'cs'; socialRows = []; memberByEmail = null; inserted = []; emailTaken = false; delete process.env.SOCIAL_NEST_REGISTER; bridgeOk = true; linked = { mb_id: 'sb_abc', mb_intercept_date: '', mb_leave_date: '' }; });

  it('start redirects to the provider with a state cookie', async () => {
    const r = await request(app.getHttpServer()).get('/v1/auth/social/kakao/start?url=/next/mypage').set('X-Forwarded-For', '2.2.2.2').expect(302);
    const loc = new URL(r.headers.location);
    expect(loc.host).toBe('kauth.kakao.com');
    expect(loc.searchParams.get('redirect_uri')).toBe('https://h.test/next/api/v1/auth/social/callback');
    expect(String(r.headers['set-cookie'])).toMatch(/sb_social_state=.*HttpOnly/);
    const g = await request(app.getHttpServer()).get('/v1/auth/social/google/start').expect(302);
    expect(g.headers.location).toMatch(/^https:\/\/accounts\.google\.com\//);
  });
  it('a host listed in SOCIAL_CALLBACK_HOSTS is its own callback host; any other keeps SOCIAL_CALLBACK_BASE', async () => {
    const uri = async (path: string, host: string) => new URL((await request(app.getHttpServer()).get(path).set('Host', host).expect(302)).headers.location).searchParams.get('redirect_uri');
    // not listed (the default): one callback host for everybody
    expect(await uri('/v1/auth/social/google/start', 'seoulbeauty.app')).toBe('https://h.test/next/api/v1/auth/social/google/callback');
    process.env.SOCIAL_CALLBACK_HOSTS = 'seoulbeauty.app, HMS.seoulbeauty.app';
    try {
      expect(await uri('/v1/auth/social/google/start', 'seoulbeauty.app')).toBe('https://seoulbeauty.app/next/api/v1/auth/social/google/callback');
      expect(await uri('/v1/auth/social/kakao/start', 'hms.seoulbeauty.app:443')).toBe('https://hms.seoulbeauty.app/next/api/v1/auth/social/callback');
      // a host that is not on the list (or is not a host name at all) never becomes a redirect URI
      expect(await uri('/v1/auth/social/google/start', 'evil.test')).toBe('https://h.test/next/api/v1/auth/social/google/callback');
      expect(await uri('/v1/auth/social/google/start', 'seoulbeauty.app.evil.test')).toBe('https://h.test/next/api/v1/auth/social/google/callback');
      // Apple's form_post is relayed to the GET callback of the host it arrived on
      const relay = await request(app.getHttpServer()).post('/v1/auth/social/apple/callback').set('Host', 'seoulbeauty.app').type('form').send({ code: 'c', state: 's' }).expect(303);
      expect(relay.headers.location).toBe('https://seoulbeauty.app/next/api/v1/auth/social/apple/callback');
    } finally { delete process.env.SOCIAL_CALLBACK_HOSTS; }
  });

  it('start refuses unknown or disabled providers', async () => {
    const r = await request(app.getHttpServer()).get('/v1/auth/social/naver/start').expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=unavailable');
    // Apple is a known provider that is not switched on here: the PHP flow keeps it
    const a = await request(app.getHttpServer()).get('/v1/auth/social/apple/start').expect(302);
    expect(a.headers.location).toBe('https://h.test/auth/login.php?provider=apple&php=1');
  });
  it('start sends a provider that is not on Nest to the PHP flow (php=1)', async () => {
    const saved = process.env.SOCIAL_NEST_PROVIDERS;
    process.env.SOCIAL_NEST_PROVIDERS = 'google';
    const r = await request(app.getHttpServer()).get('/v1/auth/social/kakao/start?url=/next/x').expect(302);
    expect(r.headers.location).toBe('https://h.test/auth/login.php?provider=kakao&url=%2Fnext%2Fx&php=1');
    expect(r.headers['set-cookie']).toBeUndefined();
    process.env.SOCIAL_NEST_PROVIDERS = saved;
  });
  it('callback rejects a bad or missing state', async () => {
    const st = stateFor('kakao');
    let r = await request(app.getHttpServer()).get(`/v1/auth/social/kakao/callback?code=c&state=wrong`).set('Cookie', `sb_social_state=${st.value}`).expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=state');
    r = await request(app.getHttpServer()).get(`/v1/auth/social/kakao/callback?code=c&state=${st.state}`).expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=state');
    const g = stateFor('google');
    r = await request(app.getHttpServer()).get(`/v1/auth/social/kakao/callback?code=c&state=${g.state}`).set('Cookie', `sb_social_state=${g.value}`).expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=state');
    expect(bridge).toHaveLength(0);
  });
  it('callback: provider denied', async () => {
    const st = stateFor('kakao');
    const r = await request(app.getHttpServer()).get(`/v1/auth/social/callback?error=access_denied&state=${st.state}`).set('Cookie', `sb_social_state=${st.value}`).expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=denied');
  });
  it('callback: linked kakao member is signed in through the bridge', async () => {
    mockFetch({ id: 77, kakao_account: { profile: { nickname: 'N' }, name: '홍길동' } });
    const st = stateFor('kakao', '/mypage.php');
    const r = await request(app.getHttpServer()).get(`/v1/auth/social/callback?code=c&state=${st.state}`).set('Cookie', `sb_social_state=${st.value}; sb_patient_app=1`).set('Host', 'h.test').expect(302);
    expect(r.headers.location).toBe('/mypage.php');
    expect(bridge[0].slice(0, 3)).toEqual(['sb_abc', 'login', true]);
    expect(bridge[0][5]).toBe('Kakao');
    expect(String(r.headers['set-cookie'])).toMatch(/PHPSESSID=abc/);
    expect(calls.some((c) => /UPDATE g5_member SET mb_name = \?/.test(c.sql))).toBe(true);
    expect(calls.find((c) => /FROM g5_member_social_profiles mp JOIN/.test(c.sql))!.args).toEqual(['kakao', '77']);
  });
  it('callback: google lookup uses sub and does not sync member fields', async () => {
    mockFetch({ sub: 'g-1', name: 'G', email: 'g@x.com', email_verified: true });
    const st = stateFor('google', '');
    const r = await request(app.getHttpServer()).get(`/v1/auth/social/google/callback?code=c&state=${st.state}`).set('Cookie', `sb_social_state=${st.value}`).expect(302);
    expect(r.headers.location).toBe('/next'); // no ?url= given
    expect(bridge[0][5]).toBe('Google');
    expect(calls.some((c) => /UPDATE g5_member SET/.test(c.sql))).toBe(false);
  });
  it('callback: unlinked profile falls back to the PHP flow', async () => {
    linked = null; mockFetch({ id: 5, kakao_account: {} });
    const st = stateFor('kakao', '/next/x');
    const r = await request(app.getHttpServer()).get(`/v1/auth/social/callback?code=c&state=${st.state}`).set('Cookie', `sb_social_state=${st.value}`).expect(302);
    expect(r.headers.location).toBe('https://h.test/auth/login.php?provider=kakao&url=%2Fnext%2Fx');
    expect(bridge).toHaveLength(0);
  });
  it('callback: blocked or left member is refused; token failure and bridge failure are generic', async () => {
    mockFetch({ id: 5 });
    const st = () => stateFor('kakao');
    linked = { mb_id: 'sb_abc', mb_intercept_date: '20000101', mb_leave_date: '' };
    let s = st();
    let r = await request(app.getHttpServer()).get(`/v1/auth/social/callback?code=c&state=${s.state}`).set('Cookie', `sb_social_state=${s.value}`);
    expect(r.headers.location).toBe('/next/login?social_error=blocked');
    linked = { mb_id: 'sb_abc', mb_intercept_date: '', mb_leave_date: '' }; bridgeOk = false;
    s = st();
    r = await request(app.getHttpServer()).get(`/v1/auth/social/callback?code=c&state=${s.state}`).set('Cookie', `sb_social_state=${s.value}`);
    expect(r.headers.location).toBe('/next/login?social_error=failed');
    global.fetch = (async () => ({ json: async () => ({ error: 'invalid_grant' }) })) as any;
    s = st();
    r = await request(app.getHttpServer()).get(`/v1/auth/social/callback?code=c&state=${s.state}`).set('Cookie', `sb_social_state=${s.value}`);
    expect(r.headers.location).toBe('/next/login?social_error=failed');
  });
  it('callback: unverified e-mail is refused when certification is on', async () => {
    mockFetch({ id: 5 }); certify = 1;
    linked = { mb_id: 'sb_abc', mb_intercept_date: '', mb_leave_date: '', mb_email_certify: '0000-00-00 00:00:00' };
    const s = stateFor('kakao');
    const r = await request(app.getHttpServer()).get(`/v1/auth/social/callback?code=c&state=${s.state}`).set('Cookie', `sb_social_state=${s.value}`);
    expect(r.headers.location).toBe('/next/login?social_error=unverified');
    expect(bridge).toHaveLength(0);
  });
  it('kakao client_secret: sent only when set and different from the rest key', async () => {
    mockFetch({ id: 5 });
    const s = stateFor('kakao');
    await request(app.getHttpServer()).get(`/v1/auth/social/callback?code=c&state=${s.state}`).set('Cookie', `sb_social_state=${s.value}`);
    expect(tokenBodies[0]).toContain('client_secret=cs');
  });
  it('kakao client_secret is omitted when empty or equal to the rest key', async () => {
    for (const sec of ['', 'rk']) {
      kakaoSecret = sec; tokenBodies = [];
      mockFetch({ id: 5 });
      const s = stateFor('kakao');
      await request(app.getHttpServer()).get(`/v1/auth/social/callback?code=c&state=${s.state}`).set('Cookie', `sb_social_state=${s.value}`);
      expect(tokenBodies[0]).toContain('client_id=rk');
      expect(tokenBodies[0]).not.toContain('client_secret');
    }
  });
  it('google keeps its provider path and the provider-less callback refuses a missing state', async () => {
    const g = await request(app.getHttpServer()).get('/v1/auth/social/google/start').expect(302);
    expect(new URL(g.headers.location).searchParams.get('redirect_uri')).toBe('https://h.test/next/api/v1/auth/social/google/callback');
    // no / expired / foreign state cookie on the provider-less callback = "login time expired", not "unavailable"
    let r = await request(app.getHttpServer()).get('/v1/auth/social/callback?code=c&state=x').expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=state');
    const old = signState(SECRET, 'kakao', '/a', Date.now() - 700_000);
    r = await request(app.getHttpServer()).get('/v1/auth/social/callback?code=c&state=x').set('Cookie', `sb_social_state=${old.value}`).expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=state');
    r = await request(app.getHttpServer()).get('/v1/auth/social/apple/callback?code=c&state=x').expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=unavailable');
  });

  const cb = (p: 'kakao' | 'google', st: { value: string; state: string }) =>
    request(app.getHttpServer()).get(p === 'kakao' ? `/v1/auth/social/callback?code=c&state=${st.state}` : `/v1/auth/social/google/callback?code=c&state=${st.state}`).set('Cookie', `sb_social_state=${st.value}`);

  it('sign-up flag off: an unlinked Kakao profile stays on the PHP flow (no insert)', async () => {
    linked = null; mockFetch({ id: 5, kakao_account: { email: 'k@x.com', is_email_verified: true } });
    const r = await cb('kakao', stateFor('kakao', '/next/x')).expect(302);
    expect(r.headers.location).toMatch(/^https:\/\/h\.test\/auth\/login\.php\?provider=kakao/);
    expect(inserted).toHaveLength(0);
  });
  it('sign-up flag on: Kakao registers a new member at once and mints a register session', async () => {
    process.env.SOCIAL_NEST_REGISTER = '1';
    linked = null; mockFetch({ id: 5, kakao_account: { profile: { nickname: 'Nick' }, name: '홍길동', phone_number: '+82 10-1111-2222', email: 'k@x.com' } });
    const r = await cb('kakao', stateFor('kakao')).expect(302);
    expect(r.headers.location).toBe('/next');
    expect(inserted).toHaveLength(1);
    expect(inserted[0].args[0]).toBe(convertId('5', 'kakao'));
    expect(inserted[0].args[2]).toBe('홍길동');
    expect(inserted[0].args[3]).toBe('Nick');
    expect(inserted[0].args[4]).toBe('k@x.com');
    expect(inserted[0].args[5]).toBe('01011112222');
    expect(bridge[0][0]).toBe(convertId('5', 'kakao'));
    expect(bridge[0][1]).toBe('register');
    expect(bridge[0][5]).toBe('Kakao');
    expect(calls.some((c) => /INSERT INTO g5_member_social_profiles/.test(c.sql))).toBe(true);
  });
  it('sign-up flag on: a verified Kakao e-mail of an existing member links and signs in (no insert)', async () => {
    process.env.SOCIAL_NEST_REGISTER = '1';
    linked = null; memberByEmail = { mb_id: 'sb_old', mb_intercept_date: '', mb_leave_date: '', mb_email_certify: '2026-01-01 00:00:00' };
    mockFetch({ id: 6, kakao_account: { email: 'old@x.com', is_email_verified: true } });
    const r = await cb('kakao', stateFor('kakao', '/mypage.php')).expect(302);
    expect(r.headers.location).toBe('/mypage.php');
    expect(inserted).toHaveLength(0);
    expect(bridge[0].slice(0, 2)).toEqual(['sb_old', 'login']);
  });
  it('sign-up flag on: an e-mail that is not verified does not link; a taken e-mail is refused', async () => {
    process.env.SOCIAL_NEST_REGISTER = '1';
    linked = null; emailTaken = true; memberByEmail = { mb_id: 'sb_old', mb_intercept_date: '', mb_leave_date: '', mb_email_certify: '2026-01-01 00:00:00' };
    mockFetch({ id: 7, kakao_account: { email: 'old@x.com', is_email_verified: false } });
    const r = await cb('kakao', stateFor('kakao')).expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=email_exists');
    expect(inserted).toHaveLength(0); expect(bridge).toHaveLength(0);
  });
  it('sign-up flag on: a profile row that is already linked blocks a second sign-up', async () => {
    process.env.SOCIAL_NEST_REGISTER = '1';
    linked = null; socialRows = [{ mp_no: 1, mb_id: 'sb_gone' }];
    mockFetch({ id: 8, kakao_account: {} });
    const r = await cb('kakao', stateFor('kakao')).expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=exists');
    expect(inserted).toHaveLength(0);
  });
  it('sign-up flag on: a leftover profile row without a member is cleaned up and the sign-up goes on', async () => {
    process.env.SOCIAL_NEST_REGISTER = '1';
    linked = null; socialRows = [{ mp_no: 9, mb_id: '' }];
    mockFetch({ id: 9, kakao_account: { email: 'n@x.com' } });
    await cb('kakao', stateFor('kakao')).expect(302);
    expect(calls.some((c) => /DELETE FROM g5_member_social_profiles WHERE mp_no/.test(c.sql))).toBe(true);
    expect(inserted).toHaveLength(1);
  });
  it('sign-up flag on: Google goes to the form, the form registers', async () => {
    process.env.SOCIAL_NEST_REGISTER = '1';
    linked = null; mockFetch({ sub: 'g-77', name: 'Gina Lee', email: 'gina@x.com', email_verified: true });
    const r = await cb('google', stateFor('google')).expect(302);
    expect(r.headers.location).toBe('/next/register/social');
    const pending = String(r.headers['set-cookie']).match(/sb_social_pending=([^;]+)/)![1];
    expect(inserted).toHaveLength(0);

    const pr = await request(app.getHttpServer()).get('/v1/auth/social/pending').set('Cookie', `sb_social_pending=${pending}`).expect(200);
    expect(pr.body).toEqual({ pending: true, provider: 'google', name: 'Gina Lee', email: 'gina@x.com' });
    expect((await request(app.getHttpServer()).get('/v1/auth/social/pending').expect(200)).body).toEqual({ pending: false });

    const post = (b: any, c = `sb_social_pending=${pending}`) => request(app.getHttpServer()).post('/v1/auth/social/register').set('X-Requested-With', 'XMLHttpRequest').set('Cookie', c).send(b);
    await post({ name: 'Gina Lee', email: 'gina@x.com', agreeTerms: true, agreePrivacy: false }).expect(400);
    await post({ name: 'Gina Lee', email: 'bad', agreeTerms: true, agreePrivacy: true }).expect(400);
    await post({ name: 'Gina Lee', email: 'gina@x.com', agreeTerms: true, agreePrivacy: true }, 'x=1').expect(410);
    await request(app.getHttpServer()).post('/v1/auth/social/register').send({}).expect(403); // same-origin header missing
    expect(inserted).toHaveLength(0);

    const ok = await post({ name: 'Gina Lee', email: 'gina@x.com', agreeTerms: true, agreePrivacy: true }).expect(201);
    expect(ok.body).toEqual({ success: true, loggedIn: true });
    expect(inserted).toHaveLength(1);
    expect(inserted[0].args[0]).toBe(convertId('g-77', 'google'));
    expect(bridge[0].slice(0, 2)).toEqual([convertId('g-77', 'google'), 'register']);
    expect(bridge[0][5]).toBe('Google');
    expect(String(ok.headers['set-cookie'])).toMatch(/sb_social_pending=;/);
  });
  it('Google form: a taken e-mail answers 409 and creates nothing', async () => {
    process.env.SOCIAL_NEST_REGISTER = '1';
    linked = null; emailTaken = true; mockFetch({ sub: 'g-78', name: 'Hana Kim', email: 'taken@x.com', email_verified: true });
    const r = await cb('google', stateFor('google')).expect(302);
    const pending = String(r.headers['set-cookie']).match(/sb_social_pending=([^;]+)/)![1];
    await request(app.getHttpServer()).post('/v1/auth/social/register').set('X-Requested-With', 'XMLHttpRequest').set('Cookie', `sb_social_pending=${pending}`)
      .send({ name: 'Hana Kim', email: 'taken@x.com', agreeTerms: true, agreePrivacy: true }).expect(409);
    expect(inserted).toHaveLength(0); expect(bridge).toHaveLength(0);
  });

  describe('apple', () => {
    let saved: string | undefined; let appleTokens: any = {}; let keyFetches = 0; let ipN = 0; // one address per sign-in: the callback is limited per IP
    const mockApple = () => {
      global.fetch = (async (u: any, init?: any) => {
        const url = String(u);
        if (/auth\/token/.test(url)) { tokenBodies.push(String(init?.body)); return { json: async () => appleTokens } as any; }
        if (/auth\/keys/.test(url)) { keyFetches++; return { json: async () => ({ keys: [APPLE_JWK] }) } as any; }
        return { json: async () => ({}) } as any;
      }) as any;
    };
    /** start → Apple's cross-site POST → the relayed GET callback, like a browser does it. */
    const flow = async (form: (state: string, nonce: string) => Record<string, string>, extraCookie = '') => {
      const st = await request(app.getHttpServer()).get('/v1/auth/social/apple/start?url=/next/mypage').expect(302);
      const loc = new URL(st.headers.location);
      const stateCookie = String(st.headers['set-cookie']).match(/sb_social_state=([^;]+)/)![1];
      const nonce = loc.searchParams.get('nonce')!;
      const post = await request(app.getHttpServer()).post('/v1/auth/social/apple/callback').type('form').send(form(loc.searchParams.get('state')!, nonce)).expect(303);
      expect(post.headers.location).toBe('https://h.test/next/api/v1/auth/social/apple/callback');
      const relay = String(post.headers['set-cookie']).match(/sb_apple_cb=([^;]+)/)![1];
      return { go: request(app.getHttpServer()).get('/v1/auth/social/apple/callback').set('Host', 'h.test').set('X-Forwarded-For', `7.7.7.${++ipN}`).set('Cookie', `sb_social_state=${stateCookie}; sb_apple_cb=${relay}${extraCookie}`) }; // wrapped: a supertest request is thenable
    };
    beforeEach(() => { saved = process.env.SOCIAL_NEST_PROVIDERS; process.env.SOCIAL_NEST_PROVIDERS = 'kakao,google,apple'; appleTokens = {}; mockApple(); });
    afterEach(() => { process.env.SOCIAL_NEST_PROVIDERS = saved; });

    it('start: form_post, name + e-mail scope, a nonce that is also in the signed state', async () => {
      const r = await request(app.getHttpServer()).get('/v1/auth/social/apple/start?url=/next/mypage').expect(302);
      const loc = new URL(r.headers.location);
      expect(loc.origin + loc.pathname).toBe('https://appleid.apple.com/auth/authorize');
      expect(loc.searchParams.get('client_id')).toBe('app.web');
      expect(loc.searchParams.get('redirect_uri')).toBe('https://h.test/next/api/v1/auth/social/apple/callback');
      expect(loc.searchParams.get('response_mode')).toBe('form_post');
      expect(loc.searchParams.get('scope')).toBe('name email');
      const cookie = String(r.headers['set-cookie']).match(/sb_social_state=([^;]+)/)![1];
      const st = readState(SECRET, cookie)!;
      expect(st.p).toBe('apple');
      expect(st.n).toMatch(/^[0-9a-f]{32}$/);
      expect(loc.searchParams.get('nonce')).toBe(st.n);
    });
    it('the POST checks nothing and stores only bounded values; no relay cookie = state error', async () => {
      const post = await request(app.getHttpServer()).post('/v1/auth/social/apple/callback').type('form').send({ code: 'c'.repeat(5000), state: 's', user: 'not json' }).expect(303);
      const relay = readAppleRelay(SECRET, String(post.headers['set-cookie']).match(/sb_apple_cb=([^;]+)/)![1])!;
      expect(relay.c).toHaveLength(2000); expect(relay.n).toBe('');
      expect(String(post.headers['set-cookie'])).toMatch(/Max-Age=120; Path=\/; HttpOnly; Secure; SameSite=Lax/);
      const st = stateFor('apple');
      const r = await request(app.getHttpServer()).get(`/v1/auth/social/apple/callback?code=c&state=${st.state}`).set('Cookie', `sb_social_state=${st.value}`).expect(302);
      expect(r.headers.location).toBe('/next/login?social_error=state'); // the query string is never trusted for Apple
      expect(bridge).toHaveLength(0);
    });
    it('a linked member is signed in: ES256 client secret, verified id_token, bridge as Apple, app marker kept', async () => {
      linked = { mb_id: 'apple_8407085f', mb_intercept_date: '', mb_leave_date: '' };
      const r = await (await flow((state, nonce) => { appleTokens = { id_token: idToken({ sub: '000123.abc', email: 'a@privaterelay.appleid.com', email_verified: 'true', nonce }) }; return { code: 'CODE', state }; }, '; sb_patient_app=1')).go.expect(302);
      expect(r.headers.location).toBe('/next/mypage');
      expect(bridge[0].slice(0, 3)).toEqual(['apple_8407085f', 'login', true]);
      expect(bridge[0][5]).toBe('Apple');
      expect(String(r.headers['set-cookie'])).toMatch(/PHPSESSID=abc/);
      expect(String(r.headers['set-cookie'])).toMatch(/sb_apple_cb=; Max-Age=0/);
      expect(calls.find((c) => /FROM g5_member_social_profiles mp JOIN/.test(c.sql))!.args).toEqual(['apple', '000123.abc']);
      const body = new URLSearchParams(tokenBodies[0]);
      expect(body.get('code')).toBe('CODE'); expect(body.get('client_id')).toBe('app.web');
      const [h, p, sig] = body.get('client_secret')!.split('.');
      expect(JSON.parse(Buffer.from(h, 'base64url').toString())).toEqual({ typ: 'JWT', alg: 'ES256', kid: 'KEYID' });
      expect(JSON.parse(Buffer.from(p, 'base64url').toString())).toMatchObject({ iss: 'TEAM', sub: 'app.web', aud: 'https://appleid.apple.com' });
      expect(cryptoVerify('sha256', Buffer.from(`${h}.${p}`), { key: appleEc.publicKey, dsaEncoding: 'ieee-p1363' }, Buffer.from(sig, 'base64url'))).toBe(true);
      expect(calls.some((c) => /UPDATE g5_member SET/.test(c.sql))).toBe(false); // no Kakao-style member sync
    });
    it('refused: wrong nonce, foreign signature, other audience, expired, no id_token, cancelled, wrong state', async () => {
      const other = generateKeyPairSync('rsa', { modulusLength: 2048 }).privateKey;
      const cases: ((state: string, nonce: string) => Record<string, string>)[] = [
        (state) => { appleTokens = { id_token: idToken({ sub: 's', nonce: 'f'.repeat(32) }) }; return { code: 'c', state }; },
        (state, nonce) => { appleTokens = { id_token: idToken({ sub: 's', nonce }, 'K1', other) }; return { code: 'c', state }; },
        (state, nonce) => { appleTokens = { id_token: idToken({ sub: 's', nonce, aud: 'someone.else' }) }; return { code: 'c', state }; },
        (state, nonce) => { appleTokens = { id_token: idToken({ sub: 's', nonce, exp: 1 }) }; return { code: 'c', state }; },
        (state) => { appleTokens = { error: 'invalid_grant' }; return { code: 'c', state }; },
      ];
      for (const c of cases) { const r = await (await flow(c)).go.expect(302); expect(r.headers.location).toBe('/next/login?social_error=failed'); }
      let r = await (await flow((state) => ({ error: 'user_cancelled_authorize', state }))).go.expect(302);
      expect(r.headers.location).toBe('/next/login?social_error=denied');
      r = await (await flow((_s, nonce) => { appleTokens = { id_token: idToken({ sub: 's', nonce }) }; return { code: 'c', state: 'forged' }; })).go.expect(302);
      expect(r.headers.location).toBe('/next/login?social_error=state');
      expect(bridge).toHaveLength(0);
    });
    it('sign-up flag on: a new Apple member is created at once with the posted name (no e-mail linking)', async () => {
      process.env.SOCIAL_NEST_REGISTER = '1';
      linked = null; memberByEmail = { mb_id: 'sb_old', mb_intercept_date: '', mb_leave_date: '' };
      const r = await (await flow((state, nonce) => { appleTokens = { id_token: idToken({ sub: 'sub-9', email: 'New@X.com', email_verified: true, nonce }) }; return { code: 'c', state, user: JSON.stringify({ name: { firstName: '길동', lastName: '홍' }, email: 'new@x.com' }) }; })).go.expect(302);
      expect(r.headers.location).toBe('/next');
      expect(inserted).toHaveLength(1);
      expect(inserted[0].args[0]).toBe(convertId('sub-9', 'apple'));
      expect(inserted[0].args[2]).toBe('홍길동');
      expect(inserted[0].args[4]).toBe('new@x.com');
      expect(bridge[0].slice(0, 2)).toEqual([convertId('sub-9', 'apple'), 'register']);
      expect(bridge[0][5]).toBe('Apple');
      expect(calls.some((c) => /INSERT INTO g5_member_social_profiles/.test(c.sql))).toBe(true);
    });
    it('sign-up: Apple sends no name and no e-mail → the Gnuboard id as the name and the PHP placeholder address', async () => {
      process.env.SOCIAL_NEST_REGISTER = '1';
      linked = null;
      await (await flow((state, nonce) => { appleTokens = { id_token: idToken({ sub: 'sub-10', nonce }) }; return { code: 'c', state }; })).go.expect(302);
      const id = convertId('sub-10', 'apple');
      expect(inserted[0].args[2]).toBe(id);
      expect(inserted[0].args[4]).toBe(`kakao_${id}@social.local`);
    });
    it('Apple keys are cached and fetched again only for an unknown key id', async () => {
      const before = keyFetches;
      await (await flow((state, nonce) => { appleTokens = { id_token: idToken({ sub: 's', nonce }) }; return { code: 'c', state }; })).go.expect(302);
      expect(keyFetches).toBe(before); // cached by the earlier tests
      await (await flow((state, nonce) => { appleTokens = { id_token: idToken({ sub: 's', nonce }, 'ROTATED') }; return { code: 'c', state }; })).go.expect(302);
      expect(keyFetches).toBe(before + 1);
    });
  });
});

describe('apple logic', () => {
  it('client secret verifies with the key pair and tolerates CRLF / literal \\n in the stored key', () => {
    for (const pem of [APPLE_PEM, APPLE_PEM.replace(/\n/g, '\r\n'), APPLE_PEM.trim().replace(/\n/g, '\\n')]) {
      const [h, p, sig] = appleClientSecret({ teamId: 'T', keyId: 'K', clientId: 'C', keyPem: pem }, 1_000_000).split('.');
      expect(cryptoVerify('sha256', Buffer.from(`${h}.${p}`), { key: appleEc.publicKey, dsaEncoding: 'ieee-p1363' }, Buffer.from(sig, 'base64url'))).toBe(true);
      expect(JSON.parse(Buffer.from(p, 'base64url').toString())).toEqual({ iss: 'T', iat: 1000, exp: 1300, aud: 'https://appleid.apple.com', sub: 'C' });
    }
    expect(() => appleClientSecret({ teamId: 'T', keyId: 'K', clientId: 'C', keyPem: 'not a key' })).toThrow();
  });
  it('id_token: only RS256 from a published key, with issuer, audience, expiry, nonce and sub', () => {
    const want = { clientId: 'app.web', nonce: 'n'.repeat(32) };
    const ok = verifyAppleIdToken(idToken({ sub: 'S', email: 'e@x.com', email_verified: 'true', nonce: want.nonce }), [APPLE_JWK], want);
    expect(ok).toEqual({ ok: true, claims: { sub: 'S', email: 'e@x.com', emailVerified: true } });
    const err = (t: string, keys: any[] = [APPLE_JWK], w = want) => { const r = verifyAppleIdToken(t, keys, w); return r.ok ? '' : r.error; };
    expect(err('a.b')).toBe('format');
    expect(err(idToken({ sub: 'S', nonce: want.nonce }, 'OTHER'))).toBe('kid');
    expect(err(idToken({ sub: 'S', nonce: want.nonce }), [])).toBe('kid');
    expect(err(idToken({ sub: 'S', nonce: want.nonce, iss: 'https://evil.test' }))).toBe('iss');
    expect(err(idToken({ sub: 'S', nonce: want.nonce }), [APPLE_JWK], { clientId: 'app.web', nonce: '' })).toBe('nonce');
    expect(err(idToken({ sub: 'S' }))).toBe('nonce');
    expect(err(idToken({ nonce: want.nonce }))).toBe('sub');
    // alg "none" and an HS256 token signed with the public key are not accepted
    const none = `${b64u({ alg: 'none', kid: 'K1' })}.${b64u({ iss: 'https://appleid.apple.com', aud: 'app.web', exp: 9e9, sub: 'S', nonce: want.nonce })}.`;
    expect(err(none)).toBe('alg');
    const tampered = idToken({ sub: 'S', nonce: want.nonce }).split('.'); tampered[1] = b64u({ iss: 'https://appleid.apple.com', aud: 'app.web', exp: 9e9, sub: 'ADMIN', nonce: want.nonce });
    expect(err(tampered.join('.'))).toBe('signature');
    expect(createPublicKey({ key: APPLE_JWK, format: 'jwk' }).type).toBe('public');
  });
  it('name, profile and relay cookie', () => {
    expect(appleUserName(JSON.stringify({ name: { firstName: 'Gil\ndong', lastName: 'Hong' } }))).toBe('HongGildong');
    expect(appleUserName('{"name":null}')).toBe(''); expect(appleUserName('junk')).toBe('');
    expect(appleUserName(JSON.stringify({ name: { firstName: 'x'.repeat(300) } }))).toHaveLength(100);
    const p = appleProfile({ sub: 'S', email: 'e@x.com', emailVerified: false }, '');
    expect(p.identifier).toBe('S'); expect(p.username).toBe(convertId('S', 'apple')); expect(p.emailVerified).toBe('');
    expect(profileEmail(p, 'apple')).toBe('e@x.com');
    const v = signAppleRelay(SECRET, { c: 'code', s: 'st', e: '', n: '홍길동' }, 1000);
    expect(readAppleRelay(SECRET, v, 2000)).toMatchObject({ c: 'code', s: 'st', n: '홍길동' });
    expect(readAppleRelay(SECRET, v, 200_000)).toBeNull();
    expect(readAppleRelay('y'.repeat(40), v, 2000)).toBeNull();
    expect(readAppleRelay(SECRET, v.slice(0, -2) + 'AA', 2000)).toBeNull();
    expect(APPLE_RELAY_COOKIE).toBe('sb_apple_cb');
  });
});
