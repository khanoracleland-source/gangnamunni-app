import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import { promises as fs } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { CommunityModule } from '../src/community/community.module';
import { encodeReportReasons, sniffImage, validatePost } from '../src/community/community.write.logic';

const XHR = { 'X-Requested-With': 'XMLHttpRequest' };
const PNG = Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(32)]);

describe('community write logic', () => {
  it('validatePost', () => {
    const cats = ['일상', '기타'];
    expect(validatePost('일상', '', 'x'.repeat(10), cats)).toMatch(/제목/);
    expect(validatePost('일상', 't', 'short', cats)).toMatch(/10자/);
    expect(validatePost('없음', 't', 'x'.repeat(10), cats)).toMatch(/카테고리/);
    expect(validatePost('일상', 't', 'x'.repeat(10), cats)).toBeNull();
  });
  it('sniffImage ignores names and rejects svg/html', () => {
    expect(sniffImage(PNG)?.ext).toBe('png');
    expect(sniffImage(Buffer.from([0xff, 0xd8, 0xff, 0xe0]))?.ext).toBe('jpg');
    expect(sniffImage(Buffer.from('<svg xmlns="x"></svg>'))).toBeNull();
    expect(sniffImage(Buffer.from('<?php echo 1;'))).toBeNull();
  });
  it('encodeReportReasons', () => {
    expect(encodeReportReasons(['spam', 'other'], ' 상세 ')).toEqual({ reason: 'spam', reasonDetail: JSON.stringify({ version: 1, reasons: ['spam', 'other'], detail: '상세' }) });
    expect(encodeReportReasons(['privacy'], 'x')?.reason).toBe('other');
    expect(encodeReportReasons([], '')).toBeNull();
    expect(encodeReportReasons(['bogus'], '')).toBeNull();
    expect(encodeReportReasons('spam', '')).toBeNull();
    expect(encodeReportReasons(['spam'], 'x'.repeat(1001))).toBeNull();
  });
});

