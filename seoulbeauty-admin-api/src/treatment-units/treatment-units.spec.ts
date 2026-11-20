import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { TreatmentUnitsModule } from './treatment-units.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
type Call = { sql: string; values: unknown[] };
type Found = { id: number; name: string; is_active: number };

describe('admin API: treatment units', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  let rwEmpty = false;
  let rwError: (Error & { code?: string; errno?: number }) | null = null;
  let roError: (Error & { code?: string; errno?: number }) | null = null;
  let found: Found | null = null;
  let dupOnce = false;

  const roStub = {
    query: jest.fn(async (sql: string) => {
      if (roError) throw roError;
      if (sql.includes('FROM gangnamunni_treatment_units WHERE is_active = 1')) {
        return [[{ id: 1, name: '회' }, { id: 2, name: 'ml' }, { id: 3, name: '시린지' }, { id: 4, name: 'Box' }]];
      }
      throw new Error('unexpected read-only sql: ' + sql);
    }),
  };
  const rwQuery = async (sql: string, values: unknown[] = []) => {
    rwCalls.push({ sql, values });
    if (rwError) throw rwError;
    if (sql.startsWith('SELECT id, name, is_active FROM gangnamunni_treatment_units WHERE name')) return [found ? [found] : []];
    if (sql.startsWith('SELECT id, name, is_active FROM gangnamunni_treatment_units WHERE id')) return [found && found.id === values[0] ? [found] : []];
    if (sql.startsWith('INSERT INTO gangnamunni_treatment_units')) {
      if (dupOnce) {
        dupOnce = false;
        found = { id: 4, name: values[0] as string, is_active: 1 };
        throw Object.assign(new Error('dup'), { code: 'ER_DUP_ENTRY', errno: 1062 });
      }
      return [{ insertId: 9, affectedRows: 1 }];
    }
    return [{ affectedRows: 1 }];
  };
  const rwStub = { get: () => (rwEmpty ? null : { query: jest.fn(rwQuery), getConnection: async () => { throw new Error('no transactions here'); } }) };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (b: unknown) => new Response(JSON.stringify(b), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_TREATMENT_UNITS_ENABLED = '1';
    process.env.ADMIN_TREATMENT_UNITS_WRITE = '1';
    rwCalls.length = 0; rwEmpty = false; rwError = null; roError = null; found = null; dupOnce = false;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, TreatmentUnitsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    delete process.env.ADMIN_PANEL_ENABLED; delete process.env.ADMIN_TREATMENT_UNITS_ENABLED; delete process.env.ADMIN_TREATMENT_UNITS_WRITE;
  });

  const get = (path: string, cookie: string = SUPER) => {
    const r = request(app.getHttpServer()).get(path);
    return cookie ? r.set('Cookie', cookie) : r;
  };
  const send = (method: 'post' | 'delete', path: string, body?: unknown, cookie: string = SUPER, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer())[method](path);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return body === undefined ? r : r.send(JSON.stringify(body));
  };

  describe('read', () => {
    it('switch off -> 404; no session -> 401; hospital role -> 403', async () => {
      expect((await get('/v1/treatment-units', '')).status).toBe(401);
      expect((await get('/v1/treatment-units', HOSPITAL)).status).toBe(403);
      process.env.ADMIN_TREATMENT_UNITS_ENABLED = '0';
      expect((await get('/v1/treatment-units')).status).toBe(404);
      expect(roStub.query).not.toHaveBeenCalled();
    });
    it('answers the active units in the PHP order, without PHP being asked for the list', async () => {
      const r = await get('/v1/treatment-units');
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ tableReady: true, items: [{ id: 4, name: 'Box' }, { id: 2, name: 'ml' }, { id: 3, name: '시린지' }, { id: 1, name: '회' }] });
      expect(r.headers['cache-control']).toBe('private, no-store');
    });
    it('a missing table -> tableReady false, not an error; another database error -> 500', async () => {
      roError = Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
      const r = await get('/v1/treatment-units');
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ tableReady: false, items: [] });
      roError = Object.assign(new Error('boom'), { code: 'ER_PARSE_ERROR', errno: 1064 });
      expect((await get('/v1/treatment-units')).status).toBe(500);
    });
    it('/v1/me reports both switches', async () => {
      expect((await get('/v1/me')).body.features).toMatchObject({ treatmentUnits: true, treatmentUnitsWrite: true });
      process.env.ADMIN_TREATMENT_UNITS_WRITE = '0';
      expect((await get('/v1/me')).body.features).toMatchObject({ treatmentUnits: true, treatmentUnitsWrite: false });
      process.env.ADMIN_TREATMENT_UNITS_ENABLED = '0';
      process.env.ADMIN_TREATMENT_UNITS_WRITE = '1';
      expect((await get('/v1/me')).body.features).toMatchObject({ treatmentUnits: false, treatmentUnitsWrite: false });
    });
  });

  describe('write guards', () => {
    const ROUTES = [['post', '/v1/treatment-units'], ['delete', '/v1/treatment-units/4']] as const;
    it('write switch off -> 404 on both routes (also when only ENABLED is on)', async () => {
      process.env.ADMIN_TREATMENT_UNITS_WRITE = '0';
      for (const [m, p] of ROUTES) expect((await send(m, p, { name: 'x' })).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
    });
    it('no CSRF header -> 403; no session -> 401; hospital role -> 403; nothing is written', async () => {
      for (const [m, p] of ROUTES) expect((await send(m, p, { name: 'x' }, SUPER, { 'Content-Type': 'application/json' })).status).toBe(403);
      for (const [m, p] of ROUTES) expect((await send(m, p, { name: 'x' }, '')).status).toBe(401);
      for (const [m, p] of ROUTES) expect((await send(m, p, { name: 'x' }, HOSPITAL)).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
    });
    it('no write pool -> 503 write_unavailable', async () => {
      rwEmpty = true;
      const r = await send('post', '/v1/treatment-units', { name: 'Box' });
      expect(r.status).toBe(503);
      expect(r.body.error).toBe('write_unavailable');
    });
  });

  describe('add', () => {
    it('a new name is inserted with its values bound', async () => {
      const r = await send('post', '/v1/treatment-units', { name: ' 시린지 ' });
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ unit: { id: 9, name: '시린지' }, status: 'created' });
      expect(rwCalls.map((c) => c.sql.split(' ')[0])).toEqual(['SELECT', 'INSERT']);
      expect(rwCalls[1].values).toEqual(['시린지']);
    });
    it('a removed name is switched on again and keeps its stored spelling', async () => {
      found = { id: 4, name: 'Box', is_active: 0 };
      const r = await send('post', '/v1/treatment-units', { name: 'box' });
      expect(r.body).toEqual({ unit: { id: 4, name: 'Box' }, status: 'restored' });
      expect(rwCalls[1]).toEqual({ sql: expect.stringContaining('SET is_active = 1 WHERE id = ?'), values: [4] });
    });
    it('an active name is reported as existing and nothing is written', async () => {
      found = { id: 4, name: 'Box', is_active: 1 };
      const r = await send('post', '/v1/treatment-units', { name: 'BOX' });
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ unit: { id: 4, name: 'Box' }, status: 'exists' });
      expect(rwCalls).toHaveLength(1);
    });
    it('a parallel insert of the same name (duplicate key) is read again once', async () => {
      dupOnce = true;
      const r = await send('post', '/v1/treatment-units', { name: 'Box' });
      expect(r.status).toBe(200);
      expect(r.body.status).toBe('exists');
    });
    it.each([[{ name: '' }, 'required'], [{ name: 'a'.repeat(21) }, 'too_long'], [{ name: 'a\u0001' }, 'bad_format'], [{ name: 7 }, 'bad_format']])('rejects %j', async (body, reason) => {
      const r = await send('post', '/v1/treatment-units', body);
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'invalid_field', field: 'name', reason });
      expect(rwCalls).toHaveLength(0);
    });
    it('a missing table -> 503 table_missing; a refused grant -> 503 write_unavailable', async () => {
      rwError = Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
      const a = await send('post', '/v1/treatment-units', { name: 'Box' });
      expect(a.status).toBe(503);
      expect(a.body.error).toBe('table_missing');
      rwError = Object.assign(new Error('denied'), { code: 'ER_TABLEACCESS_DENIED_ERROR', errno: 1142 });
      const b = await send('post', '/v1/treatment-units', { name: 'Box' });
      expect(b.status).toBe(503);
      expect(b.body.error).toBe('write_unavailable');
    });
  });

  describe('remove', () => {
    it('switches the unit off (soft delete); nothing else is touched', async () => {
      found = { id: 4, name: 'Box', is_active: 1 };
      const r = await send('delete', '/v1/treatment-units/4');
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ ok: true });
      expect(rwCalls[1]).toEqual({ sql: expect.stringContaining('SET is_active = 0 WHERE id = ?'), values: [4] });
      expect(rwCalls.some((c) => /DELETE|dosage_unit|hospital_treatment/i.test(c.sql))).toBe(false);
    });
    it('repeating the removal of a removed unit is fine', async () => {
      found = { id: 4, name: 'Box', is_active: 0 };
      expect((await send('delete', '/v1/treatment-units/4')).status).toBe(200);
    });
    it('an id that was never there, or is no id -> 404', async () => {
      expect((await send('delete', '/v1/treatment-units/99')).status).toBe(404);
      expect((await send('delete', '/v1/treatment-units/abc')).status).toBe(404);
      expect((await send('delete', '/v1/treatment-units/0')).status).toBe(404);
      expect(rwCalls.filter((c) => c.sql.startsWith('UPDATE'))).toHaveLength(0);
    });
  });
});
