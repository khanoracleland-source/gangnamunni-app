import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { BookingsModule } from '../src/bookings/bookings.module';
import { bookingTab, bookingTimePassed, cancelSteps, cardDate, dateTimeLabel, dday, detailDate, paymentState, paymentTierPoint, requestSteps, reviewState } from '../src/bookings/bookings.logic';

const NOW = '2026-10-28 14:00:00';

describe('bookings logic', () => {
  it('dates', () => {
    expect(cardDate('2026-10-28', '14:05:00')).toBe('2026.10.28(수) 오후 2:05');
    expect(cardDate('2026-01-02', '00:30:00')).toBe('2026.1.2(금) 오전 12:30');
    expect(detailDate('2026-10-28', '12:00:00')).toBe('10월 28일 (수) 오후 12:00');
    expect(detailDate(null, null)).toBe('-');
    expect(dateTimeLabel('2026-10-28 09:07:00')).toBe('10월 28일 (수) 오전 9:07');
    expect(dday('2026-10-31', '2026-10-28')).toBe('D-3');
    expect(dday('2026-10-28', '2026-10-28')).toBe('D-0');
    expect(dday('2026-10-26', '2026-10-28')).toBe('D+2');
  });
  it('time passed and tabs', () => {
    expect(bookingTimePassed('2026-10-28', '13:59:00', NOW)).toBe(true);
    expect(bookingTimePassed('2026-10-28', '14:00:00', NOW)).toBe(true);
    expect(bookingTimePassed('2026-10-28', '14:01:00', NOW)).toBe(false);
    expect(bookingTab('confirmed', '2026-10-30', '10:00:00', NOW)).toBe('upcoming');
    expect(bookingTab('confirmed', '2026-10-20', '10:00:00', NOW)).toBe('completed'); // 완료 확인중
    expect(bookingTab('pending', '2026-10-20', '10:00:00', NOW)).toBe('completed');
    expect(bookingTab('completed', '2026-10-30', '10:00:00', NOW)).toBe('completed');
    expect(bookingTab('no_show', '2026-10-30', '10:00:00', NOW)).toBe('cancelled');
    expect(bookingTab('cancelled', '2026-10-30', '10:00:00', NOW)).toBe('cancelled');
  });
  it('payment state and steps', () => {
    expect(paymentState('pending', { status: 'pending', amount: 100 })).toBe('');
    expect(paymentState('confirmed', undefined)).toBe('');
    expect(paymentState('confirmed', { status: 'pending', amount: 100, provider: 'toss' })).toBe('payable');
    expect(paymentState('confirmed', { status: 'pending', amount: 0, provider: 'toss' })).toBe('');
    expect(paymentState('confirmed', { status: 'paid', amount: 100, provider: 'toss_test' })).toBe('test_paid');
    expect(paymentState('confirmed', { status: 'paid', amount: 100, provider: 'toss' })).toBe('paid');
    expect(paymentState('confirmed', { status: 'preview', amount: 100 })).toBe('preview');
    expect(requestSteps('pending', '')).toEqual({ steps: ['예약 요청 접수', '병원 확인 중', '결제 대기', '예약 확정'], active: 1, chip: '예약 요청 접수' });
    expect(requestSteps('hospital_checking', '').chip).toBe('병원 확인 중');
    expect(requestSteps('confirmed', 'payable')).toMatchObject({ active: 3, chip: '결제 대기' });
    expect(requestSteps('confirmed', 'paid')).toMatchObject({ active: 4, chip: '예약 확정' });
    expect(requestSteps('confirmed', 'preview').steps[3]).toBe('결제 없이 진행');
    expect(requestSteps('confirmed', 'test_paid').steps[3]).toBe('테스트 결제');
    expect(requestSteps('confirmed', 'refunded').steps[2]).toBe('환불 완료');
  });
  it('cancel steps', () => {
    expect(cancelSteps(undefined)).toMatchObject({ noCharge: true, refundComplete: true, active: 4, chip: '예약 취소 완료' });
    expect(cancelSteps({ status: 'paid', provider: 'toss' })).toMatchObject({ noCharge: false, refundComplete: false, active: 1, chip: '취소 접수' });
    expect(cancelSteps({ status: 'refunded', provider: 'toss' })).toMatchObject({ refundComplete: true, chip: '취소·환불 완료' });
    expect(cancelSteps({ status: 'paid', provider: 'toss_test' }).noCharge).toBe(true);
  });
  it('review state and tier points', () => {
    expect(reviewState(null).state).toBe('none');
    expect(reviewState({ review_status: 'published', is_active: 1 }).state).toBe('published');
    expect(reviewState({ review_status: 'published', is_active: 0 }).state).toBe('pending');
    expect(reviewState({ review_status: 'rejected', is_active: 0 })).toEqual({ state: 'rejected', label: '후기 반려됨' });
    const t = { t1Under: 50000, t1: 3000, t2Under: 100000, t2: 5000, t3: 10000 };
    expect([0, 49999, 50000, 99999, 100000].map((p) => paymentTierPoint(p, t))).toEqual([0, 3000, 5000, 5000, 10000]);
  });
});

