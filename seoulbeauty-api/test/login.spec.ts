import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { SessionBridgeService } from '../src/session-bridge/session-bridge.service';
import { LoginModule } from '../src/login/login.module';
import { cookieValue, kstYmd, safeAfterUrl } from '../src/login/login.logic';
import { createHash } from '../src/password-lost/password-lost.logic';

describe('login logic', () => {
  it('safe after url', () => {
    const f = '/index.php';
    expect(safeAfterUrl('/mypage.php?a=1', 'x.com', f)).toBe('/mypage.php?a=1');
    expect(safeAfterUrl('//evil.com', 'x.com', f)).toBe(f);
    expect(safeAfterUrl('/\\evil.com', 'x.com', f)).toBe(f);
    expect(safeAfterUrl('https://x.com:443/a', 'x.com:443', f)).toBe('https://x.com:443/a');
    expect(safeAfterUrl('https://evil.com/a', 'x.com', f)).toBe(f);
    expect(safeAfterUrl('javascript:alert(1)', 'x.com', f)).toBe(f);
    expect(safeAfterUrl('', 'x.com', f)).toBe(f);
  });
  it('kst date and cookie parsing', () => {
    expect(kstYmd(new Date('2026-10-30T16:00:00Z'))).toBe('20261031');
    expect(kstYmd(new Date('2026-10-30T14:59:00Z'))).toBe('20261030');
    expect(cookieValue('a=1; sb_patient_app=1; b=2', 'sb_patient_app')).toBe('1');
    expect(cookieValue('xsb_patient_app=1', 'sb_patient_app')).toBe('');
  });
});

