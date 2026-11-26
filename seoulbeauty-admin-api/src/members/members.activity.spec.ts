import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MembersModule } from './members.module';
import { DB, DB_RW } from '../db/db.module';

describe('admin API: member activity (read)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const sqls: { sql: string; values: unknown[] }[] = [];
  const stub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      sqls.push({ sql, values });
      if (sql.includes('FROM g5_member')) return [values[0] === 5 ? [{ mb_no: 5, mb_id: 'kim01', mb_hp: '010-1111-2222' }] : []];
      if (sql.includes('COUNT(*)')) return [[{ cnt: 1 }]];
      if (sql.includes('FROM gangnamunni_consultations')) return [[{ id: 9, hospital_id: 3, hospital_name: 'H', consultation_type: 'x', message: 'hi', status: 'new', created_at: '2026-11-01 10:00:00' }]];
      if (sql.includes('FROM gangnamunni_bookings')) return [[{ id: 4, hospital_id: 3, hospital_name: 'H', treatment: 'T', price: 100, booking_date: '2026-12-01', booking_time: '10:00', status: 'pending', created_at: '2026-11-01 10:00:00' }]];
      if (sql.includes('SELECT balance')) return [[{ balance: 300 }]];
      if (sql.includes('GROUP BY type')) return [[{ type: 'earn', total: 500 }, { type: 'use', total: 200 }]];
      if (sql.includes('FROM gangnamunni_points')) return [[{ id: 1, type: 'use', amount: 200, balance: 300, reason: 'booking', reference_type: 'booking', reference_id: '4' }]];
      if (sql.includes('FROM gangnamunni_member_audit')) return [[{ id: 1, admin_username: 'boss', action: 'password_reset', before_value: null, after_value: null }]];
      throw new Error(`unexpected sql: ${sql}`);
    }),
  };
  beforeAll(() => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const role = cookie.includes('super') ? { id: 7, username: 'boss', name: 'b', role: 'super_admin', hospitalId: null } : { id: 8, username: 'h', name: 'h', role: 'hospital_super_admin', hospitalId: 12 };
      return new Response(JSON.stringify({ loggedIn: !!cookie, admin: cookie ? role : null }), { status: 200, headers: { 'content-type': 'application/json' } });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_MEMBERS_ENABLED = '1';
    process.env.ADMIN_MEMBERS_ACTIVITY = '1';
    sqls.length = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: stub }, { provide: DB_RW, useValue: { get: () => null } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MembersModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => { global.fetch = realFetch; delete process.env.ADMIN_MEMBERS_ACTIVITY; });

  const get = (path: string, cookie = 'PHPSESSID=super') => request(app.getHttpServer()).get(path).set('Cookie', cookie);

  it('switch off -> 404; hospital admin -> 403; no cookie -> 401', async () => {
    process.env.ADMIN_MEMBERS_ACTIVITY = '0';
    expect((await get('/v1/members/5/points')).status).toBe(404);
    process.env.ADMIN_MEMBERS_ACTIVITY = '1';
    expect((await get('/v1/members/5/points', 'PHPSESSID=hospital12')).status).toBe(403);
    expect((await request(app.getHttpServer()).get('/v1/members/5/points')).status).toBe(401);
  });
  it('unknown member -> 404 on every tab', async () => {
    for (const t of ['consultations', 'bookings', 'points', 'history']) expect((await get(`/v1/members/99/${t}`)).status).toBe(404);
  });
  it('points: ledger balance, totals and entries', async () => {
    const r = await get('/v1/members/5/points');
    expect(r.status).toBe(200);
    expect(r.body).toMatchObject({ balance: 300, total: 1, totals: { earned: 500, used: 200 } });
    expect(r.body.items[0]).toMatchObject({ direction: 'out', referenceType: 'booking', referenceId: '4' });
    expect(sqls.filter((s) => s.sql.includes('gangnamunni_points')).every((s) => s.values[0] === 5)).toBe(true);
  });
  it('bookings by login id; consultations by login id and phone', async () => {
    const b = await get('/v1/members/5/bookings');
    expect(b.body.items[0]).toMatchObject({ hospitalName: 'H', bookingDate: '2026-12-01' });
    expect(sqls.some((s) => s.sql.includes('gangnamunni_bookings') && s.values[0] === 'kim01')).toBe(true);
    const c = await get('/v1/members/5/consultations');
    expect(c.body.items[0]).toMatchObject({ hospitalName: 'H', status: 'new' });
    expect(sqls.some((s) => s.sql.includes('gangnamunni_consultations') && s.values.includes('01011112222'))).toBe(true);
  });
  it('history has no password values', async () => {
    const r = await get('/v1/members/5/history');
    expect(r.body.items[0]).toMatchObject({ action: 'password_reset', before: null, after: null });
  });
});
