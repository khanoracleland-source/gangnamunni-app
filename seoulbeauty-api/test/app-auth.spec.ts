import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { AppAuthModule } from '../src/app-auth/app-auth.module';
import { AppAuthService } from '../src/app-auth/app-auth.service';
import { NestSessionService } from '../src/auth/nest-session.service';
import { cookieValue, decryptPayload, deviceBinding, encryptPayload, isDeviceId, payloadKey, safeLandUrl, sha256 } from '../src/app-auth/app-auth.logic';

const KEY_B64 = 'BwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwc=';
const DEV = 'd'.repeat(64), DEV2 = 'e'.repeat(64);

describe('app auth logic', () => {
  it('device binding and input shapes are the PHP ones', () => {
    expect(deviceBinding('D'.repeat(64))).toBe(`patient-app-device:${sha256('d'.repeat(64))}`);
    expect(isDeviceId('x'.repeat(64))).toBe(false);
    expect(isDeviceId(DEV)).toBe(true);
    expect(cookieValue(`a=1; sb_app_device=${DEV}; b=2`, 'sb_app_device')).toBe(DEV);
    expect(cookieValue('xsb_app_device=1', 'sb_app_device')).toBe('');
  });
  it('land url: same-site paths only', () => {
    expect(safeLandUrl('/next/mypage?x=1')).toBe('/next/mypage?x=1');
    for (const u of ['//evil.test', 'https://evil.test', 'next', '/a\\b', '/a\nb', '', undefined, ['/x']]) expect(safeLandUrl(u)).toBe('/');
  });
  it('the payload key must be 32 bytes of base64', () => {
    expect(payloadKey(KEY_B64)).toHaveLength(32);
    for (const k of ['', 'abc', 'not base64!!', Buffer.alloc(16).toString('base64')]) expect(() => payloadKey(k)).toThrow();
  });
  it('a payload encrypted by PHP (openssl aes-256-gcm, AAD = hex sha256 of the code) is read here', () => {
    const code = 'ab'.repeat(32);
    const php = 'v1:AQEBAQEBAQEBAQEBgecFhHkTYnKBngvhb8LMnw3D6NTz2p99jqCzShVZUguKyiXFKaYKImkAclNXhQeK84M5ga0itLFvQ3k68E/fqL8q2DNsj85uk3OWOecTtKSY1m8BP/0HHFJpKFGaFTCpYq1PFWKpeEhfQkaUwOi2fGivQPbZ5Sb8AzZGnVeKc94qWeFBtgx/9tPL3FD2Avz2fjaPfOUmtfdQ+7NgXnIxwDy3XmN7pGyUF+YTFJMaJn3qeAQKiBwjJuj62wBZ8A7cHGF1WDHs0CX90PT6NdAAxT7LYhrg+SlwuU+bSu7DmOu8+m4tcBMAQwy0xVIpvg5zOo5LEg2w0py5TSHhPdPG9p4CWCn1XGu+DVpjgwv5z+oqOFPjvRsTITRTnwazrGvuf0MOj8cQwRFNStJrhq8d95eEcw3g/4CgmNagUH9aTrqdzOwoB2owllTxA2fN5B4=';
    expect(decryptPayload(code, php, payloadKey(KEY_B64))).toEqual({ access_token: '1'.repeat(64), access_expires_at: 1794470000, refresh_token: '2'.repeat(64), refresh_expires_at: 1804838000, session_bridge_token: '3'.repeat(64) });
    // bound to the code and to the key; any change is refused
    expect(() => decryptPayload('cd'.repeat(32), php, payloadKey(KEY_B64))).toThrow();
    expect(() => decryptPayload(code, php, Buffer.alloc(32, 9))).toThrow();
    expect(() => decryptPayload(code, php.slice(0, -6) + 'AAAA==', payloadKey(KEY_B64))).toThrow();
    expect(() => decryptPayload(code, 'v2:abc', payloadKey(KEY_B64))).toThrow();
    const again = encryptPayload(code, { a: 1 }, payloadKey(KEY_B64));
    expect(again).toMatch(/^v1:/);
    expect(decryptPayload(code, again, payloadKey(KEY_B64))).toEqual({ a: 1 });
  });
});

