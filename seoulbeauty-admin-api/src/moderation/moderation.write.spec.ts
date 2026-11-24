import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { ModerationModule } from './moderation.module';
import { parsePostEdit, parseReviewEdit, photosOf, postRevision, remainingPhotos, reviewRevision } from './moderation.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const OTHER_ROLE = 'PHPSESSID=editor';
const MP = { 'X-Requested-With': 'admin-next' };
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

type Call = { sql: string; values: unknown[] };
type Review = { id: number; hospital_id: number; booking_id: number | null; review_status: string; is_active: number; title: string; content: string; rating: number; treatments: string; doctor_name: string; updated_at: string };
type Post = { id: number; category: string; title: string; content: string; photos: string; status: string };

describe('admin API: moderation (edit and delete)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  const events: string[] = [];
  let reviews: Review[];
  let posts: Post[];
  let failOn: ((sql: string) => Error | null) | null;
  let rwConfigured: boolean;

  const ro = {
    query: jest.fn(async (sql: string) => {
      if (sql.includes('FROM gangnamunni_hospital_treatments ht')) return [[{ name: 'Botox' }, { name: 'Filler' }, { name: 'Laser' }]];
      if (sql.includes('SELECT DISTINCT category')) return [[{ category: '일상' }, { category: '질문' }]];
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
      const err = failOn?.(sql);
      if (err) throw err;
      if (sql.includes('FROM hospital_reviews WHERE id = ? LIMIT 1 FOR UPDATE')) return [reviews.filter((r) => r.id === values[0])];
      if (sql.includes('FROM gangnamunni_community_posts WHERE id = ? LIMIT 1 FOR UPDATE')) return [posts.filter((p) => p.id === values[0])];
      return [{ affectedRows: 1 }];
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
    Object.assign(process.env, { ADMIN_PANEL_ENABLED: '1', ADMIN_HOSPITALS_ENABLED: '1', ADMIN_MODERATION_ENABLED: '1', ADMIN_MODERATION_WRITE: '1', ADMIN_MODERATION_EDIT: '1', ADMIN_MODERATION_DELETE: '1' });
    rwCalls.length = 0;
    events.length = 0;
    failOn = null;
    rwConfigured = true;
    const base = { review_status: 'published', is_active: 1, title: 'T', content: 'C', rating: 5, treatments: 'Botox, Filler', doctor_name: 'Kim', updated_at: '2026-11-20 10:00:00' };
    reviews = [
      { id: 1, hospital_id: 12, booking_id: 55, ...base },
      { id: 2, hospital_id: 12, booking_id: null, ...base },
      { id: 3, hospital_id: 13, booking_id: 56, ...base },
    ];
    posts = [{ id: 9, category: '일상', title: 'P', content: 'body', photos: '["/u/a.png","/u/b.png"]', status: 'active' }];
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }, { provide: DB_RW, useValue: { get: () => (rwConfigured ? { getConnection: async () => conn } : null) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, ModerationModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => {
    await app.close();
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_MODERATION_ENABLED', 'ADMIN_MODERATION_WRITE', 'ADMIN_MODERATION_EDIT', 'ADMIN_MODERATION_DELETE']) delete process.env[k];
  });

  const send = (method: 'patch' | 'delete', url: string, body: unknown, cookie: string | null = SUPER, headers: Record<string, string> = MP) => {
    const r = request(app.getHttpServer())[method](url).set(headers);
    if (cookie) r.set('Cookie', cookie);
    return r.send(body as object);
  };
  const get = (url: string, cookie = SUPER) => request(app.getHttpServer()).get(url).set('Cookie', cookie);
  const reviewBody = (over: Record<string, unknown> = {}, id = 1) => ({ revision: reviewRevision(reviews.find((r) => r.id === id)!), title: 'New title', content: 'New text', rating: 4, treatments: ['Botox'], removeImages: [], ...over });
  const postBody = (over: Record<string, unknown> = {}) => ({ revision: postRevision(posts[0]), category: '질문', title: 'New', content: 'New text', removeImages: [], ...over });
  const wrote = (re: RegExp) => rwCalls.filter((c) => re.test(c.sql));

  describe('switches, session and reach', () => {
    it('answers 404 while the edit or delete switch is off, whatever the other switches say', async () => {
      process.env.ADMIN_MODERATION_EDIT = '0';
      expect((await send('patch', '/v1/moderation/reviews/1', reviewBody())).status).toBe(404);
      expect((await send('patch', '/v1/moderation/community/9', postBody())).status).toBe(404);
      process.env.ADMIN_MODERATION_DELETE = '0';
      expect((await send('delete', '/v1/moderation/reviews/1', { revision: 'x' })).status).toBe(404);
      expect((await send('delete', '/v1/moderation/community/9', { revision: 'x' })).status).toBe(404);
      process.env.ADMIN_MODERATION_ENABLED = '0';
      process.env.ADMIN_MODERATION_EDIT = '1';
      process.env.ADMIN_MODERATION_DELETE = '1';
      expect((await send('patch', '/v1/moderation/reviews/1', reviewBody())).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
    });

    it('reports both switches on /v1/me and keeps them independent of the decision switch', async () => {
      expect((await get('/v1/me')).body.features).toMatchObject({ moderation: true, moderationWrite: true, moderationEdit: true, moderationDelete: true });
      process.env.ADMIN_MODERATION_WRITE = '0';
      process.env.ADMIN_MODERATION_DELETE = '0';
      expect((await get('/v1/me')).body.features).toMatchObject({ moderationWrite: false, moderationEdit: true, moderationDelete: false });
      process.env.ADMIN_MODERATION_ENABLED = '0';
      expect((await get('/v1/me')).body.features).toMatchObject({ moderation: false, moderationEdit: false, moderationDelete: false });
    });

    it('needs the CSRF marker, a session and a role of the panel', async () => {
      expect((await send('patch', '/v1/moderation/reviews/1', reviewBody(), SUPER, {})).status).toBe(403);
      expect((await send('delete', '/v1/moderation/reviews/1', { revision: 'x' }, SUPER, {})).status).toBe(403);
      expect((await send('patch', '/v1/moderation/reviews/1', reviewBody(), null)).status).toBe(401);
      expect((await send('patch', '/v1/moderation/reviews/1', reviewBody(), OTHER_ROLE)).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
    });

    it('lets a hospital account change only booked reviews of its own hospital', async () => {
      expect((await send('patch', '/v1/moderation/reviews/1', reviewBody(), HOSPITAL)).status).toBe(200);
      expect((await send('patch', '/v1/moderation/reviews/2', reviewBody({}, 2), HOSPITAL)).status).toBe(404);
      expect((await send('patch', '/v1/moderation/reviews/3', reviewBody({}, 3), HOSPITAL)).status).toBe(404);
      expect((await send('delete', '/v1/moderation/reviews/2', { revision: reviewRevision(reviews[1]) }, HOSPITAL)).status).toBe(404);
      expect((await send('delete', '/v1/moderation/reviews/3', { revision: reviewRevision(reviews[2]) }, HOSPITAL)).status).toBe(404);
      expect(wrote(/^DELETE/)).toHaveLength(0);
      expect(events.filter((e) => e === 'commit')).toHaveLength(1);
    });

    it('lets a super admin change a review that has no booking', async () => {
      expect((await send('patch', '/v1/moderation/reviews/2', reviewBody({}, 2))).status).toBe(200);
    });

    it('keeps community posts to a super admin: 403 for a hospital account on every community route', async () => {
      expect((await send('patch', '/v1/moderation/community/9', postBody(), HOSPITAL)).status).toBe(403);
      expect((await send('delete', '/v1/moderation/community/9', { revision: postRevision(posts[0]) }, HOSPITAL)).status).toBe(403);
      expect((await get('/v1/moderation/community/categories', HOSPITAL)).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
    });

    it('answers 503 write_unavailable when the write pool is not configured', async () => {
      rwConfigured = false;
      const res = await send('patch', '/v1/moderation/reviews/1', reviewBody());
      expect(res.status).toBe(503);
      expect(res.body.error).toBe('write_unavailable');
    });
  });

  describe('pickers', () => {
    it('lists the treatments a hospital offers, only for a hospital the caller may see', async () => {
      expect((await get('/v1/moderation/hospitals/12/treatments')).body).toEqual({ items: ['Botox', 'Filler', 'Laser'] });
      expect((await get('/v1/moderation/hospitals/12/treatments', HOSPITAL)).body.items).toHaveLength(3);
      expect((await get('/v1/moderation/hospitals/13/treatments', HOSPITAL)).body).toEqual({ items: [] });
      expect((await get('/v1/moderation/hospitals/12/treatments', OTHER_ROLE)).status).toBe(403);
    });

    it('lists the categories posts use for a super admin', async () => {
      expect((await get('/v1/moderation/community/categories')).body).toEqual({ items: ['일상', '질문'] });
    });
  });

  describe('editing a review', () => {
    it('writes title, text, rating and treatments with parameters and refreshes the hospital when a shown review changes rating', async () => {
      const res = await send('patch', '/v1/moderation/reviews/1', reviewBody({ treatments: ['Botox', 'Laser'] }));
      expect(res.status).toBe(200);
      expect(res.body).toEqual({ id: 1 });
      const upd = wrote(/^UPDATE hospital_reviews SET title/)[0];
      expect(upd.values).toEqual(['New title', 'New text', 4, 'Botox, Laser', 1]);
      expect(upd.sql).not.toMatch(/user_name|review_status|is_active|booking_id|hospital_id/);
      expect(wrote(/^UPDATE gangnamunni_hospitals/)[0].values).toEqual([12, 12, 12]);
      expect(events).toEqual(['begin', 'commit']);
    });

    it('does not refresh the hospital when the rating stays or the review is not shown', async () => {
      await send('patch', '/v1/moderation/reviews/1', reviewBody({ rating: 5 }));
      expect(wrote(/^UPDATE gangnamunni_hospitals/)).toHaveLength(0);
      reviews[0].is_active = 0;
      await send('patch', '/v1/moderation/reviews/1', reviewBody({ rating: 2 }));
      expect(wrote(/^UPDATE gangnamunni_hospitals/)).toHaveLength(0);
    });

    it('removes the listed pictures of that review only', async () => {
      await send('patch', '/v1/moderation/reviews/1', reviewBody({ removeImages: ['/uploads/a.jpg'] }));
      const del = wrote(/^DELETE FROM review_images/)[0];
      expect(del.sql).toContain('review_id = ? AND image_url IN (?)');
      expect(del.values).toEqual([1, ['/uploads/a.jpg']]);
    });

    it.each([
      ['empty title', { title: '  ' }, 'title', 'required'],
      ['control character in text', { content: 'a\u0000b' }, 'content', 'bad_format'],
      ['rating zero', { rating: 0 }, 'rating', 'range'],
      ['rating text', { rating: '5' }, 'rating', 'range'],
      ['rating fraction', { rating: 4.5 }, 'rating', 'range'],
      ['comma in a treatment', { treatments: ['A, B'] }, 'treatments', 'bad_format'],
      ['too long treatment name', { treatments: ['x'.repeat(51)] }, 'treatments', 'too_long'],
      ['too many treatments', { treatments: Array.from({ length: 11 }, (_, i) => `T${i}`) }, 'treatments', 'too_many'],
      ['treatments not a list', { treatments: 'Botox' }, 'treatments', 'invalid'],
      ['removeImages not a list', { removeImages: 'x' }, 'removeImages', 'invalid'],
      ['too long title', { title: 'x'.repeat(101) }, 'title', 'too_long'],
      ['too long text', { content: 'x'.repeat(2001) }, 'content', 'too_long'],
      ['treatment the hospital does not offer', { treatments: ['Botox', 'Hair'] }, 'treatments', 'unknown'],
    ])('refuses %s with 400 and writes nothing', async (_n, over, field, code) => {
      const res = await send('patch', '/v1/moderation/reviews/1', reviewBody(over as Record<string, unknown>));
      expect(res.status).toBe(400);
      expect(res.body.fields[field]).toBe(code);
      expect(wrote(/^UPDATE hospital_reviews SET title|^DELETE/)).toHaveLength(0);
    });

    it('answers 400 without a revision and 409 changed when the review was edited meanwhile', async () => {
      const noRev = await send('patch', '/v1/moderation/reviews/1', { ...reviewBody(), revision: undefined });
      expect(noRev.status).toBe(400);
      const stale = await send('patch', '/v1/moderation/reviews/1', reviewBody({ revision: 'f'.repeat(64) }));
      expect(stale.status).toBe(409);
      expect(stale.body.error).toBe('changed');
      expect(events.at(-1)).toBe('rollback');
      expect(wrote(/^UPDATE hospital_reviews SET title/)).toHaveLength(0);
    });

    it('never refuses an unchanged long title and keeps names already on the review even if the hospital dropped them', async () => {
      reviews[0].title = 'y'.repeat(150);
      reviews[0].treatments = 'Botox, Old one';
      const res = await send('patch', '/v1/moderation/reviews/1', reviewBody({ title: 'y'.repeat(150), treatments: ['Botox', 'Old one'] }));
      expect(res.status).toBe(200);
    });

    it('answers 404 for an id that does not exist and refuses a non-number', async () => {
      expect((await send('patch', '/v1/moderation/reviews/99', reviewBody())).status).toBe(404);
      expect((await send('patch', '/v1/moderation/reviews/abc', reviewBody())).status).toBeGreaterThanOrEqual(400);
    });
  });

  describe('editing a post', () => {
    it('writes category, title and text with parameters and leaves the pictures alone when none is removed', async () => {
      const res = await send('patch', '/v1/moderation/community/9', postBody());
      expect(res.status).toBe(200);
      const upd = wrote(/^UPDATE gangnamunni_community_posts SET category/)[0];
      expect(upd.values).toEqual(['질문', 'New', 'New text', 9]);
      expect(upd.sql).not.toMatch(/photos|author|status/);
    });

    it('writes the remaining pictures as JSON when some are removed', async () => {
      await send('patch', '/v1/moderation/community/9', postBody({ removeImages: ['/u/a.png'] }));
      const upd = wrote(/^UPDATE gangnamunni_community_posts SET category/)[0];
      expect(upd.sql).toContain('photos = ?');
      expect(upd.values).toEqual(['질문', 'New', 'New text', '["/u/b.png"]', 9]);
    });

    it('refuses a category nobody uses, an empty category and unreadable stored pictures when asked to remove one', async () => {
      expect((await send('patch', '/v1/moderation/community/9', postBody({ category: '광고' }))).body.fields.category).toBe('unknown');
      expect((await send('patch', '/v1/moderation/community/9', postBody({ category: ' ' }))).body.fields.category).toBe('required');
      posts[0].photos = 'not json';
      const res = await send('patch', '/v1/moderation/community/9', postBody({ removeImages: ['/u/a.png'], revision: postRevision(posts[0]) }));
      expect(res.status).toBe(400);
      expect(res.body.fields.removeImages).toBe('invalid');
      expect(wrote(/^UPDATE gangnamunni_community_posts/)).toHaveLength(0);
    });

    it('answers 409 changed for a stale revision and 404 for a missing post', async () => {
      expect((await send('patch', '/v1/moderation/community/9', postBody({ revision: 'f'.repeat(64) }))).body.error).toBe('changed');
      expect((await send('patch', '/v1/moderation/community/99', postBody())).status).toBe(404);
    });
  });

  describe('deleting', () => {
    it('removes a review with what hangs on it in one transaction and refreshes the hospital', async () => {
      const res = await send('delete', '/v1/moderation/reviews/1', { revision: reviewRevision(reviews[0]) });
      expect(res.status).toBe(200);
      expect(res.body).toEqual({ id: 1 });
      const deletes = wrote(/^DELETE/).map((c) => c.sql.replace(/\s+/g, ' '));
      expect(deletes[0]).toContain('FROM review_images');
      expect(deletes.at(-1)).toContain('DELETE FROM hospital_reviews WHERE id = ?');
      for (const t of ['review_ai_logs', 'review_reports', 'review_likes', 'review_comments', "content_type = 'review'"]) expect(deletes.some((d) => d.includes(t))).toBe(true);
      expect(wrote(/^DELETE/).every((c) => c.values.includes(1))).toBe(true);
      expect(wrote(/^UPDATE gangnamunni_hospitals/)).toHaveLength(1);
      expect(events).toEqual(['begin', 'commit']);
    });

    it('skips a table the server does not have but stops and rolls back on any other database error', async () => {
      failOn = (sql) => (sql.includes('review_ai_logs') ? Object.assign(new Error('no table'), { errno: 1146 }) : null);
      expect((await send('delete', '/v1/moderation/reviews/1', { revision: reviewRevision(reviews[0]) })).status).toBe(200);
      rwCalls.length = 0;
      events.length = 0;
      failOn = (sql) => (sql.includes('review_reports') ? Object.assign(new Error('denied'), { errno: 1142 }) : null);
      const res = await send('delete', '/v1/moderation/reviews/1', { revision: reviewRevision(reviews[0]) });
      expect(res.status).toBe(500);
      expect(wrote(/^DELETE FROM hospital_reviews/)).toHaveLength(0);
      expect(events.at(-1)).toBe('rollback');
    });

    it('does not delete on a stale revision, without a revision or for an unreachable review', async () => {
      expect((await send('delete', '/v1/moderation/reviews/1', { revision: 'f'.repeat(64) })).body.error).toBe('changed');
      expect((await send('delete', '/v1/moderation/reviews/1', {})).status).toBe(400);
      expect((await send('delete', '/v1/moderation/reviews/99', { revision: 'f'.repeat(64) })).status).toBe(404);
      expect(wrote(/^DELETE/)).toHaveLength(0);
    });

    it('removes a post with its comments, likes and reports in one transaction', async () => {
      const res = await send('delete', '/v1/moderation/community/9', { revision: postRevision(posts[0]) });
      expect(res.status).toBe(200);
      const deletes = wrote(/^DELETE/).map((c) => c.sql.replace(/\s+/g, ' '));
      for (const t of ['gangnamunni_community_comment_threads', 'gangnamunni_community_comment_likes', 'FROM gangnamunni_community_comments WHERE post_id', 'FROM gangnamunni_community_likes WHERE post_id', "content_type = 'community'"]) expect(deletes.some((d) => d.includes(t))).toBe(true);
      expect(deletes.at(-1)).toContain('DELETE FROM gangnamunni_community_posts WHERE id = ?');
      expect(events).toEqual(['begin', 'commit']);
    });

    it('does not delete a post on a stale revision or a missing id', async () => {
      expect((await send('delete', '/v1/moderation/community/9', { revision: 'f'.repeat(64) })).body.error).toBe('changed');
      expect((await send('delete', '/v1/moderation/community/99', { revision: 'f'.repeat(64) })).status).toBe(404);
      expect(wrote(/^DELETE/)).toHaveLength(0);
    });
  });

  describe('parsing', () => {
    it('normalises line breaks, trims and drops duplicate or empty treatments', () => {
      const { value, errors } = parseReviewEdit({ title: ' T ', content: 'a\r\nb', rating: 3, treatments: [' Botox ', 'Botox', ' '], removeImages: ['/a.jpg'] });
      expect(errors).toEqual({});
      expect(value).toEqual({ title: 'T', content: 'a\nb', rating: 3, treatments: ['Botox'], removeImages: ['/a.jpg'] });
    });
    it('treats a missing body as all fields required', () => {
      expect(Object.keys(parseReviewEdit(undefined).errors).sort()).toEqual(['content', 'rating', 'title']);
      expect(Object.keys(parsePostEdit(null).errors).sort()).toEqual(['category', 'content', 'title']);
    });
    it('removes pictures by stored text or by safe address and reports unreadable lists', () => {
      expect(remainingPhotos('["/a","b.png","https://x/c"]', ['/a', 'https://x/c'])).toEqual(['b.png']);
      expect(remainingPhotos('{"a":1}', ['/a'])).toBeNull();
      expect(remainingPhotos('oops', ['/a'])).toBeNull();
      expect(remainingPhotos(null, ['/a'])).toEqual([]);
    });
    it('reads the pictures column as text or as a list the driver already decoded, with the same revision either way for the same list', () => {
      const list = ['/hairwang/a.jpg', '/hairwang/b.jpg'];
      expect(photosOf(JSON.stringify(list))).toEqual(list);
      expect(photosOf(list)).toEqual(list);
      expect(photosOf(Buffer.from(JSON.stringify(list)))).toEqual(list);
      expect(photosOf(['javascript:x', 5, '/ok.png'])).toEqual(['/ok.png']);
      expect(photosOf(null)).toEqual([]);
      expect(photosOf({ not: 'a list' })).toEqual([]);
      expect(remainingPhotos(list, ['/hairwang/a.jpg'])).toEqual(['/hairwang/b.jpg']);
      const row = { category: 'c', title: 't', content: 'x' };
      expect(postRevision({ ...row, photos: list })).toBe(postRevision({ ...row, photos: list }));
      expect(postRevision({ ...row, photos: list })).not.toBe(postRevision({ ...row, photos: ['/hairwang/b.jpg'] }));
    });
  });
});