describe('POST /v1/auth/login', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[] = [];
  let member: any; let certify = 0; let bridge: any[] = []; let bridgeOk = true; let configured = true;
  const hash = createHash('Abcdef12!');
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/cf_social_login_use/.test(sql)) return [[{ cf_social_login_use: 1, cf_social_servicelist: 'kakao, Google,apple', cf_kakao_rest_key: 'k', cf_google_clientid: 'g', cf_google_secret: '', cf_apple_team_id: 't', cf_apple_key_id: 'k', cf_apple_bundle_id: 'b', cf_apple_key_file: 'f' }]];
    if (/FROM g5_config/.test(sql)) return [[{ cf_use_email_certify: certify }]];
    if (/FROM g5_member/.test(sql)) return [member ? [member] : []];
    return [[]];
  };
  const post = (body: any, ip = '1.1.1.1', headers: Record<string, string> = {}) => {
    let r = request(app.getHttpServer()).post('/v1/auth/login').set('X-Requested-With', 'XMLHttpRequest').set('X-Forwarded-For', ip);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r.send(body);
  };
  beforeAll(async () => {
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args) } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, LoginModule] })
      .overrideProvider(SessionBridgeService).useValue({ configured: () => configured, mint: async (...a: any[]) => { bridge.push(a); return bridgeOk ? { ok: true, setCookies: ['PHPSESSID=abc; path=/; HttpOnly', 'ck_auto=t; path=/'] } : { ok: false, setCookies: [], error: 'x' }; } })
      .compile();
    app = mod.createNestApplication();
    (app.getHttpAdapter().getInstance() as any).set('trust proxy', 1);
    await app.init();
  });
  afterAll(async () => { await app.close(); });
  beforeEach(() => { calls = []; bridge = []; bridgeOk = true; configured = true; certify = 0; member = { mb_id: 'sb_abc', mb_password: hash, mb_intercept_date: '', mb_leave_date: '', mb_email_certify: '2026-10-30 12:00:00' }; });

  it('needs the same-origin header', async () => {
    await request(app.getHttpServer()).post('/v1/auth/login').send({ email: 'a@b.com', password: 'x' }).expect(403);
  });
  it('empty fields', async () => {
    expect((await post({ email: '', password: 'x' }, '2.0.0.1').expect(400)).body.message).toBe('이메일과 비밀번호를 입력해주세요.');
    expect(calls).toHaveLength(0);
  });
  it('signs in: password checked here, session minted by PHP, cookies relayed, password not trimmed', async () => {
    const r = await post({ email: ' Kim@Naver.com ', password: 'Abcdef12!', url: '/mypage.php' }, '3.0.0.1').expect(201);
    expect(r.body).toEqual({ success: true, redirect: '/mypage.php' });
    expect(r.headers['set-cookie']).toHaveLength(2);
    expect(r.headers['cache-control']).toContain('no-store');
    expect(calls.find((c) => /FROM g5_member/.test(c.sql))!.args).toEqual(['kim@naver.com']);
    expect(bridge[0].slice(0, 3)).toEqual(['sb_abc', 'login', false]);
    await post({ email: 'kim@naver.com', password: ' Abcdef12!' }, '3.0.0.2').expect(401);
  });
  it('remember: the checkbox or the app cookie', async () => {
    await post({ email: 'a@b.com', password: 'Abcdef12!', remember: true }, '4.0.0.1').expect(201);
    await post({ email: 'a@b.com', password: 'Abcdef12!' }, '4.0.0.2', { Cookie: 'sb_patient_app=1' }).expect(201);
    expect(bridge.map((a) => a[2])).toEqual([true, true]);
    expect(bridge[1][3]).toBe('sb_patient_app=1');
  });
  it('wrong password, unknown e-mail: same answer', async () => {
    const a = await post({ email: 'a@b.com', password: 'nope' }, '5.0.0.1').expect(401);
    member = null;
    const b = await post({ email: 'zzz@b.com', password: 'nope' }, '5.0.0.2').expect(401);
    expect(a.body).toEqual(b.body); expect(a.body.message).toBe('이메일 또는 비밀번호가 올바르지 않습니다.');
    expect(bridge).toHaveLength(0);
  });
  it('blocked, left, unverified: same answer', async () => {
    const today = kstYmd();
    let n = 10;
    for (const patch of [{ mb_intercept_date: today }, { mb_leave_date: '20200101' }, { mb_email_certify: '0000-00-00 00:00:00' }]) {
      certify = 'mb_email_certify' in patch ? 1 : 0;
      member = { ...member, ...patch };
      expect((await post({ email: `x${n}@b.com`, password: 'Abcdef12!' }, `6.0.0.${n++}`).expect(401)).body.message).toBe('이메일 또는 비밀번호가 올바르지 않습니다.');
      member = { mb_id: 'sb_abc', mb_password: hash, mb_intercept_date: '', mb_leave_date: '', mb_email_certify: '2026-10-30 12:00:00' };
    }
    member = { ...member, mb_intercept_date: '29991231' };
    await post({ email: 'future@b.com', password: 'Abcdef12!' }, '6.1.0.1').expect(201);
    expect(bridge).toHaveLength(1);
  });
  it('login options follow the Gnuboard social settings', async () => {
    const r = await request(app.getHttpServer()).get('/v1/auth/login-options').expect(200);
    expect(r.body).toEqual({ kakao: true, google: false, apple: true });
    expect(r.headers['cache-control']).toContain('s-maxage=30');
  });
  it('a hash that is not Gnuboard PBKDF2 (bcrypt, md5) is just a wrong password', async () => {
    for (const h of ['$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', '83c2f8a1b2c3d4e5f60718293a4b5c6d']) {
      member = { ...member, mb_password: h };
      const r = await post({ email: 'old@b.com', password: 'x' }, '7.0.0.1').expect(401);
      expect(r.body.message).toBe('이메일 또는 비밀번호가 올바르지 않습니다.');
    }
    expect(bridge).toHaveLength(0);
  });
  it('rate limits: 5 wrong per ip+e-mail, then even the right password waits; correct logins do not count', async () => {
    for (let i = 0; i < 3; i++) await post({ email: 'ok@b.com', password: 'Abcdef12!' }, '8.0.0.1').expect(201);
    for (let i = 0; i < 5; i++) await post({ email: 'r@b.com', password: 'bad' }, '8.0.0.2').expect(401);
    const r = await post({ email: 'r@b.com', password: 'Abcdef12!' }, '8.0.0.2').expect(429);
    expect(r.body.message).toMatch(/^로그인 시도가 너무 많습니다\. \d+초 후 다시 시도해주세요\.$/);
    await post({ email: 'r@b.com', password: 'Abcdef12!' }, '8.0.0.3').expect(201);   // other ip
  });
  it('per ip: 10 wrong', async () => {
    for (let i = 0; i < 10; i++) await post({ email: `u${i}@b.com`, password: 'bad' }, '9.0.0.1').expect(401);
    await post({ email: 'fresh@b.com', password: 'Abcdef12!' }, '9.0.0.1').expect(429);
  });
  it('bridge not configured / bridge failure', async () => {
    configured = false;
    await post({ email: 'a@b.com', password: 'Abcdef12!' }, '10.0.0.1').expect(503);
    configured = true; bridgeOk = false;
    await post({ email: 'a@b.com', password: 'Abcdef12!' }, '10.0.0.2').expect(502);
  });
});
