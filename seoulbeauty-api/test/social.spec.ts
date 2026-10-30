import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { SessionBridgeService } from '../src/session-bridge/session-bridge.service';
import { SocialModule } from '../src/social/social.module';
import { MailService } from '../src/mail/mail.service';
import { googleProfile, kakaoMemberUpdates, kakaoProfile, normalizeKakaoPhone, readState, signState } from '../src/social/social.logic';
import { adler32, candidates, convertId, profileEmail, readPending, signPending, socialNick, validEmail } from '../src/social/social-register.logic';

const SECRET = 'x'.repeat(40);

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
    if (/FROM g5_config/.test(sql)) return [[{ cf_register_level: 2, cf_prohibit_id: 'admin,root', cf_prohibit_email: '', cf_title: 'T', cf_email_mb_super_admin: 0, cf_admin_email: '', cf_use_email_certify: certify, cf_social_login_use: 1, cf_social_servicelist: 'kakao,google,Apple', cf_kakao_rest_key: 'rk', cf_kakao_client_secret: kakaoSecret, cf_google_clientid: 'gid', cf_google_secret: 'gs' }]];
    if (/FROM g5_member_social_profiles mp JOIN/.test(sql)) return [linked ? [linked] : []];
    return [[]];
  };
  const stateFor = (p: 'kakao' | 'google', url = '/next/mypage') => signState(SECRET, p, url);
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
  it('start refuses unknown or disabled providers', async () => {
    const r = await request(app.getHttpServer()).get('/v1/auth/social/apple/start').expect(302);
    expect(r.headers.location).toBe('/next/login?social_error=unavailable');
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
    expect(r.headers.location).toBe('/index.php');
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
});
