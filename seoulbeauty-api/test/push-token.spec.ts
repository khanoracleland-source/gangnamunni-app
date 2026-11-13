import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { PushTokenModule } from '../src/push-token/push-token.module';
import { cleanDeviceId, cleanPlatform, cleanToken, deviceLike, normalizePushToken, tokenTail } from '../src/push-token/push-token.logic';
import { deviceId, duplicateIds, fixedPlatform } from '../src/maintenance/push-token-cleanup.logic';

const TOKEN = `fcm_${'A'.repeat(140)}:APA91b-_xyz123`;
const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) SeoulBeautyApp';
const PIXEL = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) SeoulBeautyApp';

describe('push token logic (PHP push_token_registry.php rules)', () => {
  it('token: trimmed, 20–500 bytes, printable ASCII', () => {
    expect(cleanToken(`  ${TOKEN}\n`)).toBe(TOKEN);
    expect(cleanToken('a'.repeat(20))).toBe('a'.repeat(20));
    expect(cleanToken('a'.repeat(500))).toHaveLength(500);
    for (const bad of ['', 'a'.repeat(19), 'a'.repeat(501), `${'a'.repeat(30)} b`, `${'a'.repeat(30)}\u0000`, '토'.repeat(30), 12345678901234567890123, null, undefined, ['a'.repeat(30)], { token: TOKEN }]) expect(cleanToken(bad)).toBe('');
  });
  it('platform: as sent, otherwise from the user agent (android by default)', () => {
    expect(cleanPlatform('ios', PIXEL)).toBe('ios');
    expect(cleanPlatform(' Android ', IPHONE)).toBe('android');
    expect(cleanPlatform(undefined, IPHONE)).toBe('ios');
    expect(cleanPlatform('web', PIXEL)).toBe('android');
    expect(cleanPlatform({}, '')).toBe('android');
  });
  it('device id: id-shaped values only; LIKE wildcards escaped', () => {
    expect(cleanDeviceId(' 3f2b8c1e-aaaa-4bbb-8ccc-1234567890ab ')).toBe('3f2b8c1e-aaaa-4bbb-8ccc-1234567890ab');
    for (const bad of ['', 'a"b', "a'b", 'a%b', 'a b', 'x'.repeat(101), null, {}]) expect(cleanDeviceId(bad)).toBe('');
    expect(deviceLike('a_b.c')).toBe('%"device_id":"a\\_b.c"%');
  });
  it('pt_device_info is the JSON PHP writes (key order, limits) and the cleanup job reads', () => {
    const n = normalizePushToken({ token: TOKEN, platform: 'android', device_id: 'dev-1', app_version: '2.2.0' }, PIXEL)!;
    expect(n).toEqual({ token: TOKEN, platform: 'android', deviceId: 'dev-1', deviceInfo: `{"device_id":"dev-1","app_version":"2.2.0","platform":"android","user_agent":"${PIXEL}"}` });
    expect(deviceId(n.deviceInfo)).toBe('dev-1');
    // the contract's body (no device id): the PHP "no device id" marker is in the row, so the member's older tokens are found by it
    const c = normalizePushToken({ token: TOKEN }, IPHONE)!;
    expect(c.platform).toBe('ios');
    expect(c.deviceInfo).toContain('"device_id":""');
    expect(fixedPlatform(c.platform, c.deviceInfo)).toBe('ios'); // nothing left for the cleanup's iPhone-as-android step
    expect(duplicateIds([{ pt_id: 2, mb_id: 'kim', pt_platform: 'ios', pt_device_info: c.deviceInfo }, { pt_id: 1, mb_id: 'kim', pt_platform: 'ios', pt_device_info: c.deviceInfo }])).toEqual([]);
    // device_info: an object or its JSON string (PHP's array); a plain string counts as empty
    expect(JSON.parse(normalizePushToken({ token: TOKEN, device_info: { app_version: '9', user_agent: 'UA' } }, PIXEL)!.deviceInfo)).toMatchObject({ app_version: '9', user_agent: 'UA' });
    expect(JSON.parse(normalizePushToken({ token: TOKEN, device_info: '{"app_version":"8"}' }, PIXEL)!.deviceInfo)).toMatchObject({ app_version: '8', user_agent: PIXEL });
    expect(JSON.parse(normalizePushToken({ token: TOKEN, device_info: 'Pixel 9' }, PIXEL)!.deviceInfo)).toMatchObject({ app_version: '', user_agent: PIXEL });
    expect(JSON.parse(normalizePushToken({ token: TOKEN, app_version: 'v'.repeat(200) }, 'u'.repeat(900))!.deviceInfo)).toMatchObject({ app_version: 'v'.repeat(100), user_agent: 'u'.repeat(500) });
    for (const bad of [null, undefined, 'x', [], {}, { token: 'short' }, { fcm_token: TOKEN }]) expect(normalizePushToken(bad, '')).toBeNull();
    expect(tokenTail(TOKEN)).toBe('…xyz123');
  });
});

