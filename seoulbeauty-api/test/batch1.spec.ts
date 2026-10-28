import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { MypageModule } from '../src/mypage/mypage.module';
import { FaqModule } from '../src/faq/faq.module';
import { ReviewsModule } from '../src/reviews/reviews.module';
import { consultDt, safeHttpUrl } from '../src/mypage/mypage.logic';
import { faqCategories, faqCategoryOf } from '../src/faq/faq.logic';
import { htmlToText, starCount } from '../src/reviews/reviews.logic';

describe('batch 1 logic', () => {
  it('consultDt / safeHttpUrl', () => {
    expect(consultDt('2026-10-20 14:02:00')).toBe('10.20 (화) 오후 2:02');
    expect(consultDt('2026-10-21 00:05:00')).toBe('10.21 (수) 오전 12:05');
    expect(consultDt('2026-10-21 12:00:00')).toBe('10.21 (수) 오후 12:00');
    expect(consultDt(null)).toBe('');
    expect(safeHttpUrl('https://pf.kakao.com/x')).toBe('https://pf.kakao.com/x');
    expect(safeHttpUrl('javascript:alert(1)')).toBe('');
  });
  it('faq categories keep legacy ones', () => {
    expect(faqCategories(['예약', '기타', '', '기타'])).toEqual(['결제', '계정', '예약', '일반', '포인트', '후기', '기타']);
    expect(faqCategoryOf('  ')).toBe('일반');
  });
  it('htmlToText drops markup', () => {
    expect(htmlToText('<p>안녕 <b>하세요</b></p><p>a &amp; b<br>c</p><script>alert(1)</script>')).toBe('안녕 하세요\na & b\nc\nalert(1)');
    expect(starCount(7)).toBe(5);
    expect(starCount(-1)).toBe(0);
    expect(starCount(4.6)).toBe(4);
  });
});

