import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { BannersModule } from './banners.module';
import { bannerRevision, normalizeBannerQuery, parseScroll, safeLink, storedImage, toBannerItem, toScroll } from './banners.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const MP = { 'X-Requested-With': 'admin-next' };
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

type Call = { sql: string; values: unknown[] };
type Banner = { id: number; hospital_id: number | null; title: string; subtitle: string | null; image_url: string | null; mypage_image_url: string | null; link_url: string | null; display_order: number; status: string; updated_at: string };

describe('admin API: banners', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  const events: string[] = [];
  const roCalls: Call[] = [];
  let banners: Banner[];
  let scrollRows: Record<string, unknown>[];
  let rwConfigured: boolean;

  const ro = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      roCalls.push({ sql, values });
      if (sql.includes('COUNT(*) AS total')) return [[{ total: 3, visible: '1', hidden: '2' }]];
      if (sql.includes('FROM gangnamunni_ads a LEFT JOIN gangnamunni_hospitals h')) {
        return [banners.map((b) => ({ ...b, hospital_name: b.hospital_id ? 'Seoul Clinic' : null, home_enabled: b.id === 2 ? 0 : 1, created_at: new Date('2026-11-20T01:00:00Z') }))];
      }
      if (sql.includes('FROM gangnamunni_theme_settings')) return [scrollRows];
      throw new Error(`unexpected ro sql: ${sql}`);
    }),
  };

  const conn = {
    beginTransaction: jest.fn(async () => { events.push('begin'); }),
    commit: jest.fn(async () => { events.push('commit'); }),
    rollback: jest.fn(async () => { events.push('rollback'); }),
    release: jest.fn(() => undefined),
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      rwCalls.push({ sql, values });
      if (sql.includes('FROM gangnamunni_ads WHERE id = ? LIMIT 1 FOR UPDATE')) return [banners.filter((b) => b.id === values[0])];
      return [{ affectedRows: 1 }];
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
    Object.assign(process.env, { ADMIN_PANEL_ENABLED: '1', ADMIN_HOSPITALS_ENABLED: '1', ADMIN_BANNERS_ENABLED: '1', ADMIN_BANNERS_WRITE: '1' });
    rwCalls.length = 0;
    events.length = 0;
    roCalls.length = 0;
    rwConfigured = true;
    scrollRows = [];
    banners = [
      { id: 1, hospital_id: 12, title: 'Spring', subtitle: 'sale', image_url: 'uploads/ads/ad_a.jpg', mypage_image_url: null, link_url: 'https://example.com/a', display_order: 1, status: 'approved', updated_at: '2026-11-20 10:00:00' },
      { id: 2, hospital_id: null, title: 'Hidden', subtitle: null, image_url: 'uploads/ads/ad_b.png', mypage_image_url: 'uploads/ads/ad_c.png', link_url: null, display_order: 2, status: 'approved', updated_at: '2026-11-20 10:00:00' },
      { id: 3, hospital_id: 12, title: 'Waiting', subtitle: null, image_url: null, mypage_image_url: null, link_url: null, display_order: 3, status: 'pending', updated_at: '2026-11-20 10:00:00' },
    ];
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }, { provide: DB_RW, useValue: { get: () => (rwConfigured ? { getConnection: async () => conn } : null) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, BannersModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => {
    await app.close();
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_BANNERS_ENABLED', 'ADMIN_BANNERS_WRITE']) delete process.env[k];
  });

  const get = (url: string, cookie: string | null = SUPER) => {
    const r = request(app.getHttpServer()).get(url);
    if (cookie) r.set('Cookie', cookie);
    return r;
  };
  const send = (method: 'put' | 'post' | 'delete', url: string, body: unknown, cookie: string | null = SUPER, headers: Record<string, string> = MP) => {
    const r = request(app.getHttpServer())[method](url).set(headers);
    if (cookie) r.set('Cookie', cookie);
    return r.send(body as object);
  };
  const wrote = (re: RegExp) => rwCalls.filter((c) => re.test(c.sql));

  describe('switches, session and role', () => {
    it('answers 404 while the page switch is off, and 404 for writes while only the write switch is off', async () => {
      process.env.ADMIN_BANNERS_WRITE = '0';
      expect((await get('/v1/banners')).status).toBe(200);
      expect((await send('put', '/v1/banners/scroll', { enabled: true, intervalSeconds: 5 })).status).toBe(404);
      expect((await send('post', '/v1/banners/1/home', { enabled: true })).status).toBe(404);
      expect((await send('delete', '/v1/banners/1', { revision: 'x' })).status).toBe(404);
      process.env.ADMIN_BANNERS_ENABLED = '0';
      process.env.ADMIN_BANNERS_WRITE = '1';
      expect((await get('/v1/banners')).status).toBe(404);
      expect((await get('/v1/banners/scroll')).status).toBe(404);
      expect((await send('delete', '/v1/banners/1', { revision: 'x' })).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
    });

    it('reports the switches on /v1/me', async () => {
      expect((await get('/v1/me')).body.features).toMatchObject({ banners: true, bannersWrite: true });
      process.env.ADMIN_BANNERS_WRITE = '0';
      expect((await get('/v1/me')).body.features).toMatchObject({ banners: true, bannersWrite: false });
      process.env.ADMIN_BANNERS_ENABLED = '0';
      process.env.ADMIN_BANNERS_WRITE = '1';
      expect((await get('/v1/me')).body.features).toMatchObject({ banners: false, bannersWrite: false });
    });

    it('is for the super admin only: no session 401, a hospital account 403, writes without the marker 403', async () => {
      expect((await get('/v1/banners', null)).status).toBe(401);
      expect((await get('/v1/banners', HOSPITAL)).status).toBe(403);
      expect((await get('/v1/banners/scroll', HOSPITAL)).status).toBe(403);
      expect((await send('delete', '/v1/banners/1', { revision: 'x' }, HOSPITAL)).status).toBe(403);
      expect((await send('post', '/v1/banners/1/home', { enabled: true }, HOSPITAL)).status).toBe(403);
      expect((await send('delete', '/v1/banners/1', { revision: 'x' }, SUPER, {})).status).toBe(403);
      expect((await send('put', '/v1/banners/scroll', { enabled: true, intervalSeconds: 5 }, SUPER, {})).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
    });
  });

  describe('list', () => {
    it('returns the banners with what the site really shows and the counts of the three views', async () => {
      const res = await get('/v1/banners');
      expect(res.status).toBe(200);
      expect(res.body).toMatchObject({ view: 'all', page: 1, perPage: 20, hasMore: false, counts: { all: 3, visible: 1, hidden: 2 } });
      const [a, b, c] = res.body.items;
      expect(a).toMatchObject({ id: 1, hospitalId: 12, hospitalName: 'Seoul Clinic', image: 'uploads/ads/ad_a.jpg', mypageImage: '', status: 'approved', homeEnabled: true, visible: true });
      expect(b).toMatchObject({ id: 2, hospitalId: 0, hospitalName: '', mypageImage: 'uploads/ads/ad_c.png', homeEnabled: false, visible: false });
      expect(c).toMatchObject({ id: 3, status: 'pending', visible: false });
      expect(a.revision).toMatch(/^[a-f0-9]{64}$/);
    });

    it('passes the search as a parameter and ignores a view it does not know', async () => {
      await get('/v1/banners?view=bogus&search=50%25_x&page=2');
      const list = roCalls.find((c) => c.sql.includes('LEFT JOIN gangnamunni_hospitals'))!;
      expect(list.sql).not.toContain('50%');
      expect(list.values).toEqual(['%50\\%\\_x%', '%50\\%\\_x%', '%50\\%\\_x%', 21, 20]);
      expect(list.sql).not.toContain("NOT (COALESCE(a.status");
    });

    it('filters the visible and the hidden banners in SQL', async () => {
      await get('/v1/banners?view=visible');
      await get('/v1/banners?view=hidden');
      const lists = roCalls.filter((c) => c.sql.includes('LEFT JOIN gangnamunni_hospitals'));
      expect(lists[0].sql).toContain("COALESCE(a.status, '') = 'approved'");
      expect(lists[1].sql).toContain("NOT (COALESCE(a.status, '') = 'approved'");
    });
  });

  describe('auto scroll', () => {
    it('reads the site default when nothing is stored, and the stored values otherwise', async () => {
      expect((await get('/v1/banners/scroll')).body).toEqual({ enabled: true, intervalSeconds: 10 });
      scrollRows = [{ setting_key: 'ad_auto_scroll_enabled', setting_value: '0' }, { setting_key: 'ad_auto_scroll_interval', setting_value: '4500' }];
      expect((await get('/v1/banners/scroll')).body).toEqual({ enabled: false, intervalSeconds: 5 });
    });

    it('saves both values in one transaction, the interval in milliseconds', async () => {
      const res = await send('put', '/v1/banners/scroll', { enabled: false, intervalSeconds: 7 });
      expect(res.status).toBe(200);
      expect(res.body).toEqual({ enabled: false, intervalSeconds: 7 });
      expect(events).toEqual(['begin', 'commit']);
      const writes = wrote(/gangnamunni_theme_settings/);
      expect(writes.map((w) => w.values)).toEqual([['ad_auto_scroll_enabled', '0'], ['ad_auto_scroll_interval', '7000']]);
    });

    it('refuses a wrong body without writing', async () => {
      for (const body of [{}, { enabled: 'yes', intervalSeconds: 5 }, { enabled: true, intervalSeconds: 0 }, { enabled: true, intervalSeconds: 61 }, { enabled: true, intervalSeconds: 2.5 }, { enabled: true, intervalSeconds: '5' }]) {
        const res = await send('put', '/v1/banners/scroll', body);
        expect(res.status).toBe(400);
        expect(res.body.error).toBe('invalid');
      }
      expect(rwCalls).toHaveLength(0);
    });

    it('answers 503 when there is no write pool', async () => {
      rwConfigured = false;
      const res = await send('put', '/v1/banners/scroll', { enabled: true, intervalSeconds: 5 });
      expect(res.status).toBe(503);
      expect(res.body.error).toBe('write_unavailable');
    });
  });

  describe('home switch', () => {
    it('shows an approved banner and hides any banner by writing the key the site reads', async () => {
      expect((await send('post', '/v1/banners/1/home', { enabled: false })).body).toEqual({ id: 1, homeEnabled: false });
      expect(wrote(/INSERT INTO gangnamunni_settings/)[0].values).toEqual(['home_banner_enabled_1', '0']);
      rwCalls.length = 0;
      expect((await send('post', '/v1/banners/2/home', { enabled: true })).body).toEqual({ id: 2, homeEnabled: true });
      expect(wrote(/INSERT INTO gangnamunni_settings/)[0].values).toEqual(['home_banner_enabled_2', '1']);
      rwCalls.length = 0;
      expect((await send('post', '/v1/banners/3/home', { enabled: false })).status).toBe(200);
    });

    it('refuses to show a banner that is not approved, and an unknown or malformed one', async () => {
      const res = await send('post', '/v1/banners/3/home', { enabled: true });
      expect(res.status).toBe(409);
      expect(res.body.error).toBe('not_approved');
      expect(events).toContain('rollback');
      expect(wrote(/INSERT INTO gangnamunni_settings/)).toHaveLength(0);
      expect((await send('post', '/v1/banners/99/home', { enabled: true })).status).toBe(404);
      expect((await send('post', '/v1/banners/abc/home', { enabled: true })).status).toBe(404);
      expect((await send('post', '/v1/banners/1/home', { enabled: 'yes' })).status).toBe(400);
      expect((await send('post', '/v1/banners/1/home', {})).status).toBe(400);
    });
  });

  describe('delete', () => {
    it('deletes the banner and its home key in one transaction when the revision matches', async () => {
      const res = await send('delete', '/v1/banners/2', { revision: bannerRevision(banners[1]) });
      expect(res.status).toBe(200);
      expect(res.body).toEqual({ id: 2 });
      expect(events).toEqual(['begin', 'commit']);
      expect(wrote(/DELETE FROM gangnamunni_ads WHERE id = \?/)[0].values).toEqual([2]);
      expect(wrote(/DELETE FROM gangnamunni_settings WHERE setting_key = \?/)[0].values).toEqual(['home_banner_enabled_2']);
    });

    it('refuses a banner edited in the meantime, a missing one and a body without a revision', async () => {
      const stale = bannerRevision(banners[0]);
      banners[0].title = 'Edited elsewhere';
      const changed = await send('delete', '/v1/banners/1', { revision: stale });
      expect(changed.status).toBe(409);
      expect(changed.body.error).toBe('changed');
      expect(wrote(/DELETE/)).toHaveLength(0);
      expect((await send('delete', '/v1/banners/99', { revision: stale })).status).toBe(404);
      const bad = await send('delete', '/v1/banners/1', {});
      expect(bad.status).toBe(400);
      expect(bad.body).toMatchObject({ error: 'invalid', fields: { revision: 'required' } });
    });
  });

  describe('pure parts', () => {
    it('normalizes the list query', () => {
      expect(normalizeBannerQuery({ view: 'hidden', search: ' x ', page: '3' })).toEqual({ view: 'hidden', search: 'x', page: 3 });
      expect(normalizeBannerQuery({ view: ['visible'], page: '-4' })).toEqual({ view: 'all', search: '', page: 1 });
      expect(normalizeBannerQuery(undefined)).toEqual({ view: 'all', search: '', page: 1 });
    });

    it('shows a stored picture only when it is a plain site path, a rooted path or a web address', () => {
      expect(storedImage('uploads/ads/ad_1.jpg')).toBe('uploads/ads/ad_1.jpg');
      expect(storedImage('/hairwang/a.png')).toBe('/hairwang/a.png');
      expect(storedImage('https://cdn.example.com/a.png')).toBe('https://cdn.example.com/a.png');
      for (const bad of ['', '   ', 'javascript:alert(1)', '//evil.example.com/a.png', 'data:image/png;base64,AAAA', 'uploads/../../x', 'a b.png', 'uploads\\x.png', '"><img>', null]) expect(storedImage(bad)).toBe('');
    });

    it('shows a link only when it is a web address, a rooted path or a path of the site', () => {
      for (const ok of ['https://example.com/a?x=1', 'http://example.com', '/next/hospitals/12', 'hospitals/12?tab=a:b']) expect(safeLink(ok)).toBe(ok);
      for (const bad of ['', '   ', 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,<b>', '//evil.example.com', '\\evil', 'https://', 'a b', '"x"', '../x', 'vbscript:x', 'tel:+1', null]) expect(safeLink(bad)).toBe('');
    });

    it('turns the stored scroll values into seconds within the bounds and validates a new body', () => {
      expect(toScroll([])).toEqual({ enabled: true, intervalSeconds: 10 });
      expect(toScroll([{ setting_key: 'ad_auto_scroll_interval', setting_value: '999999' }]).intervalSeconds).toBe(60);
      expect(toScroll([{ setting_key: 'ad_auto_scroll_interval', setting_value: '50' }]).intervalSeconds).toBe(1);
      expect(toScroll([{ setting_key: 'ad_auto_scroll_interval', setting_value: 'abc' }]).intervalSeconds).toBe(10);
      expect(parseScroll({ enabled: true, intervalSeconds: 60 }).errors).toEqual({});
      expect(parseScroll({ enabled: true, intervalSeconds: 61 }).errors).toEqual({ intervalSeconds: 'range' });
    });

    it('derives visible from the status and the home switch, and changes the revision with the content', () => {
      const row = { id: 5, status: 'approved', home_enabled: 1, title: 'a', updated_at: 'x' };
      expect(toBannerItem(row).visible).toBe(true);
      expect(toBannerItem({ ...row, home_enabled: 0 }).visible).toBe(false);
      expect(toBannerItem({ ...row, status: 'pending' }).visible).toBe(false);
      expect(bannerRevision(row)).not.toBe(bannerRevision({ ...row, title: 'b' }));
      expect(bannerRevision(row)).toBe(bannerRevision({ ...row }));
    });
  });
});
