import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { TreatmentsModule } from './treatments.module';
import { DB, DB_RW } from '../db/db.module';
import { fallbackSlug, parseCategoryBody, parsePair, parseTableModeBody, slugFromName } from './treatments.master.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
type Call = { sql: string; values: unknown[] };

describe('treatment master: slug and body rules', () => {
  it('slug follows the PHP rule; a name with no ASCII letters has none', () => {
    expect(slugFromName('Face Lift!')).toBe('face_lift');
    expect(slugFromName('얼굴')).toBe('');
    expect(slugFromName('새 이름')).toBe('');
    expect(fallbackSlug(1_700_000_123_456)).toBe('cat_1700000123');
  });
  it('category body: name required, icon without markup, order is a non-negative integer', () => {
    expect(parseCategoryBody({ name: ' ' }, 'create')).toMatchObject({ error: 'invalid_field', field: 'name', reason: 'required' });
    expect(parseCategoryBody({ name: 'a', icon: '<b>' }, 'create')).toMatchObject({ field: 'icon', reason: 'bad_format' });
    expect(parseCategoryBody({ name: 'a', sortOrder: -1 }, 'create')).toMatchObject({ field: 'sortOrder', reason: 'range' });
    expect(parseCategoryBody({ name: 'a' }, 'create')).toEqual({ draft: { name: 'a', icon: '', sortOrder: 0 } });
    expect(parseCategoryBody({ icon: 'bi-x' }, 'patch')).toEqual({ draft: { icon: 'bi-x' } });
  });
  it('mode and pair', () => {
    expect(parseTableModeBody({ mode: 'row' })).toBe('row');
    expect(parseTableModeBody({ mode: 'grid' })).toMatchObject({ field: 'mode' });
    expect(parsePair({ a: 1, b: 1 })).toMatchObject({ field: 'b' });
    expect(parsePair({ a: 1, b: '2' })).toMatchObject({ field: 'b', reason: 'bad_format' });
    expect(parsePair({ a: 3, b: 2 })).toEqual({ a: 3, b: 2 });
  });
});

