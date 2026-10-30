import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import * as bcrypt from 'bcryptjs';
import { DB } from '../src/db/db.module';
import { MailService, reservedMailDomain } from '../src/mail/mail.service';
import { PasswordLostModule } from '../src/password-lost/password-lost.module';
import { createHash, RateWindow, resetMail, temporaryPassword, validateHash } from '../src/password-lost/password-lost.logic';

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
    expect(up.args[1]).toBe(7);
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
      [{ mb_no: 3, mb_id: 'c', mb_email: 'x@y.com', mb_leave_date: '20260101' }], [{ mb_no: 4, mb_id: 'admin', mb_name: 'A', mb_email: 'x@y.com', mb_leave_date: '' }]];
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
