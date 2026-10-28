import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import { promises as fs } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { MypageModule } from '../src/mypage/mypage.module';
import { addressShort, consultStatus, consultTypeLabel, dotDate, profileFileId, reviewDistrict, telDigits, wishlistDistrict } from '../src/mypage/mypage.logic';

describe('mypage logic', () => {
  it('consult status/type labels (consultation_view.php)', () => {
    expect(consultStatus('contact_failed')).toEqual({ label: '연락 실패', tone: 'danger' });
    expect(consultStatus('completed').label).toBe('상담 완료');
    expect(consultStatus('rejected').label).toBe('상담 불가');
    expect(consultStatus('pending')).toEqual({ label: '확인 중', tone: 'dark' });
    expect(consultStatus(null).label).toBe('확인 중');
    expect(consultTypeLabel('kakao')).toBe('카카오톡 채널 상담');
    expect(consultTypeLabel('zzz')).toBe('상담');
  });
  it('districts, address and dates', () => {
    expect(reviewDistrict('서울 강남구 테헤란로 1')).toBe('강남');
    expect(reviewDistrict('경기 양평군')).toBe('양평');
    expect(reviewDistrict('부산 해운대')).toBe('');
    expect(wishlistDistrict('서울 강남구 테헤란로')).toBe('강남구');
    expect(wishlistDistrict('서울')).toBe('서울');
    expect(addressShort('가'.repeat(25))).toBe('가'.repeat(20) + '...');
    expect(addressShort(' 짧은 주소 ')).toBe('짧은 주소');
    expect(dotDate('2026-10-01')).toBe('2026.10.01');
    expect(dotDate(null)).toBe('');
    expect(telDigits('02-123 4567')).toBe('021234567');
    expect(telDigits('+82 2')).toBe('+822');
    expect(profileFileId('../a b_c-1')).toBe('abc-1'.replace('abc-1', 'ab_c-1'));
  });
});

