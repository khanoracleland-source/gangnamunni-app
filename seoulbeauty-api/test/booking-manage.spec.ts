import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { BookingsModule } from '../src/bookings/bookings.module';
import { cancelDeadline, noshowCancelPreview } from '../src/booking/booking.logic';

describe('cancel logic', () => {
  it('deadline is 17:00 the day before', () => {
    expect(cancelDeadline('2026-11-05')).toBe('2026-11-04 17:00:00');
    expect(cancelDeadline('bad')).toBeNull();
  });
  it('no-show cancel preview: warns one before the limit, blocks at the limit', () => {
    const row = (ev: string) => ({ booking_date: ev, booking_time: '10:00', hospital_name: 'A', status: 'cancelled', ev });
    expect(noshowCancelPreview(3, 3, false, [row('2026-10-01')])).toBeNull();
    expect(noshowCancelPreview(3, 3, true, [])).toBeNull();
    expect(noshowCancelPreview(3, 3, true, [row('2026-10-01')], '2026-10-29')).toEqual({ limit: 3, months: 3, before: 1, after: 2, will_block: false, available_from: '' });
    expect(noshowCancelPreview(3, 3, true, [row('2026-10-20'), row('2026-10-01')], '2026-10-29')).toEqual({ limit: 3, months: 3, before: 2, after: 3, will_block: true, available_from: '1월 1日'.replace('日', '일') });
  });
});

