import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { HospitalsModule } from './hospitals.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const JSON_HDR = { 'X-Requested-With': 'admin-next', 'Content-Type': 'application/json' };

type Call = { sql: string; values: unknown[] };
type Ht = { id: number; hospital_id: number; treatment_id: number; is_promoted: number; note: string; registered_by: string; status: string; is_active: number; min_age: number | null };
type Price = { hospital_treatment_id: number; option_name: string; body_part: string; dosage_value: number; dosage_unit: string; regular_price: number; price: number; is_representative: number };
type Img = { hospital_treatment_id: number; approval_status: string };
const dbError = (code: string, errno: number, message = code) => Object.assign(new Error(message), { code, errno });

describe('admin API: hospital treatments (list, assign, promotion, minimum age, remove)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const roCalls: Call[] = [];
  const rwCalls: Call[] = [];
  const txEvents: string[] = [];
  let hospitals: Record<number, { status: string }>;
  let master: { id: number; name: string; hashtag: string; category_id: number | null; is_active: number; sort_order: number }[];
  let categories: { id: number; name: string; sort_order: number }[];
  let hts: Ht[];
  let prices: Price[];
  let images: Img[];
  let questions: { hospital_treatment_id: number; question: string }[];
  let questionTextsReadable: boolean;
  let failQuestionInsertAt: number | null;
  let questionInsertCount: number;
  let questionsWriteTable: boolean;
  let mode: string | null | 'ERROR';
  let minAgeColumn: boolean;
  let pricesTable: boolean;
  let imagesTable: boolean;
  let questionsTable: boolean;
  let failInsertAt: number | null;
  let insertCount: number;
  let nextId: number;
  let hasRw = true;
  let unitsRows: string[] | 'ERROR';
  let bodyPartRows: { gid: number; group_name: string; part_name: string }[] | 'ERROR';
  let priceColumns: boolean;
  let failPriceInsertAt: number | null;
  let priceInsertCount: number;
  let snapshot: { hts: Ht[]; prices: Price[]; images: Img[]; questions: { hospital_treatment_id: number; question: string }[] } | null = null;

  const copy = <T>(a: T[]): T[] => a.map((x) => ({ ...x }));
  const cat = (id: number | null) => categories.find((c) => c.id === id);
  const inValues = (values: unknown[]) => values as number[];

  const handler = async (sql: string, values: unknown[] = []): Promise<unknown> => {
    if (sql.startsWith('SELECT `value` FROM settings')) {
      if (mode === 'ERROR') throw dbError('ER_TABLEACCESS_DENIED_ERROR', 1142);
      return [mode === null ? [] : [{ value: mode }]];
    }
    if (sql.startsWith('SELECT id FROM gangnamunni_hospitals WHERE id = ?')) {
      const h = hospitals[values[0] as number];
      const okStatus = !sql.includes("status = 'approved'") || h?.status === 'approved';
      return [h && okStatus ? [{ id: values[0] }] : []];
    }
    if (sql.startsWith('SELECT id, updated_at FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE')) return [hospitals[values[0] as number] ? [{ id: values[0], updated_at: 'x' }] : []];
    if (sql.startsWith('SELECT ht.id, ht.treatment_id')) {
      if (sql.includes('ht.min_age') && !minAgeColumn) throw dbError('ER_BAD_FIELD_ERROR', 1054, "Unknown column 'ht.min_age' in 'field list'");
      const rows = hts.filter((h) => h.hospital_id === values[0] && h.status === 'active' && h.is_active === 1).map((h) => {
        const m = master.find((x) => x.id === h.treatment_id);
        return { ...h, treatment_name: m?.name ?? null, hashtag: m?.hashtag ?? null, category_id: m?.category_id ?? null, category_name: cat(m?.category_id ?? null)?.name ?? null, _sort: [cat(m?.category_id ?? null)?.sort_order ?? -1, m?.sort_order ?? -1, m?.name ?? ''] as [number, number, string] };
      });
      rows.sort((a, b) => a._sort[0] - b._sort[0] || a._sort[1] - b._sort[1] || a._sort[2].localeCompare(b._sort[2]) || a.id - b.id);
      const withAge = sql.includes('ht.min_age');
      return [rows.map(({ _sort, min_age, ...r }) => (withAge ? { ...r, min_age } : r))];
    }
    if (sql.includes('FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id IN')) {
      if (!pricesTable) throw dbError('ER_NO_SUCH_TABLE', 1146);
      return [prices.filter((p) => inValues(values).includes(p.hospital_treatment_id))];
    }
    if (sql.includes('FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id IN')) {
      if (!imagesTable) throw dbError('ER_COLUMNACCESS_DENIED_ERROR', 1143);
      const out = inValues(values).filter((id) => images.some((i) => i.hospital_treatment_id === id)).map((id) => {
        const mine = images.filter((i) => i.hospital_treatment_id === id);
        return { hospital_treatment_id: id, cnt: mine.length, pending: mine.filter((i) => i.approval_status === 'pending').length };
      });
      return [out];
    }
    if (sql.startsWith('SELECT hospital_treatment_id, question FROM gangnamunni_treatment_questions')) {
      if (!questionTextsReadable) throw dbError('ER_COLUMNACCESS_DENIED_ERROR', 1143);
      return [questions.filter((q) => inValues(values).includes(q.hospital_treatment_id))];
    }
    if (sql.startsWith('DELETE FROM gangnamunni_treatment_questions')) {
      if (!questionsWriteTable) throw dbError('ER_NO_SUCH_TABLE', 1146);
      const n = questions.filter((q) => q.hospital_treatment_id === values[0]).length;
      questions = questions.filter((q) => q.hospital_treatment_id !== values[0]);
      return [{ affectedRows: n }];
    }
    if (sql.startsWith('INSERT INTO gangnamunni_treatment_questions')) {
      if (!questionsWriteTable) throw dbError('ER_NO_SUCH_TABLE', 1146);
      questionInsertCount += 1;
      if (failQuestionInsertAt === questionInsertCount) throw dbError('ER_LOCK_DEADLOCK', 1213);
      const [htId, question] = values as [number, string];
      questions.push({ hospital_treatment_id: htId, question });
      return [{ insertId: 1, affectedRows: 1 }];
    }
    if (sql.startsWith('SELECT hospital_treatment_id, COUNT(*) AS cnt FROM gangnamunni_treatment_questions')) {
      if (!questionsTable) throw dbError('ER_NO_SUCH_TABLE', 1146);
      return [inValues(values).filter((id) => questions.some((q) => q.hospital_treatment_id === id)).map((id) => ({ hospital_treatment_id: id, cnt: questions.filter((q) => q.hospital_treatment_id === id).length }))];
    }
    if (sql.startsWith('SELECT tm.id, tm.name, tm.hashtag')) {
      const hid = values[0] as number;
      const taken = new Set(hts.filter((h) => h.hospital_id === hid && h.is_active === 1 && ['active', 'pending'].includes(h.status)).map((h) => h.treatment_id));
      return [master.filter((m) => m.is_active === 1 && !taken.has(m.id)).sort((a, b) => (cat(a.category_id)?.sort_order ?? -1) - (cat(b.category_id)?.sort_order ?? -1) || a.name.localeCompare(b.name))
        .map((m) => ({ id: m.id, name: m.name, hashtag: m.hashtag, category_id: m.category_id, category_name: cat(m.category_id)?.name ?? null }))];
    }
    if (sql.startsWith('SELECT id FROM gangnamunni_treatment_master WHERE is_active = 1 AND id IN')) return [master.filter((m) => m.is_active === 1 && inValues(values).includes(m.id)).map((m) => ({ id: m.id }))];
    if (sql.startsWith('SELECT id, treatment_id, status, is_active FROM gangnamunni_hospital_treatments WHERE hospital_id = ?')) {
      const [hid, ...ids] = values as number[];
      return [hts.filter((h) => h.hospital_id === hid && ids.includes(h.treatment_id)).sort((a, b) => a.id - b.id)];
    }
    if (sql.startsWith('INSERT INTO gangnamunni_hospital_treatments')) {
      insertCount += 1;
      if (failInsertAt === insertCount) throw dbError('ER_LOCK_DEADLOCK', 1213);
      const [hid, tid, by] = values as [number, number, string];
      hts.push({ id: nextId, hospital_id: hid, treatment_id: tid, is_promoted: 0, note: '', registered_by: by, status: 'active', is_active: 1, min_age: null });
      return [{ insertId: nextId++, affectedRows: 1 }];
    }
    if (sql.startsWith("UPDATE gangnamunni_hospital_treatments SET status = 'active', is_active = 1")) {
      const [id, hid] = values as number[];
      const row = hts.find((h) => h.id === id && h.hospital_id === hid);
      if (row) Object.assign(row, { status: 'active', is_active: 1, is_promoted: 0 });
      return [{ affectedRows: row ? 1 : 0 }];
    }
    if (sql.startsWith('SELECT id, is_active FROM gangnamunni_hospital_treatments WHERE id = ? AND hospital_id = ?')) {
      const [id, hid] = values as number[];
      const row = hts.find((h) => h.id === id && h.hospital_id === hid);
      return [row ? [{ id: row.id, is_active: row.is_active }] : []];
    }
    if (sql.startsWith('DELETE FROM gangnamunni_hospital_treatment_prices')) {
      if (!pricesTable) throw dbError('ER_NO_SUCH_TABLE', 1146);
      const n = prices.filter((p) => p.hospital_treatment_id === values[0]).length;
      prices = prices.filter((p) => p.hospital_treatment_id !== values[0]);
      return [{ affectedRows: n }];
    }
    if (sql.startsWith('DELETE FROM gangnamunni_hospital_treatment_images')) {
      const n = images.filter((p) => p.hospital_treatment_id === values[0]).length;
      images = images.filter((p) => p.hospital_treatment_id !== values[0]);
      return [{ affectedRows: n }];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospital_treatments SET is_active = 0')) {
      const [id, hid] = values as number[];
      const row = hts.find((h) => h.id === id && h.hospital_id === hid && h.is_active === 1);
      if (row) Object.assign(row, { is_active: 0, is_promoted: 0 });
      return [{ affectedRows: row ? 1 : 0 }];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospital_treatments SET is_promoted = ?')) {
      const [p, id, hid] = values as number[];
      const row = hts.find((h) => h.id === id && h.hospital_id === hid);
      if (row) row.is_promoted = p;
      return [{ affectedRows: row ? 1 : 0 }];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospital_treatments SET min_age = ?')) {
      if (!minAgeColumn) throw dbError('ER_BAD_FIELD_ERROR', 1054, "Unknown column 'min_age' in 'field list'");
      const [age, id, hid] = values as [number | null, number, number];
      const row = hts.find((h) => h.id === id && h.hospital_id === hid);
      if (row) row.min_age = age;
      return [{ affectedRows: row ? 1 : 0 }];
    }
    if (sql.includes('FROM gangnamunni_body_part_groups')) {
      if (bodyPartRows === 'ERROR') throw dbError('ER_NO_SUCH_TABLE', 1146);
      return [bodyPartRows];
    }
    if (sql.startsWith('SELECT name FROM gangnamunni_treatment_units')) {
      if (unitsRows === 'ERROR') throw dbError('ER_NO_SUCH_TABLE', 1146);
      return [unitsRows.map((name) => ({ name }))];
    }
    if (sql.startsWith('INSERT INTO gangnamunni_hospital_treatment_prices')) {
      if (!priceColumns) throw dbError('ER_BAD_FIELD_ERROR', 1054, "Unknown column 'regular_price' in 'field list'");
      priceInsertCount += 1;
      if (failPriceInsertAt === priceInsertCount) throw dbError('ER_LOCK_DEADLOCK', 1213);
      const [htId, name, part, dv, unit, reg, price, rep] = values as [number, string, string, number, string, number, number, number];
      prices.push({ hospital_treatment_id: htId, option_name: name, body_part: part, dosage_value: dv, dosage_unit: unit, regular_price: reg, price, is_representative: rep });
      return [{ insertId: 1, affectedRows: 1 }];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospital_treatments SET price_min = 0, price_max = 0, dosage_value = NULL')) return [{ affectedRows: 1 }];
    throw new Error(`unexpected sql: ${sql}`);
  };
  const roStub = { query: jest.fn(async (sql: string, values: unknown[] = []) => { roCalls.push({ sql, values }); return handler(sql, values); }) };
  const rwStub = {
    get: () => (!hasRw ? null : {
      query: jest.fn(),
      getConnection: async () => ({
        query: async (sql: string, values: unknown[] = []) => { rwCalls.push({ sql, values }); return handler(sql, values); },
        beginTransaction: async () => { txEvents.push('BEGIN'); snapshot = { hts: copy(hts), prices: copy(prices), images: copy(images), questions: copy(questions) }; },
        commit: async () => { txEvents.push('COMMIT'); snapshot = null; },
        rollback: async () => { txEvents.push('ROLLBACK'); if (snapshot) ({ hts, prices, images, questions } = snapshot); snapshot = null; },
        release: () => { txEvents.push('RELEASE'); },
      }),
    }),
  };

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
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_WRITE = '1';
    process.env.ADMIN_HOSPITALS_TREATMENTS = '1';
    process.env.ADMIN_HOSPITALS_TREATMENTS_REMOVE = '1';
    delete process.env.ADMIN_HOSPITALS_TREATMENTS_PRICES;
    delete process.env.ADMIN_HOSPITALS_TREATMENTS_QUESTIONS;
    delete process.env.ADMIN_HOSPITALS_TREATMENTS_IMAGES;
    questionTextsReadable = true; failQuestionInsertAt = null; questionInsertCount = 0; questionsWriteTable = true;
    bodyPartRows = [{ gid: 1, group_name: '얼굴', part_name: '이마' }, { gid: 1, group_name: '얼굴', part_name: '자갈턱' }, { gid: 2, group_name: '몸', part_name: '손바닥' }, { gid: 2, group_name: '몸', part_name: '이마' }, { gid: 2, group_name: '몸', part_name: 'a||b' }];
    unitsRows = ['회', 'ml', 'IU', 'Box']; priceColumns = true; failPriceInsertAt = null; priceInsertCount = 0;
    phpCalls.length = 0; roCalls.length = 0; rwCalls.length = 0; txEvents.length = 0;
    hospitals = { 12: { status: 'approved' }, 13: { status: 'approved' }, 14: { status: 'pending' } };
    categories = [{ id: 1, name: '주사', sort_order: 1 }, { id: 2, name: '레이저', sort_order: 2 }];
    master = [
      { id: 10, name: '보톡스', hashtag: '#보톡스', category_id: 1, is_active: 1, sort_order: 1 },
      { id: 11, name: '필러', hashtag: '#필러', category_id: 1, is_active: 1, sort_order: 2 },
      { id: 20, name: '레이저토닝', hashtag: '#토닝', category_id: 2, is_active: 1, sort_order: 1 },
      { id: 30, name: '중지됨', hashtag: '', category_id: null, is_active: 0, sort_order: 1 },
    ];
    hts = [
      { id: 100, hospital_id: 12, treatment_id: 20, is_promoted: 1, note: 'n', registered_by: 'hospital', status: 'active', is_active: 1, min_age: 19 },
      { id: 101, hospital_id: 12, treatment_id: 10, is_promoted: 0, note: '', registered_by: 'admin', status: 'active', is_active: 1, min_age: null },
      { id: 102, hospital_id: 12, treatment_id: 11, is_promoted: 0, note: '', registered_by: 'admin', status: 'active', is_active: 0, min_age: null },
      { id: 200, hospital_id: 13, treatment_id: 10, is_promoted: 0, note: '', registered_by: 'admin', status: 'active', is_active: 1, min_age: null },
    ];
    prices = [
      { hospital_treatment_id: 101, option_name: '', body_part: '이마', dosage_value: 50, dosage_unit: '유닛', regular_price: 100000, price: 80000, is_representative: 1 },
      { hospital_treatment_id: 100, option_name: '', body_part: '', dosage_value: 1, dosage_unit: '회', regular_price: 50000, price: 50000, is_representative: 0 },
    ];
    images = [{ hospital_treatment_id: 101, approval_status: 'approved' }, { hospital_treatment_id: 101, approval_status: 'pending' }];
    questions = [{ hospital_treatment_id: 100, question: '임신 중이세요?' }, { hospital_treatment_id: 100, question: '알레르기가 있나요?' }];
    mode = null;
    minAgeColumn = true; pricesTable = true; imagesTable = true; questionsTable = true;
    failInsertAt = null; insertCount = 0; nextId = 900; hasRw = true; snapshot = null;
    await boot();
  });
  afterEach(async () => {
    await app.close();
    jest.restoreAllMocks();
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_TREATMENTS', 'ADMIN_HOSPITALS_TREATMENTS_REMOVE', 'ADMIN_HOSPITALS_TREATMENTS_PRICES', 'ADMIN_HOSPITALS_TREATMENTS_QUESTIONS', 'ADMIN_HOSPITALS_TREATMENTS_IMAGES']) delete process.env[k];
  });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };
  const send = (method: 'post' | 'patch' | 'delete', p: string, body?: unknown, cookie: string | undefined = SUPER, headers: Record<string, string> = JSON_HDR) => {
    let r = request(app.getHttpServer())[method](p);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return body === undefined ? r : r.send(JSON.stringify(body));
  };
  const assign = (id: number | string, ids: unknown, cookie?: string, headers?: Record<string, string>) => send('post', `/v1/hospitals/${id}/treatments`, { treatmentIds: ids }, cookie, headers);
  const patch = (id: number | string, ht: number | string, body: unknown, cookie?: string) => send('patch', `/v1/hospitals/${id}/treatments/${ht}`, body, cookie);
  const del = (id: number | string, ht: number | string, cookie?: string, headers?: Record<string, string>) => send('delete', `/v1/hospitals/${id}/treatments/${ht}`, undefined, cookie, headers);
  const writes = () => rwCalls.filter((c) => /^(INSERT|UPDATE|DELETE)/.test(c.sql));
  const failRw = (prefix: string, err: Error): (() => void) => {
    const real = rwStub.get;
    rwStub.get = () => {
      const pool = real()!;
      return { ...pool, getConnection: async () => { const c = await pool.getConnection(); return { ...c, query: async (sql: string, values: any) => { if (sql.startsWith(prefix)) throw err; return c.query(sql, values); } } as any; } };
    };
    return () => { rwStub.get = real; };
  };

  describe('switches, /v1/me, CSRF and guards', () => {
    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITALS_TREATMENTS=%p -> every route 404 (database not asked), flags false', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_TREATMENTS; else process.env.ADMIN_HOSPITALS_TREATMENTS = v;
      for (const r of [await get('/v1/hospitals/12/treatments', SUPER), await assign(12, [10]), await patch(12, 100, { isPromoted: true }), await del(12, 101)]) {
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
      }
      expect(rwCalls).toHaveLength(0);
      expect(roCalls).toHaveLength(0);
      const f = (await get('/v1/me', SUPER)).body.features;
      expect(f.hospitalsTreatments).toBe(false);
      expect(f.hospitalsTreatmentsRemove).toBe(false);
    });

    it.each(['ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_PANEL_ENABLED'])('needs %s as well', async (k) => {
      process.env[k] = '0';
      expect((await assign(12, [10])).status).toBe(404);
      expect((await get('/v1/hospitals/12/treatments', SUPER)).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
      if (k !== 'ADMIN_PANEL_ENABLED') expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatments).toBe(false);
    });

    it('REMOVE off: only DELETE is 404, everything else works; REMOVE without TREATMENTS stays off', async () => {
      process.env.ADMIN_HOSPITALS_TREATMENTS_REMOVE = '0';
      const d = await del(12, 101);
      expect(d.status).toBe(404);
      expect(writes()).toHaveLength(0);
      expect((await get('/v1/hospitals/12/treatments', SUPER)).body.removeEnabled).toBe(false);
      expect((await patch(12, 101, { isPromoted: true })).status).toBe(200);
      expect((await get('/v1/me', SUPER)).body.features).toMatchObject({ hospitalsTreatments: true, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false });
      process.env.ADMIN_HOSPITALS_TREATMENTS_REMOVE = '1';
      process.env.ADMIN_HOSPITALS_TREATMENTS = '0';
      expect((await get('/v1/me', SUPER)).body.features).toMatchObject({ hospitalsTreatments: false, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false });
      expect((await del(12, 101)).status).toBe(404);
    });

    it.each(['pending', 'rejected'])('a %s application: every treatment write is 404 and nothing is written (the list stays readable)', async (status) => {
      hospitals[14] = { status };
      hts.push({ id: 300, hospital_id: 14, treatment_id: 10, is_promoted: 0, note: '', registered_by: 'admin', status: 'active', is_active: 1, min_age: null });
      process.env.ADMIN_HOSPITALS_TREATMENTS_PRICES = '1';
      process.env.ADMIN_HOSPITALS_TREATMENTS_QUESTIONS = '1';
      const put = (path: string, body: unknown) => request(app.getHttpServer()).put(`/v1/hospitals/14/treatments/300/${path}`).set('Cookie', SUPER).set(JSON_HDR).send(JSON.stringify(body));
      const answers = [
        await assign(14, [11]),
        await patch(14, 300, { isPromoted: true }),
        await del(14, 300),
        await put('prices', { options: [] }),
        await put('questions', { questions: [] }),
      ];
      for (const r of answers) {
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
      }
      expect(writes()).toHaveLength(0);
      expect(rwCalls.filter((c) => c.sql.includes('gangnamunni_hospital_treatments'))).toHaveLength(0);
      expect((await get('/v1/hospitals/14/treatments', SUPER)).status).toBe(200);
    });

    it('all on -> /v1/me reports both flags and keeps the others', async () => {
      expect((await get('/v1/me', SUPER)).body.features).toEqual({
        hospitals: true, hospitalsWrite: true, hospitalsDelete: false, archive: false, hospitalsReview: false, hospitalsOrder: false, doctors: false, doctorsWrite: false, doctorsOrder: false, doctorsDelete: false, hospitalsImages: false, hospitalsDocuments: false, hospitalsDoctors: false, hospitalsTreatments: true, hospitalsTreatmentsRemove: true, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false, hospitalsTreatmentsImages: false, treatments: false, treatmentsWrite: false, treatmentsImages: false, treatmentUnits: false, treatmentUnitsWrite: false, hospitalApply: false, admins: false, adminsWrite: false, bookings: false, bookingsWrite: false, payments: false, moderation: false, moderationWrite: false, moderationEdit: false, moderationDelete: false, banners: false, bannersWrite: false, categoryBoard: false, categoryBoardWrite: false,
      });
    });

    it('CSRF: writes without the marker header or JSON type or with a foreign Origin -> 403 csrf, nothing stored; GET needs neither', async () => {
      const bad = [
        await assign(12, [10], SUPER, {}),
        await assign(12, [10], SUPER, { 'Content-Type': 'application/json' }),
        await assign(12, [10], SUPER, { 'X-Requested-With': 'admin-next', 'Content-Type': 'text/plain' }),
        await del(12, 101, SUPER, {}),
        await assign(12, [10], SUPER, { ...JSON_HDR, Origin: 'https://evil.example' }),
      ];
      for (const r of bad) {
        expect(r.status).toBe(403);
        expect(r.body).toEqual({ error: 'csrf' });
      }
      expect((await request(app.getHttpServer()).patch('/v1/hospitals/12/treatments/100').set('Cookie', SUPER).send({ isPromoted: true })).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
      expect((await get('/v1/hospitals/12/treatments', SUPER)).status).toBe(200);
    });

    it('not signed in -> 401 on every route, nothing stored', async () => {
      const nobody = 'PHPSESSID=nobody';
      for (const r of [await get('/v1/hospitals/12/treatments', nobody), await assign(12, [10], nobody), await patch(12, 100, { isPromoted: true }, nobody), await del(12, 101, nobody)]) expect(r.status).toBe(401);
      expect(rwCalls).toHaveLength(0);
    });
  });

  describe('GET /v1/hospitals/:id/treatments', () => {
    it('lists the active assignments in PHP order with price options, counts and the available rest', async () => {
      const r = await get('/v1/hospitals/12/treatments', SUPER);
      expect(r.status).toBe(200);
      expect(r.body.items.map((i: any) => i.id)).toEqual([101, 100]);
      expect(r.body.items[0]).toEqual({
        id: 101, treatmentId: 10, name: '보톡스', hashtag: '#보톡스', categoryId: 1, categoryName: '주사', isPromoted: false, note: '', registeredBy: 'admin', minAge: null,
        priceOptions: [{ optionName: '', bodyPart: '이마', dosageValue: '50', dosageUnit: '유닛', regularPrice: 100000, price: 80000, isRepresentative: true }],
        imageCount: 2, pendingImageCount: 1, questionCount: 0,
      });
      expect(r.body.items[1]).toMatchObject({ id: 100, isPromoted: true, registeredBy: 'hospital', minAge: 19, questionCount: 2, imageCount: 0 });
      expect(r.body).toMatchObject({ count: 2, promotedCount: 1, canManage: true, minAgeSupported: true, removeEnabled: true });
      // 11 was removed (is_active=0) so it is available again; 30 is inactive in the master
      expect(r.body.available).toEqual([{ id: 11, name: '필러', hashtag: '#필러', categoryId: 1, categoryName: '주사' }]);
      expect(Object.keys(r.body).sort()).toEqual(['available', 'canManage', 'count', 'imagesEnabled', 'items', 'minAgeSupported', 'priceBodyParts', 'priceUnits', 'pricesEnabled', 'promotedCount', 'questionsEnabled', 'removeEnabled']);
    });

    it('imagesEnabled follows ADMIN_HOSPITALS_TREATMENTS_IMAGES (off by default, only the exact value 1) and does not change the image counts', async () => {
      delete process.env.ADMIN_HOSPITALS_TREATMENTS_IMAGES;
      const off = await get('/v1/hospitals/12/treatments', SUPER);
      expect(off.body.imagesEnabled).toBe(false);
      for (const v of ['0', 'true', '']) {
        process.env.ADMIN_HOSPITALS_TREATMENTS_IMAGES = v;
        expect((await get('/v1/hospitals/12/treatments', SUPER)).body.imagesEnabled).toBe(false);
      }
      process.env.ADMIN_HOSPITALS_TREATMENTS_IMAGES = '1';
      const on = await get('/v1/hospitals/12/treatments', SUPER);
      expect(on.body.imagesEnabled).toBe(true);
      expect(on.body.items.find((i: any) => i.id === 101)).toMatchObject({ imageCount: 2, pendingImageCount: 1 });
      expect((await get('/v1/hospitals/12/treatments', HOSPITAL)).body.imagesEnabled).toBe(true);
      process.env.ADMIN_HOSPITALS_TREATMENTS = '0';
      expect((await get('/v1/hospitals/12/treatments', SUPER)).status).toBe(404);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatmentsImages).toBe(false);
    });

    it('writes nothing and uses only SELECT with parameters', async () => {
      await get('/v1/hospitals/12/treatments', SUPER);
      expect(rwCalls).toHaveLength(0);
      expect(roCalls.length).toBeGreaterThan(0);
      for (const c of roCalls) {
        expect(c.sql).toMatch(/^SELECT/);
        expect(c.sql).not.toMatch(/\b(INSERT|UPDATE|DELETE)\b/);
      }
      expect(txEvents).toEqual([]);
    });

    it('a hospital role sees only its own hospital; another id -> 404; unknown/malformed -> 404', async () => {
      expect((await get('/v1/hospitals/12/treatments', HOSPITAL)).status).toBe(200);
      const other = await get('/v1/hospitals/13/treatments', HOSPITAL);
      expect(other.status).toBe(404);
      expect(other.body).toEqual({ error: 'not_found' });
      for (const id of [999, 'abc', '0', '12abc']) expect((await get(`/v1/hospitals/${id}/treatments`, SUPER)).status).toBe(404);
    });

    it('a hospital that is not approved can still be read', async () => {
      const r = await get('/v1/hospitals/14/treatments', SUPER);
      expect(r.status).toBe(200);
      expect(r.body.items).toEqual([]);
    });

    it.each([[null, true], ['both', true], ['hospital_only', true], ['admin_only', false], ['weird', false], ['ERROR', false]] as [string | null, boolean][])(
      'treatment_mgmt_mode=%p -> hospital account canManage=%p (available only when it may)', async (m, ok) => {
        mode = m;
        const r = await get('/v1/hospitals/12/treatments', HOSPITAL);
        expect(r.body.canManage).toBe(ok);
        expect(r.body.available.length > 0).toBe(ok);
        const s = await get('/v1/hospitals/12/treatments', SUPER);
        expect(s.body.canManage).toBe(true);
        expect(s.body.available.length).toBeGreaterThan(0);
      });

    it('a super admin never reads the setting', async () => {
      mode = 'ERROR';
      await get('/v1/hospitals/12/treatments', SUPER);
      expect(roCalls.some((c) => c.sql.includes('FROM settings'))).toBe(false);
    });

    it('missing min_age column -> minAgeSupported false, minAge null, still 200', async () => {
      minAgeColumn = false;
      const r = await get('/v1/hospitals/12/treatments', SUPER);
      expect(r.status).toBe(200);
      expect(r.body.minAgeSupported).toBe(false);
      expect(r.body.items.map((i: any) => i.minAge)).toEqual([null, null]);
      expect(r.body.count).toBe(2);
    });

    it('missing price / image / question tables or rights -> those parts empty or 0, never 500', async () => {
      pricesTable = false; imagesTable = false; questionsTable = false;
      const r = await get('/v1/hospitals/12/treatments', SUPER);
      expect(r.status).toBe(200);
      for (const i of r.body.items) expect(i).toMatchObject({ priceOptions: [], imageCount: 0, pendingImageCount: 0, questionCount: 0 });
    });
  });

  describe('POST /v1/hospitals/:id/treatments (assign)', () => {
    it('inserts new ones, reactivates a removed one, skips active ones; one transaction', async () => {
      const r = await assign(12, [11, 10, 20]);
      expect(r.status).toBe(200);
      // 11 was removed before (reactivated), 10 and 20 are active (skipped)
      expect(r.body).toMatchObject({ added: 1, skipped: 2, count: 3 });
      expect(r.body.items.map((i: any) => i.treatmentId).sort()).toEqual([10, 11, 20]);
      expect(r.body.available).toEqual([]);
      expect(Object.keys(r.body).sort()).toEqual(['added', 'available', 'count', 'items', 'promotedCount', 'skipped']);
      expect(txEvents).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      expect(hts.find((h) => h.id === 102)).toMatchObject({ is_active: 1, status: 'active', is_promoted: 0 });
    });

    it('a new assignment: promotion 0, registered_by admin for a super admin and hospital for a hospital account; parameters only', async () => {
      const a = await assign(13, [20]);
      expect(a.body).toMatchObject({ added: 1, skipped: 0 });
      expect(hts.find((h) => h.hospital_id === 13 && h.treatment_id === 20)).toMatchObject({ registered_by: 'admin', is_promoted: 0, status: 'active', is_active: 1 });
      hts = hts.filter((h) => h.id !== 102);
      const own = await assign(12, [11], HOSPITAL);
      expect(own.body.added).toBe(1);
      expect(hts.find((h) => h.hospital_id === 12 && h.treatment_id === 11)).toMatchObject({ registered_by: 'hospital' });
      const ins = rwCalls.filter((c) => c.sql.startsWith('INSERT'));
      expect(ins).toHaveLength(2);
      expect(ins[0].sql).toContain('VALUES (?, ?, 0, 0, 0');
      expect(ins[0].values).toEqual([13, 20, 'admin']);
      expect(ins[1].values).toEqual([12, 11, 'hospital']);
    });

    it('duplicates in the request are inserted once; IN lists use placeholders', async () => {
      const r = await assign(13, [20, 20, 11, 20]);
      expect(r.body).toMatchObject({ added: 2, skipped: 0 });
      expect(hts.filter((h) => h.hospital_id === 13 && h.treatment_id === 20)).toHaveLength(1);
      const q = rwCalls.find((c) => c.sql.includes('treatment_master WHERE is_active = 1 AND id IN'));
      expect(q?.sql).toContain('IN (?,?)');
      expect(q?.values).toEqual([20, 11]);
    });

    it('reactivating clears old price options and images only where removal is switched on', async () => {
      prices.push({ hospital_treatment_id: 102, option_name: '', body_part: '', dosage_value: 1, dosage_unit: '회', regular_price: 1, price: 1, is_representative: 0 });
      images.push({ hospital_treatment_id: 102, approval_status: 'approved' });
      process.env.ADMIN_HOSPITALS_TREATMENTS_REMOVE = '0';
      expect((await assign(12, [11])).status).toBe(200);
      expect(rwCalls.some((c) => c.sql.startsWith('DELETE'))).toBe(false);
      expect(prices.some((p) => p.hospital_treatment_id === 102)).toBe(true);
      hts.find((h) => h.id === 102)!.is_active = 0;
      process.env.ADMIN_HOSPITALS_TREATMENTS_REMOVE = '1';
      expect((await assign(12, [11])).status).toBe(200);
      expect(prices.some((p) => p.hospital_treatment_id === 102)).toBe(false);
      expect(images.some((p) => p.hospital_treatment_id === 102)).toBe(false);
    });

    it('an unknown or inactive treatment id -> 400 invalid_treatment and nothing is written', async () => {
      for (const ids of [[10, 999], [30], [999]]) {
        const r = await assign(13, ids);
        expect(r.status).toBe(400);
        expect(r.body).toEqual({ error: 'invalid_treatment' });
      }
      expect(writes()).toHaveLength(0);
      expect(txEvents.filter((e) => e === 'COMMIT')).toHaveLength(0);
    });

    it.each([[[]], ['x'], [[0]], [[1.5]], [['10']], [[null]]])('treatmentIds %p -> 400 no_treatments', async (ids) => {
      const r = await assign(13, ids);
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'no_treatments' });
      expect(writes()).toHaveLength(0);
    });

    it('an empty body -> 400 no_treatments', async () => {
      const r = await send('post', '/v1/hospitals/13/treatments', {});
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'no_treatments' });
    });

    it('hospital account: another hospital -> 404; setting admin_only or unreadable -> 403 forbidden_treatments; a super admin is not bound by the setting', async () => {
      expect((await assign(13, [20], HOSPITAL)).status).toBe(404);
      mode = 'admin_only';
      const r = await assign(12, [11], HOSPITAL);
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'forbidden_treatments' });
      mode = 'ERROR';
      expect((await assign(12, [11], HOSPITAL)).status).toBe(403);
      expect(writes()).toHaveLength(0);
      mode = 'admin_only';
      expect((await assign(13, [20])).status).toBe(200);
    });

    it('unknown, malformed and not approved hospitals -> 404', async () => {
      for (const id of [999, 14, 'abc', '0']) expect((await assign(id, [10])).status).toBe(404);
      expect(writes()).toHaveLength(0);
    });

    it('a failure in the middle rolls everything back and answers 500', async () => {
      failInsertAt = 2;
      const r = await assign(13, [20, 11, 10]);
      expect(r.status).toBe(500);
      expect(txEvents).toContain('ROLLBACK');
      expect(txEvents).not.toContain('COMMIT');
      expect(hts.filter((h) => h.hospital_id === 13)).toHaveLength(1);
    });

    it('no write user -> 503 write_unavailable; a denied right -> 503 and a rollback', async () => {
      hasRw = false;
      let r = await assign(13, [20]);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      hasRw = true;
      const restore = failRw('INSERT', dbError('ER_TABLEACCESS_DENIED_ERROR', 1142));
      r = await assign(13, [20]);
      restore();
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      expect(txEvents).toContain('ROLLBACK');
      expect(hts.filter((h) => h.hospital_id === 13)).toHaveLength(1);
    });
  });

  describe('PATCH /v1/hospitals/:id/treatments/:htId', () => {
    it('sets and clears the promotion', async () => {
      const on = await patch(12, 101, { isPromoted: true });
      expect(on.status).toBe(200);
      expect(on.body.item).toMatchObject({ id: 101, isPromoted: true });
      expect(on.body.promotedCount).toBe(2);
      expect(Object.keys(on.body).sort()).toEqual(['item', 'promotedCount']);
      const off = await patch(12, 101, { isPromoted: false });
      expect(off.body.item.isPromoted).toBe(false);
      expect(off.body.promotedCount).toBe(1);
      expect(txEvents.slice(0, 3)).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
    });

    it('sets the minimum age; null and 0 clear it; both fields in one request', async () => {
      expect((await patch(12, 101, { minAge: 19 })).body.item.minAge).toBe(19);
      expect(hts.find((h) => h.id === 101)!.min_age).toBe(19);
      expect((await patch(12, 101, { minAge: 0 })).body.item.minAge).toBeNull();
      expect(hts.find((h) => h.id === 101)!.min_age).toBeNull();
      const both = await patch(12, 101, { isPromoted: true, minAge: 120 });
      expect(both.body.item).toMatchObject({ isPromoted: true, minAge: 120 });
      expect((await patch(12, 101, { minAge: null })).body.item.minAge).toBeNull();
    });

    it.each([[{ minAge: 121 }, 'minAge'], [{ minAge: -1 }, 'minAge'], [{ minAge: '19' }, 'minAge'], [{ minAge: 1.5 }, 'minAge'], [{ isPromoted: 'yes' }, 'isPromoted'], [{}, undefined]] as [unknown, string | undefined][])(
      'body %p -> 400 invalid_field', async (body, field) => {
        const r = await patch(12, 101, body);
        expect(r.status).toBe(400);
        expect(r.body).toEqual(field ? { error: 'invalid_field', field } : { error: 'invalid_field' });
        expect(writes()).toHaveLength(0);
      });

    it('a treatment of another hospital, a removed one, an unknown one -> 404; nothing written', async () => {
      for (const ht of [200, 102, 999, 'abc']) {
        const r = await patch(12, ht, { isPromoted: true });
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
      }
      expect(writes()).toHaveLength(0);
      expect(hts.find((h) => h.id === 200)!.is_promoted).toBe(0);
    });

    it('every statement is scoped to the hospital', async () => {
      await patch(12, 101, { isPromoted: true, minAge: 18 });
      expect(writes()).toHaveLength(2);
      for (const c of writes()) expect(c.sql).toContain('AND hospital_id = ?');
    });

    it('hospital account: own hospital works, another -> 404, setting admin_only -> 403', async () => {
      expect((await patch(12, 101, { isPromoted: true }, HOSPITAL)).status).toBe(200);
      expect((await patch(13, 200, { isPromoted: true }, HOSPITAL)).status).toBe(404);
      mode = 'admin_only';
      const r = await patch(12, 101, { isPromoted: false }, HOSPITAL);
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'forbidden_treatments' });
      expect(hts.find((h) => h.id === 101)!.is_promoted).toBe(1);
    });

    it('missing min_age column -> 503 min_age_unavailable and the promotion of the same request is rolled back', async () => {
      minAgeColumn = false;
      const r = await patch(12, 101, { isPromoted: true, minAge: 18 });
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'min_age_unavailable' });
      expect(txEvents).toContain('ROLLBACK');
      expect(hts.find((h) => h.id === 101)!.is_promoted).toBe(0);
      expect((await patch(12, 101, { isPromoted: true })).status).toBe(200);
    });

    it('no write user -> 503 write_unavailable', async () => {
      hasRw = false;
      const r = await patch(12, 101, { isPromoted: true });
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
    });
  });

  describe('DELETE /v1/hospitals/:id/treatments/:htId (remove)', () => {
    it('deletes the price option and image rows, deactivates the assignment, keeps the row', async () => {
      const r = await del(12, 101);
      expect(r.status).toBe(200);
      expect(r.body).toMatchObject({ removed: true, deleted: { priceOptions: 1, images: 2 }, count: 1, promotedCount: 1 });
      expect(r.body.items.map((i: any) => i.id)).toEqual([100]);
      expect(r.body.available.map((a: any) => a.id)).toEqual([10, 11]);
      expect(hts.find((h) => h.id === 101)).toMatchObject({ is_active: 0, is_promoted: 0 });
      expect(prices.some((p) => p.hospital_treatment_id === 101)).toBe(false);
      expect(images).toEqual([]);
      expect(txEvents.slice(0, 3)).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      expect(writes().map((c) => c.sql.split(' ').slice(0, 3).join(' '))).toEqual([
        'DELETE FROM gangnamunni_hospital_treatment_prices', 'DELETE FROM gangnamunni_hospital_treatment_images', 'UPDATE gangnamunni_hospital_treatments SET',
      ]);
    });

    it('a promoted assignment loses its promotion with the removal', async () => {
      const r = await del(12, 100);
      expect(r.body).toMatchObject({ removed: true, promotedCount: 0 });
      expect(hts.find((h) => h.id === 100)).toMatchObject({ is_active: 0, is_promoted: 0 });
    });

    it('already removed -> 200 removed:false and nothing deleted', async () => {
      const r = await del(12, 102);
      expect(r.status).toBe(200);
      expect(r.body).toMatchObject({ removed: false, deleted: { priceOptions: 0, images: 0 } });
      expect(writes()).toHaveLength(0);
    });

    it("another hospital's or an unknown assignment -> 404, nothing deleted", async () => {
      for (const ht of [200, 999, 'abc']) expect((await del(12, ht)).status).toBe(404);
      expect(writes()).toHaveLength(0);
      expect(prices).toHaveLength(2);
    });

    it('a missing price table counts as 0 deleted and the removal still succeeds', async () => {
      pricesTable = false;
      const r = await del(12, 101);
      expect(r.status).toBe(200);
      expect(r.body.deleted).toEqual({ priceOptions: 0, images: 2 });
      expect(hts.find((h) => h.id === 101)!.is_active).toBe(0);
    });

    it('a denied DELETE right -> 503 write_unavailable and nothing changes', async () => {
      const restore = failRw('DELETE FROM gangnamunni_hospital_treatment_images', dbError('ER_TABLEACCESS_DENIED_ERROR', 1142));
      const r = await del(12, 101);
      restore();
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      expect(txEvents).toContain('ROLLBACK');
      expect(hts.find((h) => h.id === 101)!.is_active).toBe(1);
      expect(prices.filter((p) => p.hospital_treatment_id === 101)).toHaveLength(1);
    });

    it('hospital account: allowed only when the setting says so; another hospital -> 404', async () => {
      mode = 'admin_only';
      const r = await del(12, 101, HOSPITAL);
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'forbidden_treatments' });
      mode = 'hospital_only';
      expect((await del(13, 200, HOSPITAL)).status).toBe(404);
      expect((await del(12, 101, HOSPITAL)).status).toBe(200);
    });
  });

  describe('price options: PUT /v1/hospitals/:id/treatments/:htId/prices', () => {
    const put = (id: number | string, ht: number | string, body: unknown, cookie: string | undefined = SUPER, headers: Record<string, string> = JSON_HDR) => {
      let r = request(app.getHttpServer()).put(`/v1/hospitals/${id}/treatments/${ht}/prices`);
      if (cookie) r = r.set('Cookie', cookie);
      for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
      return r.send(JSON.stringify(body));
    };
    const opt = (o: Record<string, unknown> = {}) => ({ optionName: '', bodyParts: [], dosageValue: 1, dosageUnit: '회', regularPrice: 0, price: 0, isRepresentative: false, ...o });
    beforeEach(() => { process.env.ADMIN_HOSPITALS_TREATMENTS_PRICES = '1'; });

    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITALS_TREATMENTS_PRICES=%p -> 404, nothing asked, flag false', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_TREATMENTS_PRICES; else process.env.ADMIN_HOSPITALS_TREATMENTS_PRICES = v;
      const r = await put(12, 101, { options: [opt()] });
      expect(r.status).toBe(404);
      expect(r.body).toEqual({ error: 'not_found' });
      expect(rwCalls).toHaveLength(0);
      expect(roCalls).toHaveLength(0);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatmentsPrices).toBe(false);
      const list = await get('/v1/hospitals/12/treatments', SUPER);
      expect(list.body).toMatchObject({ pricesEnabled: false, priceUnits: [] });
      expect(roCalls.some((c) => c.sql.includes('gangnamunni_treatment_units'))).toBe(false);
    });

    it('PRICES without TREATMENTS stays off', async () => {
      process.env.ADMIN_HOSPITALS_TREATMENTS = '0';
      expect((await put(12, 101, { options: [opt()] })).status).toBe(404);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatmentsPrices).toBe(false);
    });

    it('/v1/me reports the flag; GET adds pricesEnabled and the units in PHP order (Latin first, then Korean)', async () => {
      expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatmentsPrices).toBe(true);
      const r = await get('/v1/hospitals/12/treatments', SUPER);
      expect(r.body.pricesEnabled).toBe(true);
      expect(r.body.priceUnits).toEqual(['Box', 'IU', 'ml', '회']);
      expect(r.body.items[0].priceOptions[0]).toEqual({ optionName: '', bodyPart: '이마', dosageValue: '50', dosageUnit: '유닛', regularPrice: 100000, price: 80000, isRepresentative: true });
    });

    it('GET adds the body part groups from the table (a repeat or a name with the separator is left out); table missing -> empty list, no 500', async () => {
      expect((await get('/v1/hospitals/12/treatments', SUPER)).body.priceBodyParts).toEqual([{ label: '얼굴', parts: ['이마', '자갈턱'] }, { label: '몸', parts: ['손바닥'] }]);
      bodyPartRows = 'ERROR';
      const r = await get('/v1/hospitals/12/treatments', SUPER);
      expect(r.status).toBe(200);
      expect(r.body.priceBodyParts).toEqual([]);
    });

    it('units table missing or empty -> the standard list, no 500', async () => {
      unitsRows = 'ERROR';
      expect((await get('/v1/hospitals/12/treatments', SUPER)).body.priceUnits).toEqual(['Box', 'cc', 'IU', 'kj', 'ml', '기타', '라인', '병', '샷', '시린지', '회']);
      unitsRows = [];
      expect((await get('/v1/hospitals/12/treatments', SUPER)).body.priceUnits).toHaveLength(11);
    });

    it('a hospital account that may not manage gets no units; PUT -> 403 forbidden_treatments, nothing written', async () => {
      mode = 'admin_only';
      const l = await get('/v1/hospitals/12/treatments', HOSPITAL);
      expect(l.body).toMatchObject({ canManage: false, pricesEnabled: true, priceUnits: [] });
      const r = await put(12, 100, { options: [opt()] }, HOSPITAL);
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'forbidden_treatments' });
      expect(writes()).toHaveLength(0);
    });

    it('guards: csrf 403, not signed in 401, other hospital 404, foreign / unknown / inactive / malformed treatment 404', async () => {
      expect((await put(12, 101, { options: [opt()] }, SUPER, {})).body).toEqual({ error: 'csrf' });
      expect((await put(12, 101, { options: [opt()] }, 'PHPSESSID=nobody')).status).toBe(401);
      expect((await put(13, 200, { options: [opt()] }, HOSPITAL)).status).toBe(404);
      expect((await put(12, 200, { options: [opt()] })).status).toBe(404);
      expect((await put(12, 999, { options: [opt()] })).status).toBe(404);
      expect((await put(12, 102, { options: [opt()] })).status).toBe(404);
      expect((await put(12, 'abc', { options: [opt()] })).status).toBe(404);
      expect(writes()).toHaveLength(0);
      expect(prices).toHaveLength(2);
    });

    it('replaces all options: delete, inserts with sort_order 0.., summary columns cleared, one transaction, answer re-read', async () => {
      const r = await put(12, 101, { options: [
        opt({ optionName: ' 주름 100샷 ', bodyParts: ['이마', '미간'], dosageValue: 100, dosageUnit: '샷', regularPrice: 300000, price: 250000, isRepresentative: true }),
        opt({ dosageValue: 1.5, dosageUnit: 'cc' }),
        {},
      ] });
      expect(r.status).toBe(200);
      expect(Object.keys(r.body).sort()).toEqual(['count', 'item', 'promotedCount']);
      expect(r.body.item.priceOptions).toEqual([
        { optionName: '주름 100샷', bodyPart: '이마||미간', dosageValue: '100', dosageUnit: '샷', regularPrice: 300000, price: 250000, isRepresentative: true },
        { optionName: '', bodyPart: '', dosageValue: '1.5', dosageUnit: 'cc', regularPrice: 0, price: 0, isRepresentative: false },
      ]);
      expect(r.body.count).toBe(2);
      expect(r.body.promotedCount).toBe(1);
      expect(txEvents).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      const heads = rwCalls.map((c) => c.sql.split(' ').slice(0, 3).join(' '));
      expect(heads.indexOf('DELETE FROM gangnamunni_hospital_treatment_prices')).toBeLessThan(heads.indexOf('INSERT INTO gangnamunni_hospital_treatment_prices'));
      const ins = rwCalls.filter((c) => c.sql.startsWith('INSERT INTO gangnamunni_hospital_treatment_prices'));
      expect(ins.map((c) => c.values)).toEqual([[101, '주름 100샷', '이마||미간', 100, '샷', 300000, 250000, 1, 0], [101, '', '', 1.5, 'cc', 0, 0, 0, 1]]);
      const last = rwCalls[rwCalls.length - 1];
      expect(last.sql).toMatch(/^UPDATE gangnamunni_hospital_treatments SET price_min = 0, price_max = 0, dosage_value = NULL, dosage_unit = NULL/);
      expect(last.values).toEqual([101, 12]);
      for (const c of rwCalls) expect(c.sql).not.toMatch(/ALTER|CREATE/i);
      expect(prices.filter((p) => p.hospital_treatment_id === 100)).toHaveLength(1);
    });

    it('a hospital account may edit its own prices when the setting allows it', async () => {
      mode = 'hospital_only';
      expect((await put(12, 100, { options: [opt({ dosageUnit: '병' })] }, HOSPITAL)).status).toBe(200);
      expect(prices.filter((p) => p.hospital_treatment_id === 100).map((p) => p.dosage_unit)).toEqual(['병']);
    });

    it.each([
      ['an empty body', {}, { error: 'no_options' }],
      ['an empty list', { options: [] }, { error: 'no_options' }],
      ['only blank rows', { options: [{}, { optionName: ' ' }] }, { error: 'no_options' }],
      ['a missing dosage', { options: [opt({ optionName: 'x', dosageValue: 0 })] }, { error: 'invalid_field', index: 0, field: 'dosageValue' }],
      ['a missing unit', { options: [opt({ dosageUnit: ' ' })] }, { error: 'invalid_field', index: 0, field: 'dosageUnit' }],
      ['a wrong price order', { options: [opt(), opt({ dosageValue: 2, regularPrice: 1000, price: 2000 })] }, { error: 'price_order', index: 1 }],
      ['two representatives', { options: [opt({ isRepresentative: true }), opt({ dosageValue: 2, isRepresentative: true })] }, { error: 'multiple_representative' }],
      ['a duplicate (case-insensitive)', { options: [opt({ optionName: 'A', dosageUnit: 'CC' }), opt({ dosageValue: 1.0, optionName: 'a', dosageUnit: 'cc' })] }, { error: 'duplicate_option', index: 1 }],
      ['too many options', { options: Array.from({ length: 51 }, (_, i) => opt({ dosageValue: i + 1 })) }, { error: 'too_many' }],
    ])('400 for %s, nothing written', async (_n, body, expected) => {
      const r = await put(12, 101, body);
      expect(r.status).toBe(400);
      expect(r.body).toEqual(expected);
      expect(writes()).toHaveLength(0);
      expect(prices).toHaveLength(2);
    });

    it('a deadlock halfway rolls everything back and keeps the old options', async () => {
      failPriceInsertAt = 2;
      const r = await put(12, 101, { options: [opt(), opt({ dosageValue: 2 })] });
      expect(r.status).toBe(500);
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      expect(prices.filter((p) => p.hospital_treatment_id === 101).map((p) => p.body_part)).toEqual(['이마']);
    });

    it('missing price columns -> 503 price_columns_unavailable (rolled back); missing right / no write pool -> 503 write_unavailable', async () => {
      priceColumns = false;
      const a = await put(12, 101, { options: [opt()] });
      expect(a.status).toBe(503);
      expect(a.body).toEqual({ error: 'price_columns_unavailable' });
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      expect(prices.filter((p) => p.hospital_treatment_id === 101)).toHaveLength(1);
      priceColumns = true;
      const restore = failRw('INSERT INTO gangnamunni_hospital_treatment_prices', dbError('ER_TABLEACCESS_DENIED_ERROR', 1142));
      const b = await put(12, 101, { options: [opt()] });
      expect(b.status).toBe(503);
      expect(b.body).toEqual({ error: 'write_unavailable' });
      restore();
      hasRw = false;
      expect((await put(12, 101, { options: [opt()] })).body).toEqual({ error: 'write_unavailable' });
    });

    it('GET writes nothing with the prices switch on', async () => {
      await get('/v1/hospitals/12/treatments', SUPER);
      expect(rwCalls).toHaveLength(0);
      for (const c of roCalls) expect(c.sql).toMatch(/^SELECT/);
    });
  });
  describe('"before treatment" questions: PUT /v1/hospitals/:id/treatments/:htId/questions', () => {
    const put = (id: number | string, ht: number | string, body: unknown, cookie: string | undefined = SUPER, headers: Record<string, string> = JSON_HDR) => {
      let r = request(app.getHttpServer()).put(`/v1/hospitals/${id}/treatments/${ht}/questions`);
      if (cookie) r = r.set('Cookie', cookie);
      for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
      return r.send(JSON.stringify(body));
    };
    const texts = (ht: number) => questions.filter((q) => q.hospital_treatment_id === ht).map((q) => q.question);
    beforeEach(() => { process.env.ADMIN_HOSPITALS_TREATMENTS_QUESTIONS = '1'; });

    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITALS_TREATMENTS_QUESTIONS=%p -> 404, nothing asked, flag false, list without questions', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_TREATMENTS_QUESTIONS; else process.env.ADMIN_HOSPITALS_TREATMENTS_QUESTIONS = v;
      const r = await put(12, 100, { questions: ['a'] });
      expect(r.status).toBe(404);
      expect(r.body).toEqual({ error: 'not_found' });
      expect(rwCalls).toHaveLength(0);
      expect(roCalls).toHaveLength(0);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatmentsQuestions).toBe(false);
      const list = (await get('/v1/hospitals/12/treatments', SUPER)).body;
      expect(list.questionsEnabled).toBe(false);
      for (const i of list.items) expect(i).not.toHaveProperty('questions');
      expect(roCalls.some((c) => c.sql.includes('question FROM'))).toBe(false);
    });

    it('QUESTIONS without TREATMENTS stays off', async () => {
      process.env.ADMIN_HOSPITALS_TREATMENTS = '0';
      expect((await put(12, 100, { questions: [] })).status).toBe(404);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatmentsQuestions).toBe(false);
    });

    it('/v1/me reports the flag; GET adds questionsEnabled and the texts in order, questionCount unchanged', async () => {
      expect((await get('/v1/me', SUPER)).body.features.hospitalsTreatmentsQuestions).toBe(true);
      const r = await get('/v1/hospitals/12/treatments', SUPER);
      expect(r.body.questionsEnabled).toBe(true);
      const byId = Object.fromEntries(r.body.items.map((i: any) => [i.id, i]));
      expect(byId[100].questions).toEqual(['임신 중이세요?', '알레르기가 있나요?']);
      expect(byId[100].questionCount).toBe(2);
      expect(byId[101].questions).toEqual([]);
      expect(byId[101].questionCount).toBe(0);
    });

    it('a hospital account that may not manage gets questionsEnabled but no texts; PUT -> 403', async () => {
      mode = 'admin_only';
      const l = (await get('/v1/hospitals/12/treatments', HOSPITAL)).body;
      expect(l).toMatchObject({ canManage: false, questionsEnabled: true });
      for (const i of l.items) expect(i).not.toHaveProperty('questions');
      expect(l.items.find((i: any) => i.id === 100).questionCount).toBe(2);
      expect(roCalls.some((c) => c.sql.includes('question FROM'))).toBe(false);
      const r = await put(12, 100, { questions: ['x'] }, HOSPITAL);
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'forbidden_treatments' });
      expect(writes()).toHaveLength(0);
    });

    it('question texts that cannot be read -> no texts and questionsEnabled false (a save could wipe them), page still works', async () => {
      questionTextsReadable = false;
      const l = await get('/v1/hospitals/12/treatments', SUPER);
      expect(l.status).toBe(200);
      expect(l.body.questionsEnabled).toBe(false);
      for (const i of l.body.items) expect(i).not.toHaveProperty('questions');
      expect(l.body.items.find((i: any) => i.id === 100).questionCount).toBe(2);
    });

    it('guards: guard order, csrf 403, not signed in 401, other hospital 404, foreign / unknown / malformed treatment 404', async () => {
      expect((await put(12, 100, { questions: [] }, SUPER, {})).body).toEqual({ error: 'csrf' });
      expect((await put(12, 100, { questions: [] }, 'PHPSESSID=nobody')).status).toBe(401);
      expect((await put(13, 200, { questions: [] }, HOSPITAL)).status).toBe(404);
      expect((await put(12, 200, { questions: [] })).status).toBe(404);
      expect((await put(12, 999, { questions: [] })).status).toBe(404);
      expect((await put(12, 'abc', { questions: [] })).status).toBe(404);
      // scope and rule come before the body: a bad body of a foreign hospital is 404, of a forbidden account 403
      expect((await put(13, 200, { questions: 5 }, HOSPITAL)).status).toBe(404);
      mode = 'admin_only';
      expect((await put(12, 100, { questions: 5 }, HOSPITAL)).status).toBe(403);
      expect(writes()).toHaveLength(0);
      expect(texts(100)).toHaveLength(2);
    });

    it('replaces all questions: normalized, deduplicated, sort_order 0.., one transaction, answer re-read; no ALTER / CREATE', async () => {
      const r = await put(12, 100, { questions: ['  임신   중이세요?\n', '', '   ', '임신 중이세요?', '카페인\t섭취', 'b'] });
      expect(r.status).toBe(200);
      expect(Object.keys(r.body).sort()).toEqual(['count', 'item']);
      expect(r.body.item.id).toBe(100);
      expect(r.body.item.questions).toEqual(['임신 중이세요?', '카페인 섭취', 'b']);
      expect(r.body.item.questionCount).toBe(3);
      expect(r.body.count).toBe(3);
      expect(txEvents).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      const heads = rwCalls.map((c) => c.sql.split(' ').slice(0, 3).join(' '));
      expect(heads.indexOf('SELECT id, is_active')).toBeLessThan(heads.indexOf('DELETE FROM gangnamunni_treatment_questions'));
      expect(rwCalls.find((c) => c.sql.startsWith('SELECT id, is_active'))!.sql).toContain('FOR UPDATE');
      expect(heads.indexOf('DELETE FROM gangnamunni_treatment_questions')).toBeLessThan(heads.indexOf('INSERT INTO gangnamunni_treatment_questions'));
      expect(rwCalls.filter((c) => c.sql.startsWith('INSERT INTO gangnamunni_treatment_questions')).map((c) => c.values)).toEqual([[100, '임신 중이세요?', 0], [100, '카페인 섭취', 1], [100, 'b', 2]]);
      for (const c of rwCalls) expect(c.sql).not.toMatch(/ALTER|CREATE/i);
      expect(writes().every((c) => c.sql.includes('gangnamunni_treatment_questions'))).toBe(true);
      expect(texts(100)).toEqual(['임신 중이세요?', '카페인 섭취', 'b']);
    });

    it('an empty list (or only blanks) removes every question', async () => {
      for (const list of [[], ['', '  ']]) {
        questions = [{ hospital_treatment_id: 100, question: 'x' }];
        const r = await put(12, 100, { questions: list });
        expect(r.status).toBe(200);
        expect(r.body.count).toBe(0);
        expect(r.body.item.questions).toEqual([]);
        expect(r.body.item.questionCount).toBe(0);
        expect(texts(100)).toEqual([]);
      }
      expect(rwCalls.filter((c) => c.sql.startsWith('INSERT INTO gangnamunni_treatment_questions'))).toHaveLength(0);
    });

    it('only the given assignment changes', async () => {
      questions.push({ hospital_treatment_id: 101, question: 'other' });
      expect((await put(12, 100, { questions: ['new'] })).status).toBe(200);
      expect(texts(101)).toEqual(['other']);
    });

    it('a hospital account may save its own questions when the setting allows it', async () => {
      mode = 'hospital_only';
      expect((await put(12, 100, { questions: ['q'] }, HOSPITAL)).status).toBe(200);
      expect(texts(100)).toEqual(['q']);
    });

    it('limits: 200 code points pass (emoji count once), 201 -> too_long with the sent index; 10 pass, 11 distinct -> too_many; repeats do not count', async () => {
      const ok = '가'.repeat(199) + '😀';
      expect(Array.from(ok)).toHaveLength(200);
      expect((await put(12, 100, { questions: [ok] })).status).toBe(200);
      expect(texts(100)).toEqual([ok]);
      const r = await put(12, 100, { questions: ['a', '', '가'.repeat(201)] });
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'too_long', index: 2 });
      expect(texts(100)).toEqual([ok]);
      const ten = Array.from({ length: 10 }, (_, i) => `q${i}`);
      expect((await put(12, 100, { questions: [...ten, 'q0', 'q1', ' '] })).status).toBe(200);
      expect(texts(100)).toEqual(ten);
      const r2 = await put(12, 100, { questions: [...ten, 'q10'] });
      expect(r2.status).toBe(400);
      expect(r2.body).toEqual({ error: 'too_many' });
      expect(texts(100)).toEqual(ten);
    });

    it.each([
      ['no body fields', {}, { error: 'invalid_body' }],
      ['questions not an array', { questions: 'a' }, { error: 'invalid_body' }],
      ['a body that is a list', [], { error: 'invalid_body' }],
      ['a non-string element', { questions: ['a', 5] }, { error: 'invalid_field', index: 1 }],
      ['a null element', { questions: [null] }, { error: 'invalid_field', index: 0 }],
    ])('400 for %s, nothing written', async (_n, body, expected) => {
      const r = await put(12, 100, body);
      expect(r.status).toBe(400);
      expect(r.body).toEqual(expected);
      expect(writes()).toHaveLength(0);
      expect(texts(100)).toHaveLength(2);
    });

    it('a deadlock halfway rolls everything back and keeps the old questions', async () => {
      failQuestionInsertAt = 2;
      const r = await put(12, 100, { questions: ['a', 'b', 'c'] });
      expect(r.status).toBe(500);
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      expect(texts(100)).toEqual(['임신 중이세요?', '알레르기가 있나요?']);
    });

    it('missing table -> 503 questions_table_unavailable (rolled back); missing right / no write pool -> 503 write_unavailable', async () => {
      questionsWriteTable = false;
      const a = await put(12, 100, { questions: ['a'] });
      expect(a.status).toBe(503);
      expect(a.body).toEqual({ error: 'questions_table_unavailable' });
      expect(txEvents).toEqual(['BEGIN', 'ROLLBACK', 'RELEASE']);
      questionsWriteTable = true;
      const restore = failRw('INSERT INTO gangnamunni_treatment_questions', dbError('ER_TABLEACCESS_DENIED_ERROR', 1142));
      const b = await put(12, 100, { questions: ['a'] });
      expect(b.status).toBe(503);
      expect(b.body).toEqual({ error: 'write_unavailable' });
      expect(texts(100)).toEqual(['임신 중이세요?', '알레르기가 있나요?']);
      restore();
      hasRw = false;
      expect((await put(12, 100, { questions: ['a'] })).body).toEqual({ error: 'write_unavailable' });
    });

    it('the log line carries ids and the count, never the text', async () => {
      const spy = jest.spyOn(Logger.prototype, 'log');
      await put(12, 100, { questions: ['비밀 질문'] });
      const lines = spy.mock.calls.map((c) => String(c[0]));
      expect(lines).toContain('hospital treatments: admin=7 hospital=12 questions treatment=100 count=1');
      expect(lines.join('\n')).not.toContain('비밀');
    });

    it('GET writes nothing with the questions switch on', async () => {
      await get('/v1/hospitals/12/treatments', SUPER);
      expect(rwCalls).toHaveLength(0);
      for (const c of roCalls) expect(c.sql).toMatch(/^SELECT/);
    });

    it('/v1/me with every treatments switch on', async () => {
      process.env.ADMIN_HOSPITALS_TREATMENTS_PRICES = '1';
      expect((await get('/v1/me', SUPER)).body.features).toMatchObject({ hospitalsTreatmentsPrices: true, hospitalsTreatmentsQuestions: true });
    });
  });
});