describe('mypage API (mock DB)', () => {
  let app: INestApplication;
  let dir: string;
  const sql: { q: string; v?: unknown[] }[] = [];
  const realFetch = global.fetch;
  let adShow = '1';
  let failConsult = false;

  const db = {
    query: jest.fn(async (q: string, v?: unknown[]) => {
      sql.push({ q, v });
      if (q.includes('COUNT(*) AS c FROM gangnamunni_bookings')) return [[{ c: 3 }]];
      if (q.includes('COUNT(*) AS c FROM gangnamunni_consultations')) { if (failConsult) throw new Error("Unknown column 'mb_id'"); return [[{ c: 2 }]]; }
      if (q.includes('FROM gangnamunni_points')) return [[{ balance: 1500 }]];
      if (q.includes('FROM gangnamunni_ads a')) return [[
        { id: 1, title: '광고1', subtitle: '부제', image_url: 'uploads/ads/a.jpg', mypage_image_url: 'uploads/ads/a_my.jpg', link_url: 'events.php' },
        { id: 2, title: '광고2', subtitle: null, image_url: 'uploads/ads/b.jpg', mypage_image_url: null, link_url: 'javascript:alert(1)' },
      ]];
      if (q.includes("setting_key = 'ad_show_index'")) return [[{ setting_value: adShow }]];
      if (q.includes("'ad_auto_scroll_enabled'")) return [[]];
      if (q.includes('FROM gangnamunni_wishlist w')) return [[
        { id: 11, target_id: 5, target_name: '가나병원', target_image: 'uploads/h5.jpg', target_address: '서울 강남구 테헤란로 123 가나빌딩 4층', hospital_available: 1 },
        { id: 12, target_id: 6, target_name: null, target_image: null, target_address: null, hospital_available: 0 },
      ]];
      if (q.includes('FROM hospital_images')) return [[{ hospital_id: 5, image_url: 'uploads/main5.jpg', image_type: 'main' }, { hospital_id: 5, image_url: 'uploads/logo5.jpg', image_type: 'logo' }]];
      if (q.includes('FROM gangnamunni_consultations c')) return [[
        { id: 9, hospital_id: 5, hospital_name: '가나병원', consultation_type: 'general', status: 'contact_failed', created_at: '2026-10-20 14:02:00', h_phone: '02-111-2222' },
        { id: 8, hospital_id: 6, hospital_name: '다라병원', consultation_type: 'kakao', status: 'pending', created_at: '2026-10-19 09:00:00', h_phone: '' },
      ]];
      if (q.includes('FROM hospital_reviews r LEFT JOIN')) return [[
        { id: 31, hospital_id: 5, rating: 4.5, treatments: '리쥬란', doctor_name: '김의사', review_status: 'approved', is_active: 1, hospital_name: '가나병원', main_image_url: 'uploads/h5.jpg', address: '서울 강남구 1', booking_date: '2026-09-30', booking_treatment: null },
        { id: 32, hospital_id: 5, rating: 5, treatments: null, doctor_name: null, review_status: 'pending', is_active: 0, hospital_name: '가나병원', main_image_url: null, address: '', booking_date: null, booking_treatment: '보톡스' },
      ]];
      if (q.includes('FROM gangnamunni_bookings b')) return [[{ id: 70, hospital_id: 5, hospital_name: '가나병원', treatment: '필러', booking_date: '2026-10-10', main_image_url: null, address: '서울 서초구 2', doctor_name: ' ' }]];
      return [[]];
    }),
  };

  beforeAll(async () => {
    dir = await fs.mkdtemp(join(tmpdir(), 'pf-'));
    await fs.writeFile(join(dir, 'kim.png'), 'x');
    process.env.PROFILE_PHOTO_DIR = dir;
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42, nickname: '김닉', phone: '010-1234-5678' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: '이름', level: 2, ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: db }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AuthModule, MypageModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.PROFILE_PHOTO_DIR; delete process.env.MYPAGE_POINTS_VISIBLE; await app.close(); await fs.rm(dir, { recursive: true, force: true }); });
  beforeEach(() => { sql.length = 0; adShow = '1'; failConsult = false; delete process.env.MYPAGE_POINTS_VISIBLE; });
  const get = (p: string, auth = true) => { const r = request(app.getHttpServer()).get(p); return auth ? r.set('Cookie', 'sid=kim') : r; };

  it('all pages need a login and are never cached', async () => {
    for (const p of ['/v1/mypage', '/v1/mypage/wishlist', '/v1/mypage/consultations', '/v1/mypage/reviews']) {
      await get(p, false).expect(401);
      expect((await get(p).expect(200)).headers['cache-control']).toBe('private, no-store');
    }
  });

  it('summary: counters, avatar, mypage ad image, points hidden by default', async () => {
    const r = (await get('/v1/mypage').expect(200)).body;
    expect(r).toMatchObject({ nickname: '김닉', bookingCount: 3, consultCount: 2, points: { visible: false, balance: 0 } });
    expect(r.avatarUrl).toMatch(/^\/hairwang\/gangnamunni\/uploads\/profile\/kim\.png\?v=\d+$/);
    expect(r.ads.show).toBe(true);
    expect(r.ads.autoScroll).toBe(true);
    expect(r.ads.items[0].imageUrl).toBe('uploads/ads/a_my.jpg');
    expect(r.ads.items[1]).toMatchObject({ imageUrl: 'uploads/ads/b.jpg', linkUrl: '#' });
    expect(sql.some((s) => s.q.includes('FROM gangnamunni_points'))).toBe(false);
    const b = sql.find((s) => s.q.includes('COUNT(*) AS c FROM gangnamunni_bookings'))!;
    expect(b.v).toEqual(['kim', '01012345678', '01012345678']);
  });

  it('summary: points shown only when switched on; ads off when the admin setting is off', async () => {
    process.env.MYPAGE_POINTS_VISIBLE = 'true';
    adShow = '0';
    const r = (await get('/v1/mypage').expect(200)).body;
    expect(r.points).toEqual({ visible: true, balance: 1500 });
    expect(r.ads.show).toBe(false);
  });

  it('summary survives a missing consultations column', async () => {
    failConsult = true;
    expect((await get('/v1/mypage').expect(200)).body.consultCount).toBe(0);
  });

  it('wishlist: availability, admin main image first, address rules', async () => {
    const r = (await get('/v1/mypage/wishlist').expect(200)).body;
    expect(sql.find((s) => s.q.includes('FROM gangnamunni_wishlist w'))!.v).toEqual([42]);
    expect(r.items[0]).toMatchObject({ id: 11, hospitalId: 5, name: '가나병원', available: true, imageUrl: 'uploads/main5.jpg', district: '강남구', address: '서울 강남구 테헤란로 123 가나빌딩...' });
    expect(r.items[1]).toMatchObject({ id: 12, name: '(삭제됨)', available: false, imageUrl: null, address: '' });
  });

  it('consultations: labels, date, phone digits only for tel:', async () => {
    const r = (await get('/v1/mypage/consultations').expect(200)).body;
    expect(r.items[0]).toMatchObject({ id: 9, statusLabel: '연락 실패', statusTone: 'danger', typeLabel: '전화 상담', dateLabel: '10.20', hospitalPhone: '021112222' });
    expect(r.items[1]).toMatchObject({ statusLabel: '확인 중', typeLabel: '카카오톡 채널 상담', hospitalPhone: null });
    expect(r.anyPhone).toBe('02-111-2222');
    expect(JSON.stringify(r)).not.toContain('message');
  });

  it('reviews: writable and done, pending flag', async () => {
    const r = (await get('/v1/mypage/reviews').expect(200)).body;
    expect(r.writable[0]).toMatchObject({ bookingId: 70, treatment: '필러', district: '서초', bookingDate: '2026.10.10', doctorName: null });
    expect(r.done[0]).toMatchObject({ id: 31, rating: 5, ratingValue: 4.5, treatment: '리쥬란', district: '강남', doctorName: '김의사', bookingDate: '2026.09.30', pending: false });
    expect(r.done[1]).toMatchObject({ id: 32, treatment: '보톡스', pending: true, imageUrl: null });
  });
});