describe('booking cancel / change API', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[];
  let st: any;
  const realFetch = global.fetch;
  const base = () => ({
    booking: { id: 7, mb_id: 'kim', phone: '010-1234-5678', status: 'pending', booking_date: '2099-01-05', booking_time: '10:00:00', treatment: '보톡스 · 50U', hospital_name: 'A의원', name: '김민', doctor_name: '', hms_appointment_id: null, edit_count: 0 },
    order: null as any, settings: {} as Record<string, string>, lockOk: 1, affected: 1, hold: null as any, cols: { cancelled_by: true, edit_count: true },
  });
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/SHOW COLUMNS/.test(sql)) return [st.cols[args[0]] ? [{ Field: args[0] }] : []];
    if (/SHOW TABLES/.test(sql)) return [[{ t: 1 }]];
    if (/FROM gangnamunni_bookings WHERE id = \? LIMIT 1/.test(sql)) return [args[0] === 7 ? [st.booking] : []];
    if (/SELECT status FROM gangnamunni_bookings/.test(sql)) return [[{ status: st.booking.status }]];
    if (/FROM gangnamunni_platform_payments/.test(sql)) return [st.order ? [st.order] : []];
    if (/FROM gangnamunni_point_settings/.test(sql)) return [Object.entries(st.settings).map(([skey, sval]) => ({ skey, sval }))];
    if (/FROM gangnamunni_point_payment_holds/.test(sql)) return [st.hold ? [st.hold] : []];
    if (/GET_LOCK/.test(sql)) return [[{ acquired: st.lockOk }]];
    if (/IF\(status = 'no_show'/.test(sql)) return [st.noshowRows ?? []];
    if (/FROM gangnamunni_settings/.test(sql)) return [[]];
    if (/UPDATE gangnamunni_bookings SET status = 'cancelled'|UPDATE gangnamunni_bookings SET status = IF/.test(sql)) return [{ affectedRows: st.affected }];
    if (/FROM gangnamunni_points WHERE/.test(sql)) return [[]];
    return [{ affectedRows: 1 }];
  };
  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const who = (init?.headers?.cookie || '').includes('sid=kim');
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { id: 'kim', no: 42, name: 'x', nickname: 'n', level: 2, phone: '' } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    const conn = { query: async (sql: string, args: any[] = []) => respond(sql, args), release: () => undefined };
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args), getConnection: async () => conn } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, BookingsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => { calls = []; st = base(); });
  const api = () => request(app.getHttpServer());
  const authed = (r: request.Test) => r.set('Cookie', 'sid=kim').set('X-Requested-With', 'XMLHttpRequest');
  const sqls = (re: RegExp) => calls.filter((c) => re.test(c.sql));

  it('cancel-info: own booking, no payment', async () => {
    const r = await api().get('/v1/mypage/bookings/7/cancel-info').set('Cookie', 'sid=kim').expect(200);
    expect(r.body).toMatchObject({ id: 7, dateText: '1월 5일 (월) 오전 10:00', treatment: '보톡스 · 50U', hospitalName: 'A의원', amount: 0, windowClosed: false, cancelWarn: null, paymentStatus: '' });
    expect(r.body.reasons).toHaveLength(5);
    await api().get('/v1/mypage/bookings/7/cancel-info').expect(401);
    await api().get('/v1/mypage/bookings/8/cancel-info').set('Cookie', 'sid=kim').expect(404);
  });
  it('cancel-info: paid PG order shows the amount; after the deadline the window is closed', async () => {
    st.order = { id: 1, provider: 'toss', status: 'paid', amount: 100000, refunded_amount: 20000, merchant_uid: 'm1' };
    expect((await api().get('/v1/mypage/bookings/7/cancel-info').set('Cookie', 'sid=kim').expect(200)).body).toMatchObject({ amount: 80000, refund: 80000, windowClosed: false, paymentStatus: 'paid' });
    st.booking.booking_date = '2020-01-05';
    expect((await api().get('/v1/mypage/bookings/7/cancel-info').set('Cookie', 'sid=kim').expect(200)).body.windowClosed).toBe(true);
  });
  it('cancel-info: no-show warning before the limit', async () => {
    st.noshowRows = [{ booking_date: '2026-10-01', booking_time: '10:00:00', hospital_name: 'A', status: 'cancelled', ev: '2026-10-01' }];
    expect((await api().get('/v1/mypage/bookings/7/cancel-info').set('Cookie', 'sid=kim').expect(200)).body.cancelWarn).toMatchObject({ before: 1, after: 2, will_block: false });
  });
  it('cancel-info: a cancelled booking is 409', async () => {
    st.booking.status = 'cancelled';
    await api().get('/v1/mypage/bookings/7/cancel-info').set('Cookie', 'sid=kim').expect(409);
  });

  it('cancel: updates, marks cancelled_by=member, no lock without an order', async () => {
    const r = await authed(api().post('/v1/mypage/bookings/7/cancel')).send({ reason: '일정 변경: 급한 일' }).expect(201);
    expect(r.body).toEqual({ success: true, message: '예약이 취소되었습니다.', refund_status: 'not_applicable' });
    const up = sqls(/SET status = 'cancelled'/)[0];
    expect(up.sql).toContain("cancelled_by = 'member'");
    expect(up.args).toEqual(['일정 변경: 급한 일', 7]);
    expect(sqls(/GET_LOCK/)).toHaveLength(0);
    expect(sqls(/COMMIT/)).toHaveLength(1);
    expect(sqls(/SET refund_status/)[0].args).toEqual(['not_applicable', 7]);
    expect(sqls(/UPDATE appointments/)).toHaveLength(0);
  });
  it('cancel: HMS appointment closed inside the transaction, paid order -> refund pending', async () => {
    st.booking.hms_appointment_id = 55;
    st.order = { id: 1, provider: 'toss', status: 'paid', amount: 1, refunded_amount: 0, merchant_uid: 'm1' };
    const r = await authed(api().post('/v1/mypage/bookings/7/cancel')).send({ reason: '기타' }).expect(201);
    expect(r.body.refund_status).toBe('not_refunded');
    expect(sqls(/GET_LOCK/)[0].args[0]).toMatch(/^payment:[0-9a-f]{48}$/);
    expect(sqls(/RELEASE_LOCK/)).toHaveLength(1);
    expect(sqls(/UPDATE appointments SET is_completed = 2/)[0].args).toEqual([55]);
  });
  it('cancel: lock busy -> 409, window closed -> 422, already cancelled -> 422, not mine -> 403', async () => {
    st.order = { id: 1, provider: 'toss', status: 'paid', amount: 1, refunded_amount: 0, merchant_uid: 'm1' };
    st.lockOk = 0;
    await authed(api().post('/v1/mypage/bookings/7/cancel')).send({ reason: 'x' }).expect(409);
    st.lockOk = 1; st.booking.booking_date = '2020-01-01';
    expect((await authed(api().post('/v1/mypage/bookings/7/cancel')).send({ reason: 'x' }).expect(422)).body.message).toContain('오후 5시');
    expect(sqls(/SET status = 'cancelled'/)).toHaveLength(0);
    st.booking.status = 'cancelled';
    expect((await authed(api().post('/v1/mypage/bookings/7/cancel')).send({}).expect(422)).body.message).toBe('이미 취소된 예약입니다.');
    st.booking.mb_id = 'other';
    await authed(api().post('/v1/mypage/bookings/7/cancel')).send({}).expect(403);
    await api().post('/v1/mypage/bookings/7/cancel').set('Cookie', 'sid=kim').send({}).expect(403);
  });
  it('cancel: a failed UPDATE rolls back and answers 500 without touching refund status', async () => {
    st.affected = 0;
    await authed(api().post('/v1/mypage/bookings/7/cancel')).send({ reason: 'x' }).expect(500);
    expect(sqls(/ROLLBACK/)).toHaveLength(1);
    expect(sqls(/SET refund_status/)).toHaveLength(0);
  });
  it('cancel: pending order with an empty point hold is released', async () => {
    st.settings = { v2_schema_version: '1' };
    st.order = { id: 9, provider: 'toss', status: 'pending', amount: 1, refunded_amount: 0, merchant_uid: 'm9' };
    st.hold = { id: 3, status: 'held', points: 0, use_point_id: null };
    await authed(api().post('/v1/mypage/bookings/7/cancel')).send({ reason: 'x' }).expect(201);
    expect(sqls(/SET status = 'released'/)[0].args).toEqual([3]);
    expect(sqls(/points_used = 0/)[0].args).toEqual([9]);
  });

  it('change-info: preselects the saved treatment and option; past date resets to today', async () => {
    const r = await api().get('/v1/mypage/bookings/7/change-info').set('Cookie', 'sid=kim').expect(200);
    expect(r.body).toEqual({ id: 7, hospitalId: 0, date: '2099-01-05', time: '10:00', treatment: '보톡스', optionName: '50U', phone: '010-1234-5678', editsLeft: 3, treatmentText: '보톡스 · 50U' });
    st.booking.booking_date = '2020-01-01';
    expect((await api().get('/v1/mypage/bookings/7/change-info').set('Cookie', 'sid=kim').expect(200)).body.time).toBe('');
    st.booking.edit_count = 3;
    await api().get('/v1/mypage/bookings/7/change-info').set('Cookie', 'sid=kim').expect(409);
  });
  it('change: validation', async () => {
    const post = (b: any) => authed(api().post('/v1/mypage/bookings/7/change')).send(b);
    expect((await post({ date: '2099-01-06' }).expect(422)).body.message).toBe('필수 항목을 입력해주세요.');
    expect((await post({ date: '2020-01-06', time: '10:00' }).expect(422)).body.message).toBe('유효한 미래 날짜를 선택해주세요.');
    expect((await post({ date: '2099-01-06', time: '10' }).expect(422)).body.message).toBe('유효한 시간을 선택해주세요.');
    st.cols.edit_count = false;
    await post({ date: '2099-01-06', time: '10:00' }).expect(503);
    st.cols.edit_count = true; st.booking.edit_count = 3;
    expect((await post({ date: '2099-01-06', time: '10:00' }).expect(422)).body.message).toContain('3회');
    st.booking.edit_count = 0; st.order = { id: 1, provider: 'toss', status: 'paid', merchant_uid: 'm1' };
    expect((await post({ date: '2099-01-06', time: '10:00' }).expect(422)).body.message).toContain('이미 결제된 예약');
  });
  it('change: date/time only, memo appended, edits left', async () => {
    const r = await authed(api().post('/v1/mypage/bookings/7/change')).send({ date: '2099-01-06', time: '11:00', treatment: '보톡스 · 50U' }).expect(201);
    expect(r.body).toEqual({ success: true, message: '예약이 변경되었습니다. (변경 가능 2회 남음)', new_date: '2099-01-06', new_time: '11:00', edits_left: 2 });
    const up = sqls(/SET status = IF/)[0];
    expect(up.sql).not.toContain('treatment = ?');
    expect(up.args).toEqual(['2099-01-06', '11:00', '변경: 2099-01-05 10:00:00 → 2099-01-06 11:00', 7]);
  });
  it('change: treatment change and HMS date; confirmed -> hospital_checking', async () => {
    st.booking.status = 'confirmed'; st.booking.hms_appointment_id = 55;
    await authed(api().post('/v1/mypage/bookings/7/change')).send({ date: '2099-01-06', time: '11:00', treatment: '필러' }).expect(201);
    const up = sqls(/SET status = IF/)[0];
    expect(up.sql).toContain('treatment = ?');
    expect(up.args).toEqual(['2099-01-06', '11:00', '필러', '변경: 2099-01-05 10:00:00 → 2099-01-06 11:00 / 시술: 보톡스 · 50U → 필러', 7]);
    expect(sqls(/UPDATE appointments SET opd_date/)[0].args).toEqual(['2099-01-06 11:00:00', 55]);
  });
});
