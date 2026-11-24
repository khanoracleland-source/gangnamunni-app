import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { ModerationModule } from './moderation.module';
import { normalizeModerationQuery, parseItemId, parseRevision, postRevision, reviewRevision, safeImageUrl, toPostItem, toReviewItem } from './moderation.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const OTHER_ROLE = 'PHPSESSID=editor';
const MP = { 'X-Requested-With': 'admin-next' };
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

type Call = { sql: string; values: unknown[] };
type Post = { id: number; category: string; title: string; content: string; photos: string; status: string; author_name: string };
type Review = { id: number; hospital_id: number; booking_id: number | null; review_status: string; is_active: number; title: string; content: string; rating: number; treatments: string; doctor_name: string; user_name: string; updated_at: string };

describe('admin API: moderation (review list)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const roCalls: Call[] = [];
  const rwCalls: Call[] = [];
  const events: string[] = [];
  let reviews: Review[];
  let posts: Post[];

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

  const conn = {
    beginTransaction: jest.fn(async () => { events.push('begin'); }),
    commit: jest.fn(async () => { events.push('commit'); }),
    rollback: jest.fn(async () => { events.push('rollback'); }),
    release: jest.fn(() => undefined),
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      rwCalls.push({ sql, values });
      if (sql.includes('FROM hospital_reviews WHERE id = ? LIMIT 1 FOR UPDATE')) return [reviews.filter((r) => r.id === values[0])];
      if (sql.startsWith('UPDATE hospital_reviews SET review_status')) {
        const r = reviews.find((x) => x.id === values[1])!;
        r.review_status = 'published';
        r.is_active = values[0] as number;
        return [{ affectedRows: 1 }];
      }
      if (sql.startsWith('UPDATE gangnamunni_hospitals SET')) return [{ affectedRows: 1 }];
      if (sql.includes('FROM gangnamunni_community_posts WHERE id = ? LIMIT 1 FOR UPDATE')) return [posts.filter((p) => p.id === values[0])];
      if (sql.startsWith('UPDATE gangnamunni_community_posts SET status')) {
        posts.find((p) => p.id === values[1])!.status = values[0] as string;
        return [{ affectedRows: 1 }];
      }
      throw new Error(`unexpected rw sql: ${sql}`);
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
    process.env.ADMIN_MODERATION_WRITE = '1';
    roCalls.length = 0;
    rwCalls.length = 0;
    events.length = 0;
    posts = [{ id: 9, category: '자유', title: 'P', content: 'body', photos: '["/u/a.png"]', status: 'pending', author_name: 'Han' }];
    const base = { review_status: 'pending', is_active: 0, title: 'T', content: 'C', rating: 5, treatments: 'Botox, Filler', doctor_name: 'Kim', user_name: 'Min', updated_at: '2026-11-20 10:00:00' };
    reviews = [
      { id: 1, hospital_id: 12, booking_id: 55, ...base },
      { id: 2, hospital_id: 12, booking_id: null, ...base },
      { id: 3, hospital_id: 13, booking_id: 56, ...base },
    ];
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }, { provide: DB_RW, useValue: { get: () => ({ getConnection: async () => conn }) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, ModerationModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => {
    await app.close();
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_MODERATION_ENABLED', 'ADMIN_MODERATION_WRITE']) delete process.env[k];
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

  const post = (url: string, body: unknown, cookie: string | null = SUPER, headers: Record<string, string> = MP) => {
    const r = request(app.getHttpServer()).post(url).set(headers).send(body as object);
    return cookie ? r.set('Cookie', cookie) : r;
  };
  const rev = (id: number) => reviewRevision(reviews.find((r) => r.id === id)!);

  it('refuses a write without the marker header or the write switch', async () => {
    expect((await post('/v1/moderation/reviews/1/approve', { revision: rev(1) }, SUPER, {})).status).toBe(403);
    process.env.ADMIN_MODERATION_WRITE = '0';
    expect((await post('/v1/moderation/reviews/1/approve', { revision: rev(1) })).status).toBe(404);
    expect(rwCalls).toHaveLength(0);
  });

  it('approves a review, refreshes the hospital figures and never touches the author name', async () => {
    const res = await post('/v1/moderation/reviews/1/approve', { revision: rev(1) });
    expect(res.status).toBe(200);
    expect(res.body).toEqual({ id: 1, state: 'approved' });
    expect(reviews[0]).toMatchObject({ review_status: 'published', is_active: 1, user_name: 'Min' });
    expect(rwCalls.some((c) => /^UPDATE gangnamunni_hospitals SET\s+review_count/.test(c.sql))).toBe(true);
    expect(rwCalls.every((c) => !/user_name|points?/i.test(c.sql.replace('FOR UPDATE', '')) || c.sql.startsWith('SELECT'))).toBe(true);
    expect(events).toEqual(['begin', 'commit']);
  });

  it('rejects a review: published with the active flag off', async () => {
    expect((await post('/v1/moderation/reviews/1/reject', { revision: rev(1) })).body).toEqual({ id: 1, state: 'rejected' });
    expect(reviews[0]).toMatchObject({ review_status: 'published', is_active: 0 });
  });

  it('hides reviews outside the hospital account scope: other hospital or no booking answer 404 and nothing is written', async () => {
    expect((await post('/v1/moderation/reviews/3/approve', { revision: rev(3) }, HOSPITAL)).status).toBe(404);
    expect((await post('/v1/moderation/reviews/2/approve', { revision: rev(2) }, HOSPITAL)).status).toBe(404);
    expect(rwCalls.every((c) => c.sql.startsWith('SELECT'))).toBe(true);
    expect((await post('/v1/moderation/reviews/1/approve', { revision: rev(1) }, HOSPITAL)).status).toBe(200);
  });

  it('answers 409 for an edited or already decided review and 400 for a bad revision', async () => {
    expect((await post('/v1/moderation/reviews/1/approve', { revision: 'a'.repeat(64) })).body).toMatchObject({ error: 'changed' });
    expect((await post('/v1/moderation/reviews/1/approve', { revision: 'nope' })).status).toBe(400);
    expect((await post('/v1/moderation/reviews/1/approve', {})).status).toBe(400);
    const r = rev(1);
    await post('/v1/moderation/reviews/1/approve', { revision: r });
    expect((await post('/v1/moderation/reviews/1/reject', { revision: rev(1) })).body).toMatchObject({ error: 'already_done' });
    expect((await post('/v1/moderation/reviews/1/maybe', { revision: r })).status).toBe(400);
    expect((await post('/v1/moderation/reviews/99/approve', { revision: r })).status).toBe(404);
  });

  it('decides a community post once', async () => {
    const revision = postRevision(posts[0]);
    expect((await post('/v1/moderation/community/9/approve', { revision })).body).toEqual({ id: 9, state: 'approved' });
    expect(posts[0].status).toBe('active');
    expect((await post('/v1/moderation/community/9/reject', { revision })).body).toMatchObject({ error: 'already_done' });
  });

  it('keeps community decisions for a super admin: a hospital account gets 403 and nothing is written', async () => {
    expect((await post('/v1/moderation/community/9/approve', { revision: postRevision(posts[0]) }, HOSPITAL)).status).toBe(403);
    expect((await post('/v1/moderation/community/9/approve', { revision: postRevision(posts[0]) }, SUPER, {})).status).toBe(403);
    process.env.ADMIN_MODERATION_WRITE = '0';
    expect((await post('/v1/moderation/community/9/approve', { revision: postRevision(posts[0]) })).status).toBe(404);
    expect(rwCalls).toHaveLength(0);
    expect(posts[0].status).toBe('pending');
  });

  it('answers 409 for an edited post and 404 for a missing one', async () => {
    expect((await post('/v1/moderation/community/9/reject', { revision: 'b'.repeat(64) })).body).toMatchObject({ error: 'changed' });
    expect((await post('/v1/moderation/community/99/reject', { revision: postRevision(posts[0]) })).status).toBe(404);
    expect(posts[0].status).toBe('pending');
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
  it('parses ids and revisions strictly', () => {
    expect(parseItemId('12')).toBe(12);
    expect(parseItemId('0')).toBe(0);
    expect(parseItemId('1e3')).toBe(0);
    expect(parseRevision({ revision: 'a'.repeat(64) })).toBe('a'.repeat(64));
    expect(parseRevision({ revision: 'A'.repeat(64) })).toBeNull();
    expect(parseRevision(null)).toBeNull();
  });
  it('changes the revision when the content changes', () => {
    const r = { hospital_id: 1, title: 'a', content: 'b', rating: 5, treatments: '', doctor_name: '', updated_at: 't' };
    expect(reviewRevision(r)).toBe(reviewRevision({ ...r }));
    expect(reviewRevision(r)).not.toBe(reviewRevision({ ...r, content: 'c' }));
  });
});
