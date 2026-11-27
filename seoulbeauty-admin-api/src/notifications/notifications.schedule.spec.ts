import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { FcmClient } from '../bookings/notify/fcm.client';
import { NotificationsModule } from './notifications.module';
import { NotificationsScheduleService } from './notifications.schedule.service';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const sqlError = (code: string) => Object.assign(new Error(code), { code });
const NOON = Date.UTC(2026, 10, 27, 3, 0); // 2026-11-27 12:00 KST
const PLAN = { target: 'all', type: 'general', title: '공지', message: '내용', link: '/next/events', runAt: '2026-11-28T09:30' };
const payload = (o: object = {}) => JSON.stringify({ target: 'selected', type: 'booking', eventId: 0, memberId: '', memberIds: ['kim01', 'park03'], title: '예약', message: '내용', link: '/next/mypage/bookings', nightPolicy: 'skip', ...o });

describe('admin API: notifications (planned sends)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwSqls: { sql: string; values: unknown[] }[] = [];
  let dueRows: Record<string, unknown>[] = [];
  let pendingCount = 0;
  let claimAffected = 1;
  let cancelAffected = 1;
  let exists = true;
  let rwReady = true;
  let listRows: Record<string, unknown>[] = [];
  let tableMissing = false;
  const sent: any[] = [];

  const ro = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      if (sql.includes('FROM gangnamunni_notification_scheduled')) { if (tableMissing) throw sqlError('ER_NO_SUCH_TABLE'); return [listRows]; }
      if (sql.includes('SHOW COLUMNS FROM gangnamunni_event_entries')) return [[{ Field: 'member_id' }]];
      if (sql.includes('FROM g5_member m')) return [(values.length ? (values as string[]) : ['kim01', 'lee02', 'park03']).map((mb_id) => ({ mb_id }))];
      if (sql.includes('FROM gangnamunni_notification_settings')) return [[]];
      if (sql.includes("setting_key = 'fcm_enabled'")) return [[]];
      if (sql.includes('SELECT pt_token, mb_id, pt_platform, pt_device_info')) return [[{ pt_token: 'tk1', mb_id: 'kim01', pt_platform: 'ios', pt_device_info: '' }]];
      throw new Error(`unexpected read: ${sql}`);
    }),
  };
  const rwQuery = jest.fn(async (sql: string, values: unknown[] = []) => {
    rwSqls.push({ sql, values });
    if (sql.includes('SELECT COUNT(*) AS n FROM gangnamunni_notification_scheduled')) return [[{ n: pendingCount }]];
    if (sql.startsWith('INSERT INTO gangnamunni_notification_scheduled')) return [{ insertId: 12 }];
    if (sql.includes("SET status = 'cancelled'")) return [{ affectedRows: cancelAffected }];
    if (sql.startsWith('SELECT id FROM gangnamunni_notification_scheduled WHERE id')) return [exists ? [{ id: 5 }] : []];
    if (sql.includes("WHERE status = 'pending' AND run_at <=")) return [dueRows];
    if (sql.includes("SET status = 'running'")) return [{ affectedRows: claimAffected }];
    if (sql.startsWith('INSERT INTO gangnamunni_notification_sends')) return [{ insertId: 77 }];
    if (sql.startsWith('DELETE FROM gangnamunni_notification_send_items')) return [{ affectedRows: 8 }];
    if (sql.startsWith('DELETE FROM gangnamunni_notification_scheduled')) return [{ affectedRows: 2 }];
    if (sql.includes("SET push_error = 'failed: interrupted'")) return [{ affectedRows: 1 }];
    if (sql.startsWith('SELECT COUNT(*)')) return [[{ n: 3 }]];
    return [{ affectedRows: 1 }];
  });
  const fakeFcm = { session: jest.fn(async () => ({ project: 'p', access: 'a' })), sendMessage: jest.fn(async (_s: unknown, m: any) => { sent.push(m.message); return { ok: true }; }) };

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
    process.env.ADMIN_PANEL_ENABLED = '1'; process.env.ADMIN_NOTIFICATIONS_ENABLED = '1'; process.env.ADMIN_NOTIFICATIONS_SEND = '1'; process.env.ADMIN_NOTIFICATIONS_SCHEDULE = '1';
    process.env.NOTIFICATIONS_FCM_CONCURRENCY = '1';
    rwSqls.length = 0; sent.length = 0; dueRows = []; pendingCount = 0; claimAffected = 1; cancelAffected = 1; exists = true; rwReady = true; listRows = []; tableMissing = false;
    rwQuery.mockClear(); fakeFcm.sendMessage.mockClear();
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }, { provide: DB_RW, useValue: { get: () => (rwReady ? { query: rwQuery } : null) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, NotificationsModule] }).overrideProvider(FcmClient).useValue(fakeFcm).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_NOTIFICATIONS_ENABLED', 'ADMIN_NOTIFICATIONS_SEND', 'ADMIN_NOTIFICATIONS_SCHEDULE', 'NOTIFICATIONS_FCM_CONCURRENCY']) delete process.env[k];
  });

  const http = (method: 'post' | 'get' | 'delete', url: string, cookie?: string, body?: unknown, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer())[method](url).set(headers);
    if (cookie) r = r.set('Cookie', cookie);
    return body === undefined ? r : r.send(body as object);
  };
  const rw = (frag: string) => rwSqls.filter((c) => c.sql.includes(frag));
  const svc = () => app.get(NotificationsScheduleService);

  it('answers 404 unless both the send and the plan switch are on, then csrf / 401 / 403', async () => {
    process.env.ADMIN_NOTIFICATIONS_SCHEDULE = '0';
    for (const [m, u] of [['post', '/v1/notifications/schedule'], ['get', '/v1/notifications/scheduled'], ['delete', '/v1/notifications/scheduled/5']] as const) expect((await http(m, u, SUPER, m === 'post' ? PLAN : undefined)).status).toBe(404);
    process.env.ADMIN_NOTIFICATIONS_SCHEDULE = '1'; process.env.ADMIN_NOTIFICATIONS_SEND = '0';
    expect((await http('get', '/v1/notifications/scheduled', SUPER)).status).toBe(404);
    process.env.ADMIN_NOTIFICATIONS_SEND = '1';
    expect(await http('post', '/v1/notifications/schedule', undefined, PLAN, { 'Content-Type': 'application/json' }).then((r) => [r.status, r.body])).toEqual([403, { error: 'csrf' }]);
    expect((await http('delete', '/v1/notifications/scheduled/5', SUPER, undefined, { 'X-Requested-With': 'admin-next' })).status).toBe(403);
    expect((await http('post', '/v1/notifications/schedule', undefined, PLAN)).status).toBe(401);
    expect((await http('get', '/v1/notifications/scheduled')).status).toBe(401);
    expect((await http('post', '/v1/notifications/schedule', HOSPITAL, PLAN)).status).toBe(403);
    expect((await http('get', '/v1/notifications/scheduled', HOSPITAL)).status).toBe(403);
    expect(rwSqls).toHaveLength(0);
  });

  it('plans a send with parameters only and answers its id', async () => {
    const r = await http('post', '/v1/notifications/schedule', SUPER, { ...PLAN, title: "x'); DROP TABLE a;--", nightPolicy: 'delay' });
    expect([r.status, r.body]).toEqual([200, { id: 12, runAt: '2026-11-28 09:30', status: 'pending' }]);
    const ins = rw('INSERT INTO gangnamunni_notification_scheduled')[0];
    expect(ins.sql).not.toContain('DROP TABLE');
    expect(ins.values[0]).toBe(7);
    expect(ins.values[1]).toBe('boss');
    expect(JSON.parse(ins.values[2] as string)).toMatchObject({ target: 'all', title: "x'); DROP TABLE a;--", nightPolicy: 'delay' });
    expect(ins.values.slice(3)).toEqual(['2026-11-28 09:30:00', 'pending']);
  });

  it('refuses a bad plan, too many pending plans, and a missing write user', async () => {
    expect((await http('post', '/v1/notifications/schedule', SUPER, { ...PLAN, runAt: '2020-01-01T10:00' })).body).toEqual({ error: 'run_at_past' });
    pendingCount = 50;
    expect((await http('post', '/v1/notifications/schedule', SUPER, PLAN)).body).toEqual({ error: 'too_many_pending' });
    pendingCount = 0; rwReady = false;
    expect((await http('post', '/v1/notifications/schedule', SUPER, PLAN)).body).toEqual({ error: 'write_unavailable' });
    expect(rw('INSERT INTO')).toHaveLength(0);
  });

  it('lists the plans and says so when the table is missing', async () => {
    listRows = [{ id: 3, admin_id: 7, admin_username: 'boss', payload: payload(), run_at: '2026-11-28 09:30:00', status: 'pending', send_id: null, error: '' }];
    const r = await http('get', '/v1/notifications/scheduled', SUPER);
    expect(r.body).toMatchObject({ available: true, items: [{ id: 3, status: 'pending', runAt: '2026-11-28 09:30', memberCount: 2 }] });
    expect(r.headers['cache-control']).toContain('no-store');
    tableMissing = true;
    expect((await http('get', '/v1/notifications/scheduled', SUPER)).body).toEqual({ items: [], available: false });
  });

  it('cancels only a pending plan', async () => {
    expect((await http('delete', '/v1/notifications/scheduled/5', SUPER)).body).toEqual({ id: 5, status: 'cancelled' });
    cancelAffected = 0;
    expect((await http('delete', '/v1/notifications/scheduled/5', SUPER)).body).toEqual({ error: 'not_pending' });
    exists = false;
    expect((await http('delete', '/v1/notifications/scheduled/5', SUPER)).status).toBe(404);
    expect((await http('delete', '/v1/notifications/scheduled/abc', SUPER)).status).toBe(404);
  });

  it('runs a due plan: takes it, sends it as the planner, marks it done with the history id', async () => {
    dueRows = [{ id: 9, admin_id: 3, admin_username: 'planner', payload: payload(), run_at: '2026-11-27 11:55:00' }];
    const rep = await svc().runDue(NOON);
    expect(rep).toMatchObject({ due: 1, sent: 1, failed: 0 });
    expect(rw("SET status = 'running'")[0].values).toEqual([9]);
    const claim = rw('INSERT INTO gangnamunni_notification_sends')[0];
    expect(claim.values.slice(0, 2)).toEqual([3, 'planner']);
    expect(claim.values).toContain('sched_000000000009');
    expect(sent.length).toBeGreaterThan(0);
    const done = rw("SET status = ?, error = ?, send_id = ?")[0];
    expect(done.values).toEqual(['done', '', 77, 9]);
  });

  it('does not run a plan that another pass already took', async () => {
    dueRows = [{ id: 9, admin_id: 3, admin_username: 'p', payload: payload(), run_at: '2026-11-27 11:55:00' }];
    claimAffected = 0;
    expect(await svc().runDue(NOON)).toMatchObject({ due: 1, sent: 0, skipped: 1 });
    expect(rw('INSERT INTO gangnamunni_notification_sends')).toHaveLength(0);
  });

  it('does not send a plan that is hours late, nor one with a broken payload', async () => {
    dueRows = [
      { id: 1, admin_id: 3, admin_username: 'p', payload: payload(), run_at: '2026-11-27 08:00:00' },
      { id: 2, admin_id: 3, admin_username: 'p', payload: 'broken', run_at: '2026-11-27 11:59:00' },
    ];
    expect(await svc().runDue(NOON)).toMatchObject({ due: 2, sent: 0, missed: 1, failed: 1 });
    const fin = rw('SET status = ?, error = ?, send_id = ?').map((c) => c.values);
    expect(fin).toEqual([['failed', 'missed: too late to send', null, 1], ['failed', 'bad payload', null, 2]]);
    expect(fakeFcm.sendMessage).not.toHaveBeenCalled();
  });

  it('puts a marketing plan that asked to wait back to 08:00 when it is night', async () => {
    const night = Date.UTC(2026, 10, 27, 14, 0); // 23:00 KST
    dueRows = [{ id: 4, admin_id: 3, admin_username: 'p', payload: payload({ type: 'promotion', nightPolicy: 'delay' }), run_at: '2026-11-27 22:58:00' }];
    expect(await svc().runDue(night)).toMatchObject({ delayed: 1, sent: 0 });
    const back = rw("SET status = 'pending', run_at = ?")[0];
    expect(back.values).toEqual(['2026-11-28 08:00:00', 4]);
    expect(rw('INSERT INTO gangnamunni_notification_sends')).toHaveLength(0);
  });

  it('records why a plan failed and keeps the pass going', async () => {
    dueRows = [{ id: 6, admin_id: 3, admin_username: 'p', payload: payload({ memberIds: ['kim01'], target: 'individual', memberId: 'nobody' }), run_at: '2026-11-27 11:59:00' }];
    const ro2 = ro.query.getMockImplementation()!;
    ro.query.mockImplementation(async (sql: string, v: unknown[] = []) => (sql.includes('FROM g5_member m') ? [[]] : ro2(sql, v)));
    expect(await svc().runDue(NOON)).toMatchObject({ failed: 1, sent: 0 });
    expect(rw('SET status = ?, error = ?, send_id = ?')[0].values).toEqual(['failed', 'no_recipients', null, 6]);
    ro.query.mockImplementation(ro2);
  });

  it('cleans the old per-recipient rows, plans and stuck history rows; a dry run only counts', async () => {
    expect(await svc().cleanup(90)).toEqual({ items: 8, scheduled: 2, interrupted: 1 });
    expect(rw('DELETE FROM gangnamunni_notification_send_items')[0].values).toEqual([90]);
    rwSqls.length = 0;
    pendingCount = 3;
    expect(await svc().cleanup(90, true)).toEqual({ items: 3, scheduled: 3, interrupted: 3 });
    expect(rwSqls.every((c) => c.sql.startsWith('SELECT'))).toBe(true);
    rwSqls.length = 0;
    await svc().cleanup(1);
    expect(rw('DELETE FROM gangnamunni_notification_send_items')[0].values).toEqual([7]); // never keeps less than a week
  });
});