describe('community write API (mock DB)', () => {
  let app: INestApplication;
  let dir: string;
  const sql: { q: string; v?: unknown[] }[] = [];
  const realFetch = global.fetch;
  let reports: string[] = [];
  let reviewLikes: string[] = [];
  const own = { id: 5, mb_id: 'kim', category: '일상', title: '옛', content: 'x'.repeat(12), photos: '["/hairwang/app/uploads/community/a.png"]', status: 'active' };

  const db = {
    query: jest.fn(async (q: string, v?: unknown[]) => {
      sql.push({ q, v });
      if (q.startsWith('SELECT * FROM gangnamunni_community_posts WHERE id = ? AND status IN')) return [v![0] === 5 ? [{ ...own }] : v![0] === 6 ? [{ ...own, id: 6, mb_id: 'other' }] : []];
      if (q.startsWith('INSERT INTO gangnamunni_community_posts')) return [{ insertId: 77 }];
      if (q.startsWith('UPDATE gangnamunni_community_posts SET category')) return [{ affectedRows: 1 }];
      if (q.startsWith("UPDATE gangnamunni_community_posts SET status = 'deleted'")) return [{ affectedRows: v![0] === 5 ? 1 : 0 }];
      if (q.startsWith('SELECT * FROM gangnamunni_community_posts WHERE id = ? AND status =')) return [v![0] === 5 ? [{ ...own, mb_id: 'owner' }] : v![0] === 8 ? [{ ...own, id: 8, mb_id: 'kim' }] : []];
      if (q.startsWith('SELECT * FROM hospital_reviews')) return [[{ id: 9, user_id: 'owner', content: '후기' }]];
      if (q.startsWith('SELECT mb_no')) return [[{ mb_no: 1, mb_nick: '글쓴이', mb_name: '' }]];
      if (q.startsWith('SELECT id FROM gangnamunni_content_reports')) return [reports.includes(`${v![0]}:${v![1]}:${v![2]}`) ? [{ id: 1 }] : []];
      if (q.startsWith('INSERT INTO gangnamunni_content_reports')) { reports.push(`${v![0]}:${v![2]}:${v![3]}`); return [{ insertId: 3 }]; }
      if (q.startsWith('INSERT IGNORE INTO gangnamunni_blocked_users')) return [{}];
      if (q.startsWith('SELECT id FROM hospital_reviews')) return [v![0] === 9 ? [{ id: 9 }] : []];
      if (q.startsWith('SELECT id FROM review_likes')) return [reviewLikes.includes(String(v![1])) ? [{ id: 1 }] : []];
      if (q.startsWith('INSERT INTO review_likes')) { reviewLikes.push(String(v![1])); return [{}]; }
      if (q.startsWith('DELETE FROM review_likes')) { reviewLikes = reviewLikes.filter((u) => u !== String(v![1])); return [{}]; }
      if (q.includes('COUNT(*) AS c FROM review_likes')) return [[{ c: reviewLikes.length }]];
      if (q.startsWith('UPDATE hospital_reviews SET like_count')) return [{ affectedRows: 1 }];
      return [[]];
    }),
  };

  beforeAll(async () => {
    dir = await fs.mkdtemp(join(tmpdir(), 'cu-'));
    process.env.COMMUNITY_UPLOAD_DIR = dir;
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42, nickname: '김닉' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: '이름', level: 2, phone: '010', ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: db }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AuthModule, CommunityModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.COMMUNITY_UPLOAD_DIR; await app.close(); await fs.rm(dir, { recursive: true, force: true }); });
  beforeEach(() => { sql.length = 0; reports = []; reviewLikes = []; });
  const as = (r: request.Test) => r.set(XHR).set('Cookie', 'sid=kim');
  const srv = () => app.getHttpServer();
  const GOOD = { category: '일상', title: '제목', content: '내용은 열 글자 이상입니다.' };

  it('requires login and the XHR header', async () => {
    await request(srv()).post('/v1/community').set(XHR).field('title', 'a').expect(401);
    await request(srv()).post('/v1/community').set('Cookie', 'sid=kim').field('title', 'a').expect(403);
    await request(srv()).get('/v1/community/form').expect(401);
    await request(srv()).get('/v1/community/form').set('Cookie', 'sid=kim').expect(200); // reads need no XHR header (the web get() helper sends none)
  });

  it('form: categories, and edit only for the author', async () => {
    const r = await as(request(srv()).get('/v1/community/form')).expect(200);
    expect(r.body).toMatchObject({ maxPhotos: 4, post: null });
    expect(r.body.categories).toContain('일상');
    const e = await as(request(srv()).get('/v1/community/form?id=5')).expect(200);
    expect(e.body.post).toMatchObject({ id: 5, title: '옛', photos: ['/hairwang/app/uploads/community/a.png'] });
    await as(request(srv()).get('/v1/community/form?id=6')).expect(403);
    await as(request(srv()).get('/v1/community/form?id=99')).expect(404);
  });

  it('create: validation, pending status, photos saved with sniffed extension', async () => {
    await as(request(srv()).post('/v1/community').field('category', '일상').field('title', '').field('content', 'x'.repeat(12))).expect(400);
    await as(request(srv()).post('/v1/community').field('category', '일상').field('title', 't').field('content', 'short')).expect(400);
    let r = await as(request(srv()).post('/v1/community').field(GOOD)).expect(201);
    expect(r.body).toMatchObject({ success: true, id: 77 });
    const ins = sql.find((s) => s.q.startsWith('INSERT INTO gangnamunni_community_posts'))!;
    expect(ins.q).toContain("'pending'");
    expect(ins.v).toEqual(['kim', '김닉', '일상', '제목', GOOD.content, null]);
    r = await as(request(srv()).post('/v1/community').field(GOOD).attach('photos', PNG, { filename: 'evil.php', contentType: 'text/html' })).expect(201);
    const files = await fs.readdir(dir);
    expect(files).toHaveLength(1);
    expect(files[0]).toMatch(/^community_\d+_0_[0-9a-f]{8}\.png$/);
    const urls = JSON.parse(sql.filter((s) => s.q.startsWith('INSERT INTO gangnamunni_community_posts')).pop()!.v![5] as string);
    expect(urls).toEqual([`/hairwang/app/uploads/community/${files[0]}`]);
  });

  it('create: bad image rejects everything and leaves no file', async () => {
    const before = (await fs.readdir(dir)).length;
    await as(request(srv()).post('/v1/community').field(GOOD).attach('photos', PNG, 'a.png').attach('photos', Buffer.from('<svg/>'), 'b.png')).expect(400);
    expect((await fs.readdir(dir)).length).toBe(before);
    expect(sql.some((s) => s.q.startsWith('INSERT INTO gangnamunni_community_posts'))).toBe(false);
    await as(request(srv()).post('/v1/community').field(GOOD).attach('photos', Buffer.alloc(5 * 1024 * 1024 + 10, 1), 'big.png')).expect(413);
  });

  it('update: author only, removes selected photos, back to pending; delete is author-only', async () => {
    await as(request(srv()).put('/v1/community/6').field(GOOD)).expect(403);
    await as(request(srv()).put('/v1/community/5').field(GOOD).field('deletePhotos', '/hairwang/app/uploads/community/a.png')).expect(200);
    const up = sql.find((s) => s.q.startsWith('UPDATE gangnamunni_community_posts SET category'))!;
    expect(up.q).toContain("status = 'pending'");
    expect(up.v).toEqual(['일상', '제목', GOOD.content, null, 5, 'kim']);
    await as(request(srv()).delete('/v1/community/5')).expect(200);
    await as(request(srv()).delete('/v1/community/6')).expect(404);
  });

  it('report: validates, blocks self and duplicates, stores reason JSON', async () => {
    const send = (b: object) => as(request(srv()).post('/v1/community/reports').send(b));
    await send({ contentType: 'bogus', contentId: 5, reasons: ['spam'] }).expect(400);
    await send({ contentType: 'community', contentId: 5, reasons: [] }).expect(400);
    await send({ contentType: 'community', contentId: 8, reasons: ['spam'] }).expect(400); // own post
    const r = await send({ contentType: 'community', contentId: 5, reasons: ['spam', 'other'], reasonDetail: '상세' }).expect(201);
    expect(r.body).toMatchObject({ success: true, reportId: 3 });
    const ins = sql.find((s) => s.q.startsWith('INSERT INTO gangnamunni_content_reports'))!;
    expect(ins.v!.slice(0, 4)).toEqual([42, '김닉', 'community', 5]);
    expect(ins.v![8]).toBe('spam');
    await send({ contentType: 'community', contentId: 5, reasons: ['spam'] }).expect(409);
    await send({ contentType: 'review', contentId: 9, reasons: ['sexual'] }).expect(201);
    await send({ contentType: 'community', contentId: 999, reasons: ['spam'] }).expect(404);
  });

  it('block: by content, never self', async () => {
    await as(request(srv()).post('/v1/community/blocks').send({ contentType: 'community', contentId: 8 })).expect(400);
    await as(request(srv()).post('/v1/community/blocks').send({ contentType: 'community', contentId: 5 })).expect(201);
    const ins = sql.find((s) => s.q.startsWith('INSERT IGNORE INTO gangnamunni_blocked_users'))!;
    expect(ins.v).toEqual(['kim', 'owner', '글쓴이']);
  });

  it('review like toggles', async () => {
    let r = await as(request(srv()).post('/v1/community/reviews/9/like')).expect(201);
    expect(r.body).toEqual({ success: true, liked: true, count: 1 });
    r = await as(request(srv()).post('/v1/community/reviews/9/like')).expect(201);
    expect(r.body).toEqual({ success: true, liked: false, count: 0 });
    await as(request(srv()).post('/v1/community/reviews/404/like')).expect(404);
  });
});
