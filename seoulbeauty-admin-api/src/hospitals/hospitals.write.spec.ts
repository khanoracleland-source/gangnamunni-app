import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { HospitalsModule } from './hospitals.module';
import { DB, DB_RW } from '../db/db.module';
import { WRITABLE_COLUMNS } from './hospitals.write.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const HADMIN = 'PHPSESSID=hadmin12';
const UNBOUND = 'PHPSESSID=unbound';
const PLAIN = 'PHPSESSID=plain';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const T0 = '2026-11-01 11:00:00';
const T1 = '2026-11-16 09:30:00';

describe('admin API: hospital writes (edit, activate, review)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const roCalls: { sql: string; values: unknown[] }[] = [];
  const rwCalls: { sql: string; values: unknown[] }[] = [];
  const txEvents: string[] = [];
  /** updated_at per approved hospital as the database holds it; 14 is pending. */
  let stored: Record<number, string | null>;
  let rwProviderEmpty = false;
  let rwError: (Error & { code?: string; errno?: number }) | null = null;
  let forceAffected: number | null = null;
  const phones = [{ id: 13, phone: '02-9999-0000' }, { id: 15, phone: '+82 31 555 6666' }];

  const roStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      roCalls.push({ sql, values });
      if (sql.includes(`AND status = 'approved'`)) {
        const id = values[0] as number;
        return [id in stored && id !== 14 ? [{ id, updated_at: stored[id] }] : []];
      }
      if (sql.includes('phone IS NOT NULL')) return [phones.filter((p) => p.id !== values[0])];
      if (sql.includes('FROM gangnamunni_treatment_master')) return [[]];
      throw new Error(`unexpected read-only sql: ${sql}`);
    }),
  };
  const rwQuery = async (sql: string, values: unknown[] = []) => {
    rwCalls.push({ sql, values });
    if (rwError) throw rwError;
    if (sql.startsWith('SELECT id, updated_at')) {
      const id = values[0] as number;
      return [id in stored ? [{ id, updated_at: stored[id] }] : []];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospitals') && sql.includes('updated_at <=> ?')) {
      const id = values[values.length - 2] as number;
      const expected = values[values.length - 1];
      const hit = forceAffected === null && id in stored && stored[id] === expected;
      if (hit) stored[id] = T1;
      return [{ affectedRows: forceAffected ?? (hit ? 1 : 0) }];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospitals SET is_active')) {
      stored[values[1] as number] = T1;
      return [{ affectedRows: 1 }];
    }
    return [{ affectedRows: 1 }];
  };
  const rwStub = {
    get: () => (rwProviderEmpty ? null : { query: jest.fn(rwQuery), getConnection: async () => ({
      query: async (sql: string, values: unknown[] = []) => { txEvents.push(sql); return rwQuery(sql, values); },
      beginTransaction: async () => { txEvents.push('BEGIN'); },
      commit: async () => { txEvents.push('COMMIT'); },
      rollback: async () => { txEvents.push('ROLLBACK'); },
      release: () => { txEvents.push('RELEASE'); },
    }) }),
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
      if (cookie.includes('hadmin12')) return json({ loggedIn: true, admin: admin('hospital_admin', 12) });
      if (cookie.includes('unbound')) return json({ loggedIn: true, admin: admin('hospital_admin', null) });
      if (cookie.includes('plain')) return json({ loggedIn: true, admin: admin('admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_WRITE = '1';
    phpCalls.length = 0;
    roCalls.length = 0;
    rwCalls.length = 0;
    txEvents.length = 0;
    stored = { 12: T0, 13: '2026-10-01 10:00:00', 14: '2026-10-01 10:00:00' };
    rwProviderEmpty = false;
    rwError = null;
    forceAffected = null;
    await boot();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    delete process.env.ADMIN_PANEL_ENABLED; delete process.env.ADMIN_HOSPITALS_ENABLED; delete process.env.ADMIN_HOSPITALS_WRITE;
  });

  const send = (method: 'patch' | 'post', path: string, body: unknown, cookie?: string, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer())[method](path);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r.send(typeof body === 'object' && headers['Content-Type'] !== 'application/json' ? JSON.stringify(body) : (body as object));
  };
  const patch = (id: number | string, body: unknown, cookie: string = SUPER) => send('patch', `/v1/hospitals/${id}`, body, cookie);
  const noWrites = () => expect(rwCalls).toHaveLength(0);

  describe('switch', () => {
    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITALS_WRITE=%p -> all three routes 404, nothing asked or written', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_WRITE; else process.env.ADMIN_HOSPITALS_WRITE = v;
      for (const [m, p] of [['patch', '/v1/hospitals/12'], ['post', '/v1/hospitals/12/active'], ['post', '/v1/hospitals/12/revisions/review']] as const) {
        const r = await send(m, p, { fields: { name: 'x' } }, SUPER);
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
      }
      expect(phpCalls).toHaveLength(0);
      expect(roCalls).toHaveLength(0);
      noWrites();
    });

    it('write switch needs the hospital switch too', async () => {
      process.env.ADMIN_HOSPITALS_ENABLED = '0';
      expect((await patch(12, { expectedUpdatedAt: T0, fields: { name: 'x' } })).status).toBe(404);
      const me = await request(app.getHttpServer()).get('/v1/me').set('Cookie', SUPER);
      expect(me.body.features).toEqual({ hospitals: false, hospitalsWrite: false, hospitalsDelete: false, archive: false, hospitalsReview: false, hospitalsOrder: false, doctors: false, doctorsWrite: false, doctorsOrder: false, doctorsDelete: false, hospitalsImages: false, hospitalsDocuments: false, hospitalsDoctors: false, hospitalsTreatments: false, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false, hospitalsTreatmentsImages: false, treatments: false, treatmentsWrite: false, treatmentsImages: false, treatmentUnits: false, treatmentUnitsWrite: false, hospitalApply: false, admins: false, adminsWrite: false, bookings: false, bookingsWrite: false, consultations: false, consultationsWrite: false, payments: false, moderation: false, moderationWrite: false, moderationEdit: false, moderationDelete: false, banners: false, bannersWrite: false, categoryBoard: false, categoryBoardWrite: false, members: false, membersActivity: false, membersPassword: false, membersStatus: false, membersWrite: false, paymentsRefund: false, notifications: false, notificationsSend: false, notificationsSchedule: false });
    });

    it('/v1/me reports hospitalsWrite', async () => {
      const on = await request(app.getHttpServer()).get('/v1/me').set('Cookie', SUPER);
      expect(on.body.features).toEqual({ hospitals: true, hospitalsWrite: true, hospitalsDelete: false, archive: false, hospitalsReview: false, hospitalsOrder: false, doctors: false, doctorsWrite: false, doctorsOrder: false, doctorsDelete: false, hospitalsImages: false, hospitalsDocuments: false, hospitalsDoctors: false, hospitalsTreatments: false, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false, hospitalsTreatmentsImages: false, treatments: false, treatmentsWrite: false, treatmentsImages: false, treatmentUnits: false, treatmentUnitsWrite: false, hospitalApply: false, admins: false, adminsWrite: false, bookings: false, bookingsWrite: false, consultations: false, consultationsWrite: false, payments: false, moderation: false, moderationWrite: false, moderationEdit: false, moderationDelete: false, banners: false, bannersWrite: false, categoryBoard: false, categoryBoardWrite: false, members: false, membersActivity: false, membersPassword: false, membersStatus: false, membersWrite: false, paymentsRefund: false, notifications: false, notificationsSend: false, notificationsSchedule: false });
      process.env.ADMIN_HOSPITALS_WRITE = '0';
      const off = await request(app.getHttpServer()).get('/v1/me').set('Cookie', SUPER);
      expect(off.body.features).toEqual({ hospitals: true, hospitalsWrite: false, hospitalsDelete: false, archive: false, hospitalsReview: false, hospitalsOrder: false, doctors: false, doctorsWrite: false, doctorsOrder: false, doctorsDelete: false, hospitalsImages: false, hospitalsDocuments: false, hospitalsDoctors: false, hospitalsTreatments: false, hospitalsTreatmentsRemove: false, hospitalsTreatmentsPrices: false, hospitalsTreatmentsQuestions: false, hospitalsTreatmentsImages: false, treatments: false, treatmentsWrite: false, treatmentsImages: false, treatmentUnits: false, treatmentUnitsWrite: false, hospitalApply: false, admins: false, adminsWrite: false, bookings: false, bookingsWrite: false, consultations: false, consultationsWrite: false, payments: false, moderation: false, moderationWrite: false, moderationEdit: false, moderationDelete: false, banners: false, bannersWrite: false, categoryBoard: false, categoryBoardWrite: false, members: false, membersActivity: false, membersPassword: false, membersStatus: false, membersWrite: false, paymentsRefund: false, notifications: false, notificationsSend: false, notificationsSchedule: false });
    });
  });

  describe('CSRF and login', () => {
    const body = { expectedUpdatedAt: T0, fields: { name: 'x' } };
    it('rejects a missing marker header, a wrong content type and a foreign Origin with 403 csrf', async () => {
      for (const h of [
        { 'Content-Type': 'application/json' },
        { 'X-Requested-With': 'admin-next', 'Content-Type': 'text/plain' },
        { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
        { ...HEADERS, Origin: 'https://evil.example', Host: 'seoulbeauty.app' },
        { ...HEADERS, Origin: 'https://evil.example', 'X-Forwarded-Host': 'seoulbeauty.app' },
        { ...HEADERS, Origin: 'null' },
      ] as Record<string, string>[]) {
        const r = await send('patch', '/v1/hospitals/12', body, SUPER, h);
        expect(r.status).toBe(403);
        expect(r.body).toEqual({ error: 'csrf' });
      }
      expect(phpCalls).toHaveLength(0);
      noWrites();
    });
    it('also guards active and review', async () => {
      expect((await send('post', '/v1/hospitals/12/active', { active: false }, SUPER, { 'Content-Type': 'application/json' })).body).toEqual({ error: 'csrf' });
      expect((await send('post', '/v1/hospitals/12/revisions/review', 'a=1', SUPER, { 'X-Requested-With': 'admin-next' })).body).toEqual({ error: 'csrf' });
      noWrites();
    });
    it('accepts an Origin equal to the forwarded host or to the Host', async () => {
      const a = await send('patch', '/v1/hospitals/12', body, SUPER, { ...HEADERS, Origin: 'https://seoulbeauty.app', 'X-Forwarded-Host': 'seoulbeauty.app' });
      expect(a.status).toBe(200);
      const b = await send('patch', '/v1/hospitals/12', { ...body, expectedUpdatedAt: T1 }, SUPER, { ...HEADERS, Origin: 'http://127.0.0.1:3300', Host: '127.0.0.1:3300' });
      expect(b.status).toBe(200);
    });
    it('401 when not signed in (with the headers)', async () => {
      const r = await send('patch', '/v1/hospitals/12', body, undefined);
      expect(r.status).toBe(401);
      expect(r.body).toEqual({ error: 'not_logged_in' });
      noWrites();
    });
    it('403 for roles outside this panel', async () => {
      expect((await patch(12, body, PLAIN)).status).toBe(403);
      noWrites();
    });
  });

  describe('PATCH /v1/hospitals/:id', () => {
    it('super admin: stores PHP-format values with the optimistic-lock WHERE and returns the new updatedAt', async () => {
      const r = await patch(12, {
        expectedUpdatedAt: T0,
        fields: {
          name: ' 서울의원 ', phone: '+82 2 1234 5678', categories: ['피부', '성형'], badges: [], treatmentTags: ['보톡스'], subwayNames: ['강남역'],
          businessHours: { mon: '09:00-18:00' }, latitude: 37.5, longitude: 127.03, showRating: false, email: 'a@b.co', website: 'https://x.y',
        },
      });
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ updatedAt: T1 });
      const update = rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_hospitals'))!;
      expect(update.sql).toBe(
        'UPDATE gangnamunni_hospitals SET name = ?, phone = ?, categories = ?, badges = ?, treatment_tags = ?, subway_names = ?, business_hours = ?, show_rating = ?, email = ?, website = ?, latitude = ?, longitude = ?, updated_at = NOW() WHERE id = ? AND updated_at <=> ?',
      );
      expect(update.values).toEqual([
        '서울의원', '02-1234-5678', '["피부","성형"]', '[]', '["보톡스"]', '["강남역"]', '{"mon":"09:00-18:00"}', 0, 'a@b.co', 'https://x.y', 37.5, 127.03, 12, T0,
      ]);
    });

    it('writes only whitelisted columns (never ad_grade, status, rating ...)', async () => {
      await patch(12, { expectedUpdatedAt: T0, fields: { name: 'x', ownerName: 'a', businessNumber: 'b', introduction: 'c', address: 'd', contactName: 'e', contactPhone: 'f', snsUrl: '', phone: '' } });
      for (const c of rwCalls.filter((x) => x.sql.startsWith('UPDATE'))) {
        const set = c.sql.match(/SET (.*) WHERE/)![1].split(', ').map((p) => p.split(' = ')[0]);
        for (const col of set) expect(WRITABLE_COLUMNS).toContain(col);
        expect(c.sql).not.toMatch(/ad_grade|status|rating|cert_|SELECT \*/);
      }
    });

    it('hospital roles edit their own hospital', async () => {
      expect((await patch(12, { expectedUpdatedAt: T0, fields: { address: '서울' } }, HOSPITAL)).status).toBe(200);
      expect((await patch(12, { expectedUpdatedAt: T1, fields: { address: '부산' } }, HADMIN)).status).toBe(200);
    });

    it('a hospital role cannot touch another hospital: 404, nothing read or written', async () => {
      const r = await patch(13, { expectedUpdatedAt: '2026-10-01 10:00:00', fields: { name: 'hijack' } }, HOSPITAL);
      expect(r.status).toBe(404);
      expect(r.body).toEqual({ error: 'not_found' });
      expect((await patch(13, { fields: { name: 'x' } }, UNBOUND)).status).toBe(404);
      expect(roCalls).toHaveLength(0);
      noWrites();
    });

    it('404 for an unknown, pending or malformed id', async () => {
      for (const id of [999, 14, 'abc', '0', '12abc', 'treatment-options']) expect((await patch(id, { expectedUpdatedAt: T0, fields: { name: 'x' } })).status).toBe(404);
      noWrites();
    });

    it('400 with per-field errors: not allowed, bad values, missing expectedUpdatedAt', async () => {
      const r = await patch(12, { fields: { adGrade: 'S', status: 'approved', isActive: false, kakaoChannelUrl: 'https://x.y', name: '', email: 'x', website: 'ftp://x' } });
      expect(r.status).toBe(400);
      expect(r.body).toEqual({
        error: 'invalid',
        fields: { adGrade: 'not_allowed', status: 'not_allowed', isActive: 'not_allowed', kakaoChannelUrl: 'not_allowed', name: 'required', email: 'bad_format', website: 'bad_url', expectedUpdatedAt: 'required' },
      });
      noWrites();
    });
    it('400 for empty fields or a bad expectedUpdatedAt', async () => {
      expect((await patch(12, { expectedUpdatedAt: T0, fields: {} })).body).toEqual({ error: 'invalid', fields: { fields: 'required' } });
      expect((await patch(12, { expectedUpdatedAt: T0 })).body).toEqual({ error: 'invalid', fields: { fields: 'required' } });
      expect((await patch(12, { expectedUpdatedAt: 'yesterday', fields: { name: 'x' } })).body).toEqual({ error: 'invalid', fields: { expectedUpdatedAt: 'bad_format' } });
      noWrites();
    });

    it('duplicate phone (any formatting) -> duplicate_phone without naming the other hospital; own number is fine', async () => {
      const r = await patch(12, { expectedUpdatedAt: T0, fields: { phone: '02 9999 0000' } });
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'invalid', fields: { phone: 'duplicate_phone' } });
      expect(JSON.stringify(r.body)).not.toMatch(/13|다른/);
      expect((await patch(12, { expectedUpdatedAt: T0, fields: { phone: '+82-31-555-6666' } })).body).toEqual({ error: 'invalid', fields: { phone: 'duplicate_phone' } });
      noWrites();
      expect((await patch(12, { expectedUpdatedAt: T0, fields: { phone: '02-1111-2222' } })).status).toBe(200);
      const dup = roCalls.find((c) => c.sql.includes('phone IS NOT NULL'))!;
      expect(dup.sql).toBe(`SELECT id, phone FROM gangnamunni_hospitals WHERE phone IS NOT NULL AND phone <> '' AND id <> ?`);
    });

    it('409 conflict when updated_at already moved (nothing written) and when it moves during the write', async () => {
      stored[12] = T1;
      const early = await patch(12, { expectedUpdatedAt: T0, fields: { name: 'x' } });
      expect(early.status).toBe(409);
      expect(early.body).toEqual({ error: 'conflict', updatedAt: T1 });
      noWrites();

      // The pre-read still sees the old value but the guarded UPDATE matches nothing (changed in between).
      stored[12] = T0;
      forceAffected = 0;
      const late = await patch(12, { expectedUpdatedAt: T0, fields: { name: 'x' } });
      expect(late.status).toBe(409);
      expect(late.body).toEqual({ error: 'conflict', updatedAt: T0 });
    });

    it('expectedUpdatedAt null matches a NULL updated_at', async () => {
      stored[12] = null;
      const r = await patch(12, { expectedUpdatedAt: null, fields: { name: 'x' } });
      expect(r.status).toBe(200);
      const update = rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_hospitals'));
      expect(update?.values.slice(-2)).toEqual([12, null]);
    });

    it('audit log has admin, hospital and field NAMES only', async () => {
      const spy = jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
      await patch(12, { expectedUpdatedAt: T0, fields: { name: '비밀병원이름', phone: '02-1111-2222', latitude: 1, longitude: 2 } });
      const line = spy.mock.calls.map((c) => String(c[0])).find((l) => l.startsWith('hospital write'))!;
      expect(line).toBe('hospital write: admin=7 role=super_admin hospital=12 action=update fields=name,phone,latitude,longitude');
      expect(line).not.toMatch(/비밀|1111/);
    });

    it('all SQL is parameterised: no request value appears in the statement text', async () => {
      await patch(12, { expectedUpdatedAt: T0, fields: { name: "x'; DROP TABLE a;--", address: 'ADDR_MARK' } });
      for (const c of [...rwCalls, ...roCalls]) expect(c.sql).not.toMatch(/DROP TABLE|ADDR_MARK/);
    });
  });

  describe('write database unavailable', () => {
    it('503 write_unavailable when no write user is configured; reads and the existence check still work', async () => {
      rwProviderEmpty = true;
      const r = await patch(12, { expectedUpdatedAt: T0, fields: { name: 'x' } });
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      const me = await request(app.getHttpServer()).get('/v1/me').set('Cookie', SUPER);
      expect(me.status).toBe(200);
    });
    it.each([['ER_ACCESS_DENIED_ERROR', 1045], ['ER_COLUMNACCESS_DENIED_ERROR', 1143], ['ECONNREFUSED', 0]])('503 on %s', async (code, errno) => {
      rwError = Object.assign(new Error('x'), { code, errno });
      for (const r of [
        await patch(12, { expectedUpdatedAt: T0, fields: { name: 'x' } }),
        await send('post', '/v1/hospitals/12/active', { active: true }, SUPER),
        await send('post', '/v1/hospitals/12/revisions/review', {}, SUPER),
      ]) {
        expect(r.status).toBe(503);
        expect(r.body).toEqual({ error: 'write_unavailable' });
      }
    });
    it('any other database error is a 500 server_error without details', async () => {
      rwError = Object.assign(new Error('Data too long for column secret'), { code: 'ER_DATA_TOO_LONG', errno: 1406 });
      jest.spyOn(Logger.prototype, 'error').mockImplementation(() => undefined);
      const r = await patch(12, { expectedUpdatedAt: T0, fields: { name: 'x' } });
      expect(r.status).toBe(500);
      expect(r.body).toEqual({ error: 'server_error' });
    });
  });

  describe('a pending application is never written', () => {
    it('edit, active switch and revision review on a pending hospital -> 404 asking for approved only; nothing written', async () => {
      const answers = [
        await patch(14, { expectedUpdatedAt: T0, fields: { name: 'x' } }),
        await send('post', '/v1/hospitals/14/active', { active: true }, SUPER),
        await send('post', '/v1/hospitals/14/revisions/review', {}, SUPER),
      ];
      for (const r of answers) {
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
      }
      expect(roCalls).toHaveLength(3);
      for (const c of roCalls) expect(c.sql).toContain(`AND status = 'approved'`);
      noWrites();
    });
  });

  describe('POST /v1/hospitals/:id/active', () => {
    it('super admin sets the exact value', async () => {
      const off = await send('post', '/v1/hospitals/12/active', { active: false }, SUPER);
      expect(off.status).toBe(200);
      expect(off.body).toEqual({ isActive: false, updatedAt: T1 });
      expect(rwCalls[0]).toEqual({ sql: 'UPDATE gangnamunni_hospitals SET is_active = ?, updated_at = NOW() WHERE id = ?', values: [0, 12] });
      await send('post', '/v1/hospitals/12/active', { active: true }, SUPER);
      expect(rwCalls.filter((c) => c.sql.includes('is_active')).pop()!.values).toEqual([1, 12]);
    });
    it('hospital roles get 403 (even for their own hospital); nothing is read or written', async () => {
      for (const c of [HOSPITAL, HADMIN, UNBOUND]) {
        const r = await send('post', '/v1/hospitals/12/active', { active: false }, c);
        expect(r.status).toBe(403);
        expect(r.body).toEqual({ error: 'forbidden' });
      }
      expect(roCalls).toHaveLength(0);
      noWrites();
    });
    it('400 for a missing or non-boolean value; 404 for unknown or pending', async () => {
      expect((await send('post', '/v1/hospitals/12/active', {}, SUPER)).body).toEqual({ error: 'invalid', fields: { active: 'required' } });
      expect((await send('post', '/v1/hospitals/12/active', { active: 'yes' }, SUPER)).body).toEqual({ error: 'invalid', fields: { active: 'bad_format' } });
      expect((await send('post', '/v1/hospitals/14/active', { active: true }, SUPER)).status).toBe(404);
      expect((await send('post', '/v1/hospitals/x/active', { active: true }, SUPER)).status).toBe(404);
      noWrites();
    });
  });

  describe('POST /v1/hospitals/:id/revisions/review', () => {
    it('super admin: one short transaction marks the revisions and clears the flag', async () => {
      const r = await send('post', '/v1/hospitals/12/revisions/review', {}, SUPER);
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ ok: true });
      expect(txEvents).toEqual([
        'BEGIN',
        'UPDATE gangnamunni_hospital_revisions SET reviewed = 1 WHERE hospital_id = ? AND reviewed = 0',
        'UPDATE gangnamunni_hospitals SET has_unreviewed_changes = 0 WHERE id = ?',
        'COMMIT',
        'RELEASE',
      ]);
      expect(rwCalls.map((c) => c.values)).toEqual([[12], [12]]);
    });
    it('rolls back and releases when a statement fails', async () => {
      rwError = Object.assign(new Error('boom'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      jest.spyOn(Logger.prototype, 'error').mockImplementation(() => undefined);
      const r = await send('post', '/v1/hospitals/12/revisions/review', {}, SUPER);
      expect(r.status).toBe(500);
      expect(txEvents).toContain('ROLLBACK');
      expect(txEvents).not.toContain('COMMIT');
      expect(txEvents[txEvents.length - 1]).toBe('RELEASE');
    });
    it('hospital roles get 403; unknown or pending hospital 404', async () => {
      for (const c of [HOSPITAL, HADMIN]) expect((await send('post', '/v1/hospitals/12/revisions/review', {}, c)).status).toBe(403);
      expect((await send('post', '/v1/hospitals/14/revisions/review', {}, SUPER)).status).toBe(404);
      expect((await send('post', '/v1/hospitals/999/revisions/review', {}, SUPER)).status).toBe(404);
      noWrites();
    });
  });

  it('read routes are unchanged by the write module (GET still reaches the read-only service)', async () => {
    const r = await request(app.getHttpServer()).get('/v1/hospitals/treatment-options').set('Cookie', SUPER);
    expect(r.status).toBe(200);
    expect(r.body).toEqual({ groups: [] });
  });
});
