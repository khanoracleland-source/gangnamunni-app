import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import * as bcrypt from 'bcryptjs';
import { DB } from '../src/db/db.module';
import { MailService, reservedMailDomain } from '../src/mail/mail.service';
import { PasswordLostService } from '../src/password-lost/password-lost.service';
import { PasswordLostModule } from '../src/password-lost/password-lost.module';
import { createHash, RateWindow, resetMail, resetMarker, resetLimitsOff, resetLimitsSkipped, resetMarkerValid, temporaryPassword, validateHash } from '../src/password-lost/password-lost.logic';

describe('password-lost logic', () => {
  it('matches Gnuboard create_hash (vector computed independently with python hashlib.pbkdf2_hmac)', () => {
    const salt = 'abcDEF123+/xyzABCDEF123456789012';
    expect(createHash('Passw0rd!x', salt)).toBe(`sha256:12000:${salt}:zPPlqftm+jlvb7WLZg0gRoofPxpxCYYy`);
    expect(validateHash('Passw0rd!x', createHash('Passw0rd!x'))).toBe(true);
    expect(validateHash('wrong', createHash('Passw0rd!x'))).toBe(false);
    expect(createHash('a').split(':')[2]).toHaveLength(32); // 24 bytes in base64
  });
  it('temporary password has every character group and the right length', () => {
    for (let i = 0; i < 50; i++) {
      const p = temporaryPassword(12);
      expect(p).toHaveLength(12);
      expect(p).toMatch(/[A-HJ-NP-Z]/); expect(p).toMatch(/[a-km-z]/); expect(p).toMatch(/[2-9]/); expect(p).toMatch(/[!@#$%*\-_]/);
    }
  });
  it('mail body escapes the name', () => {
    const m = resetMail('서울미녀', '<b>x</b>', 'Ab3!');
    expect(m.subject).toBe('[서울미녀] 비밀번호 재설정 안내');
    expect(m.html).toContain('&lt;b&gt;x&lt;/b&gt;님'); expect(m.html).toContain('Ab3!');
  });
  it('rate window records only allowed hits', () => {
    const r = new RateWindow(2, 1000);
    expect(r.hit('k', 0).allowed).toBe(true); expect(r.hit('k', 10).allowed).toBe(true);
    const x = r.hit('k', 20); expect(x.allowed).toBe(false); expect(x.retry).toBe(1);
    expect(r.hit('k', 1005).allowed).toBe(true);
    expect(r.hit('other', 20).allowed).toBe(true);
  });
  it('reserved mail domains', () => {
    for (const a of ['a@example.com', 'a@x.test', 'a@foo.local']) expect(reservedMailDomain(a)).toBe(true);
    expect(reservedMailDomain('a@naver.com')).toBe(false);
  });
});

describe('POST /v1/auth/password-lost', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[] = [];
  let members: any[];
  let mailOk = true;
  let mails: { to: string; subject: string; html: string }[] = [];
  let hmsId = 0;
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/FROM g5_member WHERE LOWER/.test(sql)) return [members];
    if (/FROM g5_config/.test(sql)) return [[{ cf_title: '서울미녀', cf_admin: 'admin' }]];
    if (/^UPDATE g5_member SET mb_password/.test(sql)) return [{ affectedRows: 1 }];
    if (/FROM member_sync/.test(sql)) return [hmsId ? [{ hms_user_id: hmsId }] : []];
    return [{ affectedRows: 1 }];
  };
  const post = (email: any, ip = '1.1.1.1') => request(app.getHttpServer()).post('/v1/auth/password-lost').set('X-Requested-With', 'XMLHttpRequest').set('X-Forwarded-For', ip).send({ email });
  beforeAll(async () => {
    const conn = { query: async (sql: string, args: any[] = []) => respond(sql, args), release: () => undefined };
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args), getConnection: async () => conn } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, PasswordLostModule] })
      .overrideProvider(MailService).useValue({ send: async (to: string, subject: string, html: string) => { mails.push({ to, subject, html }); return mailOk ? { success: true } : { success: false, error: 'smtp down' }; } })
      .compile();
    app = mod.createNestApplication();
    (app.getHttpAdapter().getInstance() as any).set('trust proxy', 1);
    await app.init();
  });
  afterAll(async () => { await app.close(); });
  beforeEach(() => { calls = []; mails = []; mailOk = true; hmsId = 0; members = [{ mb_no: 7, mb_id: 'kim', mb_name: '김민', mb_nick: 'k', mb_email: 'kim@naver.com', mb_leave_date: '' }]; });
  const seq = (re: RegExp) => calls.findIndex((c) => re.test(c.sql));

  it('needs the same-origin header', async () => {
    await request(app.getHttpServer()).post('/v1/auth/password-lost').send({ email: 'kim@naver.com' }).expect(403);
  });
  it('rejects a bad address', async () => {
    const r = await post('nope', '2.2.2.2').expect(400);
    expect(r.body).toEqual({ success: false, message: '올바른 이메일 주소를 입력해주세요.' });
    expect(calls).toHaveLength(0);
  });
  it('member: hash saved, mail sent, then committed and every credential revoked', async () => {
    const r = await post('Kim@Naver.com', '3.3.3.3').expect(201);
    expect(r.headers['cache-control']).toContain('no-store');
    expect(r.body.success).toBe(true); expect(r.body.requestId).toMatch(/^[0-9a-f]{12}$/);
    expect(mails).toHaveLength(1); expect(mails[0].to).toBe('kim@naver.com');
    const up = calls.find((c) => /^UPDATE g5_member SET mb_password/.test(c.sql))!;
    const plain = /<strong[^>]*>([^<]+)<\/strong>/.exec(mails[0].html)![1];
    expect(validateHash(plain, up.args[0])).toBe(true);       // the mailed password is the one stored
    expect(up.args[2]).toBe(7);
    expect(up.args[1]).toMatch(/^pwreset:\d+$/);                 // an open reset request is marked (verify only works with it)
    expect(seq(/START TRANSACTION/)).toBeLessThan(seq(/^UPDATE g5_member SET mb_password/));
    expect(seq(/COMMIT/)).toBeGreaterThan(seq(/^UPDATE g5_member SET mb_password/));
    expect(seq(/mb_cred_epoch \+ 1/)).toBeGreaterThan(seq(/COMMIT/));
    expect(seq(/DELETE FROM sso_tokens/)).toBeGreaterThan(seq(/COMMIT/));
    expect(calls.find((c) => /DELETE FROM sso_tokens/.test(c.sql))!.args).toEqual(['kim']);
    expect(calls.some((c) => /^ROLLBACK/.test(c.sql))).toBe(false);
  });
  it('linked HMS user: bcrypt hash of the same password ($2y$), tokens revoked', async () => {
    hmsId = 55;
    await post('kim@naver.com', '4.4.4.4').expect(201);
    const plain = /<strong[^>]*>([^<]+)<\/strong>/.exec(mails[0].html)![1];
    const u = calls.find((c) => /^UPDATE users SET password/.test(c.sql))!;
    expect(u.args[1]).toBe(55); expect(u.args[0]).toMatch(/^\$2y\$10\$/);
    expect(bcrypt.compareSync(plain, u.args[0].replace(/^\$2y\$/, '$2b$'))).toBe(true);
    const t = calls.find((c) => /DELETE FROM personal_access_tokens/.test(c.sql))!;
    expect(t.args).toEqual(['App\\Models\\User', 55]);
  });
  it('mail failure: rolled back, nothing revoked, same answer', async () => {
    mailOk = false;
    const r = await post('kim@naver.com', '5.5.5.5').expect(201);
    expect(r.body.success).toBe(true);
    expect(calls.some((c) => /^ROLLBACK/.test(c.sql))).toBe(true);
    expect(calls.some((c) => /^COMMIT/.test(c.sql))).toBe(false);
    expect(calls.some((c) => /mb_cred_epoch/.test(c.sql) || /sso_tokens/.test(c.sql))).toBe(false);
  });
  it('unknown / duplicate / left / super admin: same answer, nothing happens', async () => {
    const cases: any[][] = [[], [{ mb_no: 1, mb_id: 'a', mb_email: 'x@y.com', mb_leave_date: '' }, { mb_no: 2, mb_id: 'b', mb_email: 'x@y.com', mb_leave_date: '' }],
      [{ mb_no: 3, mb_id: 'c', mb_email: 'x@y.com', mb_leave_date: '20260101' }],
      [{ mb_no: 5, mb_id: 'd', mb_email: 'x@y.com', mb_leave_date: '', mb_intercept_date: '20200101' }], [{ mb_no: 4, mb_id: 'admin', mb_name: 'A', mb_email: 'x@y.com', mb_leave_date: '' }]];
    let n = 10;
    for (const m of cases) {
      members = m; calls = [];
      const r = await post('x@y.com', `6.6.6.${n++}`).expect(201);
      expect(r.body.success).toBe(true);
      expect(mails).toHaveLength(0);
      expect(calls.some((c) => /^UPDATE/.test(c.sql))).toBe(false);
    }
  });
  it('rate limits: 60 s per address+ip, then 3 per ip', async () => {
    await post('a@naver.com', '7.7.7.7').expect(201);
    const again = await post('a@naver.com', '7.7.7.7').expect(429);
    expect(again.body).toEqual({ success: false, message: '잠시 후 다시 시도해주세요.' });
    await post('b@naver.com', '7.7.7.7').expect(201);
    await post('c@naver.com', '7.7.7.7').expect(201);
    await post('d@naver.com', '7.7.7.7').expect(429);
    await post('d@naver.com', '7.7.7.8').expect(201);
  });
});

