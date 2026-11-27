import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { FcmClient } from '../bookings/notify/fcm.client';
import { NotificationsModule } from './notifications.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const REQ = 'req_0123456789abcdef';
const sqlError = (code: string) => Object.assign(new Error(code), { code });

describe('admin API: notifications (sending)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const roSqls: { sql: string; values: unknown[] }[] = [];
  const rwSqls: { sql: string; values: unknown[] }[] = [];
  const members = ['kim01', 'lee02', 'park03'];
  let tokenRows: Record<string, unknown>[] = [];
  let fcmEnabledRow: Record<string, unknown>[] = [];
  let dupOnClaim: Record<string, unknown> | null = null;
  let rwReady = true;
  let failItems = false;
  const sent: any[] = [];
  let respond: (msg: any) => any = () => ({ ok: true });
  let session: any = { project: 'p', access: 'a' };

  const ro = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      roSqls.push({ sql, values });
      if (sql.includes('SHOW COLUMNS FROM gangnamunni_event_entries')) return [[{ Field: 'member_id' }]];
      if (sql.includes('FROM g5_member m')) return [members.map((mb_id) => ({ mb_id }))];
      if (sql.includes('FROM gangnamunni_notification_settings')) {
        return [[
          { member_id: 'kim01', noti_booking: 1, noti_community: 1, noti_review: 1, mkt_push: 1, mkt_night_push: 0 },
          { member_id: 'lee02', noti_booking: 0, noti_community: 1, noti_review: 1, mkt_push: 1, mkt_night_push: 1 },
        ]];
      }
      if (sql.includes("setting_key = 'fcm_enabled'")) return [fcmEnabledRow];
      if (sql.includes('SELECT pt_token, mb_id, pt_platform, pt_device_info')) return [tokenRows.filter((t) => values.includes(t.mb_id))];
      throw new Error(`unexpected read: ${sql}`);
    }),
  };
  const rwQuery = jest.fn(async (sql: string, values: unknown[] = []) => {
    rwSqls.push({ sql, values });
    if (sql.startsWith('INSERT INTO gangnamunni_notification_sends')) {
      if (dupOnClaim) throw sqlError('ER_DUP_ENTRY');
      return [{ insertId: 77 }];
    }
    if (sql.includes('FROM gangnamunni_notification_sends WHERE request_id')) return [dupOnClaim ? [dupOnClaim] : []];
    if (sql.startsWith('INSERT INTO gangnamunni_notification_send_items') && failItems) throw sqlError('ER_NO_SUCH_TABLE');
    return [{ affectedRows: 1 }];
  });
  const fakeFcm = {
    session: jest.fn(async () => session),
    sendMessage: jest.fn(async (_s: unknown, msg: any) => { sent.push(msg.message); return respond(msg.message); }),
  };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_NOTIFICATIONS_ENABLED = '1';
    process.env.ADMIN_NOTIFICATIONS_SEND = '1';
    process.env.NOTIFICATIONS_FCM_CONCURRENCY = '1'; // one at a time: the order of the calls is the order of the tokens
    roSqls.length = 0; rwSqls.length = 0; sent.length = 0;
    tokenRows = []; fcmEnabledRow = []; dupOnClaim = null; rwReady = true; failItems = false; respond = () => ({ ok: true }); session = { project: 'p', access: 'a' };
    fakeFcm.sendMessage.mockClear(); fakeFcm.session.mockClear();
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }, { provide: DB_RW, useValue: { get: () => (rwReady ? { query: rwQuery } : null) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, NotificationsModule] }).overrideProvider(FcmClient).useValue(fakeFcm).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_NOTIFICATIONS_ENABLED', 'ADMIN_NOTIFICATIONS_SEND', 'NOTIFICATIONS_FCM_CONCURRENCY']) delete process.env[k];
  });

  const post = (body: unknown, cookie?: string, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post('/v1/notifications/send').set(headers).send(body as object);
    if (cookie) r = r.set('Cookie', cookie);
    return r;
  };
  const BOOKING = { target: 'selected', memberIds: members, type: 'booking', title: '예약 안내', message: '내용입니다', link: '/next/mypage/bookings', requestId: REQ, expectedResolved: 3 };
  const tok = (token: string, mb_id: string, pt_platform: string, pt_device_info = '') => ({ pt_token: token, mb_id, pt_platform, pt_device_info });
  const rw = (frag: string) => rwSqls.filter((c) => c.sql.includes(frag));
  const itemRows = () => { const c = rw('INTO gangnamunni_notification_send_items')[0]; const v = (c?.values ?? []) as unknown[]; const out: unknown[][] = []; for (let i = 0; i < v.length; i += 6) out.push(v.slice(i, i + 6)); return out; };

  it('answers 404 while the send switch is off (the read side stays on), 403 csrf, 401 and 403 — before any SQL', async () => {
    process.env.ADMIN_NOTIFICATIONS_SEND = '0';
    expect((await post(BOOKING, SUPER)).status).toBe(404);
    process.env.ADMIN_NOTIFICATIONS_SEND = '1';
    expect(await post(BOOKING, undefined, { 'Content-Type': 'application/json' }).then((r) => [r.status, r.body])).toEqual([403, { error: 'csrf' }]);
    expect((await post(BOOKING)).status).toBe(401);
    expect((await post(BOOKING, HOSPITAL)).status).toBe(403);
    expect(roSqls).toHaveLength(0);
    expect(rwSqls).toHaveLength(0);
    expect(fakeFcm.sendMessage).not.toHaveBeenCalled();
  });

  it('answers 400 with a code and writes nothing for a bad body', async () => {
    for (const [patch, code] of [[{ link: 'https://evil.example' }, 'bad_link'], [{ requestId: 'x' }, 'bad_request_id'], [{ title: ' ' }, 'title_required'], [{ expectedResolved: undefined }, 'expected_required'], [{ test: true }, 'test_needs_individual']] as const) {
      const r = await post({ ...BOOKING, ...patch }, SUPER);
      expect([r.status, r.body]).toEqual([400, { error: code }]);
    }
    expect(rwSqls).toHaveLength(0);
  });

  it('refuses with 409 when the audience is not the one that was previewed, and writes nothing', async () => {
    const r = await post({ ...BOOKING, expectedResolved: 2 }, SUPER);
    expect([r.status, r.body]).toEqual([409, { error: 'audience_changed', resolved: 3 }]);
    expect(rwSqls).toHaveLength(0);
    expect(fakeFcm.sendMessage).not.toHaveBeenCalled();
  });

  it('sends a booking notice: in-app rows and pushes follow the 설정, a dead token is switched off, the history row is completed', async () => {
    tokenRows = [tok('tk-ios', 'kim01', 'ios'), tok('tk-and', 'kim01', 'android'), tok('tk-dead', 'park03', 'android'), tok('tk-lee', 'lee02', 'ios')];
    respond = (m) => (m.token === 'tk-dead' ? { ok: false, dead: true, status: 404, code: 'UNREGISTERED' } : { ok: true });
    const r = await post(BOOKING, SUPER);
    expect(r.status).toBe(200);
    // lee02 turned 예약·결제 알림 off: neither channel
    expect(r.body).toEqual({ sendId: 77, duplicate: false, test: false, resolved: 3, inApp: 2, inAppFailed: 0, pushSent: 2, pushFailed: 1, pushSkipped: 1, nightSkipped: 0, deadTokens: 1, pushError: '' });
    const inApp = rw('INTO gangnamunni_user_notifications')[0];
    expect(inApp.values).toEqual(['kim01', 'booking', '예약 안내', '내용입니다', 'https://seoulbeauty.app/next/mypage/bookings', 'bi-calendar-check', 'park03', 'booking', '예약 안내', '내용입니다', 'https://seoulbeauty.app/next/mypage/bookings', 'bi-calendar-check']);
    expect(sent.map((m) => m.token)).toEqual(['tk-ios', 'tk-and', 'tk-dead']); // lee02's token is never asked for
    expect(sent[0]).toMatchObject({ notification: { title: '예약 안내', body: '내용입니다' }, data: { type: 'member_notification', url: 'https://seoulbeauty.app/next/mypage/bookings', source: 'gangnamunni_admin' } });
    expect(rw('UPDATE g5_push_tokens SET pt_use = 0')[0].values).toEqual(['tk-dead']);
    const claim = rw('INSERT INTO gangnamunni_notification_sends')[0];
    expect(claim.values).toEqual([7, 'boss', '선택 회원 3명', 'booking', '예약 안내', '내용입니다', 'https://seoulbeauty.app/next/mypage/bookings', 3, 'sending', REQ, 0]);
    const done = rw('UPDATE gangnamunni_notification_sends SET target_count')[0];
    expect(done.values.slice(0, 6)).toEqual([2, 2, 2, 1, 1, '']);
    expect(JSON.parse(done.values[6] as string)).toMatchObject({ target: 'selected', type: 'booking', resolved_users: 3, push_enabled_users: 2, direct: { sent: 2, failed: 1, dead_tokens: 1 }, android_topic: null });
    const rows = itemRows();
    expect(rows).toEqual(expect.arrayContaining([
      [77, 'kim01', 'in_app', '', 'sent', ''], [77, 'lee02', 'in_app', '', 'skipped', 'setting'], [77, 'lee02', 'push', '', 'skipped', 'setting'],
      [77, 'kim01', 'push', 'android', 'sent', ''], [77, 'park03', 'push', 'android', 'failed', 'UNREGISTERED'],
    ]));
  });

  it('writes only the in-app row for a member without a device token and says why', async () => {
    tokenRows = [tok('tk-ios', 'kim01', 'ios')];
    const r = await post(BOOKING, SUPER);
    expect(r.body).toMatchObject({ inApp: 2, pushSent: 1, pushFailed: 0 });
    expect(itemRows()).toEqual(expect.arrayContaining([[77, 'park03', 'push', '', 'skipped', 'no_token']]));
  });

  it('sends a general notice to everybody as an Android topic plus the iOS and web tokens (no Android token twice)', async () => {
    tokenRows = [tok('tk-ios', 'kim01', 'ios'), tok('tk-and', 'lee02', 'android'), tok('tk-iphone-as-android', 'park03', 'android', '{"model":"iPhone 15"}')];
    const r = await post({ target: 'all', type: 'general', title: '공지', requestId: REQ, expectedResolved: 3 }, SUPER);
    expect(r.body).toMatchObject({ resolved: 3, inApp: 3, pushSent: 3, pushFailed: 0 });
    expect(sent.map((m) => m.token ?? `topic:${m.topic}`)).toEqual(['tk-ios', 'tk-iphone-as-android', 'topic:seoulbeauty_android_all']);
    expect(rw('INTO gangnamunni_user_notifications')[0].values).toContain('bi-bell');
    expect(itemRows()).toEqual(expect.arrayContaining([[77, 'lee02', 'push', '', 'skipped', 'topic']]));
  });

  it('never uses the Android topic for a type a 설정 switch decides, nor for a promotion', async () => {
    tokenRows = [tok('tk-and', 'kim01', 'android')];
    await post({ target: 'all', type: 'promotion', title: '광고', requestId: REQ, expectedResolved: 3 }, SUPER);
    expect(sent.every((m) => m.token)).toBe(true);
    expect(sent.map((m) => m.token)).toEqual(['tk-and']);
  });

  it('retries a server error and counts the push as sent', async () => {
    tokenRows = [tok('tk-ios', 'kim01', 'ios')];
    let n = 0;
    respond = () => (++n < 2 ? { ok: false, dead: false, status: 503, code: '' } : { ok: true });
    const r = await post({ ...BOOKING, memberIds: ['kim01'] }, SUPER);
    expect(r.body).toMatchObject({ pushSent: 1, pushFailed: 0 });
    expect(fakeFcm.sendMessage).toHaveBeenCalledTimes(2);
  });

  it('stops the iOS sends after the APNs key error and says so', async () => {
    tokenRows = [tok('i1', 'kim01', 'ios'), tok('i2', 'park03', 'ios'), tok('a1', 'kim01', 'android')];
    respond = (m) => (m.token === 'i1' ? { ok: false, dead: false, status: 401, code: 'THIRD_PARTY_AUTH_ERROR' } : { ok: true });
    const r = await post({ ...BOOKING, memberIds: ['kim01', 'park03'] }, SUPER);
    expect(r.body).toMatchObject({ pushSent: 1, pushFailed: 1 });
    expect(r.body.pushError).toContain('APNs');
    expect(sent.map((m) => m.token)).toEqual(['i1', 'a1']); // i2 was not tried
    expect(itemRows()).toEqual(expect.arrayContaining([[77, 'park03', 'push', 'ios', 'skipped', 'apns_auth']]));
  });

  it('skips every push, with a reason, when the PHP admin switched FCM off', async () => {
    fcmEnabledRow = [{ setting_value: '0' }];
    tokenRows = [tok('tk-ios', 'kim01', 'ios')];
    const r = await post(BOOKING, SUPER);
    expect(r.body).toMatchObject({ inApp: 2, pushSent: 0, pushFailed: 0, pushError: 'FCM disabled' });
    expect(fakeFcm.sendMessage).not.toHaveBeenCalled();
    expect(itemRows()).toEqual(expect.arrayContaining([[77, 'kim01', 'push', '', 'skipped', 'fcm_disabled']]));
  });

  it('reports failed pushes (not a crash) when the Firebase credentials cannot be loaded', async () => {
    session = null;
    tokenRows = [tok('tk-ios', 'kim01', 'ios')];
    const r = await post(BOOKING, SUPER);
    expect(r.status).toBe(200);
    expect(r.body).toMatchObject({ inApp: 2, pushSent: 0, pushFailed: 1, pushError: 'Firebase credentials unavailable' });
  });

  it('sends nothing twice: a repeated request id returns the first result, a running one answers 409', async () => {
    dupOnClaim = { id: 41, resolved_count: 3, in_app_count: 2, push_sent: 2, push_failed: 0, push_skipped: 1, push_error: '', is_test: 0 };
    const again = await post(BOOKING, SUPER);
    expect([again.status, again.body]).toMatchObject([200, { sendId: 41, duplicate: true, inApp: 2, pushSent: 2 }]);
    expect(fakeFcm.sendMessage).not.toHaveBeenCalled();
    expect(rw('INTO gangnamunni_user_notifications')).toHaveLength(0);
    dupOnClaim = { ...dupOnClaim, push_error: 'sending' };
    const running = await post(BOOKING, SUPER);
    expect([running.status, running.body]).toEqual([409, { error: 'in_progress' }]);
  });

  it('allows one send at a time', async () => {
    tokenRows = [tok('tk-ios', 'kim01', 'ios')];
    let release: () => void = () => undefined;
    const gate = new Promise<void>((r) => { release = r; });
    fakeFcm.sendMessage.mockImplementationOnce(async (_s: unknown, msg: any) => { sent.push(msg.message); await gate; return { ok: true }; });
    const first = post({ ...BOOKING, memberIds: ['kim01'] }, SUPER).then((r) => r);
    for (let i = 0; i < 50 && fakeFcm.sendMessage.mock.calls.length === 0; i++) await new Promise((r) => setTimeout(r, 10));
    const second = await post({ ...BOOKING, requestId: 'req_other_0123456789', memberIds: ['kim01'] }, SUPER);
    expect([second.status, second.body]).toEqual([409, { error: 'busy' }]);
    release();
    expect((await first).status).toBe(200);
    expect((await post({ ...BOOKING, requestId: 'req_third_0123456789', memberIds: ['kim01'] }, SUPER)).status).toBe(200);
  });

  it('answers 503 when the write user is missing or the history row cannot be written yet', async () => {
    rwReady = false;
    expect((await post(BOOKING, SUPER)).body).toEqual({ error: 'write_unavailable' });
    rwReady = true;
    rwQuery.mockImplementationOnce(async () => { throw sqlError('ER_BAD_FIELD_ERROR'); });
    const r = await post(BOOKING, SUPER);
    expect([r.status, r.body]).toEqual([503, { error: 'not_ready' }]);
    expect(fakeFcm.sendMessage).not.toHaveBeenCalled();
  });

  it('keeps the totals right when the per-recipient rows cannot be written', async () => {
    failItems = true;
    tokenRows = [tok('tk-ios', 'kim01', 'ios')];
    const r = await post(BOOKING, SUPER);
    expect(r.status).toBe(200);
    expect(r.body).toMatchObject({ inApp: 2, pushSent: 1 });
  });

  it('sends a test to one member only and marks the history row', async () => {
    tokenRows = [tok('tk-ios', 'kim01', 'ios')];
    const r = await post({ target: 'individual', memberId: 'kim01', type: 'general', title: '테스트', requestId: REQ, test: true }, SUPER);
    expect(r.status).toBe(200);
    expect(r.body).toMatchObject({ test: true, resolved: 3 }); // the stub returns all three members for any recipient query
    expect(rw('INSERT INTO gangnamunni_notification_sends')[0].values.slice(-1)).toEqual([1]);
    expect((await post({ ...BOOKING, test: true }, SUPER)).body).toEqual({ error: 'test_needs_individual' });
  });

  it('never puts a typed value into the SQL text', async () => {
    tokenRows = [tok('tk-ios', 'kim01', 'ios')];
    await post({ ...BOOKING, title: "x'); DROP TABLE a;--", message: "' OR '1'='1", memberIds: ['kim01'] }, SUPER);
    for (const c of [...roSqls, ...rwSqls]) { expect(c.sql).not.toContain('DROP TABLE'); expect(c.sql).not.toContain("OR '1'='1"); }
  });
});
