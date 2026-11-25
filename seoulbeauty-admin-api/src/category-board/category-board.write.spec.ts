import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { CategoryBoardModule } from './category-board.module';
import { parseAssignBody } from './category-board.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
type Call = { sql: string; values: unknown[] };

describe('category board: write bodies', () => {
  it('treatmentIds: a non-empty list of distinct positive integers', () => {
    expect(parseAssignBody(null)).toEqual({ error: 'invalid_body' });
    expect(parseAssignBody({})).toMatchObject({ field: 'treatmentIds', reason: 'bad_format' });
    expect(parseAssignBody({ treatmentIds: [] })).toMatchObject({ field: 'treatmentIds', reason: 'required' });
    expect(parseAssignBody({ treatmentIds: [1, 'x'] })).toMatchObject({ reason: 'bad_format' });
    expect(parseAssignBody({ treatmentIds: Array.from({ length: 201 }, (_, i) => i + 1) })).toMatchObject({ reason: 'range' });
    expect(parseAssignBody({ treatmentIds: [3, 3, 4] })).toEqual([3, 4]);
  });
});

describe('admin API: category board writes', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  let rwEmpty = false;
  let rwError: (Error & { code?: string; errno?: number }) | null = null;
  let detachAffected = 1;
  let txFailsAt = -1;
  let deleteAffected = 1;
  let lockRows: { id: number }[] = [{ id: 1 }];
  let freedRows = 2;

  const roStub = {
    query: jest.fn(async (sql: string) => {
      const s = sql.replace(/\s+/g, ' ');
      if (s.includes('FROM gangnamunni_treatment_categories c')) return [[
        { id: 1, name: 'Face', slug: 'face', icon: '', sort_order: 1, is_active: 1, treatment_count: 2 },
        { id: 2, name: 'Empty', slug: 'empty', icon: '', sort_order: 2, is_active: 1, treatment_count: 0 },
      ]];
      if (s.includes('FROM gangnamunni_treatment_master t LEFT JOIN')) {
        const row = (id: number, categoryId: number | null, sort: number) => ({ id, category_id: categoryId, category_name: categoryId ? 'Face' : null, sort_order: sort, name: `T${id}`, hashtag: '', description: '', duration_min: 0, recovery_days: 0, anesthesia_type: 'none', repeat_cycle_months: 0, precautions: '', avg_price_min: 0, avg_price_max: 0, is_active: 1, popularity_score: 0, created_by: '', created_at: null, hospital_count: 0 });
        return [[row(10, 1, 3), row(11, 1, 7), row(12, null, 1), row(13, 2, 1)]];
      }
      if (s.includes('FROM gangnamunni_treatment_aliases')) return [[]];
      if (s.includes('default_image_url FROM')) return [[]];
      if (s.includes('revisit_days, revisit_strict FROM')) return [[]];
      if (s.includes('FROM gangnamunni_treatment_conflicts')) return [[]];
      if (s.includes('FROM gangnamunni_settings')) return [[]];
      throw new Error('unexpected read-only sql: ' + s);
    }),
  };
  let txStarted = 0;
  let rolledBack = 0;
  const rwQuery = async (sql: string, values: unknown[] = []) => {
    rwCalls.push({ sql, values });
    if (rwError) throw rwError;
    if (txFailsAt >= 0 && rwCalls.length === txFailsAt + 1) throw Object.assign(new Error('boom'), { errno: 1213 });
    if (sql.startsWith('SELECT id FROM gangnamunni_treatment_categories')) return [lockRows];
    if (sql.startsWith('DELETE FROM gangnamunni_treatment_categories')) return [{ affectedRows: deleteAffected }];
    if (sql.startsWith('UPDATE gangnamunni_treatment_master SET category_id = NULL WHERE category_id')) return [{ affectedRows: freedRows }];
    if (sql.includes('SET category_id = NULL')) return [{ affectedRows: detachAffected }];
    return [{ affectedRows: 1 }];
  };
  const rwStub = {
    get: () => (rwEmpty ? null : { query: jest.fn(rwQuery), getConnection: async () => ({
      query: async (sql: string, values: unknown[] = []) => rwQuery(sql, values),
      beginTransaction: async () => { txStarted++; }, commit: async () => undefined, rollback: async () => { rolledBack++; }, release: () => undefined,
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
  const boot = async () => {
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, CategoryBoardModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  };
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_TREATMENTS_ENABLED = '1';
    process.env.ADMIN_TREATMENTS_WRITE = '1';
    process.env.ADMIN_CATEGORY_BOARD = '1';
    rwCalls.length = 0; rwEmpty = false; rwError = null; detachAffected = 1; deleteAffected = 1; lockRows = [{ id: 1 }]; freedRows = 2; txFailsAt = -1; txStarted = 0; rolledBack = 0;
    await boot();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_TREATMENTS_ENABLED', 'ADMIN_TREATMENTS_WRITE', 'ADMIN_CATEGORY_BOARD']) delete process.env[k];
  });
  const post = (path: string, body: unknown, cookie = SUPER) => request(app.getHttpServer()).post(path).set(HEADERS).set('Cookie', cookie).send(body as object);
  const del = (path: string, cookie = SUPER) => request(app.getHttpServer()).delete(path).set(HEADERS).set('Cookie', cookie);

  describe('POST /v1/category-board/categories/:id/treatments', () => {
    it('moves treatments to the end of the category, one transaction, parameterised', async () => {
      const res = await post('/v1/category-board/categories/1/treatments', { treatmentIds: [12, 13, 10] }).expect(200);
      // 10 is already in category 1 and is left alone; 12 and 13 follow the last place (7).
      expect(rwCalls[0].sql).toBe('SELECT id FROM gangnamunni_treatment_categories WHERE id = ? FOR UPDATE');
      expect(rwCalls.slice(1).map((c) => c.values)).toEqual([[1, 8, 12], [1, 9, 13]]);
      expect(rwCalls[1].sql).toBe('UPDATE gangnamunni_treatment_master SET category_id = ?, sort_order = ? WHERE id = ?');
      expect(txStarted).toBe(1);
      expect(res.body.categories).toHaveLength(2);
    });
    it('into an empty category the places start at 1', async () => {
      await post('/v1/category-board/categories/2/treatments', { treatmentIds: [12] }).expect(200);
      expect(rwCalls.slice(1).map((c) => c.values)).toEqual([[2, 2, 12]]);
    });
    it('everything already in the category: nothing is written', async () => {
      await post('/v1/category-board/categories/1/treatments', { treatmentIds: [10, 11] }).expect(200);
      expect(rwCalls).toHaveLength(0);
    });
    it('unknown category 404, unknown treatment 400, bad id 404, bad body 400', async () => {
      await post('/v1/category-board/categories/99/treatments', { treatmentIds: [12] }).expect(404);
      await post('/v1/category-board/categories/1/treatments', { treatmentIds: [999] }).expect(400);
      await post('/v1/category-board/categories/abc/treatments', { treatmentIds: [12] }).expect(404);
      await post('/v1/category-board/categories/1/treatments', {}).expect(400);
      expect(rwCalls).toHaveLength(0);
    });
    it('the category deleted after the read: 404, rolled back, nothing moved', async () => {
      lockRows = [];
      await post('/v1/category-board/categories/1/treatments', { treatmentIds: [12] }).expect(404);
      expect(rolledBack).toBe(1);
      expect(rwCalls).toHaveLength(1);
    });
    it('a failing statement rolls the whole move back', async () => {
      txFailsAt = 2;
      await post('/v1/category-board/categories/1/treatments', { treatmentIds: [12, 13] }).expect(500);
      expect(rolledBack).toBe(1);
    });
    it('a refused grant is 503 write_unavailable', async () => {
      rwError = Object.assign(new Error('denied'), { errno: 1142 });
      const res = await post('/v1/category-board/categories/1/treatments', { treatmentIds: [12] }).expect(503);
      expect(res.body).toEqual({ error: 'write_unavailable' });
    });
  });

  describe('DELETE /v1/category-board/categories/:id/treatments/:treatmentId', () => {
    it('sets the category to NULL only while the treatment is still in that category', async () => {
      await del('/v1/category-board/categories/1/treatments/10').expect(200);
      expect(rwCalls).toEqual([{ sql: 'UPDATE gangnamunni_treatment_master SET category_id = NULL WHERE id = ? AND category_id = ?', values: [10, 1] }]);
    });
    it('treatment of another category or of none: 409 not_in_category, nothing written', async () => {
      const res = await del('/v1/category-board/categories/1/treatments/13').expect(409);
      expect(res.body).toEqual({ error: 'not_in_category' });
      await del('/v1/category-board/categories/1/treatments/12').expect(409);
      expect(rwCalls).toHaveLength(0);
    });
    it('moved away between the read and the write: 409', async () => {
      detachAffected = 0;
      await del('/v1/category-board/categories/1/treatments/10').expect(409);
    });
    it('unknown treatment 404', async () => {
      await del('/v1/category-board/categories/1/treatments/999').expect(404);
    });
    it('a column that does not take NULL is 503 uncategorized_unsupported', async () => {
      rwError = Object.assign(new Error("Column 'category_id' cannot be null"), { errno: 1048 });
      const res = await del('/v1/category-board/categories/1/treatments/10').expect(503);
      expect(res.body).toEqual({ error: 'uncategorized_unsupported' });
    });
  });

  describe('DELETE /v1/category-board/categories/:id', () => {
    it('frees the treatments, then deletes the category, in one transaction', async () => {
      const res = await del('/v1/category-board/categories/1').expect(200);
      expect(rwCalls.map((c) => [c.sql, c.values])).toEqual([
        ['UPDATE gangnamunni_treatment_master SET category_id = NULL WHERE category_id = ?', [1]],
        ['DELETE FROM gangnamunni_treatment_categories WHERE id = ? AND NOT EXISTS (SELECT 1 FROM gangnamunni_treatment_master WHERE category_id = ?)', [1, 1]],
      ]);
      expect(txStarted).toBe(1);
      expect(res.body.detached).toBe(2);
      expect(res.body.categories).toBeDefined();
    });
    it('an empty category is deleted too', async () => {
      freedRows = 0;
      const res = await del('/v1/category-board/categories/2').expect(200);
      expect(res.body.detached).toBe(0);
    });
    it('a treatment added in between: rolled back, 409 in_use', async () => {
      deleteAffected = 0;
      const res = await del('/v1/category-board/categories/1').expect(409);
      expect(res.body).toMatchObject({ error: 'in_use' });
      expect(rolledBack).toBe(1);
    });
    it('unknown 404, bad id 404', async () => {
      await del('/v1/category-board/categories/99').expect(404);
      await del('/v1/category-board/categories/abc').expect(404);
      expect(rwCalls).toHaveLength(0);
    });
    it('a column that does not take NULL: 503 uncategorized_unsupported, rolled back', async () => {
      rwError = Object.assign(new Error('null'), { errno: 1048 });
      const res = await del('/v1/category-board/categories/1').expect(503);
      expect(res.body).toEqual({ error: 'uncategorized_unsupported' });
      expect(rolledBack).toBe(1);
    });
    it('guards: switch off 404, hospital admin 403, no CSRF header 403', async () => {
      await del('/v1/category-board/categories/1', HOSPITAL).expect(403);
      await request(app.getHttpServer()).delete('/v1/category-board/categories/1').set('Cookie', SUPER).expect(403);
      expect(rwCalls).toHaveLength(0);
      process.env.ADMIN_TREATMENTS_WRITE = '0';
      await del('/v1/category-board/categories/1').expect(404);
    });
  });

  describe('guards', () => {
    it('write switch off: 404 even for a visitor', async () => {
      process.env.ADMIN_TREATMENTS_WRITE = '0';
      await request(app.getHttpServer()).post('/v1/category-board/categories/1/treatments').send({}).expect(404);
      await del('/v1/category-board/categories/1/treatments/10').expect(404);
    });
    it('board switch off: 404', async () => {
      process.env.ADMIN_CATEGORY_BOARD = '0';
      await post('/v1/category-board/categories/1/treatments', { treatmentIds: [12] }).expect(404);
    });
    it('no CSRF header 403, visitor 401, hospital admin 403; nothing written', async () => {
      await request(app.getHttpServer()).post('/v1/category-board/categories/1/treatments').set('Cookie', SUPER).send({ treatmentIds: [12] }).expect(403);
      await post('/v1/category-board/categories/1/treatments', { treatmentIds: [12] }, '').expect(401);
      await post('/v1/category-board/categories/1/treatments', { treatmentIds: [12] }, HOSPITAL).expect(403);
      await del('/v1/category-board/categories/1/treatments/10', HOSPITAL).expect(403);
      expect(rwCalls).toHaveLength(0);
    });
    it('write pool not configured: 503 write_unavailable', async () => {
      rwEmpty = true;
      await post('/v1/category-board/categories/1/treatments', { treatmentIds: [12] }).expect(503);
    });
  });
});
