import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import { promises as fs } from 'fs';
import * as os from 'os';
import * as path from 'path';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { DB, DB_RW } from '../db/db.module';
import { BannersModule } from './banners.module';
import { bannerRevision, parseBannerForm } from './banners.logic';
import { isOwnBannerImage, removableBannerPath } from './banners.image';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const MP = { 'X-Requested-With': 'admin-next' };
const PNG = Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(64, 1)]);
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

type Call = { sql: string; values: unknown[] };

describe('admin API: banner create and edit', () => {
  let app: INestApplication;
  let root: string;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  const events: string[] = [];
  let row: Record<string, unknown>;
  let users: number;
  let failInsert: boolean;
  let hospitalFound: boolean;

  const ro = {
    query: jest.fn(async (sql: string) => {
      if (sql.includes('FROM gangnamunni_hospitals WHERE id = ?')) return [hospitalFound ? [{ id: 12 }] : []];
      if (sql.includes('SELECT id, name FROM gangnamunni_hospitals')) return [[{ id: 12, name: 'Seoul Clinic' }]];
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
      if (sql.includes('FOR UPDATE')) return [values[0] === row.id ? [row] : []];
      if (sql.startsWith('INSERT INTO gangnamunni_ads')) {
        if (failInsert) throw new Error('boom');
        return [{ insertId: 55 }];
      }
      return [{ affectedRows: 1 }];
    }),
  };
  const pool = { getConnection: async () => conn, query: jest.fn(async () => [[{ c: users }]]) };

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
    root = await fs.mkdtemp(path.join(os.tmpdir(), 'banner-'));
    Object.assign(process.env, { ADMIN_PANEL_ENABLED: '1', ADMIN_HOSPITALS_ENABLED: '1', ADMIN_BANNERS_ENABLED: '1', ADMIN_BANNERS_WRITE: '1', ADMIN_UPLOAD_ROOT: root });
    rwCalls.length = 0;
    events.length = 0;
    users = 0;
    failInsert = false;
    hospitalFound = true;
    row = { id: 3, hospital_id: null, title: 'Old', subtitle: null, image_url: 'uploads/ads/ad_old.png', mypage_image_url: '', link_url: '', display_order: 1, status: 'approved', updated_at: '2026-11-20 10:00:00' };
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }, { provide: DB_RW, useValue: { get: () => pool } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, BannersModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => {
    await app.close();
    await fs.rm(root, { recursive: true, force: true });
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_BANNERS_ENABLED', 'ADMIN_BANNERS_WRITE', 'ADMIN_UPLOAD_ROOT']) delete process.env[k];
  });

  const form = (method: 'post' | 'patch', url: string, fields: Record<string, string>, files: Record<string, Buffer | [Buffer, string]> = {}, cookie: string | null = SUPER, headers: Record<string, string> = MP) => {
    const r = request(app.getHttpServer())[method](url).set(headers);
    if (cookie) r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(fields)) r.field(k, v);
    for (const [k, v] of Object.entries(files)) {
      const [buf, name] = Array.isArray(v) ? v : [v, 'pic.png'];
      r.attach(k, buf, name);
    }
    return r;
  };
  const stored = async () => {
    try {
      return await fs.readdir(path.join(root, 'uploads', 'ads'));
    } catch {
      return [];
    }
  };
  const tmpLeft = async () => (await fs.readdir(os.tmpdir())).length;

  it('creates an approved, active banner with a home picture; the file lands in uploads/ads', async () => {
    const res = await form('post', '/v1/banners', { title: ' Spring ', linkUrl: 'https://example.com/x', displayOrder: '4', hospitalId: '12' }, { image: PNG });
    expect(res.status).toBe(201);
    expect(res.body).toEqual({ id: 55 });
    const ins = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_ads'))!;
    expect(ins.sql).toContain(`'approved'`);
    expect(ins.values[0]).toBe(12);
    expect(ins.values[1]).toBe('Spring');
    expect(String(ins.values[3])).toMatch(/^uploads\/ads\/ad_[0-9a-f]{32}\.png$/);
    expect(ins.values[7]).toBe('김관리');
    expect(events).toEqual(['begin', 'commit']);
    const files = await stored();
    expect(files).toHaveLength(1);
    expect(`uploads/ads/${files[0]}`).toBe(ins.values[3]);
  });

  it('creates a standalone banner (no hospital) from a typed address', async () => {
    const res = await form('post', '/v1/banners', { title: 'Solo', imageUrl: 'https://cdn.example.com/a.jpg' });
    expect(res.status).toBe(201);
    const ins = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_ads'))!;
    expect(ins.values[0]).toBeNull();
    expect(ins.values[3]).toBe('https://cdn.example.com/a.jpg');
    expect(await stored()).toHaveLength(0);
  });

  it('answers field errors without touching the database or the disk', async () => {
    const res = await form('post', '/v1/banners', { title: '', linkUrl: 'javascript:alert(1)', displayOrder: '-1', hospitalId: 'abc' });
    expect(res.status).toBe(400);
    expect(res.body.fields).toMatchObject({ title: 'required', linkUrl: 'bad_url', displayOrder: 'range', hospitalId: 'invalid', image: 'required' });
    expect(rwCalls).toHaveLength(0);
    expect(await stored()).toHaveLength(0);
  });

  it('refuses an unknown hospital, a fake picture, a wrong extension and a too large picture', async () => {
    hospitalFound = false;
    const a = await form('post', '/v1/banners', { title: 'T', hospitalId: '99' }, { image: PNG });
    expect(a.body.fields).toMatchObject({ hospitalId: 'unknown' });
    hospitalFound = true;
    const b = await form('post', '/v1/banners', { title: 'T' }, { image: [Buffer.from('<?php echo 1;'), 'x.png'] });
    expect(b.body.fields).toMatchObject({ image: 'invalid_image' });
    const c = await form('post', '/v1/banners', { title: 'T' }, { image: [PNG, 'x.php'] });
    expect(c.body.fields).toMatchObject({ image: 'invalid_image' });
    const d = await form('post', '/v1/banners', { title: 'T' }, { image: [Buffer.concat([PNG, Buffer.alloc(5 * 1024 * 1024)]), 'big.png'] });
    expect(d.status).toBe(400);
    expect(d.body.error).toBe('too_large');
    expect(rwCalls).toHaveLength(0);
    expect(await stored()).toHaveLength(0);
  });

  it('removes the file it wrote when the database fails, and leaves no temporary file', async () => {
    const before = await tmpLeft();
    failInsert = true;
    const res = await form('post', '/v1/banners', { title: 'T' }, { image: PNG });
    expect(res.status).toBe(500);
    expect(events).toEqual(['begin', 'rollback']);
    expect(await stored()).toHaveLength(0);
    expect(await tmpLeft()).toBe(before);
  });

  it('answers 503 uploads_unavailable when the upload root is missing', async () => {
    process.env.ADMIN_UPLOAD_ROOT = path.join(root, 'missing');
    const res = await form('post', '/v1/banners', { title: 'T' }, { image: PNG });
    expect(res.status).toBe(503);
    expect(res.body.error).toBe('uploads_unavailable');
  });

  it('is for the super admin only and needs the multipart marker and the write switch', async () => {
    expect((await form('post', '/v1/banners', { title: 'T' }, { image: PNG }, null)).status).toBe(401);
    expect((await form('post', '/v1/banners', { title: 'T' }, { image: PNG }, HOSPITAL)).status).toBe(403);
    expect((await form('post', '/v1/banners', { title: 'T' }, { image: PNG }, SUPER, {})).status).toBe(403);
    expect((await request(app.getHttpServer()).post('/v1/banners').set(MP).set('Cookie', SUPER).send({ title: 'T' })).status).toBe(403);
    process.env.ADMIN_BANNERS_WRITE = '0';
    expect((await form('patch', '/v1/banners/3', { title: 'T' })).status).toBe(404);
    expect(rwCalls).toHaveLength(0);
    expect(await stored()).toHaveLength(0);
  });

  it('edits a banner, keeps its status, and removes the replaced own picture when nobody else uses it', async () => {
    await fs.mkdir(path.join(root, 'uploads', 'ads'), { recursive: true });
    await fs.writeFile(path.join(root, 'uploads', 'ads', 'ad_old.png'), PNG);
    const res = await form('patch', '/v1/banners/3', { title: 'New', revision: bannerRevision(row), imageUrl: 'uploads/ads/ad_old.png', displayOrder: '2' }, { image: PNG });
    expect(res.status).toBe(200);
    const up = rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_ads'))!;
    expect(up.sql).not.toMatch(/status|is_active/);
    expect(String(up.values[3])).toMatch(/^uploads\/ads\/ad_[0-9a-f]{32}\.png$/);
    expect(up.values[7]).toBe(3);
    const files = await stored();
    expect(files).toHaveLength(1);
    expect(files).not.toContain('ad_old.png');
  });

  it('keeps the old file when another banner still uses it', async () => {
    users = 1;
    await fs.mkdir(path.join(root, 'uploads', 'ads'), { recursive: true });
    await fs.writeFile(path.join(root, 'uploads', 'ads', 'ad_old.png'), PNG);
    const res = await form('patch', '/v1/banners/3', { title: 'New', revision: bannerRevision(row) }, { image: PNG });
    expect(res.status).toBe(200);
    expect(await stored()).toContain('ad_old.png');
  });

  it('refuses a banner changed in the meantime, a missing one and a form without a revision, and keeps no file', async () => {
    const stale = bannerRevision(row);
    row.title = 'Changed elsewhere';
    const a = await form('patch', '/v1/banners/3', { title: 'N', revision: stale }, { image: PNG });
    expect(a.status).toBe(409);
    expect(a.body.error).toBe('changed');
    expect((await form('patch', '/v1/banners/99', { title: 'N', revision: stale }, { image: PNG })).status).toBe(404);
    const c = await form('patch', '/v1/banners/3', { title: 'N' }, { image: PNG });
    expect(c.status).toBe(400);
    expect(c.body).toMatchObject({ error: 'invalid', fields: { revision: 'required' } });
    expect(rwCalls.filter((x) => x.sql.startsWith('UPDATE'))).toHaveLength(0);
    expect(await stored()).toHaveLength(0);
  });

  it('deleting a banner removes its own pictures when nobody else uses them', async () => {
    await fs.mkdir(path.join(root, 'uploads', 'ads'), { recursive: true });
    await fs.writeFile(path.join(root, 'uploads', 'ads', 'ad_old.png'), PNG);
    const res = await request(app.getHttpServer()).delete('/v1/banners/3').set(MP).set('Cookie', SUPER).send({ revision: bannerRevision(row) });
    expect(res.status).toBe(200);
    expect(await stored()).toHaveLength(0);
  });

  it('lists the hospitals to link to', async () => {
    const res = await request(app.getHttpServer()).get('/v1/banners/hospitals').set('Cookie', SUPER);
    expect(res.status).toBe(200);
    expect(res.body).toEqual({ items: [{ id: 12, name: 'Seoul Clinic' }] });
    expect((await request(app.getHttpServer()).get('/v1/banners/hospitals').set('Cookie', HOSPITAL)).status).toBe(403);
  });

  describe('pure parts', () => {
    it('parses the form', () => {
      const ok = parseBannerForm({ title: ' A ', subtitle: '', displayOrder: '', hospitalId: '0', imageUrl: 'uploads/ads/ad_1.jpg', mypageImageUrl: '' });
      expect(ok.errors).toEqual({});
      expect(ok.value).toMatchObject({ title: 'A', subtitle: null, displayOrder: 0, hospitalId: null, imageUrl: 'uploads/ads/ad_1.jpg', mypageImageUrl: '' });
      expect(parseBannerForm({ title: 'x'.repeat(256) }).errors.title).toBe('too_long');
      expect(parseBannerForm({ title: ['a'] }).errors.title).toBe('invalid');
      expect(parseBannerForm({ title: 'A', imageUrl: '../../etc/passwd' }).errors.imageUrl).toBe('invalid_image');
      expect(parseBannerForm({ title: 'A', imageUrl: 'uploads/other/a.jpg' }).errors.imageUrl).toBe('invalid_image');
      expect(parseBannerForm({ title: 'A', displayOrder: '100000' }).errors.displayOrder).toBe('range');
    });
    it('only touches its own picture folder', () => {
      expect(isOwnBannerImage('uploads/ads/ad_1.jpg')).toBe(true);
      expect(isOwnBannerImage('uploads/ads/../x.jpg')).toBe(false);
      expect(isOwnBannerImage('https://a/b.jpg')).toBe(false);
      expect(removableBannerPath('/srv/up', 'uploads/ads/ad_1.jpg')).toBe('/srv/up/uploads/ads/ad_1.jpg');
      expect(removableBannerPath('/srv/up', 'uploads/ads/a/b.jpg')).toBeNull();
      expect(removableBannerPath('rel', 'uploads/ads/ad_1.jpg')).toBeNull();
    });
  });
});
