import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DashboardModule } from './dashboard.module';
import { HealthController } from '../health/health.controller';
import { DB } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

describe('admin API (PHP admin session + dashboard summary)', () => {
  let app: INestApplication;
  const phpCalls: { url: string; cookie: string }[] = [];
  const sqls: string[] = [];
  const realFetch = global.fetch;
  let missingPayments = false;
  let linkColumn: 'ok' | 'missing' | 'denied' = 'ok';

  const dbStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      sqls.push(sql);
      const last = values[values.length - 1];
      if (sql.includes('JOIN g5_member')) {
        if (linkColumn === 'missing') throw Object.assign(new Error("Unknown column 'a.g5_mb_id' in 'on clause'"), { code: 'ER_BAD_FIELD_ERROR', errno: 1054 });
        if (linkColumn === 'denied') throw Object.assign(new Error('SELECT command denied'), { code: 'ER_COLUMNACCESS_DENIED_ERROR', errno: 1143 });
        return [[{ cnt: '2' }]];
      }
      if (sql.includes('FROM gangnamunni_admins')) return [[{ cnt: '4' }]];
      if (sql.includes('g5_member')) return [[{ total: '1200', today: '3', last7d: '25', last30d: '110', withdrawn: '37' }]];
      if (sql.includes('gangnamunni_bookings')) {
        return [[last === 14
          ? { cnt: '10', amount: '900000', cancelled: '2', pending: '3', prev_cnt: '4', prev_amount: '300000' }
          : { cnt: '40', amount: '3500000', cancelled: '5', pending: '6', prev_cnt: '30', prev_amount: '2000000' }]];
      }
      if (sql.includes('gangnamunni_platform_payments')) {
        if (missingPayments) throw Object.assign(new Error("Table 'x.gangnamunni_platform_payments' doesn't exist"), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
        return [[last === 14 ? { paid: '500000', refunded: '50000', prev_paid: '120000' } : { paid: '2100000', refunded: '150000', prev_paid: '1800000' }]];
      }
      if (sql.includes('gangnamunni_consultations')) return [[{ d7: '8', d30: 31 }]];
      if (sql.includes('gangnamunni_hospitals')) return [[{ visible: '95', pending: '4' }]];
      if (sql.includes('gangnamunni_doctors')) return [[{ cnt: 210 }]];
      if (sql.includes('gangnamunni_hospital_treatments')) return [[{ cnt: 1830 }]];
      throw new Error(`unexpected sql: ${sql}`);
    }),
  };

  async function boot(): Promise<void> {
    @Global() @Module({ providers: [{ provide: DB, useValue: dbStub }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, DashboardModule], controllers: [HealthController] }).compile();
    app = mod.createNestApplication();
    await app.init();
  }

  beforeAll(() => {
    global.fetch = jest.fn(async (url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      phpCalls.push({ url: String(url), cookie });
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('boom')) return new Response('oops', { status: 500 });
      if (cookie.includes('down')) throw new Error('connect ECONNREFUSED');
      if (cookie.includes('html')) return new Response('<html>login</html>', { status: 200 });
      if (cookie.includes('odd')) return json({ loggedIn: true });
      if (cookie.includes('redirect')) return new Response(null, { status: 302, headers: { location: '/dashboard.php' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('plain')) return json({ loggedIn: true, admin: admin('admin') });
      if (cookie.includes('hospital')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    phpCalls.length = 0;
    sqls.length = 0;
    missingPayments = false;
    linkColumn = 'ok';
    dbStub.query.mockClear();
    await boot();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => { global.fetch = realFetch; delete process.env.ADMIN_PANEL_ENABLED; });

  const get = (path: string, cookie?: string) => {
    const r = request(app.getHttpServer()).get(path);
    return cookie ? r.set('Cookie', cookie) : r;
  };

  describe('switch off', () => {
    it.each([undefined, '', '0', 'true', 'on'])('ADMIN_PANEL_ENABLED=%s -> every /v1 route is 404, PHP and DB untouched', async (v) => {
      if (v === undefined) delete process.env.ADMIN_PANEL_ENABLED; else process.env.ADMIN_PANEL_ENABLED = v;
      await get('/v1/me', SUPER).expect(404).expect({ error: 'not_found' });
      await get('/v1/dashboard/summary', SUPER).expect(404).expect({ error: 'not_found' });
      expect(phpCalls).toHaveLength(0);
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it.each([undefined, '0'])('ADMIN_PANEL_ENABLED=%s -> unknown paths and wrong methods under /v1 have the same 404 shape, uncached', async (v) => {
      if (v === undefined) delete process.env.ADMIN_PANEL_ENABLED; else process.env.ADMIN_PANEL_ENABLED = v;
      const api = () => request(app.getHttpServer());
      for (const r of [await get('/v1/nothing'), await get('/v1'), await api().post('/v1/me').set('Cookie', SUPER), await api().delete('/v1/dashboard/summary')]) {
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
        expect(r.headers['cache-control']).toBe('private, no-store');
      }
      expect(phpCalls).toHaveLength(0);
    });
    it('switch on -> unknown /v1 paths and wrong methods are still {"error":"not_found"}; outside /v1 keeps the default 404', async () => {
      for (const r of [await get('/v1/nothing', SUPER), await request(app.getHttpServer()).post('/v1/me').set('Cookie', SUPER)]) {
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
        expect(r.headers['cache-control']).toBe('private, no-store');
      }
      const other = await get('/nothing').expect(404);
      expect(other.body.error).toBe('Not Found');
    });
    it('/health still answers', async () => {
      delete process.env.ADMIN_PANEL_ENABLED;
      await get('/health').expect(200).expect({ ok: true });
    });
  });

  describe('who may enter', () => {
    it('no cookie -> 401 without calling PHP', async () => {
      await get('/v1/me').expect(401).expect({ error: 'not_logged_in' });
      await get('/v1/dashboard/summary').expect(401).expect({ error: 'not_logged_in' });
      expect(phpCalls).toHaveLength(0);
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('PHP says loggedIn=false -> 401', async () => {
      await get('/v1/dashboard/summary', 'PHPSESSID=stranger').expect(401).expect({ error: 'not_logged_in' });
      expect(phpCalls).toHaveLength(1);
      expect(phpCalls[0].cookie).toBe('PHPSESSID=stranger');
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it.each(['PHPSESSID=plain', 'PHPSESSID=hospital'])('signed in but not super_admin (%s) -> 403', async (cookie) => {
      await get('/v1/me', cookie).expect(403).expect({ error: 'forbidden' });
      await get('/v1/dashboard/summary', cookie).expect(403).expect({ error: 'forbidden' });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it.each(['x=boom', 'x=down', 'x=html', 'x=odd', 'x=redirect'])('PHP unusable (%s) -> 503, never cached', async (cookie) => {
      await get('/v1/dashboard/summary', cookie).expect(503).expect({ error: 'session_unavailable' });
      await get('/v1/me', cookie).expect(503).expect({ error: 'session_unavailable' });
      expect(phpCalls).toHaveLength(2);
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('super_admin -> /v1/me, one PHP call for requests within the cache window', async () => {
      const r = await get('/v1/me', SUPER).expect(200);
      expect(r.body).toEqual({ admin: { id: 7, username: 'boss', name: '김관리', role: 'super_admin' } });
      expect(r.headers['cache-control']).toContain('no-store');
      expect(r.headers['access-control-allow-origin']).toBeUndefined();
      await get('/v1/me', SUPER).expect(200);
      await get('/v1/dashboard/summary', SUPER).expect(200);
      expect(phpCalls).toHaveLength(1);
      expect(phpCalls[0].url).toContain('admin_session_me.php');
    });
    it('the session cache expires after 5 s', async () => {
      const now = jest.spyOn(Date, 'now');
      try {
        now.mockReturnValue(1_000_000);
        await get('/v1/me', SUPER).expect(200);
        now.mockReturnValue(1_004_999);
        await get('/v1/me', SUPER).expect(200);
        expect(phpCalls).toHaveLength(1);
        now.mockReturnValue(1_005_000);
        await get('/v1/me', SUPER).expect(200);
        expect(phpCalls).toHaveLength(2);
      } finally {
        now.mockRestore();
      }
    });
  });

  describe('GET /v1/dashboard/summary', () => {
    it('returns the documented shape with numbers', async () => {
      const r = await get('/v1/dashboard/summary', SUPER).expect(200);
      expect(r.body).toEqual({
        generatedAt: expect.stringMatching(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/),
        members: { total: 1200, today: 3, last7d: 25, last30d: 110, withdrawn: 37, admins: 4, adminsAlsoMembers: 2 },
        bookings: {
          d7: { count: 10, amount: 900000, paidAmount: 500000, refundedAmount: 50000, cancelled: 2, pending: 3, prev: { count: 4, amount: 300000, paidAmount: 120000 } },
          d30: { count: 40, amount: 3500000, paidAmount: 2100000, refundedAmount: 150000, cancelled: 5, pending: 6, prev: { count: 30, amount: 2000000, paidAmount: 1800000 } },
        },
        consultations: { d7: 8, d30: 31 },
        catalog: { hospitals: 95, hospitalsPending: 4, doctors: 210, treatments: 1830 },
      });
      expect(r.headers['cache-control']).toContain('no-store');
    });
    it('only runs SELECT statements', async () => {
      await get('/v1/dashboard/summary', SUPER).expect(200);
      expect(sqls).toHaveLength(11);
      for (const sql of sqls) expect(sql.trim()).toMatch(/^SELECT\b/);
    });
    it('a missing table is reported as 0, not as an error', async () => {
      missingPayments = true;
      const r = await get('/v1/dashboard/summary', SUPER).expect(200);
      expect(r.body.bookings.d7).toMatchObject({ count: 10, amount: 900000, paidAmount: 0, refundedAmount: 0, prev: { count: 4, amount: 300000, paidAmount: 0 } });
      expect(r.body.bookings.d30).toMatchObject({ paidAmount: 0, refundedAmount: 0 });
      expect(r.body.members.total).toBe(1200);
    });
    it.each(['missing', 'denied'] as const)('admin link column %s: adminsAlsoMembers is 0, the rest is unchanged', async (mode) => {
      linkColumn = mode;
      const r = await get('/v1/dashboard/summary', SUPER).expect(200);
      expect(r.body.members).toEqual({ total: 1200, today: 3, last7d: 25, last30d: 110, withdrawn: 37, admins: 4, adminsAlsoMembers: 0 });
    });
    it('any other database error is not swallowed', async () => {
      dbStub.query.mockImplementationOnce(async () => { throw Object.assign(new Error('Access denied'), { code: 'ER_TABLEACCESS_DENIED_ERROR', errno: 1142 }); });
      await get('/v1/dashboard/summary', SUPER).expect(500);
    });
    it('is computed once per 10 s', async () => {
      const now = jest.spyOn(Date, 'now');
      try {
        now.mockReturnValue(2_000_000);
        await get('/v1/dashboard/summary', SUPER).expect(200);
        now.mockReturnValue(2_009_999);
        await get('/v1/dashboard/summary', SUPER).expect(200);
        expect(dbStub.query).toHaveBeenCalledTimes(11);
        now.mockReturnValue(2_010_000);
        await get('/v1/dashboard/summary', SUPER).expect(200);
        expect(dbStub.query).toHaveBeenCalledTimes(22);
      } finally {
        now.mockRestore();
      }
    });
  });
});
