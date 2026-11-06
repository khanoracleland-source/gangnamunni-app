import { Test } from '@nestjs/testing';
import { Global, Module } from '@nestjs/common';
import { DB } from '../src/db/db.module';
import { FcmClient } from '../src/admin-notify/fcm.client';
import { MemberNotifyModule } from '../src/member-notify/member-notify.module';
import { MemberNotifyService } from '../src/member-notify/member-notify.service';
import { buildMemberFcmMessage, defaultBody, detailLink, koreanPhone, memberPushData, payload, switchFor } from '../src/member-notify/member-notify.logic';

const B = { id: 7, hospital_id: 5, hospital_name: '가나병원', treatment: '필러', booking_date: '2026-11-07', booking_time: '14:00:00', price: 120000, mb_id: 'kim', phone: '' };

describe('member notify logic (port of booking_notifications.php)', () => {
  it('default body = 병원명 · 날짜 시간, like PHP', () => {
    expect(defaultBody(B)).toBe('가나병원 · 2026-11-07 14:00');
    expect(defaultBody({ ...B, hospital_name: '', booking_date: new Date(2026, 10, 7) })).toBe('2026-11-07 14:00');
    expect(defaultBody({ id: 1, treatment: '보톡스' })).toBe('보톡스');
  });
  it('titles / bodies per event', () => {
    expect(payload(B, 'received').title).toBe('예약이 접수되었어요');
    expect(payload(B, 'payment_requested', { deadlineHours: 48 }).body).toContain('48시간 안에 결제하지 않으면');
    expect(payload(B, 'payment_requested').body).toContain('24시간');
    expect(payload(B, 'paid', { amount: 120000 }).body).toBe('가나병원 · 2026-11-07 14:00\n120,000원 결제 완료');
    expect(payload(B, 'payment_expired').title).toBe('결제 기한이 지나 예약이 취소되었어요');
    expect(payload(B, 'cancelled_by_member')).toMatchObject({ title: '예약이 취소되었어요', link: 'booking_cancel_detail.php?booking_id=7' });
    expect(payload(B, 'changed').link).toBe('my_bookings.php');
    expect(payload(B, 'changed').body).toBe('가나병원 · 2026-11-07 14:00');
    expect(payload(B, 'changed', { recheck: true }).body).toContain('병원 확인 후 다시 알려드릴게요');
    expect(payload(B, 'doctor_assigned', { doctorName: '김의사' }).body).toBe('김의사 · 가나병원 · 2026-11-07 14:00');
    expect(payload(B, 'visit_reminder').body).toBe('가나병원 · 2026-11-07 14:00\n필러');
  });
  it('links follow the PHP rules', () => {
    expect(detailLink(B, 'payment_requested')).toBe('booking_payment.php?booking_id=7');
    expect(detailLink(B, 'payment_expired')).toBe('booking_cancel_detail.php?booking_id=7');
    expect(detailLink(B, 'reschedule_required')).toBe('booking.php?id=5&rebook=1&source_booking_id=7&treatment=%ED%95%84%EB%9F%AC');
    expect(detailLink({ id: 0 }, 'paid')).toBe('my_bookings.php');
  });
  it('switch: visit reminder follows noti_visit, everything else noti_booking', () => {
    expect(switchFor('visit_reminder')).toBe('noti_visit');
    expect(switchFor('paid')).toBe('noti_booking');
    expect(switchFor('received')).toBe('noti_booking');
  });
  it('push data and message body', () => {
    const data = memberPushData('https://seoulbeauty.app/my_bookings.php', 7, 'received');
    expect(data).toMatchObject({ type: 'booking_notification', booking_event: 'received', booking_id: '7', url: 'https://seoulbeauty.app/my_bookings.php', webview_url: 'https://seoulbeauty.app/my_bookings.php' });
    const ios = buildMemberFcmMessage('tok', 'ios', 't', 'b', data).message as any;
    expect(ios.apns.payload.aps.sound).toBe('default');
    expect(ios.data.booking_id).toBe('7');
    expect((buildMemberFcmMessage('tok', '', 't', 'b', data).message as any).android.notification.channel_id).toBe('notification');
  });
  it('korean phone', () => {
    expect(koreanPhone('010-1234-5678')).toBe('01012345678');
    expect(koreanPhone('+82 10 1234 5678')).toBe('01012345678');
    expect(koreanPhone('02-123-4567')).toBe('');
  });
});

