import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { AlertsModule } from './alerts.module';
import { DB } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const OTHER = 'PHPSESSID=other9';
const admin = (id: number) => ({ id, username: `a${id}`, name: '관리', role: 'super_admin', hospitalId: null });

describe('admin API: alert inbox', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const reads: { sql: string; values: unknown[] }[] = [];
  const ro = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      reads.push({ sql, values });
      if (sql.startsWith('SELECT COUNT(*) AS total')) return [[{ total: 5, unread: 3, u_activity: 1, u_pending: 1, u_system: 1 }]];
      if (sql.startsWith('SELECT COUNT(*) AS n') && sql.includes('is_read = 0')) return [[{ n: 3 }]];
      if (sql.startsWith('SELECT COUNT(*) AS n')) return [[{ n: 41 }]];
      if (sql.startsWith('SELECT id, type')) return [[
        { id: 9, type: 'review_pending', title: 't', message: 'm', link: 'admin_reviews.php', icon: 'x', is_read: 0, created_at: '2026-11-27 10:00:00' },
        { id: 8, type: 'login_fail', title: 't2', message: '', link: 'http://evil.test', icon: '', is_read: 1, created_at: '2026-11-26 09:00:00' },
      ]];
      throw new Error(`unexpected sql: ${sql}`);
    }),
  };

  beforeAll(() => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (b: unknown) => new Response(JSON.stringify(b), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin(7) });
      if (cookie.includes('other9')) return json({ loggedIn: true, admin: admin(9) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_ALERTS_ENABLED = '1';
    reads.length = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, AlertsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => { global.fetch = realFetch; delete process.env.ADMIN_PANEL_ENABLED; delete process.env.ADMIN_ALERTS_ENABLED; });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };

  it('is off by default: 404 everywhere and no SQL', async () => {
    process.env.ADMIN_ALERTS_ENABLED = '0';
    for (const r of [await get('/v1/alerts', SUPER), await get('/v1/alerts/unread', SUPER)]) expect(r.status).toBe(404);
    expect(reads).toHaveLength(0);
    expect((await get('/v1/me', SUPER)).body.features.alerts).toBe(false);
  });

  it('answers 401 without a session', async () => {
    expect((await get('/v1/alerts')).status).toBe(401);
    expect((await get('/v1/alerts/unread')).status).toBe(401);
    expect((await get('/v1/me', SUPER)).body.features.alerts).toBe(true);
  });

  it('lists only the signed-in admin\'s alerts, drops unsafe links, counts per tab', async () => {
    const r = await get('/v1/alerts?tab=pending&type=review_pending&from=2026-11-01&to=2026-11-27&page=2&admin_id=99', SUPER);
    expect(r.status).toBe(200);
    expect(r.body.items[0]).toMatchObject({ id: 9, type: 'review_pending', link: 'admin_reviews.php', read: false });
    expect(r.body.items[1]).toMatchObject({ id: 8, link: '', read: true });
    expect(r.body).toMatchObject({ page: 2, perPage: 20, total: 41, pages: 3, counts: { all: 3, activity: 1, pending: 1, system: 1, unread: 3, read: 2, total: 5 } });
    for (const q of reads) { expect(q.sql).toContain('admin_id = ?'); expect(q.values).toContain(7); expect(q.values).not.toContain(99); }
    const list = reads.find((q) => q.sql.startsWith('SELECT id, type'))!;
    expect(list.values.slice(-2)).toEqual([20, 20]);
    expect(list.sql).not.toContain('review_pending');
    expect(list.values).toEqual(expect.arrayContaining(['review_pending', '2026-11-01 00:00:00', '2026-11-28 00:00:00'].slice(0, 1)));
  });

  it('unread count is the admin\'s own', async () => {
    const r = await get('/v1/alerts/unread', OTHER);
    expect(r.body).toEqual({ unread: 3 });
    expect(reads[0].values).toEqual([9]);
  });

});
