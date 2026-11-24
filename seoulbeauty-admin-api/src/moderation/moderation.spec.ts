import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB } from '../db/db.module';
import { ModerationModule } from './moderation.module';
import { normalizeModerationQuery, postRevision, reviewRevision, safeImageUrl, toPostItem, toReviewItem } from './moderation.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const OTHER_ROLE = 'PHPSESSID=editor';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

type Call = { sql: string; values: unknown[] };

describe('admin API: moderation (review list)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const roCalls: Call[] = [];

  const ro = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      roCalls.push({ sql, values });
      if (sql.includes('FROM hospital_reviews r') && sql.includes('SUM(')) return [[{ pending: 2, approved: 1, rejected: 0 }]];
      if (sql.includes('FROM hospital_reviews r')) {
        const row = { id: 1, hospital_id: 12, hospital_name: 'H12', user_name: 'Min', title: 'T', content: 'C', rating: 5, treatments: 'Botox, Filler', doctor_name: 'Kim', booking_id: 55, review_status: 'pending', is_active: 0, created_at: '2026-11-20 10:00:00', updated_at: '2026-11-20 10:00:00' };
        return [[row]];
      }
      if (sql.includes('FROM review_images')) return [[{ review_id: 1, image_url: '/uploads/a.jpg' }, { review_id: 1, image_url: 'javascript:alert(1)' }]];
      if (sql.includes('FROM gangnamunni_community_posts p') && sql.includes('SUM(')) return [[{ pending: 1, approved: 0, rejected: 0 }]];
      if (sql.includes('FROM gangnamunni_community_posts p')) return [[{ id: 9, category: '자유', author_name: 'Han', title: 'P', content: 'body', photos: '["/u/a.png","javascript:x"]', status: 'pending', created_at: '2026-11-20 09:00:00' }]];
      if (sql.includes('FROM gangnamunni_hospitals')) return [[{ id: 12, name: 'A' }]];
      throw new Error(`unexpected ro sql: ${sql}`);
    }),
  };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      if (cookie.includes('editor')) return json({ loggedIn: true, admin: admin('editor') });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_MODERATION_ENABLED = '1';
    roCalls.length = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, ModerationModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => {
    await app.close();
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_MODERATION_ENABLED']) delete process.env[k];
  });

  const get = (url: string, cookie: string | null = SUPER) => {
    const r = request(app.getHttpServer()).get(url);
    return cookie ? r.set('Cookie', cookie) : r;
  };
  const reviewSelect = () => roCalls.find((c) => c.sql.includes('FROM hospital_reviews r') && !c.sql.includes('SUM('))!;

  it('answers 404 while the page switch is off, 401 without a session, 403 for a role outside the panel', async () => {
    expect((await get('/v1/moderation/reviews', null)).status).toBe(401);
    expect((await get('/v1/moderation/reviews', OTHER_ROLE)).status).toBe(403);
    process.env.ADMIN_MODERATION_ENABLED = '0';
    expect((await get('/v1/moderation/reviews')).status).toBe(404);
    expect((await get('/v1/moderation/hospitals')).status).toBe(404);
    expect(roCalls).toHaveLength(0);
  });

  it('lists reviews for a super admin without narrowing and with only safe picture addresses', async () => {
    const res = await get('/v1/moderation/reviews');
    expect(res.status).toBe(200);
    expect(res.body.counts).toEqual({ pending: 2, approved: 1, rejected: 0 });
    expect(res.body.items[0]).toMatchObject({ id: 1, hasBooking: true, state: 'pending', author: 'Min', treatments: ['Botox', 'Filler'], images: ['/uploads/a.jpg'] });
    expect(res.body.items[0].revision).toMatch(/^[a-f0-9]{64}$/);
    expect(reviewSelect().sql).not.toContain('r.hospital_id IN');
  });

  it('narrows a hospital account to its own hospital and to reviews that belong to a booking', async () => {
    await get('/v1/moderation/reviews', HOSPITAL);
    const q = reviewSelect();
    expect(q.sql).toContain('r.hospital_id IN (?)');
    expect(q.sql).toContain('COALESCE(r.booking_id, 0) > 0');
    expect(q.values[0]).toEqual([12]);
  });

  it('returns an empty page for a hospital filter outside the scope and never reaches the database', async () => {
    const res = await get('/v1/moderation/reviews?hospitalId=13', HOSPITAL);
    expect(res.status).toBe(200);
    expect(res.body.items).toEqual([]);
    expect(roCalls).toHaveLength(0);
  });

  it('puts the filters in the query as parameters, with LIKE wildcards escaped', async () => {
    await get('/v1/moderation/reviews?status=approved&search=50%25_off&hospitalId=12&page=2');
    const q = reviewSelect();
    expect(q.sql).toContain("r.review_status = 'published' AND r.is_active = 1");
    expect(q.values).toEqual(expect.arrayContaining(['%50\\%\\_off%', 12]));
    expect(q.values.slice(-2)).toEqual([21, 20]);
    expect(q.sql).not.toContain('50');
  });

  it('lists community posts for a super admin only; a hospital account gets 403 and the database is not reached', async () => {
    expect((await get('/v1/moderation/community', HOSPITAL)).status).toBe(403);
    expect(roCalls).toHaveLength(0);
    expect((await get('/v1/moderation/community', null)).status).toBe(401);
    const res = await get('/v1/moderation/community?status=approved&search=a_b');
    expect(res.status).toBe(200);
    expect(res.body.counts).toEqual({ pending: 1, approved: 0, rejected: 0 });
    expect(res.body.items[0]).toMatchObject({ id: 9, author: 'Han', state: 'pending', images: ['/u/a.png'] });
    const q = roCalls.find((c) => c.sql.includes('FROM gangnamunni_community_posts p') && !c.sql.includes('SUM('))!;
    expect(q.sql).toContain('p.status = ?');
    expect(q.values).toEqual(expect.arrayContaining(['active', '%a\\_b%']));
    process.env.ADMIN_MODERATION_ENABLED = '0';
    expect((await get('/v1/moderation/community')).status).toBe(404);
  });

  it('lists the hospitals of the caller', async () => {
    expect((await get('/v1/moderation/hospitals')).body).toEqual({ items: [{ id: 12, name: 'A' }] });
  });
});

