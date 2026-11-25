import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { CategoryBoardModule } from './category-board.module';
import { buildBoard } from './category-board.logic';
import { TreatmentsAnswer } from '../treatments/treatments.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

const item = (id: number, categoryId: number | null, sortOrder: number, extra: Record<string, unknown> = {}) =>
  ({ id, categoryId, name: `T${id}`, sortOrder, isActive: true, popularityScore: 0, ...extra }) as unknown as TreatmentsAnswer['items'][number];
const cat = (id: number, sortOrder: number, extra: Record<string, unknown> = {}) =>
  ({ id, name: `C${id}`, slug: `c${id}`, icon: '', sortOrder, isActive: true, treatmentCount: 0, ...extra }) as TreatmentsAnswer['categories'][number];

describe('category board: grouping', () => {
  it('categories keep the home order, treatments the home order, orphans are listed apart', () => {
    const snap = {
      categories: [cat(2, 2), cat(1, 1), cat(3, 1, { isActive: false })],
      items: [item(10, 1, 2), item(11, 1, 1), item(12, 2, 1, { isActive: false }), item(13, null, 1), item(14, 99, 2), item(15, 1, 2, { popularityScore: 5 })],
    } as unknown as TreatmentsAnswer;
    const b = buildBoard(snap);
    expect(b.categories.map((c) => c.id)).toEqual([1, 3, 2]);
    expect(b.categories[0].treatments.map((t) => t.id)).toEqual([11, 15, 10]);
    expect(b.categories[2].treatments).toEqual([{ id: 12, name: 'T12', sortOrder: 1, isActive: false }]);
    expect(b.uncategorized.map((t) => t.id)).toEqual([13, 14]);
    expect(b.stats).toEqual({ categories: 3, activeCategories: 2, treatments: 6, uncategorized: 2 });
  });
  it('an empty master gives an empty board', () => {
    expect(buildBoard({ categories: [], items: [] } as unknown as TreatmentsAnswer)).toEqual({ categories: [], uncategorized: [], stats: { categories: 0, activeCategories: 0, treatments: 0, uncategorized: 0 } });
  });
});

describe('admin API: GET /v1/category-board', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const roStub = {
    query: jest.fn(async (sql: string) => {
      const s = sql.replace(/\s+/g, ' ');
      if (s.includes('FROM gangnamunni_treatment_categories c')) return [[
        { id: 1, name: 'Face', slug: 'face', icon: '', sort_order: 1, is_active: 1, treatment_count: 1 },
        { id: 2, name: 'Empty', slug: 'empty', icon: '', sort_order: 2, is_active: 1, treatment_count: 0 },
      ]];
      if (s.includes('FROM gangnamunni_treatment_master t LEFT JOIN')) {
        const row = (id: number, categoryId: number | null) => ({ id, category_id: categoryId, category_name: categoryId ? 'Face' : null, sort_order: id, name: `T${id}`, hashtag: '', description: '', duration_min: 0, recovery_days: 0, anesthesia_type: 'none', repeat_cycle_months: 0, precautions: '', avg_price_min: 0, avg_price_max: 0, is_active: 1, popularity_score: 0, created_by: '', created_at: null, hospital_count: 0 });
        return [[row(10, 1), row(11, null)]];
      }
      if (s.includes('FROM gangnamunni_treatment_aliases')) return [[]];
      if (s.includes('default_image_url FROM')) return [[]];
      if (s.includes('revisit_days, revisit_strict FROM')) return [[]];
      if (s.includes('FROM gangnamunni_treatment_conflicts')) return [[]];
      if (s.includes('FROM gangnamunni_settings')) return [[]];
      throw new Error('unexpected read-only sql: ' + s);
    }),
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
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: { get: () => null } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, CategoryBoardModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  };
  beforeEach(() => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_TREATMENTS_ENABLED = '1';
    process.env.ADMIN_CATEGORY_BOARD = '1';
    delete process.env.ADMIN_TREATMENTS_WRITE;
  });
  afterEach(async () => { await app?.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_TREATMENTS_ENABLED', 'ADMIN_TREATMENTS_WRITE', 'ADMIN_CATEGORY_BOARD']) delete process.env[k];
  });

  it('super admin gets the board', async () => {
    await boot();
    const res = await request(app.getHttpServer()).get('/v1/category-board').set('Cookie', SUPER).expect(200);
    expect(res.body.categories.map((c: any) => [c.id, c.treatments.map((t: any) => t.id)])).toEqual([[1, [10]], [2, []]]);
    expect(res.body.uncategorized.map((t: any) => t.id)).toEqual([11]);
  });
  it('switch off: 404 before anything else (also for a visitor)', async () => {
    process.env.ADMIN_CATEGORY_BOARD = '0';
    await boot();
    await request(app.getHttpServer()).get('/v1/category-board').expect(404);
    await request(app.getHttpServer()).get('/v1/category-board').set('Cookie', SUPER).expect(404);
  });
  it('needs the treatment pages on as well', async () => {
    process.env.ADMIN_TREATMENTS_ENABLED = '0';
    await boot();
    await request(app.getHttpServer()).get('/v1/category-board').set('Cookie', SUPER).expect(404);
  });
  it('visitor 401, hospital admin 403', async () => {
    await boot();
    await request(app.getHttpServer()).get('/v1/category-board').expect(401);
    await request(app.getHttpServer()).get('/v1/category-board').set('Cookie', HOSPITAL).expect(403);
  });
  it('/v1/me reports the switches', async () => {
    process.env.ADMIN_TREATMENTS_WRITE = '1';
    await boot();
    const res = await request(app.getHttpServer()).get('/v1/me').set('Cookie', SUPER).expect(200);
    expect(res.body.features).toMatchObject({ categoryBoard: true, categoryBoardWrite: true });
  });
});