describe('MemberNotifyService (mock DB + FCM)', () => {
  let svc: MemberNotifyService;
  let sql: { q: string; v: any[] }[] = [];
  let pref: any = { noti_booking: 1, noti_visit: 1 };
  let tokens: any[] = [{ pt_token: 'T1', pt_platform: 'ios', pt_device_info: '' }];
  let fcmEnabled = '1';
  let sends: any[] = [];
  let fcmOk = true;
  const fcm = { session: async () => ({ project: 'p', access: 'a' }), sendMessage: async (_s: any, m: any) => { sends.push(m); return fcmOk ? { ok: true } : { ok: false, dead: true }; }, send: async () => ({ ok: true }) };
  const db = { query: async (q: string, v: any[] = []) => {
    sql.push({ q, v });
    if (q.includes('FROM gangnamunni_bookings')) return [v[0] === 7 ? [B] : []];
    if (q.includes('FROM g5_member')) return [[{ mb_id: 'byphone' }]];
    if (q.includes('FROM gangnamunni_notification_settings')) return [pref ? [pref] : []];
    if (q.includes("setting_key = 'fcm_enabled'")) return [[{ setting_value: fcmEnabled }]];
    if (q.includes('FROM g5_push_tokens')) return [tokens];
    return [{ affectedRows: 1, insertId: 1 }];
  } };
  beforeAll(async () => {
    @Global() @Module({ providers: [{ provide: DB, useValue: db }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, MemberNotifyModule] }).overrideProvider(FcmClient).useValue(fcm).compile();
    svc = mod.get(MemberNotifyService);
  });
  beforeEach(() => { sql = []; sends = []; pref = { noti_booking: 1, noti_visit: 1 }; tokens = [{ pt_token: 'T1', pt_platform: 'ios', pt_device_info: '' }]; fcmEnabled = '1'; fcmOk = true; });

  it('switch on: in-app row + push + send log, links absolute', async () => {
    const r = await svc.booking(7, 'received');
    expect(r).toMatchObject({ memberId: 'kim', inApp: true, pushSent: 1, pushFailed: 0, pushSkipped: false });
    const ins = sql.find((x) => x.q.includes('INSERT INTO gangnamunni_user_notifications'))!;
    expect(ins.v).toEqual(['kim', 'booking', '예약이 접수되었어요', '가나병원 · 2026-11-07 14:00\n병원 확인 후 알려드릴게요', 'https://seoulbeauty.app/my_bookings.php', 'bi-calendar-check']);
    expect(sends[0].message.data).toMatchObject({ type: 'booking_notification', booking_event: 'received', booking_id: '7' });
    const log = sql.find((x) => x.q.includes('INSERT INTO gangnamunni_notification_sends'))!;
    expect(log.v.slice(0, 2)).toEqual(['booking:kim', 'booking_received']);
    expect(JSON.parse(log.v[log.v.length - 1])).toMatchObject({ booking_id: 7, event: 'received', push_sent: 1 });
  });
  it('switch off (noti_booking = 0): nothing is written or sent', async () => {
    pref = { noti_booking: 0, noti_visit: 1 };
    const r = await svc.booking(7, 'paid', { amount: 120000 });
    expect(r.reason).toBe('booking_notifications_disabled');
    expect(sql.some((x) => x.q.startsWith('INSERT'))).toBe(false);
    expect(sends).toHaveLength(0);
  });
  it('visit reminder follows noti_visit, not noti_booking', async () => {
    pref = { noti_booking: 0, noti_visit: 1 };
    expect((await svc.booking(7, 'visit_reminder')).inApp).toBe(true);
    pref = { noti_booking: 1, noti_visit: 0 };
    expect((await svc.booking(7, 'visit_reminder')).reason).toBe('visit_notifications_disabled');
  });
  it('no settings row / missing column = on (the default)', async () => {
    pref = null;
    expect((await svc.booking(7, 'cancelled_by_member')).inApp).toBe(true);
    pref = { noti_booking: 1 }; // noti_visit column not added yet
    expect((await svc.booking(7, 'visit_reminder')).inApp).toBe(true);
  });
  it('no device token: in-app only, push skipped; dead token is switched off; FCM disabled skips', async () => {
    tokens = [];
    expect(await svc.booking(7, 'changed')).toMatchObject({ inApp: true, pushSent: 0, pushSkipped: true });
    tokens = [{ pt_token: 'DEAD', pt_platform: '', pt_device_info: 'iPhone' }]; fcmOk = false;
    expect(await svc.booking(7, 'changed')).toMatchObject({ pushSent: 0, pushFailed: 1 });
    expect(sql.find((x) => x.q.includes('UPDATE g5_push_tokens SET pt_use = 0'))!.v).toEqual(['DEAD']);
    fcmOk = true; fcmEnabled = '0';
    expect(await svc.booking(7, 'changed')).toMatchObject({ inApp: true, pushSkipped: true, pushError: 'FCM disabled' });
  });
  it('member found by phone when mb_id is empty; unknown booking / member stop early', async () => {
    (db as any).query = (((orig) => async (q: string, v: any[] = []) => { if (q.includes('FROM gangnamunni_bookings') && v[0] === 8) return [[{ ...B, id: 8, mb_id: '', phone: '010-9999-0000' }]]; return orig(q, v); })(db.query));
    expect((await svc.booking(8, 'received')).memberId).toBe('byphone');
    expect((await svc.booking(99, 'received')).reason).toBe('booking_not_found');
  });
});
