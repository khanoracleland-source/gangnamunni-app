import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import { promises as fs, readdirSync } from 'fs';
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

const jpeg = (size = 64) => Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(Math.max(0, size - 4), 1)]);
const png = (size = 64) => Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(Math.max(0, size - 8), 2)]);
const webp = (size = 64) => Buffer.concat([Buffer.from('RIFF', 'latin1'), Buffer.from([1, 2, 3, 4]), Buffer.from('WEBP', 'latin1'), Buffer.alloc(Math.max(0, size - 12), 3)]);

type Call = { sql: string; values: unknown[] };
type Doc = { id: number; hospital_id: number; name: string; specialty: string; experience: number; qualification: string; bio: string; photo_url: string; status: string };
type File = { data: Buffer; name: string };

describe('admin API: hospital doctors (list and add)', () => {
  let app: INestApplication;
  let root: string;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const rwCalls: Call[] = [];
  const txEvents: string[] = [];
  let hospitals: Record<number, { status: string }>;
  let doctors: Doc[];
  let nextId: number;
  /** throws on the n-th (1-based) INSERT of the transaction when set */
  let failInsertAt: number | null;
  let insertCount: number;
  let failLock: boolean;
  let failPhotoUpdate: boolean;
  let hasRw = true;
  /** committed state is `doctors`; a transaction works on it directly and a rollback restores this copy */
  let snapshot: Doc[] | null = null;

  const handler = async (sql: string, values: unknown[] = []): Promise<unknown> => {
    const id = values[0] as number;
    if (sql.includes("AND status = 'approved' LIMIT 1")) return [hospitals[id] && hospitals[id].status === 'approved' ? [{ id }] : []];
    if (sql.includes('FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE')) {
      if (failLock) throw Object.assign(new Error('denied'), { code: 'ER_COLUMNACCESS_DENIED_ERROR', errno: 1143 });
      return [hospitals[id] ? [{ id, updated_at: 'x' }] : []];
    }
    if (sql.startsWith('SELECT id, name, specialty, experience, qualification, bio, photo_url, status FROM gangnamunni_doctors')) {
      return [doctors.filter((d) => d.hospital_id === id).sort((a, b) => a.id - b.id)];
    }
    if (sql.startsWith('INSERT INTO gangnamunni_doctors')) {
      insertCount += 1;
      if (failInsertAt === insertCount) throw Object.assign(new Error('boom'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      const [hid, name, specialty, experience, qualification, bio] = values as [number, string, string, number, string, string];
      doctors.push({ id: nextId, hospital_id: hid, name, specialty, experience, qualification, bio, photo_url: '', status: 'active' });
      return [{ insertId: nextId++ }];
    }
    if (sql.startsWith('UPDATE gangnamunni_doctors SET photo_url')) {
      if (failPhotoUpdate) throw Object.assign(new Error('boom'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      const [url, did, hid] = values as [string, number, number];
      const row = doctors.find((d) => d.id === did && d.hospital_id === hid);
      if (row) row.photo_url = url;
      return [{ affectedRows: row ? 1 : 0 }];
    }
    throw new Error(`unexpected sql: ${sql}`);
  };
  const roStub = { query: jest.fn(async (sql: string, values: unknown[] = []) => handler(sql, values)) };
  const rwStub = {
    get: () => (!hasRw ? null : {
      query: jest.fn(),
      getConnection: async () => ({
        query: async (sql: string, values: unknown[] = []) => { rwCalls.push({ sql, values }); return handler(sql, values); },
        beginTransaction: async () => { txEvents.push('BEGIN'); snapshot = doctors.map((d) => ({ ...d })); },
        commit: async () => { txEvents.push('COMMIT'); snapshot = null; },
        rollback: async () => { txEvents.push('ROLLBACK'); if (snapshot) doctors = snapshot; snapshot = null; },
        release: () => { txEvents.push('RELEASE'); },
      }),
    }),
  };

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
    root = await fs.mkdtemp(path.join(os.tmpdir(), 'sb-doctors-test-'));
    process.env.ADMIN_UPLOAD_ROOT = root;
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_WRITE = '1';
    process.env.ADMIN_HOSPITALS_DOCTORS = '1';
    phpCalls.length = 0; rwCalls.length = 0; txEvents.length = 0;
    hospitals = { 12: { status: 'approved' }, 13: { status: 'approved' }, 14: { status: 'pending' } };
    doctors = [];
    nextId = 500;
    failInsertAt = null;
    insertCount = 0;
    failLock = false;
    failPhotoUpdate = false;
    hasRw = true;
    snapshot = null;
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
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_DOCTORS', 'ADMIN_UPLOAD_ROOT']) delete process.env[k];
  });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };
  /** multipart POST: `doctors` text field plus `photo` files (supertest sets the content type with the boundary) */
  const add = (id: number | string, rows: unknown, files: File[] = [], cookie: string | undefined = SUPER, headers: Record<string, string> = MP) => {
    let r = request(app.getHttpServer()).post(`/v1/hospitals/${id}/doctors`);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    if (rows !== undefined) r = r.field('doctors', typeof rows === 'string' ? rows : JSON.stringify(rows));
    for (const f of files) r = r.attach('photo', f.data, f.name);
    return r;
  };
  const rwWrites = () => rwCalls.filter((c) => /^(INSERT|UPDATE)/.test(c.sql));
  const hospitalDir = (id = 12) => path.join(root, 'uploads', 'hospitals', String(id));
  const filesOnDisk = async (id = 12): Promise<string[]> => fs.readdir(hospitalDir(id)).catch(() => []);
  const noLeftovers = () => expect(tmpHex().filter((n) => !tmpBefore.includes(n))).toEqual([]);
  const cannotChmod = process.platform === 'win32' || (typeof process.getuid === 'function' && process.getuid() === 0);
  const oneDoc = [{ name: 'A' }];

  describe('switches, /v1/me, CSRF and guards', () => {
    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITALS_DOCTORS=%p -> POST 404 (PHP and database not asked), flag false, GET still works', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_DOCTORS; else process.env.ADMIN_HOSPITALS_DOCTORS = v;
      const r = await add(12, oneDoc);
      expect(r.status).toBe(404);
      expect(r.body).toEqual({ error: 'not_found' });
      expect(phpCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsDoctors).toBe(false);
      expect((await get('/v1/hospitals/12/doctors', SUPER)).status).toBe(200);
      noLeftovers();
    });

    it.each(['ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_PANEL_ENABLED'])('needs %s as well', async (k) => {
      process.env[k] = '0';
      expect((await add(12, oneDoc)).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
      if (k !== 'ADMIN_PANEL_ENABLED') expect((await get('/v1/me', SUPER)).body.features.hospitalsDoctors).toBe(false);
    });

    it('all on -> /v1/me reports hospitalsDoctors and keeps the other flags', async () => {
      expect((await get('/v1/me', SUPER)).body.features).toEqual({ hospitals: true, hospitalsWrite: true, hospitalsDelete: false, archive: false, hospitalsReview: false, hospitalsOrder: false, doctors: false, doctorsWrite: false, doctorsOrder: false, doctorsDelete: false, hospitalsImages: false, hospitalsDocuments: false, hospitalsDoctors: true, hospitalsTreatments: false, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false, hospitalsTreatmentsImages: false, treatments: false, treatmentsWrite: false, treatmentsImages: false, treatmentUnits: false, treatmentUnitsWrite: false, hospitalApply: false, admins: false, adminsWrite: false, bookings: false, bookingsWrite: false, consultations: false, payments: false, moderation: false, moderationWrite: false, moderationEdit: false, moderationDelete: false, banners: false, bannersWrite: false, categoryBoard: false, categoryBoardWrite: false });
      expect((await get('/v1/me', HOSPITAL)).body.features.hospitalsDoctors).toBe(true);
    });

    it('CSRF: no marker header -> 403 csrf, nothing stored, PHP not asked', async () => {
      const r = await add(12, oneDoc, [], SUPER, {});
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'csrf' });
      expect(phpCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('CSRF: JSON and plain form content types are not accepted', async () => {
      const json = await request(app.getHttpServer()).post('/v1/hospitals/12/doctors').set('Cookie', SUPER).set('X-Requested-With', 'admin-next').send({ doctors: '[]' });
      expect(json.status).toBe(403);
      const form = await request(app.getHttpServer()).post('/v1/hospitals/12/doctors').set('Cookie', SUPER).set('X-Requested-With', 'admin-next').type('form').send('a=1');
      expect(form.status).toBe(403);
      expect(rwCalls).toHaveLength(0);
    });

    it('CSRF: a foreign Origin -> 403; the same host as X-Forwarded-Host passes', async () => {
      expect((await add(12, oneDoc, [], SUPER, { ...MP, Origin: 'https://evil.example' })).status).toBe(403);
      expect((await add(12, oneDoc, [], SUPER, { ...MP, Origin: 'https://seoulbeauty.app', 'X-Forwarded-Host': 'seoulbeauty.app' })).status).toBe(200);
    });

    it('not signed in -> 401, nothing stored', async () => {
      const r = await add(12, oneDoc, [{ data: jpeg(), name: 'a.jpg' }], 'PHPSESSID=nobody');
      expect(r.status).toBe(401);
      expect(rwCalls).toHaveLength(0);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('hospital role: own hospital works, another hospital -> 404 with nothing written', async () => {
      expect((await add(12, oneDoc, [], HOSPITAL)).status).toBe(200);
      const other = await add(13, [{ name: 'B', photo: 0 }], [{ data: jpeg(), name: 'a.jpg' }], HOSPITAL);
      expect(other.status).toBe(404);
      expect(other.body).toEqual({ error: 'not_found' });
      expect(await filesOnDisk(13)).toEqual([]);
      expect(doctors.filter((d) => d.hospital_id === 13)).toEqual([]);
      noLeftovers();
    });

    it('unknown, malformed and not approved hospitals -> 404', async () => {
      for (const id of [999, 14, 'abc', '0', '-1', '12abc']) expect((await add(id, oneDoc)).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });
  });

  describe('GET /v1/hospitals/:id/doctors', () => {
    it('lists doctors by id with count and limit; empty photo_url -> null', async () => {
      doctors.push({ id: 3, hospital_id: 12, name: '나', specialty: '', experience: 2, qualification: 'q', bio: 'b', photo_url: 'uploads/hospitals/12/doctor_3_x.jpg', status: 'active' });
      doctors.push({ id: 2, hospital_id: 12, name: '가', specialty: '피부', experience: 0, qualification: '', bio: '', photo_url: '', status: 'active' });
      doctors.push({ id: 4, hospital_id: 13, name: '다', specialty: '', experience: 0, qualification: '', bio: '', photo_url: '', status: 'active' });
      const r = await get('/v1/hospitals/12/doctors', SUPER);
      expect(r.status).toBe(200);
      expect(r.headers['cache-control']).toBe('private, no-store');
      expect(r.body).toEqual({
        items: [
          { id: 2, name: '가', specialty: '피부', experience: 0, qualification: '', bio: '', photoUrl: null, status: 'active' },
          { id: 3, name: '나', specialty: '', experience: 2, qualification: 'q', bio: 'b', photoUrl: 'uploads/hospitals/12/doctor_3_x.jpg', status: 'active' },
        ],
        count: 2,
        addLimit: 20,
      });
    });

    it('empty list; needs the hospital pages switch only (not the doctors switch); scope and approval -> 404', async () => {
      delete process.env.ADMIN_HOSPITALS_DOCTORS;
      expect((await get('/v1/hospitals/12/doctors', SUPER)).body).toEqual({ items: [], count: 0, addLimit: 20 });
      expect((await get('/v1/hospitals/13/doctors', HOSPITAL)).status).toBe(404);
      expect((await get('/v1/hospitals/14/doctors', SUPER)).status).toBe(404);
      expect((await get('/v1/hospitals/abc/doctors', SUPER)).status).toBe(404);
      expect((await get('/v1/hospitals/12/doctors')).status).toBe(401);
      process.env.ADMIN_HOSPITALS_ENABLED = '0';
      expect((await get('/v1/hospitals/12/doctors', SUPER)).status).toBe(404);
    });
  });

  describe('POST /v1/hospitals/:id/doctors', () => {
    it('adds several doctors in one transaction, with photos named doctor_<id>_<hex>, and answers with the new list', async () => {
      doctors.push({ id: 1, hospital_id: 12, name: '기존', specialty: '', experience: 0, qualification: '', bio: '', photo_url: '', status: 'active' });
      const r = await add(12, [
        { name: ' 김서울 ', specialty: '피부과', experience: 10, qualification: '전문의\n학회', bio: '소개', photo: 0 },
        { name: '' },
        { name: '이부산', experience: '120', photo: 1 },
        { name: '박대구' },
      ], [{ data: jpeg(), name: 'a.jpg' }, { data: webp(), name: 'b.webp' }]);
      expect(r.status).toBe(200);
      expect(r.body.added).toBe(3);
      expect(r.body.count).toBe(4);
      expect(r.body.addLimit).toBe(20);
      expect(r.body).not.toHaveProperty('updatedAt');
      const [old, a, b, c] = r.body.items;
      expect(old.name).toBe('기존');
      expect(a).toMatchObject({ id: 500, name: '김서울', specialty: '피부과', experience: 10, qualification: '전문의\n학회', bio: '소개', status: 'active' });
      expect(a.photoUrl).toMatch(/^uploads\/hospitals\/12\/doctor_500_[0-9a-f]{32}\.jpg$/);
      expect(b).toMatchObject({ id: 501, name: '이부산', experience: 80 });
      expect(b.photoUrl).toMatch(/^uploads\/hospitals\/12\/doctor_501_[0-9a-f]{32}\.webp$/);
      expect(c).toMatchObject({ id: 502, name: '박대구', photoUrl: null });
      expect((await filesOnDisk()).sort()).toEqual([a.photoUrl, b.photoUrl].map((u: string) => path.basename(u)).sort());
      expect(txEvents).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      noLeftovers();
    });

    it('SQL is parametrised, writes only gangnamunni_doctors and never touches the hospital row', async () => {
      await add(12, [{ name: "x'); DROP TABLE a;--", specialty: '" OR 1=1', photo: 0 }], [{ data: png(), name: 'p.png' }]);
      const writes = rwWrites();
      expect(writes.map((w) => w.sql.split(' ').slice(0, 3).join(' '))).toEqual(['INSERT INTO gangnamunni_doctors', 'UPDATE gangnamunni_doctors SET']);
      expect(writes[0].values).toEqual([12, "x'); DROP TABLE a;--", '" OR 1=1', 0, '', '']);
      expect(writes[0].sql).not.toContain('DROP');
      expect(writes[1].values).toEqual([expect.stringMatching(/^uploads\/hospitals\/12\/doctor_500_[0-9a-f]{32}\.png$/), 500, 12]);
      expect(rwCalls.some((c) => /gangnamunni_hospitals SET/.test(c.sql))).toBe(false);
      expect(rwCalls[0].sql).toMatch(/FOR UPDATE/);
    });

    it('the stored extension comes from the real type; files get mode 0644 and the folder 0755', async () => {
      if (cannotChmod) return;
      await add(12, [{ name: 'A', photo: 0 }], [{ data: png(), name: 'whatever.PNG' }]);
      const [file] = await filesOnDisk();
      expect(file).toMatch(/^doctor_500_[0-9a-f]{32}\.png$/);
      expect((await fs.stat(path.join(hospitalDir(), file))).mode & 0o777).toBe(0o644);
      expect((await fs.stat(hospitalDir())).mode & 0o777).toBe(0o755);
    });

    it('without photos the upload folder is not needed (ADMIN_UPLOAD_ROOT empty still works)', async () => {
      process.env.ADMIN_UPLOAD_ROOT = '';
      const r = await add(12, oneDoc);
      expect(r.status).toBe(200);
      expect(r.body.items[0].photoUrl).toBeNull();
    });

    it('rows without a name are skipped with their photo; the unused file is not stored', async () => {
      const r = await add(12, [{ name: '', photo: 0 }, { name: 'B' }], [{ data: jpeg(), name: 'a.jpg' }]);
      expect(r.status).toBe(200);
      expect(r.body.added).toBe(1);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('no doctors / missing field / blank names -> 400 no_doctors, nothing written', async () => {
      for (const rows of [[], [{ name: ' ' }], undefined]) {
        const r = await add(12, rows as any, [{ data: jpeg(), name: 'a.jpg' }]);
        expect(r.status).toBe(400);
        expect(r.body).toEqual({ error: 'no_doctors' });
      }
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('more than 20 rows -> 400 too_many; 20 rows are fine', async () => {
      const r = await add(12, Array.from({ length: 21 }, (_, i) => ({ name: `d${i}` })));
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'too_many' });
      expect(rwCalls).toHaveLength(0);
      const ok = await add(12, Array.from({ length: 20 }, (_, i) => ({ name: `d${i}` })));
      expect(ok.status).toBe(200);
      expect(ok.body.count).toBe(20);
    });

    it('more than 20 photo files -> 400 too_many, temporary files removed', async () => {
      const files = Array.from({ length: 21 }, (_, i) => ({ data: jpeg(), name: `p${i}.jpg` }));
      const r = await add(12, oneDoc, files);
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'too_many' });
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('wrong field values name the row and the field', async () => {
      const cases: [unknown, unknown][] = [
        [[{ name: 'A' }, { name: 'x'.repeat(101) }], { error: 'invalid_field', index: 1, field: 'name' }],
        [[{ name: 'A', specialty: 's'.repeat(256) }], { error: 'invalid_field', index: 0, field: 'specialty' }],
        [[{ name: 'A', experience: 'many' }], { error: 'invalid_field', index: 0, field: 'experience' }],
        [[{ name: 'A', qualification: 'q'.repeat(2001) }], { error: 'invalid_field', index: 0, field: 'qualification' }],
        [[{ name: 'A', bio: 'b'.repeat(2001) }], { error: 'invalid_field', index: 0, field: 'bio' }],
        [[{ name: 'A', photo: 3 }], { error: 'invalid_field', index: 0, field: 'photo' }],
        ['{broken', { error: 'invalid_field' }],
      ];
      for (const [rows, body] of cases) {
        const r = await add(12, rows);
        expect(r.status).toBe(400);
        expect(r.body).toEqual(body);
      }
      expect(rwCalls).toHaveLength(0);
    });

    it('the doctors field above 200 KB -> 400 invalid_field', async () => {
      const r = await add(12, JSON.stringify([{ name: 'A', bio: 'x'.repeat(210 * 1024) }]));
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'invalid_field' });
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('photo checks: wrong real type, extension mismatch, svg, too large -> 400 naming the row; nothing stored', async () => {
      const bad: [File, string][] = [
        [{ data: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"/>'), name: 'a.jpg' }, 'invalid_type'],
        [{ data: jpeg(), name: 'a.png' }, 'invalid_type'],
        [{ data: Buffer.from('GIF89a......'), name: 'a.gif' }, 'invalid_type'],
        [{ data: jpeg(5 * 1024 * 1024 + 1), name: 'a.jpg' }, 'too_large'],
      ];
      for (const [file, error] of bad) {
        const r = await add(12, [{ name: 'A' }, { name: 'B', photo: 0 }], [file]);
        expect(r.status).toBe(400);
        expect(r.body).toEqual(error === 'too_large' ? { error, field: 'photo' } : { error, index: 1, field: 'photo' });
      }
      expect(rwCalls).toHaveLength(0);
      expect(doctors).toEqual([]);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('a photo of exactly 5 MB is accepted', async () => {
      const r = await add(12, [{ name: 'A', photo: 0 }], [{ data: jpeg(5 * 1024 * 1024), name: 'big.jpg' }]);
      expect(r.status).toBe(200);
    });

    it('an unknown file field -> 400 upload_failed', async () => {
      const r = await request(app.getHttpServer()).post('/v1/hospitals/12/doctors').set('Cookie', SUPER).set('X-Requested-With', 'admin-next')
        .field('doctors', JSON.stringify(oneDoc)).attach('main', jpeg(), 'a.jpg');
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'upload_failed' });
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('a failing INSERT of the second doctor -> rollback, the first photo file is removed, nothing is left', async () => {
      failInsertAt = 2;
      doctors.push({ id: 1, hospital_id: 12, name: '기존', specialty: '', experience: 0, qualification: '', bio: '', photo_url: '', status: 'active' });
      const r = await add(12, [{ name: 'A', photo: 0 }, { name: 'B' }], [{ data: jpeg(), name: 'a.jpg' }]);
      expect(r.status).toBe(500);
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      expect(doctors.map((d) => d.name)).toEqual(['기존']);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('a failing photo link (UPDATE) -> rollback and the written photo is removed', async () => {
      failPhotoUpdate = true;
      const r = await add(12, [{ name: 'A', photo: 0 }], [{ data: jpeg(), name: 'a.jpg' }]);
      expect(r.status).toBe(500);
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      expect(doctors).toEqual([]);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('missing grants -> 503 write_unavailable, nothing changed, files removed', async () => {
      failLock = true;
      const r = await add(12, [{ name: 'A', photo: 0 }], [{ data: jpeg(), name: 'a.jpg' }]);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      expect(doctors).toEqual([]);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('no write user configured -> 503 write_unavailable', async () => {
      hasRw = false;
      const r = await add(12, oneDoc);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
    });

    it.each(['', 'relative/path', '/nonexistent/sb-root'])('photos need a usable upload root (%p) -> 503 uploads_unavailable, nothing written', async (v) => {
      process.env.ADMIN_UPLOAD_ROOT = v;
      const r = await add(12, [{ name: 'A', photo: 0 }], [{ data: jpeg(), name: 'a.jpg' }]);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(rwCalls).toHaveLength(0);
      expect(doctors).toEqual([]);
      noLeftovers();
    });

    it('a folder that cannot be written -> 503 uploads_unavailable, the inserted rows are rolled back', async () => {
      if (cannotChmod) return;
      await fs.mkdir(hospitalDir(), { recursive: true });
      await fs.chmod(hospitalDir(), 0o555);
      const r = await add(12, [{ name: 'A', photo: 0 }], [{ data: jpeg(), name: 'a.jpg' }]);
      await fs.chmod(hospitalDir(), 0o755);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      expect(doctors).toEqual([]);
      noLeftovers();
    });

    it('the answer is never cached', async () => {
      const r = await add(12, oneDoc);
      expect(r.headers['cache-control']).toBe('private, no-store');
    });
  });
});
