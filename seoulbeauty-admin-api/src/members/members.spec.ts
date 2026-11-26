import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { MembersModule } from './members.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

describe('admin API: members (read)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const sqls: { sql: string; values: unknown[] }[] = [];
  const member = {
    mb_no: 5, mb_id: 'kim01', mb_name: '김서연', mb_nick: 'seo', mb_email: 'a@b.kr', mb_hp: '010-1111-2222',
    mb_datetime: '2026-01-02 03:04:05', mb_today_login: '2026-11-26 09:00:00', mb_leave_date: '', mb_intercept_date: '',
    mb_tel: '', mb_sex: 'F', mb_birth: '19900131', mb_addr1: 'a1', mb_addr2: 'a2', mb_login_ip: '1.2.3.4', mb_sms: 1, mb_mailling: 1, mb_memo: 'vip',
  };
  const stub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      sqls.push({ sql, values });
      if (sql.includes('COUNT(*) AS total')) return [[{ total: 10, active: 7, blocked: 2, today_login: 3, today_join: 1 }]];
      if (sql.includes('COUNT(*) AS cnt')) return [[{ cnt: 1 }]];
      if (sql.includes('WHERE mb_no = ?')) return [values[0] === 5 ? [member] : []];
      if (sql.includes('FROM g5_member')) return [[member]];
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
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_MEMBERS_ENABLED = '1';
    sqls.length = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: stub }, { provide: DB_RW, useValue: { get: () => null } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, MembersModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => {
    await app.close();
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_MEMBERS_ENABLED']) delete process.env[k];
  });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };

  it('answers 404 while the switch is off, 401 without a session and 403 for a hospital account', async () => {
    process.env.ADMIN_MEMBERS_ENABLED = '0';
    expect((await get('/v1/members', SUPER)).status).toBe(404);
    expect((await get('/v1/members/5', SUPER)).status).toBe(404);
    process.env.ADMIN_MEMBERS_ENABLED = '1';
    expect((await get('/v1/members')).status).toBe(401);
    expect((await get('/v1/members', HOSPITAL)).status).toBe(403);
    expect((await get('/v1/members/5', HOSPITAL)).status).toBe(403);
    expect((await get('/v1/members/summary', HOSPITAL)).status).toBe(403);
    expect(sqls).toHaveLength(0);
  });

  it('lists members with paging and no password data', async () => {
    const r = await get('/v1/members?perPage=10&status=active&sort=name&order=asc', SUPER);
    expect(r.status).toBe(200);
    expect(r.body).toMatchObject({ total: 1, page: 1, perPage: 10 });
    expect(r.body.items[0]).toMatchObject({ no: 5, id: 'kim01', name: '김서연', phone: '010-1111-2222', status: 'active' });
    expect(JSON.stringify(r.body)).not.toMatch(/password|mb_memo/i);
    expect(sqls.every((c) => !c.sql.includes('mb_password'))).toBe(true);
    expect(r.headers['cache-control']).toContain('no-store');
  });

  it('gives the counters, the detail and 404 for bad or unknown ids', async () => {
    expect((await get('/v1/members/summary', SUPER)).body).toEqual({ total: 10, active: 7, blocked: 2, todayLogin: 3, todayJoin: 1 });
    const d = await get('/v1/members/5', SUPER);
    expect(d.body).toMatchObject({ no: 5, sex: 'F', birth: '1990-01-31', memo: 'vip', loginIp: '1.2.3.4', smsConsent: true });
    for (const bad of ['0', 'abc', '99', '5%20OR%201=1']) expect((await get(`/v1/members/${bad}`, SUPER)).status).toBe(404);
  });
});