describe('moderation logic', () => {
  it('normalizes the query', () => {
    expect(normalizeModerationQuery({ status: 'x', page: '0', hospitalId: 'a' })).toEqual({ status: 'pending', search: '', hospitalId: 0, page: 1 });
    expect(normalizeModerationQuery({ status: 'rejected', search: ' hi ', hospitalId: '12', page: '3' })).toEqual({ status: 'rejected', search: 'hi', hospitalId: 12, page: 3 });
    expect(normalizeModerationQuery({ search: 'x'.repeat(300) }).search).toHaveLength(100);
  });
  it('shows only plain image addresses', () => {
    expect(safeImageUrl('/uploads/a.jpg')).toBe('/uploads/a.jpg');
    expect(safeImageUrl('https://x.test/a.jpg')).toBe('https://x.test/a.jpg');
    for (const bad of ['//evil.test/a.jpg', '/\\evil.test/a.jpg', 'javascript:alert(1)', 'data:image/png;base64,AA', '/a b.jpg', '']) expect(safeImageUrl(bad)).toBe('');
  });
  it('maps stored states and clamps the rating', () => {
    const base = { id: 1, hospital_id: 1, title: '', content: '', rating: 9, treatments: '', doctor_name: '', user_name: 'x', updated_at: '' };
    expect(toReviewItem({ ...base, review_status: 'pending', is_active: 0 }, []).state).toBe('pending');
    expect(toReviewItem({ ...base, review_status: 'published', is_active: 1 }, []).state).toBe('approved');
    expect(toReviewItem({ ...base, review_status: 'published', is_active: 0 }, []).state).toBe('rejected');
    expect(toReviewItem({ ...base, review_status: 'pending', is_active: 0 }, []).rating).toBe(5);
  });
  it('maps a community post and changes its revision with the content', () => {
    const row = { id: 1, category: 'c', title: 't', content: 'b', photos: 'not json', status: 'rejected', author_name: 'x' };
    expect(toPostItem(row)).toMatchObject({ state: 'rejected', images: [], author: 'x' });
    expect(toPostItem({ ...row, status: 'active' }).state).toBe('approved');
    expect(postRevision(row)).not.toBe(postRevision({ ...row, content: 'c' }));
  });
  it('changes the revision when the content changes', () => {
    const r = { hospital_id: 1, title: 'a', content: 'b', rating: 5, treatments: '', doctor_name: '', updated_at: 't' };
    expect(reviewRevision(r)).toBe(reviewRevision({ ...r }));
    expect(reviewRevision(r)).not.toBe(reviewRevision({ ...r, content: 'c' }));
  });
});
