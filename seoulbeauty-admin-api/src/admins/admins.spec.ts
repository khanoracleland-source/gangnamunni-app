import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { AdminsModule } from './admins.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL5 = 'PHPSESSID=hospital5';
const PLAIN = 'PHPSESSID=plain';
const admin = (id: number, role: string, hospitalId: number | null = null) => ({ id, username: `u${id}`, name: `관리자${id}`, role, hospitalId });

const ROWS: Record<number, Record<string, unknown>> = {
  1: { id: 1, username: 'boss', name: '최고', email: 'b@example.com', phone: '', role: 'super_admin', hospital_id: null, hospital_name: null, is_active: 1, last_login_at: '2026-11-19 08:00:00', g5_mb_id: 'm1', permissions: null },
  5: { id: 5, username: 'hosp5', name: '병원', email: 'h@example.com', phone: '010', role: 'hospital_admin', hospital_id: 12, hospital_name: '서울의원', is_active: 1, last_login_at: null, g5_mb_id: null, permissions: '{"notifications":["review_new"]}' },
  6: { id: 6, username: 'old6', name: '오래', email: '', phone: '', role: 'hospital_admin', hospital_id: 13, hospital_name: '다른', is_active: 0, last_login_at: null, g5_mb_id: null, permissions: '{}' },
  7: { id: 7, username: 'new7', name: '방금', email: '', phone: '', role: 'hospital_admin', hospital_id: 13, hospital_name: '다른', is_active: 0, last_login_at: null, g5_mb_id: null, permissions: '{}' },
};

