import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DashboardModule } from '../dashboard/dashboard.module';
import { HospitalsModule } from './hospitals.module';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const HOSPITAL_ADMIN = 'PHPSESSID=hadmin12';
const UNBOUND = 'PHPSESSID=unbound';
const PLAIN = 'PHPSESSID=plain';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

/** Two approved hospitals (12, 13); 14 is a pending application, 15 a rejected one. */
const HOSPITALS: Record<number, Record<string, unknown>> = {
  12: {
    id: 12, name: '서울의원', owner_name: '김원장', business_number: '123-45-67890', introduction: '소개', address: '서울 강남구', phone: '02-000-0000',
    email: 'a@example.com', contact_name: '담당', contact_phone: '010-0000-0000', website: 'https://example.com', sns_url: '',
    categories: '["피부","성형"]', badges: '[]', treatment_tags: '보톡스, 필러', subway_names: null, business_hours: '{"mon":"09:00-18:00"}',
    latitude: 37.5, longitude: 127.03, status: 'approved', is_active: 1, ad_grade: 'A', rating: 4.5, review_count: 10, show_rating: 1,
    has_unreviewed_changes: 1, main_image_url: 'uploads/hospitals/12/main.jpg', profile_image_url: 'https://cdn.example.com/p.jpg',
    applied_by: 'member1', applied_at: '2026-10-01 09:00:00', created_at: '2026-10-02 10:00:00', updated_at: '2026-11-01 11:00:00',
    has_cert_business: 1, has_cert_medical: 0,
  },
  13: {
    id: 13, name: '다른의원', status: 'approved', is_active: 0, ad_grade: 'none', rating: null, review_count: 0, show_rating: 0, has_unreviewed_changes: 0,
    categories: null, created_at: '2026-09-01 10:00:00', has_cert_business: 0, has_cert_medical: 0,
  },
  14: {
    id: 14, name: '신청의원', owner_name: '박원장', status: 'pending', is_active: 0, ad_grade: 'none', rating: null, review_count: 0, show_rating: 0, has_unreviewed_changes: 1,
    categories: '["피부"]', created_at: '2026-11-15 10:00:00', applied_by: 'member9', applied_at: '2026-11-15 09:00:00', has_cert_business: 1, has_cert_medical: 1,
  },
  15: {
    id: 15, name: '반려의원', status: 'rejected', is_active: 0, ad_grade: 'none', rating: null, review_count: 0, show_rating: 0, has_unreviewed_changes: 0,
    categories: null, created_at: '2026-11-01 10:00:00', has_cert_business: 0, has_cert_medical: 0,
  },
};

