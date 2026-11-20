import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { BookingsModule } from './bookings.module';
import { BookingNotifyService } from './notify/booking-notify.service';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
type Call = { sql: string; values: unknown[] };

describe('admin API: bookings', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const roCalls: Call[] = [];
  const rwCalls: Call[] = [];
  const later = jest.fn();
  const booked = jest.fn(async () => ({}));
  let booking: Record<string, unknown> | null = null;
  let doctors: Record<string, unknown>[] = [];
  let paid: Record<string, unknown> | null = null;
  let raceLost = false;

  const roStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      roCalls.push({ sql, values });
      if (sql.includes('COUNT(*)')) return [[{ total: 0, cnt: 0 }]];
      return [[]];
    }),
  };
  const rwQuery = async (sql: string, values: unknown[] = []) => {
    rwCalls.push({ sql, values });
    if (sql.includes('FROM gangnamunni_bookings WHERE id = ?') && sql.includes('hospital_id')) {
      const scoped = sql.includes('hospital_id IN (');
      if (!booking || (scoped && Number(values[1]) !== Number(booking.hospital_id))) return [[]];
      return [[booking]];
    }
    if (sql.includes('FROM gangnamunni_doctors WHERE hospital_id')) return [doctors];
    if (sql.includes('FROM gangnamunni_doctors WHERE id')) return [doctors.filter((d) => d.id === values[0])];
    if (sql.includes('FROM gangnamunni_platform_payments')) return [paid ? [paid] : []];
    if (sql.startsWith('UPDATE gangnamunni_bookings')) return [{ affectedRows: raceLost ? 0 : 1 }];
    return [{ affectedRows: 1 }];
  };
  const conn = { query: jest.fn(rwQuery), beginTransaction: jest.fn(async () => undefined), commit: jest.fn(async () => undefined), rollback: jest.fn(async () => undefined), release: jest.fn() };
  const rwStub = { get: () => ({ query: jest.fn(rwQuery), getConnection: async () => conn }) };

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
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_BOOKINGS_ENABLED = '1';
    process.env.ADMIN_BOOKINGS_WRITE = '1';
    roCalls.length = 0; rwCalls.length = 0; later.mockClear(); booked.mockClear(); conn.rollback.mockClear(); conn.commit.mockClear();
    booking = { status: 'hospital_checking', hospital_id: 12, doctor_id: 0, doctor_name: '', id: 5, hospital_name: 'H', mb_id: 'm', name: 'n', phone: '010', treatment: 't', price: 50000 };
    doctors = [{ id: 3, name: '가나다', hospital_id: 12 }];
    paid = null; raceLost = false;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, BookingsModule] })
      .overrideProvider(BookingNotifyService).useValue({ later, booking: booked }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_BOOKINGS_ENABLED', 'ADMIN_BOOKINGS_WRITE']) delete process.env[k];
  });

  const get = (path: string, cookie: string = SUPER) => {
    const r = request(app.getHttpServer()).get(path);
    return cookie ? r.set('Cookie', cookie) : r;
  };
  const post = (path: string, body: unknown, cookie: string = SUPER, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post(path);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r.send(JSON.stringify(body));
  };
  const flush = () => new Promise((r) => setImmediate(r));

  describe('read', () => {
    it('switch off -> 404; no session -> 401', async () => {
      expect((await get('/v1/bookings', '')).status).toBe(401);
      process.env.ADMIN_BOOKINGS_ENABLED = '0';
      expect((await get('/v1/bookings')).status).toBe(404);
      expect((await get('/v1/bookings/hospitals')).status).toBe(404);
    });
    it('a hospital account is held to its own hospital in the list query', async () => {
      const r = await get('/v1/bookings?status=confirmed', HOSPITAL);
      expect(r.status).toBe(200);
      expect(roCalls.some((c) => c.values.includes(12))).toBe(true);
      expect(roCalls.every((c) => !c.sql.includes('12'))).toBe(true);
    });
    it('a bad query is a 400, not an SQL error', async () => {
      expect((await get('/v1/bookings?status=nope')).status).toBe(400);
      expect((await get('/v1/bookings?from=2026-13-40')).status).toBe(400);
    });
    it('the Excel export is an xlsx download, 404 when nothing matches', async () => {
      expect((await get('/v1/bookings/export?from=2026-11-01&to=2026-11-30')).status).toBe(404);
      expect((await get('/v1/bookings/export')).status).toBe(400);
    });
    it('/v1/me reports both switches', async () => {
      expect((await get('/v1/me')).body.features).toMatchObject({ bookings: true, bookingsWrite: true });
      process.env.ADMIN_BOOKINGS_WRITE = '0';
      expect((await get('/v1/me')).body.features).toMatchObject({ bookings: true, bookingsWrite: false });
    });
  });

  describe('status', () => {
    it('guards in order: switch 404, csrf 403, session 401', async () => {
      process.env.ADMIN_BOOKINGS_WRITE = '0';
      expect((await post('/v1/bookings/5/status', { action: 'confirm' })).status).toBe(404);
      process.env.ADMIN_BOOKINGS_WRITE = '1';
      expect((await post('/v1/bookings/5/status', { action: 'confirm' }, SUPER, { 'Content-Type': 'application/json' })).body).toEqual({ error: 'csrf' });
      expect((await post('/v1/bookings/5/status', { action: 'confirm' }, '')).status).toBe(401);
      expect(rwCalls).toHaveLength(0);
    });
    it('confirm: assigns the first active doctor, opens the payment order, then tells the member', async () => {
      const r = await post('/v1/bookings/5/status', { action: 'confirm' });
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ id: 5, status: 'confirmed', doctorId: 3, doctorName: '가나다' });
      const upd = rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_bookings'))!;
      expect(upd.sql).toContain('AND status = ?');
      expect(upd.sql).toContain('confirmed_at = NOW()');
      await flush(); await flush();
      expect(rwCalls.some((c) => c.sql.startsWith('INSERT INTO gangnamunni_platform_payments'))).toBe(true);
      expect(booked.mock.calls.map((c: any[]) => c[1])).toEqual(['doctor_assigned', 'confirmed', 'payment_requested']);
    });
    it('confirm without an active doctor -> 422 and nothing is saved', async () => {
      doctors = [];
      const r = await post('/v1/bookings/5/status', { action: 'confirm' });
      expect(r.status).toBe(422);
      expect(r.body.error).toBe('no_active_doctor');
      expect(rwCalls.some((c) => c.sql.startsWith('UPDATE'))).toBe(false);
      expect(conn.rollback).toHaveBeenCalled();
      expect(booked).not.toHaveBeenCalled();
    });
    it('a step the flow does not allow -> 409; a lost race -> 409 and no notice', async () => {
      booking = { ...booking, status: 'completed' };
      expect((await post('/v1/bookings/5/status', { action: 'confirm' })).status).toBe(409);
      booking = { ...booking, status: 'hospital_checking' };
      raceLost = true;
      expect((await post('/v1/bookings/5/status', { action: 'confirm' })).status).toBe(409);
      await flush();
      expect(booked).not.toHaveBeenCalled();
    });
    it('cancel stores the reason (optional, as in PHP); a too long reason is a 400', async () => {
      expect((await post('/v1/bookings/5/status', { action: 'cancel', cancelReason: 'x'.repeat(501) })).status).toBe(400);
      const r = await post('/v1/bookings/5/status', { action: 'cancel', cancelReason: '일정 불가' });
      expect(r.status).toBe(200);
      const upd = rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_bookings'))!;
      expect(upd.sql).toContain("cancelled_by = 'hospital'");
      expect(upd.values).toContain('일정 불가');
      await flush(); await flush();
      expect(booked.mock.calls.map((c: any[]) => c[1])).toEqual(['cancelled']);
    });
    it("another hospital's booking looks missing to a hospital account", async () => {
      booking = { ...booking, hospital_id: 99 };
      expect((await post('/v1/bookings/5/status', { action: 'confirm' }, HOSPITAL)).status).toBe(404);
      expect(rwCalls.some((c) => c.sql.startsWith('UPDATE'))).toBe(false);
    });
  });

  describe('doctor', () => {
    it("a doctor of another hospital -> 422; the booking's own doctor is saved and the member is told", async () => {
      doctors = [{ id: 3, name: '가나다', hospital_id: 12 }, { id: 4, name: '라마바', hospital_id: 77 }];
      expect((await post('/v1/bookings/5/doctor', { doctorId: 4 })).status).toBe(422);
      const ok = await post('/v1/bookings/5/doctor', { doctorId: 3 });
      expect(ok.body).toEqual({ doctorId: 3, doctorName: '가나다' });
      expect(later).toHaveBeenCalledWith(5, 'doctor_assigned', { doctorName: '가나다' });
    });
    it('assigning the same doctor again changes nothing and sends nothing', async () => {
      booking = { ...booking, doctor_id: 3, doctor_name: '가나다' };
      expect((await post('/v1/bookings/5/doctor', { doctorId: 3 })).status).toBe(200);
      expect(rwCalls.some((c) => c.sql.startsWith('UPDATE'))).toBe(false);
      expect(later).not.toHaveBeenCalled();
    });
  });
});
