import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { CommunityModule } from '../src/community/community.module';
import {
  chars, commentsInThreadOrder, detailPhotos, listPhotos, normalizeTab, notBlockedSql, parseRegions, previewOf, sanitizeImageUrl, stripTags,
} from '../src/community/community.logic';

const XHR = { 'X-Requested-With': 'XMLHttpRequest' };

describe('community logic (community.php / detail_community.php rules)', () => {
  it('normalizeTab / parseRegions', () => {
    expect(normalizeTab('popular')).toBe('popular');
    expect(normalizeTab('review')).toBe('review');
    expect(normalizeTab('x')).toBe('latest');
    expect(normalizeTab(undefined)).toBe('latest');
    expect(parseRegions(' 서울, ,부산 ,')).toEqual(['서울', '부산']);
    expect(parseRegions('')).toEqual([]);
  });
  it('previewOf strips tags, counts characters, gives the full text only for long posts', () => {
    expect(previewOf('<b>짧은</b> 글')).toEqual({ preview: '짧은 글', fullText: null });
    const long = '가'.repeat(120);
    const r = previewOf(long);
    expect(Array.from(r.preview)).toHaveLength(90);
    expect(r.fullText).toBe(long);
    expect(chars('😀😀😀', 2)).toBe('😀😀');
    expect(stripTags(null)).toBe('');
  });
  it('sanitizeImageUrl follows functions.php', () => {
    expect(sanitizeImageUrl('uploads/a.jpg')).toBe('uploads/a.jpg');
    expect(sanitizeImageUrl('/uploads/a.jpg')).toBe('/uploads/a.jpg');
    expect(sanitizeImageUrl('https://cdn.example.com/a.jpg')).toBe('https://cdn.example.com/a.jpg');
    for (const bad of ['javascript:alert(1)', '//evil.com/a.jpg', 'a b.jpg', '../x.jpg', 'a".jpg', 'data:image/png;base64,AA', '', 5 as any]) {
      expect(sanitizeImageUrl(bad)).toBe('');
    }
  });
  it('listPhotos sanitises, detailPhotos only accepts absolute or root-relative', () => {
    const raw = JSON.stringify(['uploads/a.jpg', '/b.jpg', 'https://x.io/c.jpg', 'javascript:1', 7]);
    expect(listPhotos(raw)).toEqual(['uploads/a.jpg', '/b.jpg', 'https://x.io/c.jpg']);
    expect(detailPhotos(raw)).toEqual(['/b.jpg', 'https://x.io/c.jpg']);
    expect(listPhotos('not json')).toEqual([]);
    expect(listPhotos(null)).toEqual([]);
  });
  it('commentsInThreadOrder: replies follow their parent, orphans become roots', () => {
    const out = commentsInThreadOrder([
      { id: 1, parent_id: 0 }, { id: 2, parent_id: 0 }, { id: 3, parent_id: 1 }, { id: 4, parent_id: 99 }, { id: 5, parent_id: 2 }, { id: 6, parent_id: 1 },
    ]);
    expect(out.map((c) => [c.id, c.parent_id])).toEqual([[1, 0], [3, 1], [6, 1], [2, 0], [5, 2], [4, 0]]);
  });
  it('notBlockedSql', () => {
    expect(notBlockedSql('p.mb_id', [])).toEqual({ sql: '1=1', values: [] });
    expect(notBlockedSql('p.mb_id', ['a', 'b'])).toEqual({ sql: "COALESCE(p.mb_id, '') NOT IN (?,?)", values: ['a', 'b'] });
    expect(() => notBlockedSql('x; DROP', [])).toThrow();
  });
});

