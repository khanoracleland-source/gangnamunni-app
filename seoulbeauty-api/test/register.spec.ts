import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { MailService } from '../src/mail/mail.service';
import { SessionBridgeService } from '../src/session-bridge/session-bridge.service';
import { RegisterModule } from '../src/register/register.module';
import { makeNick, OtpStore, parseBirth, PASSWORD_RE, prohibitedMail } from '../src/register/register.logic';
import { validateHash } from '../src/password-lost/password-lost.logic';

describe('register logic', () => {
  const now = new Date('2026-10-30T03:00:00Z'); // 12:00 KST
  it('birth date: calendar, future, 120 years, age', () => {
    expect(parseBirth('2000-10-30', now)).toEqual({ ymd: '20001030', age: 26 });
    expect(parseBirth('2000-10-31', now)).toEqual({ ymd: '20001031', age: 25 });
    expect(parseBirth('2000-02-30', now)).toBeNull();
    expect(parseBirth('2026-10-31', now)).toBeNull();
    expect(parseBirth('1906-10-30', now)).not.toBeNull();
    expect(parseBirth('1906-10-29', now)).toBeNull();
    expect(parseBirth('abc', now)).toBeNull();
  });
  it('KST day boundary: 16:00Z is already the next day in Korea', () => {
    expect(parseBirth('2026-10-31', new Date('2026-10-30T16:00:00Z'))).toEqual({ ymd: '20261031', age: 0 });
  });
  it('password rule', () => {
    expect(PASSWORD_RE.test('Abcdef12!')).toBe(true);
    for (const p of ['Abcdef12', 'abcdefgh!', '12345678!', 'Ab1!', 'Abcdef12!Abcdef12!xyz', 'Abc def12!']) expect(PASSWORD_RE.test(p)).toBe(false);
  });
  it('prohibited domains and nick', () => {
    expect(prohibitedMail('a@Bad.com', 'x.com\nbad.com\n')).toBe('bad.com 메일은 사용할 수 없습니다.');
    expect(prohibitedMail('a@ok.com', 'bad.com')).toBe('');
    expect(makeNick('김 민!', 'sb_abcdef1234', '')).toBe('김민ef1234'.length ? '김민' + 'abcdef1234'.slice(-8) : '');
    expect(makeNick('김민', 'sb_abcdef1234', 'admin,cdef')).toMatch(/^member[0-9a-f]{10}$/);
  });
  it('otp: wrong code counts, 5 wrong kills it, right code is consumed and leaves a verified mark', () => {
    const o = new OtpStore();
    expect(o.check('a@b.com', '1')).toBe('이메일 인증을 먼저 진행해주세요.');
    o.issue('a@b.com', '123456', 0);
    for (let i = 0; i < 4; i++) expect(o.check('a@b.com', '000000', 10)).toBe('인증코드가 올바르지 않습니다.');
    expect(o.check('a@b.com', '000000', 10)).toContain('횟수를 초과');
    expect(o.check('a@b.com', '123456', 10)).toBe('이메일 인증을 먼저 진행해주세요.');
    o.issue('a@b.com', '123456', 0);
    expect(o.check('a@b.com', '123456', 600_001)).toContain('만료');
    o.issue('a@b.com', '123456', 0);
    expect(o.check('a@b.com', '123456', 5)).toBe('');
    expect(o.check('a@b.com', '123456', 1_000)).toBe('');     // verified mark, same code
    expect(o.check('a@b.com', '', 1_000)).not.toBe('');       // the mark alone is not enough
    expect(o.check('a@b.com', '000000', 1_000)).not.toBe('');
    expect(o.check('a@b.com', '123456', 1_800_006)).toBe('이메일 인증을 먼저 진행해주세요.');
    expect(o.check('other@b.com', '123456', 5)).toBe('이메일 인증을 먼저 진행해주세요.');
  });
});

