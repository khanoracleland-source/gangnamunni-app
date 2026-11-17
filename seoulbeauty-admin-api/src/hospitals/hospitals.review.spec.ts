import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { HospitalsModule } from './hospitals.module';
import { DB, DB_RW } from '../db/db.module';
import { parseRejectBody, reviewerName } from './hospitals.review.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null, name = '김관리') => ({ id: 7, username: 'boss', name, role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };

type Call = { sql: string; values: unknown[] };

describe('admin API: approve / reject a hospital application', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const roCalls: Call[] = [];
  const rwCalls: Call[] = [];
  const txEvents: string[] = [];
  let statuses: Record<number, string>;
  let updateAffected: number;
  let failWith: (Error & { code?: string; errno?: number }) | null;
  let failOn: 'connect' | 'lock' | 'update' | null;
  let rwConfigured: boolean;
  let superName: string;

  const handler = async (sql: string, values: unknown[] = []): Promise<unknown> => {
    if (sql.startsWith('SELECT id, status FROM gangnamunni_hospitals')) {
      if (failOn === 'lock' && failWith) throw failWith;
      const id = values[0] as number;
      return [statuses[id] === undefined ? [] : [{ id, status: statuses[id] }]];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospitals')) {
      if (failOn === 'update' && failWith) throw failWith;
      return [{ affectedRows: updateAffected }];
    }
    throw new Error(`unexpected sql: ${sql}`);
  };
  const roStub = { query: jest.fn(async (sql: string, values: unknown[] = []) => { roCalls.push({ sql, values }); return [[]]; }) };
  const rwStub = {
    get: () => (!rwConfigured ? null : {
      query: jest.fn(async (sql: string, values: unknown[] = []) => { rwCalls.push({ sql, values }); return handler(sql, values); }),
      getConnection: async () => {
        if (failOn === 'connect' && failWith) throw failWith;
        return {
          query: async (sql: string, values: unknown[] = []) => { rwCalls.push({ sql, values }); txEvents.push(sql.split(' ').slice(0, 2).join(' ')); return handler(sql, values); },
          beginTransaction: async () => { txEvents.push('BEGIN'); },
          commit: async () => { txEvents.push('COMMIT'); },
          rollback: async () => { txEvents.push('ROLLBACK'); },
          release: () => { txEvents.push('RELEASE'); },
        };
      },
    }),
  };

  async function boot(): Promise<void> {
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, HospitalsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  }

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      phpCalls.push(cookie);
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin', null, superName) });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
    jest.spyOn(Logger.prototype, 'warn').mockImplementation(() => undefined);
    jest.spyOn(Logger.prototype, 'error').mockImplementation(() => undefined);
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_WRITE = '1';
    process.env.ADMIN_HOSPITALS_REVIEW = '1';
    phpCalls.length = 0; roCalls.length = 0; rwCalls.length = 0; txEvents.length = 0;
    statuses = { 14: 'pending', 12: 'approved', 15: 'rejected' };
    updateAffected = 1;
    failWith = null;
    failOn = null;
    rwConfigured = true;
    superName = '김관리';
    await boot();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_REVIEW']) delete process.env[k];
  });

  const get = (path: string, cookie?: string) => { let r = request(app.getHttpServer()).get(path); if (cookie) r = r.set('Cookie', cookie); return r; };
  const post = (path: string, body: unknown, cookie?: string, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post(path);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r.send(JSON.stringify(body));
  };
  const approve = (id: number | string = 14, body: unknown = {}) => post(`/v1/hospitals/${id}/approve`, body, SUPER);
  const reject = (id: number | string = 14, body: unknown = { reason: '서류 미비' }) => post(`/v1/hospitals/${id}/reject`, body, SUPER);
  const updates = () => rwCalls.filter((c) => c.sql.startsWith('UPDATE'));

  describe('switch, /v1/me and guard order', () => {
    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITALS_REVIEW=%p -> both routes 404 (PHP and database not asked), flag false', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_REVIEW; else process.env.ADMIN_HOSPITALS_REVIEW = v;
      for (const r of [await approve(), await reject()]) {
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
      }
      expect(phpCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsReview).toBe(false);
    });

    it.each(['ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_PANEL_ENABLED'])('needs %s as well', async (k) => {
      process.env[k] = '0';
      expect((await approve()).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
    });

    it('all on -> /v1/me reports hospitalsReview and keeps the old flags', async () => {
      expect((await get('/v1/me', SUPER)).body.features).toMatchObject({ hospitals: true, hospitalsWrite: true, hospitalsReview: true, hospitalsDelete: false });
      expect((await get('/v1/me', HOSPITAL)).body.features.hospitalsReview).toBe(true);
    });

    it('no X-Requested-With -> 403 csrf before any session lookup or database', async () => {
      const r = await post('/v1/hospitals/14/approve', {}, SUPER, { 'Content-Type': 'application/json' });
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'csrf' });
      expect(phpCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
    });

    it('not signed in -> 401', async () => {
      expect((await post('/v1/hospitals/14/approve', {}, undefined)).status).toBe(401);
      expect(rwCalls).toHaveLength(0);
    });

    it('hospital roles -> 403 on both routes; nothing written', async () => {
      expect((await post('/v1/hospitals/14/approve', {}, HOSPITAL)).status).toBe(403);
      expect((await post('/v1/hospitals/14/reject', { reason: 'x' }, HOSPITAL)).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
    });
  });

  describe('approve', () => {
    it('pending -> approved: one transaction, locked row first, parametrised update, exact answer', async () => {
      const r = await approve(14);
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ ok: true, id: 14, status: 'approved' });
      expect(txEvents).toEqual(['BEGIN', 'SELECT id,', 'UPDATE gangnamunni_hospitals', 'COMMIT', 'RELEASE']);
      expect(rwCalls[0].sql).toBe('SELECT id, status FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE');
      expect(rwCalls[0].values).toEqual([14]);
      expect(updates()).toHaveLength(1);
      const u = updates()[0];
      expect(u.sql).toContain("SET status = 'approved', is_active = 1, ad_grade = 'none', reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()");
      expect(u.sql).toContain("WHERE id = ? AND status = 'pending'");
      expect(u.values).toEqual(['김관리', 14]);
      expect(roCalls).toHaveLength(0);
    });

    it('body is ignored (also an array or junk)', async () => {
      expect((await approve(14, ['x'])).status).toBe(200);
    });

    it.each([[12, 'approved'], [15, 'rejected']])('hospital %p already %p -> 409 already_reviewed, rolled back, nothing updated', async (id) => {
      const r = await approve(id);
      expect(r.status).toBe(409);
      expect(r.body).toEqual({ error: 'already_reviewed' });
      expect(updates()).toHaveLength(0);
      expect(txEvents).toEqual(['BEGIN', 'SELECT id,', 'ROLLBACK', 'RELEASE']);
    });

    it('a status changed between the lock and the update (0 rows) -> 409 and rollback', async () => {
      updateAffected = 0;
      const r = await approve(14);
      expect(r.status).toBe(409);
      expect(r.body).toEqual({ error: 'already_reviewed' });
      expect(txEvents).toContain('ROLLBACK');
      expect(txEvents).not.toContain('COMMIT');
    });

    it.each(['99', '0', 'abc', '-1', '1e3'])('unknown or malformed id %p -> 404 not_found', async (id) => {
      const r = await approve(id);
      expect(r.status).toBe(404);
      expect(r.body).toEqual({ error: 'not_found' });
      expect(updates()).toHaveLength(0);
    });

    it('reviewer name: login name when the name is empty, cut to 100 characters', async () => {
      superName = '';
      await approve(14);
      expect(updates()[0].values[0]).toBe('boss');
      rwCalls.length = 0; statuses[14] = 'pending'; superName = '가'.repeat(150);
      await app.close(); await boot();
      await approve(14);
      expect(updates()[0].values[0]).toBe('가'.repeat(100));
    });
  });

  describe('reject', () => {
    it('pending -> rejected: reason (trimmed) stored in both columns, exact answer', async () => {
      const r = await reject(14, { reason: '  서류 미비  ' });
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ ok: true, id: 14, status: 'rejected' });
      expect(txEvents).toEqual(['BEGIN', 'SELECT id,', 'UPDATE gangnamunni_hospitals', 'COMMIT', 'RELEASE']);
      const u = updates()[0];
      expect(u.sql).toContain("SET status = 'rejected', is_active = 0, reject_reason = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()");
      expect(u.sql).toContain("WHERE id = ? AND status = 'pending'");
      expect(u.values).toEqual(['서류 미비', '서류 미비', '김관리', 14]);
    });

    it('a reason of exactly 1000 characters is fine; SQL text never carries the reason', async () => {
      const reason = "'; DROP TABLE x; --".padEnd(1000, 'a');
      expect((await reject(14, { reason })).status).toBe(200);
      expect(updates()[0].values[0]).toBe(reason);
      expect(updates()[0].sql).not.toContain('DROP');
    });

    it.each([
      [{}, 'required'], [{ reason: '' }, 'required'], [{ reason: '   ' }, 'required'], [{ reason: null }, 'required'],
      [{ reason: 5 }, 'bad_format'], [{ reason: 'a'.repeat(1001) }, 'too_long'], [[], 'required'],
    ])('body #%# -> 400 reason_required (%s), database untouched', async (body, field) => {
      const r = await reject(14, body);
      expect(r.status).toBe(400);
      expect(r.body.error).toBe('reason_required');
      expect(r.body.fields).toEqual({ reason: field });
      expect(rwCalls).toHaveLength(0);
    });

    it.each([12, 15])('hospital %p not pending -> 409 already_reviewed', async (id) => {
      const r = await reject(id);
      expect(r.status).toBe(409);
      expect(r.body).toEqual({ error: 'already_reviewed' });
      expect(updates()).toHaveLength(0);
    });

    it('unknown id -> 404; the id is checked before the body', async () => {
      expect((await reject(99)).status).toBe(404);
      expect((await reject(0, {})).status).toBe(404);
    });
  });

  describe('write database problems', () => {
    it('write user not configured -> 503 write_unavailable on both routes', async () => {
      rwConfigured = false;
      for (const r of [await approve(), await reject()]) {
        expect(r.status).toBe(503);
        expect(r.body).toEqual({ error: 'write_unavailable' });
      }
    });

    it.each([
      ['connect', 'ER_ACCESS_DENIED_ERROR', 1045], ['lock', 'ER_COLUMNACCESS_DENIED_ERROR', 1143], ['update', 'ER_COLUMNACCESS_DENIED_ERROR', 1143],
      ['update', 'ER_TABLEACCESS_DENIED_ERROR', 1142], ['connect', 'ECONNREFUSED', 0],
    ] as const)('missing GRANT / connection (%s, %s) -> 503 write_unavailable and rollback', async (where, code, errno) => {
      failOn = where;
      failWith = Object.assign(new Error('denied'), { code, errno });
      for (const r of [await approve(), await reject()]) {
        expect(r.status).toBe(503);
        expect(r.body).toEqual({ error: 'write_unavailable' });
      }
      if (where !== 'connect') { expect(txEvents).toContain('ROLLBACK'); expect(txEvents).not.toContain('COMMIT'); }
    });

    it('any other database error -> 500 server_error without details, rolled back', async () => {
      failOn = 'update';
      failWith = Object.assign(new Error('secret detail'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      const r = await approve();
      expect(r.status).toBe(500);
      expect(r.body).toEqual({ error: 'server_error' });
      expect(JSON.stringify(r.body)).not.toContain('secret');
      expect(txEvents).toContain('ROLLBACK');
    });
  });

  describe('pure helpers', () => {
    it('parseRejectBody trims and counts characters, not bytes', () => {
      expect(parseRejectBody({ reason: ' 가 ' })).toEqual({ ok: true, reason: '가' });
      expect(parseRejectBody({ reason: '가'.repeat(1000) }).ok).toBe(true);
      expect(parseRejectBody({ reason: '가'.repeat(1001) })).toEqual({ ok: false, fields: { reason: 'too_long' } });
      expect(parseRejectBody('x')).toEqual({ ok: false, fields: { reason: 'required' } });
    });
    it('reviewerName', () => {
      expect(reviewerName('김', 'u')).toBe('김');
      expect(reviewerName('', 'u')).toBe('u');
      expect(reviewerName(null, undefined)).toBe('');
    });
  });
});