describe('POST /v1/auth/password-reset (verify + confirm)', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[] = [];
  let member: any;
  let em = '';
  let n = 0;
  const TEMP = 'Tmp#pass12Ab';
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/FROM g5_member WHERE LOWER/.test(sql)) return [member && args[0] === member.mb_email ? [member] : []];
    if (/FROM g5_config/.test(sql)) return [[{ cf_admin: 'admin' }]];
    if (/FROM g5_member WHERE mb_id = \?/.test(sql)) return [member ? [member] : []];
    if (/^UPDATE g5_member SET mb_password/.test(sql)) { member = { ...member, mb_password: args[0] }; return [{ affectedRows: 1 }]; }
    if (/FROM member_sync/.test(sql)) return [[]];
    return [{ affectedRows: 1 }];
  };
  const verify = (email: any, temp: any, ip: string) => request(app.getHttpServer()).post('/v1/auth/password-reset/verify').set('X-Requested-With', 'XMLHttpRequest').set('X-Forwarded-For', ip).send({ email, temporaryPassword: temp });
  const confirm = (ticket: any, password: any, ip: string) => request(app.getHttpServer()).post('/v1/auth/password-reset/confirm').set('X-Requested-With', 'XMLHttpRequest').set('X-Forwarded-For', ip).send({ ticket, password });
  beforeAll(async () => {
    process.env.SESSION_BRIDGE_SECRET = 'x'.repeat(40);
    const conn = { query: async (sql: string, args: any[] = []) => respond(sql, args), release: () => undefined };
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args), getConnection: async () => conn } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, PasswordLostModule] }).overrideProvider(MailService).useValue({ send: async () => ({ success: true }) }).compile();
    app = mod.createNestApplication();
    (app.getHttpAdapter().getInstance() as any).set('trust proxy', 1);
    await app.init();
  });
  afterAll(async () => { await app.close(); });
  beforeEach(() => { calls = []; em = `kim${++n}@naver.com`; member = { mb_no: 7, mb_id: 'kim', mb_email: em, mb_leave_date: '', mb_intercept_date: '', mb_email_certify: '2026-01-01', mb_lost_certify: resetMarker(), mb_password: createHash(TEMP) }; });

  it('wrong password and unknown e-mail: the same answer', async () => {
    const a = await verify(em, 'nope', '11.0.0.1').expect(401);
    const b = await verify('who@naver.com', TEMP, '11.0.0.2').expect(401);
    member = null;
    const c = await verify(em, TEMP, '11.0.0.3').expect(401);
    expect(a.body).toEqual(b.body); expect(b.body).toEqual(c.body);
  });
  it('temporary password → ticket → new password saved, then the ticket is dead', async () => {
    const v = await verify(em.toUpperCase(), TEMP, '11.0.1.1').expect(201);
    expect(v.headers['cache-control']).toContain('no-store');
    const ticket = v.body.ticket as string;
    expect(typeof ticket).toBe('string');
    await confirm(ticket, 'short1!', '11.0.1.2').expect(400);               // rule: 9~20, letter + digit + special
    await confirm(ticket, TEMP, '11.0.1.2').expect(400);                    // not the temporary one again
    await confirm('garbage', 'Newpass#123', '11.0.1.2').expect(401);
    await confirm(ticket, 'Newpass#123', '11.0.1.3').expect(201);
    const up = calls.find((c) => /^UPDATE g5_member SET mb_password/.test(c.sql))!;
    expect(validateHash('Newpass#123', up.args[0])).toBe(true);
    expect(calls.some((c) => /mb_cred_epoch \+ 1/.test(c.sql))).toBe(true);
    await confirm(ticket, 'Another#456', '11.0.1.4').expect(401);           // hash changed: single use
  });
  it('verify is limited: 5 tries per address+ip', async () => {
    for (let i = 0; i < 5; i++) await verify(em, `bad${i}`, '11.0.2.1').expect(401);
    await verify(em, TEMP, '11.0.2.1').expect(429);
  });
  it('only with an open (marked, unexpired) reset request; blocked / left accounts refused', async () => {
    member = { ...member, mb_lost_certify: '' };
    await verify(em, TEMP, '11.0.3.1').expect(401);            // right password, but nobody asked for a reset
    member = { ...member, mb_lost_certify: `pwreset:${Date.now() - 1000}` };
    await verify(em, TEMP, '11.0.3.2').expect(401);            // expired
    member = { ...member, mb_lost_certify: resetMarker(), mb_intercept_date: '20200101' };
    await verify(em, TEMP, '11.0.3.3').expect(401);            // blocked
    member = { ...member, mb_intercept_date: '', mb_leave_date: '20200101' };
    await verify(em, TEMP, '11.0.3.4').expect(401);            // left
    member = { ...member, mb_leave_date: '' };
    await verify(em, TEMP, '11.0.3.5').expect(201);
  });
  it('confirm refuses a ticket when the request was closed or the account got blocked meanwhile', async () => {
    const t = (await verify(em, TEMP, '11.0.4.1').expect(201)).body.ticket;
    member = { ...member, mb_intercept_date: '20200101' };
    await confirm(t, 'Newpass#123', '11.0.4.2').expect(401);
    member = { ...member, mb_intercept_date: '', mb_lost_certify: '' };
    await confirm(t, 'Newpass#123', '11.0.4.3').expect(401);
  });
  it('limit per e-mail across different ips (10/h)', async () => {
    for (let i = 0; i < 10; i++) await verify(em, `bad${i}`, `11.0.5.${i}`).expect(401);
    await verify(em, TEMP, '11.0.5.99').expect(429);
  });
  it('marker helpers', () => {
    expect(resetMarkerValid(resetMarker())).toBe(true);
    expect(resetMarkerValid(resetMarker(Date.now() - 31 * 60_000))).toBe(false);
    expect(resetMarkerValid('')).toBe(false); expect(resetMarkerValid('abc')).toBe(false);
  });
});

