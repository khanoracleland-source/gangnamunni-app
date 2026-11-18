import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { TreatmentsModule } from './treatments.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const PLAIN = 'PHPSESSID=plain';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const dbError = (code: string, errno: number, message = code) => Object.assign(new Error(message), { code, errno });

type Call = { sql: string; values: unknown[] };

describe('GET /v1/treatments (treatment master, read-only)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const calls: Call[] = [];
  /** which optional statements fail, and how */
  let failing: Record<string, Error>;
  let tableModeRows: unknown[];

  const dbStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      calls.push({ sql, values });
      const s = sql.replace(/\s+/g, ' ');
      for (const key of Object.keys(failing)) if (s.includes(key)) throw failing[key];
      if (s.includes('FROM gangnamunni_treatment_categories c')) {
        return [[{ id: 1, name: 'Face', slug: 'face', icon: 'bi-emoji-smile', sort_order: 1, is_active: 1, treatment_count: 2 }, { id: 2, name: 'Old', slug: 'old', icon: '', sort_order: 2, is_active: 0, treatment_count: 0 }]];
      }
      if (s.includes('FROM gangnamunni_treatment_master t LEFT JOIN')) {
        return [[
          { id: 10, category_id: 1, category_name: 'Face', sort_order: 1, name: 'Botox', hashtag: '#Botox', description: 'd', duration_min: 30, recovery_days: 1, anesthesia_type: 'local', repeat_cycle_months: 6, precautions: '', avg_price_min: 10, avg_price_max: 20, is_active: 1, popularity_score: 9, created_by: 'admin', created_at: '2026-11-18 09:00:00', hospital_count: 2 },
          { id: 11, category_id: 1, category_name: 'Face', sort_order: 2, name: 'Filler', hashtag: '#Filler', description: '', duration_min: 0, recovery_days: 0, anesthesia_type: 'none', repeat_cycle_months: 0, precautions: '', avg_price_min: 0, avg_price_max: 0, is_active: 0, popularity_score: 0, created_by: '', created_at: null, hospital_count: 0 },
        ]];
      }
      if (s.includes('FROM gangnamunni_treatment_aliases')) return [[{ id: 1, treatment_id: 10, alias: 'botox', lang: 'en' }]];
      if (s.includes('SELECT id, default_image_url FROM gangnamunni_treatment_master')) return [[{ id: 10, default_image_url: 'uploads/treatments/b.jpg' }, { id: 11, default_image_url: null }]];
      if (s.includes('SELECT id, revisit_days, revisit_strict FROM gangnamunni_treatment_master')) return [[{ id: 10, revisit_days: 90, revisit_strict: 0 }, { id: 11, revisit_days: null, revisit_strict: 0 }]];
      if (s.includes('FROM gangnamunni_treatment_conflicts')) return [[{ id: 3, tm_a_id: 10, tm_b_id: 11 }]];
      if (s.includes('FROM gangnamunni_settings')) return [tableModeRows];
      throw new Error('unexpected statement: ' + s);
    }),
  };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      if (cookie.includes('plain')) return json({ loggedIn: true, admin: admin('admin') });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_TREATMENTS_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    delete process.env.ADMIN_TREATMENTS_WRITE;
    delete process.env.ADMIN_TREATMENTS_IMAGES;
    failing = {};
    tableModeRows = [{ setting_value: 'hybrid' }];
    calls.length = 0;
    dbStub.query.mockClear();
    (global.fetch as jest.Mock).mockClear();
    @Global() @Module({ providers: [{ provide: DB, useValue: dbStub }, { provide: DB_RW, useValue: { get: () => null } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, TreatmentsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_TREATMENTS_ENABLED', 'ADMIN_TREATMENTS_WRITE', 'ADMIN_TREATMENTS_IMAGES', 'ADMIN_HOSPITALS_ENABLED']) delete process.env[k];
  });

  const get = (cookie?: string, path = '/v1/treatments') => {
    const r = request(app.getHttpServer()).get(path);
    return cookie ? r.set('Cookie', cookie) : r;
  };

  it('super admin: the whole master in one uncacheable answer', async () => {
    const r = await get(SUPER).expect(200);
    expect(r.headers['cache-control']).toContain('no-store');
    expect(r.body.stats).toEqual({ total: 2, active: 1, inactive: 1, categories: 1 });
    expect(r.body.schema).toEqual({ revisit: true, defaultImage: true, conflicts: true });
    expect(r.body.tableMode).toBe('hybrid');
    expect(r.body.categories).toEqual([
      { id: 1, name: 'Face', slug: 'face', icon: 'bi-emoji-smile', sortOrder: 1, isActive: true, treatmentCount: 2 },
      { id: 2, name: 'Old', slug: 'old', icon: '', sortOrder: 2, isActive: false, treatmentCount: 0 },
    ]);
    expect(r.body.conflicts).toEqual([{ id: 3, a: 10, b: 11 }]);
    expect(r.body.items).toHaveLength(2);
    expect(r.body.items[0]).toEqual({
      id: 10, categoryId: 1, categoryName: 'Face', sortOrder: 1, name: 'Botox', hashtag: '#Botox', description: 'd', defaultImageUrl: 'uploads/treatments/b.jpg',
      durationMin: 30, recoveryDays: 1, anesthesiaType: 'local', repeatCycleMonths: 6, revisitDays: 90, revisitStrict: false, precautions: '', avgPriceMin: 10, avgPriceMax: 20,
      isActive: true, popularityScore: 9, createdBy: 'admin', createdAt: '2026-11-18 09:00:00', aliasCount: 1, hospitalCount: 2, aliases: [{ id: 1, alias: 'botox', lang: 'en' }],
    });
    expect(r.body.items[1]).toMatchObject({ id: 11, isActive: false, defaultImageUrl: null, revisitDays: null, revisitStrict: false, createdAt: null, aliasCount: 0, aliases: [] });
    expect(r.body.items[0]).not.toHaveProperty('reviewCount');
  });

  it('only SELECT statements, no SELECT *, and the setting key is a parameter', async () => {
    await get(SUPER).expect(200);
    expect(calls).toHaveLength(7);
    for (const c of calls) {
      expect(c.sql.trim()).toMatch(/^SELECT /);
      expect(c.sql).not.toMatch(/SELECT\s+\*/i);
      expect(c.sql).not.toMatch(/INSERT|UPDATE|DELETE|FOR UPDATE/);
    }
    const setting = calls.find((c) => c.sql.includes('gangnamunni_settings'));
    expect(setting?.values).toEqual(['tc_table_mode']);
    expect(calls.some((c) => c.sql.includes('treatment_reviews'))).toBe(false);
  });

  it('missing optional columns / table / setting (no migration or no grant): flags false, page still served', async () => {
    failing = {
      'default_image_url': dbError('ER_BAD_FIELD_ERROR', 1054, "Unknown column 'default_image_url'"),
      'revisit_days': dbError('ER_COLUMNACCESS_DENIED_ERROR', 1143),
      'gangnamunni_treatment_conflicts': dbError('ER_NO_SUCH_TABLE', 1146),
      'gangnamunni_settings': dbError('ER_TABLEACCESS_DENIED_ERROR', 1142),
    };
    const r = await get(SUPER).expect(200);
    expect(r.body.schema).toEqual({ revisit: false, defaultImage: false, conflicts: false });
    expect(r.body.tableMode).toBe('excel');
    expect(r.body.conflicts).toEqual([]);
    expect(r.body.items[0]).toMatchObject({ defaultImageUrl: null, revisitDays: null, revisitStrict: false });
    expect(r.body.stats.total).toBe(2);
  });

  it('no stored table mode -> excel', async () => {
    tableModeRows = [];
    expect((await get(SUPER).expect(200)).body.tableMode).toBe('excel');
  });

  it('a required statement failing, or an optional one failing for another reason -> 500 server_error', async () => {
    failing = { 'FROM gangnamunni_treatment_aliases': dbError('ER_TABLEACCESS_DENIED_ERROR', 1142) };
    expect((await get(SUPER).expect(500)).body).toEqual({ error: 'server_error' });
    failing = { 'gangnamunni_treatment_conflicts': dbError('ER_LOCK_WAIT_TIMEOUT', 1205) };
    expect((await get(SUPER).expect(500)).body).toEqual({ error: 'server_error' });
  });

  it('not signed in -> 401; hospital role and plain admin -> 403; database untouched', async () => {
    expect((await get().expect(401)).body).toEqual({ error: 'not_logged_in' });
    expect((await get(HOSPITAL).expect(403)).body).toEqual({ error: 'forbidden' });
    expect((await get(PLAIN).expect(403)).body).toEqual({ error: 'forbidden' });
    expect(dbStub.query).not.toHaveBeenCalled();
  });

  it('switch off (or not exactly 1) -> 404 for everybody, PHP and database untouched', async () => {
    for (const v of ['0', 'true', undefined]) {
      if (v === undefined) delete process.env.ADMIN_TREATMENTS_ENABLED;
      else process.env.ADMIN_TREATMENTS_ENABLED = v;
      expect((await get(SUPER).expect(404)).body).toEqual({ error: 'not_found' });
      await get().expect(404);
    }
    process.env.ADMIN_TREATMENTS_ENABLED = '1';
    process.env.ADMIN_PANEL_ENABLED = '0';
    await get(SUPER).expect(404);
    expect(global.fetch).not.toHaveBeenCalled();
    expect(dbStub.query).not.toHaveBeenCalled();
  });

  it('only GET exists: other methods and sub-paths -> 404 not_found', async () => {
    const h = { Cookie: SUPER, 'X-Requested-With': 'admin-next', 'Content-Type': 'application/json' };
    expect((await request(app.getHttpServer()).post('/v1/treatments').set(h).send({ name: 'x' }).expect(404)).body).toEqual({ error: 'not_found' });
    await request(app.getHttpServer()).put('/v1/treatments/order').set(h).send({ ids: [1] }).expect(404);
    await request(app.getHttpServer()).delete('/v1/treatments/10').set(h).expect(404);
    await get(SUPER, '/v1/treatments/10').expect(404);
    await get(SUPER, '/v1/treatment-categories').expect(404);
    await get(SUPER, '/v1/treatment-conflicts').expect(404);
    expect(dbStub.query).not.toHaveBeenCalled();
  });

  it('/v1/me reports the three switches (treatments on, write and images off)', async () => {
    const r = await get(SUPER, '/v1/me').expect(200);
    expect(r.body.features).toMatchObject({ treatments: true, treatmentsWrite: false, treatmentsImages: false });
    process.env.ADMIN_TREATMENTS_WRITE = '1';
    process.env.ADMIN_TREATMENTS_IMAGES = '1';
    expect((await get(SUPER, '/v1/me').expect(200)).body.features).toMatchObject({ treatments: true, treatmentsWrite: true, treatmentsImages: true });
    process.env.ADMIN_TREATMENTS_ENABLED = '0';
    expect((await get(SUPER, '/v1/me').expect(200)).body.features).toMatchObject({ treatments: false, treatmentsWrite: false, treatmentsImages: false });
    delete process.env.ADMIN_TREATMENTS_ENABLED;
    process.env.ADMIN_TREATMENTS_WRITE = '1';
    delete process.env.ADMIN_TREATMENTS_IMAGES;
    expect((await get(SUPER, '/v1/me').expect(200)).body.features).toMatchObject({ treatments: false, treatmentsWrite: false, treatmentsImages: false });
  });
});
