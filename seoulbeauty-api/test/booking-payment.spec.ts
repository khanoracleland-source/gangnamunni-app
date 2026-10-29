import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { BookingsModule } from '../src/bookings/bookings.module';
import { BookingPaymentService } from '../src/bookings/booking-payment.service';

describe('booking payment API (preview only; Toss stays in PHP)', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[];
  let st: any;
  const realFetch = global.fetch;
  const base = () => ({
    booking: { id: 7, hospital_id: 3, mb_id: 'kim', phone: '010-1234-5678', status: 'confirmed', booking_date: '2099-01-05', booking_time: '14:00:00', treatment: '보톡스 · 50U', hospital_name: 'A의원', h_name: 'A의원', hospital_address: '서울 강남구 테헤란로 1', profile_image_url: 'l.png', name: '김민', price: 50000, hms_appointment_id: null },
    order: null as any, previews: [] as number[], settings: {} as Record<string, string>, tables: true, insertFails: false,
  });
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/SHOW TABLES/.test(sql)) return [st.tables ? [{ t: 1 }] : []];
    if (/FROM gangnamunni_bookings b LEFT JOIN/.test(sql)) return [args[0] === 7 ? [st.booking] : []];
    if (/FROM gangnamunni_bookings WHERE id = \? FOR UPDATE/.test(sql)) return [[st.booking]];
    if (/FROM gangnamunni_platform_payments/.test(sql)) return [st.order ? [st.order] : []];
    if (/INSERT INTO gangnamunni_platform_payments/.test(sql)) { if (st.insertFails) throw new Error('denied'); st.order = { id: 31, booking_id: 7, merchant_uid: args[8], status: 'pending', amount: args[7], provider: null }; return [{ insertId: 31 }]; }
    if (/FROM gangnamunni_booking_payment_previews/.test(sql)) return [st.previews.includes(args[0]) ? [{ booking_id: args[0] }] : []];
    if (/INSERT INTO gangnamunni_booking_payment_previews/.test(sql)) { st.previews.push(args[0]); return [{ affectedRows: 1 }]; }
    if (/FROM gangnamunni_point_settings/.test(sql)) return [Object.entries(st.settings).map(([skey, sval]) => ({ skey, sval }))];
    return [{ affectedRows: 1 }];
  };
  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const who = (init?.headers?.cookie || '').includes('sid=kim');
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { id: 'kim', no: 42, name: '김민', nickname: 'n', level: 2, phone: '' } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    const conn = { query: async (sql: string, args: any[] = []) => respond(sql, args), release: () => undefined };
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args), getConnection: async () => conn } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, BookingsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => { calls = []; st = base(); delete process.env.BOOKING_PAYMENT_PREVIEW_ENABLED; delete process.env.PG_PAYMENTS_ENABLED; });
  const api = () => request(app.getHttpServer());
  const authed = (r: request.Test) => r.set('Cookie', 'sid=kim').set('X-Requested-With', 'XMLHttpRequest');

  it('settings: live needs the switch, methods and both Toss keys', () => {
    expect(BookingPaymentService.liveAvailable()).toBe(false);
    process.env.PG_PAYMENTS_ENABLED = '1'; process.env.BOOKING_PAYMENT_METHODS = 'card, bogus'; process.env.TOSS_CLIENT_KEY = 'c'; process.env.TOSS_SECRET_KEY = 's';
    expect(BookingPaymentService.liveAvailable()).toBe(true);
    process.env.BOOKING_PAYMENT_METHODS = 'bogus';
    expect(BookingPaymentService.liveAvailable()).toBe(false);
    delete process.env.BOOKING_PAYMENT_METHODS; delete process.env.TOSS_CLIENT_KEY; delete process.env.TOSS_SECRET_KEY;
    expect(BookingPaymentService.previewEnabled()).toBe(true);
    process.env.BOOKING_PAYMENT_PREVIEW_ENABLED = '0';
    expect(BookingPaymentService.previewEnabled()).toBe(false);
  });

  it('info: a confirmed booking without an order gets one (server price), no PG, preview offered', async () => {
    const r = await api().get('/v1/mypage/bookings/7/payment').set('Cookie', 'sid=kim').expect(200);
    expect(r.body.booking).toMatchObject({ id: 7, statusText: '결제 대기', hospitalName: 'A의원', hospitalArea: '서울 강남구', hospitalAddress: '서울 강남구 테헤란로 1', treatment: '보톡스 · 50U', bookerName: '김민', phone: '010-1234-5678', dateText: '1월 5일 (월) 오후 2:00', logo: 'l.png' });
    expect(r.body.order).toEqual({ status: 'pending', provider: '', amount: 50000, gross: 50000, pointsUsed: 0, isTest: false });
    expect(r.body).toMatchObject({ isConfirmed: true, isPaid: false, preview: false, liveAvailable: false, previewAvailable: true, orderError: '', points: { v2: false, expected: 0 } });
    const ins = calls.find((c) => /INSERT INTO gangnamunni_platform_payments/.test(c.sql))!;
    expect(ins.args.slice(0, 8)).toEqual([7, 3, 'A의원', 'kim', '김민', '010-1234-5678', '보톡스 · 50U', 50000]);
    expect(ins.args[8]).toMatch(/^BK_\d{14}_7_[0-9a-f]{12}$/);
    expect(calls.filter((c) => /COMMIT/.test(c.sql))).toHaveLength(1);
  });
  it('info: not confirmed -> no order is created; unknown/other -> 404; anonymous -> 401', async () => {
    st.booking.status = 'pending';
    const r = await api().get('/v1/mypage/bookings/7/payment').set('Cookie', 'sid=kim').expect(200);
    expect(r.body.order).toBeNull();
    expect(r.body.gross).toBe(50000); // booking_payment.php: the booking price before an order exists
    expect(r.body.isConfirmed).toBe(false);
    expect(calls.some((c) => /INSERT INTO gangnamunni_platform_payments/.test(c.sql))).toBe(false);
    st.booking.mb_id = 'other';
    await api().get('/v1/mypage/bookings/7/payment').set('Cookie', 'sid=kim').expect(404);
    await api().get('/v1/mypage/bookings/7/payment').expect(401);
  });
  it('info: paid order and gross/points, preview flag, expected points when v2 is on', async () => {
    st.order = { id: 1, status: 'paid', provider: 'toss_test', amount: 45000, gross_amount: 50000, points_used: 5000, merchant_uid: 'm' };
    st.previews = [7];
    st.settings = { enabled: '1', v2_schema_version: '1', v2_policy_enabled: '1', schema_version: '3' };
    const r = await api().get('/v1/mypage/bookings/7/payment').set('Cookie', 'sid=kim').expect(200);
    expect(r.body.order).toEqual({ status: 'paid', provider: 'toss_test', amount: 45000, gross: 50000, pointsUsed: 5000, isTest: true });
    expect(r.body).toMatchObject({ isPaid: true, preview: true, points: { v2: true, expected: 5000 } });
  });
  it('info: an order create failure is reported, not thrown', async () => {
    st.insertFails = true;
    const r = await api().get('/v1/mypage/bookings/7/payment').set('Cookie', 'sid=kim').expect(200);
    expect(r.body.orderError).toContain('결제 주문을 만들지 못했습니다');
    expect(calls.filter((c) => /ROLLBACK/.test(c.sql))).toHaveLength(1);
  });

  it('preview: creates the order, records the preview under a row lock, answers the success page', async () => {
    const r = await authed(api().post('/v1/mypage/bookings/7/payment/preview')).expect(201);
    expect(r.body).toEqual({ success: true, redirect: '/mypage/bookings/7/payment/success' });
    expect(st.previews).toEqual([7]);
    expect(calls.filter((c) => /FOR UPDATE/.test(c.sql))).toHaveLength(2);
  });
  it('preview: refusals', async () => {
    await api().post('/v1/mypage/bookings/7/payment/preview').set('Cookie', 'sid=kim').expect(403);
    st.booking.status = 'pending';
    expect((await authed(api().post('/v1/mypage/bookings/7/payment/preview')).expect(409)).body.code).toBe('BOOKING_NOT_CONFIRMED');
    st.booking.status = 'confirmed'; st.order = { id: 1, status: 'failed', merchant_uid: 'm', amount: 1 };
    expect((await authed(api().post('/v1/mypage/bookings/7/payment/preview')).expect(409)).body.code).toBe('INVALID_ORDER_STATE');
    st.order = { id: 1, status: 'paid', merchant_uid: 'm', amount: 1 };
    expect((await authed(api().post('/v1/mypage/bookings/7/payment/preview')).expect(201)).body.redirect).toContain('/payment/success');
    st.order = null; process.env.BOOKING_PAYMENT_PREVIEW_ENABLED = '0';
    expect((await authed(api().post('/v1/mypage/bookings/7/payment/preview')).expect(403)).body.code).toBe('PREVIEW_DISABLED');
    expect(st.previews).toEqual([]);
    st.booking.mb_id = 'x';
    await authed(api().post('/v1/mypage/bookings/7/payment/preview')).expect(404);
  });

  it('success: preview, paid, test-paid, refunded, unknown', async () => {
    const get = () => api().get('/v1/mypage/bookings/7/payment/success').set('Cookie', 'sid=kim').expect(200);
    expect((await get()).body).toMatchObject({ title: '결제 확인 중', paidFinal: false, preview: false, hospitalName: 'A의원', dateText: '99.01.05 (월) 오후 2:00', booker: '김민 / 01012345678', bookingNo: '7' });
    st.previews = [7];
    expect((await get()).body.title).toBe('결제 없이 계속했어요');
    st.order = { id: 1, status: 'paid', provider: 'toss', amount: 1 };
    expect((await get()).body).toMatchObject({ title: '예약이 확정되었어요!', paidFinal: true, isPg: true });
    st.order.provider = 'toss_test';
    expect((await get()).body.title).toBe('테스트 결제 완료');
    st.order = { id: 1, status: 'refunded', provider: 'toss', amount: 1 };
    expect((await get()).body.title).toBe('환불 완료');
    st.booking.treatment = 'x'.repeat(30);
    expect((await get()).body.treatment).toBe('x'.repeat(24) + '...');
  });
});
