import * as fsSync from 'fs';
import { promises as fs } from 'fs';
import * as os from 'os';
import * as path from 'path';
// The multipart parser reads the temporary directory when its module is loaded, so a private one is set first: the check for
// left-over temporary files then sees only this file's requests.
const privateTmp = fsSync.mkdtempSync(path.join(os.tmpdir(), 'sb-tm-img-tmp-'));
process.env.TMPDIR = privateTmp;

import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { TreatmentsModule } from './treatments.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const MARK = { 'X-Requested-With': 'admin-next' };
const pad = (head: number[], n = 64): Buffer => Buffer.concat([Buffer.from(head), Buffer.alloc(n)]);
const jpeg = () => pad([0xff, 0xd8, 0xff, 0xe0]);
const png = () => pad([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
type Call = { sql: string; values: unknown[] };

describe('admin API: default image of a treatment (upload)', () => {
  let app: INestApplication;
  let root: string;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  let hasColumn: boolean;
  let rwFails: boolean;
  let storedUrl: string | null;

  const roStub = {
    query: jest.fn(async (sql: string) => {
      const s = sql.replace(/\s+/g, ' ');
      if (s.includes('FROM gangnamunni_treatment_categories c')) return [[{ id: 1, name: 'Face', slug: 'face', icon: '', sort_order: 1, is_active: 1, treatment_count: 1 }]];
      if (s.includes('FROM gangnamunni_treatment_master t LEFT JOIN')) {
        return [[{ id: 10, category_id: 1, category_name: 'Face', sort_order: 1, name: 'Botox', hashtag: '#Botox', description: '', duration_min: 0, recovery_days: 0, anesthesia_type: 'none', repeat_cycle_months: 0, precautions: '', avg_price_min: 0, avg_price_max: 0, is_active: 1, popularity_score: 0, created_by: '', created_at: null, hospital_count: 0 }]];
      }
      if (s.includes('FROM gangnamunni_treatment_aliases')) return [[]];
      if (s.includes('default_image_url FROM')) {
        if (!hasColumn) throw Object.assign(new Error('no column'), { code: 'ER_BAD_FIELD_ERROR', errno: 1054 });
        return [[{ id: 10, default_image_url: storedUrl }]];
      }
      if (s.includes('revisit_days, revisit_strict FROM')) return [[{ id: 10, revisit_days: null, revisit_strict: 0 }]];
      if (s.includes('FROM gangnamunni_treatment_conflicts')) return [[]];
      if (s.includes('FROM gangnamunni_settings')) return [[{ setting_value: 'excel' }]];
      throw new Error('unexpected read-only sql: ' + s);
    }),
  };
  const rwStub = {
    get: () => ({
      query: jest.fn(async (sql: string, values: unknown[] = []) => {
        rwCalls.push({ sql, values });
        if (rwFails) throw new Error('boom');
        if (sql.startsWith('UPDATE gangnamunni_treatment_master SET default_image_url')) storedUrl = values[0] as string;
        return [{ affectedRows: 1 }];
      }),
    }),
  };

  const stored = async (): Promise<string[]> => {
    try {
      return await fs.readdir(path.join(root, 'uploads', 'treatments'));
    } catch {
      return [];
    }
  };
  const upload = (id: number | string, file: { buf: Buffer; name: string } | null, cookie = SUPER, headers: Record<string, string> = MARK) => {
    let r = request(app.getHttpServer()).post(`/v1/treatments/${id}/image`);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return file ? r.attach('image', file.buf, file.name) : r.field('x', '1');
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
  beforeEach(async () => {
    root = await fs.mkdtemp(path.join(path.dirname(privateTmp), 'sb-tm-img-root-'));
    process.env.ADMIN_UPLOAD_ROOT = root;
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_TREATMENTS_ENABLED = '1';
    process.env.ADMIN_TREATMENTS_WRITE = '1';
    process.env.ADMIN_TREATMENTS_IMAGES = '1';
    rwCalls.length = 0; hasColumn = true; rwFails = false; storedUrl = null;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, TreatmentsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => {
    await app.close();
    await fs.rm(root, { recursive: true, force: true });
  });
  afterAll(async () => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_UPLOAD_ROOT', 'ADMIN_PANEL_ENABLED', 'ADMIN_TREATMENTS_ENABLED', 'ADMIN_TREATMENTS_WRITE', 'ADMIN_TREATMENTS_IMAGES']) delete process.env[k];
    await fs.rm(privateTmp, { recursive: true, force: true });
  });
  const noTemp = async () => expect(await fs.readdir(privateTmp)).toEqual([]);

  describe('guards', () => {
    it.each([undefined, '0', 'true'])('ADMIN_TREATMENTS_IMAGES=%p -> 404, nothing stored', async (v) => {
      if (v === undefined) delete process.env.ADMIN_TREATMENTS_IMAGES; else process.env.ADMIN_TREATMENTS_IMAGES = v;
      const r = await upload(10, { buf: jpeg(), name: 'a.jpg' });
      expect(r.status).toBe(404);
      expect(await stored()).toEqual([]);
      expect(rwCalls).toHaveLength(0);
    });
    it('no CSRF header -> 403; no session -> 401; hospital role -> 403; nothing stored', async () => {
      expect((await upload(10, { buf: jpeg(), name: 'a.jpg' }, SUPER, {})).status).toBe(403);
      expect((await upload(10, { buf: jpeg(), name: 'a.jpg' }, '')).status).toBe(401);
      expect((await upload(10, { buf: jpeg(), name: 'a.jpg' }, HOSPITAL)).status).toBe(403);
      expect(await stored()).toEqual([]);
      expect(rwCalls).toHaveLength(0);
      await noTemp();
    });
  });

  describe('upload', () => {
    it('stores the file under uploads/treatments and writes its address; the temporary file is gone', async () => {
      const r = await upload(10, { buf: jpeg(), name: 'photo.JPG' });
      expect(r.status).toBe(200);
      const files = await stored();
      expect(files).toHaveLength(1);
      expect(files[0]).toMatch(/^treat_\d+_[0-9a-f]{8}\.jpg$/);
      expect(rwCalls).toHaveLength(1);
      expect(rwCalls[0].sql).toBe('UPDATE gangnamunni_treatment_master SET default_image_url = ? WHERE id = ?');
      expect(rwCalls[0].values).toEqual([`uploads/treatments/${files[0]}`, 10]);
      expect(r.body.item.defaultImageUrl).toBe(`uploads/treatments/${files[0]}`);
      await noTemp();
    });
    it('a file whose real type disagrees with its extension is refused (400 invalid_image)', async () => {
      const r = await upload(10, { buf: png(), name: 'a.jpg' });
      expect(r.status).toBe(400);
      expect(r.body.error).toBe('invalid_image');
      expect(await stored()).toEqual([]);
      expect(rwCalls).toHaveLength(0);
      await noTemp();
    });
    it('a script with an image name and a wrong extension are refused', async () => {
      expect((await upload(10, { buf: Buffer.from('<?php echo 1;'), name: 'a.jpg' })).body.error).toBe('invalid_image');
      expect((await upload(10, { buf: jpeg(), name: 'a.php' })).body.error).toBe('invalid_image');
      expect(await stored()).toEqual([]);
    });
    it('over 10 MB -> 400 too_large', async () => {
      const big = Buffer.concat([jpeg(), Buffer.alloc(10 * 1024 * 1024)]);
      const r = await upload(10, { buf: big, name: 'a.jpg' });
      expect(r.status).toBe(400);
      expect(r.body.error).toBe('too_large');
      expect(await stored()).toEqual([]);
      await noTemp();
    });
    it('no file -> 400 image_required; unknown treatment -> 404', async () => {
      expect((await upload(10, null)).body.error).toBe('image_required');
      expect((await upload(99, { buf: jpeg(), name: 'a.jpg' })).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
      await noTemp();
    });
    it('the column is not installed -> 503 default_image_unavailable, nothing stored', async () => {
      hasColumn = false;
      const r = await upload(10, { buf: jpeg(), name: 'a.jpg' });
      expect(r.status).toBe(503);
      expect(r.body.error).toBe('default_image_unavailable');
      expect(await stored()).toEqual([]);
      await noTemp();
    });
    it('the upload folder cannot be used -> 503 uploads_unavailable', async () => {
      process.env.ADMIN_UPLOAD_ROOT = path.join(root, 'missing');
      const r = await upload(10, { buf: jpeg(), name: 'a.jpg' });
      expect(r.status).toBe(503);
      expect(r.body.error).toBe('uploads_unavailable');
      await noTemp();
    });
    it('a database failure removes the file it just stored', async () => {
      rwFails = true;
      const r = await upload(10, { buf: jpeg(), name: 'a.jpg' });
      expect(r.status).toBe(500);
      expect(await stored()).toEqual([]);
      await noTemp();
    });
  });
});
