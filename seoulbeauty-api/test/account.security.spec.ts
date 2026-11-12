import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { AccountModule } from '../src/account/account.module';
import { fullReason, isEmail } from '../src/account/account-security.logic';
import { AccountSecurityService } from '../src/account/account-security.service';

describe('account security logic', () => {
  it('withdraw reason is "<reason> - <detail>"', () => {
    expect(fullReason('기타', '')).toBe('기타');
    expect(fullReason('기타', '  더 나은 서비스 ')).toBe('기타 - 더 나은 서비스');
    expect(fullReason('', 'x')).toBe(' - x');
    expect(fullReason(undefined, undefined)).toBe('');
    expect(fullReason('기타', 'a'.repeat(900)).length).toBe('기타 - '.length + 500);
  });
  it('e-mail shape', () => {
    expect(isEmail('a@b.co')).toBe(true);
    expect(isEmail('a b@c.co')).toBe(false);
    expect(isEmail('nope')).toBe(false);
    expect(isEmail(`${'a'.repeat(100)}@b.co`)).toBe(false);
  });
});

/* The three actions are carried out by Nest itself; PHP is only asked who is signed in. */
import { MailService } from '../src/mail/mail.service';
import { createHash as gnuHash } from '../src/password-lost/password-lost.logic';
import { AccountSecurityNativeService } from '../src/account/account-security-native.service';
import { passwordMatches } from '../src/login/login.logic';