describe('POST /v1/auth/register', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[] = [];
  let mails: { to: string; subject: string; html: string }[] = [];
  let mailOk = true; let dupMember = false; let dupHospital = false; let insertRows = 1; let cfg: any;
  let bridge: any[] = []; let bridgeOk = true; let bridgeConfigured = true;
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/FROM g5_config/.test(sql)) return [[cfg]];
    if (/^SELECT mb_no FROM g5_member/.test(sql)) return [dupMember ? [{ mb_no: 1 }] : []];
    if (/FROM gangnamunni_hospitals/.test(sql)) return [dupHospital ? [{ id: 1 }] : []];
    if (/GET_LOCK/.test(sql)) return [[{ got: 1 }]];
    if (/^INSERT INTO g5_member/.test(sql)) return [{ affectedRows: insertRows }];
    return [[]];
  };
  const post = (path: string, body: any, ip = '1.1.1.1') => request(app.getHttpServer()).post(path).set('X-Requested-With', 'XMLHttpRequest').set('X-Forwarded-For', ip).send(body);
  const form = (o: any = {}) => ({ email: 'New@Naver.com', code: '', password: 'Abcdef12!', password2: 'Abcdef12!', name: '김민', birthDate: '2000-01-02', phone: '010-1234-5678', agreeTerms: true, agreePrivacy: true, agreeMarketing: false, ...o });
  const issue = async (email: string, ip: string) => {
    mails = [];
    await post('/v1/auth/register/code', { email }, ip).expect(201);
    return /letter-spacing:4px[^>]*>(\d{6})</.exec(mails[0].html)![1];
  };
  beforeAll(async () => {
    const conn = { query: async (sql: string, args: any[] = []) => respond(sql, args), release: () => undefined };
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args), getConnection: async () => conn } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, RegisterModule] })
      .overrideProvider(MailService).useValue({ send: async (to: string, subject: string, html: string) => { mails.push({ to, subject, html }); return mailOk ? { success: true } : { success: false, error: 'smtp down' }; } })
      .overrideProvider(SessionBridgeService).useValue({ configured: () => bridgeConfigured, mint: async (...a: any[]) => { bridge.push(a); return bridgeOk ? { ok: true, setCookies: ['PHPSESSID=abc; path=/; HttpOnly'] } : { ok: false, setCookies: [] }; } })
      .compile();
    app = mod.createNestApplication();
    (app.getHttpAdapter().getInstance() as any).set('trust proxy', 1);
    await app.init();
  });
  afterAll(async () => { await app.close(); });
  beforeEach(() => { calls = []; mails = []; bridge = []; mailOk = true; bridgeOk = true; bridgeConfigured = true; dupMember = false; dupHospital = false; insertRows = 1; cfg = { cf_title: '서울미녀', cf_register_level: 2, cf_prohibit_email: 'bad.com', cf_prohibit_id: '', cf_email_mb_member: 0, cf_email_mb_super_admin: 0, cf_admin_email: '' }; });

  it('same-origin header is required', async () => {
    await request(app.getHttpServer()).post('/v1/auth/register/code').send({ email: 'a@naver.com' }).expect(403);
    await request(app.getHttpServer()).post('/v1/auth/register').send(form()).expect(403);
  });
  it('code: bad address, prohibited domain, mail failure', async () => {
    expect((await post('/v1/auth/register/code', { email: 'x' }, '2.0.0.1').expect(400)).body.message).toBe('올바른 이메일 주소를 입력해주세요.');
    expect((await post('/v1/auth/register/code', { email: 'a@bad.com' }, '2.0.0.2').expect(400)).body.message).toBe('bad.com 메일은 사용할 수 없습니다.');
    mailOk = false;
    expect((await post('/v1/auth/register/code', { email: 'a@naver.com' }, '2.0.0.3').expect(502)).body.message).toContain('발송에 실패');
  });
  it('code: 60 s cooldown per address, 5 per ip', async () => {
    await post('/v1/auth/register/code', { email: 'c1@naver.com' }, '3.0.0.1').expect(201);
    expect((await post('/v1/auth/register/code', { email: 'c1@naver.com' }, '3.0.0.2').expect(429)).body.message).toMatch(/^잠시 후 다시 요청해주세요/);
    for (const n of [2, 3, 4, 5, 6]) await post('/v1/auth/register/code', { email: `c${n}@naver.com` }, '3.0.0.9').expect(201);
    expect((await post('/v1/auth/register/code', { email: 'c7@naver.com' }, '3.0.0.9').expect(429)).body.message).toMatch(/^인증 메일 요청이 너무 많습니다/);
  });
  it('registers: stores the Gnuboard row, relays the PHP session cookie', async () => {
    const code = await issue('new@naver.com', '4.0.0.1');
    const r = await post('/v1/auth/register', form({ code }), '4.0.0.1').expect(201);
    expect(r.body).toEqual({ success: true, loggedIn: true });
    expect(r.headers['set-cookie'][0]).toContain('PHPSESSID=abc');
    expect(r.headers['cache-control']).toContain('no-store');
    const ins = calls.find((c) => /^INSERT INTO g5_member/.test(c.sql))!;
    const [id, hash, name, nick, email, level, , , mk1, mk2, phone, birth, age] = ins.args;
    expect(id).toMatch(/^sb_[0-9a-f]{10}$/); expect(validateHash('Abcdef12!', hash)).toBe(true);
    expect([name, email, level, phone, birth, age, mk1, mk2]).toEqual(['김민', 'new@naver.com', 2, '01012345678', '20000102', '26', 0, 0]);
    expect(nick).toBe('김민' + id.slice(-8));
    expect(ins.sql).toContain("'general'");
    expect(calls.findIndex((c) => /GET_LOCK/.test(c.sql))).toBeLessThan(calls.findIndex((c) => /^INSERT/.test(c.sql)));
    expect(calls.some((c) => /RELEASE_LOCK/.test(c.sql))).toBe(true);
    expect(bridge[0].slice(0, 3)).toEqual([id, 'register', false]);
  });
  it('the code is single use but the 30-minute verified mark survives a failed field check', async () => {
    const code = await issue('v@naver.com', '5.0.0.1');
    expect((await post('/v1/auth/register', form({ email: 'v@naver.com', code, password2: 'x' }), '5.0.0.1').expect(400)).body.message).toBe('비밀번호가 일치하지 않습니다.');
    await post('/v1/auth/register', form({ email: 'v@naver.com', code: '' }), '5.0.0.1').expect(400);
    await post('/v1/auth/register', form({ email: 'v@naver.com', code }), '5.0.0.1').expect(201);
  });
  it('validation messages, in order', async () => {
    const code = await issue('m@naver.com', '6.0.0.1');
    const bad = async (o: any, msg: string) => expect((await post('/v1/auth/register', form({ email: 'm@naver.com', code, ...o }), '6.0.0.1').expect(400)).body.message).toBe(msg);
    await bad({ agreePrivacy: false }, '회원가입 약관과 개인정보 수집·이용에 동의해주세요.');
    await bad({ name: 'a' }, '이름을 2~50자의 올바른 형식으로 입력해주세요.');
    await bad({ birthDate: '2999-01-01' }, '올바른 생년월일을 입력해주세요.');
    await bad({ password: 'abc', password2: 'abc' }, '비밀번호는 영문, 숫자, 특수문자를 각각 포함한 9~20자로 입력해주세요.');
    await bad({ phone: '12345' }, '휴대폰 번호를 정확히 입력해주세요. (예: 01012345678)');
    expect(calls.some((c) => /^INSERT/.test(c.sql))).toBe(false);
  });
  it('wrong or missing code', async () => {
    expect((await post('/v1/auth/register', form({ email: 'nocode@naver.com', code: '123456' }), '7.0.0.1').expect(400)).body.message).toBe('이메일 인증을 먼저 진행해주세요.');
    await issue('wc@naver.com', '7.0.0.2');
    expect((await post('/v1/auth/register', form({ email: 'wc@naver.com', code: '000000' }), '7.0.0.2').expect(400)).body.message).toBe('인증코드가 올바르지 않습니다.');
  });
  it('duplicates: member, hospital admin, lost race', async () => {
    const c1 = await issue('d1@naver.com', '8.0.0.1'); dupMember = true;
    expect((await post('/v1/auth/register', form({ email: 'd1@naver.com', code: c1 }), '8.0.0.1').expect(409)).body.message).toMatch(/^이미 가입된 이메일입니다/);
    dupMember = false; dupHospital = true;
    expect((await post('/v1/auth/register', form({ email: 'd1@naver.com', code: c1 }), '8.0.0.1').expect(409)).body.message).toMatch(/병원관리자/);
    dupHospital = false; insertRows = 0;
    expect((await post('/v1/auth/register', form({ email: 'd1@naver.com', code: c1 }), '8.0.0.1').expect(409)).body.message).toMatch(/^이미 가입된 이메일입니다/);
    expect(bridge).toHaveLength(0);
  });
  it('bridge not configured: refuses before creating anything', async () => {
    const code = await issue('nb@naver.com', '9.1.0.1'); bridgeConfigured = false;
    expect((await post('/v1/auth/register', form({ email: 'nb@naver.com', code }), '9.1.0.1').expect(503)).body.message).toMatch(/처리할 수 없습니다/);
    expect(calls.some((c) => /^INSERT/.test(c.sql))).toBe(false);
  });
  it('bridge down: account exists, caller is told to sign in', async () => {
    const code = await issue('b@naver.com', '9.0.0.1'); bridgeOk = false;
    const r = await post('/v1/auth/register', form({ email: 'b@naver.com', code }), '9.0.0.1').expect(201);
    expect(r.body).toEqual({ success: true, loggedIn: false });
    expect(r.headers['set-cookie']).toBeUndefined();
  });
  it('welcome and admin notices follow the Gnuboard mail switches', async () => {
    cfg.cf_email_mb_member = 1; cfg.cf_email_mb_super_admin = 1; cfg.cf_admin_email = 'boss@naver.com';
    const code = await issue('w@naver.com', '10.0.0.1'); mails = [];
    await post('/v1/auth/register', form({ email: 'w@naver.com', code }), '10.0.0.1').expect(201);
    await new Promise((r) => setTimeout(r, 20));
    expect(mails.map((m) => m.to).sort()).toEqual(['boss@naver.com', 'w@naver.com']);
  });
});
