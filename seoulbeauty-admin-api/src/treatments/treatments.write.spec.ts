import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { TreatmentsModule } from './treatments.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
type Call = { sql: string; values: unknown[] };

describe('admin API: treatment master writes', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  const txEvents: string[] = [];
  let rwEmpty = false;
  let rwError: (Error & { code?: string; errno?: number }) | null = null;
  let activeRows: { id: number }[];
  let created = false;

  const roStub = {
    query: jest.fn(async (sql: string) => {
      const s = sql.replace(/\s+/g, ' ');
      if (s.includes('FROM gangnamunni_treatment_categories c')) return [[{ id: 1, name: 'Face', slug: 'face', icon: '', sort_order: 1, is_active: 1, treatment_count: 2 }]];
      if (s.includes('FROM gangnamunni_treatment_master t LEFT JOIN')) {
        return [[
          { id: 10, category_id: 1, category_name: 'Face', sort_order: 1, name: 'Botox', hashtag: '#Botox', description: '', duration_min: 30, recovery_days: 1, anesthesia_type: 'local', repeat_cycle_months: 6, precautions: '', avg_price_min: 10, avg_price_max: 20, is_active: 1, popularity_score: 9, created_by: 'admin', created_at: null, hospital_count: 2 },
          ...(created ? [{ id: 12, category_id: 1, category_name: 'Face', sort_order: 3, name: 'New Thing', hashtag: '#NewThing', description: '', duration_min: 0, recovery_days: 0, anesthesia_type: 'none', repeat_cycle_months: 0, precautions: '', avg_price_min: 0, avg_price_max: 0, is_active: 1, popularity_score: 0, created_by: '', created_at: null, hospital_count: 0 }] : []),
          { id: 11, category_id: 1, category_name: 'Face', sort_order: 2, name: 'Filler', hashtag: '#Filler', description: '', duration_min: 0, recovery_days: 0, anesthesia_type: 'none', repeat_cycle_months: 0, precautions: '', avg_price_min: 0, avg_price_max: 0, is_active: 1, popularity_score: 0, created_by: '', created_at: null, hospital_count: 0 },
        ]];
      }
      if (s.includes('FROM gangnamunni_treatment_aliases')) return [[{ id: 1, treatment_id: 10, alias: 'botox', lang: 'en' }]];
      if (s.includes('default_image_url FROM')) return [[{ id: 10, default_image_url: null }, { id: 11, default_image_url: null }]];
      if (s.includes('revisit_days, revisit_strict FROM')) return [[{ id: 10, revisit_days: null, revisit_strict: 0 }, { id: 11, revisit_days: null, revisit_strict: 0 }]];
      if (s.includes('FROM gangnamunni_treatment_conflicts')) return [[]];
      if (s.includes('FROM gangnamunni_settings')) return [[{ setting_value: 'hybrid' }]];
      if (s.includes('SELECT id FROM gangnamunni_treatment_categories WHERE id = ?')) return [[{ id: 1 }]];
      throw new Error('unexpected read-only sql: ' + s);
    }),
  };
  const rwQuery = async (sql: string, values: unknown[] = []) => {
    rwCalls.push({ sql, values });
    if (rwError) throw rwError;
    if (sql.startsWith('SELECT id FROM gangnamunni_treatment_master WHERE is_active = 1')) return [activeRows];
    if (sql.startsWith('INSERT INTO gangnamunni_treatment_master')) { created = true; return [{ insertId: 12, affectedRows: 1 }]; }
    return [{ affectedRows: 1 }];
  };
  const rwStub = {
    get: () => (rwEmpty ? null : { query: jest.fn(rwQuery), getConnection: async () => ({
      query: async (sql: string, values: unknown[] = []) => { txEvents.push(sql); return rwQuery(sql, values); },
      beginTransaction: async () => { txEvents.push('BEGIN'); },
      commit: async () => { txEvents.push('COMMIT'); },
      rollback: async () => { txEvents.push('ROLLBACK'); },
      release: () => { txEvents.push('RELEASE'); },
    }) }),
  };

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
    process.env.ADMIN_TREATMENTS_ENABLED = '1';
    process.env.ADMIN_TREATMENTS_WRITE = '1';
    rwCalls.length = 0; txEvents.length = 0; rwEmpty = false; rwError = null; created = false; activeRows = [{ id: 10 }, { id: 11 }, { id: 12 }];
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, TreatmentsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    delete process.env.ADMIN_PANEL_ENABLED; delete process.env.ADMIN_TREATMENTS_ENABLED; delete process.env.ADMIN_TREATMENTS_WRITE;
  });

  const send = (method: 'post' | 'put' | 'patch' | 'delete', path: string, body?: unknown, cookie: string = SUPER, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer())[method](path);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return body === undefined ? r : r.send(JSON.stringify(body));
  };

  describe('guards', () => {
    it.each([undefined, '0', 'true'])('switch %p -> 404 and nothing written', async (v) => {
      if (v === undefined) delete process.env.ADMIN_TREATMENTS_WRITE; else process.env.ADMIN_TREATMENTS_WRITE = v;
      for (const [m, p] of [['post', '/v1/treatments'], ['put', '/v1/treatments/order'], ['post', '/v1/treatments/bulk'], ['delete', '/v1/treatments/10']] as const) {
        const r = await send(m, p, {});
        expect(r.status).toBe(404);
      }
      expect(rwCalls).toHaveLength(0);
    });
    it('missing CSRF header -> 403 csrf', async () => {
      const r = await send('post', '/v1/treatments/10/active', { active: false }, SUPER, { 'Content-Type': 'application/json' });
      expect(r.status).toBe(403);
      expect(r.body.error).toBe('csrf');
      expect(rwCalls).toHaveLength(0);
    });
    it('no session -> 401, hospital admin -> 403', async () => {
      expect((await send('post', '/v1/treatments/10/active', { active: false }, '')).status).toBe(401);
      expect((await send('post', '/v1/treatments/10/active', { active: false }, HOSPITAL)).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
    });
    it('no write pool -> 503 write_unavailable', async () => {
      rwEmpty = true;
      const r = await send('post', '/v1/treatments/10/active', { active: false });
      expect(r.status).toBe(503);
      expect(r.body.error).toBe('write_unavailable');
    });
  });

  describe('create / update', () => {
    it('creates with a derived hashtag, links conflicts to active ones only, in one transaction', async () => {
      const r = await send('post', '/v1/treatments', { name: 'New Thing', categoryId: 1, conflictIds: [10, 99] });
      expect(r.status).toBe(200);
      const ins = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_treatment_master'))!;
      expect(ins.sql).not.toMatch(/New Thing/);
      expect(ins.values).toContain('#NewThing');
      const pair = rwCalls.filter((c) => c.sql.includes('gangnamunni_treatment_conflicts'));
      expect(JSON.stringify(pair.map((c) => c.values))).toContain('10');
      expect(JSON.stringify(pair.map((c) => c.values))).not.toContain('99');
      expect(txEvents[0]).toBe('BEGIN');
      expect(txEvents).toContain('COMMIT');
    });
    it.each<[object, string]>([
      [{ categoryId: 1 }, 'name'],
      [{ name: 'x', categoryId: 1, avgPriceMin: 50, avgPriceMax: 10 }, 'avgPriceMax'],
      [{ name: 'x', categoryId: 1, anesthesiaType: 'weird' }, 'anesthesiaType'],
      [{ name: 'x'.repeat(201), categoryId: 1 }, 'name'],
    ])('rejects %j with invalid_field', async (body, field) => {
      const r = await send('post', '/v1/treatments', body);
      expect(r.status).toBe(400);
      expect(r.body.error).toBe('invalid_field');
      expect(r.body.field).toBe(field);
      expect(rwCalls).toHaveLength(0);
    });
    it('a long name without a hashtag still saves: the derived hashtag is cut to the limit', async () => {
      const r = await send('post', '/v1/treatments', { name: 'x'.repeat(150), categoryId: 1 });
      expect(r.status).toBe(200);
      const ins = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_treatment_master'))!;
      expect((ins.values as unknown[]).find((v) => typeof v === 'string' && (v as string).startsWith('#'))).toHaveLength(100);
      const own = await send('post', '/v1/treatments', { name: 'ok', categoryId: 1, hashtag: 'y'.repeat(150) });
      expect(own.status).toBe(400);
      expect(own.body.field).toBe('hashtag');
    });
    it('PATCH changes only sent columns; unknown id -> 404', async () => {
      const ok = await send('patch', '/v1/treatments/10', { name: 'Botox 2' });
      expect(ok.status).toBe(200);
      const upd = rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_treatment_master SET'))!;
      expect(upd.sql).toContain('name = ?');
      expect(upd.sql).not.toContain('description');
      expect((await send('patch', '/v1/treatments/999', { name: 'x' })).status).toBe(404);
    });
  });

  describe('active / delete / bulk', () => {
    it('toggles active', async () => {
      const r = await send('post', '/v1/treatments/10/active', { active: false });
      expect(r.status).toBe(200);
      expect(rwCalls[0].values).toEqual([0, 10]);
    });
    it('delete of a treatment used by hospitals -> 409 in_use with the count, nothing deleted', async () => {
      const r = await send('delete', '/v1/treatments/10');
      expect(r.status).toBe(409);
      expect(r.body).toMatchObject({ error: 'in_use', hospitalCount: 2 });
      expect(rwCalls.filter((c) => c.sql.startsWith('DELETE'))).toHaveLength(0);
    });
    it('delete of a free treatment cleans aliases and conflicts first', async () => {
      const r = await send('delete', '/v1/treatments/11');
      expect(r.status).toBe(200);
      const del = rwCalls.filter((c) => c.sql.startsWith('DELETE')).map((c) => c.sql);
      expect(del[0]).toContain('treatment_aliases');
      expect(del[1]).toContain('treatment_conflicts');
      expect(del[2]).toContain('treatment_master');
    });
    it('bulk activate skips unknown ids; bulk delete is not offered', async () => {
      const r = await send('post', '/v1/treatments/bulk', { action: 'deactivate', ids: [10, 77] });
      expect(r.status).toBe(200);
      expect(r.body.done).toBe(1);
      expect(r.body.skipped).toEqual([{ id: 77, reason: 'not_found' }]);
      expect((await send('post', '/v1/treatments/bulk', { action: 'delete', ids: [11] })).status).toBe(400);
      expect(rwCalls.filter((c) => c.sql.startsWith('DELETE'))).toHaveLength(0);
    });
    it('bulk with a bad action or empty ids -> 400', async () => {
      expect((await send('post', '/v1/treatments/bulk', { action: 'nuke', ids: [1] })).status).toBe(400);
      expect((await send('post', '/v1/treatments/bulk', { action: 'delete', ids: [] })).status).toBe(400);
    });
    it('"bulk" and "order" are not read as ids', async () => {
      expect((await send('put', '/v1/treatments/order', { ids: [11, 10] })).status).toBe(200);
      const sorts = rwCalls.filter((c) => c.sql.includes('SET sort_order'));
      expect(sorts.map((c) => c.values)).toEqual([[1, 11], [2, 10]]);
    });
  });

  describe('aliases', () => {
    it('adds, refuses a duplicate, deletes only inside its own treatment', async () => {
      expect((await send('post', '/v1/treatments/11/aliases', { alias: 'filler', lang: 'en' })).status).toBe(200);
      const dup = await send('post', '/v1/treatments/10/aliases', { alias: 'botox', lang: 'en' });
      expect(dup.status).toBe(409);
      expect(dup.body.error).toBe('duplicate');
      expect((await send('delete', '/v1/treatments/11/aliases/1')).status).toBe(404);
      expect((await send('delete', '/v1/treatments/10/aliases/1')).status).toBe(200);
      expect(rwCalls[rwCalls.length - 1].values).toEqual([1, 10]);
    });
  });

  describe('database errors', () => {
    it('foreign key error -> 409 in_use; denied grant -> 503', async () => {
      rwError = Object.assign(new Error('fk'), { code: 'ER_ROW_IS_REFERENCED_2', errno: 1451 });
      expect((await send('delete', '/v1/treatments/11')).status).toBe(409);
      rwError = Object.assign(new Error('gone'), { code: 'ER_TABLEACCESS_DENIED_ERROR', errno: 1142 });
      expect((await send('post', '/v1/treatments/10/active', { active: true })).status).toBe(503);
    });
    it('the audit line holds ids and action only, no field values', async () => {
      const spy = jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
      await send('patch', '/v1/treatments/10', { name: 'SECRET-NAME' });
      expect(JSON.stringify(spy.mock.calls)).not.toContain('SECRET-NAME');
    });
  });
});
