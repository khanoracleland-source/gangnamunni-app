import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import { promises as fs, readdirSync } from 'fs';
import * as os from 'os';
import * as path from 'path';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { HospitalApplyModule } from './hospital-apply.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const MP = { 'X-Requested-With': 'admin-next' };

const jpeg = (size = 64) => Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(Math.max(0, size - 4), 1)]);
const png = (size = 64) => Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(Math.max(0, size - 8), 2)]);
const pdf = (size = 64) => Buffer.concat([Buffer.from('%PDF-1.4'), Buffer.alloc(Math.max(0, size - 8), 3)]);
const gif = (size = 64) => Buffer.concat([Buffer.from('GIF89a'), Buffer.alloc(Math.max(0, size - 6), 4)]);

type Call = { sql: string; values: unknown[] };
type Attach = { field: string; data: Buffer; name: string };

describe('admin API: hospital apply', () => {
  let app: INestApplication;
  let root: string;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const rwCalls: Call[] = [];
  const txEvents: string[] = [];
  let nextId: number;
  let failOn: string | null;
  let hasRw = true;
  let devMode = false;

  const handler = async (sql: string, values: unknown[] = []): Promise<unknown> => {
    if (sql.startsWith('SELECT id, name FROM gangnamunni_treatment_categories')) return [[{ id: 1, name: '피부' }, { id: 2, name: '쓰이지 않는 분류' }, { id: 3, name: '성형' }]];
    if (sql.startsWith('SELECT id, name, category_id FROM gangnamunni_treatment_master')) return [[{ id: 10, name: '보톡스', category_id: 1 }, { id: 11, name: '코필러', category_id: 3 }]];
    if (sql.startsWith('SELECT name FROM gangnamunni_treatment_units')) return [[{ name: '회' }, { name: 'cc' }]];
    if (sql.startsWith('SELECT setting_value FROM gangnamunni_settings')) return [devMode ? [{ setting_value: 'on' }] : []];
    if (sql.startsWith('SELECT id, name, phone FROM gangnamunni_hospitals')) return [[{ id: 3, name: '라온의원', phone: '02-555-1234' }, { id: 4, name: '무전화', phone: '' }]];
    if (sql.startsWith('SELECT id FROM gangnamunni_treatment_master')) return [(values as number[]).filter((id) => id === 10 || id === 11).map((id) => ({ id }))];
    if (failOn && sql.includes(failOn)) throw Object.assign(new Error('boom'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
    if (sql.startsWith('INSERT INTO gangnamunni_hospitals')) return [{ insertId: nextId }];
    if (sql.startsWith('INSERT INTO gangnamunni_doctors')) return [{ insertId: 900 + rwCalls.filter((c) => c.sql.startsWith('INSERT INTO gangnamunni_doctors')).length }];
    if (sql.startsWith('INSERT INTO gangnamunni_hospital_treatments ')) return [{ insertId: 700 + rwCalls.filter((c) => c.sql.startsWith('INSERT INTO gangnamunni_hospital_treatments ')).length }];
    if (/^(INSERT|UPDATE)/.test(sql)) return [{ insertId: 1, affectedRows: 1 }];
    throw new Error(`unexpected sql: ${sql}`);
  };
  const roStub = { query: jest.fn(async (sql: string, values: unknown[] = []) => handler(sql, values)) };
  const rwStub = {
    get: () => (!hasRw ? null : {
      query: jest.fn(),
      getConnection: async () => ({
        query: async (sql: string, values: unknown[] = []) => { rwCalls.push({ sql, values }); return handler(sql, values); },
        beginTransaction: async () => { txEvents.push('BEGIN'); },
        commit: async () => { txEvents.push('COMMIT'); },
        rollback: async () => { txEvents.push('ROLLBACK'); },
        release: () => { txEvents.push('RELEASE'); },
      }),
    }),
  };

  const tmpHex = (): string[] => readdirSync(os.tmpdir()).filter((n) => /^[0-9a-f]{32}$/.test(n));
  let tmpBefore: string[];

  async function boot(): Promise<void> {
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, HospitalApplyModule] }).compile();
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
    root = await fs.mkdtemp(path.join(os.tmpdir(), 'sb-apply-test-'));
    process.env.ADMIN_UPLOAD_ROOT = root;
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITAL_APPLY_ENABLED = '1';
    phpCalls.length = 0; rwCalls.length = 0; txEvents.length = 0;
    nextId = 321;
    failOn = null;
    hasRw = true;
    devMode = false;
    tmpBefore = tmpHex();
    await boot();
  });
  afterEach(async () => {
    await app.close();
    jest.restoreAllMocks();
    await fs.rm(root, { recursive: true, force: true });
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITAL_APPLY_ENABLED', 'ADMIN_UPLOAD_ROOT']) delete process.env[k];
  });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };
  const post = (payload: unknown, files: Attach[] = [], cookie: string | undefined = SUPER, headers: Record<string, string> = MP) => {
    let r = request(app.getHttpServer()).post('/v1/hospital-apply');
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    if (payload !== undefined) r = r.field('payload', typeof payload === 'string' ? payload : JSON.stringify(payload));
    for (const f of files) r = r.attach(f.field, f.data, f.name);
    return r;
  };
  const writes = () => rwCalls.filter((c) => /^(INSERT|UPDATE)/.test(c.sql));
  const allFiles = async (): Promise<string[]> => {
    const out: string[] = [];
    const walk = async (dir: string): Promise<void> => {
      for (const e of await fs.readdir(dir, { withFileTypes: true }).catch(() => [])) {
        if (e.isDirectory()) await walk(path.join(dir, e.name));
        else out.push(path.relative(root, path.join(dir, e.name)));
      }
    };
    await walk(root);
    return out.sort();
  };
  const noLeftovers = () => expect(tmpHex().filter((n) => !tmpBefore.includes(n))).toEqual([]);

  describe('switch, /v1/me, CSRF and roles', () => {
    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITAL_APPLY_ENABLED=%p -> every route 404, PHP and database not asked, flag false', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITAL_APPLY_ENABLED; else process.env.ADMIN_HOSPITAL_APPLY_ENABLED = v;
      for (const r of [await post({ name: 'a' }), await get('/v1/hospital-apply/options', SUPER), await get('/v1/hospital-apply/phone-check?phone=1', SUPER)]) {
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
      }
      expect(rwCalls).toHaveLength(0);
      expect(roStub.query).not.toHaveBeenCalled();
      expect((await get('/v1/me', SUPER)).body.features.hospitalApply).toBe(false);
      noLeftovers();
    });

    it('on -> /v1/me reports hospitalApply', async () => {
      expect((await get('/v1/me', SUPER)).body.features.hospitalApply).toBe(true);
    });

    it('needs the master switch too', async () => {
      process.env.ADMIN_PANEL_ENABLED = '0';
      expect((await post({ name: 'a' })).status).toBe(404);
    });

    it('CSRF: no marker header / JSON content type / foreign Origin -> 403, nothing stored', async () => {
      expect((await post({ name: 'a' }, [], SUPER, {})).status).toBe(403);
      const json = await request(app.getHttpServer()).post('/v1/hospital-apply').set('Cookie', SUPER).set('X-Requested-With', 'admin-next').send({ payload: '{}' });
      expect(json.status).toBe(403);
      expect((await post({ name: 'a' }, [], SUPER, { ...MP, Origin: 'https://evil.example' })).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('not signed in -> 401; a hospital role -> 403; neither writes anything', async () => {
      expect((await post({ name: 'a' }, [{ field: 'profileImage', data: jpeg(), name: 'a.jpg' }], 'PHPSESSID=nobody')).status).toBe(401);
      const h = await post({ name: 'a' }, [], HOSPITAL);
      expect(h.status).toBe(403);
      expect(h.body).toEqual({ error: 'forbidden' });
      expect((await get('/v1/hospital-apply/options', HOSPITAL)).status).toBe(403);
      expect((await get('/v1/hospital-apply/options')).status).toBe(401);
      expect(rwCalls).toHaveLength(0);
      expect(await allFiles()).toEqual([]);
      noLeftovers();
    });
  });

  describe('lists and the phone question', () => {
    it('options: used categories only, active treatments, units, dev mode, limits', async () => {
      devMode = true;
      const r = await get('/v1/hospital-apply/options', SUPER);
      expect(r.status).toBe(200);
      expect(r.body.categories).toEqual([{ id: 1, name: '피부' }, { id: 3, name: '성형' }]);
      expect(r.body.treatments).toEqual([{ id: 10, name: '보톡스', categoryId: 1 }, { id: 11, name: '코필러', categoryId: 3 }]);
      expect(r.body.units).toEqual(['cc', '회']);
      expect(r.body.devMode).toBe(true);
      expect(r.body.limits).toEqual({ interiorMax: 10, certMaxMb: 5, imageMaxMb: 5, interiorMaxMb: 10, treatmentImageMaxMb: 10, doctorPhotoMaxMb: 5 });
    });

    it('phone-check: any formatting of a used number is a duplicate and names the hospital', async () => {
      for (const p of ['02-555-1234', '025551234', '+82 2 555 1234']) {
        expect((await get(`/v1/hospital-apply/phone-check?phone=${encodeURIComponent(p)}`, SUPER)).body).toEqual({ duplicate: true, hospitalName: '라온의원' });
      }
      expect((await get('/v1/hospital-apply/phone-check?phone=010-1111-2222', SUPER)).body).toEqual({ duplicate: false });
    });

    it.each(['', '%20', 'abc', '1'.repeat(21)])('phone-check with %p -> 400 invalid_field', async (p) => {
      const r = await get(`/v1/hospital-apply/phone-check?phone=${p}`, SUPER);
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'invalid_field', field: 'phone' });
    });

    it('phone-check without the parameter -> 400', async () => {
      expect((await get('/v1/hospital-apply/phone-check', SUPER)).status).toBe(400);
    });
  });

  describe('registering', () => {
    it('only a name: one hospital row, approved and inactive, committed', async () => {
      const r = await post({ name: '새 병원' });
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ id: 321, warnings: [] });
      expect(txEvents).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      const w = writes();
      expect(w).toHaveLength(1);
      expect(w[0].sql).toContain("'approved', 0, 'none'");
      expect(w[0].values).toContain('새 병원');
      expect(w[0].values).toContain('boss');
      expect(w[0].values).toContain(0);
      expect(await allFiles()).toEqual([]);
      noLeftovers();
    });

    it('dev mode on -> the hospital is a demo one', async () => {
      devMode = true;
      await post({ name: 'a' });
      expect(writes()[0].values[writes()[0].values.length - 1]).toBe(1);
    });

    it('everything: rows in order, files in their places, skipped price rows reported', async () => {
      const r = await post(
        {
          name: '전체 병원', phone: '0212345678', email: 'a@b.co', lat: 37.5, lng: 127.02, businessHours: { 월: '10:00~19:00' },
          treatments: [{ treatmentId: 10, prices: [{ optionName: 'A', dosageValue: 1, dosageUnit: '회', price: 10000 }, { optionName: 'B', dosageValue: 0, dosageUnit: '회', price: 5 }] }, { treatmentId: 11 }],
          doctors: [{ name: '' }, { name: '김의사', specialty: '피부', experienceYears: 5 }],
        },
        [
          { field: 'certBusiness', data: pdf(), name: 'b.pdf' },
          { field: 'certMedical', data: png(), name: 'm.png' },
          { field: 'profileImage', data: jpeg(), name: 'p.jpg' },
          { field: 'interiorImages', data: jpeg(), name: 'i1.jpg' },
          { field: 'interiorImages', data: png(), name: 'i2.png' },
          { field: 'doctorPhoto_1', data: jpeg(), name: 'd.jpg' },
          { field: 'doctorPhoto_0', data: jpeg(), name: 'ignored.jpg' },
          { field: 'treatmentImages_10', data: gif(), name: 't.gif' },
        ],
      );
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ id: 321, warnings: [{ code: 'price_rows_skipped', count: 1 }] });
      expect(txEvents).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      const sqls = writes().map((c) => /^(?:INSERT INTO|UPDATE) \w+/.exec(c.sql)![0]);
      expect(sqls).toEqual([
        'INSERT INTO gangnamunni_hospitals',
        'INSERT INTO hospital_images',
        'INSERT INTO hospital_images',
        'INSERT INTO hospital_images',
        'INSERT INTO gangnamunni_doctors',
        'UPDATE gangnamunni_doctors',
        'INSERT INTO gangnamunni_hospital_treatments',
        'INSERT INTO gangnamunni_hospital_treatment_prices',
        'INSERT INTO gangnamunni_hospital_treatment_images',
        'INSERT INTO gangnamunni_hospital_treatments',
      ]);
      const hospital = writes()[0].values as unknown[];
      expect(hospital).toContain('02-1234-5678');
      expect(hospital.filter((v) => typeof v === 'string' && /^uploads\/hospital_apply\/admin_7\/(profile|cert_business|cert_medical)_[0-9a-f]{32}\.(jpg|png|pdf)$/.test(v))).toHaveLength(4);
      const files = await allFiles();
      expect(files.filter((f) => f.startsWith('uploads/hospital_apply/admin_7/'))).toHaveLength(5);
      expect(files.filter((f) => /^uploads\/hospitals\/321\/doctor_901_[0-9a-f]{32}\.jpg$/.test(f))).toHaveLength(1);
      expect(files.filter((f) => /^uploads\/treatments\/treat_\d+_[0-9a-f]{8}\.gif$/.test(f))).toHaveLength(1);
      expect(files).toHaveLength(7);
      const imageRow = writes().find((c) => c.sql.startsWith('INSERT INTO gangnamunni_hospital_treatment_images'))!;
      expect(imageRow.values.slice(0, 3)).toEqual([701, 321, 10]);
      expect(imageRow.values.slice(-2)).toEqual(['pending', 'hospital']);
      noLeftovers();
    });

    it('validation errors: nothing is written, no connection is taken', async () => {
      for (const [payload, field] of [[{ name: '' }, 'name'], [{ name: 'a', email: 'bad' }, 'email'], [{ name: 'a', website: 'javascript:x' }, 'website']] as const) {
        const r = await post(payload, [{ field: 'profileImage', data: jpeg(), name: 'p.jpg' }]);
        expect(r.status).toBe(400);
        expect(r.body).toEqual({ error: 'invalid_field', field });
      }
      expect((await post('not json')).body).toEqual({ error: 'invalid_body' });
      expect((await request(app.getHttpServer()).post('/v1/hospital-apply').set('Cookie', SUPER).set('X-Requested-With', 'admin-next').attach('profileImage', jpeg(), 'p.jpg')).body).toEqual({ error: 'invalid_body' });
      expect(txEvents).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
      expect(await allFiles()).toEqual([]);
      noLeftovers();
    });

    it('a used phone number -> 409 with the hospital name, before the files are looked at', async () => {
      const r = await post({ name: 'a', phone: '025551234' }, [{ field: 'profileImage', data: png(), name: 'p.jpg' }]);
      expect(r.status).toBe(409);
      expect(r.body).toEqual({ error: 'duplicate_phone', hospitalName: '라온의원' });
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('the phone duplicate comes before the e-mail check, the name before both', async () => {
      expect((await post({ name: 'a', phone: '025551234', email: 'bad' })).status).toBe(409);
      expect((await post({ name: '', phone: '025551234', email: 'bad' })).body).toEqual({ error: 'invalid_field', field: 'name' });
      expect((await post({ name: 'a', phone: '010-1', email: 'bad' })).body).toEqual({ error: 'invalid_field', field: 'email' });
    });

    it('a treatment that is not in the active master list -> 400', async () => {
      const r = await post({ name: 'a', treatments: [{ treatmentId: 10 }, { treatmentId: 99 }] });
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'invalid_field', field: 'treatments', index: 1 });
      expect(rwCalls).toHaveLength(0);
    });

    it('file problems name the field: wrong type 415, too large 413, too many 400', async () => {
      const wrong = await post({ name: 'a' }, [{ field: 'profileImage', data: png(), name: 'p.jpg' }]);
      expect(wrong.status).toBe(415);
      expect(wrong.body).toEqual({ error: 'bad_file_type', field: 'profileImage' });
      const big = await post({ name: 'a' }, [{ field: 'profileImage', data: jpeg(6 * 1024 * 1024), name: 'p.jpg' }]);
      expect(big.status).toBe(413);
      expect(big.body).toEqual({ error: 'file_too_large', field: 'profileImage' });
      const huge = await post({ name: 'a' }, [{ field: 'certBusiness', data: pdf(11 * 1024 * 1024), name: 'c.pdf' }]);
      expect(huge.status).toBe(413);
      expect(huge.body).toEqual({ error: 'file_too_large', field: 'certBusiness' });
      const many = await post({ name: 'a' }, Array.from({ length: 11 }, (_, i) => ({ field: 'interiorImages', data: jpeg(), name: `i${i}.jpg` })));
      expect(many.status).toBe(400);
      expect(many.body).toEqual({ error: 'too_many', field: 'interiorImages' });
      const two = await post({ name: 'a' }, [{ field: 'profileImage', data: jpeg(), name: 'a.jpg' }, { field: 'profileImage', data: jpeg(), name: 'b.jpg' }]);
      expect(two.body).toEqual({ error: 'too_many', field: 'profileImage' });
      expect(rwCalls).toHaveLength(0);
      expect(await allFiles()).toEqual([]);
      noLeftovers();
    });

    it('a field the form does not have, and images of a treatment that is not in the payload -> 400', async () => {
      expect((await post({ name: 'a' }, [{ field: 'evil', data: jpeg(), name: 'a.jpg' }])).status).toBe(400);
      const r = await post({ name: 'a', treatments: [{ treatmentId: 10 }] }, [{ field: 'treatmentImages_11', data: jpeg(), name: 'a.jpg' }]);
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'invalid_field', field: 'treatmentImages_11' });
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it.each(['INSERT INTO gangnamunni_doctors', 'INSERT INTO gangnamunni_hospital_treatment_prices', 'UPDATE gangnamunni_doctors', 'INSERT INTO gangnamunni_hospital_treatment_images'])(
      'a failure at %s -> everything rolled back and every file removed',
      async (failing) => {
        failOn = failing;
        const r = await post(
          { name: 'a', doctors: [{ name: 'D' }], treatments: [{ treatmentId: 10, prices: [{ dosageValue: 1, dosageUnit: '회', price: 5 }] }] },
          [
            { field: 'profileImage', data: jpeg(), name: 'p.jpg' },
            { field: 'certMedical', data: pdf(), name: 'm.pdf' },
            { field: 'doctorPhoto_0', data: jpeg(), name: 'd.jpg' },
            { field: 'treatmentImages_10', data: jpeg(), name: 't.jpg' },
          ],
        );
        expect(r.status).toBe(500);
        expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
        expect(await allFiles()).toEqual([]);
        noLeftovers();
      },
    );

    it('no write user -> 503 write_unavailable and nothing left on disk', async () => {
      hasRw = false;
      const r = await post({ name: 'a' }, [{ field: 'profileImage', data: jpeg(), name: 'p.jpg' }]);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      expect(await allFiles()).toEqual([]);
      noLeftovers();
    });

    it('an unusable upload root -> 503 uploads_unavailable, nothing written', async () => {
      process.env.ADMIN_UPLOAD_ROOT = path.join(root, 'missing');
      const r = await post({ name: 'a' }, [{ field: 'profileImage', data: jpeg(), name: 'p.jpg' }]);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('a request without files does not need the upload root', async () => {
      delete process.env.ADMIN_UPLOAD_ROOT;
      expect((await post({ name: 'a' })).status).toBe(200);
    });
  });
});