describe('admin API: admin accounts (read)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const calls: { sql: string; values: unknown[] }[] = [];
  let missingColumn = false;
  let missingSso = false;
  let rwOpened = 0;

  const handler = async (sql: string, values: unknown[] = []): Promise<unknown> => {
    calls.push({ sql, values });
    if (sql.includes('deactivated_at')) {
      if (missingColumn) throw Object.assign(new Error('Unknown column'), { code: 'ER_BAD_FIELD_ERROR', errno: 1054 });
      const ids = values[1] as number[];
      const marks: Record<number, Record<string, unknown>> = {
        6: { id: 6, deactivated_at: '2026-11-10 10:00:00', inactive_days: 9, ready: 1 },
        7: { id: 7, deactivated_at: '2026-11-18 10:00:00', inactive_days: 1, ready: 0 },
      };
      return [ids.filter((i) => marks[i]).map((i) => marks[i])];
    }
    if (sql.includes('g5_mb_id') && !sql.includes('permissions')) {
      if (missingSso) throw Object.assign(new Error('Unknown column'), { code: 'ER_BAD_FIELD_ERROR', errno: 1054 });
      return [(values[0] as number[]).filter((i) => ROWS[i]).map((i) => ({ id: i, g5_mb_id: ROWS[i].g5_mb_id }))];
    }
    if (sql.startsWith('SELECT id, name FROM gangnamunni_hospitals')) return [[{ id: 12, name: '서울의원' }, { id: 13, name: '다른' }]];
    if (sql.includes('FROM gangnamunni_admins WHERE id = ?')) return [ROWS[values[0] as number] ? [ROWS[values[0] as number]] : []];
    if (sql.includes('FROM gangnamunni_admins ORDER BY')) return [Object.values(ROWS)];
    throw new Error(`unexpected sql: ${sql}`);
  };
  const dbStub = { query: jest.fn(async (sql: string, values: unknown[] = []) => handler(sql, values)) };
  const rwStub = { get: () => { rwOpened += 1; return null; } };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      phpCalls.push(cookie);
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin(1, 'super_admin') });
      if (cookie.includes('hospital5')) return json({ loggedIn: true, admin: admin(5, 'hospital_admin', 12) });
      if (cookie.includes('plain')) return json({ loggedIn: true, admin: admin(9, 'viewer') });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
    jest.spyOn(Logger.prototype, 'warn').mockImplementation(() => undefined);
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_ADMINS_ENABLED = '1';
    phpCalls.length = 0; calls.length = 0; missingColumn = false; missingSso = false; rwOpened = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: dbStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, AdminsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => { global.fetch = realFetch; delete process.env.ADMIN_PANEL_ENABLED; delete process.env.ADMIN_ADMINS_ENABLED; });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };

  describe('switch, /v1/me and roles', () => {
    it('off -> every route answers 404 and PHP is not asked', async () => {
      process.env.ADMIN_ADMINS_ENABLED = '0';
      for (const p of ['/v1/admins', '/v1/admins/options', '/v1/admins/1']) expect((await get(p, SUPER)).status).toBe(404);
      expect(phpCalls).toEqual([]);
      expect((await get('/v1/me', SUPER)).body.features.admins).toBe(false);
    });
    it('on -> /v1/me reports admins; master switch off -> 404', async () => {
      expect((await get('/v1/me', SUPER)).body.features.admins).toBe(true);
      process.env.ADMIN_PANEL_ENABLED = '0';
      expect((await get('/v1/admins', SUPER)).status).toBe(404);
    });
    it('not signed in -> 401; a role outside the panel -> 403; nothing is read', async () => {
      expect((await get('/v1/admins')).status).toBe(401);
      expect((await get('/v1/admins', 'PHPSESSID=nobody')).status).toBe(401);
      expect((await get('/v1/admins', PLAIN)).status).toBe(403);
      expect(calls).toEqual([]);
    });
    it('never opens the write pool', async () => {
      await get('/v1/admins', SUPER);
      expect(rwOpened).toBe(0);
    });
  });

  describe('list, options, detail', () => {
    it('super admin: every account, no secrets, inactivity marks for the switched-off ones', async () => {
      const res = await get('/v1/admins', SUPER);
      expect(res.status).toBe(200);
      expect(res.body).toMatchObject({ scope: 'all', deleteAfterDays: 5, total: 4 });
      expect(JSON.stringify(res.body)).not.toMatch(/password|cred_epoch/i);
      const by = Object.fromEntries(res.body.items.map((i: any) => [i.id, i]));
      expect(by[1]).toMatchObject({ role: 'super_admin', hospitalId: null, isSelf: true, ssoMemberId: 'm1' });
      expect(by[5]).toMatchObject({ notifications: ['review_new'], notificationsCustom: true });
      expect(by[6]).toMatchObject({ isActive: false, deleteReady: true, inactiveDays: 9 });
      expect(by[7]).toMatchObject({ isActive: false, deleteReady: false, inactiveDays: 1 });
      expect(by[1].ssoMemberId).toBe('m1');
      const inactivity = calls.find((c) => c.sql.includes('deactivated_at'))!;
      expect(inactivity.values).toEqual([5, [6, 7]]);
    });
    it('hospital role: only itself, no SSO link, no options for others', async () => {
      const res = await get('/v1/admins', HOSPITAL5);
      expect(res.body).toMatchObject({ scope: 'self', total: 1 });
      expect(res.body.items[0]).toMatchObject({ id: 5, isSelf: true, ssoMemberId: null });
      expect(calls[0].sql).toContain('WHERE id = ?');
      expect(calls[0].values).toEqual([5]);
      const opt = await get('/v1/admins/options', HOSPITAL5);
      expect(opt.body).toMatchObject({ roles: [], hospitals: [], minPasswordLength: 8 });
    });
    it('options for a super admin: roles, hospitals, notification types', async () => {
      const res = await get('/v1/admins/options', SUPER);
      expect(res.body.roles).toEqual(['super_admin', 'hospital_admin']);
      expect(res.body.hospitals).toEqual([{ id: 12, name: '서울의원' }, { id: 13, name: '다른' }]);
      expect(res.body.notificationTypes).toHaveLength(15);
    });
    it('detail: super any, others only themselves (another id -> 403 without reading), bad id -> 404', async () => {
      expect((await get('/v1/admins/6', SUPER)).body).toMatchObject({ id: 6, deleteReady: true });
      expect((await get('/v1/admins/99', SUPER)).status).toBe(404);
      expect((await get('/v1/admins/abc', SUPER)).status).toBe(404);
      expect((await get('/v1/admins/0', SUPER)).status).toBe(404);
      calls.length = 0;
      expect((await get('/v1/admins/1', HOSPITAL5)).status).toBe(403);
      expect(calls).toEqual([]);
      expect((await get('/v1/admins/5', HOSPITAL5)).body).toMatchObject({ id: 5, isSelf: true });
    });
    it('the g5_mb_id column is not there yet (SSO migration not run) -> the list still works, no links shown', async () => {
      missingSso = true;
      const res = await get('/v1/admins', SUPER);
      expect(res.status).toBe(200);
      expect(res.body.total).toBe(4);
      expect(res.body.items.every((i: any) => i.ssoMemberId === null)).toBe(true);
      expect(calls.filter((c) => c.sql.includes('FROM gangnamunni_admins') && c.sql.includes('permissions') && c.sql.includes('g5_mb_id'))).toEqual([]);
    });
    it('the deactivated_at column is not there yet -> the page still works, nobody is delete-ready', async () => {
      missingColumn = true;
      const res = await get('/v1/admins', SUPER);
      expect(res.status).toBe(200);
      expect(res.body.items.every((i: any) => i.deleteReady === false && i.deactivatedAt === null)).toBe(true);
    });
  });
});