describe('bookings API (mock DB)', () => {
  let app: INestApplication;
  const sql: { q: string; v?: unknown[] }[] = [];
  const realFetch = global.fetch;
  let seenAffected = 1;
  let pointsOn = false;
  const future = '2999-01-01';
  const B = (o: Record<string, unknown>): Record<string, any> => ({ hospital_id: 5, hospital_name: '가나병원', hospital_image: 'uploads/h5.jpg', treatment: '필러', price: 120000, booking_time: '14:00:00', mb_id: 'kim', phone: '010-1234-5678', name: '김', edit_count: 1, created_at: '2026-10-01 10:00:00', ...o });
  const rows = [
    B({ id: 1, status: 'pending', booking_date: future }),
    B({ id: 2, status: 'confirmed', booking_date: future }),
    B({ id: 3, status: 'confirmed', booking_date: future }),
    B({ id: 4, status: 'completed', booking_date: '2026-09-01' }),
    B({ id: 5, status: 'completed', booking_date: '2026-09-02' }),
    B({ id: 6, status: 'confirmed', booking_date: '2026-09-03' }),
    B({ id: 7, status: 'cancelled', booking_date: '2026-09-04', cancelled_by: 'system', cancel_reason: '결제 기한', cancelled_at: new Date(Date.now() + 9 * 3600_000 - 86400_000).toISOString().slice(0, 19).replace('T', ' '), expire_notice_seen_at: null }),
    B({ id: 8, status: 'no_show', booking_date: '2026-09-05' }),
    B({ id: 9, status: 'cancelled', booking_date: '2026-09-06', cancelled_by: 'system', cancel_reason: 'old', cancelled_at: '2020-01-01 00:00:00', expire_notice_seen_at: null }),
  ];
  const db = {
    query: jest.fn(async (q: string, v?: unknown[]) => {
      sql.push({ q, v });
      if (q.includes('FROM gangnamunni_bookings b LEFT JOIN') && q.includes('WHERE b.mb_id = ?')) return [rows];
      if (q.includes('FROM gangnamunni_bookings b LEFT JOIN') && q.includes('WHERE b.id = ?')) {
        const r = rows.find((x) => x.id === (v as number[])[0]);
        if ((v as number[])[0] === 20) return [[B({ id: 20, mb_id: 'other', status: 'confirmed', booking_date: future })]];
        if ((v as number[])[0] === 21) return [[B({ id: 21, mb_id: '', phone: '010-1234-5678', status: 'hospital_checking', booking_date: future })]];
        return [r ? [r] : []];
      }
      if (q.includes('SELECT booking_id, status, amount, provider FROM gangnamunni_platform_payments')) return [[
        { booking_id: 2, status: 'pending', amount: 120000, provider: 'toss' }, { booking_id: 3, status: 'paid', amount: 120000, provider: 'toss' }, { booking_id: 7, status: 'paid', amount: 120000, provider: 'toss' }]];
      if (q.includes('gangnamunni_booking_payment_previews')) return [[{ booking_id: 2 }]];
      if (q.includes('FROM hospital_reviews WHERE booking_id IN')) return [[{ booking_id: 4, id: 77, review_status: 'published', is_active: 1 }]];
      if (q.includes('FROM g5_push_tokens')) return [[{ c: 0 }]];
      if (q.includes('FROM gangnamunni_notification_settings')) return [[{ noti_booking: 1, noti_push: 1 }]];
      if (q.includes("setting_key = 'payment_deadline_hours'")) return [[{ setting_value: '48' }]];
      if (q.includes('SELECT status, provider, amount, refunded_amount')) {
        const id = (v as number[])[0];
        if (id === 7) return [[{ status: 'partially_refunded', provider: 'toss', amount: 120000, refunded_amount: 0 }]];
        return [[]];
      }
      if (q.includes('SELECT amount, points_used, gross_amount')) return [[{ amount: 100000, points_used: 20000, gross_amount: 0 }]];
      if (q.includes('FROM gangnamunni_point_settings')) return [pointsOn ? [{ skey: 'enabled', sval: '1' }, { skey: 'v2_schema_version', sval: '1' }, { skey: 'v2_policy_enabled', sval: '1' }] : []];
      if (q.includes('FROM hospital_reviews WHERE booking_id = ?')) return [[{ id: 77, review_status: 'rejected', is_active: 0 }]];
      if (q.includes('FROM gangnamunni_notification_sends')) return [[
        { created_at: '2026-10-20 14:02:00', notification_type: 'booking_confirmed', title: '예약 확정', message: '확정되었어요\n[push] ok', in_app_count: 1, push_sent: 1, push_failed: 0, push_skipped: 0, push_details: '{"event":"confirmed"}' }]];
      if (q.startsWith('UPDATE gangnamunni_bookings SET expire_notice_seen_at')) return [{ affectedRows: seenAffected }];
      return [[]];
    }),
  };

  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42, nickname: '김닉', phone: '010-1234-5678' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: '이름', level: 2, ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: db }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AuthModule, BookingsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => { sql.length = 0; seenAffected = 1; pointsOn = false; });
  const get = (p: string, auth = true) => { const r = request(app.getHttpServer()).get(p); return auth ? r.set('Cookie', 'sid=kim') : r; };

  it('needs a login and is never cached', async () => {
    await get('/v1/mypage/bookings', false).expect(401);
    await get('/v1/mypage/bookings/1', false).expect(401);
    expect((await get('/v1/mypage/bookings').expect(200)).headers['cache-control']).toBe('private, no-store');
  });

  it('list: own rows only (mb_id or own phone), three tabs', async () => {
    const r = (await get('/v1/mypage/bookings').expect(200)).body;
    const q = sql.find((x) => x.q.includes('WHERE b.mb_id = ?'))!;
    expect(q.v).toEqual(['kim', '01012345678', '01012345678']);
    expect(r.upcoming.map((x: any) => x.id)).toEqual([1, 2, 3]);
    expect(r.completed.map((x: any) => x.id)).toEqual([4, 5, 6]);
    expect(r.cancelled.map((x: any) => x.id)).toEqual([7, 8, 9]);
  });

  it('list: upcoming actions, steps and chip', async () => {
    const r = (await get('/v1/mypage/bookings').expect(200)).body;
    const [pending, payable, paid] = r.upcoming;
    expect(pending).toMatchObject({ action: 'change', editsLeft: 2, activeStep: 1, paid: false, treatment: '필러', price: 120000, imageUrl: 'uploads/h5.jpg' });
    expect(pending.chip).toMatch(/^예약 요청 접수 · D-\d+$/);
    expect(payable).toMatchObject({ action: 'pay', activeStep: 4 }); // preview => "결제 없이 진행"
    expect(payable.steps[3]).toBe('결제 없이 진행');
    expect(paid).toMatchObject({ action: null, activeStep: 4, paid: true });
  });

  it('list: completed card review states, 완료 확인중, cancelled tracks', async () => {
    const r = (await get('/v1/mypage/bookings').expect(200)).body;
    expect(r.completed[0]).toMatchObject({ isDone: true, chip: '시술완료', review: { state: 'published', reviewId: 77, label: '내 후기 보기' } });
    expect(r.completed[1]).toMatchObject({ isDone: true, review: { state: 'none', reviewId: null } });
    expect(r.completed[2]).toMatchObject({ isDone: false, chip: '완료 확인중', review: null });
    expect(r.cancelled[0]).toMatchObject({ refundComplete: false, chip: '취소 접수' }); // paid, not refunded
    expect(r.cancelled[1]).toMatchObject({ refundComplete: true, chip: '예약 취소 완료' });
  });

  it('list: auto-cancel notice only for a fresh unseen system cancel; push hint', async () => {
    const r = (await get('/v1/mypage/bookings').expect(200)).body;
    expect(r.expiredNotice).toEqual({ bookingId: 7, hospitalId: 5, treatment: '필러', deadlineHours: 48 });
    expect(r.showPushHint).toBe(true); // no token
  });

  it('expire-seen: needs the XHR header, updates only own system cancels', async () => {
    await request(app.getHttpServer()).post('/v1/mypage/bookings/7/expire-seen').set('Cookie', 'sid=kim').expect(403);
    await request(app.getHttpServer()).post('/v1/mypage/bookings/7/expire-seen').set('X-Requested-With', 'XMLHttpRequest').expect(401);
    const res = await request(app.getHttpServer()).post('/v1/mypage/bookings/7/expire-seen').set('Cookie', 'sid=kim').set('X-Requested-With', 'XMLHttpRequest').expect(201);
    expect(res.body).toEqual({ success: true });
    const u = sql.find((x) => x.q.startsWith('UPDATE gangnamunni_bookings'))!;
    expect(u.q).toContain("cancelled_by = 'system'");
    expect(u.q).toContain('expire_notice_seen_at IS NULL');
    expect(u.v).toEqual([7, 'kim']);
    seenAffected = 0;
    expect((await request(app.getHttpServer()).post('/v1/mypage/bookings/7/expire-seen').set('Cookie', 'sid=kim').set('X-Requested-With', 'XMLHttpRequest').expect(201)).body).toEqual({ success: false });
  });

  it('detail: someone else’s booking is a 404, an old row with my phone is mine', async () => {
    await get('/v1/mypage/bookings/20').expect(404);
    await get('/v1/mypage/bookings/999').expect(404);
    await get('/v1/mypage/bookings/abc').expect(400);
    expect((await get('/v1/mypage/bookings/21').expect(200)).body.kind).toBe('request');
  });

  it('detail: confirmed request shows the payment summary; expected points only with the v2 policy', async () => {
    let r = (await get('/v1/mypage/bookings/2').expect(200)).body;
    expect(r).toMatchObject({ kind: 'request', isActive: true, statusLabel: '예약확정', hospitalName: '가나병원', treatment: '필러', bookerName: '김', phone: '010-1234-5678', doctorName: null });
    expect(r.dday).toMatch(/^D-\d+$/);
    expect(r.summary).toEqual({ gross: 120000, points: 20000, final: 100000, expectedPoints: null });
    pointsOn = true;
    r = (await get('/v1/mypage/bookings/2').expect(200)).body;
    expect(r.summary.expectedPoints).toBe(10000);
    expect(r.notifications[0]).toMatchObject({ at: '2026.10.20 14:02', event: 'confirmed', title: '예약 확정', body: '확정되었어요', inApp: 1, pushSent: 1, pushSkipped: false });
    expect((await get('/v1/mypage/bookings/1').expect(200)).body.summary).toBeNull(); // 요청 단계: no amount summary
  });

  it('detail: completed shows doctor, payment info and the review button state', async () => {
    const r = (await get('/v1/mypage/bookings/4').expect(200)).body;
    expect(r).toMatchObject({ kind: 'request', isCompleted: true, isActive: false, statusLabel: '시술완료', doctorName: '-', treatmentPrice: 120000, totalPayment: 120000 });
    expect(r.review).toEqual({ state: 'rejected', label: '후기 반려됨', reviewId: 77 });
  });

  it('detail: cancelled', async () => {
    const r = (await get('/v1/mypage/bookings/7').expect(200)).body;
    expect(r).toMatchObject({ kind: 'cancelled', badge: '자동 취소', reason: '결제 기한', paid: { total: 120000, refunded: null } });
    const none = (await get('/v1/mypage/bookings/8').expect(200)).body;
    expect(none).toMatchObject({ kind: 'cancelled', badge: '취소 완료', reason: null, paid: null });
  });
});
