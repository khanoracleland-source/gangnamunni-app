import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { NotificationsModule } from './notifications.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const sqlError = (code: string) => Object.assign(new Error(code), { code });

describe('admin API: notifications (read side)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const sqls: { sql: string; values: unknown[] }[] = [];
  let missing: string[] = [];
  const members = ['kim01', 'lee02', 'park03'];
  const stub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      sqls.push({ sql, values });
      for (const frag of missing) if (sql.includes(frag)) throw sqlError('ER_NO_SUCH_TABLE');
      if (sql.includes("SHOW COLUMNS FROM gangnamunni_event_entries")) return [[{ Field: 'member_id' }]];
      if (sql.includes('m.mb_nick')) return [[{ mb_id: 'kim01', mb_name: '김하나', mb_nick: '하나', mb_hp: '010-1234-5678' }, { mb_id: 'lee02', mb_name: '이둘', mb_nick: '', mb_hp: '' }]];
      if (sql.includes('SELECT DISTINCT mb_id FROM g5_push_tokens')) return [[{ mb_id: 'kim01' }]];
      if (sql.includes('FROM g5_member m')) return [members.map((mb_id) => ({ mb_id }))];
      if (sql.includes('FROM gangnamunni_notification_settings')) {
        return [[
          { member_id: 'kim01', noti_booking: 1, noti_community: 1, noti_review: 1, mkt_push: 1, mkt_night_push: 0 },
          { member_id: 'lee02', noti_booking: 0, noti_community: 1, noti_review: 1, mkt_push: 1, mkt_night_push: 1 },
        ]];
      }
      if (sql.includes('COUNT(DISTINCT mb_id) AS members_with_tokens')) return [[{ members_with_tokens: 2, tokens: 3 }]];
      if (sql.includes('FROM gangnamunni_events WHERE is_active = 1 ORDER BY id')) return [[{ id: 4, title: '가을 이벤트' }]];
      if (sql.includes('FROM gangnamunni_events WHERE is_active = 1 AND title LIKE')) return [[{ id: 4, title: '가을 이벤트' }]];
      if (sql.includes('FROM gangnamunni_hospitals')) return [[{ id: 128, name: '라온의원' }]];
      if (sql.includes('COUNT(*) AS cnt FROM gangnamunni_notification_sends')) return [[{ cnt: 41 }]];
      if (sql.includes('FROM gangnamunni_notification_sends WHERE admin_id') && sql.includes('ORDER BY id DESC')) {
        if (sql.includes('request_id')) throw sqlError('ER_BAD_FIELD_ERROR');
        return [[{ id: 41, admin_id: 7, admin_username: 'boss', target_type: '전체 회원', notification_type: 'general', title: '안내', message: '내용', link: '/next/events', resolved_count: 10, in_app_count: 9, push_sent: 8, push_failed: 1, push_skipped: 1, push_error: '', created_at: '2026-11-27 10:00:00' }]];
      }
      if (sql.includes('FROM gangnamunni_notification_sends WHERE id = ?')) {
        if (values[0] !== 41) return [[]];
        if (sql.includes('request_id')) return [[{ id: 41, title: '안내', notification_type: 'general', push_details: '{"direct":{"sent":8}}', is_test: 1, request_id: 'abc' }]];
        return [[]];
      }
      if (sql.includes('FROM gangnamunni_notification_send_items')) {
        if (sql.includes('COUNT(*)')) return [[{ cnt: 1 }]];
        return [[{ member_id: 'kim01', channel: 'push', platform: 'ios', status: 'failed', error_code: 'UNREGISTERED', created_at: '2026-11-27 10:00:01' }]];
      }
      if (sql.includes('SUM(pt_use = 1) AS active_tokens')) return [[{ total_tokens: 10, active_tokens: 8, dead_tokens: 2, active_android: 5, active_ios: 3, active_web: 0, active_without_member: 0, ios_marked_android: 1 }]];
      if (sql.includes('GROUP BY mb_id, pt_platform')) return [[{ mb_id: 'kim01', pt_platform: 'ios', cnt: 2, last_seen: '2026-11-27 09:00:00' }]];
      if (sql.includes('LEFT(pt_token, 6)')) return [[{ mb_id: 'kim01', pt_platform: 'ios', pt_use: 1, token_start: 'abcdef', pt_datetime: '2026-11-27 09:00:00' }]];
      if (sql.includes('FROM g5_push_tokens WHERE pt_use = 1 AND mb_id IS NOT NULL')) return [[]];
      if (sql.includes('visit_reminder_sent_at')) return [[{ today: 3, yesterday: 12, last_sent: '2026-11-27 10:15:00' }]];
      throw new Error(`unexpected sql: ${sql}`);
    }),
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
    sqls.length = 0;
    missing = [];
    @Global() @Module({ providers: [{ provide: DB, useValue: stub }, { provide: DB_RW, useValue: { get: () => null } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, NotificationsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_NOTIFICATIONS_ENABLED', 'ADMIN_NOTIFICATIONS_SEND', 'ADMIN_NOTIFICATIONS_SCHEDULE', 'FCM_SERVICE_ACCOUNT_PATH']) delete process.env[k];
  });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };
  const post = (p: string, body: unknown, cookie?: string, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post(p).set(headers).send(body as object);
    if (cookie) r = r.set('Cookie', cookie);
    return r;
  };
  const READS = ['/v1/notifications/options', '/v1/notifications/history', '/v1/notifications/history/41', '/v1/notifications/link-targets?q=a', '/v1/notifications/member-search?q=ab', '/v1/notifications/diagnostics', '/v1/notifications/automatic'];
  const AUD = { target: 'selected', memberIds: members, type: 'promotion' };

  it('searches members by text: two characters at least, only the last four phone digits, a push flag, values kept out of the SQL', async () => {
    const short = await get('/v1/notifications/member-search?q=a', SUPER);
    expect(short.status).toBe(200);
    expect(short.body).toEqual({ items: [] });
    expect(sqls).toHaveLength(0);
    const r = await get('/v1/notifications/member-search?q=%ED%95%98_%25', SUPER);
    expect(r.status).toBe(200);
    expect(r.body.items).toEqual([
      { id: 'kim01', name: '김하나', nick: '하나', phoneTail: '5678', hasPush: true },
      { id: 'lee02', name: '이둘', nick: '', phoneTail: '', hasPush: false },
    ]);
    const q = sqls.find((x) => x.sql.includes('m.mb_nick'))!;
    expect(q.sql).not.toContain('%');
    expect(q.sql).toContain('mb_leave_date');
    expect(q.values).toContain('%하\\_\\%%');
  });

  it('splits the history: admin sends (admin_id > 0) and automatic ones (admin_id = 0)', async () => {
    const admin = await get('/v1/notifications/history', SUPER);
    expect(admin.status).toBe(200);
    const auto = await get('/v1/notifications/history?kind=auto', SUPER);
    expect(auto.status).toBe(200);
    const sql = sqls.map((x) => x.sql);
    expect(sql.some((x) => x.includes('WHERE admin_id > 0 ORDER BY id DESC'))).toBe(true);
    expect(sql.some((x) => x.includes('WHERE admin_id = 0 ORDER BY id DESC'))).toBe(true);
    expect(sql.some((x) => x.includes('COUNT(*) AS cnt FROM gangnamunni_notification_sends WHERE admin_id = 0'))).toBe(true);
  });

  it('answers 404 while the switch is off, 401 without a session and 403 for a hospital account — before any SQL', async () => {
    process.env.ADMIN_NOTIFICATIONS_ENABLED = '0';
    for (const p of READS) expect((await get(p, SUPER)).status).toBe(404);
    expect((await post('/v1/notifications/preview', AUD, SUPER)).status).toBe(404);
    process.env.ADMIN_NOTIFICATIONS_ENABLED = '1';
    for (const p of READS) { expect((await get(p)).status).toBe(401); expect((await get(p, HOSPITAL)).status).toBe(403); }
    expect((await post('/v1/notifications/preview', AUD, HOSPITAL)).status).toBe(403);
    expect(sqls).toHaveLength(0);
  });

  it('refuses a preview without the CSRF headers (403 csrf) before the role is looked at', async () => {
    const r = await post('/v1/notifications/preview', AUD, undefined, { 'Content-Type': 'application/json' });
    expect(r.status).toBe(403);
    expect(r.body).toEqual({ error: 'csrf' });
    expect((await post('/v1/notifications/preview', AUD, undefined)).status).toBe(401);
  });

  it('previews a promotion by day: in-app for all, push by 마케팅 푸시', async () => {
    const r = await post('/v1/notifications/preview', AUD, SUPER);
    expect(r.status).toBe(200);
    // night depends on the clock; the by-day / night numbers are asserted through the pure split in the logic spec
    expect(r.body).toMatchObject({ resolved: 3, inApp: 3, marketing: true, topic: false, settingsAvailable: true, pushWithTokens: 2, pushTokens: 3 });
    expect(r.body.skippedPush + r.body.nightSkipped + r.body.pushMembers).toBe(3);
    expect(r.headers['cache-control']).toContain('no-store');
  });

  it('previews a booking notice: the type switch removes lee02 from both channels', async () => {
    const r = await post('/v1/notifications/preview', { ...AUD, type: 'booking' }, SUPER);
    expect(r.body).toMatchObject({ resolved: 3, inApp: 2, pushMembers: 2, skippedInApp: 1, skippedPush: 1, nightSkipped: 0, marketing: false });
  });

  it('marks a general notice to everybody as a topic send and keeps every value out of the SQL text', async () => {
    const r = await post('/v1/notifications/preview', { target: 'all', type: 'general' }, SUPER);
    expect(r.body).toMatchObject({ topic: true, inApp: 3, pushMembers: 3 });
    const evil = await post('/v1/notifications/preview', { target: 'individual', type: 'general', memberId: "x' OR '1'='1" }, SUPER);
    expect(evil.status).toBe(200);
    for (const c of sqls) expect(c.sql).not.toContain("OR '1'='1");
  });

  it('answers 400 with a code for a bad body', async () => {
    for (const [body, code] of [[{ target: 'all', type: 'visit' }, 'bad_type'], [{ target: 'x', type: 'general' }, 'bad_target'], [{ target: 'selected', type: 'general', memberIds: [] }, 'members_required']] as const) {
      const r = await post('/v1/notifications/preview', body, SUPER);
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: code });
    }
  });

  it('falls back to the defaults, and says so, when the settings table cannot be read', async () => {
    missing = ['FROM gangnamunni_notification_settings'];
    const r = await post('/v1/notifications/preview', AUD, SUPER);
    expect(r.body).toMatchObject({ settingsAvailable: false, inApp: 3, pushMembers: 0, skippedPush: 3 }); // marketing is off by default: nothing is pushed
  });

  it('gives the options with the switches', async () => {
    process.env.ADMIN_NOTIFICATIONS_SEND = '1';
    process.env.FCM_SERVICE_ACCOUNT_PATH = '/x.json';
    const r = await get('/v1/notifications/options', SUPER);
    expect(r.body.types.map((t: any) => t.value)).toEqual(['general', 'booking', 'community', 'review', 'event', 'raffle', 'system', 'promotion']);
    expect(r.body).toMatchObject({ events: [{ id: 4, title: '가을 이벤트' }], nightWindow: { start: 21, end: 8 }, fcmConfigured: true, sendEnabled: true, scheduleEnabled: false });
    expect(r.body.links.length).toBeGreaterThan(5);
  });

  it('lists the history (also before the new columns exist) and opens one send with its recipients', async () => {
    const h = await get('/v1/notifications/history?page=1', SUPER);
    expect(h.body).toMatchObject({ available: true, total: 41, page: 1, perPage: 20 });
    expect(h.body.items[0]).toMatchObject({ id: 41, pushSent: 8, pushFailed: 1, isTest: false, requestId: null, link: '/next/events' });
    const d = await get('/v1/notifications/history/41?status=failed', SUPER);
    expect(d.body).toMatchObject({ id: 41, isTest: true, requestId: 'abc', details: { direct: { sent: 8 } }, itemsAvailable: true });
    expect(d.body.items).toMatchObject({ total: 1, status: 'failed', rows: [{ memberId: 'kim01', status: 'failed', errorCode: 'UNREGISTERED' }] });
    for (const bad of ['0', 'abc', '99', '41%20OR%201=1']) expect((await get(`/v1/notifications/history/${bad}`, SUPER)).status).toBe(404);
  });

  it('says "not available" instead of failing while the tables are missing', async () => {
    missing = ['FROM gangnamunni_notification_sends', 'FROM gangnamunni_notification_send_items', 'visit_reminder_sent_at'];
    expect((await get('/v1/notifications/history', SUPER)).body).toMatchObject({ available: false, total: 0, items: [] });
    const h = await get('/v1/notifications/automatic', SUPER);
    expect(h.body.visitReminder).toMatchObject({ available: false, sentToday: 0 });
  });

  it('searches link targets by name: fixed pages, hospitals and events, with `/next` paths only', async () => {
    const r = await get('/v1/notifications/link-targets?q=%EC%9D%B4%EB%B2%A4%ED%8A%B8', SUPER);
    expect(r.body.items.map((i: any) => i.path)).toEqual(['/next/events', '/next/hospitals/128', '/next/events/4']);
    expect(r.body.items.every((i: any) => i.path.startsWith('/next'))).toBe(true);
    const onlyHospitals = await get('/v1/notifications/link-targets?q=100%25_&kind=hospital', SUPER);
    expect(onlyHospitals.body.items.every((i: any) => i.kind === 'hospital')).toBe(true);
    const like = sqls.filter((c) => c.sql.includes('FROM gangnamunni_hospitals')).pop()!;
    expect(like.values).toEqual(['%100\\%\\_%']); // typed % and _ are literal
  });

  it('gives the token health without any token', async () => {
    const r = await get('/v1/notifications/diagnostics', SUPER);
    expect(r.body.summary).toEqual({ total: 10, active: 8, dead: 2, android: 5, ios: 3, web: 0, withoutMember: 0, iosMarkedAndroid: 1 });
    expect(r.body.recent[0]).toMatchObject({ memberId: 'kim01', tokenStart: 'abcdef', active: true });
    expect(r.body.multiDevices[0]).toMatchObject({ memberId: 'kim01', count: 2 });
    expect(sqls.every((c) => !/SELECT[^F]*\bpt_token\b(?!\s*<>)/.test(c.sql.replace(/LEFT\(pt_token, 6\)/g, '').replace(/COUNT\(DISTINCT pt_token\)/g, '')))).toBe(true);
  });

  it('reports what the visit reminder has sent', async () => {
    expect((await get('/v1/notifications/automatic', SUPER)).body).toEqual({ visitReminder: { available: true, sentToday: 3, sentYesterday: 12, lastSentAt: '2026-11-27 10:15:00', startsAt: '10:00' } });
  });
});