describe('PASSWORD_LOST_RATE_LIMIT flag', () => {
  const f = (env: Record<string, string>) => resetLimitsOff({ PASSWORD_LOST_RATE_LIMIT: 'off', ...env } as any);
  it('works only with NODE_ENV explicitly development or test', () => {
    expect(f({ NODE_ENV: 'development' })).toBe(true);
    expect(f({ NODE_ENV: 'test' })).toBe(true);
    expect(f({ NODE_ENV: 'production' })).toBe(false);
    expect(f({ NODE_ENV: 'staging' })).toBe(false);
    expect(f({})).toBe(false);                                   // NODE_ENV not set (pm2, hand-started node)
    expect(resetLimitsOff({ NODE_ENV: 'development' } as any)).toBe(false);
    expect(resetLimitsOff({ PASSWORD_LOST_RATE_LIMIT: 'on', NODE_ENV: 'development' } as any)).toBe(false);
  });
});

describe('PASSWORD_LOST_TEST_* rule', () => {
  const now = Date.parse('2026-11-03T06:00:00Z');
  const base = { PASSWORD_LOST_TEST_EMAILS: ' Me@Gmail.com ', PASSWORD_LOST_TEST_IPS: '1.2.3.4', PASSWORD_LOST_TEST_UNTIL: '2026-11-03T12:00:00Z', NODE_ENV: 'production' } as any;
  it('needs the listed email AND the listed ip, before the expiry', () => {
    expect(resetLimitsSkipped('me@gmail.com', '1.2.3.4', base, now)).toBe('test-rule');
    expect(resetLimitsSkipped('ME@gmail.com', '::ffff:1.2.3.4', base, now)).toBe('test-rule');
    expect(resetLimitsSkipped('me@gmail.com', '9.9.9.9', base, now)).toBeNull();     // knows the address, wrong ip
    expect(resetLimitsSkipped('victim@naver.com', '1.2.3.4', base, now)).toBeNull();  // right ip, other address
  });
  it('expires, and a missing/far-future end date or missing ip makes it inert', () => {
    expect(resetLimitsSkipped('me@gmail.com', '1.2.3.4', base, Date.parse('2026-11-03T12:00:01Z'))).toBeNull();
    expect(resetLimitsSkipped('me@gmail.com', '1.2.3.4', { ...base, PASSWORD_LOST_TEST_UNTIL: '2030-01-01T00:00:00Z' }, now)).toBeNull(); // > 24 h ahead
    expect(resetLimitsSkipped('me@gmail.com', '1.2.3.4', { ...base, PASSWORD_LOST_TEST_UNTIL: '' }, now)).toBeNull();
    expect(resetLimitsSkipped('me@gmail.com', '1.2.3.4', { ...base, PASSWORD_LOST_TEST_IPS: '' }, now)).toBeNull();
    expect(resetLimitsSkipped('me@gmail.com', '1.2.3.4', { PASSWORD_LOST_TEST_EMAILS: 'me@gmail.com' } as any, now)).toBeNull();
  });
});