describe('push token API (APP_PUSH_TOKEN_NEST)', () => {
  let app: INestApplication;
  interface Row { mb_id: string; pt_token: string; pt_platform: string; pt_device_info: string; pt_use: number; touched: boolean }
  let rows: Row[];
  let calls: { sql: string; args: any[] }[];
  let failOn: RegExp | null;
  const logged: string[] = [];
  const realFetch = global.fetch;
  const likeHit = (info: string, pattern: string) => info.includes(pattern.replace(/^%|%$/g, '').replace(/\\(.)/g, '$1'));
  const query = async (sql: string, args: any[] = []) => {
    calls.push({ sql, args });
    if (failOn && failOn.test(sql)) { const e: any = new Error(`Duplicate entry '${TOKEN}'`); e.code = 'ER_TEST'; throw e; }
    if (/^UPDATE g5_push_tokens SET pt_use = 0 WHERE pt_token <> \? AND pt_device_info LIKE \?/.test(sql)) {
      const hit = rows.filter((r) => r.pt_token !== args[0] && likeHit(r.pt_device_info, args[1]));
      hit.forEach((r) => { r.pt_use = 0; });
      return [{ affectedRows: hit.length }];
    }
    if (/^UPDATE g5_push_tokens SET pt_use = 0\s+WHERE pt_token <> \? AND mb_id = \? AND pt_platform = \?/.test(sql)) {
      const hit = rows.filter((r) => r.pt_token !== args[0] && r.mb_id === args[1] && r.pt_platform === args[2] && (r.pt_device_info === '' || r.pt_device_info.includes('"device_id":""')));
      hit.forEach((r) => { r.pt_use = 0; });
      return [{ affectedRows: hit.length }];
    }
    if (/^INSERT INTO g5_push_tokens \(mb_id, pt_token, pt_platform, pt_device_info, pt_datetime, pt_use\)\s+VALUES \(\?, \?, \?, \?, NOW\(\), 1\)\s+ON DUPLICATE KEY UPDATE mb_id = VALUES\(mb_id\), pt_platform = VALUES\(pt_platform\), pt_device_info = VALUES\(pt_device_info\), pt_datetime = NOW\(\), pt_use = 1$/.test(sql)) {
      const [mb_id, pt_token, pt_platform, pt_device_info] = args;
      const old = rows.find((r) => r.pt_token === pt_token);
      if (old) Object.assign(old, { mb_id, pt_platform, pt_device_info, pt_use: 1, touched: true });
      else rows.push({ mb_id, pt_token, pt_platform, pt_device_info, pt_use: 1, touched: true });
      return [{ affectedRows: old ? 2 : 1 }];
    }
    if (/^UPDATE g5_push_tokens SET mb_id = '', pt_datetime = NOW\(\) WHERE pt_token = \? AND mb_id = \?$/.test(sql)) {
      const hit = rows.filter((r) => r.pt_token === args[0] && r.mb_id === args[1]);
      hit.forEach((r) => { r.mb_id = ''; r.touched = true; });
      return [{ affectedRows: hit.length }];
    }
    throw new Error(`unexpected SQL: ${sql}`);
  };
  const row = (mb_id: string, pt_token: string, pt_platform = 'android', device = ''): Row => ({ mb_id, pt_token, pt_platform, pt_device_info: JSON.stringify({ device_id: device, app_version: '', platform: pt_platform, user_agent: '' }), pt_use: 1, touched: false });

  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const id = /sid=(\w+)/.exec(c)?.[1];
      return new Response(JSON.stringify(id ? { loggedIn: true, user: { id, no: 42, name: 'x', nickname: 'n', level: 2, phone: '' } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    for (const m of ['log', 'warn', 'error'] as const) jest.spyOn(Logger.prototype, m).mockImplementation((...a: any[]) => { logged.push(String(a[0])); });
    @Global() @Module({ providers: [{ provide: DB, useValue: { query } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, PushTokenModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.APP_PUSH_TOKEN_NEST; jest.restoreAllMocks(); await app.close(); });
  beforeEach(() => { process.env.APP_PUSH_TOKEN_NEST = '1'; rows = []; calls = []; failOn = null; logged.length = 0; });

  const X = { 'X-Requested-With': 'XMLHttpRequest' };
  const post = (path: string, body: unknown, who: string | null = 'kim', ua = PIXEL) => {
    const r = request(app.getHttpServer()).post(`/v1/app/push-token${path}`).set(X).set('User-Agent', ua);
    return (who ? r.set('Cookie', `sid=${who}`) : r).send(body as any);
  };

  it('switch off: 404 for everything, before any other check, and nothing is read or written', async () => {
    for (const v of ['', '0', 'true']) {
      process.env.APP_PUSH_TOKEN_NEST = v;
      await post('', { token: TOKEN }).expect(404);
      await post('/unlink', { token: TOKEN }).expect(404);
      await request(app.getHttpServer()).post('/v1/app/push-token').send({ token: TOKEN }).expect(404);
    }
    expect(calls).toEqual([]);
  });
  it('403 without the same-origin header; 401 login_required for a visitor; 400 invalid_token', async () => {
    await request(app.getHttpServer()).post('/v1/app/push-token').set('Cookie', 'sid=kim').send({ token: TOKEN }).expect(403);
    await request(app.getHttpServer()).post('/v1/app/push-token').set(X).set('Origin', 'https://evil.test').set('Cookie', 'sid=kim').send({ token: TOKEN }).expect(403);
    for (const p of ['', '/unlink']) {
      const r = await post(p, { token: TOKEN }, null).expect(401);
      expect(r.body).toEqual({ success: false, error: 'login_required' });
      for (const bad of [{}, { token: '' }, { token: 'short' }, { token: `${'a'.repeat(30)} b` }, { token: 'a'.repeat(501) }, { token: 123 }]) {
        expect((await post(p, bad).expect(400)).body).toEqual({ success: false, error: 'invalid_token' });
      }
    }
    expect(calls).toEqual([]);
  });
  it('registers for the session member (a member id in the body is ignored)', async () => {
    const r = await post('', { token: TOKEN, platform: 'android', mb_id: 'victim', member_id: 'victim' }).expect(200);
    expect(r.body).toEqual({ success: true, linked: true });
    expect(r.headers['cache-control']).toContain('no-store');
    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({ mb_id: 'kim', pt_token: TOKEN, pt_platform: 'android', pt_use: 1 });
    expect(JSON.parse(rows[0].pt_device_info)).toEqual({ device_id: '', app_version: '', platform: 'android', user_agent: PIXEL });
    // PHP order: (device step skipped without a device id) member step, then the upsert
    expect(calls.map((c) => c.sql.slice(0, 6))).toEqual(['UPDATE', 'INSERT']);
    expect(calls[0].args).toEqual([TOKEN, 'kim', 'android']);
    expect(calls[1].args).toEqual(['kim', TOKEN, 'android', rows[0].pt_device_info]);
  });
  it('no platform in the body: iPhone user agent → ios', async () => {
    await post('', { token: TOKEN }, 'kim', IPHONE).expect(200);
    expect(rows[0].pt_platform).toBe('ios');
  });
  it('a token another member had on this device moves to the member; an inactive row is switched on again', async () => {
    rows = [{ ...row('lee', TOKEN), pt_use: 0 }];
    await post('', { token: TOKEN }).expect(200);
    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({ mb_id: 'kim', pt_use: 1 });
  });
  it('older tokens: same member + platform without a device id go off; other platform, other member and device-bound rows stay', async () => {
    rows = [row('kim', 'old-android-no-device'), row('kim', 'old-ios', 'ios'), row('lee', 'lee-android'), row('kim', 'kim-other-phone', 'android', 'dev-2')];
    await post('', { token: TOKEN, platform: 'android' }).expect(200);
    const use = Object.fromEntries(rows.map((r) => [r.pt_token, r.pt_use]));
    expect(use).toEqual({ 'old-android-no-device': 0, 'old-ios': 1, 'lee-android': 1, 'kim-other-phone': 1, [TOKEN]: 1 });
  });
  it('device_id (optional): the device\'s older tokens go off whoever owned them', async () => {
    rows = [row('lee', 'same-device-old', 'android', 'dev_1'), row('lee', 'similar-device', 'android', 'devX1')];
    await post('', { token: TOKEN, device_id: 'dev_1' }).expect(200);
    expect(rows.find((r) => r.pt_token === 'same-device-old')!.pt_use).toBe(0);
    expect(rows.find((r) => r.pt_token === 'similar-device')!.pt_use).toBe(1); // "_" is not a wildcard
    expect(calls[0].args).toEqual([TOKEN, '%"device_id":"dev\\_1"%']);
    expect(calls).toHaveLength(3);
  });
  it('the two switch-off steps may fail (PHP ignores them); a failing upsert is 503 and the token is not logged', async () => {
    failOn = /SET pt_use = 0/;
    await post('', { token: TOKEN }).expect(200);
    expect(rows).toHaveLength(1);
    failOn = /^INSERT/;
    const r = await post('', { token: TOKEN }).expect(503);
    expect(r.body).toEqual({ success: false, error: 'push_token_unavailable' });
    expect(logged.join('\n')).toContain('…xyz123');
    expect(logged.join('\n')).toContain('ER_TEST');
    expect(logged.join('\n')).not.toContain(TOKEN);
  });
  it('unlink: only the member\'s own token; anything else is still 200', async () => {
    rows = [row('kim', TOKEN), row('lee', 'lee-token-0123456789abcdef')];
    expect((await post('/unlink', { token: TOKEN }).expect(200)).body).toEqual({ success: true });
    expect(rows[0]).toMatchObject({ mb_id: '', pt_use: 1, touched: true }); // PHP's signed-out state; the cleanup's 14 days start now
    expect((await post('/unlink', { token: 'lee-token-0123456789abcdef' }).expect(200)).body).toEqual({ success: true });
    expect(rows[1]).toMatchObject({ mb_id: 'lee', touched: false });
    expect((await post('/unlink', { token: 'unknown-token-0123456789' }).expect(200)).body).toEqual({ success: true });
    await post('/unlink', { token: TOKEN }).expect(200); // twice
    failOn = /^UPDATE/;
    expect((await post('/unlink', { token: TOKEN }).expect(503)).body).toEqual({ success: false, error: 'push_token_unavailable' });
    // sign in again → linked again
    failOn = null;
    await post('', { token: TOKEN }, 'lee').expect(200);
    expect(rows[0]).toMatchObject({ mb_id: 'lee', pt_use: 1 });
  });
});