describe('account security API', () => {
  let app: INestApplication;
  let sql: { q: string; p: any[] }[];
  let phpCalls: string[];
  let kakao: { status: number; body: unknown } | 'throw';
  let kakaoCalls: { auth: string; body: string }[];
  let mails: { to: string; subject: string; html: string }[];
  let mailOk: boolean;
  let member: Record<string, any> | null;
  let profile: { provider: string; identifier: string } | null;
  let emailOwners: string[];
  let deleteMemberRows: number;
  const realFetch = global.fetch;
  const H = { Cookie: 'sid=kim', 'X-Requested-With': 'XMLHttpRequest' };
  const post = (path: string, body: object) => request(app.getHttpServer()).post(path).set(H).send(body);
  const ran = (re: RegExp) => sql.filter((x) => re.test(x.q));
  const query = async (q: string, p: any[] = []) => {
    sql.push({ q, p });
    if (/^SELECT mb_no, mb_password FROM g5_member/.test(q) || /^SELECT mb_no, mb_id, mb_password/.test(q)) return [member ? [member] : []];
    if (/^SELECT mb_id FROM g5_member WHERE mb_email/.test(q)) return [emailOwners.includes(p[0]) ? [{ mb_id: 'other' }] : []];
    if (/FROM g5_member_social_profiles WHERE mb_id = \? LIMIT 1/.test(q) && /^SELECT/.test(q)) return [profile ? [profile] : []];
    if (/^SELECT cf_admin/.test(q)) return [[{ cf_admin: 'admin' }]];
    if (/information_schema/.test(q)) return [[...(p[0] as string[]).filter((t) => t !== 'g5_memo').flatMap((t) => ['member_id', 'mb_id', 'mb_no', 'user_id', 'reported_user_id', 'g5_mb_id'].map((c) => ({ t, c })))]];
    if (/^SELECT hms_user_id FROM member_sync/.test(q)) return [[{ hms_user_id: 77 }]];
    if (/^SELECT \(SELECT COUNT/.test(q)) return [[{ n: 0 }]];
    if (/^DELETE FROM g5_member WHERE/.test(q)) return [{ affectedRows: deleteMemberRows }];
    if (/^(UPDATE|DELETE|INSERT)/.test(q)) return [{ affectedRows: 1 }];
    return [[]];
  };

  beforeAll(async () => {
    process.env.KAKAO_ADMIN_KEY = 'admin-key';
    global.fetch = jest.fn(async (u: any, init: any = {}) => {
      const url = new URL(String(u));
      if (url.pathname === '/api/session_me.php') return new Response(JSON.stringify((init.headers?.cookie || '').includes('sid=kim') ? { loggedIn: true, user: { id: 'kim', no: 42, name: 'x', nickname: 'n', level: 2, phone: '' } } : { loggedIn: false, user: null }), { status: 200 });
      if (url.hostname === 'kapi.kakao.com') {
        kakaoCalls.push({ auth: init.headers?.Authorization, body: String(init.body) });
        if (kakao === 'throw') throw new Error('timeout');
        return new Response(JSON.stringify(kakao.body), { status: kakao.status });
      }
      phpCalls.push(url.pathname);
      return new Response('{}', { status: 500 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: { query, getConnection: async () => ({ query, release: () => undefined }) } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, AccountModule] })
      .overrideProvider(MailService).useValue({ send: async (to: string, subject: string, html: string) => { mails.push({ to, subject, html }); return mailOk ? { success: true } : { success: false, error: 'smtp down' }; } })
      .compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.KAKAO_ADMIN_KEY; await app.close(); });
  beforeEach(() => {
    sql = []; phpCalls = []; kakaoCalls = []; mails = []; mailOk = true; emailOwners = []; deleteMemberRows = 1; profile = null;
    kakao = { status: 200, body: { id: 12345 } };
    member = { mb_no: 42, mb_id: 'kim', mb_password: gnuHash('old-pass-1'), mb_nick: 'n', mb_email: 'kim@a.co' };
    (app.get(AccountSecurityService) as any).attempts.clear();
    (app.get(AccountSecurityNativeService) as any).codes.clear();
  });

  it('401 for visitors, 403 without the same-origin header; nothing is read or written', async () => {
    await request(app.getHttpServer()).post('/v1/account/password').set('X-Requested-With', 'XMLHttpRequest').send({}).expect(401);
    await request(app.getHttpServer()).post('/v1/account/withdraw').set('Cookie', 'sid=kim').send({}).expect(403);
    expect(sql).toEqual([]);
  });

  it('input is checked before anything is read; attempts are limited per member', async () => {
    expect((await post('/v1/account/password', { new_pw: 'abcdef', new_pw_confirm: 'abcdef' }).expect(400)).body.field).toBe('current_pw');
    expect((await post('/v1/account/password', { current_pw: 'x', new_pw: 'abcdef', new_pw_confirm: 'abcdeX' }).expect(400)).body.field).toBe('new_pw_confirm');
    expect((await post('/v1/account/password', { current_pw: 'x', new_pw: 'abc', new_pw_confirm: 'abc' }).expect(400)).body.field).toBe('new_pw');
    expect((await post('/v1/account/email/send', { new_email: 'nope' }).expect(400)).body.field).toBe('new_email');
    expect((await post('/v1/account/email/verify', { code: '12a456' }).expect(400)).body.field).toBe('code');
    expect((await post('/v1/account/withdraw', { reason: '기타' }).expect(400)).body.field).toBe('password');
    expect((await post('/v1/account/withdraw', { reason: '없는 사유', password: 'x' }).expect(400)).body.field).toBe('reason');
    expect(ran(/^(UPDATE|DELETE|INSERT|START)/)).toHaveLength(0);
    for (let i = 0; i < 5; i++) await post('/v1/account/password', { current_pw: 'nope', new_pw: 'new-pass-1', new_pw_confirm: 'new-pass-1' });
    await post('/v1/account/password', { current_pw: 'nope', new_pw: 'new-pass-1', new_pw_confirm: 'new-pass-1' }).expect(429);
  });

  it('password: wrong current password changes nothing', async () => {
    const r = await post('/v1/account/password', { current_pw: 'nope', new_pw: 'new-pass-1', new_pw_confirm: 'new-pass-1' }).expect(422);
    expect(r.body.field).toBe('current_pw');
    expect(ran(/^UPDATE/)).toHaveLength(0);
    expect(ran(/^ROLLBACK/)).toHaveLength(1);
    expect(phpCalls).toEqual([]);
  });

  it('password: new hash, HMS hash, then every sign-in revoked; PHP is not called', async () => {
    const r = await post('/v1/account/password', { current_pw: 'old-pass-1', new_pw: 'new-pass-1', new_pw_confirm: 'new-pass-1' }).expect(201);
    expect(r.body.success).toBe(true);
    const up = ran(/^UPDATE g5_member SET mb_password/)[0];
    expect(passwordMatches('new-pass-1', up.p[0])).toBe(true);
    const hms = ran(/^UPDATE users SET password/)[0]; // the HMS user linked through member_sync gets a bcrypt hash of the same password
    expect(hms.p[1]).toBe(77);
    expect(hms.p[0]).toMatch(/^\$2y\$/);
    const order = sql.map((x) => x.q);
    expect(order.findIndex((q) => /^COMMIT/.test(q))).toBeLessThan(order.findIndex((q) => /mb_cred_epoch = mb_cred_epoch \+ 1/.test(q)));
    expect(ran(/^DELETE FROM sso_tokens/)).toHaveLength(1);
    expect(phpCalls).toEqual([]);
  });

  it('e-mail: taken address 409; failed mail is reported and leaves no code', async () => {
    emailOwners = ['taken@a.co'];
    await post('/v1/account/email/send', { new_email: 'taken@a.co' }).expect(409);
    mailOk = false;
    await post('/v1/account/email/send', { new_email: 'new@a.co' }).expect(503);
    await post('/v1/account/email/verify', { code: '123456' }).expect(422);
    expect(ran(/^UPDATE/)).toHaveLength(0);
  });

  it('e-mail: the mailed code saves the address once', async () => {
    const r = await post('/v1/account/email/send', { new_email: 'new@a.co' }).expect(201);
    expect(r.body).toEqual({ success: true, email: 'new@a.co', expiresIn: 180 });
    expect(JSON.stringify(r.body)).not.toMatch(/\d{6}/);
    const code = /<b>(\d{6})<\/b>/.exec(mails[0].html)![1];
    const wrong = code === '000000' ? '000001' : '000000';
    expect((await post('/v1/account/email/verify', { code: wrong }).expect(422)).body.field).toBe('code');
    const ok = await post('/v1/account/email/verify', { code }).expect(201);
    expect(ok.body).toEqual({ success: true, email: 'new@a.co' });
    expect(ran(/^UPDATE g5_member SET mb_email/)[0].p).toEqual(['new@a.co', 'kim']);
    await post('/v1/account/email/verify', { code }).expect(422); // used
    expect(phpCalls).toEqual([]);
  });

  it('e-mail: five wrong codes end the attempt; an address taken meanwhile is refused', async () => {
    await post('/v1/account/email/send', { new_email: 'new@a.co' }).expect(201);
    const code = /<b>(\d{6})<\/b>/.exec(mails[0].html)![1];
    const wrong = code === '000000' ? '000001' : '000000';
    for (let i = 0; i < 4; i++) await post('/v1/account/email/verify', { code: wrong }).expect(422);
    await post('/v1/account/email/verify', { code: wrong }).expect(429);
    await post('/v1/account/email/verify', { code }).expect(422);
    await post('/v1/account/email/send', { new_email: 'new@a.co' }).expect(201);
    emailOwners = ['new@a.co'];
    await post('/v1/account/email/verify', { code: /<b>(\d{6})<\/b>/.exec(mails[1].html)![1] }).expect(409);
    expect(ran(/^UPDATE/)).toHaveLength(0);
  });

  it('withdraw, password member: wrong password deletes nothing', async () => {
    const r = await post('/v1/account/withdraw', { reason: '기타', password: 'nope' }).expect(422);
    expect(r.body.field).toBe('password');
    expect(ran(/^(DELETE|INSERT|UPDATE)/)).toHaveLength(0);
  });

  it('withdraw, password member: reason row and the account only (service data stays, as in withdraw.php)', async () => {
    const r = await post('/v1/account/withdraw', { reason: '기타', detail: 'bye', password: 'old-pass-1' }).expect(201);
    expect(r.body.success).toBe(true);
    expect(ran(/^INSERT INTO gangnamunni_withdrawals/)[0].p).toEqual(['kim', 'n', 'kim@a.co', '기타 - bye']);
    expect(ran(/^UPDATE users SET status = 0/)[0].p).toEqual([77]);
    expect(ran(/^DELETE FROM `/)).toHaveLength(0);
    expect(ran(/^DELETE FROM gangnamunni_admins WHERE g5_mb_id/)).toHaveLength(1);
    expect(ran(/^DELETE FROM g5_member WHERE mb_id/)[0].p).toEqual(['kim']);
    expect(sql[sql.length - 1].q).toBe('COMMIT');
    expect(kakaoCalls).toHaveLength(0);
    expect(phpCalls).toEqual([]);
  });

  it('withdraw, Kakao member: unlink first, then the service data by number and by id', async () => {
    profile = { provider: 'Kakao', identifier: '12345' };
    await post('/v1/account/withdraw', { reason: '기타' }).expect(201);
    expect(kakaoCalls).toEqual([{ auth: 'KakaoAK admin-key', body: 'target_id_type=user_id&target_id=12345' }]);
    expect(ran(/^INSERT INTO gangnamunni_withdrawals/)).toHaveLength(0);
    const dels = ran(/^DELETE FROM `/).map((x) => `${/`(\w+)` WHERE `(\w+)`/.exec(x.q)!.slice(1).join('.')}=${x.p[0]}`);
    expect(dels).toContain('gangnamunni_wishlist.member_id=42');
    expect(dels).toContain('gangnamunni_bookings.mb_id=kim');
    expect(dels).toContain('gangnamunni_members.mb_no=42');
    expect(dels.some((d) => d.startsWith('g5_memo'))).toBe(false); // a table the schema does not have is skipped
    expect(dels).toHaveLength(7 + 14 - 2); // every listed table except the two g5_memo columns
  });

  it('withdraw, Kakao member: a failed unlink stops before any delete; "already unlinked" goes on', async () => {
    profile = { provider: 'kakao', identifier: '12345' };
    kakao = { status: 401, body: { code: -401 } };
    await post('/v1/account/withdraw', {}).expect(502);
    kakao = 'throw';
    await post('/v1/account/withdraw', {}).expect(502);
    kakao = { status: 200, body: { id: 999 } };
    await post('/v1/account/withdraw', {}).expect(502);
    expect(ran(/^(DELETE|UPDATE|START)/)).toHaveLength(0);
    kakao = { status: 400, body: { code: -101 } };
    await post('/v1/account/withdraw', {}).expect(201);
  });

  it('withdraw: the member row not deleted -> rolled back, 500; the site owner is refused', async () => {
    deleteMemberRows = 0;
    await post('/v1/account/withdraw', { password: 'old-pass-1' }).expect(500);
    expect(sql[sql.length - 1].q).toBe('ROLLBACK');
    sql = []; member = { ...member!, mb_id: 'admin' };
    await post('/v1/account/withdraw', { password: 'old-pass-1' }).expect(403);
    expect(ran(/^(DELETE|UPDATE|START)/)).toHaveLength(0);
  });
});