describe('admin API: categories, table mode, conflicts (writes)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  let rwEmpty = false;
  let rwError: (Error & { code?: string; errno?: number }) | null = null;
  let settingRow: boolean;
  let deleteAffected: number;
  let conflictsTable: boolean;
  let pairs: { id: number; tm_a_id: number; tm_b_id: number }[];

  const roStub = {
    query: jest.fn(async (sql: string) => {
      const s = sql.replace(/\s+/g, ' ');
      if (s.includes('FROM gangnamunni_treatment_categories c')) return [[
        { id: 1, name: 'Face', slug: 'face', icon: '', sort_order: 1, is_active: 1, treatment_count: 2 },
        { id: 2, name: '빈 카테고리', slug: 'cat_1', icon: '', sort_order: 2, is_active: 1, treatment_count: 0 },
      ]];
      if (s.includes('FROM gangnamunni_treatment_master t LEFT JOIN')) {
        const row = (id: number, name: string) => ({ id, category_id: 1, category_name: 'Face', sort_order: id, name, hashtag: `#${name}`, description: '', duration_min: 0, recovery_days: 0, anesthesia_type: 'none', repeat_cycle_months: 0, precautions: '', avg_price_min: 0, avg_price_max: 0, is_active: 1, popularity_score: 0, created_by: '', created_at: null, hospital_count: 0 });
        return [[row(10, 'Botox'), row(11, 'Filler')]];
      }
      if (s.includes('FROM gangnamunni_treatment_aliases')) return [[]];
      if (s.includes('default_image_url FROM')) return [[]];
      if (s.includes('revisit_days, revisit_strict FROM')) return [[]];
      if (s.includes('FROM gangnamunni_treatment_conflicts')) {
        if (!conflictsTable) throw Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
        return [pairs];
      }
      if (s.includes('FROM gangnamunni_settings')) return [[{ setting_value: 'hybrid' }]];
      throw new Error('unexpected read-only sql: ' + s);
    }),
  };
  const rwQuery = async (sql: string, values: unknown[] = []) => {
    rwCalls.push({ sql, values });
    if (rwError) throw rwError;
    if (sql.startsWith('SELECT id FROM gangnamunni_settings')) return [settingRow ? [{ id: 1 }] : []];
    if (sql.startsWith('INSERT INTO gangnamunni_treatment_categories')) return [{ insertId: 2, affectedRows: 1 }];
    if (sql.startsWith('DELETE FROM gangnamunni_treatment_categories')) return [{ affectedRows: deleteAffected }];
    return [{ affectedRows: 1 }];
  };
  const rwStub = {
    get: () => (rwEmpty ? null : { query: jest.fn(rwQuery), getConnection: async () => ({
      query: async (sql: string, values: unknown[] = []) => rwQuery(sql, values),
      beginTransaction: async () => undefined, commit: async () => undefined, rollback: async () => undefined, release: () => undefined,
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
    rwCalls.length = 0; rwEmpty = false; rwError = null; settingRow = true; deleteAffected = 1; conflictsTable = true; pairs = [{ id: 5, tm_a_id: 10, tm_b_id: 11 }];
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
  const ROUTES = [['post', '/v1/treatment-categories'], ['put', '/v1/treatment-categories/order'], ['patch', '/v1/treatment-categories/1'], ['put', '/v1/treatment-settings/table-mode'], ['post', '/v1/treatment-conflicts'], ['delete', '/v1/treatment-conflicts/5']] as const;

  describe('guards', () => {
    it('switch off -> 404 on every route', async () => {
      delete process.env.ADMIN_TREATMENTS_WRITE;
      for (const [m, p] of ROUTES) expect((await send(m, p, {})).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
    });
    it('no CSRF header -> 403; no session -> 401; hospital role -> 403', async () => {
      for (const [m, p] of ROUTES) expect((await send(m, p, {}, SUPER, { 'Content-Type': 'application/json' })).status).toBe(403);
      for (const [m, p] of ROUTES) expect((await send(m, p, {}, '')).status).toBe(401);
      for (const [m, p] of ROUTES) expect((await send(m, p, {}, HOSPITAL)).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
    });
    it('no write pool -> 503', async () => {
      rwEmpty = true;
      const r = await send('put', '/v1/treatment-settings/table-mode', { mode: 'row' });
      expect(r.status).toBe(503);
      expect(r.body.error).toBe('write_unavailable');
    });
  });

  describe('categories', () => {
    it('creates with a slug from the name, or cat_<time> when there are no ASCII letters', async () => {
      const a = await send('post', '/v1/treatment-categories', { name: 'Skin Care', icon: 'bi-star', sortOrder: 3 });
      expect(a.status).toBe(200);
      expect(rwCalls[0].values).toEqual(['Skin Care', 'skin_care', 'bi-star', 3]);
      rwCalls.length = 0;
      await send('post', '/v1/treatment-categories', { name: '피부' });
      expect(String(rwCalls[0].values[1])).toMatch(/^cat_\d+$/);
    });
    it.each([[{ name: '' }, 'name'], [{ name: 'a', icon: '<x>' }, 'icon'], [{ name: 'a', sortOrder: 'x' }, 'sortOrder']])('rejects %j', async (body, field) => {
      const r = await send('post', '/v1/treatment-categories', body);
      expect(r.status).toBe(400);
      expect(r.body.field).toBe(field);
      expect(rwCalls).toHaveLength(0);
    });
    it('a Korean-only rename keeps the slug; an ASCII rename changes it; unknown id -> 404', async () => {
      await send('patch', '/v1/treatment-categories/2', { name: '새 이름' });
      expect(rwCalls[0].sql).toContain('slug = ?');
      expect(rwCalls[0].values).toEqual(['새 이름', 'cat_1', 2]);
      rwCalls.length = 0;
      await send('patch', '/v1/treatment-categories/1', { name: 'Skin' });
      expect(rwCalls[0].values).toEqual(['Skin', 'skin', 1]);
      rwCalls.length = 0;
      await send('patch', '/v1/treatment-categories/1', { icon: 'bi-x' });
      expect(rwCalls[0].sql).not.toContain('slug');
      expect((await send('patch', '/v1/treatment-categories/99', { name: 'x' })).status).toBe(404);
    });
    it('toggle, and delete of a category that has treatments -> 409 in_use with the count', async () => {
      expect((await send('post', '/v1/treatment-categories/1/active', { active: false })).status).toBe(200);
      const r = await send('delete', '/v1/treatment-categories/1');
      expect(r.status).toBe(409);
      expect(r.body).toEqual({ error: 'in_use', count: 2 });
      expect(rwCalls.filter((c) => c.sql.startsWith('DELETE'))).toHaveLength(0);
    });
    it('an empty category is deleted by one guarded statement; a treatment added meanwhile -> 409', async () => {
      const ok = await send('delete', '/v1/treatment-categories/2');
      expect(ok.status).toBe(200);
      expect(rwCalls[0].sql).toContain('NOT EXISTS');
      deleteAffected = 0;
      expect((await send('delete', '/v1/treatment-categories/2')).status).toBe(409);
    });
    it('order stores positions; a duplicate slug -> 409 duplicate', async () => {
      await send('put', '/v1/treatment-categories/order', { ids: [2, 1] });
      expect(rwCalls.map((c) => c.values)).toEqual([[1, 2], [2, 1]]);
      rwError = Object.assign(new Error('dup'), { code: 'ER_DUP_ENTRY', errno: 1062 });
      const r = await send('post', '/v1/treatment-categories', { name: 'Face' });
      expect(r.status).toBe(409);
      expect(r.body.error).toBe('duplicate');
    });
  });

  describe('table mode', () => {
    it('updates the row when there is one, inserts when not, refuses a mode outside the list', async () => {
      const a = await send('put', '/v1/treatment-settings/table-mode', { mode: 'row' });
      expect(a.status).toBe(200);
      expect(a.body).toEqual({ tableMode: 'row' });
      expect(rwCalls[1].sql.startsWith('UPDATE gangnamunni_settings')).toBe(true);
      expect(rwCalls[1].values).toEqual(['row', 'tc_table_mode']);
      rwCalls.length = 0; settingRow = false;
      await send('put', '/v1/treatment-settings/table-mode', { mode: 'excel' });
      expect(rwCalls[1].sql.startsWith('INSERT INTO gangnamunni_settings')).toBe(true);
      rwCalls.length = 0;
      const bad = await send('put', '/v1/treatment-settings/table-mode', { mode: 'grid' });
      expect(bad.status).toBe(400);
      expect(rwCalls).toHaveLength(0);
    });
  });

  describe('conflicts', () => {
    it('adds a pair stored as (smaller, larger); an existing pair is not written again', async () => {
      pairs = [];
      const a = await send('post', '/v1/treatment-conflicts', { a: 11, b: 10 });
      expect(a.status).toBe(200);
      expect(a.body.existed).toBe(false);
      expect(rwCalls[0].values).toEqual([10, 11]);
      rwCalls.length = 0; pairs = [{ id: 5, tm_a_id: 10, tm_b_id: 11 }];
      const b = await send('post', '/v1/treatment-conflicts', { a: 10, b: 11 });
      expect(b.body.existed).toBe(true);
      expect(rwCalls).toHaveLength(0);
    });
    it('same id, unknown treatment -> 400; table missing -> 503', async () => {
      expect((await send('post', '/v1/treatment-conflicts', { a: 10, b: 10 })).status).toBe(400);
      const unknown = await send('post', '/v1/treatment-conflicts', { a: 10, b: 99 });
      expect(unknown.status).toBe(400);
      expect(unknown.body.field).toBe('b');
      conflictsTable = false;
      const r = await send('post', '/v1/treatment-conflicts', { a: 10, b: 11 });
      expect(r.status).toBe(503);
      expect(r.body.error).toBe('conflicts_unavailable');
    });
    it('deletes a known pair only', async () => {
      expect((await send('delete', '/v1/treatment-conflicts/5')).status).toBe(200);
      expect(rwCalls[0].values).toEqual([5]);
      expect((await send('delete', '/v1/treatment-conflicts/6')).status).toBe(404);
    });
  });

  it('the audit line holds ids and action only', async () => {
    const spy = jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
    await send('patch', '/v1/treatment-categories/1', { name: 'SECRET-NAME' });
    expect(JSON.stringify(spy.mock.calls)).not.toContain('SECRET-NAME');
  });
});
