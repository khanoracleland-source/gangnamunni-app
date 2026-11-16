import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MapConfigModule } from './map-config.module';
import { pickMapConfig } from './map-config.service';
import { DB } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const row = (k: string, v: string) => ({ setting_key: k, setting_value: v });

describe('pickMapConfig', () => {
  it('kakao with a single key', () => {
    expect(pickMapConfig([row('map_provider', 'kakao'), row('kakao_map_api_key', ' abc ')])).toEqual({ provider: 'kakao', key: 'abc' });
  });
  it('provider defaults to kakao when the setting is absent', () => {
    expect(pickMapConfig([row('kakao_map_api_key', 'abc')])).toEqual({ provider: 'kakao', key: 'abc' });
  });
  it('kakao without any key -> osm', () => {
    expect(pickMapConfig([row('map_provider', 'kakao')])).toEqual({ provider: 'osm', key: null });
  });
  it('JSON array: one of the keys, blanks skipped, wins over the single key', () => {
    const rows = [row('map_provider', 'kakao'), row('kakao_map_api_keys', '["", "k1", "k2"]'), row('kakao_map_api_key', 'single')];
    expect(pickMapConfig(rows, () => 0)).toEqual({ provider: 'kakao', key: 'k1' });
    expect(pickMapConfig(rows, () => 0.99)).toEqual({ provider: 'kakao', key: 'k2' });
  });
  it('empty array or broken JSON falls back to the single key', () => {
    expect(pickMapConfig([row('kakao_map_api_keys', '[]'), row('kakao_map_api_key', 'single')])).toEqual({ provider: 'kakao', key: 'single' });
    expect(pickMapConfig([row('kakao_map_api_keys', '{oops'), row('kakao_map_api_key', 'single')])).toEqual({ provider: 'kakao', key: 'single' });
  });
  it('google, osm and unknown providers -> osm without a key', () => {
    for (const p of ['google', 'osm', 'other', '']) {
      expect(pickMapConfig([row('map_provider', p), row('kakao_map_api_key', 'abc')])).toEqual({ provider: 'osm', key: null });
    }
  });
  it('no rows -> osm', () => {
    expect(pickMapConfig([])).toEqual({ provider: 'osm', key: null });
  });
});

describe('GET /v1/map-config', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  let rows: unknown[] = [];
  let fail = false;
  const sqls: { sql: string; values: unknown[] }[] = [];
  const dbStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      sqls.push({ sql, values });
      if (fail) throw Object.assign(new Error('SELECT command denied'), { code: 'ER_COLUMNACCESS_DENIED_ERROR', errno: 1143 });
      return [rows];
    }),
  };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      if (cookie.includes('plain')) return json({ loggedIn: true, admin: admin('admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    rows = [row('map_provider', 'kakao'), row('kakao_map_api_key', 'abc')];
    fail = false;
    sqls.length = 0;
    dbStub.query.mockClear();
    @Global() @Module({ providers: [{ provide: DB, useValue: dbStub }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MapConfigModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => { global.fetch = realFetch; delete process.env.ADMIN_PANEL_ENABLED; delete process.env.ADMIN_HOSPITALS_ENABLED; });

  const get = (cookie?: string) => {
    const r = request(app.getHttpServer()).get('/v1/map-config');
    return cookie ? r.set('Cookie', cookie) : r;
  };

  it('super admin and hospital admin get the config, uncacheable, read with a bound fixed key list', async () => {
    for (const cookie of [SUPER, HOSPITAL]) {
      const r = await get(cookie).expect(200);
      expect(r.body).toEqual({ provider: 'kakao', key: 'abc' });
      expect(r.headers['cache-control']).toContain('no-store');
    }
    expect(sqls[0].sql).toContain('FROM gangnamunni_settings');
    expect(sqls[0].values).toEqual(['map_provider', 'kakao_map_api_keys', 'kakao_map_api_key']);
  });

  it('not signed in -> 401, role without the hospitals feature -> 403, DB untouched', async () => {
    await get().expect(401);
    await get('PHPSESSID=plain').expect(403);
    expect(dbStub.query).not.toHaveBeenCalled();
  });

  it('hospitals switch off -> 404 and DB untouched', async () => {
    process.env.ADMIN_HOSPITALS_ENABLED = '0';
    await get(SUPER).expect(404);
    expect(dbStub.query).not.toHaveBeenCalled();
  });

  it('DB error (missing grant) -> 200 osm, and the failure is cached too', async () => {
    fail = true;
    const r = await get(SUPER).expect(200);
    expect(r.body).toEqual({ provider: 'osm', key: null });
    fail = false;
    expect((await get(SUPER).expect(200)).body).toEqual({ provider: 'osm', key: null });
    expect(dbStub.query).toHaveBeenCalledTimes(1);
  });

  it('answer is cached for 5 minutes, then read again', async () => {
    const now = jest.spyOn(Date, 'now');
    const t0 = 1_800_000_000_000;
    now.mockReturnValue(t0);
    await get(SUPER).expect(200);
    rows = [row('map_provider', 'osm')];
    now.mockReturnValue(t0 + 4 * 60_000);
    expect((await get(SUPER)).body).toEqual({ provider: 'kakao', key: 'abc' });
    expect(dbStub.query).toHaveBeenCalledTimes(1);
    now.mockReturnValue(t0 + 5 * 60_000 + 1);
    expect((await get(SUPER)).body).toEqual({ provider: 'osm', key: null });
    expect(dbStub.query).toHaveBeenCalledTimes(2);
    now.mockRestore();
  });
});
