import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import { promises as fs, statSync, readdirSync } from 'fs';
import * as os from 'os';
import * as path from 'path';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { HospitalsModule } from './hospitals.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const MP = { 'X-Requested-With': 'admin-next' };
const T0 = '2026-11-01 11:00:00';
const T1 = '2026-11-16 09:30:00';

const jpeg = (size = 64) => Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(Math.max(0, size - 4), 1)]);
const png = (size = 64) => Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(Math.max(0, size - 8), 2)]);
const webp = (size = 64) => Buffer.concat([Buffer.from('RIFF', 'latin1'), Buffer.from([1, 2, 3, 4]), Buffer.from('WEBP', 'latin1'), Buffer.alloc(Math.max(0, size - 12), 3)]);

type Call = { sql: string; values: unknown[] };
type Img = { id: number; hospital_id: number; image_url: string; image_type: string; sort_order: number; is_active: number };

describe('admin API: hospital images (list and upload)', () => {
  let app: INestApplication;
  let root: string;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const rwCalls: Call[] = [];
  const txEvents: string[] = [];
  let hospitals: Record<number, { status: string; updated_at: string | null; main_image_url: string | null }>;
  let images: Img[];
  let nextId: number;
  /** throws on the n-th (1-based) INSERT of the transaction when set */
  let failInsertAt: number | null;
  let insertCount: number;
  let failLock: boolean;
  let hasRw = true;

  const handler = async (sql: string, values: unknown[] = []): Promise<unknown> => {
    const id = values[0] as number;
    if (sql.includes("AND status = 'approved' LIMIT 1")) return [hospitals[id] && hospitals[id].status === 'approved' ? [{ id }] : []];
    if (sql.includes('FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE')) {
      if (failLock) throw Object.assign(new Error('denied'), { code: 'ER_COLUMNACCESS_DENIED_ERROR', errno: 1143 });
      return [hospitals[id] ? [{ id, updated_at: hospitals[id].updated_at }] : []];
    }
    if (sql.startsWith('SELECT updated_at FROM gangnamunni_hospitals')) return [hospitals[id] ? [{ updated_at: hospitals[id].updated_at }] : []];
    if (sql.includes("image_type NOT IN ('main','logo')")) {
      return [[{ cnt: images.filter((i) => i.hospital_id === id && i.is_active === 1 && i.image_type !== 'main' && i.image_type !== 'logo').length }]];
    }
    if (sql.includes("image_type = 'main' ORDER BY is_active DESC")) {
      const m = images.filter((i) => i.hospital_id === id && i.image_type === 'main').sort((a, b) => b.is_active - a.is_active || a.sort_order - b.sort_order || a.id - b.id);
      return [m.length ? [{ id: m[0].id }] : []];
    }
    if (sql.startsWith('SELECT COALESCE(MAX(sort_order)')) return [[{ max_sort: Math.max(0, ...images.filter((i) => i.hospital_id === id).map((i) => i.sort_order)) }]];
    if (sql.startsWith('SELECT id, image_url, image_type, sort_order, is_active FROM hospital_images')) {
      return [images.filter((i) => i.hospital_id === id).sort((a, b) => a.sort_order - b.sort_order || a.id - b.id)];
    }
    if (sql.startsWith('INSERT INTO hospital_images')) {
      insertCount += 1;
      if (failInsertAt === insertCount) throw Object.assign(new Error('boom'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      const [hid, url, type, sort] = values as [number, string, string, number];
      images.push({ id: nextId, hospital_id: hid, image_url: url, image_type: type, sort_order: sort, is_active: 1 });
      return [{ insertId: nextId++ }];
    }
    if (sql.startsWith('UPDATE hospital_images SET image_url')) {
      const [url, imageId, hid] = values as [string, number, number];
      const row = images.find((i) => i.id === imageId && i.hospital_id === hid);
      if (row) { row.image_url = url; row.sort_order = 0; row.is_active = 1; }
      return [{ affectedRows: row ? 1 : 0 }];
    }
    if (sql.startsWith('UPDATE hospital_images SET is_active = 0')) {
      const [hid, keep] = values as [number, number];
      for (const i of images) if (i.hospital_id === hid && i.image_type === 'main' && i.id !== keep) i.is_active = 0;
      return [{ affectedRows: 1 }];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospitals SET main_image_url')) {
      const [url, hid] = values as [string, number];
      hospitals[hid].main_image_url = url;
      hospitals[hid].updated_at = T1;
      return [{ affectedRows: 1 }];
    }
    throw new Error(`unexpected sql: ${sql}`);
  };
  const roStub = { query: jest.fn(async (sql: string, values: unknown[] = []) => handler(sql, values)) };
  const rwStub = {
    get: () => (!hasRw ? null : {
      query: jest.fn(),
      getConnection: async () => ({
        query: async (sql: string, values: unknown[] = []) => { rwCalls.push({ sql, values }); txEvents.push(sql.split(' ').slice(0, 3).join(' ')); return handler(sql, values); },
        beginTransaction: async () => { txEvents.push('BEGIN'); },
        commit: async () => { txEvents.push('COMMIT'); },
        rollback: async () => { txEvents.push('ROLLBACK'); },
        release: () => { txEvents.push('RELEASE'); },
      }),
    }),
  };

  /** multer names its temporary files with 32 random hex characters in the system temporary directory */
  const tmpHex = (): string[] => readdirSync(os.tmpdir()).filter((n) => /^[0-9a-f]{32}$/.test(n));
  let tmpBefore: string[];

  async function boot(): Promise<void> {
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, HospitalsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  }

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      phpCalls.push(cookie);
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
    jest.spyOn(Logger.prototype, 'warn').mockImplementation(() => undefined);
    jest.spyOn(Logger.prototype, 'error').mockImplementation(() => undefined);
    root = await fs.mkdtemp(path.join(os.tmpdir(), 'sb-upload-test-'));
    process.env.ADMIN_UPLOAD_ROOT = root;
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_WRITE = '1';
    process.env.ADMIN_HOSPITALS_IMAGES = '1';
    phpCalls.length = 0; rwCalls.length = 0; txEvents.length = 0;
    hospitals = { 12: { status: 'approved', updated_at: T0, main_image_url: null }, 13: { status: 'approved', updated_at: T0, main_image_url: null }, 14: { status: 'pending', updated_at: T0, main_image_url: null } };
    images = [];
    nextId = 100;
    failInsertAt = null;
    insertCount = 0;
    failLock = false;
    hasRw = true;
    tmpBefore = tmpHex();
    await boot();
  });
  afterEach(async () => {
    await app.close();
    jest.restoreAllMocks();
    await fs.chmod(root, 0o755).catch(() => undefined);
    await fs.rm(root, { recursive: true, force: true });
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_IMAGES', 'ADMIN_UPLOAD_ROOT']) delete process.env[k];
  });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };
  /** multipart POST (supertest sets the content type with the boundary when files are attached) */
  const upload = (id: number | string, files: { field: string; data: Buffer; name: string }[], cookie: string | undefined = SUPER, headers: Record<string, string> = MP) => {
    let r = request(app.getHttpServer()).post(`/v1/hospitals/${id}/images`);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    for (const f of files) r = r.attach(f.field, f.data, f.name);
    return r;
  };
  const main = (name = 'm.jpg', data = jpeg()) => ({ field: 'main', data, name });
  const interior = (name = 'i.jpg', data = jpeg()) => ({ field: 'interior', data, name });
  const rwWrites = () => rwCalls.filter((c) => /^(INSERT|UPDATE)/.test(c.sql));
  const hospitalDir = (id = 12) => path.join(root, 'uploads', 'hospitals', String(id));
  const filesOnDisk = async (id = 12): Promise<string[]> => fs.readdir(hospitalDir(id)).catch(() => []);
  const noLeftovers = () => expect(tmpHex().filter((n) => !tmpBefore.includes(n))).toEqual([]);
  const seed = (n: number, type = 'interior', active = 1) => { for (let k = 0; k < n; k += 1) images.push({ id: 10 + images.length, hospital_id: 12, image_url: `uploads/hospitals/12/seed${k}.jpg`, image_type: type, sort_order: k + 1, is_active: active }); };
  const cannotChmod = process.platform === 'win32' || (typeof process.getuid === 'function' && process.getuid() === 0);

  describe('switches, /v1/me, CSRF and guards', () => {
    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITALS_IMAGES=%p -> POST 404 (PHP and database not asked), flag false, GET still works', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_IMAGES; else process.env.ADMIN_HOSPITALS_IMAGES = v;
      const r = await upload(12, [main()]);
      expect(r.status).toBe(404);
      expect(r.body).toEqual({ error: 'not_found' });
      expect(phpCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsImages).toBe(false);
      expect((await get('/v1/hospitals/12/images', SUPER)).status).toBe(200);
      noLeftovers();
    });

    it.each(['ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_PANEL_ENABLED'])('needs %s as well', async (k) => {
      process.env[k] = '0';
      expect((await upload(12, [main()])).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
      if (k !== 'ADMIN_PANEL_ENABLED') expect((await get('/v1/me', SUPER)).body.features.hospitalsImages).toBe(false);
    });

    it('all on -> /v1/me reports hospitalsImages and keeps the other flags', async () => {
      expect((await get('/v1/me', SUPER)).body.features).toEqual({ hospitals: true, hospitalsWrite: true, hospitalsDelete: false, archive: false, hospitalsReview: false, hospitalsOrder: false, doctors: false, doctorsWrite: false, doctorsOrder: false, doctorsDelete: false, hospitalsImages: true, hospitalsDocuments: false, hospitalsDoctors: false, hospitalsTreatments: false, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false, hospitalsTreatmentsImages: false, treatments: false, treatmentsWrite: false, treatmentsImages: false, treatmentUnits: false, treatmentUnitsWrite: false, hospitalApply: false, admins: false, adminsWrite: false, bookings: false, bookingsWrite: false, consultations: false, payments: false, moderation: false, moderationWrite: false, moderationEdit: false, moderationDelete: false, banners: false, bannersWrite: false, categoryBoard: false, categoryBoardWrite: false });
      expect((await get('/v1/me', HOSPITAL)).body.features.hospitalsImages).toBe(true);
    });

    it('CSRF: no marker header -> 403 csrf, nothing stored, PHP not asked', async () => {
      const r = await upload(12, [main()], SUPER, {});
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'csrf' });
      expect(phpCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('CSRF: JSON and plain form content types are not accepted for the upload route', async () => {
      const json = await request(app.getHttpServer()).post('/v1/hospitals/12/images').set('Cookie', SUPER).set('X-Requested-With', 'admin-next').set('Content-Type', 'application/json').send('{}');
      expect(json.status).toBe(403);
      expect(json.body).toEqual({ error: 'csrf' });
      const form = await request(app.getHttpServer()).post('/v1/hospitals/12/images').set('Cookie', SUPER).set('X-Requested-With', 'admin-next').type('form').send('a=1');
      expect(form.status).toBe(403);
    });

    it('CSRF: a foreign Origin -> 403; the same host as X-Forwarded-Host passes', async () => {
      expect((await upload(12, [main()], SUPER, { ...MP, Origin: 'https://evil.example' })).status).toBe(403);
      expect((await upload(12, [main()], SUPER, { ...MP, Origin: 'https://seoulbeauty.app', 'X-Forwarded-Host': 'other.example' })).status).toBe(403);
      expect((await upload(12, [main()], SUPER, { ...MP, Origin: 'https://seoulbeauty.app', 'X-Forwarded-Host': 'seoulbeauty.app' })).status).toBe(200);
    });

    it('the JSON write routes still refuse multipart (their rule is unchanged)', async () => {
      const r = await request(app.getHttpServer()).post('/v1/hospitals/12/active').set('Cookie', SUPER).set('X-Requested-With', 'admin-next').attach('main', jpeg(), 'a.jpg');
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'csrf' });
    });

    it('not signed in -> 401, the body is never stored', async () => {
      const r = await upload(12, [main()], 'PHPSESSID=nobody');
      expect(r.status).toBe(401);
      expect(rwCalls).toHaveLength(0);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('hospital role: own hospital works, another hospital -> 404 with nothing written', async () => {
      expect((await upload(12, [interior()], HOSPITAL)).status).toBe(200);
      const other = await upload(13, [interior()], HOSPITAL);
      expect(other.status).toBe(404);
      expect(other.body).toEqual({ error: 'not_found' });
      expect(await filesOnDisk(13)).toEqual([]);
      noLeftovers();
    });

    it('unknown, malformed and not approved hospitals -> 404', async () => {
      for (const id of [999, 14, 'abc', '0', '-1', '12abc']) expect((await upload(id, [main()])).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });
  });

  describe('GET /v1/hospitals/:id/images', () => {
    it('lists images in order with the main url and the interior count', async () => {
      images.push({ id: 1, hospital_id: 12, image_url: 'uploads/hospitals/12/main_x.jpg', image_type: 'main', sort_order: 0, is_active: 1 });
      images.push({ id: 3, hospital_id: 12, image_url: 'uploads/hospitals/12/b.jpg', image_type: 'interior', sort_order: 2, is_active: 1 });
      images.push({ id: 2, hospital_id: 12, image_url: 'uploads/hospitals/12/a.jpg', image_type: 'interior', sort_order: 1, is_active: 0 });
      const r = await get('/v1/hospitals/12/images', SUPER);
      expect(r.status).toBe(200);
      expect(r.body).toEqual({
        items: [
          { id: 1, url: 'uploads/hospitals/12/main_x.jpg', type: 'main', sortOrder: 0, isActive: true },
          { id: 2, url: 'uploads/hospitals/12/a.jpg', type: 'interior', sortOrder: 1, isActive: false },
          { id: 3, url: 'uploads/hospitals/12/b.jpg', type: 'interior', sortOrder: 2, isActive: true },
        ],
        mainUrl: 'uploads/hospitals/12/main_x.jpg', detailCount: 1, detailLimit: 10,
      });
      expect(rwCalls).toHaveLength(0);
    });

    it('empty hospital, 401, scope 404, missing 404', async () => {
      expect((await get('/v1/hospitals/12/images', SUPER)).body).toEqual({ items: [], mainUrl: null, detailCount: 0, detailLimit: 10 });
      expect((await get('/v1/hospitals/12/images')).status).toBe(401);
      expect((await get('/v1/hospitals/12/images', HOSPITAL)).status).toBe(200);
      expect((await get('/v1/hospitals/13/images', HOSPITAL)).status).toBe(404);
      expect((await get('/v1/hospitals/999/images', SUPER)).status).toBe(404);
      expect((await get('/v1/hospitals/x/images', SUPER)).status).toBe(404);
    });

    it('with the hospital pages off -> 404', async () => {
      process.env.ADMIN_HOSPITALS_ENABLED = '0';
      expect((await get('/v1/hospitals/12/images', SUPER)).status).toBe(404);
    });
  });

  describe('upload: storage and database', () => {
    it('main only, no earlier main row -> INSERT main (sort 0), hospital synced, safe file name and modes', async () => {
      const r = await upload(12, [main('../../evil name.JPG')]);
      expect(r.status).toBe(200);
      expect(r.body.added).toBe(1);
      expect(r.body.updatedAt).toBe(T1);
      expect(r.body.detailCount).toBe(0);
      expect(r.body.detailLimit).toBe(10);
      expect(r.body.mainUrl).toMatch(/^uploads\/hospitals\/12\/main_[0-9a-f]{32}\.jpg$/);
      expect(r.body.items).toEqual([{ id: 100, url: r.body.mainUrl, type: 'main', sortOrder: 0, isActive: true }]);
      expect(hospitals[12].main_image_url).toBe(r.body.mainUrl);
      expect(await filesOnDisk()).toEqual([path.basename(r.body.mainUrl)]);
      expect(await fs.readFile(path.join(root, r.body.mainUrl))).toEqual(jpeg());
      if (process.platform !== 'win32') {
        expect(statSync(hospitalDir()).mode & 0o777).toBe(0o755);
        expect(statSync(path.join(root, r.body.mainUrl)).mode & 0o777).toBe(0o644);
      }
      expect(txEvents[0]).toBe('BEGIN');
      expect(txEvents).toContain('COMMIT');
      expect(txEvents).not.toContain('ROLLBACK');
      // the row lock is the first statement of the transaction
      expect(rwCalls[0].sql).toContain('FOR UPDATE');
      expect(rwCalls.some((c) => c.sql.startsWith('UPDATE hospital_images SET is_active = 0'))).toBe(true);
      noLeftovers();
    });

    it('main with an existing main row -> UPDATE of that row (no new row), other main rows off, old file stays on disk', async () => {
      await fs.mkdir(hospitalDir(), { recursive: true });
      await fs.writeFile(path.join(hospitalDir(), 'main_old.jpg'), jpeg());
      images.push({ id: 5, hospital_id: 12, image_url: 'uploads/hospitals/12/main_old.jpg', image_type: 'main', sort_order: 0, is_active: 1 });
      images.push({ id: 6, hospital_id: 12, image_url: 'uploads/hospitals/12/main_older.jpg', image_type: 'main', sort_order: 3, is_active: 1 });
      const r = await upload(12, [main('new.png', png())]);
      expect(r.status).toBe(200);
      expect(rwCalls.some((c) => c.sql.startsWith('INSERT INTO hospital_images'))).toBe(false);
      expect(images.find((i) => i.id === 5)!.image_url).toMatch(/^uploads\/hospitals\/12\/main_[0-9a-f]{32}\.png$/);
      expect(images.find((i) => i.id === 6)!.is_active).toBe(0);
      expect(r.body.mainUrl).toBe(images.find((i) => i.id === 5)!.image_url);
      expect(hospitals[12].main_image_url).toBe(r.body.mainUrl);
      expect(await filesOnDisk()).toContain('main_old.jpg');
      expect(await filesOnDisk()).toHaveLength(2);
    });

    it('interior only -> INSERTs with sort_order MAX+1.., type interior; the hospital row is not touched', async () => {
      seed(2);
      const r = await upload(12, [interior('a.jpg', jpeg()), interior('b.webp', webp()), interior('c.png', png())]);
      expect(r.status).toBe(200);
      expect(r.body.added).toBe(3);
      expect(r.body.detailCount).toBe(5);
      expect(r.body.updatedAt).toBe(T0);
      expect(hospitals[12].main_image_url).toBeNull();
      const added = images.filter((i) => i.id >= 100 && i.image_type === 'interior');
      expect(added.map((i) => i.sort_order)).toEqual([3, 4, 5]);
      expect(added.map((i) => path.extname(i.image_url))).toEqual(['.jpg', '.webp', '.png']);
      for (const a of added) expect(a.image_url).toMatch(/^uploads\/hospitals\/12\/interior_[0-9a-f]{32}\.(jpg|webp|png)$/);
      expect(rwCalls.some((c) => c.sql.startsWith('UPDATE gangnamunni_hospitals'))).toBe(false);
      expect(await filesOnDisk()).toHaveLength(3);
      noLeftovers();
    });

    it('main and interior together in one transaction', async () => {
      const r = await upload(12, [main(), interior(), interior('x.png', png())]);
      expect(r.status).toBe(200);
      expect(r.body.added).toBe(3);
      expect(r.body.items.map((i: { type: string }) => i.type)).toEqual(['main', 'interior', 'interior']);
      expect(txEvents.filter((e) => e === 'COMMIT')).toHaveLength(1);
    });

    it('every value is a parameter, never part of the SQL text', async () => {
      await upload(12, [main(), interior()]);
      expect(rwWrites().length).toBeGreaterThan(0);
      for (const c of rwCalls) expect(c.sql).not.toMatch(/uploads\/hospitals|\.jpg|main_[0-9a-f]/);
    });

    it('file names sent by the client never reach the path', async () => {
      const r = await upload(12, [interior('..\\..\\x/../../../etc/passwd.jpg')]);
      expect(r.status).toBe(200);
      const files = await filesOnDisk();
      expect(files).toHaveLength(1);
      expect(files[0]).toMatch(/^interior_[0-9a-f]{32}\.jpg$/);
      expect(await fs.readdir(root)).toEqual(['uploads']);
    });
  });

  describe('upload: limits and validation', () => {
    it('existing interior images count: 8 + 2 passes, 8 + 3 -> 400 too_many with remaining 2', async () => {
      seed(8);
      expect((await upload(12, [interior(), interior()])).status).toBe(200);
      images = images.filter((i) => i.id < 100);
      const r = await upload(12, [interior(), interior(), interior()]);
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'too_many', field: 'interior', remaining: 2 });
    });

    it('10 existing -> any interior is too_many with remaining 0; a main alone still works', async () => {
      seed(10);
      expect((await upload(12, [interior()])).body).toEqual({ error: 'too_many', field: 'interior', remaining: 0 });
      expect((await upload(12, [main()])).status).toBe(200);
    });

    it('inactive, main and logo images are not counted', async () => {
      seed(5, 'interior', 0);
      seed(3, 'logo');
      images.push({ id: 9, hospital_id: 12, image_url: 'm.jpg', image_type: 'main', sort_order: 0, is_active: 1 });
      expect((await upload(12, Array.from({ length: 10 }, () => interior()))).status).toBe(200);
    });

    it('a rejected count leaves no file, no row and no temporary file; main is not applied either', async () => {
      seed(9);
      const before = images.length;
      const r = await upload(12, [main(), interior(), interior()]);
      expect(r.status).toBe(400);
      expect(images).toHaveLength(before);
      expect(await filesOnDisk()).toEqual([]);
      expect(txEvents).toContain('ROLLBACK');
      expect(hospitals[12].main_image_url).toBeNull();
      noLeftovers();
    });

    it('11 interior files in one request -> 400 too_many; main + 10 interior is accepted', async () => {
      const r = await upload(12, Array.from({ length: 11 }, () => interior()));
      expect(r.status).toBe(400);
      expect(r.body).toMatchObject({ error: 'too_many', field: 'interior' });
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
      const ok = await upload(12, [main(), ...Array.from({ length: 10 }, () => interior())]);
      expect(ok.status).toBe(200);
      expect(ok.body.added).toBe(11);
    });

    it('two main files -> 400 too_many on main', async () => {
      const r = await upload(12, [main('a.jpg'), main('b.jpg')]);
      expect(r.status).toBe(400);
      expect(r.body).toMatchObject({ error: 'too_many', field: 'main' });
      noLeftovers();
    });

    it('unknown field name -> 400 upload_failed', async () => {
      const r = await upload(12, [{ field: 'avatar', data: jpeg(), name: 'a.jpg' }]);
      expect(r.status).toBe(400);
      expect(r.body.error).toBe('upload_failed');
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('no files -> 400 no_files', async () => {
      const r = await request(app.getHttpServer()).post('/v1/hospitals/12/images').set('Cookie', SUPER).set('X-Requested-With', 'admin-next').field('note', 'x');
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'no_files' });
      expect(rwCalls).toHaveLength(0);
    });

    it('wrong content (PNG named .jpg, text, SVG, GIF, WebP as main) -> 400 invalid_type with the field', async () => {
      const cases: [ReturnType<typeof main>, string][] = [
        [interior('a.jpg', png()), 'interior'], [interior('a.jpg', Buffer.from('hello world, not an image')), 'interior'], [interior('a.svg', Buffer.from('<svg xmlns="x"/>')), 'interior'],
        [interior('a.gif', Buffer.from('GIF89a......')), 'interior'], [main('a.jpg', png()), 'main'], [main('a.webp', webp()), 'main'], [main('a.jpg', webp()), 'main'], [main('a', jpeg()), 'main'],
      ];
      for (const [f, field] of cases) {
        const r = await upload(12, [f]);
        expect(r.status).toBe(400);
        expect(r.body).toEqual({ error: 'invalid_type', field });
      }
      expect(rwWrites()).toHaveLength(0);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('one bad file rejects the whole request; the good ones are not stored', async () => {
      const r = await upload(12, [main(), interior(), interior('bad.jpg', Buffer.from('nope'))]);
      expect(r.status).toBe(400);
      expect(r.body.error).toBe('invalid_type');
      expect(await filesOnDisk()).toEqual([]);
      expect(hospitals[12].main_image_url).toBeNull();
      noLeftovers();
    });

    it('main over 5 MB -> 400 too_large (main); interior over 10 MB -> 400 too_large (interior)', async () => {
      const bigMain = await upload(12, [main('big.jpg', jpeg(5 * 1024 * 1024 + 1))]);
      expect(bigMain.status).toBe(400);
      expect(bigMain.body).toEqual({ error: 'too_large', field: 'main' });
      const bigInterior = await upload(12, [interior('big.jpg', jpeg(10 * 1024 * 1024 + 1))]);
      expect(bigInterior.status).toBe(400);
      expect(bigInterior.body).toEqual({ error: 'too_large', field: 'interior' });
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    }, 30000);

    it('exactly 5 MB main and 10 MB interior are accepted', async () => {
      const r = await upload(12, [main('ok.jpg', jpeg(5 * 1024 * 1024)), interior('ok.png', png(10 * 1024 * 1024))]);
      expect(r.status).toBe(200);
      noLeftovers();
    }, 30000);
  });

  describe('upload: failures and cleanup', () => {
    it('database error in the middle -> rollback, files written by this request removed, earlier files untouched, 500 server_error', async () => {
      await fs.mkdir(hospitalDir(), { recursive: true });
      await fs.writeFile(path.join(hospitalDir(), 'interior_existing.jpg'), jpeg());
      failInsertAt = 3; // main INSERT, interior 1 ok, interior 2 fails
      const r = await upload(12, [main(), interior(), interior(), interior()]);
      expect(r.status).toBe(500);
      expect(r.body).toEqual({ error: 'server_error' });
      expect(txEvents).toContain('ROLLBACK');
      expect(txEvents).not.toContain('COMMIT');
      expect(txEvents).toContain('RELEASE');
      expect(await filesOnDisk()).toEqual(['interior_existing.jpg']);
      noLeftovers();
    });

    it('write user without rights -> 503 write_unavailable, no file left', async () => {
      failLock = true;
      const r = await upload(12, [main()]);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('no write pool configured -> 503 write_unavailable, no temporary file left', async () => {
      hasRw = false;
      const r = await upload(12, [main()]);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      noLeftovers();
    });

    it('unwritable hospitals folder -> 503 uploads_unavailable, rollback, nothing left', async () => {
      if (cannotChmod) return; // root ignores folder permissions
      await fs.mkdir(path.join(root, 'uploads', 'hospitals'), { recursive: true });
      await fs.chmod(path.join(root, 'uploads', 'hospitals'), 0o555);
      const r = await upload(12, [interior()]);
      await fs.chmod(path.join(root, 'uploads', 'hospitals'), 0o755);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(txEvents).toContain('ROLLBACK');
      noLeftovers();
    });
  });

  describe('upload root', () => {
    it.each([
      ['not set', () => { delete process.env.ADMIN_UPLOAD_ROOT; }],
      ['empty', () => { process.env.ADMIN_UPLOAD_ROOT = ''; }],
      ['relative', () => { process.env.ADMIN_UPLOAD_ROOT = 'uploads-here'; }],
      ['missing folder', () => { process.env.ADMIN_UPLOAD_ROOT = path.join(root, 'does-not-exist'); }],
      ['a file', () => { process.env.ADMIN_UPLOAD_ROOT = path.join(root, 'afile'); }],
    ])('%s -> 503 uploads_unavailable, nothing in the database, no path in the answer', async (_n, setup) => {
      await fs.writeFile(path.join(root, 'afile'), 'x');
      setup();
      const r = await upload(12, [main()]);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(JSON.stringify(r.body)).not.toContain(root);
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('is checked on the request, not at start-up: fixing it makes the next upload work', async () => {
      delete process.env.ADMIN_UPLOAD_ROOT;
      expect((await upload(12, [main()])).status).toBe(503);
      process.env.ADMIN_UPLOAD_ROOT = root;
      expect((await upload(12, [main()])).status).toBe(200);
    });

    it('a read-only folder -> 503 uploads_unavailable', async () => {
      if (cannotChmod) return;
      await fs.chmod(root, 0o555);
      const r = await upload(12, [main()]);
      await fs.chmod(root, 0o755);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
    });
  });
});