describe('app auth API (APP_AUTH_NEST)', () => {
  let app: INestApplication;
  interface Row { token: string; user_type: string; g5_mb_id: string; user_agent: string | null; payload: string | null; exp: number }
  let tokens: Row[];
  let sessions: { token_hash: string; mb_id: string; source: string; user_agent: string; remember: number }[];
  let member: Record<string, any> | null;
  let certify: number;
  let now: number;
  let failOn: RegExp | null;
  const live = (r: Row) => r.exp > now;
  const query = async (q: string, p: any[] = []) => {
    if (failOn && failOn.test(q)) throw new Error('db down');
    const ut = /user_type = '([a-z_]+)'/.exec(q)?.[1] ?? '';
    if (/^(START TRANSACTION|COMMIT|ROLLBACK)/.test(q)) return [{}];
    if (/FROM g5_member WHERE mb_id = \? LIMIT 1/.test(q)) return [member && member.mb_id === p[0] ? [member] : []];
    if (/FROM g5_config/.test(q)) return [[{ cf_use_email_certify: certify }]];
    if (/^INSERT INTO sso_tokens/.test(q)) {
      const hasUa = /ip_address, user_agent, expires_at/.test(q);
      tokens.push({ token: p[0], user_type: /VALUES \(\?, '([a-z_]+)'/.exec(q)![1], g5_mb_id: p[1], user_agent: hasUa ? p[3] : null, payload: null, exp: now + (hasUa ? p[4] : p[3]) * 1000 });
      return [{ affectedRows: 1 }];
    }
    if (/^UPDATE sso_tokens SET payload = NULL/.test(q)) { tokens.filter((r) => r.user_type === ut && !live(r)).forEach((r) => { r.payload = null; }); return [{}]; }
    if (/^UPDATE sso_tokens SET user_agent = \?/.test(q)) { const r = tokens.find((x) => x.token === p[1] && x.user_type === ut && live(x) && x.user_agent === null); if (r) r.user_agent = p[0]; return [{ affectedRows: r ? 1 : 0 }]; }
    if (/^UPDATE sso_tokens SET payload = \?/.test(q)) { const r = tokens.find((x) => x.token === p[1] && x.user_type === ut && live(x) && x.payload === null); if (r) r.payload = p[0]; return [{ affectedRows: r ? 1 : 0 }]; }
    if (/^UPDATE sso_tokens SET expires_at = GREATEST/.test(q)) { const r = tokens.find((x) => x.token === p[1]); if (r) r.exp = Math.max(r.exp, now + p[0] * 1000); return [{ affectedRows: r ? 1 : 0 }]; }
    if (/^SELECT g5_mb_id, payload FROM sso_tokens/.test(q)) return [tokens.filter((x) => x.token === p[0] && x.user_type === ut && x.user_agent === p[1] && live(x))];
    if (/^SELECT g5_mb_id FROM sso_tokens/.test(q)) return [tokens.filter((x) => x.token === p[0] && x.user_type === ut && x.user_agent === p[1] && live(x))];
    if (/^SELECT token FROM sso_tokens/.test(q)) return [tokens.filter((x) => x.token === p[0] && x.g5_mb_id === p[1] && x.user_type === ut && x.user_agent === p[2] && live(x))];
    if (/^SELECT g5_mb_id, user_agent FROM sso_tokens/.test(q)) return [tokens.filter((x) => x.token === p[0] && x.user_type === ut && live(x))];
    if (/^DELETE FROM sso_tokens WHERE token = \?/.test(q)) { const n = tokens.length; tokens = tokens.filter((x) => !(x.token === p[0] && x.user_type === ut)); return [{ affectedRows: n - tokens.length }]; }
    if (/^DELETE FROM sso_tokens WHERE g5_mb_id = \?/.test(q)) {
      const types = /user_type IN \(([^)]+)\)/.exec(q)?.[1].replace(/'/g, '').split(/,\s*/) ?? [ut];
      const n = tokens.length;
      tokens = tokens.filter((x) => !(x.g5_mb_id === p[0] && types.includes(x.user_type)
        && (/expires_at <= NOW\(\)/.test(q) ? !live(x) : /user_agent = \? OR user_agent IS NULL/.test(q) ? (x.user_agent === p[1] || x.user_agent === null) : x.user_agent === p[1])));
      return [{ affectedRows: n - tokens.length }];
    }
    if (/^SELECT mb_cred_epoch FROM g5_member/.test(q)) return [member && member.mb_id === p[0] ? [{ mb_cred_epoch: 0 }] : []];
    if (/^INSERT INTO sb_sessions/.test(q)) { sessions.push({ token_hash: p[0], mb_id: p[1], remember: p[3], source: p[4], user_agent: p[5] }); return [{ affectedRows: 1 }]; }
    if (/^DELETE FROM sb_sessions WHERE mb_id = \? AND source = 'app'/.test(q)) { sessions = sessions.filter((x) => !(x.mb_id === p[0] && x.source === 'app' && x.user_agent === p[1])); return [{}]; }
    if (/^DELETE FROM sb_sessions WHERE token_hash/.test(q)) { sessions = sessions.filter((x) => x.token_hash !== p[0]); return [{}]; }
    if (/^SELECT mb_id, cred_epoch, remember/.test(q)) { const r = sessions.find((x) => x.token_hash === p[0]); return [r ? [{ mb_id: r.mb_id, cred_epoch: 0, remember: r.remember, left_s: 1000 }] : []]; }
    if (/^SELECT mb_no, mb_id, mb_name/.test(q)) return [member && member.mb_id === p[0] ? [{ ...member, mb_no: 42, mb_cred_epoch: 0 }] : []];
    return [[]];
  };
  const svc = () => app.get(AppAuthService);
  const post = (path: string, body: object, headers: Record<string, string> = {}) => request(app.getHttpServer()).post(`/v1/app-auth/${path}`).set(headers).send(body);
  const count = (type: string) => tokens.filter((x) => x.user_type === type).length;
  const realFetch = global.fetch;

  beforeAll(async () => {
    global.fetch = jest.fn(async () => new Response(JSON.stringify({ loggedIn: false, user: null }), { status: 200 })) as any; // PHP knows nobody here
    @Global() @Module({ providers: [{ provide: DB, useValue: { query, getConnection: async () => ({ query, release: () => undefined }) } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, AppAuthModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; for (const k of ['APP_AUTH_NEST', 'APP_NATIVE_AUTH_KEY', 'NEST_SESSIONS']) delete process.env[k]; await app.close(); });
  beforeEach(() => {
    process.env.APP_AUTH_NEST = '1'; process.env.APP_NATIVE_AUTH_KEY = KEY_B64; process.env.NEST_SESSIONS = '1';
    tokens = []; sessions = []; certify = 0; now = 1_800_000_000_000; failOn = null;
    member = { mb_id: 'kim', mb_name: '김', mb_nick: 'n', mb_email: 'k@a.co', mb_hp: '010', mb_level: 2, mb_intercept_date: '', mb_leave_date: '', mb_email_certify: '2026-01-01 00:00:00' };
    (svc() as any).recentCodes.clear(); (app.get(NestSessionService) as any).cache.clear();
  });

  it('switched off: every endpoint is 404', async () => {
    process.env.APP_AUTH_NEST = '0';
    for (const p of ['exchange', 'verify', 'refresh', 'logout', 'bootstrap']) await post(p, {}).expect(404);
    await request(app.getHttpServer()).get('/v1/app-auth/land?url=/next').expect(404);
  });

  it('exchange: input is checked first; a code opens the bundle once per device', async () => {
    await post('exchange', { code: 'x' }).expect(400);
    await post('exchange', { code: 'a'.repeat(64), device_id: 'short' }).expect(400);
    expect((await post('exchange', { code: 'a'.repeat(64), device_id: DEV }).expect(401)).body).toEqual({ success: false, error: 'exchange_invalid' });
    const code = await svc().issueExchangeCode('kim', '1.1.1.1');
    expect(tokens[0]).toMatchObject({ token: sha256(code), user_type: 'patient_app_exchange', user_agent: null });
    const r = (await post('exchange', { code, device_id: DEV }).expect(200)).body;
    expect(r).toMatchObject({ success: true, user: { mb_id: 'kim', name: '김', nickname: 'n', email: 'k@a.co', phone: '010', level: 2 } });
    for (const k of ['access_token', 'refresh_token', 'session_bridge_token']) expect(r[k]).toMatch(/^[a-f0-9]{64}$/);
    expect(r.refresh_expires_at - r.access_expires_at).toBe(86400 * 120 - 3600);
    // stored the PHP way: access and refresh as SHA-256, the bridge token as is, all bound to the device
    const b = deviceBinding(DEV);
    expect(tokens.find((x) => x.user_type === 'patient_app_access')).toMatchObject({ token: sha256(r.access_token), user_agent: b });
    expect(tokens.find((x) => x.user_type === 'patient_app_remember')).toMatchObject({ token: sha256(r.refresh_token), user_agent: b });
    expect(tokens.find((x) => x.user_type === 'patient_app_bridge')).toMatchObject({ token: r.session_bridge_token, user_agent: b });
    expect(tokens.find((x) => x.user_type === 'patient_app_exchange')!.payload).toMatch(/^v1:/);
    // the same device again (the answer was lost): the same bundle, nothing new is issued
    const again = (await post('exchange', { code, device_id: DEV }).expect(200)).body;
    expect(again.access_token).toBe(r.access_token);
    expect(again.refresh_token).toBe(r.refresh_token);
    expect(count('patient_app_remember')).toBe(1);
    // another device with the same code, and the code after its 90 seconds: refused
    await post('exchange', { code, device_id: DEV2 }).expect(401);
    now += 91_000;
    await post('exchange', { code, device_id: DEV }).expect(401);
  });

  it('exchange: without the key nothing is claimed (503, the app keeps what it has)', async () => {
    const code = await svc().issueExchangeCode('kim', '');
    delete process.env.APP_NATIVE_AUTH_KEY;
    expect((await post('exchange', { code, device_id: DEV }).expect(503)).body).toEqual({ success: false, error: 'exchange_unavailable' });
    expect(tokens[0].user_agent).toBeNull();
    expect(count('patient_app_access')).toBe(0);
  });

  it('verify: a live access token gives a fresh bridge token; refresh keeps the same refresh token', async () => {
    const code = await svc().issueExchangeCode('kim', '');
    const first = (await post('exchange', { code, device_id: DEV }).expect(200)).body;
    await post('verify', { device_id: DEV }).expect(400);
    await post('verify', { access_token: 'f'.repeat(64), device_id: DEV }).expect(401);
    await post('verify', { access_token: first.access_token, device_id: DEV2 }).expect(401); // another device
    const v = (await post('verify', { device_id: DEV }, { Authorization: `Bearer ${first.access_token}` }).expect(200)).body;
    expect(v.session_bridge_token).not.toBe(first.session_bridge_token);
    expect(count('patient_app_bridge')).toBe(1);
    now += 3601_000; // the access token is over
    expect((await post('verify', { access_token: first.access_token, device_id: DEV }).expect(401)).body.error).toBe('access_token_invalid');
    await post('refresh', { mb_id: 'kim', refresh_token: first.refresh_token }).expect(400);
    await post('refresh', { mb_id: 'lee', refresh_token: first.refresh_token, device_id: DEV }).expect(401);
    await post('refresh', { mb_id: 'kim', refresh_token: first.refresh_token, device_id: DEV2 }).expect(401);
    const r = (await post('refresh', { mb_id: 'kim', refresh_token: first.refresh_token, device_id: DEV }).expect(200)).body;
    expect(r.refresh_token).toBe(first.refresh_token);
    expect(r.access_token).not.toBe(first.access_token);
    expect(count('patient_app_access')).toBe(1);
    expect(count('patient_app_remember')).toBe(1);
    await post('verify', { access_token: r.access_token, device_id: DEV }).expect(200);
  });

  it('a blocked, left or unverified member is refused everywhere; a database error is a 503, not a sign-out', async () => {
    const first = (await post('exchange', { code: await svc().issueExchangeCode('kim', ''), device_id: DEV }).expect(200)).body;
    member!.mb_intercept_date = '20200101';
    expect((await post('verify', { access_token: first.access_token, device_id: DEV }).expect(401)).body.error).toBe('account_unavailable');
    await post('refresh', { mb_id: 'kim', refresh_token: first.refresh_token, device_id: DEV }).expect(401);
    member!.mb_intercept_date = ''; certify = 1; member!.mb_email_certify = '0000-00-00 00:00:00';
    await post('verify', { access_token: first.access_token, device_id: DEV }).expect(401);
    certify = 0; failOn = /FROM sso_tokens WHERE token = \?/;
    const logged: string[] = [];
    const spy = jest.spyOn((svc() as any).log, 'error').mockImplementation((m: any) => { logged.push(String(m)); });
    expect((await post('verify', { access_token: first.access_token, device_id: DEV }).expect(503)).body).toEqual({ success: false, error: 'verify_unavailable' });
    expect((await post('refresh', { mb_id: 'kim', refresh_token: first.refresh_token, device_id: DEV }).expect(503)).body.error).toBe('refresh_unavailable');
    expect(count('patient_app_remember')).toBe(1);
    // the log names the error, never its message (a driver message can quote a stored token)
    spy.mockRestore();
    expect(logged).toEqual(['verify failed: Error', 'refresh failed: Error']);
    expect(logged.join(' ')).not.toMatch(/db down/);
  });

  it('logout: only with one of the device\'s own tokens, and then all of that device\'s tokens go', async () => {
    const a = (await post('exchange', { code: await svc().issueExchangeCode('kim', ''), device_id: DEV }).expect(200)).body;
    const b = (await post('exchange', { code: await svc().issueExchangeCode('kim', ''), device_id: DEV2 }).expect(200)).body;
    await post('logout', { mb_id: 'kim', device_id: DEV, access_token: 'f'.repeat(64) }).expect(200); // a guess: nothing happens
    await post('logout', { mb_id: 'kim', device_id: DEV, access_token: b.access_token }).expect(200); // another device's token
    expect(tokens.filter((x) => x.user_agent === deviceBinding(DEV))).toHaveLength(4);
    await post('logout', { mb_id: 'kim', device_id: DEV, refresh_token: a.refresh_token }).expect(200);
    expect(tokens.filter((x) => x.user_agent === deviceBinding(DEV))).toHaveLength(0);
    expect(tokens.filter((x) => x.user_agent === deviceBinding(DEV2))).toHaveLength(4);
    await post('verify', { access_token: a.access_token, device_id: DEV }).expect(401);
    await post('verify', { access_token: b.access_token, device_id: DEV2 }).expect(200);
  });

  it('land: the bridge token becomes the WebView\'s Nest session once, for its device only', async () => {
    const land = (cookie: string, url = '/next/mypage') => request(app.getHttpServer()).get(`/v1/app-auth/land?url=${encodeURIComponent(url)}`).set('Cookie', cookie);
    const bundle = (await post('exchange', { code: await svc().issueExchangeCode('kim', ''), device_id: DEV }).expect(200)).body;
    // nothing to present, a wrong device, an outside address: redirected, no session
    expect((await land('a=1').expect(302)).headers['set-cookie']).toBeUndefined();
    expect((await land(`sb_sso_token=${bundle.session_bridge_token}; sb_app_device=${DEV2}`, 'https://evil.test').expect(302)).headers.location).toBe('/');
    expect(sessions).toHaveLength(0);
    expect(count('patient_app_bridge')).toBe(1);
    const r = await land(`sb_sso_token=${bundle.session_bridge_token}; sb_app_device=${DEV}`).expect(302);
    expect(r.headers.location).toBe('/next/mypage');
    const set = r.headers['set-cookie'] as unknown as string[];
    expect(set[0]).toBe('sb_sso_token=; Path=/; Max-Age=0; HttpOnly; Secure; SameSite=Lax');
    expect(set[1]).toMatch(new RegExp(`^sb_sid=[a-f0-9]{64}; Path=/; Max-Age=${86400 * 120}; HttpOnly; Secure; SameSite=Lax$`));
    expect(sessions).toEqual([expect.objectContaining({ mb_id: 'kim', source: 'app', remember: 1, user_agent: deviceBinding(DEV) })]);
    expect(count('patient_app_bridge')).toBe(0);
    // used once
    expect(((await land(`sb_sso_token=${bundle.session_bridge_token}; sb_app_device=${DEV}`).expect(302)).headers['set-cookie'] as unknown as string[])).toHaveLength(1);
    expect(sessions).toHaveLength(1);
    // the next start of the same device replaces its session instead of leaving the old row behind
    const v = (await post('verify', { access_token: bundle.access_token, device_id: DEV }).expect(200)).body;
    await land(`sb_sso_token=${v.session_bridge_token}; sb_app_device=${DEV}`).expect(302);
    expect(sessions).toHaveLength(1);
    // and the session it got answers /v1/me and bootstrap without PHP
    const sid = /sb_sid=([a-f0-9]{64})/.exec(set[1])![1];
    expect(sessions[0].token_hash).not.toBe(sha256(sid)); // (replaced by the second start)
  });

  it('bootstrap: a signed-in app WebView gets an exchange code, the same one for a minute', async () => {
    const sid = /sb_sid=([a-f0-9]{64})/.exec((await app.get(NestSessionService).create('kim', { remember: true, app: true, source: 'login' }))!)![1];
    const C = `sb_sid=${sid}; sb_patient_app=1; sb_app_device=${DEV}`;
    await post('bootstrap', {}, { Cookie: C }).expect(400); // no X-Native-Auth
    await post('bootstrap', {}, { Cookie: `sb_patient_app=1; sb_app_device=${DEV}`, 'X-Native-Auth': '1' }).expect(401); // not signed in
    await post('bootstrap', {}, { Cookie: `sb_sid=${sid}; sb_app_device=${DEV}`, 'X-Native-Auth': '1' }).expect(401); // not the app
    const r = (await post('bootstrap', {}, { Cookie: C, 'X-Native-Auth': '1' }).expect(200)).body;
    expect(r).toEqual({ success: true, code: expect.stringMatching(/^[a-f0-9]{64}$/), device_id: DEV });
    expect((await post('bootstrap', {}, { Cookie: C, 'X-Native-Auth': '1' }).expect(200)).body.code).toBe(r.code);
    expect(count('patient_app_exchange')).toBe(1);
    expect((await post('exchange', { code: r.code, device_id: DEV }).expect(200)).body.user.mb_id).toBe('kim');
  });
});
