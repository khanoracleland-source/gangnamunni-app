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