describe('batch 1 API (mock DB)', () => {
  let app: INestApplication;
  const sql: { q: string; v?: unknown[] }[] = [];
  const realFetch = global.fetch;
  let consultStatus = 'contact_failed';
  let affected = 1;
  let blocked = false;
  let failKakao = false;

  const db = {
    query: jest.fn(async (q: string, v?: unknown[]) => {
      sql.push({ q, v });
      if (q.includes('FROM gangnamunni_faq')) return [[
        { id: 1, category: '결제', question: 'Q1', answer: 'A1\nline2' },
        { id: 2, category: '', question: 'Q2', answer: 'A2' },
        { id: 3, category: '기타', question: 'Q3', answer: 'A3' },
      ]];
      if (q.includes('FROM gangnamunni_consultations c')) {
        if (failKakao && q.includes('kakao_channel_url')) throw new Error("Unknown column 'kakao_channel_url'");
        if (v && v[1] !== 'kim') return [[]];
        return [[{ id: 9, hospital_id: 5, hospital_name: '가나병원', consultation_type: 'kakao', status: consultStatus, name: '김', phone: '010-1', callback_slot: 'pm',
          created_at: '2026-10-20 14:02:00', hospital_message: '연락드렸어요', hospital_message_at: '2026-10-20 15:30:00', h_phone: '02-111-2222', h_kakao_channel_url: 'https://pf.kakao.com/x' }]];
      }
      if (q.includes('FROM gangnamunni_consultation_events')) return [[
        { kind: 'no_answer', created_at: '2026-10-20 15:00:00' }, { kind: 'weird', created_at: '2026-10-20 15:05:00' }, { kind: 'completed', created_at: '2026-10-21 10:00:00' },
      ]];
      if (q.startsWith('SELECT status FROM gangnamunni_consultations')) return [v && v[1] !== 'kim' ? [] : [{ status: consultStatus }]];
      if (q.startsWith('UPDATE gangnamunni_consultations')) return [{ affectedRows: affected }];
      if (q.startsWith('INSERT INTO gangnamunni_consultation_events')) return [{ affectedRows: 1 }];
      if (q.includes('FROM hospital_reviews r')) return [[{ id: 31, hospital_id: 5, user_id: 'lee', user_name: '이', user_avatar: 'javascript:x', rating: 4.5, title: '제목', content: '본문',
        editor_type: 'quill', editor_content: '<p>꾸밈 <b>본문</b></p>', treatments: '리쥬란, 보톡스', like_count: 3, view_count: 7,
        hospital_name: '가나병원', hospital_image: 'uploads/h.jpg', hospital_address: '서울 강남구', hospital_rating: 4.26, hospital_review_count: 12 }]];
      if (q.includes('FROM gangnamunni_blocked_users')) return [blocked ? [{ 1: 1 }] : []];
      if (q.includes('FROM review_images')) return [[{ image_url: 'uploads/r/1.jpg', thumbnail_url: 'uploads/r/1_t.jpg' }, { image_url: 'javascript:x', thumbnail_url: null }]];
      if (q.includes('COUNT(*) AS c FROM review_comments')) return [[{ c: 2 }]];
      if (q.includes('FROM review_likes')) return [[{ id: 1 }]];
      return [[]];
    }),
  };

  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42, nickname: '김닉', phone: '010-1234-5678' } : c.includes('sid=lee') ? { id: 'lee', no: 43, nickname: '이닉', phone: '' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: '이름', level: 2, ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: db }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AuthModule, MypageModule, FaqModule, ReviewsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => { sql.length = 0; consultStatus = 'contact_failed'; affected = 1; blocked = false; failKakao = false; });
  const get = (p: string, who: string | null = 'kim') => { const r = request(app.getHttpServer()).get(p); return who ? r.set('Cookie', `sid=${who}`) : r; };
  const post = (p: string, body: object, who: string | null = 'kim', xhr = true) => {
    let r = request(app.getHttpServer()).post(p).send(body);
    if (who) r = r.set('Cookie', `sid=${who}`);
    if (xhr) r = r.set('X-Requested-With', 'XMLHttpRequest');
    return r;
  };

  it('faq is public and groups by category with legacy ones appended', async () => {
    const res = await get('/v1/faq', null).expect(200);
    expect(res.headers['cache-control']).toContain('public');
    expect(res.body.categories).toEqual(['결제', '계정', '예약', '일반', '포인트', '후기', '기타']);
    expect(res.body.items.map((i: any) => i.category)).toEqual(['결제', '일반', '기타']);
    expect(res.body.items[0].answer).toBe('A1\nline2');
  });

  it('consultation detail: own only, timeline, message, buttons', async () => {
    await get('/v1/mypage/consultations/9', null).expect(401);
    const res = await get('/v1/mypage/consultations/9').expect(200);
    expect(res.headers['cache-control']).toBe('private, no-store');
    expect(res.body).toMatchObject({
      id: 9, status: 'contact_failed', statusLabel: '연락 실패', sub: '카카오톡 채널 상담 · 오후 · 김 010-1', createdLabel: '10.20 (화) 오후 2:02',
      canRerequest: true, showSlots: true, slot: 'pm', hospitalPhone: '021112222', kakaoUrl: null, completed: false,
      hospitalMessage: { text: '연락드렸어요', meta: '가나병원 · 오후 3:30' },
    });
    expect(res.body.events).toEqual([{ label: '병원 연락 · 받지 않음', at: '10.20 (화) 오후 3:00' }]);
    expect(res.body.slots.map((s: any) => s.key)).toEqual(['am', 'pm', 'eve', 'any']);
    expect(sql.find((s) => s.q.includes('FROM gangnamunni_consultations c'))!.v).toEqual([9, 'kim']);
    await get('/v1/mypage/consultations/9', 'lee').expect(404);
    await get('/v1/mypage/consultations/abc').expect(400);
  });

  it('consultation detail: kakao button for pending kakao, completed line, kakao column fallback', async () => {
    consultStatus = 'pending';
    expect((await get('/v1/mypage/consultations/9').expect(200)).body.kakaoUrl).toBe('https://pf.kakao.com/x');
    consultStatus = 'completed';
    const done = (await get('/v1/mypage/consultations/9').expect(200)).body;
    expect(done).toMatchObject({ completed: true, completedAt: '10.21 (수) 오전 10:00', kakaoUrl: null, canRerequest: false });
    failKakao = true;
    const r = (await get('/v1/mypage/consultations/9').expect(200)).body;
    expect(r.kakaoUrl).toBeNull();
  });

  it('re-request: needs XHR header, a valid slot and the contact_failed state', async () => {
    await post('/v1/mypage/consultations/9/rerequest', { slot: 'am' }, 'kim', false).expect(403);
    await post('/v1/mypage/consultations/9/rerequest', { slot: 'am' }, null).expect(401);
    await post('/v1/mypage/consultations/9/rerequest', { slot: 'night' }).expect(400);
    await post('/v1/mypage/consultations/9/rerequest', {}).expect(400);
    expect(sql.some((s) => s.q.startsWith('UPDATE'))).toBe(false);
    await post('/v1/mypage/consultations/9/rerequest', { slot: 'am' }, 'lee').expect(404);
    consultStatus = 'pending';
    await post('/v1/mypage/consultations/9/rerequest', { slot: 'am' }).expect(400);
    expect(sql.some((s) => s.q.startsWith('UPDATE'))).toBe(false);
  });

  it('re-request: updates with the state guard and logs the event', async () => {
    const res = await post('/v1/mypage/consultations/9/rerequest', { slot: 'eve' }).expect(201);
    expect(res.body).toEqual({ success: true });
    const up = sql.find((s) => s.q.startsWith('UPDATE gangnamunni_consultations'))!;
    expect(up.q).toContain("AND status = 'contact_failed'");
    expect(up.v).toEqual(['eve', 9, 'kim']);
    expect(sql.some((s) => s.q.startsWith('INSERT INTO gangnamunni_consultation_events'))).toBe(true);
  });

  it('re-request: a lost race (0 rows) is rejected and logs nothing', async () => {
    affected = 0;
    await post('/v1/mypage/consultations/9/rerequest', { slot: 'eve' }).expect(400);
    expect(sql.some((s) => s.q.startsWith('INSERT INTO gangnamunni_consultation_events'))).toBe(false);
  });

  it('review detail: public, plain text, safe urls, viewer like and ownership', async () => {
    const res = await get('/v1/reviews/31', null).expect(200);
    expect(res.headers['cache-control']).toBe('private, no-store');
    expect(res.body).toMatchObject({
      id: 31, hospitalId: 5, treatments: ['리쥬란', '보톡스'], rating: 4, ratingValue: 4.5, title: '제목', content: '꾸밈 본문',
      likeCount: 3, commentCount: 2, viewCount: 7, liked: false, isMine: false,
      author: { name: '이', avatar: null },
      hospital: { id: 5, name: '가나병원', imageUrl: 'uploads/h.jpg', rating: 4.3, reviewCount: 12 },
    });
    expect(res.body.images).toEqual([{ src: 'uploads/r/1_t.jpg', full: 'uploads/r/1.jpg' }]);
    const mine = (await get('/v1/reviews/31', 'lee').expect(200)).body;
    expect(mine).toMatchObject({ isMine: true, liked: true });
  });

  it('review detail: blocked author is a 404, unknown id too', async () => {
    blocked = true;
    await get('/v1/reviews/31', 'kim').expect(404);
    blocked = false;
    await get('/v1/reviews/0').expect(400);
  });
});
