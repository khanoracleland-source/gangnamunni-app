import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { AlertsModule } from './alerts.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const OTHER = 'PHPSESSID=other9';
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const admin = (id: number) => ({ id, username: `a${id}`, name: '관리', role: 'super_admin', hospitalId: null });

describe('admin API: alert inbox', () => {
  let app: INestApplication;
  let rwOn = true;
  const realFetch = global.fetch;
  const reads: { sql: string; values: unknown[] }[] = [];
  const writes: { sql: string; values: unknown[] }[] = [];
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
  const rw = { query: jest.fn(async (sql: string, values: unknown[] = []) => { writes.push({ sql, values }); return [{ affectedRows: 2 }]; }) };

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
    rwOn = true; reads.length = 0; writes.length = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }, { provide: DB_RW, useValue: { get: () => (rwOn ? rw : null) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, AlertsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => { global.fetch = realFetch; delete process.env.ADMIN_PANEL_ENABLED; delete process.env.ADMIN_ALERTS_ENABLED; });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };
  const post = (p: string, body: unknown, cookie?: string, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post(p).set(headers).send(body as object);
    if (cookie) r = r.set('Cookie', cookie);
    return r;
  };

  it('is off by default: 404 everywhere and no SQL', async () => {
    process.env.ADMIN_ALERTS_ENABLED = '0';
    for (const r of [await get('/v1/alerts', SUPER), await get('/v1/alerts/unread', SUPER), await post('/v1/alerts/read-tab', {}, SUPER), await post('/v1/alerts/3/read', {}, SUPER)]) expect(r.status).toBe(404);
    expect(reads.length + writes.length).toBe(0);
    expect((await get('/v1/me', SUPER)).body.features.alerts).toBe(false);
  });

  it('answers 401 without a session and 403 without the CSRF header (switch -> csrf -> session)', async () => {
    expect((await get('/v1/alerts')).status).toBe(401);
    expect((await get('/v1/alerts/unread')).status).toBe(401);
    expect((await post('/v1/alerts/3/read', {}, SUPER, { 'Content-Type': 'application/json' })).status).toBe(403);
    expect((await post('/v1/alerts/read-tab', '{}', SUPER, { 'Content-Type': 'text/plain' })).status).toBe(403);
    expect((await post('/v1/alerts/3/read', {}, undefined)).status).toBe(401);
    expect(writes).toHaveLength(0);
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
  });

  it('unread count is the admin\'s own', async () => {
    const r = await get('/v1/alerts/unread', OTHER);
    expect(r.body).toEqual({ unread: 3 });
    expect(reads[0].values).toEqual([9]);
  });

  it('marks one alert read only for its owner (the session admin id is in the SQL)', async () => {
    const r = await post('/v1/alerts/5/read', {}, OTHER);
    expect(r.status).toBe(200);
    expect(r.body).toEqual({ ok: true, updated: 2 });
    expect(writes[0].values).toEqual([5, 9]);
    expect(writes[0].sql).toContain('admin_id = ?');
    expect((await post('/v1/alerts/abc/read', {}, SUPER)).status).toBe(400);
    expect((await post('/v1/alerts/0/read', {}, SUPER)).status).toBe(400);
  });

  it('marks a tab read; no tab means all of the admin\'s own, an unknown tab is 400', async () => {
    expect((await post('/v1/alerts/read-tab', { tab: 'system' }, SUPER)).body).toEqual({ updated: 2 });
    expect(writes[0].values[0]).toBe(7);
    expect(writes[0].sql).toContain('type IN');
    expect((await post('/v1/alerts/read-tab', { tab: "x'; DROP TABLE y" }, SUPER)).status).toBe(400);
    expect(writes).toHaveLength(1);
    await post('/v1/alerts/read-tab', {}, SUPER);
    expect(writes[1].sql).not.toContain('type IN');
    expect(writes[1].values).toEqual([7]);
  });

  it('without a write user the writes answer 503 and nothing is run', async () => {
    rwOn = false;
    expect((await post('/v1/alerts/3/read', {}, SUPER)).status).toBe(503);
    expect(writes).toHaveLength(0);
  });
});
