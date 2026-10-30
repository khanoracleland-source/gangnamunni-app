import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { PointsModule } from '../src/points/points.module';
import { fifoExpiring, isCredit, logItem } from '../src/points/points.logic';

describe('points logic', () => {
  it('credit types', () => {
    for (const t of ['earn', 'refund', 'admin_add', 'admin']) expect(isCredit(t)).toBe(true);
    for (const t of ['use', 'expire', 'admin_sub', 'earn_cancel', 'x']) expect(isCredit(t)).toBe(false);
  });
  it('row display', () => {
    expect(logItem({ id: 3, type: 'earn', reason: '후기 작성', amount: 3000, balance: 5000, created_at: '2026-10-28 14:05:33', expires_at: '2027-01-26' }))
      .toEqual({ id: 3, kind: 'earn', type: 'earn', label: '적립', reason: '후기 작성', meta: '10.28 14:05 · 사용 기한 2027.01.26', amount: 3000, balance: 5000 });
    const u = logItem({ id: 4, type: 'use', reason: ' ', amount: -1000, balance: null, created_at: '2026-10-29 09:00:00', expires_at: '2027-01-26' });
    expect(u).toMatchObject({ kind: 'use', label: '사용', reason: '사용', meta: '10.29 09:00', amount: 1000, balance: null });
    expect(logItem({ type: 'weird', created_at: '2026-10-29 09:00:00' })).toMatchObject({ kind: 'use', label: '사용' });
  });
  it('fifo expiry estimate', () => {
    expect(fifoExpiring(5000, 3000, 1000)).toBe(2000);
    expect(fifoExpiring(1500, 3000, 1000)).toBe(1500);
    expect(fifoExpiring(5000, 500, 1000)).toBe(0);
  });
});

describe('points API', () => {
  let app: INestApplication;
  let settings: Record<string, string>;
  let calls: { sql: string; args: any[] }[] = [];
  const realFetch = global.fetch;
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/FROM gangnamunni_point_settings/.test(sql)) return [Object.entries(settings).map(([skey, sval]) => ({ skey, sval }))];
    if (/SELECT balance FROM/.test(sql)) return [[{ balance: 4000 }]];
    if (/SELECT \* FROM gangnamunni_points/.test(sql)) return [[
      { id: 9, type: 'earn', reason: '후기', amount: 3000, balance: 4000, created_at: '2026-10-28 10:00:00', expires_at: '2027-01-26' },
      { id: 8, type: 'use', reason: '', amount: 1000, balance: 1000, created_at: '2026-10-27 10:00:00', expires_at: null },
    ]];
    if (/AS e,/.test(sql)) return [[{ e: 3000, u: 1000 }]];
    if (/FROM gangnamunni_point_lots/.test(sql)) return [[{ expires_at: '2027-01-26', amt: 2500 }]];
    if (/MIN\(expires_at\)/.test(sql)) return [[{ d: '2027-01-26' }]];
    if (/AS c,/.test(sql)) return [[{ c: 3000, u: 1000 }]];
    return [[]];
  };
  beforeAll(async () => {
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args) } }], exports: [DB] }) class FakeDb {}
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=m1') ? { id: 'm1', no: 42, nickname: 'n', phone: '010-1234-5678' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: 'x', level: 2, ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, PointsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.MYPAGE_POINTS_VISIBLE; await app.close(); });
  beforeEach(() => { calls = []; settings = {}; process.env.MYPAGE_POINTS_VISIBLE = 'true'; });

  it('401 for visitors', async () => { await request(app.getHttpServer()).get('/v1/mypage/points').expect(401); });
  it('paused menu: nothing is read', async () => {
    process.env.MYPAGE_POINTS_VISIBLE = 'false';
    const r = await request(app.getHttpServer()).get('/v1/mypage/points').set('Cookie', 'sid=m1').expect(200);
    expect(r.body).toEqual({ visible: false });
    expect(calls).toHaveLength(0);
  });
  it('ledger with the FIFO estimate when lots are not ready', async () => {
    const r = await request(app.getHttpServer()).get('/v1/mypage/points').set('Cookie', 'sid=m1').expect(200);
    expect(r.headers['cache-control']).toContain('no-store');
    expect(r.body).toMatchObject({ visible: true, balance: 4000, monthEarn: 3000, monthUse: 1000, hasMore: false, expiry: { date: '2027-01-26', amount: 2000 } });
    expect(r.body.logs).toHaveLength(2);
    expect(r.body.logs[0]).toMatchObject({ kind: 'earn', label: '적립', meta: '10.28 10:00 · 사용 기한 2027.01.26' });
    expect(calls.find((c) => /SELECT \* FROM gangnamunni_points/.test(c.sql))!.args).toEqual([42]);
  });
  it('lots win when v2 is ready', async () => {
    settings = { v2_schema_version: '1', lots_ready: '1' };
    const r = await request(app.getHttpServer()).get('/v1/mypage/points').set('Cookie', 'sid=m1').expect(200);
    expect(r.body.expiry).toEqual({ date: '2027-01-26', amount: 2500 });
  });
  it('policy: hidden / v1 days / v2 days', async () => {
    process.env.MYPAGE_POINTS_VISIBLE = 'false';
    expect((await request(app.getHttpServer()).get('/v1/points/policy').expect(200)).body).toEqual({ visible: false, expireDays: 0 });
    process.env.MYPAGE_POINTS_VISIBLE = 'true';
    expect((await request(app.getHttpServer()).get('/v1/points/policy').expect(200)).body).toEqual({ visible: true, expireDays: 365 });
    settings = { schema_version: '3', v2_schema_version: '1', enabled: '1', v2_policy_enabled: '1', v2_expire_days: '120' };
    expect((await request(app.getHttpServer()).get('/v1/points/policy').expect(200)).body).toEqual({ visible: true, expireDays: 120 });
  });
});
