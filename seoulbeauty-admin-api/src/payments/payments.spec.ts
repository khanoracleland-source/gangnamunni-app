import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { PaymentsModule } from './payments.module';
import { DB } from '../db/db.module';
import { periodsOf, seoulToday } from './payments.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const NOHOSP = 'PHPSESSID=nohosp';
const EDITOR = 'PHPSESSID=editor';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
type Call = { sql: string; values: unknown[] };

describe('admin API: payments (read only)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const calls: Call[] = [];
  let missing = false;
  let treatmentRows = 1;
  const roStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      calls.push({ sql, values });
      if (missing) throw Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
      if (sql.includes('COUNT(*) AS cnt')) return [[{ cnt: 1 }]];
      if (sql.includes('GROUP BY p.treatment_name')) return [Array.from({ length: treatmentRows }, (_, i) => ({ treatment_name: `t${i}`, payments: 1, received: '10', refunded: '0', recognized: '0', held: '10', stuck: '0' }))];
      if (sql.includes('AS payments')) return [[{ payments: 1, received: '180000', refunded: '0', recognized: '0', held: '180000', stuck: '0' }]];
      if (sql.includes('AS pay_day')) return [[{ pay_day: '2026-11-20', value: '180000' }]];
      if (sql.includes('p.booking_id')) return [[{ id: 9, booking_id: 4, hospital_id: 12, hospital_name: 'H', patient_name: '김', patient_phone: '010-1234-5678', treatment_name: 't', amount: '180000', refunded_amount: '0', status: 'paid', paid_at: '2026-11-20 14:02:00', booking_status: 'confirmed', booking_date: '2026-11-23', booking_time: '10:00:00' }]];
      return [[]];
    }),
  };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (b: unknown) => new Response(JSON.stringify(b), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      if (cookie.includes('nohosp')) return json({ loggedIn: true, admin: admin('hospital_admin', null) });
      if (cookie.includes('editor')) return json({ loggedIn: true, admin: admin('editor') });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_PAYMENTS_ENABLED = '1';
    calls.length = 0; missing = false; treatmentRows = 1;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, PaymentsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => { global.fetch = realFetch; for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_PAYMENTS_ENABLED']) delete process.env[k]; });

  const get = (path: string, cookie: string = SUPER) => { const r = request(app.getHttpServer()).get(path); return cookie ? r.set('Cookie', cookie) : r; };

  it('switch off -> 404 on every route; no session -> 401; a role without the page -> 403', async () => {
    expect((await get('/v1/payments', '')).status).toBe(401);
    expect((await get('/v1/payments', EDITOR)).status).toBe(403);
    process.env.ADMIN_PAYMENTS_ENABLED = '0';
    for (const p of ['', '/hospitals', '/by-hospital', '/by-treatment', '/compare']) expect((await get(`/v1/payments${p}`)).status).toBe(404);
  });
  it('there is no way to write: POST is 404', async () => {
    expect((await request(app.getHttpServer()).post('/v1/payments').set('Cookie', SUPER).send({})).status).toBe(404);
    expect((await request(app.getHttpServer()).post('/v1/payments/refund').set('Cookie', SUPER).send({})).status).toBe(404);
  });
  it('the list carries the rows, the totals of the period and the stage of each payment', async () => {
    const r = await get('/v1/payments?from=2026-11-01&to=2026-11-20');
    expect(r.status).toBe(200);
    expect(r.body.items[0]).toMatchObject({ id: 9, stage: 'held', patientPhone: '010-****-5678', amount: 180000, visitDate: '2026-11-23' });
    expect(r.body.summary).toEqual({ payments: 1, received: 180000, refunded: 0, recognized: 0, held: 180000, stuck: 0 });
    expect(r.body.total).toBe(1);
  });
  it('ADMIN_PAYMENTS_FEES=1 adds method, fee, settlement, per-method and weekly figures; off leaves every answer as it was', async () => {
    const off = await get('/v1/payments?from=2026-11-01&to=2026-11-20');
    expect(off.body).not.toHaveProperty('methods');
    expect(off.body.items[0]).not.toHaveProperty('settlement');
    process.env.ADMIN_PAYMENTS_FEES = '1';
    try {
      calls.length = 0;
      const on = await get('/v1/payments?from=2026-11-01&to=2026-11-20');
      expect(on.status).toBe(200);
      expect(on.body.items[0]).toHaveProperty('settlementState');
      expect(on.body.summary).toMatchObject({ payments: 1, held: 180000, fee: 0, settlement: 0, settlementCounted: 0, settlementReceived: 0 });
      expect(Array.isArray(on.body.methods)).toBe(true);
      expect(Array.isArray(on.body.weeks)).toBe(true);
      expect(calls.every((c) => (c.sql.match(/\?/g) || []).length === c.values.length)).toBe(true);
      for (const p of ['/by-hospital', '/by-treatment']) expect((await get(`/v1/payments${p}`)).body.items[0]).toHaveProperty('settlement');
    } finally { delete process.env.ADMIN_PAYMENTS_FEES; }
  });
  it('a hospital account is held to its own hospital in every query, whatever hospital_id it asks for', async () => {
    for (const path of ['/v1/payments', '/v1/payments?hospital_id=99', '/v1/payments/by-hospital', '/v1/payments/by-treatment', '/v1/payments/compare?metric=held']) {
      calls.length = 0;
      const r = await get(path, HOSPITAL);
      expect(r.status).toBe(200);
      expect(calls.length).toBeGreaterThan(0);
      for (const c of calls) { expect(c.values).toContain(12); expect(c.sql).not.toMatch(/\b12\b/); }
    }
  });
  it('a hospital account without a hospital sees nothing', async () => {
    const r = await get('/v1/payments', NOHOSP);
    expect(r.status).toBe(200);
    expect(calls.every((c) => c.sql.includes('1 = 0'))).toBe(true);
  });
  it('a bad query is a 400, not an SQL error', async () => {
    for (const q of ['from=2026-13-40', 'page=0', 'hospital_id=abc']) expect((await get(`/v1/payments?${q}`)).status).toBe(400);
    expect((await get('/v1/payments/compare?metric=net')).status).toBe(400);
    expect((await get('/v1/payments/by-hospital?from=2026-11-21&to=2026-11-20')).status).toBe(400);
  });
  it('compare reads this week and last week from last Monday, and this month and last month from the 1st of last month', async () => {
    const r = await get('/v1/payments/compare?metric=held');
    expect(r.status).toBe(200);
    expect(r.body.metric).toBe('held');
    expect(r.body.week.lastWeek.days).toHaveLength(7);
    expect(r.body.month.lastMonth.cumulative.length).toBeGreaterThanOrEqual(28);
    expect(calls).toHaveLength(2);
    const p = periodsOf(seoulToday());
    expect(calls[0].values.slice(-2)).toEqual([p.week.lastStart, p.today]);
    expect(calls[1].values.slice(-2)).toEqual([p.month.lastStart, p.today]);
    expect(calls[0].sql).toContain("COALESCE(b.status, '') <> 'completed'");
  });
  it('the hospital and treatment tables say when they were cut', async () => {
    treatmentRows = 501;
    const cut = await get('/v1/payments/by-treatment');
    expect(cut.body.truncated).toBe(true);
    expect(cut.body.items).toHaveLength(500);
    treatmentRows = 3;
    const whole = await get('/v1/payments/by-treatment');
    expect(whole.body.truncated).toBe(false);
    expect(whole.body.items).toHaveLength(3);
  });
  it('a range of a year or more is a 400 on every route that takes dates', async () => {
    for (const p of ['', '/by-hospital', '/by-treatment']) {
      const r = await get(`/v1/payments${p}?from=2000-01-01&to=2026-11-20`);
      expect(r.status).toBe(400);
      expect(r.body).toMatchObject({ error: 'range_too_long' });
    }
  });
  it('missing ledger tables are a clear 503, not a crash', async () => {
    missing = true;
    const r = await get('/v1/payments');
    expect(r.status).toBe(503);
    expect(r.body).toMatchObject({ error: 'payments_unavailable' });
  });
  it('/me reports the switch', async () => {
    const r = await get('/v1/me');
    expect(r.body.features.payments).toBe(true);
    process.env.ADMIN_PAYMENTS_ENABLED = '0';
    expect((await get('/v1/me')).body.features.payments).toBe(false);
  });
});
