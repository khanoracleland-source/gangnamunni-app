import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MembersModule } from './members.module';
import { DB, DB_RW } from '../db/db.module';

describe('admin API: members blocked by a member, with the reports (read)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const sqls: { sql: string; values: unknown[] }[] = [];
  const stub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      sqls.push({ sql, values });
      if (sql.startsWith('SELECT mb_no, mb_id, mb_hp FROM g5_member')) return [values[0] === 5 ? [{ mb_no: 5, mb_id: 'kimA', mb_hp: '' }] : []];
      if (sql.startsWith('SELECT COUNT(*) AS cnt FROM gangnamunni_blocked_users')) return [[{ cnt: 3 }]];
      if (sql.startsWith('SELECT id, blocked_id')) {
        return [[
          { id: 30, blocked_id: 'bee', blocked_name: 'B', reason: '', created_at: '2026-11-03 10:00:00' },
          { id: 29, blocked_id: 'cee', blocked_name: 'C', reason: 'rude', created_at: '2026-11-02 10:00:00' },
          { id: 28, blocked_id: 'gone', blocked_name: 'Old name', reason: '', created_at: '2026-11-01 10:00:00' },
        ]];
      }
      if (sql.startsWith('SELECT mb_no, mb_id, mb_nick')) {
        return [[
          { mb_no: 11, mb_id: 'bee', mb_nick: 'Bee', mb_name: 'B', mb_leave_date: '', mb_intercept_date: '' },
          { mb_no: 12, mb_id: 'cee', mb_nick: '', mb_name: 'Cee', mb_leave_date: '', mb_intercept_date: '20261101' },
        ]];
      }
      if (sql.startsWith('SELECT reported_user_id, COUNT(*)')) return [[{ reported_user_id: 11, cnt: 7 }]];
      if (sql.startsWith('SELECT id, reported_user_id')) {
        return [[
          { id: 1, reported_user_id: 11, content_type: 'comment', content_id: 90, content_snippet: 'bad words', reason: 'harassment', reason_detail: 'insulted me', status: 'pending', created_at: '2026-11-03 09:59:00' },
        ]];
      }
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

  it('switch off -> 404; hospital admin -> 403; no cookie -> 401; no such member -> 404', async () => {
    process.env.ADMIN_MEMBERS_ACTIVITY = '0';
    expect((await get('/v1/members/5/blocks')).status).toBe(404);
    process.env.ADMIN_MEMBERS_ACTIVITY = '1';
    expect((await get('/v1/members/5/blocks', 'PHPSESSID=hospital12')).status).toBe(403);
    expect((await request(app.getHttpServer()).get('/v1/members/5/blocks')).status).toBe(401);
    expect((await get('/v1/members/99/blocks')).status).toBe(404);
  });

  it('lists who the member blocked with the reports about each, newest first; a removed member keeps the stored name', async () => {
    const r = await get('/v1/members/5/blocks');
    expect(r.status).toBe(200);
    expect(r.body.total).toBe(3);
    expect(r.body.items).toEqual([
      { id: 30, userNo: 11, userId: 'bee', name: 'Bee', status: 'active', reason: '', blockedAt: '2026-11-03 10:00:00', reportCount: 7,
        reports: [{ id: 1, contentType: 'comment', contentId: 90, snippet: 'bad words', reason: 'harassment', detail: 'insulted me', status: 'pending', createdAt: '2026-11-03 09:59:00' }] },
      { id: 29, userNo: 12, userId: 'cee', name: 'Cee', status: 'blocked', reason: 'rude', blockedAt: '2026-11-02 10:00:00', reportCount: 0, reports: [] },
      { id: 28, userNo: null, userId: 'gone', name: 'Old name', status: null, reason: '', blockedAt: '2026-11-01 10:00:00', reportCount: 0, reports: [] },
    ]);
  });

  it('only reports of this member (reporter) about the blocked ones are read; every value is a parameter', async () => {
    await get('/v1/members/5/blocks');
    const reports = sqls.find((q) => q.sql.startsWith('SELECT id, reported_user_id'))!;
    expect(reports.sql).toContain('reporter_id = ?');
    expect(reports.values).toEqual([5, 11, 12]);
    expect(sqls.find((q) => q.sql.startsWith('SELECT id, blocked_id'))!.values).toEqual(['kimA', 0, 20]);
    for (const q of sqls) expect(q.sql).not.toMatch(/kimA|bee/);
  });
});
