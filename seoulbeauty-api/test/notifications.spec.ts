import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { NotificationsModule } from '../src/notifications/notifications.module';
import { bookingTabOf, displayIcon, extractBookingId, filterTypes, notiFilter, openUrl, timeLabel } from '../src/notifications/notifications.logic';

const NOW = '2026-10-28 14:00:00';

describe('notifications logic', () => {
  it('filters', () => {
    expect(notiFilter('booking')).toBe('booking');
    expect(notiFilter('x')).toBe('all');
    expect(filterTypes('all')).toEqual([]);
    expect(filterTypes('review')).toEqual(['review', 'community', 'comment']);
  });
  it('icons', () => {
    expect(displayIcon('booking', '결제 완료', '', 'bi-bell')).toBe('payment');
    expect(displayIcon('booking', '내일 시술', '', '')).toBe('clock');
    expect(displayIcon('booking', '예약 확정', '', '')).toBe('calendar');
    expect(displayIcon('review', 'x', '', '')).toBe('review');
    expect(displayIcon('general', '포인트 적립', '', '')).toBe('gift');
    expect(displayIcon('raffle', 'x', '', '')).toBe('gift');
    expect(displayIcon('system', 'x', '', '')).toBe('bell');
    expect(displayIcon('other', 'x', '', 'bi-clock')).toBe('clock');
    expect(displayIcon('other', 'x', '', 'zzz')).toBe('bell');
  });
  it('booking id and target', () => {
    expect(extractBookingId('booking_request_detail.php?booking_id=12')).toBe(12);
    expect(extractBookingId('x.php?source_booking_id=7#a')).toBe(7);
    expect(extractBookingId('x.php')).toBe(0);
    expect(extractBookingId(null)).toBe(0);
    expect(bookingTabOf({ status: 'confirmed', booking_date: '2026-10-30', booking_time: '10:00:00' }, NOW)).toBe('active');
    expect(bookingTabOf({ status: 'confirmed', booking_date: '2026-10-20', booking_time: '10:00:00' }, NOW)).toBe('completed');
    expect(bookingTabOf({ status: 'no_show', booking_date: '2026-10-30', booking_time: '10:00:00' }, NOW)).toBe('cancelled');
    expect(openUrl('review', 'x', undefined, NOW)).toBe('');
    expect(openUrl('booking', 'booking_cancel_detail.php?id=3', undefined, NOW)).toBe('/mypage/bookings?tab=cancelled');
    expect(openUrl('booking', 'x', undefined, NOW)).toBe('/mypage/bookings');
    expect(openUrl('booking', 'x', { status: 'completed', booking_date: '2026-10-30', booking_time: '10:00:00' }, NOW)).toBe('/mypage/bookings?tab=completed');
  });
  it('time label', () => {
    expect(timeLabel('2026-10-28 13:59:30', NOW)).toBe('방금');
    expect(timeLabel('2026-10-28 13:30:00', NOW)).toBe('30분 전');
    expect(timeLabel('2026-10-28 09:00:00', NOW)).toBe('5시간 전');
    expect(timeLabel('2026-10-27 23:00:00', NOW)).toBe('어제');
    expect(timeLabel('2026-10-27 10:00:00', NOW)).toBe('어제');
    expect(timeLabel('2026-10-25 10:00:00', NOW)).toBe('3일 전');
    expect(timeLabel('2026-10-10 10:00:00', NOW)).toBe('10.10');
    expect(timeLabel(null, NOW)).toBe('');
  });
});

describe('notifications API', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  let calls: { sql: string; args: any[] }[];
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/SELECT id, type, title/.test(sql)) return [[{ id: 5, type: 'booking', title: '예약 확정', message: 'm', link: 'x.php?booking_id=9', icon: 'bi-bell', is_read: 0, created_at: '2026-10-28 13:59:59' }]];
    if (/FROM gangnamunni_bookings/.test(sql)) return [[{ id: 9, status: 'confirmed', booking_date: '2030-01-01', booking_time: '10:00:00' }]];
    if (/is_read = 0/.test(sql) && /COUNT/.test(sql)) return [[{ cnt: 3 }]];
    if (/COUNT/.test(sql)) return [[{ cnt: 41 }]];
    if (/^DELETE/.test(sql)) return [{ affectedRows: args[0] === 5 ? 1 : 0 }];
    return [{ affectedRows: 1 }];
  };
  beforeAll(async () => {
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args) } }], exports: [DB] }) class FakeDb {}
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=m1') ? { id: 'm1', no: 42, nickname: 'n', phone: '010-1234-5678' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: 'x', level: 2, ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, NotificationsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => { calls = []; });

  it('401 for visitors', async () => { await request(app.getHttpServer()).get('/v1/mypage/notifications').expect(401); });
  it('lists with paging and unread', async () => {
    const r = await request(app.getHttpServer()).get('/v1/mypage/notifications?type=booking&page=2').set('Cookie', 'sid=m1').expect(200);
    expect(r.headers['cache-control']).toContain('no-store');
    expect(r.body.total).toBe(41); expect(r.body.unread).toBe(3); expect(r.body.hasMore).toBe(true);
    expect(r.body.items[0]).toMatchObject({ id: 5, isRead: false, icon: 'calendar', openUrl: '/mypage/bookings' });
    const sel = calls.find((c) => /SELECT id, type, title/.test(c.sql))!;
    expect(sel.sql).toContain('type IN (?,?)'); expect(sel.args).toEqual(['m1', 'booking', 'payment', 20, 20]);
  });
  it('rejects bad page', async () => { await request(app.getHttpServer()).get('/v1/mypage/notifications?page=0').set('Cookie', 'sid=m1').expect(400); });
  it('writes need same-origin header and are scoped to the member', async () => {
    await request(app.getHttpServer()).post('/v1/mypage/notifications/5/read').set('Cookie', 'sid=m1').expect(403);
    await request(app.getHttpServer()).post('/v1/mypage/notifications/5/read').set('Cookie', 'sid=m1').set('X-Requested-With', 'XMLHttpRequest').expect(201);
    expect(calls.at(-1)!.args).toEqual([5, 'm1']);
    await request(app.getHttpServer()).post('/v1/mypage/notifications/read-all').set('Cookie', 'sid=m1').set('X-Requested-With', 'XMLHttpRequest').expect(201);
    expect(calls.at(-1)!.args).toEqual(['m1']);
  });
  it('delete: own row only', async () => {
    const h = { Cookie: 'sid=m1', 'X-Requested-With': 'XMLHttpRequest' };
    expect((await request(app.getHttpServer()).delete('/v1/mypage/notifications/5').set(h).expect(200)).body).toEqual({ success: true });
    expect((await request(app.getHttpServer()).delete('/v1/mypage/notifications/6').set(h).expect(200)).body).toEqual({ success: false });
  });
});
