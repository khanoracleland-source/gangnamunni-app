import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { HospitalsModule } from './hospitals.module';
import { HospitalArchiveModule } from '../hospital-archive/hospital-archive.module';
import { DB, DB_RW } from '../db/db.module';
import { BOOKING_CANCELLED_STATUSES, PAYMENT_PAID_STATUSES, PAYMENT_TEST_PROVIDERS } from '../dashboard/dashboard.logic';
import { buildBlockers, buildSnapshot, nameMatches, SNAPSHOT_HOSPITAL_COLUMNS } from './hospitals.delete.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const T0 = '2026-11-01 11:00:00';

type Call = { sql: string; values: unknown[] };

describe('admin API: hospital stats, delete-check, delete and archive', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const roCalls: Call[] = [];
  const rwCalls: Call[] = [];
  const txEvents: string[] = [];
  // state of the fake database
  let hospitals: Record<number, { name: string; status: string; updated_at: string | null }>;
  let openBookings: { cnt: number; last_at: string | null };
  let openConsultations: number;
  let openSettlements: number;
  let lastSettlement: Record<string, unknown> | null;
  let unsettledPayments: number;
  let unsettledRefunds: number;
  let failOn: string | null;
  let deleteAffected: number;
  let archiveMissing: boolean;
  let archiveRows: Record<string, unknown>[];
  /** runs before a statement of the transaction connection (lets a test change the data mid-transaction or fail a statement) */
  let txHook: ((sql: string) => void) | null;

  const hospitalRow = (id: number) => ({
    id, name: hospitals[id].name, updated_at: hospitals[id].updated_at, owner_name: '홍', phone: '02-1', status: hospitals[id].status,
    // columns that must never reach the snapshot (the fake row holds them to prove the whitelist works)
    ai_own_api_key: 'sk-secret', cert_business: '/files/cert.pdf', cert_medical: '/files/med.pdf',
  });

  /** One fake database for the read-only pool and the transaction connection. */
  const handler = async (sql: string, values: unknown[] = []): Promise<unknown> => {
    if (failOn && sql.includes(failOn)) throw Object.assign(new Error('boom'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
    const id = values[0] as number;
    if (sql.includes('FROM gangnamunni_hospital_deletions')) {
      if (archiveMissing) throw Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
      if (sql.includes('COUNT(*) AS total')) return [[{ total: archiveRows.length, due: archiveRows.filter((r) => r.notice_due === 1).length }]];
      if (sql.includes('WHERE id = ?')) return [archiveRows.filter((r) => r.id === id)];
      return [archiveRows];
    }
    if (sql.startsWith('UPDATE gangnamunni_hospital_deletions')) return [{ affectedRows: 1 }];
    const approved = !!hospitals[id] && hospitals[id].status === 'approved';
    if (sql.startsWith('SELECT id, name, updated_at FROM gangnamunni_hospitals') || sql.startsWith('SELECT id, name FROM gangnamunni_hospitals')) {
      expect(sql).toContain("AND status = 'approved'");
      return [approved ? [{ id, name: hospitals[id].name, updated_at: hospitals[id].updated_at }] : []];
    }
    if (sql.includes("AND status = 'approved' LIMIT 1 FOR UPDATE")) return [approved ? [hospitalRow(id)] : []];
    if (sql.includes("AND status = 'approved' LIMIT 1")) return [approved ? [{ id }] : []];
    if (sql.includes('FROM gangnamunni_bookings')) {
      if (sql.includes('MAX(TIMESTAMP')) return [[openBookings]];
      if (sql.includes('from_date')) return [[{ from_date: '2026-11-16', to_date: '2026-11-18', created: 5, cancelled: 2 }]];
      return [[{ total: 40, cancelled: 6 }]];
    }
    if (sql.includes('FROM gangnamunni_consultations')) return [[{ cnt: openConsultations }]];
    if (sql.includes('FROM gangnamunni_payment_events')) return [[{ cnt: unsettledRefunds }]];
    if (sql.includes('FROM gangnamunni_platform_payments p')) return [[{ cnt: unsettledPayments }]];
    if (sql.includes('FROM gangnamunni_hospital_settlements')) {
      if (sql.includes('open_cnt')) return [[{ open_cnt: openSettlements }]];
      return [lastSettlement ? [lastSettlement] : []];
    }
    if (sql.includes('FROM gangnamunni_platform_payments')) {
      if (sql.includes('COUNT(*)')) return [[{ cnt: unsettledPayments }]];
      return [[{ paid: 300000, refunded: 50000 }]];
    }
    if (sql.includes('FROM gangnamunni_doctors WHERE hospital_id = ? ORDER BY')) return [[{ id: 1, name: '의사', specialty: '원장' }]];
    if (sql.includes('FROM gangnamunni_doctors')) return [[{ cnt: 2 }]];
    if (sql.includes('FROM hospital_images WHERE hospital_id = ? ORDER BY')) return [[{ id: 9, image_type: 'main', image_url: '/img/9.jpg' }]];
    if (sql.includes('FROM hospital_images')) return [[{ cnt: 3 }]];
    if (sql.includes('FROM gangnamunni_hospital_treatments')) return [[{ cnt: 4 }]];
    if (sql.startsWith('INSERT INTO gangnamunni_hospital_deletions')) return [{ insertId: 12 }];
    if (sql.startsWith('DELETE FROM gangnamunni_hospitals')) return [{ affectedRows: deleteAffected }];
    if (sql.startsWith('DELETE')) return [{ affectedRows: 1 }];
    throw new Error(`unexpected sql: ${sql}`);
  };
  const roStub = { query: jest.fn(async (sql: string, values: unknown[] = []) => { roCalls.push({ sql, values }); return handler(sql, values); }) };
  const rwStub = {
    get: () => ({
      query: jest.fn(async (sql: string, values: unknown[] = []) => { rwCalls.push({ sql, values }); return handler(sql, values); }),
      getConnection: async () => ({
        query: async (sql: string, values: unknown[] = []) => {
          txHook?.(sql);
          rwCalls.push({ sql, values });
          txEvents.push(sql.split(' ').slice(0, 3).join(' '));
          return handler(sql, values);
        },
        beginTransaction: async () => { txEvents.push('BEGIN'); },
        commit: async () => { txEvents.push('COMMIT'); },
        rollback: async () => { txEvents.push('ROLLBACK'); },
        release: () => { txEvents.push('RELEASE'); },
      }),
    }),
  };

  async function boot(): Promise<void> {
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, HospitalsModule, HospitalArchiveModule] }).compile();
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
    process.env.ADMIN_HOSPITALS_DELETE = '1';
    phpCalls.length = 0; roCalls.length = 0; rwCalls.length = 0; txEvents.length = 0;
    hospitals = { 12: { name: '서울병원', status: 'approved', updated_at: T0 }, 13: { name: '대기병원', status: 'pending', updated_at: null } };
    openBookings = { cnt: 0, last_at: null };
    openConsultations = 0;
    openSettlements = 0;
    lastSettlement = { status: 'paid', period_end: '2026-10-31', balance: 1000 };
    unsettledPayments = 0;
    unsettledRefunds = 0;
    failOn = null;
    deleteAffected = 1;
    archiveMissing = false;
    archiveRows = [];
    txHook = null;
    await boot();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_DELETE']) delete process.env[k];
  });

  const get = (path: string, cookie?: string) => { let r = request(app.getHttpServer()).get(path); if (cookie) r = r.set('Cookie', cookie); return r; };
  const post = (path: string, body: unknown, cookie?: string, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post(path);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r.send(JSON.stringify(body));
  };
  const goodBody = { confirmName: '서울병원', expectedUpdatedAt: T0 };
  const del = (id = 12, body: unknown = goodBody) => post(`/v1/hospitals/${id}/delete`, body, SUPER);
  const rwWrites = () => rwCalls.filter((c) => /^(INSERT|UPDATE|DELETE)/.test(c.sql));

  describe('switches and /v1/me', () => {
    it.each([undefined, '0', 'true'])('ADMIN_HOSPITALS_DELETE=%p -> delete-check, delete and archive 404; flags false; stats still works', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_DELETE; else process.env.ADMIN_HOSPITALS_DELETE = v;
      for (const [m, p] of [['get', '/v1/hospitals/12/delete-check'], ['post', '/v1/hospitals/12/delete'], ['get', '/v1/hospital-archive'], ['get', '/v1/hospital-archive/1'], ['post', '/v1/hospital-archive/1/ack']] as const) {
        const r = m === 'get' ? await get(p, SUPER) : await post(p, goodBody, SUPER);
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
      }
      expect(phpCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
      expect((await get('/v1/me', SUPER)).body.features).toMatchObject({ hospitalsDelete: false, archive: false });
      expect((await get('/v1/hospitals/12/stats', SUPER)).status).toBe(200);
    });

    it.each(['ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_ENABLED'])('needs %s as well', async (k) => {
      process.env[k] = '0';
      expect((await get('/v1/hospitals/12/delete-check', SUPER)).status).toBe(404);
      expect((await get('/v1/hospital-archive', SUPER)).status).toBe(404);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsDelete).toBe(false);
    });

    it('all three on -> /v1/me reports both flags and keeps the old ones', async () => {
      expect((await get('/v1/me', SUPER)).body.features).toMatchObject({ hospitals: true, hospitalsWrite: true, hospitalsDelete: true, archive: true });
      expect((await get('/v1/me', HOSPITAL)).status).toBe(200);
    });
  });

  describe('roles and scope', () => {
    it('hospital roles: delete-check, delete, archive -> 403; nothing read or written', async () => {
      expect((await get('/v1/hospitals/12/delete-check', HOSPITAL)).status).toBe(403);
      expect((await post('/v1/hospitals/12/delete', goodBody, HOSPITAL)).status).toBe(403);
      expect((await get('/v1/hospital-archive', HOSPITAL)).status).toBe(403);
      expect((await get('/v1/hospital-archive/1', HOSPITAL)).status).toBe(403);
      expect((await post('/v1/hospital-archive/1/ack', {}, HOSPITAL)).status).toBe(403);
      expect(roCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
    });

    it('not signed in -> 401', async () => {
      expect((await get('/v1/hospitals/12/delete-check')).status).toBe(401);
      expect((await post('/v1/hospitals/12/delete', goodBody)).status).toBe(401);
      expect((await get('/v1/hospital-archive')).status).toBe(401);
    });

    it('missing CSRF marker -> 403 csrf before anything is looked at', async () => {
      const r = await post('/v1/hospitals/12/delete', goodBody, SUPER, { 'Content-Type': 'application/json' });
      expect(r.status).toBe(403);
      expect(r.body).toEqual({ error: 'csrf' });
      expect((await post('/v1/hospital-archive/1/ack', {}, SUPER, { 'Content-Type': 'application/json' })).body).toEqual({ error: 'csrf' });
      expect(phpCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
    });

    it('stats: a hospital role sees only its own hospital; another id and a pending one are 404', async () => {
      hospitals[14] = { name: 'x', status: 'approved', updated_at: null };
      expect((await get('/v1/hospitals/12/stats', HOSPITAL)).status).toBe(200);
      expect((await get('/v1/hospitals/14/stats', HOSPITAL)).status).toBe(404);
      expect((await get('/v1/hospitals/13/stats', SUPER)).status).toBe(404);
      expect((await get('/v1/hospitals/99/stats', SUPER)).status).toBe(404);
      expect((await get('/v1/hospitals/abc/stats', SUPER)).status).toBe(404);
      expect((await get('/v1/hospitals/12/stats')).status).toBe(401);
    });
  });

  describe('stats', () => {
    it('answers week and month with the contract shape', async () => {
      const r = await get('/v1/hospitals/12/stats', SUPER);
      expect(r.status).toBe(200);
      const period = { from: '2026-11-16', to: '2026-11-18', bookings: { created: 5, cancelled: 2, active: 3 }, sales: { paid: 300000, refunded: 50000, net: 250000 } };
      expect(r.body).toEqual({ week: period, month: period });
    });

    it('sales use the dashboard rules (real provider, paid statuses, hospital funds, booking); bookings use the dashboard cancelled statuses', async () => {
      await get('/v1/hospitals/12/stats', SUPER);
      const pay = roCalls.filter((c) => c.sql.includes('FROM gangnamunni_platform_payments'));
      expect(pay).toHaveLength(2);
      for (const c of pay) {
        expect(c.sql).toContain(`payment_type = 'booking' AND funds_recipient = 'hospital'`);
        expect(c.sql).toContain('provider NOT IN (?) AND status IN (?)');
        expect(c.sql).toContain('paid_at >=');
        expect(c.values).toEqual([12, PAYMENT_TEST_PROVIDERS, PAYMENT_PAID_STATUSES]);
      }
      for (const c of roCalls.filter((x) => x.sql.includes('FROM gangnamunni_bookings'))) expect(c.values).toEqual([BOOKING_CANCELLED_STATUSES, 12]);
    });

    it('week starts on Monday and month on the 1st (computed in the database, KST)', async () => {
      await get('/v1/hospitals/12/stats', SUPER);
      const sqls = roCalls.map((c) => c.sql).join('\n');
      expect(sqls).toContain('DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)');
      expect(sqls).toContain(`DATE_FORMAT(CURDATE(), '%Y-%m-01')`);
    });

    it('a database error is 500 server_error, never zeros', async () => {
      failOn = 'FROM gangnamunni_platform_payments';
      const r = await get('/v1/hospitals/12/stats', SUPER);
      expect(r.status).toBe(500);
      expect(r.body).toEqual({ error: 'server_error' });
    });

    it('every statement is parameterised: the hospital id is never in the SQL text', async () => {
      await get('/v1/hospitals/12/stats', SUPER);
      for (const c of roCalls) expect(c.sql).not.toMatch(/hospital_id = 12/);
    });
  });

  describe('delete-check', () => {
    it('nothing in the way -> canDelete with the sealed figures and what will be removed', async () => {
      const r = await get('/v1/hospitals/12/delete-check', SUPER);
      expect(r.status).toBe(200);
      expect(r.body).toEqual({
        canDelete: true, name: '서울병원', blockers: [], deletableAfter: null,
        stats: { bookings: 40, cancelled: 6, sales: { paid: 300000, refunded: 50000, net: 250000 } },
        willDelete: { images: 3, doctors: 2, treatments: 4 },
      });
    });

    it('open bookings block and name the latest time; deletableAfter = that time', async () => {
      openBookings = { cnt: 3, last_at: '2026-12-11 16:00:00' };
      const r = await get('/v1/hospitals/12/delete-check', SUPER);
      expect(r.body.canDelete).toBe(false);
      expect(r.body.blockers).toEqual([{ code: 'upcoming_bookings', count: 3, lastAt: '2026-12-11 16:00:00' }]);
      expect(r.body.deletableAfter).toBe('2026-12-11 16:00:00');
    });

    it('unsettled money blocks and removes deletableAfter', async () => {
      openBookings = { cnt: 1, last_at: '2026-12-11 16:00:00' };
      openSettlements = 2;
      unsettledPayments = 5;
      const r = await get('/v1/hospitals/12/delete-check', SUPER);
      expect(r.body.blockers).toEqual([
        { code: 'upcoming_bookings', count: 1, lastAt: '2026-12-11 16:00:00' },
        { code: 'unsettled', settlements: 2, unsettledPayments: 5 },
      ]);
      expect(r.body.deletableAfter).toBeNull();
    });

    it('open consultations block (no date: deletableAfter stays null)', async () => {
      openConsultations = 1;
      const r = await get('/v1/hospitals/12/delete-check', SUPER);
      expect(r.body.blockers).toEqual([{ code: 'open_consultations', count: 1, lastAt: null }]);
      expect(r.body.deletableAfter).toBeNull();
    });

    it('payments and refunds are counted when no settlement period covers them (same real-payment rules as the dashboard and the monthly job)', async () => {
      await get('/v1/hospitals/12/delete-check', SUPER);
      const pay = roCalls.find((c) => c.sql.includes('COUNT(*)') && c.sql.includes('FROM gangnamunni_platform_payments p'))!;
      expect(pay.sql).toContain('NOT EXISTS (SELECT 1 FROM gangnamunni_hospital_settlements s');
      expect(pay.sql).toContain('p.paid_at >= s.period_start AND p.paid_at < DATE_ADD(s.period_end, INTERVAL 1 DAY)');
      expect(pay.sql).toContain(`p.payment_type = 'booking' AND p.funds_recipient = 'hospital' AND p.provider NOT IN (?) AND p.status IN (?)`);
      expect(pay.values).toEqual([12, PAYMENT_TEST_PROVIDERS, PAYMENT_PAID_STATUSES]);
      const ref = roCalls.find((c) => c.sql.includes('FROM gangnamunni_payment_events e'))!;
      expect(ref.sql).toContain(`e.event_type = 'refund_verified' AND p.provider NOT IN (?)`);
      expect(ref.sql).toContain('e.received_at >= s.period_start AND e.received_at < DATE_ADD(s.period_end, INTERVAL 1 DAY)');
      expect(ref.values).toEqual([12, PAYMENT_TEST_PROVIDERS]);
    });

    it('a refund after the last period_end (of an old payment) blocks, even when every settlement is paid', async () => {
      unsettledRefunds = 1;
      const r = await get('/v1/hospitals/12/delete-check', SUPER);
      expect(r.body.canDelete).toBe(false);
      expect(r.body.blockers).toEqual([{ code: 'unsettled', settlements: 0, unsettledPayments: 1 }]);
    });

    it('historical payments (before the first settlement / in a month with no settlement / no settlement at all) block; refunds add to the count', async () => {
      lastSettlement = null;
      unsettledPayments = 3;
      unsettledRefunds = 2;
      const r = await get('/v1/hospitals/12/delete-check', SUPER);
      expect(r.body.blockers).toEqual([{ code: 'unsettled', settlements: 0, unsettledPayments: 5 }]);
      expect(r.body.deletableAfter).toBeNull();
    });

    it('delete is refused (409 blocked, nothing written) for a refund that no settlement covers, also inside the transaction round', async () => {
      unsettledRefunds = 1;
      const r = await del();
      expect(r.status).toBe(409);
      expect(r.body.blockers).toEqual([{ code: 'unsettled', settlements: 0, unsettledPayments: 1 }]);
      expect(rwCalls).toHaveLength(0);
      unsettledRefunds = 0;
      txHook = (sql) => { if (sql.includes('payment_events')) unsettledRefunds = 1; };
      const r2 = await del();
      expect(r2.status).toBe(409);
      expect(txEvents).toContain('ROLLBACK');
      expect(rwWrites()).toHaveLength(0);
    });

    it('a pending hospital and an unknown id -> 404', async () => {
      expect((await get('/v1/hospitals/13/delete-check', SUPER)).status).toBe(404);
      expect((await get('/v1/hospitals/99/delete-check', SUPER)).status).toBe(404);
      expect((await get('/v1/hospitals/0/delete-check', SUPER)).status).toBe(404);
    });

    it('buildBlockers: a paid newest settlement with a negative balance still blocks; a zero balance does not', () => {
      const base = { bookings: { cnt: 0 }, consultations: { cnt: 0 }, settlements: { open_cnt: 0 }, payments: { cnt: 0 } };
      expect(buildBlockers({ ...base, last: { status: 'paid', balance: -5 } }).blockers).toEqual([{ code: 'unsettled', settlements: 1, unsettledPayments: 0 }]);
      expect(buildBlockers({ ...base, last: { status: 'paid', balance: 0 } }).blockers).toEqual([]);
    });
  });

  describe('delete', () => {
    it('success: one transaction in the contract order, archive id returned', async () => {
      const r = await del();
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ archiveId: 12 });
      expect(txEvents[0]).toBe('BEGIN');
      expect(txEvents.at(-2)).toBe('COMMIT');
      expect(txEvents.at(-1)).toBe('RELEASE');
      const writes = rwWrites().map((c) => c.sql.replace(/\s+/g, ' '));
      expect(writes.map((w) => w.split(' (')[0].split(' WHERE')[0])).toEqual([
        'INSERT INTO gangnamunni_hospital_deletions',
        'DELETE dt FROM gangnamunni_doctor_treatments dt JOIN gangnamunni_doctors d ON d.id = dt.doctor_id',
        'DELETE FROM gangnamunni_doctors',
        'DELETE FROM gangnamunni_hospital_treatments',
        'DELETE FROM hospital_images',
        'DELETE FROM gangnamunni_hospital_revisions',
        'DELETE FROM gangnamunni_hospitals',
      ]);
      expect(writes.at(-1)).toContain('updated_at <=> ?');
      expect(rwCalls.at(-1)?.values).toEqual([12, T0]);
      // the row is locked before it is copied
      expect(rwCalls.some((c) => c.sql.includes('FOR UPDATE'))).toBe(true);
    });

    it('never writes bookings, payments, settlements or consultations', async () => {
      await del();
      for (const c of rwWrites()) expect(c.sql).not.toMatch(/gangnamunni_bookings|platform_payments|hospital_settlements|gangnamunni_consultations|review/);
    });

    it('archive record: sealed totals, name, admin; snapshot has no secrets', async () => {
      await del();
      const ins = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_hospital_deletions'))!;
      const [hid, name, snapshot, total, cancelled, paid, refunded, net, adminId, adminName] = ins.values as [number, string, string, number, number, number, number, number, number, string];
      expect([hid, name, total, cancelled, paid, refunded, net, adminId, adminName]).toEqual([12, '서울병원', 40, 6, 300000, 50000, 250000, 7, '김관리']);
      const snap = JSON.parse(snapshot);
      expect(snap.doctors).toEqual([{ id: 1, name: '의사', position: '원장' }]);
      expect(snap.images).toEqual([{ id: 9, type: 'main', url: '/img/9.jpg' }]);
      expect(snap.treatmentsCount).toBe(4);
      expect(snap.hospital.name).toBe('서울병원');
      for (const secret of ['ai_own_api_key', 'sk-secret', 'cert_business', 'cert_medical', '/files/cert.pdf', '/files/med.pdf']) expect(snapshot).not.toContain(secret);
    });

    it('audit log has ids only, no names or values', async () => {
      const log = jest.spyOn(Logger.prototype, 'log');
      await del();
      expect(log).toHaveBeenCalledWith('hospital delete: admin=7 hospital=12 archive=12');
    });

    it('buildSnapshot copies only whitelisted columns', () => {
      const s = buildSnapshot({ id: 1, name: 'n', ai_own_api_key: 'k', cert_business: 'p', random_secret: 's' }, [], [], 0);
      expect(Object.keys(s.hospital).sort()).toEqual(['id', 'name']);
      expect(SNAPSHOT_HOSPITAL_COLUMNS.some((c) => /key|cert|token|password/i.test(c))).toBe(false);
    });

    it('confirmName mismatch (also trailing space / empty) -> 400, nothing written', async () => {
      for (const confirmName of ['서울병원 ', '다른병원', '']) {
        const r = await del(12, { confirmName, expectedUpdatedAt: T0 });
        expect(r.status).toBe(400);
        expect(r.body).toEqual({ error: 'invalid', fields: { confirmName: 'mismatch' } });
      }
      expect(rwCalls).toHaveLength(0);
    });

    it('confirmName is compared in NFC: decomposed Hangul matches a composed stored name and the other way round', async () => {
      const nfd = '서울병원'.normalize('NFD');
      expect(nfd).not.toBe('서울병원');
      expect((await del(12, { confirmName: nfd, expectedUpdatedAt: T0 })).status).toBe(200);
      hospitals[12] = { name: nfd, status: 'approved', updated_at: T0 };
      expect((await del(12, { confirmName: '서울병원', expectedUpdatedAt: T0 })).status).toBe(200);
      expect(nameMatches('서울병원'.normalize('NFD'), '서울병원')).toBe(true);
      expect(nameMatches('서울병원', '서울병원 ')).toBe(false);
    });

    it('bad body -> 400 with field codes', async () => {
      const r = await del(12, { confirmName: 5 });
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'invalid', fields: { confirmName: 'bad_format', expectedUpdatedAt: 'required' } });
      expect((await del(12, { confirmName: '서울병원', expectedUpdatedAt: 'yesterday' })).body.fields).toEqual({ expectedUpdatedAt: 'bad_format' });
      expect(rwCalls).toHaveLength(0);
    });

    it('blockers -> 409 blocked with blockers and deletableAfter; nothing written', async () => {
      openBookings = { cnt: 2, last_at: '2026-12-01 10:00:00' };
      const r = await del();
      expect(r.status).toBe(409);
      expect(r.body).toEqual({ error: 'blocked', blockers: [{ code: 'upcoming_bookings', count: 2, lastAt: '2026-12-01 10:00:00' }], deletableAfter: '2026-12-01 10:00:00' });
      expect(rwCalls).toHaveLength(0);
    });

    it('a booking that appears after the first check is caught inside the transaction -> blocked, ROLLBACK, nothing written', async () => {
      txHook = (sql) => { if (sql.includes('MAX(TIMESTAMP')) openBookings = { cnt: 1, last_at: '2026-12-02 09:00:00' }; };
      const r = await del();
      expect(r.status).toBe(409);
      expect(r.body.error).toBe('blocked');
      expect(txEvents).toContain('ROLLBACK');
      expect(txEvents).not.toContain('COMMIT');
      expect(rwWrites()).toHaveLength(0);
    });

    it('expectedUpdatedAt differs -> 409 conflict with the current value; nothing written', async () => {
      const r = await del(12, { confirmName: '서울병원', expectedUpdatedAt: '2026-10-01 00:00:00' });
      expect(r.status).toBe(409);
      expect(r.body).toEqual({ error: 'conflict', updatedAt: T0 });
      expect(rwCalls).toHaveLength(0);
    });

    it('the row changed between the check and the lock -> 409 conflict, ROLLBACK, nothing written', async () => {
      txHook = (sql) => { if (sql.includes('FOR UPDATE')) hospitals[12].updated_at = '2026-11-17 08:00:00'; };
      const r = await del();
      expect(r.status).toBe(409);
      expect(r.body).toEqual({ error: 'conflict', updatedAt: '2026-11-17 08:00:00' });
      expect(txEvents).toContain('ROLLBACK');
      expect(rwWrites()).toHaveLength(0);
    });

    it('expectedUpdatedAt null matches a hospital whose updated_at is NULL', async () => {
      hospitals[12].updated_at = null;
      expect((await del(12, { confirmName: '서울병원', expectedUpdatedAt: null })).status).toBe(200);
    });

    it('the final DELETE touches 0 rows (edited in between) -> 409 conflict and ROLLBACK, no COMMIT', async () => {
      deleteAffected = 0;
      const r = await del();
      expect(r.status).toBe(409);
      expect(r.body.error).toBe('conflict');
      expect(txEvents).toContain('ROLLBACK');
      expect(txEvents).not.toContain('COMMIT');
      expect(txEvents.at(-1)).toBe('RELEASE');
    });

    it.each([
      ['archive insert', 'INSERT INTO gangnamunni_hospital_deletions'],
      ['doctors delete', 'DELETE FROM gangnamunni_doctors'],
      ['images delete', 'DELETE FROM hospital_images'],
      ['hospital delete', 'DELETE FROM gangnamunni_hospitals'],
    ])('a database error at the %s rolls everything back -> 500 server_error', async (_n, marker) => {
      failOn = marker;
      const r = await del();
      expect(r.status).toBe(500);
      expect(r.body).toEqual({ error: 'server_error' });
      expect(txEvents).toContain('ROLLBACK');
      expect(txEvents).not.toContain('COMMIT');
      expect(txEvents.at(-1)).toBe('RELEASE');
    });

    it('a missing archive table inside the transaction -> 503 write_unavailable and ROLLBACK', async () => {
      txHook = (sql) => { if (sql.startsWith('INSERT INTO gangnamunni_hospital_deletions')) throw Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 }); };
      const r = await del();
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'write_unavailable' });
      expect(txEvents).toContain('ROLLBACK');
      expect(txEvents).not.toContain('COMMIT');
    });

    it('unknown hospital / bad id -> 404', async () => {
      expect((await del(99)).status).toBe(404);
      expect((await post('/v1/hospitals/abc/delete', goodBody, SUPER)).status).toBe(404);
    });

    it.each(['pending', 'rejected'])('a %s application can not be deleted or checked here -> 404, nothing written', async (status) => {
      hospitals[13].status = status;
      const r = await del(13, { confirmName: '대기병원', expectedUpdatedAt: null });
      expect(r.status).toBe(404);
      expect(r.body).toEqual({ error: 'not_found' });
      expect((await get('/v1/hospitals/13/delete-check', SUPER)).status).toBe(404);
      expect(rwWrites()).toHaveLength(0);
    });
  });

  describe('archive', () => {
    const row = (over: Record<string, unknown> = {}) => ({
      id: 12, hospital_id: 12, hospital_name: '서울병원',
      snapshot: JSON.stringify({ version: 1, hospital: { name: '서울병원' }, doctors: [{ id: 1, name: '의사', position: '원장' }], images: [{ id: 9, type: 'main', url: '/i.jpg' }], treatmentsCount: 4 }),
      bookings_total: 40, bookings_cancelled: 6, sales_paid: 300000, sales_refunded: 50000, sales_net: 250000, deleted_by_name: '김관리',
      deleted_at: '2026-10-01 10:00:00', notice_acked_at: null, notice_due: 1, ...over,
    });

    it('list: items, total and dueCount; read only', async () => {
      archiveRows = [row()];
      const r = await get('/v1/hospital-archive?perPage=10', SUPER);
      expect(r.status).toBe(200);
      expect(r.body).toEqual({
        items: [{ id: 12, hospitalId: 12, name: '서울병원', deletedAt: '2026-10-01 10:00:00', deletedByName: '김관리', bookingsTotal: 40, bookingsCancelled: 6, salesNet: 250000, noticeDue: true, noticeAckedAt: null }],
        total: 1, dueCount: 1,
      });
      expect(roCalls.every((c) => c.sql.startsWith('SELECT'))).toBe(true);
      const q = roCalls.find((c) => c.sql.includes('ORDER BY deleted_at DESC'))!;
      expect(q.values).toEqual([10, 0]);
      expect(q.sql).toContain('deleted_at + INTERVAL 1 MONTH <= NOW()');
    });

    it('list: perPage outside 10/20/50 falls back to 20; page offsets', async () => {
      await get('/v1/hospital-archive?perPage=7&page=3', SUPER);
      expect(roCalls.find((c) => c.sql.includes('ORDER BY deleted_at DESC'))!.values).toEqual([20, 40]);
    });

    it('detail adds salesPaid, salesRefunded and the structured snapshot; unknown id 404', async () => {
      archiveRows = [row()];
      const r = await get('/v1/hospital-archive/12', SUPER);
      expect(r.status).toBe(200);
      expect(r.body).toMatchObject({ id: 12, salesPaid: 300000, salesRefunded: 50000, salesNet: 250000, noticeDue: true });
      expect(r.body.snapshot).toEqual({ hospital: { name: '서울병원' }, doctors: [{ id: 1, name: '의사', position: '원장' }], images: [{ id: 9, type: 'main', url: '/i.jpg' }], imagesCount: 1, treatmentsCount: 4 });
      expect((await get('/v1/hospital-archive/99', SUPER)).status).toBe(404);
    });

    it('a broken snapshot text never fails the page', async () => {
      archiveRows = [row({ snapshot: '{not json' })];
      const r = await get('/v1/hospital-archive/12', SUPER);
      expect(r.status).toBe(200);
      expect(r.body.snapshot).toEqual({ hospital: {}, doctors: [], images: [], imagesCount: 0, treatmentsCount: 0 });
    });

    it('missing table -> 503 archive_unavailable on both read routes', async () => {
      archiveMissing = true;
      for (const p of ['/v1/hospital-archive', '/v1/hospital-archive/1']) {
        const r = await get(p, SUPER);
        expect(r.status).toBe(503);
        expect(r.body).toEqual({ error: 'archive_unavailable' });
      }
    });

    it('ack: 200 {ok:true}, writes only the two ack columns, also when already acknowledged; 404 for an unknown record', async () => {
      archiveRows = [row({ notice_acked_at: '2026-11-02 10:00:00' })];
      const r = await post('/v1/hospital-archive/12/ack', {}, SUPER);
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ ok: true });
      const upd = rwCalls.filter((c) => c.sql.startsWith('UPDATE'));
      expect(upd).toHaveLength(1);
      expect(upd[0].sql).toContain('SET notice_acked_at = NOW(), notice_acked_by = ?');
      expect(upd[0].values).toEqual(['김관리', 12]);
      expect((await post('/v1/hospital-archive/99/ack', {}, SUPER)).status).toBe(404);
    });
  });
});
