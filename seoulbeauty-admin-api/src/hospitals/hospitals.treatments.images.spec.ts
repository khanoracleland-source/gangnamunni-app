import * as fsSync from 'fs';
import { promises as fs } from 'fs';
import * as os from 'os';
import * as path from 'path';
// The multipart parser reads the temporary directory when its module is loaded, so a private one is set first: the checks for left-over
// temporary files then see only this file's requests (other suites upload in parallel).
const privateTmp = fsSync.mkdtempSync(path.join(os.tmpdir(), 'sb-tri-tmp-'));
process.env.TMPDIR = privateTmp;

import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const MARK = { 'X-Requested-With': 'admin-next' };
const dbError = (code: string, errno: number, message = code) => Object.assign(new Error(message), { code, errno });

const pad = (head: number[], n = 64): Buffer => Buffer.concat([Buffer.from(head), Buffer.alloc(n)]);
const jpeg = () => pad([0xff, 0xd8, 0xff, 0xe0]);
const png = () => pad([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
const gif = () => Buffer.concat([Buffer.from('GIF89a', 'latin1'), Buffer.alloc(64)]);
const webp = () => Buffer.concat([Buffer.from('RIFF', 'latin1'), Buffer.from([0, 0, 0, 0]), Buffer.from('WEBP', 'latin1'), Buffer.alloc(64)]);

type Call = { sql: string; values: unknown[] };
type Img = { id: number; ht: number; hospital_id: number; treatment_id: number; image_url: string; caption: string; sort_order: number; approval_status: string; uploaded_by: string; reject_reason: string | null; reviewed_at: string | null; reviewed_by: string | null };
type Ht = { id: number; hospital_id: number; treatment_id: number; is_active: number };

describe('admin API: detail images of a hospital treatment (list, add, delete, reorder, review)', () => {
  let app: INestApplication;
  let root: string;
  const realFetch = global.fetch;
  const roCalls: Call[] = [];
  const rwCalls: Call[] = [];
  const txEvents: string[] = [];
  const logLines: string[] = [];
  let hospitals: Record<number, { status: string }>;
  let hts: Ht[];
  let images: Img[];
  let mode: string | null;
  let hasRw = true;
  let imagesTable = true;
  let nextId = 900;
  let failInsert = false;
  let failSortAt: number | null = null;
  let sortCount = 0;
  let failDelete = false;
  let snapshot: Img[] | null = null;
  let defaultColumn: boolean | 'denied' = true;
  let master: Record<number, { url: string | null; active: boolean }>;

  const sorted = (ht: unknown) => images.filter((i) => i.ht === ht).sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);
  const handler = async (sql: string, values: unknown[] = []): Promise<unknown> => {
    if (sql.startsWith('SELECT `value` FROM settings')) return [mode === null ? [] : [{ value: mode }]];
    if (sql === 'SELECT id FROM gangnamunni_hospitals WHERE id = ? LIMIT 1') return [hospitals[values[0] as number] ? [{ id: values[0] }] : []];
    if (sql.startsWith('SELECT id, treatment_id FROM gangnamunni_hospital_treatments WHERE id = ? AND hospital_id = ? LIMIT 1 FOR UPDATE')) {
      const ht = hts.find((h) => h.id === values[0] && h.hospital_id === values[1]);
      return [ht ? [{ id: ht.id, treatment_id: ht.treatment_id }] : []];
    }
    if (sql.startsWith('SELECT id FROM gangnamunni_hospital_treatments WHERE id = ? AND hospital_id = ? LIMIT 1')) {
      const ht = hts.find((h) => h.id === values[0] && h.hospital_id === values[1]);
      return [ht ? [{ id: ht.id }] : []];
    }
    if (sql.startsWith('SELECT id, image_url, caption, sort_order, approval_status')) {
      if (!imagesTable) throw dbError('ER_COLUMNACCESS_DENIED_ERROR', 1143);
      return [sorted(values[0]).map((i) => ({ id: i.id, image_url: i.image_url, caption: i.caption, sort_order: i.sort_order, approval_status: i.approval_status, uploaded_by: i.uploaded_by, reject_reason: i.reject_reason, reviewed_at: i.reviewed_at, reviewed_by: i.reviewed_by }))];
    }
    if (sql.startsWith('SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort')) {
      const mine = images.filter((i) => i.ht === values[0]);
      return [[{ next_sort: mine.length === 0 ? 0 : Math.max(...mine.map((i) => i.sort_order)) + 1 }]];
    }
    if (sql.startsWith('INSERT INTO gangnamunni_hospital_treatment_images')) {
      if (failInsert) throw dbError('ER_LOCK_DEADLOCK', 1213);
      const [ht, hid, tid, url, caption, sort, status, by] = values as [number, number, number, string, string, number, string, string];
      images.push({ id: nextId, ht, hospital_id: hid, treatment_id: tid, image_url: url, caption, sort_order: sort, approval_status: status, uploaded_by: by, reject_reason: null, reviewed_at: null, reviewed_by: null });
      return [{ insertId: nextId++, affectedRows: 1 }];
    }
    if (sql.startsWith('SELECT image_url FROM gangnamunni_hospital_treatment_images WHERE id = ? AND hospital_treatment_id = ? LIMIT 1 FOR UPDATE')) {
      const i = images.find((x) => x.id === values[0] && x.ht === values[1]);
      return [i ? [{ image_url: i.image_url }] : []];
    }
    if (sql.startsWith('DELETE FROM gangnamunni_hospital_treatment_images WHERE id = ? AND hospital_treatment_id = ?')) {
      if (failDelete) throw dbError('ER_TABLEACCESS_DENIED_ERROR', 1142);
      const n = images.filter((x) => x.id === values[0] && x.ht === values[1]).length;
      images = images.filter((x) => !(x.id === values[0] && x.ht === values[1]));
      return [{ affectedRows: n }];
    }
    if (sql.startsWith('SELECT id, approval_status FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ? ORDER BY sort_order ASC, id ASC FOR UPDATE')) return [sorted(values[0]).map((i) => ({ id: i.id, approval_status: i.approval_status }))];
    if (sql.includes('SELECT tm.default_image_url')) {
      if (defaultColumn === false) throw dbError('ER_BAD_FIELD_ERROR', 1054);
      if (defaultColumn === 'denied') throw dbError('ER_COLUMNACCESS_DENIED_ERROR', 1143);
      const ht = hts.find((h) => h.id === values[0] && h.hospital_id === values[1]);
      const m = ht ? master[ht.treatment_id] : undefined;
      return [m && m.active ? [{ default_image_url: m.url }] : []];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospital_treatment_images SET sort_order = ?')) {
      sortCount += 1;
      if (failSortAt === sortCount) throw dbError('ER_LOCK_DEADLOCK', 1213);
      const [sort, id, ht] = values as number[];
      const i = images.find((x) => x.id === id && x.ht === ht);
      if (i) i.sort_order = sort;
      return [{ affectedRows: i ? 1 : 0 }];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospital_treatment_images SET approval_status = ?')) {
      const [status, reason, by, id, ht] = values as [string, string | null, string, number, number];
      const i = images.find((x) => x.id === id && x.ht === ht && x.approval_status === 'pending');
      if (i) Object.assign(i, { approval_status: status, reject_reason: reason, reviewed_at: '2026-11-17 10:00:00', reviewed_by: by });
      return [{ affectedRows: i ? 1 : 0 }];
    }
    throw new Error(`unexpected sql: ${sql}`);
  };
  const roStub = { query: jest.fn(async (sql: string, values: unknown[] = []) => { roCalls.push({ sql, values }); return handler(sql, values); }) };
  const rwStub = {
    get: () => (!hasRw ? null : {
      query: jest.fn(),
      getConnection: async () => ({
        query: async (sql: string, values: unknown[] = []) => { rwCalls.push({ sql, values }); return handler(sql, values); },
        beginTransaction: async () => { txEvents.push('BEGIN'); snapshot = images.map((i) => ({ ...i })); },
        commit: async () => { txEvents.push('COMMIT'); snapshot = null; },
        rollback: async () => { txEvents.push('ROLLBACK'); if (snapshot) images = snapshot; snapshot = null; },
        release: () => { txEvents.push('RELEASE'); },
      }),
    }),
  };

  async function boot(): Promise<void> {
    // loaded only now: the upload parser must see the private temporary directory
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const { HospitalsModule } = require('./hospitals.module');
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, HospitalsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  }

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  const KEYS = ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_TREATMENTS', 'ADMIN_HOSPITALS_TREATMENTS_IMAGES', 'ADMIN_UPLOAD_ROOT'];
  const img = (id: number, ht: number, sort: number, over: Partial<Img> = {}): Img => ({
    id, ht, hospital_id: 12, treatment_id: 10, image_url: `uploads/treatments/treat_1_0000000${id % 10}.jpg`, caption: `c${id}`, sort_order: sort,
    approval_status: 'approved', uploaded_by: 'admin', reject_reason: null, reviewed_at: null, reviewed_by: null, ...over,
  });
  beforeEach(async () => {
    jest.spyOn(Logger.prototype, 'log').mockImplementation((m: any) => { logLines.push(String(m)); });
    jest.spyOn(Logger.prototype, 'warn').mockImplementation((m: any) => { logLines.push(String(m)); });
    jest.spyOn(Logger.prototype, 'error').mockImplementation(() => undefined);
    root = await fs.mkdtemp(path.join(os.tmpdir(), 'sb-tri-root-'));
    process.env.ADMIN_UPLOAD_ROOT = root;
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_WRITE = '1';
    process.env.ADMIN_HOSPITALS_TREATMENTS = '1';
    process.env.ADMIN_HOSPITALS_TREATMENTS_IMAGES = '1';
    roCalls.length = 0; rwCalls.length = 0; txEvents.length = 0; logLines.length = 0;
    hospitals = { 12: { status: 'approved' }, 13: { status: 'approved' }, 14: { status: 'pending' } };
    hts = [
      { id: 100, hospital_id: 12, treatment_id: 10, is_active: 1 },
      { id: 101, hospital_id: 12, treatment_id: 11, is_active: 1 },
      { id: 102, hospital_id: 12, treatment_id: 12, is_active: 0 },
      { id: 200, hospital_id: 13, treatment_id: 10, is_active: 1 },
    ];
    images = [img(1, 100, 0), img(2, 100, 1, { approval_status: 'pending', uploaded_by: 'hospital' }), img(3, 100, 2, { approval_status: 'rejected', uploaded_by: 'hospital', reject_reason: '흐림', reviewed_at: '2026-11-16 09:00:00', reviewed_by: '김관리' }), img(9, 200, 0)];
    mode = null; hasRw = true; imagesTable = true; nextId = 900; failInsert = false; failSortAt = null; sortCount = 0; failDelete = false; snapshot = null;
    defaultColumn = true;
    master = { 10: { url: 'https://cdn.example.com/default-10.jpg', active: true }, 11: { url: '  ', active: true }, 12: { url: 'https://cdn.example.com/d12.jpg', active: false } };
    await boot();
  });
  afterEach(async () => {
    await app.close();
    jest.restoreAllMocks();
    await fs.rm(root, { recursive: true, force: true });
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of KEYS) delete process.env[k];
    delete process.env.TMPDIR;
    fsSync.rmSync(privateTmp, { recursive: true, force: true });
  });

  const base = (id: number | string = 12, ht: number | string = 100) => `/v1/hospitals/${id}/treatments/${ht}/images`;
  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };
  const json = (method: 'post' | 'delete', p: string, body?: unknown, cookie: string | undefined = SUPER, headers: Record<string, string> = { ...MARK, 'Content-Type': 'application/json' }) => {
    let r = request(app.getHttpServer())[method](p);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return body === undefined ? r : r.send(JSON.stringify(body));
  };
  const remove = (imageId: number | string, id: number | string = 12, ht: number | string = 100, cookie?: string, headers?: Record<string, string>) => json('delete', `${base(id, ht)}/${imageId}`, undefined, cookie, headers);
  const move = (imageId: number | string, body: unknown, id: number | string = 12, ht: number | string = 100, cookie?: string) => json('post', `${base(id, ht)}/${imageId}/move`, body, cookie);
  const review = (imageId: number | string, body: unknown, id: number | string = 12, ht: number | string = 100, cookie?: string) => json('post', `${base(id, ht)}/${imageId}/review`, body, cookie);
  /** multipart POST; `fields` are text fields, `file` the optional `image` part */
  const add = (fields: Record<string, string> = {}, file?: { data: Buffer; name: string; field?: string }, cookie: string | undefined = SUPER, id: number | string = 12, ht: number | string = 100, headers: Record<string, string> = MARK) => {
    let r = request(app.getHttpServer()).post(base(id, ht));
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    if (Object.keys(fields).length === 0 && !file) r = r.field('noop', '');
    for (const [k, v] of Object.entries(fields)) r = r.field(k, v);
    if (file) r = r.attach(file.field ?? 'image', file.data, file.name);
    return r;
  };
  const writes = () => rwCalls.filter((c) => /^(INSERT|UPDATE|DELETE)/.test(c.sql));
  const stored = async (): Promise<string[]> => fs.readdir(path.join(root, 'uploads', 'treatments')).catch(() => []);
  const leftovers = () => fsSync.readdirSync(privateTmp).filter((n) => /^[0-9a-f]{32}$/.test(n));
  const failRw = (prefix: string, err: Error): (() => void) => {
    const real = rwStub.get;
    rwStub.get = () => {
      const pool = real()!;
      return { ...pool, getConnection: async () => { const c = await pool.getConnection(); return { ...c, query: async (sql: string, values: any) => { if (sql.startsWith(prefix)) throw err; return c.query(sql, values); } } as any; } };
    };
    return () => { rwStub.get = real; };
  };

  describe('switches, /v1/me, CSRF and guards', () => {
    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITALS_TREATMENTS_IMAGES=%p -> every image route 404 (database not asked), /v1/me flag false', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_TREATMENTS_IMAGES; else process.env.ADMIN_HOSPITALS_TREATMENTS_IMAGES = v;
      for (const r of [await get(base(), SUPER), await add({ imageUrl: 'https://a.com/a.jpg' }), await remove(1), await move(1, { direction: 'up' }), await review(2, { decision: 'approve' })]) {
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
      }
      expect(rwCalls).toHaveLength(0);
      expect(roCalls).toHaveLength(0);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatmentsImages).toBe(false);
    });

    it.each(['ADMIN_HOSPITALS_TREATMENTS', 'ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_PANEL_ENABLED'])('needs %s as well', async (k) => {
      process.env[k] = '0';
      expect((await get(base(), SUPER)).status).toBe(404);
      expect((await add({ imageUrl: 'https://a.com/a.jpg' })).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
      if (k !== 'ADMIN_PANEL_ENABLED') expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatmentsImages).toBe(false);
    });

    it('all on -> /v1/me reports the flag', async () => {
      expect((await get('/v1/me', SUPER)).body.features).toMatchObject({ hospitalsTreatments: true, hospitalsTreatmentsImages: true });
    });

    it('CSRF: no marker header, wrong content type or foreign Origin -> 403 csrf, nothing stored; GET needs none', async () => {
      const bad = [
        await add({ imageUrl: 'https://a.com/a.jpg' }, undefined, SUPER, 12, 100, {}),
        await add({ imageUrl: 'https://a.com/a.jpg' }, undefined, SUPER, 12, 100, { ...MARK, Origin: 'https://evil.example' }),
        await json('post', base(), { imageUrl: 'https://a.com/a.jpg' }),
        await remove(1, 12, 100, SUPER, { 'Content-Type': 'application/json' }),
        await remove(1, 12, 100, SUPER, { ...MARK, 'Content-Type': 'multipart/form-data; boundary=x' }),
        await json('post', `${base()}/1/move`, { direction: 'down' }, SUPER, { ...MARK, 'Content-Type': 'text/plain' }),
        await json('post', `${base()}/2/review`, { decision: 'approve' }, SUPER, { 'Content-Type': 'application/json' }),
      ];
      for (const r of bad) {
        expect(r.status).toBe(403);
        expect(r.body).toEqual({ error: 'csrf' });
      }
      expect(rwCalls).toHaveLength(0);
      expect(leftovers()).toEqual([]);
      expect((await get(base(), SUPER)).status).toBe(200);
    });

    it('not signed in -> 401 on every route (after the switch and CSRF), nothing stored', async () => {
      const calls = [await get(base()), await add({ imageUrl: 'https://a.com/a.jpg' }, undefined, 'PHPSESSID=nobody'), await remove(1, 12, 100, 'PHPSESSID=nobody'),
        await move(1, { direction: 'up' }, 12, 100, 'PHPSESSID=nobody'), await review(2, { decision: 'approve' }, 12, 100, 'PHPSESSID=nobody')];
      for (const r of calls) expect(r.status).toBe(401);
      expect(rwCalls).toHaveLength(0);
    });

    it('unknown / foreign / malformed ids -> 404 everywhere; a hospital role only reaches its own hospital', async () => {
      for (const [id, ht] of [[999, 100], [12, 999], [12, 200], [13, 100], [14, 100], ['abc', 100], [12, 'x'], [0, 100], [12, 0]] as const) {
        for (const r of [await get(base(id, ht), SUPER), await add({ imageUrl: 'https://a.com/a.jpg' }, undefined, SUPER, id, ht), await remove(1, id, ht), await move(1, { direction: 'down' }, id, ht), await review(2, { decision: 'approve' }, id, ht)]) {
          expect([id, ht, r.status, r.body]).toEqual([id, ht, 404, { error: 'not_found' }]);
        }
      }
      for (const r of [await get(base(13, 200), HOSPITAL), await add({ imageUrl: 'https://a.com/a.jpg' }, undefined, HOSPITAL, 13, 200), await remove(9, 13, 200, HOSPITAL), await move(9, { direction: 'down' }, 13, 200, HOSPITAL), await review(9, { decision: 'approve' }, 13, 200, HOSPITAL)]) {
        expect(r.status).toBe(404);
      }
      expect(writes()).toHaveLength(0);
    });
  });

  describe('GET list', () => {
    it('lists every status in sort order with the review flag; writes nothing', async () => {
      const r = await get(base(), SUPER);
      expect(r.status).toBe(200);
      expect(Object.keys(r.body).sort()).toEqual(['canReview', 'defaultImageUrl', 'items']);
      expect(r.body.defaultImageUrl).toBe('https://cdn.example.com/default-10.jpg');
      expect(r.body.canReview).toBe(true);
      expect(r.body.items.map((i: any) => [i.id, i.status])).toEqual([[1, 'approved'], [2, 'pending'], [3, 'rejected']]);
      expect(r.body.items[2]).toEqual({ id: 3, url: 'uploads/treatments/treat_1_00000003.jpg', caption: 'c3', status: 'rejected', uploadedBy: 'hospital', rejectReason: '흐림', reviewedAt: '2026-11-16 09:00:00', reviewedBy: '김관리', sortOrder: 2 });
      expect(rwCalls).toHaveLength(0);
      expect(r.headers['cache-control']).toBe('private, no-store');
    });

    it('a hospital account sees all statuses of its own hospital but cannot review; a released assignment is still readable', async () => {
      const r = await get(base(), HOSPITAL);
      expect(r.status).toBe(200);
      expect(r.body.canReview).toBe(false);
      expect(r.body.items).toHaveLength(3);
      expect((await get(base(12, 102), SUPER)).body).toEqual({ items: [], canReview: true, defaultImageUrl: null });
    });

    it('defaultImageUrl: blank or inactive master -> null; a missing column or grant -> null (200, never 503)', async () => {
      expect((await get(base(12, 101), SUPER)).body.defaultImageUrl).toBeNull();
      expect((await get(base(12, 102), SUPER)).body.defaultImageUrl).toBeNull();
      for (const v of [false, 'denied'] as const) {
        defaultColumn = v;
        const r = await get(base(), SUPER);
        expect([r.status, r.body.defaultImageUrl, r.body.items.length]).toEqual([200, null, 3]);
      }
      expect(rwCalls).toHaveLength(0);
    });

    it('a missing table or grant -> 503 images_unavailable', async () => {
      imagesTable = false;
      const r = await get(base(), SUPER);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'images_unavailable' });
    });
  });

  describe('POST add', () => {
    it('super admin + address: approved / admin, next sort order, caption trimmed, answer = item + whole list', async () => {
      const r = await add({ imageUrl: ' https://cdn.example.com/a.jpg ', caption: '  시술 후  ' });
      expect(r.status).toBe(200);
      expect(r.body.item).toEqual({ id: 900, url: 'https://cdn.example.com/a.jpg', caption: '시술 후', status: 'approved', uploadedBy: 'admin', rejectReason: null, reviewedAt: null, reviewedBy: null, sortOrder: 3 });
      expect(r.body.items.map((i: any) => i.id)).toEqual([1, 2, 3, 900]);
      expect(txEvents).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      const ins = writes().find((c) => c.sql.startsWith('INSERT'))!;
      expect(ins.values).toEqual([100, 12, 10, 'https://cdn.example.com/a.jpg', '시술 후', 3, 'approved', 'admin']);
      expect(ins.sql).not.toContain('cdn.example.com');
      expect(rwCalls.some((c) => c.sql.includes('FOR UPDATE') && c.sql.includes('gangnamunni_hospital_treatments'))).toBe(true);
      expect(await stored()).toEqual([]);
    });

    it('hospital account: pending / hospital (the client cannot choose); the first image of an assignment gets sort 0', async () => {
      const r = await add({ imageUrl: 'https://cdn.example.com/b.png', status: 'approved', uploadedBy: 'admin' }, undefined, HOSPITAL, 12, 101);
      expect(r.status).toBe(200);
      expect(r.body.item).toMatchObject({ status: 'pending', uploadedBy: 'hospital', sortOrder: 0, caption: '' });
      expect(writes().find((c) => c.sql.startsWith('INSERT'))!.values).toEqual([101, 12, 11, 'https://cdn.example.com/b.png', '', 0, 'pending', 'hospital']);
    });

    it.each([['a.jpg', jpeg, 'jpg'], ['a.JPEG', jpeg, 'jpg'], ['a.png', png, 'png'], ['a.gif', gif, 'gif'], ['a.webp', webp, 'webp']] as const)('file %s -> stored in uploads/treatments as treat_<time>_<8 hex>.%s', async (name, make, ext) => {
      const data = make();
      const r = await add({ caption: 'x' }, { data, name });
      expect(r.status).toBe(200);
      expect(r.body.item.url).toMatch(new RegExp(`^uploads/treatments/treat_\\d{10}_[0-9a-f]{8}\\.${ext}$`));
      const file = path.join(root, r.body.item.url);
      expect((await fs.readFile(file)).equals(data)).toBe(true);
      expect((await fs.stat(file)).mode & 0o777).toBe(0o644);
      expect((await fs.stat(path.dirname(file))).mode & 0o777).toBe(0o755);
      expect(await stored()).toEqual([path.basename(file)]);
      expect(leftovers()).toEqual([]);
    });

    it('a file wins over the address (a bad address is not even looked at); two uploads get different names', async () => {
      const a = await add({ imageUrl: 'ftp://nonsense' }, { data: jpeg(), name: 'a.jpg' });
      const b = await add({ imageUrl: 'https://cdn.example.com/x.jpg' }, { data: jpeg(), name: 'a.jpg' });
      expect(a.status).toBe(200);
      expect(b.status).toBe(200);
      expect(a.body.item.url).toMatch(/^uploads\/treatments\//);
      expect(b.body.item.url).toMatch(/^uploads\/treatments\//);
      expect(a.body.item.url).not.toBe(b.body.item.url);
      expect(await stored()).toHaveLength(2);
    });

    it('neither file nor address -> 400 image_required; a blank address counts as none', async () => {
      for (const fields of [{} as Record<string, string>, { imageUrl: '' }, { imageUrl: '   ' }, { caption: 'only caption' }]) {
        const r = await add(fields);
        expect(r.status).toBe(400);
        expect(r.body).toEqual({ error: 'image_required' });
      }
      expect(writes()).toHaveLength(0);
    });

    it.each(['ftp://example.com/a.jpg', 'javascript:alert(1)', 'data:image/png;base64,AAAA', 'example.com/a.jpg', 'https://', 'https:///a.jpg', 'http://exa mple.com/a.jpg', `https://example.com/${'a'.repeat(2100)}`])('address %p -> 400 invalid_url, nothing stored', async (url) => {
      const r = await add({ imageUrl: url });
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'invalid_url' });
      expect(writes()).toHaveLength(0);
    });

    it('caption over 200 characters -> 400 caption_too_long (the file is not kept); exactly 200 is fine', async () => {
      const long = await add({ imageUrl: 'https://cdn.example.com/a.jpg', caption: '가'.repeat(201) });
      expect(long.status).toBe(400);
      expect(long.body).toEqual({ error: 'caption_too_long' });
      const withFile = await add({ caption: '가'.repeat(201) }, { data: jpeg(), name: 'a.jpg' });
      expect(withFile.status).toBe(400);
      expect(withFile.body).toEqual({ error: 'caption_too_long' });
      expect(writes()).toHaveLength(0);
      expect(await stored()).toEqual([]);
      expect(leftovers()).toEqual([]);
      expect((await add({ imageUrl: 'https://cdn.example.com/a.jpg', caption: '가'.repeat(200) })).status).toBe(200);
    });

    it('repeated caption / address fields -> 400 upload_failed', async () => {
      const r = await request(app.getHttpServer()).post(base()).set('Cookie', SUPER).set(MARK).field('imageUrl', 'https://a.com/a.jpg').field('caption', 'one').field('caption', 'two');
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'upload_failed' });
      const r2 = await request(app.getHttpServer()).post(base()).set('Cookie', SUPER).set(MARK).field('imageUrl', 'https://a.com/a.jpg').field('imageUrl', 'https://a.com/b.jpg');
      expect(r2.status).toBe(400);
      expect(writes()).toHaveLength(0);
    });

    it('wrong content (extension or real type) -> 400 invalid_image; nothing written, no temporary file left', async () => {
      const cases: [string, Buffer][] = [['a.svg', Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"/>')], ['a.jpg', png()], ['a.png', jpeg()], ['a.gif', jpeg()], ['a.jpg', Buffer.from('plain text pretending to be a picture')],
        ['a.php', jpeg()], ['a.jpg.php', jpeg()], ['noextension', jpeg()], ['a.bmp', jpeg()], ['a.jpg', Buffer.alloc(0)]];
      for (const [name, data] of cases) {
        const r = await add({}, { data, name });
        expect([name, r.status, r.body]).toEqual([name, 400, { error: 'invalid_image' }]);
      }
      expect(writes()).toHaveLength(0);
      expect(await stored()).toEqual([]);
      expect(leftovers()).toEqual([]);
    });

    it('over 10 MB -> 400 too_large; exactly 10 MB is accepted', async () => {
      const big = Buffer.concat([jpeg(), Buffer.alloc(10 * 1024 * 1024)]);
      const r = await add({}, { data: big, name: 'a.jpg' });
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'too_large' });
      expect(writes()).toHaveLength(0);
      expect(await stored()).toEqual([]);
      expect(leftovers()).toEqual([]);
      const edge = Buffer.concat([jpeg(), Buffer.alloc(10 * 1024 * 1024 - jpeg().length)]);
      expect(edge.length).toBe(10 * 1024 * 1024);
      expect((await add({}, { data: edge, name: 'a.jpg' })).status).toBe(200);
    });

    it('an unknown file field or a second file -> 400 upload_failed', async () => {
      const a = await add({}, { data: jpeg(), name: 'a.jpg', field: 'photo' });
      expect(a.status).toBe(400);
      expect(a.body).toEqual({ error: 'upload_failed' });
      const b = await request(app.getHttpServer()).post(base()).set('Cookie', SUPER).set(MARK).attach('image', jpeg(), 'a.jpg').attach('image', jpeg(), 'b.jpg');
      expect(b.status).toBe(400);
      expect(b.body).toEqual({ error: 'upload_failed' });
      expect(writes()).toHaveLength(0);
      expect(await stored()).toEqual([]);
      expect(leftovers()).toEqual([]);
    });

    it('management rule: a hospital account under admin_only -> 403 forbidden_treatments before the body is judged; super admin unaffected', async () => {
      mode = 'admin_only';
      const r = await add({}, undefined, HOSPITAL);
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'forbidden_treatments' });
      expect(writes()).toHaveLength(0);
      const f = await add({}, { data: jpeg(), name: 'a.jpg' }, HOSPITAL);
      expect(f.status).toBe(403);
      expect(await stored()).toEqual([]);
      expect(leftovers()).toEqual([]);
      expect((await add({ imageUrl: 'https://cdn.example.com/a.jpg' }, undefined, SUPER)).status).toBe(200);
      mode = 'hospital_only';
      expect((await add({ imageUrl: 'https://cdn.example.com/b.jpg' }, undefined, HOSPITAL)).status).toBe(200);
    });

    it('a failing INSERT -> full rollback, the written file is removed, no temporary file left', async () => {
      failInsert = true;
      const r = await add({ caption: 'x' }, { data: jpeg(), name: 'a.jpg' });
      expect(r.status).toBe(500);
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      expect(images.map((i) => i.id)).toEqual([1, 2, 3, 9]);
      expect(await stored()).toEqual([]);
      expect(leftovers()).toEqual([]);
    });

    it('a failing INSERT keeps the files of earlier uploads untouched', async () => {
      const ok = await add({}, { data: jpeg(), name: 'a.jpg' });
      expect(ok.status).toBe(200);
      failInsert = true;
      expect((await add({}, { data: png(), name: 'b.png' })).status).toBe(500);
      expect(await stored()).toEqual([path.basename(ok.body.item.url)]);
    });

    it('missing write grants or write pool -> 503 write_unavailable, nothing changes', async () => {
      const restore = failRw('INSERT INTO gangnamunni_hospital_treatment_images', dbError('ER_TABLEACCESS_DENIED_ERROR', 1142, 'INSERT command denied'));
      const r = await add({}, { data: jpeg(), name: 'a.jpg' });
      restore();
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      expect(images).toHaveLength(4);
      expect(await stored()).toEqual([]);
      expect(leftovers()).toEqual([]);
      hasRw = false;
      const r2 = await add({ imageUrl: 'https://cdn.example.com/a.jpg' });
      expect(r2.status).toBe(503);
      expect(r2.body).toEqual({ error: 'write_unavailable' });
    });

    it.each(['', 'relative/path', '/nonexistent/sb-root'])('upload root %p: a file -> 503 uploads_unavailable (nothing written), an address still works', async (value) => {
      process.env.ADMIN_UPLOAD_ROOT = value;
      const r = await add({}, { data: jpeg(), name: 'a.jpg' });
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(writes()).toHaveLength(0);
      expect(leftovers()).toEqual([]);
      expect((await add({ imageUrl: 'https://cdn.example.com/a.jpg' })).status).toBe(200);
    });

    it('a folder that cannot be written -> 503 uploads_unavailable and the row is rolled back', async () => {
      await fs.mkdir(path.join(root, 'uploads'));
      await fs.writeFile(path.join(root, 'uploads', 'treatments'), 'a file where the folder should be');
      const r = await add({}, { data: jpeg(), name: 'a.jpg' });
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(images).toHaveLength(4);
      expect(txEvents).toContain('ROLLBACK');
      expect(leftovers()).toEqual([]);
    });

    it('the log line names admin, hospital, treatment and image only (no address, no caption)', async () => {
      await add({ imageUrl: 'https://secret-host.example.com/private.jpg', caption: 'SECRET CAPTION' });
      expect(logLines).toContain('hospital treatments: admin=7 hospital=12 image add treatment=100 image=900');
      expect(logLines.join('\n')).not.toMatch(/secret/i);
    });
  });

  describe('DELETE', () => {
    it('deletes the row and then its uploaded file; answers the remaining list', async () => {
      const up = await add({}, { data: jpeg(), name: 'a.jpg' });
      const file = path.join(root, up.body.item.url);
      expect(fsSync.existsSync(file)).toBe(true);
      rwCalls.length = 0; txEvents.length = 0;
      const r = await remove(up.body.item.id);
      expect(r.status).toBe(200);
      expect(Object.keys(r.body)).toEqual(['items']);
      expect(r.body.items.map((i: any) => i.id)).toEqual([1, 2, 3]);
      expect(fsSync.existsSync(file)).toBe(false);
      expect(txEvents).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      expect(writes().map((c) => c.sql.split(' ').slice(0, 3).join(' '))).toEqual(['DELETE FROM gangnamunni_hospital_treatment_images']);
      expect(writes()[0].values).toEqual([up.body.item.id, 100]);
      expect(logLines).toContain(`hospital treatments: admin=7 hospital=12 image delete treatment=100 image=${up.body.item.id}`);
    });

    it('an address image: row removed, no file touched; a file outside uploads/treatments is never removed', async () => {
      const outside = path.join(root, 'uploads', 'hospitals', '12');
      await fs.mkdir(outside, { recursive: true });
      await fs.writeFile(path.join(outside, 'keep.jpg'), 'keep');
      await fs.mkdir(path.join(root, 'uploads', 'treatments'), { recursive: true });
      await fs.writeFile(path.join(root, 'uploads', 'treatments', 'keep2.jpg'), 'keep');
      images.push(img(50, 100, 5, { image_url: 'https://cdn.example.com/a.jpg' }), img(51, 100, 6, { image_url: 'uploads/hospitals/12/keep.jpg' }), img(52, 100, 7, { image_url: 'uploads/treatments/../hospitals/12/keep.jpg' }), img(53, 100, 8, { image_url: '/etc/hostname' }));
      for (const id of [50, 51, 52, 53]) expect((await remove(id)).status).toBe(200);
      expect(images.filter((i) => i.ht === 100).map((i) => i.id)).toEqual([1, 2, 3]);
      expect(fsSync.existsSync(path.join(outside, 'keep.jpg'))).toBe(true);
      expect(fsSync.existsSync(path.join(root, 'uploads', 'treatments', 'keep2.jpg'))).toBe(true);
    });

    it('a file that is already gone or cannot be removed never fails the request', async () => {
      images.push(img(60, 100, 5, { image_url: 'uploads/treatments/treat_1_deadbeef.jpg' }));
      expect((await remove(60)).status).toBe(200);
      const up = await add({}, { data: jpeg(), name: 'a.jpg' });
      const spy = jest.spyOn(fs, 'unlink').mockRejectedValue(Object.assign(new Error('denied'), { code: 'EACCES' }));
      const r = await remove(up.body.item.id);
      spy.mockRestore();
      expect(r.status).toBe(200);
      expect(images.some((i) => i.id === up.body.item.id)).toBe(false);
      expect(logLines.some((l) => l.includes('could not remove a treatment image file: EACCES'))).toBe(true);
    });

    it('unknown image or an image of another assignment -> 404; nothing deleted', async () => {
      for (const id of [999, 9, 'abc', 0]) {
        const r = await remove(id);
        expect([id, r.status, r.body]).toEqual([id, 404, { error: 'not_found' }]);
      }
      expect(images).toHaveLength(4);
      expect(writes()).toHaveLength(0);
    });

    it('management rule: 403 forbidden_treatments for a hospital account under admin_only; a failing DELETE rolls back and keeps the file', async () => {
      mode = 'admin_only';
      const r = await remove(1, 12, 100, HOSPITAL);
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'forbidden_treatments' });
      mode = null;
      const up = await add({}, { data: jpeg(), name: 'a.jpg' });
      failDelete = true;
      const f = await remove(up.body.item.id);
      expect(f.status).toBe(503);
      expect(f.body).toEqual({ error: 'write_unavailable' });
      expect(images.some((i) => i.id === up.body.item.id)).toBe(true);
      expect(fsSync.existsSync(path.join(root, up.body.item.url))).toBe(true);
    });
  });

  describe('POST move', () => {
    const order = () => sorted(100).map((i) => [i.id, i.sort_order]);
    const mixed = () => { images = [img(1, 100, 0), img(2, 100, 1, { approval_status: 'pending' }), img(3, 100, 2), img(4, 100, 3, { approval_status: 'rejected' }), img(5, 100, 4)]; };

    it('moves among the approved only; pending / rejected keep their relative places; all numbered 0..n-1', async () => {
      mixed();
      const r = await move(3, { direction: 'up' });
      expect(r.status).toBe(200);
      expect(Object.keys(r.body)).toEqual(['items']);
      expect(r.body.items.map((i: any) => [i.id, i.sortOrder])).toEqual([[3, 0], [2, 1], [1, 2], [4, 3], [5, 4]]);
      expect(txEvents).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      expect(writes().map((c) => c.values)).toEqual([[0, 3, 100], [1, 2, 100], [2, 1, 100], [3, 4, 100], [4, 5, 100]]);
      expect(logLines).toContain('hospital treatments: admin=7 hospital=12 image move-up treatment=100 image=3');
      const d = await move(3, { direction: 'down' });
      expect(d.body.items.map((i: any) => i.id)).toEqual([1, 2, 3, 4, 5]);
      const e = await move(1, { direction: 'down' });
      expect(e.body.items.map((i: any) => [i.id, i.status])).toEqual([[3, 'approved'], [2, 'pending'], [1, 'approved'], [4, 'rejected'], [5, 'approved']]);
    });

    it('the neighbour is the next approved picture even when others lie between', async () => {
      mixed();
      const r = await move(1, { direction: 'down' });
      expect(r.body.items.map((i: any) => i.id)).toEqual([3, 2, 1, 4, 5]);
      const l = await move(5, { direction: 'up' });
      expect(l.body.items.map((i: any) => i.id)).toEqual([3, 2, 5, 4, 1]);
    });

    it('a pending or rejected picture -> 409 not_approved, nothing written', async () => {
      mixed();
      for (const id of [2, 4]) for (const direction of ['up', 'down']) {
        const r = await move(id, { direction });
        expect([id, direction, r.status, r.body]).toEqual([id, direction, 409, { error: 'not_approved' }]);
      }
      expect(writes()).toHaveLength(0);
      expect(order().map((o) => o[0])).toEqual([1, 2, 3, 4, 5]);
    });

    it('first / last approved picture -> 409 cannot_move with no change', async () => {
      mixed();
      for (const [id, direction] of [[1, 'up'], [5, 'down']] as const) {
        const r = await move(id, { direction });
        expect([id, direction, r.status, r.body]).toEqual([id, direction, 409, { error: 'cannot_move' }]);
      }
      expect(writes()).toHaveLength(0);
      expect(txEvents.filter((e) => e === 'ROLLBACK')).toHaveLength(2);
    });

    it('a single approved picture cannot move either way', async () => {
      images = [img(1, 100, 0), img(2, 100, 1, { approval_status: 'pending' })];
      expect((await move(1, { direction: 'up' })).body).toEqual({ error: 'cannot_move' });
      expect((await move(1, { direction: 'down' })).status).toBe(409);
    });

    it('bad direction -> 400 invalid_field; unknown / foreign image -> 404', async () => {
      for (const body of [{}, { direction: 'left' }, { direction: 1 }, []]) {
        const r = await move(2, body);
        expect([r.status, r.body]).toEqual([400, { error: 'invalid_field', field: 'direction' }]);
      }
      for (const id of [999, 9, 'x']) expect((await move(id, { direction: 'up' })).status).toBe(404);
      expect(writes()).toHaveLength(0);
    });

    it('a failing second UPDATE -> rollback, the old order stays', async () => {
      images = [img(1, 100, 0), img(2, 100, 1), img(3, 100, 2)];
      failSortAt = 2;
      const r = await move(3, { direction: 'up' });
      expect(r.status).toBe(500);
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      expect(order()).toEqual([[1, 0], [2, 1], [3, 2]]);
    });

    it('management rule: hospital under admin_only -> 403; a hospital account is allowed under both', async () => {
      images = [img(1, 100, 0), img(2, 100, 1)];
      mode = 'admin_only';
      expect((await move(2, { direction: 'up' }, 12, 100, HOSPITAL)).status).toBe(403);
      mode = 'both';
      expect((await move(2, { direction: 'up' }, 12, 100, HOSPITAL)).status).toBe(200);
    });
  });

  describe('POST review', () => {
    it('approve: super admin; status approved, reviewer + time set, reason cleared', async () => {
      const r = await review(2, { decision: 'approve' });
      expect(r.status).toBe(200);
      expect(r.body.item).toEqual({ id: 2, url: 'uploads/treatments/treat_1_00000002.jpg', caption: 'c2', status: 'approved', uploadedBy: 'hospital', rejectReason: null, reviewedAt: '2026-11-17 10:00:00', reviewedBy: '김관리', sortOrder: 1 });
      expect(r.body.items.map((i: any) => i.id)).toEqual([1, 2, 3]);
      expect(writes()[0].values).toEqual(['approved', null, '김관리', 2, 100]);
      expect(writes()[0].sql).toContain("approval_status = 'pending'");
      expect(logLines).toContain('hospital treatments: admin=7 hospital=12 image approve treatment=100 image=2');
    });

    it('reject: reason trimmed and stored; approving again -> 409 already_reviewed', async () => {
      const r = await review(2, { decision: 'reject', reason: '  해상도가 낮아요  ' });
      expect(r.status).toBe(200);
      expect(r.body.item).toMatchObject({ status: 'rejected', rejectReason: '해상도가 낮아요', reviewedBy: '김관리' });
      expect(writes()[0].values).toEqual(['rejected', '해상도가 낮아요', '김관리', 2, 100]);
      const again = await review(2, { decision: 'approve' });
      expect(again.status).toBe(409);
      expect(again.body).toEqual({ error: 'already_reviewed' });
      expect(images.find((i) => i.id === 2)!.approval_status).toBe('rejected');
    });

    it('already approved, already rejected, or not existing -> 409 already_reviewed', async () => {
      for (const id of [1, 3, 999, 9]) {
        const r = await review(id, { decision: 'approve' });
        expect([id, r.status, r.body]).toEqual([id, 409, { error: 'already_reviewed' }]);
      }
      expect(images.find((i) => i.id === 3)!.reject_reason).toBe('흐림');
    });

    it('a hospital account -> 403 forbidden_review (also when it may manage), but another hospital is still 404', async () => {
      const r = await review(2, { decision: 'approve' }, 12, 100, HOSPITAL);
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'forbidden_review' });
      expect(writes()).toHaveLength(0);
      expect((await review(9, { decision: 'approve' }, 13, 200, HOSPITAL)).status).toBe(404);
    });

    it('reject needs a reason (400 reason_required); 1000 characters are fine, 1001 -> 400 reason_too_long', async () => {
      for (const body of [{ decision: 'reject' }, { decision: 'reject', reason: '' }, { decision: 'reject', reason: '   ' }, { decision: 'reject', reason: null }]) {
        const r = await review(2, body);
        expect([r.status, r.body]).toEqual([400, { error: 'reason_required' }]);
      }
      const long = await review(2, { decision: 'reject', reason: '가'.repeat(1001) });
      expect([long.status, long.body]).toEqual([400, { error: 'reason_too_long' }]);
      expect(writes()).toHaveLength(0);
      expect((await review(2, { decision: 'reject', reason: '가'.repeat(1000) })).status).toBe(200);
    });

    it('bad decision or reason type -> 400 invalid_field', async () => {
      for (const [body, field] of [[{}, 'decision'], [{ decision: 'maybe' }, 'decision'], [[], 'decision'], [{ decision: 'reject', reason: 5 }, 'reason']] as const) {
        const r = await review(2, body);
        expect([r.status, r.body]).toEqual([400, { error: 'invalid_field', field }]);
      }
    });

    it('a failing UPDATE -> rollback, the image stays pending', async () => {
      const restore = failRw('UPDATE gangnamunni_hospital_treatment_images SET approval_status', dbError('ER_LOCK_DEADLOCK', 1213));
      const r = await review(2, { decision: 'approve' });
      restore();
      expect(r.status).toBe(500);
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      expect(images.find((i) => i.id === 2)!.approval_status).toBe('pending');
    });

    it('unknown assignment -> 404; missing write grant -> 503 write_unavailable', async () => {
      expect((await review(2, { decision: 'approve' }, 12, 999)).status).toBe(404);
      const restore = failRw('UPDATE gangnamunni_hospital_treatment_images SET approval_status', dbError('ER_COLUMNACCESS_DENIED_ERROR', 1143));
      const r = await review(2, { decision: 'approve' });
      restore();
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
    });
  });

  describe('SQL is parametrised', () => {
    it('no statement carries a client value inside its text', async () => {
      await add({ imageUrl: "https://cdn.example.com/INJECT'-OR-1=1.jpg", caption: "x'); DROP TABLE a; --" });
      await review(900, { decision: 'reject', reason: "r'; DELETE FROM x; --" });
      for (const c of [...rwCalls, ...roCalls]) {
        expect(c.sql).not.toMatch(/INJECT|DROP TABLE|DELETE FROM x/);
        expect((c.sql.match(/\?/g) || []).length).toBe(c.values.length);
      }
    });
  });
});
