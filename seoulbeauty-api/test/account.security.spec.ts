import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { AccountModule } from '../src/account/account.module';
import { fullReason, isEmail, parsePhpJson } from '../src/account/account-security.logic';
import { AccountSecurityService } from '../src/account/account-security.service';

describe('account security logic', () => {
  it('parsePhpJson drops a script appended after the JSON', () => {
    expect(parsePhpJson('{"success":true,"message":"ok"}')).toEqual({ success: true, message: 'ok' });
    expect(parsePhpJson('{"success":true}\n<script>(function(){FlutterBridge.postMessage(JSON.stringify({a:1}))})();</script>')).toEqual({ success: true });
    expect(parsePhpJson('<html>login</html>')).toBeNull();
    expect(parsePhpJson('[1]')).toBeNull();
    expect(parsePhpJson('')).toBeNull();
  });
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

describe('account security API', () => {
  let app: INestApplication;
  let php: { url: string; method: string; headers: Record<string, string>; body: string | undefined }[];
  let sessionCalls: number;
  let social: boolean;
  let reply: (url: URL, init: any) => { status?: number; body: string } | 'throw';
  const realFetch = global.fetch;
  const H = { Cookie: 'sid=kim; PHPSESSID=abc', 'X-Requested-With': 'XMLHttpRequest' };
  const post = (path: string, body: object, headers: Record<string, string> = H) => request(app.getHttpServer()).post(path).set(headers).send(body);
  const json = (o: unknown, status = 200) => ({ status, body: JSON.stringify(o) });

  beforeAll(async () => {
    global.fetch = jest.fn(async (u: any, init: any = {}) => {
      const url = new URL(String(u));
      if (url.pathname === '/api/session_me.php') {
        sessionCalls++;
        const ok = (init.headers?.cookie || '').includes('sid=kim');
        return new Response(JSON.stringify(ok ? { loggedIn: true, user: { id: 'kim', no: 42, name: 'x', nickname: 'n', level: 2, phone: '' } } : { loggedIn: false, user: null }), { status: 200 });
      }
      php.push({ url: `${url.pathname}${url.search}`, method: init.method || 'GET', headers: init.headers || {}, body: init.body });
      const r = reply(url, init);
      if (r === 'throw') throw new Error('connect ECONNREFUSED');
      return new Response(r.body, { status: r.status ?? 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string) => (/g5_member_social_profiles/.test(sql) ? [social ? [{ provider: 'kakao' }] : []] : [[]]) } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, AccountModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => {
    php = []; sessionCalls = 0; social = false; reply = () => json({ success: true });
    (app.get(AccountSecurityService) as any).attempts.clear(); // the limiter is per member and in memory: isolate the tests
  });

  it('401 for visitors, 403 without the header; PHP is not called', async () => {
    await request(app.getHttpServer()).post('/v1/account/password').set('X-Requested-With', 'XMLHttpRequest').send({}).expect(401);
    await request(app.getHttpServer()).post('/v1/account/withdraw').set('Cookie', 'sid=kim').send({}).expect(403);
    expect(php.length).toBe(0);
  });

  describe('password', () => {
    const ok = { current_pw: 'old-pass', new_pw: 'new-pass1', new_pw_confirm: 'new-pass1' };
    it('validates before calling PHP', async () => {
      expect((await post('/v1/account/password', { ...ok, current_pw: '' }).expect(400)).body.field).toBe('current_pw');
      expect((await post('/v1/account/password', { ...ok, new_pw_confirm: 'x' }).expect(400)).body.field).toBe('new_pw_confirm');
      expect((await post('/v1/account/password', { current_pw: 'a', new_pw: '12345', new_pw_confirm: '12345' }).expect(400)).body.field).toBe('new_pw');
      expect((await post('/v1/account/password', { current_pw: 'a', new_pw: 'x'.repeat(129), new_pw_confirm: 'x'.repeat(129) }).expect(400)).body.field).toBe('new_pw');
      expect(php.length).toBe(0);
    });
    it('forwards the cookie to account_security.php and forgets the cached session', async () => {
      reply = () => json({ success: true, message: '비밀번호가 변경되었습니다.' });
      await post('/v1/account/password', ok).expect(201);
      const call = php[0];
      expect(call.url).toBe('/api/account_security.php');
      expect(call.method).toBe('POST');
      expect(call.headers.cookie).toBe(H.Cookie);
      expect(call.headers['x-requested-with']).toBe('XMLHttpRequest');
      expect(call.headers['x-forwarded-proto']).toBe('https'); // gangnamunni/.htaccess would 301 a plain-HTTP (loopback) request
      expect(JSON.parse(call.body!)).toEqual({ action: 'password', ...ok });
      const before = sessionCalls;
      await post('/v1/account/password', ok); // still logged in as far as the mock goes; the cached entry was dropped, so PHP is asked again
      expect(sessionCalls).toBeGreaterThan(before);
    });
    it('a wrong current password comes back as a field error', async () => {
      reply = () => json({ success: false, field: 'current_pw', message: '현재 비밀번호가 올바르지 않습니다.' }, 422);
      const r = await post('/v1/account/password', ok).expect(422);
      expect(r.body).toMatchObject({ success: false, field: 'current_pw', message: '현재 비밀번호가 올바르지 않습니다.' });
    });
    it('PHP unreachable -> 502, PHP 500 -> 502', async () => {
      reply = () => 'throw';
      expect((await post('/v1/account/password', ok).expect(502)).body.success).toBe(false);
      reply = () => json({ success: false, message: 'db' }, 500);
      await post('/v1/account/password', ok).expect(502);
    });
    it('a reply that is not JSON is a 502, not a success', async () => {
      reply = () => ({ body: '<html>login</html>' });
      await post('/v1/account/password', ok).expect(502);
    });
  });

  describe('e-mail', () => {
    it('send: validates, then relays PHP', async () => {
      expect((await post('/v1/account/email/send', { new_email: 'nope' }).expect(400)).body.field).toBe('new_email');
      expect(php.length).toBe(0);
      reply = () => json({ success: true, email: 'new@b.co', expiresIn: 180 });
      const r = await post('/v1/account/email/send', { new_email: ' new@b.co ' }).expect(201);
      expect(r.body).toEqual({ success: true, email: 'new@b.co', expiresIn: 180 });
      expect(JSON.parse(php[0].body!)).toEqual({ action: 'email_send', new_email: 'new@b.co' });
    });
    it('send: a taken address is a 409 field error', async () => {
      reply = () => json({ success: false, field: 'new_email', message: '이미 사용 중인 이메일입니다.' }, 409);
      expect((await post('/v1/account/email/send', { new_email: 'a@b.co' }).expect(409)).body).toMatchObject({ field: 'new_email' });
    });
    it('verify: six digits only; PHP errors pass through', async () => {
      expect((await post('/v1/account/email/verify', { code: '12ab' }).expect(400)).body.field).toBe('code');
      expect(php.length).toBe(0);
      reply = () => json({ success: false, field: 'code', message: '인증 코드가 올바르지 않습니다.' }, 422);
      expect((await post('/v1/account/email/verify', { code: '123456' }).expect(422)).body).toMatchObject({ field: 'code', message: '인증 코드가 올바르지 않습니다.' });
    });
    it('verify: success returns the new address', async () => {
      reply = () => json({ success: true, email: 'new@b.co' });
      const r = await post('/v1/account/email/verify', { code: '123456' }).expect(201);
      expect(r.body).toEqual({ success: true, email: 'new@b.co' });
    });
  });

  describe('withdraw', () => {
    const route = (csrf = 'tok123') => (url: URL) => {
      if (url.pathname === '/api/account_security.php') return json({ success: true, token: csrf });
      if (url.pathname === '/notification_settings.php') return json({ ok: true, message: '회원 탈퇴가 완료되었습니다.' });
      if (url.pathname === '/api/withdraw.php') return json({ success: true, message: '탈퇴 완료' });
      return json({}, 404);
    };
    it('password member: needs the password and a known reason, nothing is called otherwise', async () => {
      expect((await post('/v1/account/withdraw', { reason: '기타' }).expect(400)).body.field).toBe('password');
      expect((await post('/v1/account/withdraw', { reason: 'hacked', password: 'p' }).expect(400)).body.field).toBe('reason');
      expect(php.length).toBe(0);
    });
    it('password member -> api/withdraw.php with the session CSRF token and the joined reason', async () => {
      reply = route();
      const r = await post('/v1/account/withdraw', { reason: '기타', detail: '불편', password: 'pw1234' }).expect(201);
      expect(r.body).toEqual({ success: true, message: '탈퇴 완료' });
      expect(php.map((c) => c.url)).toEqual(['/api/account_security.php?action=csrf', '/api/withdraw.php']);
      const w = php[1];
      expect(w.headers['x-csrf-token']).toBe('tok123');
      expect(w.headers.cookie).toBe(H.Cookie);
      expect(JSON.parse(w.body!)).toEqual({ reason: '기타 - 불편', password: 'pw1234' });
    });
    it('wrong password -> field error on password', async () => {
      reply = (url) => (url.pathname === '/api/withdraw.php' ? json({ success: false, message: '비밀번호가 일치하지 않습니다.' }, 400) : route()(url));
      const r = await post('/v1/account/withdraw', { reason: '기타', password: 'bad' }).expect(422);
      expect(r.body).toMatchObject({ field: 'password', message: '비밀번호가 일치하지 않습니다.' });
    });
    it('social member -> notification_settings.php withdraw_social, no password', async () => {
      social = true;
      reply = route();
      const r = await post('/v1/account/withdraw', { reason: '기타' }).expect(201);
      expect(r.body.success).toBe(true);
      expect(php.map((c) => c.url)).toEqual(['/api/account_security.php?action=csrf', '/notification_settings.php']);
      const w = php[1];
      expect(w.headers['content-type']).toBe('application/x-www-form-urlencoded');
      expect(Object.fromEntries(new URLSearchParams(w.body))).toEqual({ action: 'withdraw_social', csrf_token: 'tok123' });
    });
    it('Kakao unlink failure (502 from PHP) is not reported as success', async () => {
      social = true;
      reply = (url) => (url.pathname === '/notification_settings.php' ? json({ ok: false, message: '카카오 계정 연결 해제에 실패했습니다. 다시 로그인한 후 재시도해주세요.' }, 502) : route()(url));
      const r = await post('/v1/account/withdraw', { reason: '기타' }).expect(502);
      expect(r.body).toMatchObject({ success: false, message: expect.stringContaining('카카오') });
    });
    it('a stray <script> after the JSON does not break the success', async () => {
      reply = (url) => (url.pathname === '/api/withdraw.php' ? { body: '{"success":true,"message":"탈퇴 완료"}<script>x()</script>' } : route()(url));
      await post('/v1/account/withdraw', { reason: '기타', password: 'p' }).expect(201);
    });
    it('no CSRF token from PHP -> nothing is deleted', async () => {
      reply = () => json({ success: false, message: '로그인이 필요합니다.' }, 401);
      await post('/v1/account/withdraw', { reason: '기타', password: 'p' }).expect(401);
      expect(php.map((c) => c.url)).toEqual(['/api/account_security.php?action=csrf']);
    });
  });

  it('password attempts are limited per member', async () => {
    reply = () => json({ success: false, field: 'current_pw', message: 'x' }, 422);
    const ok = { current_pw: 'a', new_pw: 'bbbbbb', new_pw_confirm: 'bbbbbb' };
    let last = 0;
    for (let i = 0; i < 12; i++) last = (await post('/v1/account/password', ok)).status;
    expect(last).toBe(429);
  });
});

/* ACCOUNT_NEST_ACTIONS: the same three actions carried out by Nest itself; PHP is only asked who is signed in. */
import { MailService } from '../src/mail/mail.service';
import { createHash as gnuHash } from '../src/password-lost/password-lost.logic';
import { nestActions } from '../src/account/account-security.logic';
import { AccountSecurityNativeService } from '../src/account/account-security-native.service';
import { passwordMatches } from '../src/login/login.logic';

describe('account security in Nest (ACCOUNT_NEST_ACTIONS)', () => {
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
    process.env.ACCOUNT_NEST_ACTIONS = 'password,email,withdraw';
    process.env.KAKAO_ADMIN_KEY = 'admin-key';
    global.fetch = jest.fn(async (u: any, init: any = {}) => {
      const url = new URL(String(u));
      if (url.pathname === '/api/session_me.php') return new Response(JSON.stringify({ loggedIn: true, user: { id: 'kim', no: 42, name: 'x', nickname: 'n', level: 2, phone: '' } }), { status: 200 });
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
  afterAll(async () => { global.fetch = realFetch; delete process.env.ACCOUNT_NEST_ACTIONS; delete process.env.KAKAO_ADMIN_KEY; await app.close(); });
  beforeEach(() => {
    sql = []; phpCalls = []; kakaoCalls = []; mails = []; mailOk = true; emailOwners = []; deleteMemberRows = 1; profile = null;
    kakao = { status: 200, body: { id: 12345 } };
    member = { mb_no: 42, mb_id: 'kim', mb_password: gnuHash('old-pass-1'), mb_nick: 'n', mb_email: 'kim@a.co' };
    (app.get(AccountSecurityService) as any).attempts.clear();
    (app.get(AccountSecurityNativeService) as any).codes.clear();
  });

  it('the switch lists known actions only', () => {
    expect([...nestActions('password, EMAIL ,x')]).toEqual(['password', 'email']);
    expect(nestActions('').size).toBe(0);
    expect(nestActions(undefined as any).size).toBeGreaterThanOrEqual(0);
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