describe('admin API: hospitals (list, treatment options, detail)', () => {
  let app: INestApplication;
  const phpCalls: string[] = [];
  const calls: { sql: string; values: unknown[] }[] = [];
  const realFetch = global.fetch;
  let kakaoColumn: 'ok' | 'missing' = 'ok';
  let revisionsTable: 'ok' | 'missing' = 'ok';
  let failHospitals = false;

  /** Ids the statement may return: the bound IN (?) list when there is one, and only the statuses the statement asks for (approved by default). */
  const visible = (sql: string, values: unknown[]): number[] => {
    const bound = values.find((v) => Array.isArray(v)) as number[] | undefined;
    const wanted = sql.includes("h.status = 'pending'") ? ['pending'] : sql.includes("h.status IN ('approved', 'pending')") ? ['approved', 'pending'] : ['approved'];
    const ids = Object.keys(HOSPITALS).map(Number).filter((id) => wanted.includes(HOSPITALS[id].status as string));
    return sql.includes(' IN (?)') ? ids.filter((id) => (bound || []).includes(id)) : ids;
  };

  const dbStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      calls.push({ sql, values });
      if (failHospitals) throw Object.assign(new Error('SELECT command denied to user for table gangnamunni_hospitals'), { code: 'ER_TABLEACCESS_DENIED_ERROR', errno: 1142 });
      if (sql.includes('FROM gangnamunni_treatment_master')) {
        const ids = visible(sql, values);
        return [[
          ...(ids.includes(12) ? [{ id: 1, name: '보톡스', category_name: '쁘띠' }] : []),
          ...(ids.includes(13) ? [{ id: 2, name: '제모', category_name: null }] : []),
        ]];
      }
      if (sql.includes('FROM hospital_images')) return [[{ id: 3, image_url: 'uploads/hospitals/12/a.jpg', image_type: 'interior', sort_order: 1, is_active: 1 }]];
      if (sql.includes('FROM gangnamunni_doctors')) {
        return [[{ id: 4, name: '이의사', specialty: '피부과', experience: 10, qualification: '전문의', bio: null, photo_url: 'uploads/hospitals/12/doctor_4.jpg', status: 'active' }]];
      }
      if (sql.includes('FROM gangnamunni_hospital_revisions')) {
        if (revisionsTable === 'missing') throw Object.assign(new Error("Table 'x.gangnamunni_hospital_revisions' doesn't exist"), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
        return [[{ id: 5, field_name: 'phone', old_value: '02-1', new_value: '02-2', changed_at: '2026-11-10 12:00:00' }]];
      }
      if (sql.includes('kakao_channel_url')) {
        if (kakaoColumn === 'missing') throw Object.assign(new Error("Unknown column 'h.kakao_channel_url' in 'field list'"), { code: 'ER_BAD_FIELD_ERROR', errno: 1054 });
        return [[{ kakao_channel_url: 'https://pf.kakao.com/_x' }]];
      }
      if (sql.includes('FROM gangnamunni_hospitals h')) {
        const ids = visible(sql, values);
        if (sql.includes('AS unreviewed')) {
          const rows = ids.map((id) => HOSPITALS[id]);
          return [[{ total: String(rows.length), active: String(rows.filter((r) => r.is_active === 1).length),
            inactive: String(rows.filter((r) => r.is_active !== 1).length), unreviewed: String(rows.filter((r) => r.has_unreviewed_changes === 1).length),
            pending: sql.includes("hp.status = 'pending'") ? String(Object.values(HOSPITALS).filter((r) => r.status === 'pending').length) : '0' }]];
        }
        if (sql.includes('COUNT(*) AS cnt')) return [[{ cnt: ids.length }]];
        if (sql.includes('WHERE h.id = ?')) return [ids.filter((id) => id === values[0]).map((id) => HOSPITALS[id])];
        return [ids.sort((a, b) => b - a).map((id) => HOSPITALS[id])];
      }
      throw new Error(`unexpected sql: ${sql}`);
    }),
  };

  async function boot(): Promise<void> {
    @Global() @Module({ providers: [{ provide: DB, useValue: dbStub }, { provide: DB_RW, useValue: { get: () => null } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, DashboardModule, HospitalsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  }

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      phpCalls.push(cookie);
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('down')) throw new Error('connect ECONNREFUSED');
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      if (cookie.includes('hadmin12')) return json({ loggedIn: true, admin: admin('hospital_admin', 12) });
      if (cookie.includes('unbound')) return json({ loggedIn: true, admin: admin('hospital_admin', null) });
      if (cookie.includes('plain')) return json({ loggedIn: true, admin: admin('admin', 12) });
      if (cookie.includes('editor')) return json({ loggedIn: true, admin: admin('editor', 12) });
      if (cookie.includes('viewer')) return json({ loggedIn: true, admin: admin('viewer') });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    phpCalls.length = 0;
    calls.length = 0;
    kakaoColumn = 'ok';
    revisionsTable = 'ok';
    failHospitals = false;
    dbStub.query.mockClear();
    await boot();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => { global.fetch = realFetch; delete process.env.ADMIN_PANEL_ENABLED; delete process.env.ADMIN_HOSPITALS_ENABLED; });

  const get = (path: string, cookie?: string) => {
    const r = request(app.getHttpServer()).get(path);
    return cookie ? r.set('Cookie', cookie) : r;
  };
  const ROUTES = ['/v1/hospitals', '/v1/hospitals/treatment-options', '/v1/hospitals/12'];

  describe('ADMIN_HOSPITALS_ENABLED off', () => {
    it.each([undefined, '', '0', 'true', 'on'])('value %s -> hospital routes are 404 for everyone, PHP and DB untouched', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_ENABLED; else process.env.ADMIN_HOSPITALS_ENABLED = v;
      for (const cookie of [SUPER, HOSPITAL, undefined]) {
        for (const path of ROUTES) {
          const r = await get(path, cookie);
          expect(r.status).toBe(404);
          expect(r.body).toEqual({ error: 'not_found' });
          expect(r.headers['cache-control']).toBe('private, no-store');
        }
      }
      expect(phpCalls).toHaveLength(0);
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('hospital roles stay locked out of every other /v1 route (403), super admin is unchanged', async () => {
      delete process.env.ADMIN_HOSPITALS_ENABLED;
      for (const cookie of [HOSPITAL, HOSPITAL_ADMIN, UNBOUND]) {
        await get('/v1/me', cookie).expect(403).expect({ error: 'forbidden' });
        await get('/v1/dashboard/summary', cookie).expect(403).expect({ error: 'forbidden' });
      }
      const me = await get('/v1/me', SUPER).expect(200);
      expect(me.body).toEqual({ admin: { id: 7, username: 'boss', name: '김관리', role: 'super_admin', hospitalId: null, scope: 'all' }, features: { hospitals: false, hospitalsWrite: false, hospitalsDelete: false, archive: false, hospitalsReview: false, hospitalsImages: false, hospitalsDocuments: false, hospitalsDoctors: false, hospitalsTreatments: false, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false, hospitalsTreatmentsImages: false } });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('ADMIN_PANEL_ENABLED off wins: everything is 404 even with the hospital switch on', async () => {
      process.env.ADMIN_PANEL_ENABLED = '0';
      for (const path of [...ROUTES, '/v1/me']) await get(path, SUPER).expect(404).expect({ error: 'not_found' });
      expect(phpCalls).toHaveLength(0);
    });
  });

  describe('who may enter (switch on)', () => {
    it('not signed in -> 401', async () => {
      for (const path of ROUTES) await get(path).expect(401).expect({ error: 'not_logged_in' });
      for (const path of ROUTES) await get(path, 'PHPSESSID=stranger').expect(401).expect({ error: 'not_logged_in' });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it.each([PLAIN, 'PHPSESSID=editor', 'PHPSESSID=viewer'])('platform role other than super_admin (%s) -> 403 everywhere', async (cookie) => {
      for (const path of [...ROUTES, '/v1/me', '/v1/dashboard/summary']) await get(path, cookie).expect(403).expect({ error: 'forbidden' });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('PHP unusable -> 503', async () => {
      for (const path of ROUTES) await get(path, 'x=down').expect(503).expect({ error: 'session_unavailable' });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('/v1/me: scope and feature flag per role', async () => {
      expect((await get('/v1/me', SUPER).expect(200)).body)
        .toEqual({ admin: { id: 7, username: 'boss', name: '김관리', role: 'super_admin', hospitalId: null, scope: 'all' }, features: { hospitals: true, hospitalsWrite: false, hospitalsDelete: false, archive: false, hospitalsReview: false, hospitalsImages: false, hospitalsDocuments: false, hospitalsDoctors: false, hospitalsTreatments: false, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false, hospitalsTreatmentsImages: false } });
      expect((await get('/v1/me', HOSPITAL).expect(200)).body)
        .toEqual({ admin: { id: 7, username: 'boss', name: '김관리', role: 'hospital_super_admin', hospitalId: 12, scope: 'own' }, features: { hospitals: true, hospitalsWrite: false, hospitalsDelete: false, archive: false, hospitalsReview: false, hospitalsImages: false, hospitalsDocuments: false, hospitalsDoctors: false, hospitalsTreatments: false, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false, hospitalsTreatmentsImages: false } });
      expect((await get('/v1/me', HOSPITAL_ADMIN).expect(200)).body.admin).toMatchObject({ role: 'hospital_admin', hospitalId: 12, scope: 'own' });
      expect((await get('/v1/me', UNBOUND).expect(200)).body.admin).toMatchObject({ role: 'hospital_admin', hospitalId: null, scope: 'own' });
    });
    it('the dashboard stays super admin only', async () => {
      await get('/v1/dashboard/summary', HOSPITAL).expect(403).expect({ error: 'forbidden' });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
  });

  describe('GET /v1/hospitals', () => {
    it('super admin: every approved hospital, documented shape', async () => {
      const r = await get('/v1/hospitals', SUPER).expect(200);
      expect(r.headers['cache-control']).toContain('no-store');
      expect(r.body).toEqual({
        scope: 'all',
        stats: { total: 2, active: 1, inactive: 1, unreviewed: 1, pending: 1 },
        total: 2, page: 1, perPage: 20,
        items: [
          { id: 13, name: '다른의원', address: '', phone: '', categories: [], adGrade: 'none', isActive: false, rating: 0, reviewCount: 0, showRating: false,
            hasUnreviewedChanges: false, createdAt: '2026-09-01 10:00:00' },
          { id: 12, name: '서울의원', address: '서울 강남구', phone: '02-000-0000', categories: ['피부', '성형'], adGrade: 'A', isActive: true, rating: 4.5, reviewCount: 10,
            showRating: true, hasUnreviewedChanges: true, createdAt: '2026-10-02 10:00:00' },
        ],
      });
      expect(calls).toHaveLength(3);
      for (const c of calls) expect(c.sql).not.toContain(' IN (?)');
    });
    it.each([HOSPITAL, HOSPITAL_ADMIN])('hospital role (%s): only its own hospital, in the list and in the counters', async (cookie) => {
      const r = await get('/v1/hospitals', cookie).expect(200);
      expect(r.body.scope).toBe('own');
      expect(r.body.stats).toEqual({ total: 1, active: 1, inactive: 0, unreviewed: 1, pending: 0 });
      for (const c of calls) expect(c.sql).not.toContain("hp.status");
      expect(r.body.total).toBe(1);
      expect(r.body.items.map((i: any) => i.id)).toEqual([12]);
      expect(calls).toHaveLength(3);
      for (const c of calls) {
        expect(c.sql).toContain('h.id IN (?)');
        expect(c.values).toContainEqual([12]);
      }
    });
    it('a hospital role cannot widen its scope through the query string', async () => {
      const r = await get('/v1/hospitals?hospitalId=13&scope=all&hospitalIds[]=13&id=13&search=%EB%8B%A4%EB%A5%B8', HOSPITAL).expect(200);
      expect(r.body.items.map((i: any) => i.id)).toEqual([12]);
      for (const c of calls) expect(c.values).toContainEqual([12]);
      for (const c of calls) expect(JSON.stringify(c.values.filter((v) => Array.isArray(v)))).toBe('[[12]]');
    });
    it('hospital role without a bound hospital: empty answer, the database is not asked', async () => {
      const r = await get('/v1/hospitals?perPage=50&page=3', UNBOUND).expect(200);
      expect(r.body).toEqual({ scope: 'own', stats: { total: 0, active: 0, inactive: 0, unreviewed: 0, pending: 0 }, total: 0, page: 3, perPage: 50, items: [] });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('query values are normalised and bound', async () => {
      const r = await get(`/v1/hospitals?perPage=15&page=-2&active=maybe&treatmentId=abc&search=${'a'.repeat(300)}`, SUPER).expect(200);
      expect(r.body).toMatchObject({ page: 1, perPage: 20 });
      const list = calls.find((c) => c.sql.includes('LIMIT ?, ?'))!;
      expect(list.values).toEqual([...Array(5).fill(`%${'a'.repeat(100)}%`), 0, 20]);
      expect(list.sql).not.toContain('treatment_id');
      expect(list.sql).not.toContain('is_active = ');

      calls.length = 0;
      const r2 = await get('/v1/hospitals?perPage=50&page=3&active=0&treatmentId=9', SUPER).expect(200);
      expect(r2.body).toMatchObject({ page: 3, perPage: 50 });
      const list2 = calls.find((c) => c.sql.includes('LIMIT ?, ?'))!;
      expect(list2.values).toEqual([9, 100, 50]);
      expect(list2.sql).toContain('COALESCE(h.is_active, 0) = 0');
      // the counters never take the list filters
      expect(calls.find((c) => c.sql.includes('AS unreviewed'))!.values).toEqual([]);
    });
    it('pending=1: super admin gets only pending applications; counters stay approved-only plus the pending count', async () => {
      const r = await get('/v1/hospitals?pending=1&active=1&unreviewed=1', SUPER).expect(200);
      expect(r.body.items.map((i: any) => i.id)).toEqual([14]);
      expect(r.body.total).toBe(1);
      expect(r.body.stats).toEqual({ total: 2, active: 1, inactive: 1, unreviewed: 1, pending: 1 });
      const list = calls.find((c) => c.sql.includes('LIMIT ?, ?'))!;
      expect(list.sql).toContain("h.status = 'pending'");
      expect(list.sql).not.toContain("h.status = 'approved'");
      expect(list.sql).not.toContain('is_active = ');
      expect(list.sql).not.toContain('has_unreviewed_changes = 1');
      expect(list.values).toEqual([0, 20]);
    });
    it('pending=1 keeps the search filter and binds it', async () => {
      await get('/v1/hospitals?pending=1&search=%EC%8B%A0%EC%B2%AD', SUPER).expect(200);
      const list = calls.find((c) => c.sql.includes('LIMIT ?, ?'))!;
      expect(list.sql).toContain('h.name LIKE ?');
      expect(list.values).toEqual([...Array(5).fill('%신청%'), 0, 20]);
    });
    it.each(['0', 'true', '', '2'])('pending=%j is not the pending filter: approved list, 14 never shown', async (v) => {
      const r = await get(`/v1/hospitals?pending=${v}`, SUPER).expect(200);
      expect(r.body.items.map((i: any) => i.id)).toEqual([13, 12]);
      expect(calls.find((c) => c.sql.includes('LIMIT ?, ?'))!.sql).toContain("h.status = 'approved'");
    });
    it('the unreviewed filter is gone: the query parameter changes nothing', async () => {
      const r = await get('/v1/hospitals?unreviewed=1', SUPER).expect(200);
      expect(r.body.items.map((i: any) => i.id)).toEqual([13, 12]);
      expect(calls.find((c) => c.sql.includes('LIMIT ?, ?'))!.sql).not.toContain('AND h.has_unreviewed_changes');
    });
    it.each([HOSPITAL, HOSPITAL_ADMIN, UNBOUND])('pending=1 for a hospital role (%s): empty answer, the database is not asked', async (cookie) => {
      const r = await get('/v1/hospitals?pending=1', cookie).expect(200);
      expect(r.body).toMatchObject({ scope: 'own', total: 0, items: [], stats: { pending: 0 } });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('a database error on a required query is 500 without details', async () => {
      failHospitals = true;
      await get('/v1/hospitals', SUPER).expect(500).expect({ error: 'server_error' });
    });
  });

  describe('GET /v1/hospitals/treatment-options', () => {
    it('super admin: grouped by category', async () => {
      const r = await get('/v1/hospitals/treatment-options', SUPER).expect(200);
      expect(r.body).toEqual({ groups: [{ category: '쁘띠', items: [{ id: 1, name: '보톡스' }] }, { category: '기타', items: [{ id: 2, name: '제모' }] }] });
      expect(calls).toHaveLength(1);
      expect(calls[0].sql).toContain("h.status = 'approved'");
    });
    it('hospital role: only treatments of its own hospital', async () => {
      const r = await get('/v1/hospitals/treatment-options', HOSPITAL).expect(200);
      expect(r.body).toEqual({ groups: [{ category: '쁘띠', items: [{ id: 1, name: '보톡스' }] }] });
      expect(calls[0].sql).toContain('ht.hospital_id IN (?)');
      expect(calls[0].values).toEqual([[12]]);
    });
    it('hospital role without a bound hospital: empty, the database is not asked', async () => {
      await get('/v1/hospitals/treatment-options', UNBOUND).expect(200).expect({ groups: [] });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
  });

  describe('GET /v1/hospitals/:id', () => {
    it('documented shape; no secret or file path in the answer', async () => {
      const r = await get('/v1/hospitals/12', SUPER).expect(200);
      expect(r.body).toEqual({
        hospital: {
          id: 12, name: '서울의원', ownerName: '김원장', businessNumber: '123-45-67890', introduction: '소개', address: '서울 강남구', phone: '02-000-0000',
          email: 'a@example.com', contactName: '담당', contactPhone: '010-0000-0000', website: 'https://example.com', snsUrl: '',
          kakaoChannelUrl: 'https://pf.kakao.com/_x',
          categories: ['피부', '성형'], badges: [], treatmentTags: ['보톡스', '필러'], subwayNames: [], businessHours: { mon: '09:00-18:00' },
          latitude: 37.5, longitude: 127.03, status: 'approved', isActive: true, adGrade: 'A', rating: 4.5, reviewCount: 10, showRating: true,
          hasUnreviewedChanges: true, mainImageUrl: 'uploads/hospitals/12/main.jpg', profileImageUrl: 'https://cdn.example.com/p.jpg',
          appliedBy: 'member1', appliedAt: '2026-10-01 09:00:00', createdAt: '2026-10-02 10:00:00', updatedAt: '2026-11-01 11:00:00',
        },
        documents: { business: true, medical: false }, documentsEditable: false,
        images: [{ id: 3, url: 'uploads/hospitals/12/a.jpg', type: 'interior', sortOrder: 1, isActive: true }],
        doctors: [{ id: 4, name: '이의사', specialty: '피부과', experience: 10, qualification: '전문의', bio: '', photoUrl: 'uploads/hospitals/12/doctor_4.jpg', status: 'active' }],
        revisions: [{ id: 5, field: 'phone', oldValue: '02-1', newValue: '02-2', changedAt: '2026-11-10 12:00:00' }],
      });
      expect(Object.keys(r.body)).toEqual(['hospital', 'documents', 'documentsEditable', 'images', 'doctors', 'revisions']);
    });
    it('hospital role: its own hospital is served, with the scope in SQL too', async () => {
      const r = await get('/v1/hospitals/12', HOSPITAL).expect(200);
      expect(r.body.hospital.id).toBe(12);
      const main = calls.find((c) => c.sql.includes('WHERE h.id = ?'))!;
      expect(main.sql).toContain('h.id IN (?)');
      expect(main.values).toEqual([12, [12]]);
    });
    it.each([HOSPITAL, HOSPITAL_ADMIN, UNBOUND])('hospital role (%s): another hospital is 404 and the database is not asked', async (cookie) => {
      await get('/v1/hospitals/13', cookie).expect(404).expect({ error: 'not_found' });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('hospital role without a bound hospital cannot open any hospital', async () => {
      await get('/v1/hospitals/12', UNBOUND).expect(404).expect({ error: 'not_found' });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('unknown or rejected -> 404, same body as out of scope', async () => {
      await get('/v1/hospitals/15', SUPER).expect(404).expect({ error: 'not_found' });
      await get('/v1/hospitals/999', SUPER).expect(404).expect({ error: 'not_found' });
      // only the main statement ran, and it asks for approved or pending hospitals
      expect(calls).toHaveLength(2);
      for (const c of calls) expect(c.sql).toContain("h.status IN ('approved', 'pending')");
    });
    it('a pending application opens for the super admin, read only: status pending, documents not editable', async () => {
      process.env.ADMIN_HOSPITALS_DOCUMENTS = '1';
      try {
        const r = await get('/v1/hospitals/14', SUPER).expect(200);
        expect(r.body.hospital).toMatchObject({ id: 14, name: '신청의원', status: 'pending', appliedBy: 'member9' });
        expect(r.body.documentsEditable).toBe(false);
        expect(r.body.documents).toEqual({ business: true, medical: true });
        // the owner edits of an application are not listed
        expect(r.body.revisions).toEqual([]);
        expect(calls.some((c) => c.sql.includes('gangnamunni_hospital_revisions'))).toBe(false);
      } finally {
        delete process.env.ADMIN_HOSPITALS_DOCUMENTS;
      }
    });
    it.each([HOSPITAL, HOSPITAL_ADMIN, UNBOUND])('a pending application is 404 for a hospital role (%s), the database is not asked', async (cookie) => {
      await get('/v1/hospitals/14', cookie).expect(404).expect({ error: 'not_found' });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('a hospital role asking for its own hospital only ever asks for approved ones', async () => {
      await get('/v1/hospitals/12', HOSPITAL).expect(200);
      const main = calls.find((c) => c.sql.includes('WHERE h.id = ?'))!;
      expect(main.sql).toContain("h.status = 'approved'");
      expect(main.sql).not.toContain('pending');
    });
    it.each(['abc', '0', '-1', '1.5', '12abc', '01', '99999999999', '12%20OR%201=1'])('id %s is not an id -> 404, the database is not asked', async (id) => {
      await get(`/v1/hospitals/${id}`, SUPER).expect(404).expect({ error: 'not_found' });
      expect(dbStub.query).not.toHaveBeenCalled();
    });
    it('optional column or table missing: that part is empty, the page is served', async () => {
      kakaoColumn = 'missing';
      revisionsTable = 'missing';
      const r = await get('/v1/hospitals/12', SUPER).expect(200);
      expect(r.body.hospital.kakaoChannelUrl).toBe('');
      expect(r.body.revisions).toEqual([]);
      expect(r.body.images).toHaveLength(1);
    });
    it('revisions are only read while the hospital has unreviewed changes', async () => {
      const r = await get('/v1/hospitals/13', SUPER).expect(200);
      expect(r.body.revisions).toEqual([]);
      expect(calls.some((c) => c.sql.includes('gangnamunni_hospital_revisions'))).toBe(false);
    });
  });

  it('every statement is a parameterised SELECT with an explicit column list, and never reads secret columns', async () => {
    await get("/v1/hospitals?search=x'%20OR%201=1--&treatmentId=3&active=1", SUPER).expect(200);
    await get('/v1/hospitals/treatment-options', HOSPITAL).expect(200);
    await get('/v1/hospitals/12', HOSPITAL).expect(200);
    expect(calls.length).toBeGreaterThanOrEqual(9);
    for (const c of calls) {
      expect(c.sql.trim()).toMatch(/^SELECT\b/);
      expect(c.sql).not.toMatch(/SELECT\s+(\w+\.)?\*/);
      expect(c.sql).not.toMatch(/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b/i);
      expect(c.sql).not.toMatch(/ai_own_api_key|password|token/i);
      expect(c.sql).not.toContain("x'");
      expect(c.sql).not.toContain(';');
    }
    // certificate columns are only tested for presence, never selected as values
    const certUses = calls.flatMap((c) => c.sql.match(/[^\n]*cert_[a-z]+[^\n]*/g) || []);
    expect(certUses.length).toBeGreaterThan(0);
    for (const line of certUses) expect(line).toMatch(/IS NOT NULL AND h\.cert_(business|medical) <> ''\) AS has_cert_/);
  });
});