describe('requestForMember (signed-in member who forgot the current password)', () => {
  const build = (mailOk: boolean, email = 'me@naver.com') => {
    const calls: string[] = [];
    const respond = (sql: string): any => {
      calls.push(sql);
      if (/SELECT mb_email FROM g5_member WHERE mb_id/.test(sql)) return [[{ mb_email: email }]];
      if (/FROM g5_member WHERE LOWER/.test(sql)) return [[{ mb_no: 1, mb_id: 'kim', mb_name: '김', mb_nick: 'k', mb_email: email, mb_leave_date: '', mb_intercept_date: '', mb_email_certify: '2020', }]];
      if (/FROM g5_config/.test(sql)) return [[{ cf_title: '서울미녀', cf_admin: 'admin', cf_use_email_certify: 0 }]];
      if (/FROM member_sync/.test(sql)) return [[]];
      return [{ affectedRows: 1 }];
    };
    const conn = { query: async (sql: string) => respond(sql), release: () => undefined };
    const db: any = { query: async (sql: string) => respond(sql), getConnection: async () => conn };
    const mail: any = { send: async () => (mailOk ? { success: true } : { success: false, error: 'smtp' }) };
    return { svc: new PasswordLostService(db, mail), calls };
  };
  it('mail accepted: sent=true and every sign-in revoked', async () => {
    const { svc, calls } = build(true);
    expect(await svc.requestForMember('kim', '1.1.1.1')).toEqual({ sent: true });
    expect(calls.some((c) => /mb_cred_epoch/.test(c))).toBe(true);
  });
  it('mail failure: sent=false, rolled back, nothing revoked (the member stays signed in)', async () => {
    const { svc, calls } = build(false);
    expect(await svc.requestForMember('kim', '1.1.1.1')).toEqual({ sent: false });
    expect(calls).toContain('ROLLBACK');
    expect(calls.some((c) => /mb_cred_epoch/.test(c))).toBe(false);
  });
  it('no usable e-mail on file: sent=false; asking again within a minute is limited', async () => {
    expect(await build(true, '').svc.requestForMember('kim', '1.1.1.1')).toEqual({ sent: false });
    const { svc } = build(true);
    await svc.requestForMember('kim', '1.1.1.1');
    await expect(svc.requestForMember('kim', '1.1.1.1')).rejects.toMatchObject({ status: 429 });
  });
});