describe('community API (mock DB)', () => {
  let app: INestApplication;
  const sql: { q: string; v?: unknown[] }[] = [];
  const realFetch = global.fetch;
  let blocks: { blocker_id: string; blocked_id: string }[] = [];
  let postLikes: string[] = [];
  let commentLikes: { c: number; u: string }[] = [];
  let comments: any[] = [];
  let threads: Record<number, number> = {};
  let failThread = false;
  const post = { id: 7, mb_id: 'owner', author_name: '글쓴이', author_avatar: 'uploads/av.jpg', category: '자유', title: '제목', content: '<i>내용</i>이 있습니다', photos: '["/p1.jpg","uploads/p2.jpg"]', like_count: 3, comment_count: 2, view_count: 10, status: 'active', created_at: '2026-10-01 10:00:00', updated_at: '2026-10-01 10:00:00' };

  const db = {
    query: jest.fn(async (q: string, v?: unknown[]) => {
      sql.push({ q, v });
      if (q.includes('FROM gangnamunni_blocked_users')) return [blocks];
      if (q.includes('COUNT(*) AS cnt FROM gangnamunni_community_posts')) return [[{ cnt: 41 }]];
      if (q.includes('FROM gangnamunni_community_posts p WHERE')) return [[{ ...post }]];
      if (q.includes('COUNT(*) AS cnt FROM hospital_reviews')) return [[{ cnt: 1 }]];
      if (q.includes('FROM hospital_reviews r WHERE')) return [[{ id: 31, title: '후기', content: '좋아요', created_at: '2026-10-02 09:00:00', updated_at: null, author_name: '리뷰어', author_avatar: null, like_count: 1, comment_count: 0, view_count: 4, rating: 4.5, photos: '["uploads/old.jpg"]' }]];
      if (q.includes('FROM review_images')) return [[{ review_id: 31, image_url: 'uploads/before.jpg' }, { review_id: 31, image_url: 'javascript:1' }]];
      if (q.includes('FROM review_likes')) return [[{ lid: 31 }]];
      if (q.includes('SELECT post_id AS lid FROM gangnamunni_community_likes')) return [postLikes.includes(String(v![0])) ? [{ lid: 7 }] : []];
      if (q.startsWith('SELECT * FROM gangnamunni_community_posts WHERE id = ?')) {
        const hidden = (v!.slice(1) as string[]).includes(post.mb_id);
        return [v![0] === 7 && !hidden ? [{ ...post }] : []];
      }
      if (q.includes('COALESCE(t.parent_id, 0) AS parent_id')) return [comments.map((c) => ({ ...c, parent_id: threads[c.id] ?? 0 }))];
      if (q.includes('FROM gangnamunni_community_comment_likes l JOIN')) {
        const user = v![0] as string;
        const by = new Map<number, { cnt: number; liked: number }>();
        for (const l of commentLikes) { const e = by.get(l.c) ?? { cnt: 0, liked: 0 }; e.cnt++; if (l.u === user) e.liked = 1; by.set(l.c, e); }
        return [[...by].map(([c, e]) => ({ comment_id: c, cnt: e.cnt, liked: e.liked }))];
      }
      if (q.startsWith('SELECT id FROM gangnamunni_community_likes')) return [postLikes.includes(String(v![1])) ? [{ id: 1 }] : []];
      if (q.startsWith('INSERT IGNORE INTO gangnamunni_community_likes')) { if (!postLikes.includes(String(v![1]))) postLikes.push(String(v![1])); return [{}]; }
      if (q.startsWith('DELETE FROM gangnamunni_community_likes')) { postLikes = postLikes.filter((u) => u !== String(v![1])); return [{}]; }
      if (q.includes('COUNT(*) AS c FROM gangnamunni_community_likes')) return [[{ c: postLikes.length }]];
      if (q.startsWith('UPDATE gangnamunni_community_posts SET like_count')) return [{ affectedRows: 1 }];
      if (q.startsWith('SELECT id FROM gangnamunni_community_comments WHERE id = ? AND post_id = ?')) return [comments.some((c) => c.id === v![0]) ? [{ id: v![0] }] : []];
      if (q.startsWith('INSERT IGNORE INTO gangnamunni_community_comment_likes')) { if (!commentLikes.some((l) => l.c === v![0] && l.u === v![1])) commentLikes.push({ c: v![0] as number, u: v![1] as string }); return [{}]; }
      if (q.startsWith('DELETE FROM gangnamunni_community_comment_likes')) { commentLikes = commentLikes.filter((l) => !(l.c === v![0] && l.u === v![1])); return [{}]; }
      if (q.includes('COUNT(*) AS c FROM gangnamunni_community_comment_likes')) return [[{ c: commentLikes.filter((l) => l.c === v![0]).length }]];
      if (q.includes('WHERE c.id = ? AND c.post_id = ? AND t.comment_id IS NULL')) return [comments.some((c) => c.id === v![0]) && !(Number(v![0]) in threads) ? [{ id: v![0] }] : []];
      if (q.startsWith('INSERT INTO gangnamunni_community_comments')) {
        const id = 100 + comments.length;
        comments.push({ id, mb_id: v![1], author: v![2], content: v![3], created_at: '2026-10-28 12:00:00' });
        return [{ insertId: id }];
      }
      if (q.startsWith('INSERT INTO gangnamunni_community_comment_threads')) { if (failThread) throw new Error('denied'); threads[v![0] as number] = v![1] as number; return [{}]; }
      if (q.startsWith('UPDATE gangnamunni_community_posts SET comment_count') || q.startsWith('UPDATE gangnamunni_community_posts SET view_count')) return [{ affectedRows: 1 }];
      return [[]];
    }),
  };

  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42, nickname: '김닉' } : c.includes('sid=owner') ? { id: 'owner', no: 1, nickname: '' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: '이름', level: 2, phone: '010', ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: db }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AuthModule, CommunityModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => {
    sql.length = 0; blocks = []; postLikes = []; commentLikes = []; threads = { 2: 1 }; failThread = false;
    comments = [
      { id: 1, mb_id: 'a', author: '첫댓글', content: '안녕', created_at: '2026-10-02 10:00:00' },
      { id: 2, mb_id: 'b', author: '답글러', content: '답글', created_at: '2026-10-02 11:00:00' },
      { id: 3, mb_id: 'bad', author: '차단됨', content: '숨김', created_at: '2026-10-02 12:00:00' },
    ];
  });
  const cookie = 'sid=kim';

  it('list: no-store, paging, sanitised photos, parameterised filters', async () => {
    const r = await request(app.getHttpServer()).get('/v1/community').query({ tab: 'popular', category: "자유' OR 1=1", region: '서울,부산', q: '50%_', page: '2' }).expect(200);
    expect(r.headers['cache-control']).toBe('private, no-store');
    expect(r.body).toMatchObject({ tab: 'popular', page: 2, total: 41, totalPages: 3, regions: ['서울', '부산'], q: '50%_' });
    expect(r.body.items[0]).toMatchObject({ id: 7, kind: 'post', title: '제목', preview: '내용이 있습니다', fullText: null, photos: ['/p1.jpg', 'uploads/p2.jpg'], liked: false });
    const list = sql.find((s) => s.q.includes('FROM gangnamunni_community_posts p WHERE') && s.q.includes('ORDER BY'))!;
    expect(list.q).toContain('ORDER BY like_count DESC, p.created_at DESC');
    expect(list.q).not.toContain('OR 1=1');
    expect(list.v).toEqual(["자유' OR 1=1", '%서울%', '%서울%', '%부산%', '%부산%', '%50\\%\\_%', '%50\\%\\_%', 20, 20]);
  });

  it('list: bad page is 400, unknown tab falls back to latest', async () => {
    await request(app.getHttpServer()).get('/v1/community').query({ page: '0' }).expect(400);
    await request(app.getHttpServer()).get('/v1/community').query({ page: 'abc' }).expect(400);
    const r = await request(app.getHttpServer()).get('/v1/community').query({ tab: 'zzz' }).expect(200);
    expect(r.body.tab).toBe('latest');
    expect(sql.find((s) => s.q.includes('ORDER BY'))!.q).toContain('ORDER BY p.created_at DESC');
  });

  it('list: blocked authors are excluded for the viewer (both directions) and liked flag is the viewer\'s', async () => {
    blocks = [{ blocker_id: 'kim', blocked_id: 'x1' }, { blocker_id: 'x2', blocked_id: 'kim' }];
    postLikes = ['kim'];
    const r = await request(app.getHttpServer()).get('/v1/community').set('Cookie', cookie).expect(200);
    const list = sql.find((s) => s.q.includes('FROM gangnamunni_community_posts p WHERE') && s.q.includes('ORDER BY'))!;
    expect(list.q).toContain("COALESCE(p.mb_id, '') NOT IN (?,?)");
    expect(list.v!.slice(0, 2)).toEqual(['x1', 'x2']);
    expect(r.body.items[0].liked).toBe(true);
  });

  it('list: review tab shows published reviews, rating label, review_images beat photo_urls, review likes', async () => {
    const r = await request(app.getHttpServer()).get('/v1/community').query({ tab: 'review' }).set('Cookie', cookie).expect(200);
    expect(r.body.category).toBe('시술후기');
    expect(r.body.items[0]).toMatchObject({ id: 31, kind: 'review', category: '⭐ 4.5 시술후기', photos: ['uploads/before.jpg'], liked: true });
    const q = sql.find((s) => s.q.includes('FROM hospital_reviews r WHERE') && s.q.includes('ORDER BY'))!.q;
    expect(q).toContain("r.review_status = 'published'");
  });

  it('detail: nested comments in thread order, blocked authors hidden and not counted, view counted only with ?view=1', async () => {
    blocks = [{ blocker_id: 'kim', blocked_id: 'bad' }];
    commentLikes = [{ c: 1, u: 'kim' }, { c: 1, u: 'z' }];
    const r = await request(app.getHttpServer()).get('/v1/community/7').query({ view: '1' }).set('Cookie', cookie).expect(200);
    expect(r.headers['cache-control']).toBe('private, no-store');
    expect(r.body).toMatchObject({ id: 7, title: '제목', content: '<i>내용</i>이 있습니다', commentCount: 2, viewCount: 10, likeCount: 3, isAuthor: false, photos: ['/p1.jpg'] });
    expect(r.body.comments.map((c: any) => [c.id, c.parentId])).toEqual([[1, 0], [2, 1]]);
    expect(r.body.comments[0]).toMatchObject({ authorName: '첫댓글', likeCount: 2, liked: true });
    expect(r.body.comments[1]).toMatchObject({ likeCount: 0, liked: false });
    expect(sql.some((s) => s.q.startsWith('UPDATE gangnamunni_community_posts SET view_count'))).toBe(true);
    sql.length = 0;
    await request(app.getHttpServer()).get('/v1/community/7').expect(200);
    expect(sql.some((s) => s.q.includes('view_count = view_count + 1'))).toBe(false);
  });

  it('detail: author flag, 404 for unknown / blocked author, 400 for bad id', async () => {
    const own = await request(app.getHttpServer()).get('/v1/community/7').set('Cookie', 'sid=owner').expect(200);
    expect(own.body.isAuthor).toBe(true);
    await request(app.getHttpServer()).get('/v1/community/404').expect(404);
    await request(app.getHttpServer()).get('/v1/community/abc').expect(400);
    blocks = [{ blocker_id: 'kim', blocked_id: 'owner' }];
    await request(app.getHttpServer()).get('/v1/community/7').set('Cookie', cookie).expect(404);
  });

  it('post like: guards, toggles on/off and syncs like_count', async () => {
    await request(app.getHttpServer()).post('/v1/community/7/like').set(XHR).expect(401);
    await request(app.getHttpServer()).post('/v1/community/7/like').set('Cookie', cookie).expect(403);
    let r = await request(app.getHttpServer()).post('/v1/community/7/like').set(XHR).set('Cookie', cookie).expect(201);
    expect(r.body).toEqual({ success: true, liked: true, count: 1 });
    expect(sql.some((s) => s.q.startsWith('UPDATE gangnamunni_community_posts SET like_count') && s.v![0] === 1)).toBe(true);
    r = await request(app.getHttpServer()).post('/v1/community/7/like').set(XHR).set('Cookie', cookie).expect(201);
    expect(r.body).toEqual({ success: true, liked: false, count: 0 });
    await request(app.getHttpServer()).post('/v1/community/404/like').set(XHR).set('Cookie', cookie).expect(404);
  });

  it('comment like: sets the requested state (idempotent), unknown comment 404', async () => {
    const url = '/v1/community/7/comments/1/like';
    let r = await request(app.getHttpServer()).post(url).set(XHR).set('Cookie', cookie).send({ liked: true }).expect(201);
    expect(r.body).toEqual({ success: true, liked: true, count: 1 });
    r = await request(app.getHttpServer()).post(url).set(XHR).set('Cookie', cookie).send({ liked: true }).expect(201);
    expect(r.body.count).toBe(1);
    r = await request(app.getHttpServer()).post(url).set(XHR).set('Cookie', cookie).send({ liked: false }).expect(201);
    expect(r.body).toEqual({ success: true, liked: false, count: 0 });
    await request(app.getHttpServer()).post('/v1/community/7/comments/999/like').set(XHR).set('Cookie', cookie).send({ liked: true }).expect(404);
    await request(app.getHttpServer()).post(url).send({ liked: true }).set(XHR).expect(401);
  });

  it('comment submit: validation, top-level comment, reply thread link, counter, author = nickname', async () => {
    const url = '/v1/community/7/comments';
    const send = (b: object) => request(app.getHttpServer()).post(url).set(XHR).set('Cookie', cookie).send(b);
    await request(app.getHttpServer()).post(url).send({ content: 'x' }).expect(403);
    await request(app.getHttpServer()).post(url).set(XHR).send({ content: 'x' }).expect(401);
    await send({ content: '   ' }).expect(400);
    await send({ content: 'x'.repeat(501) }).expect(400);
    await send({ content: 'ok', parentId: -1 }).expect(400);
    await send({ content: 'ok', parentId: 1.5 }).expect(400);
    await send({ content: 'ok', parentId: 2 }).expect(400); // 2 is itself a reply
    await send({ content: 'ok', parentId: 999 }).expect(400);
    expect(sql.some((s) => s.q.startsWith('INSERT INTO gangnamunni_community_comments'))).toBe(false);
    let r = await send({ content: '  새 댓글  ' }).expect(201);
    expect(r.body).toEqual({ success: true, id: 103 });
    const ins = sql.find((s) => s.q.startsWith('INSERT INTO gangnamunni_community_comments'))!;
    expect(ins.v).toEqual([7, 'kim', '김닉', '새 댓글']);
    expect(sql.some((s) => s.q.startsWith('INSERT INTO gangnamunni_community_comment_threads'))).toBe(false);
    r = await send({ content: '답글', parentId: 1 }).expect(201);
    expect(threads[r.body.id]).toBe(1);
    expect(sql.filter((s) => s.q.includes('SET comment_count'))).toHaveLength(2);
    await request(app.getHttpServer()).post('/v1/community/404/comments').set(XHR).set('Cookie', cookie).send({ content: 'x' }).expect(404);
  });

  it('comment submit: a failed thread link does not fail the request', async () => {
    failThread = true;
    await request(app.getHttpServer()).post('/v1/community/7/comments').set(XHR).set('Cookie', cookie).send({ content: '답글', parentId: 1 }).expect(201);
  });

  it('comment submit: rate limited after 20 per window', async () => {
    let last = 201;
    for (let i = 0; i < 22; i++) {
      last = (await request(app.getHttpServer()).post('/v1/community/7/comments').set(XHR).set('Cookie', 'sid=owner').send({ content: `c${i}` })).status;
    }
    expect(last).toBe(429);
  });
});
