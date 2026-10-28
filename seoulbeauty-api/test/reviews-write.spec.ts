import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { generateKeyPairSync } from 'node:crypto';
import { mkdtempSync, readdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { ReviewsModule } from '../src/reviews/reviews.module';
import { orderDoctors, originalName, parseDoctorIds, safeBackUrl, validateReview } from '../src/reviews/reviews.write.logic';
import { buildFcmMessage, escapeHtml, isDeadToken, shouldReceive } from '../src/admin-notify/admin-notify.logic';

const JPG = Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(100)]);

describe('review write logic', () => {
  it('safeBackUrl allows only the listed same-site pages', () => {
    expect(safeBackUrl('my_bookings.php?tab=completed')).toBe('my_bookings.php?tab=completed');
    expect(safeBackUrl('/review_detail.php?id=3')).toBe('review_detail.php?id=3');
    expect(safeBackUrl('https://evil.test/x')).toBe('my_reviews.php');
    expect(safeBackUrl('//evil.test/my_reviews.php')).toBe('my_reviews.php');
    expect(safeBackUrl('javascript:alert(1)')).toBe('my_reviews.php');
    expect(safeBackUrl('admin.php')).toBe('my_reviews.php');
    expect(safeBackUrl('my_reviews.php\\..\\x')).toBe('my_reviews.php');
    expect(safeBackUrl(undefined)).toBe('my_reviews.php');
  });
  it('doctors: parse, validate, keep the picked order, cut at 100', () => {
    expect(parseDoctorIds('3, 5,3,x,-1,0,')).toEqual([3, 5]);
    const valid = new Map([[3, '김원장'], [5, '이원장']]);
    expect(orderDoctors([5, 9, 3], valid)).toEqual({ doctorId: 5, doctorName: '이원장, 김원장' });
    expect(orderDoctors([9], valid)).toBeNull();
    expect(Array.from(orderDoctors([1], new Map([[1, '가'.repeat(150)]]))!.doctorName).length).toBe(100);
  });
  it('validation order and wording', () => {
    const ok = { hospitalId: 1, rating: 5, title: 't', content: '열 글자 이상 내용입니다 정말', doctorUnknown: false, agreed: true, hasDoctor: true };
    expect(validateReview(ok)).toBeNull();
    expect(validateReview({ ...ok, hospitalId: 0 })).toBe('병원 정보를 확인해주세요.');
    expect(validateReview({ ...ok, hasDoctor: false })).toContain('담당 의사');
    expect(validateReview({ ...ok, hasDoctor: false, doctorUnknown: true })).toBeNull();
    expect(validateReview({ ...ok, rating: 6 })).toBe('평점을 선택해주세요.');
    expect(validateReview({ ...ok, title: '' })).toBe('제목을 입력해주세요.');
    expect(validateReview({ ...ok, content: '짧음' })).toBe('내용을 10자 이상 입력해주세요.');
    expect(validateReview({ ...ok, agreed: false })).toBe('커뮤니티 가이드라인에 동의해주세요.');
  });
  it('multipart file names are restored from latin1', () => {
    expect(originalName(Buffer.from('사진.jpg', 'utf8').toString('latin1'))).toBe('사진.jpg');
    expect(originalName('plain.jpg')).toBe('plain.jpg');
  });
});

describe('admin notification logic', () => {
  it('who receives a type', () => {
    expect(shouldReceive('{}', 'review')).toBe(false); // not in the defaults
    expect(shouldReceive(null, 'review_new')).toBe(true);
    expect(shouldReceive('{"notifications":["review"]}', 'review')).toBe(true);
    expect(shouldReceive('{"notifications":[]}', 'review_new')).toBe(false);
    expect(shouldReceive('{"notifications":"review"}', 'review')).toBe(false);
    expect(shouldReceive('not json', 'review_new')).toBe(true);
  });
  it('FCM v1 body per platform; dead-token detection', () => {
    const a = buildFcmMessage('tok', 'android', 'T', 'B', 'admin_reviews.php', 7).message as any;
    expect(a.android.notification.channel_id).toBe('notification');
    expect(a.data).toMatchObject({ type: 'admin_notification', admin_id: '7', webview_url: 'admin_reviews.php', url: 'admin_reviews.php', click_action: 'FLUTTER_NOTIFICATION_CLICK', source: 'gangnamunni_admin' });
    const i = buildFcmMessage('tok', 'IOS', 'T', 'B', null, 7).message as any;
    expect(i.apns.payload.aps).toEqual({ sound: 'default', badge: 1 });
    expect(i.data.url).toBeUndefined();
    expect(buildFcmMessage('tok', 'weird', 'T', 'B', null, 1).message).toHaveProperty('android');
    expect(isDeadToken(404, null)).toBe(true);
    expect(isDeadToken(400, { error: { details: [{ errorCode: 'UNREGISTERED' }] } })).toBe(true);
    expect(isDeadToken(500, { error: { details: [{ errorCode: 'INTERNAL' }] } })).toBe(false);
    expect(escapeHtml(`<a href="x">'&`)).toBe('&lt;a href=&quot;x&quot;&gt;&#039;&amp;');
  });
});

describe('review write API', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[];
  let failRecount = false; let booking: any; let existingReview: any; let editRow: any; let hospitalActive: boolean; let fcmEnabledRow: any;
  let fcmCalls: { url: string; body: any }[];
  const dir = mkdtempSync(join(tmpdir(), 'rev-'));
  const realFetch = global.fetch;
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/FROM gangnamunni_bookings b/.test(sql)) return [booking && args[1] === 'kim' ? [booking] : []];
    if (/FROM gangnamunni_hospitals h WHERE h\.id/.test(sql)) return [[{ name: '서울병원', address: '강남구', main_image_url: 'a.jpg' }]];
    if (/FROM hospital_reviews WHERE booking_id/.test(sql)) return [existingReview ? [existingReview] : []];
    if (/SELECT \* FROM hospital_reviews WHERE id = \? AND user_id = \? AND is_active = 1/.test(sql)) return [editRow && args[1] === 'kim' ? [editRow] : []];
    if (/FROM gangnamunni_doctors d WHERE/.test(sql)) return [[{ id: 3, name: '김원장', specialty: '', photo_url: null, treatment_tags: '눈||코' }, { id: 5, name: '이원장', specialty: '성형외과', photo_url: 'd.jpg', treatment_tags: null }]];
    if (/FROM gangnamunni_hospital_treatments ht/.test(sql)) return [[{ name: '쌍꺼풀' }, { name: '코필러' }]];
    if (/FROM gangnamunni_doctors WHERE id IN/.test(sql)) return [args.slice(0, -1).filter((x: number) => x === 3 || x === 5).map((id: number) => ({ id, name: id === 3 ? '김원장' : '이원장' }))];
    if (/SELECT name FROM gangnamunni_hospitals WHERE id = \? AND is_active = 1/.test(sql)) return [hospitalActive ? [{ name: '서울병원' }] : []];
    if (/SELECT name FROM gangnamunni_hospitals WHERE id = \? LIMIT 1/.test(sql)) return [[{ name: '<서울>병원' }]];
    if (failRecount && /^UPDATE gangnamunni_hospitals SET/.test(sql)) throw new Error('UPDATE command denied');
    if (/^INSERT INTO hospital_reviews/.test(sql)) return [{ insertId: 77 }];
    if (/FROM gangnamunni_admins WHERE role IN/.test(sql)) return [[{ id: 1 }, { id: 2 }]];
    if (/FROM gangnamunni_theme_settings/.test(sql)) return [fcmEnabledRow ? [fcmEnabledRow] : []];
    if (/FROM gangnamunni_admins WHERE is_active = 1/.test(sql)) return [[{ id: 1, permissions: '{"notifications":["review"]}' }, { id: 2, permissions: '{}' }]];
    if (/FROM gangnamunni_admin_fcm_tokens WHERE admin_id/.test(sql)) return [[{ fcm_token: `tok-${args[0]}`, platform: 'android' }]];
    return [{ affectedRows: 1 }];
  };
  beforeAll(async () => {
    const { privateKey } = generateKeyPairSync('rsa', { modulusLength: 2048, privateKeyEncoding: { type: 'pkcs8', format: 'pem' }, publicKeyEncoding: { type: 'spki', format: 'pem' } });
    const sa = join(dir, 'sa.json');
    writeFileSync(sa, JSON.stringify({ project_id: 'hospital-8425b', client_email: 'x@hospital-8425b.iam.gserviceaccount.com', private_key: privateKey }));
    process.env.FCM_SERVICE_ACCOUNT_PATH = sa;
    process.env.REVIEW_UPLOAD_DIR = join(dir, 'reviews');
    process.env.REVIEW_RATE_MAX = '1000';
    global.fetch = jest.fn(async (u: any, init: any) => {
      const url = String(u);
      if (url.startsWith('https://oauth2.googleapis.com/token')) return new Response(JSON.stringify({ access_token: 'AT', expires_in: 3600 }), { status: 200 });
      if (url.startsWith('https://fcm.googleapis.com/')) {
        const body = JSON.parse(init.body);
        fcmCalls.push({ url, body });
        return body.message.token === 'tok-1' && fcmDead ? new Response(JSON.stringify({ error: { details: [{ errorCode: 'UNREGISTERED' }] } }), { status: 404 }) : new Response('{}', { status: 200 });
      }
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42, nickname: '김&닉', phone: '010' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: 'x', level: 2, ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args) } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, ReviewsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  let fcmDead = false;
  afterAll(async () => { global.fetch = realFetch; delete process.env.FCM_SERVICE_ACCOUNT_PATH; delete process.env.REVIEW_UPLOAD_DIR; await app.close(); });
  beforeEach(() => {
    failRecount = false; calls = []; fcmCalls = []; fcmDead = false; fcmEnabledRow = null; hospitalActive = true; existingReview = null; editRow = null;
    booking = { hospital_id: 9, name: '서울병원', treatment: '쌍꺼풀', booking_date: '2026-10-01', address: '강남구', main_image_url: 'a.jpg', doctor_name: '' };
  });
  const H = { Cookie: 'sid=kim', 'X-Requested-With': 'XMLHttpRequest' };
  const base = { booking_id: '4', hospital_id: '999', rating: '5', title: '좋아요', content: '아주 만족스러운 시술이었습니다', doctor_ids: '5,3', agree_guidelines: '1' };
  const settle = () => new Promise((r) => setTimeout(r, 80)); // admin notices run after the response
  const post = (fields: Record<string, string>, photos: { field: string; buf: Buffer }[] = []) => {
    let r = request(app.getHttpServer()).post('/v1/mypage/reviews').set(H);
    for (const [k, v] of Object.entries(fields)) r = r.field(k, v);
    for (const p of photos) r = r.attach(p.field, p.buf, { filename: '사진.jpg' });
    return r;
  };

  it('401 for visitors, 403 without the header', async () => {
    await request(app.getHttpServer()).get('/v1/mypage/review-form').expect(401);
    await request(app.getHttpServer()).post('/v1/mypage/reviews').set('Cookie', 'sid=kim').field('title', 'x').expect(403);
  });
  it('form: booking card, doctors, treatments (booked one first)', async () => {
    const r = await request(app.getHttpServer()).get('/v1/mypage/review-form?booking_id=4&return_url=https://evil.test').set('Cookie', 'sid=kim').expect(200);
    expect(r.headers['cache-control']).toContain('no-store');
    expect(r.body).toMatchObject({ mode: 'create', hospitalId: 9, bookingId: 4, backUrl: 'my_reviews.php', selectedTreatment: '쌍꺼풀', treatments: ['쌍꺼풀', '코필러'] });
    expect(r.body.card).toMatchObject({ name: '서울병원', treatment: '쌍꺼풀', bookingDate: '2026-10-01' });
    expect(r.body.doctors[0]).toMatchObject({ id: 3, specialty: '전문의', tags: ['눈', '코'] });
  });
  it('form: someone else’s booking falls back to the hospital card only', async () => {
    booking = null;
    const r = await request(app.getHttpServer()).get('/v1/mypage/review-form?booking_id=4&hospital_id=9').set('Cookie', 'sid=kim').expect(200);
    expect(r.body).toMatchObject({ bookingId: 0, hospitalId: 9, selectedTreatment: '' });
  });
  it('form: edit needs an own published review', async () => {
    await request(app.getHttpServer()).get('/v1/mypage/review-form?id=8').set('Cookie', 'sid=kim').expect(404);
    editRow = { id: 8, hospital_id: 9, rating: 4, title: 'T', content: 'C'.repeat(12), treatments: '코필러', doctor_id: 5, doctor_name: '이원장' };
    const r = await request(app.getHttpServer()).get('/v1/mypage/review-form?id=8').set('Cookie', 'sid=kim').expect(200);
    expect(r.body).toMatchObject({ mode: 'edit', editId: 8, selectedTreatment: '코필러', selectedDoctorIds: [5], review: { rating: 4, title: 'T' } });
  });

  it('creates from the booking (hospital/treatment never from the form), notifies admins', async () => {
    const r = await post(base).expect(201);
    expect(r.body).toMatchObject({ success: true, id: 77 });
    await settle();
    const ins = calls.find((c) => /^INSERT INTO hospital_reviews/.test(c.sql))!;
    expect(ins.args).toEqual([9, 'kim', '김&닉', '좋아요', '아주 만족스러운 시술이었습니다', 5, '쌍꺼풀', 4, 5, '이원장, 김원장']);
    const inApp = calls.filter((c) => /INSERT INTO gangnamunni_user_notifications/.test(c.sql));
    expect(inApp.map((c) => c.args[0])).toEqual(['1', '2']);
    expect(inApp[0].args.slice(1)).toEqual(['review', '새 후기 등록', '&lt;서울&gt;병원 — 김&amp;닉 (★5)', 'admin_reviews.php', 'bi-bell']);
    const log = calls.find((c) => /INSERT INTO gangnamunni_notification_sends/.test(c.sql))!;
    expect(log.args).toEqual(['review', '새 후기 등록', '&lt;서울&gt;병원 — 김&amp;닉 (★5)', 'admin_reviews.php']);
    // only the admin subscribed to 'review' is pushed
    expect(fcmCalls.map((c) => c.body.message.token)).toEqual(['tok-1']);
    expect(fcmCalls[0].url).toBe('https://fcm.googleapis.com/v1/projects/hospital-8425b/messages:send');
  });
  it('an expired token is switched off', async () => {
    fcmDead = true;
    await post(base).expect(201);
    await settle();
    const off = calls.find((c) => /UPDATE gangnamunni_admin_fcm_tokens SET is_active = 0/.test(c.sql))!;
    expect(off.args).toEqual(['tok-1']);
  });
  it('FCM switched off in settings: in-app and log only', async () => {
    fcmEnabledRow = { setting_value: '0' };
    await post(base).expect(201);
    await settle();
    expect(fcmCalls).toHaveLength(0);
    expect(calls.some((c) => /INSERT INTO gangnamunni_notification_sends/.test(c.sql))).toBe(true);
  });
  it('a failing notification never fails the request', async () => {
    const f = global.fetch as jest.Mock;
    const prev = f.getMockImplementation()!;
    f.mockImplementation(async (u: any, init: any) => { if (String(u).includes('googleapis')) throw new Error('net'); return prev(u, init); });
    await post(base).expect(201);
    f.mockImplementation(prev);
  });
  it('someone else’s booking -> 403, nothing written', async () => {
    booking = null;
    await post(base).expect(403);
    expect(calls.some((c) => /^INSERT/.test(c.sql))).toBe(false);
  });
  it('already reviewed booking -> success/already, no insert', async () => {
    existingReview = { id: 55, review_status: 'pending', is_active: 0 };
    const r = await post(base).expect(201);
    expect(r.body).toMatchObject({ success: true, already: true, id: 55 });
    expect(calls.some((c) => /^INSERT INTO hospital_reviews/.test(c.sql))).toBe(false);
  });
  it('doctor rules: unknown ok, foreign doctor not, none not', async () => {
    const noBooking = { ...base, booking_id: '', hospital_id: '9' };
    await post({ ...noBooking, doctor_ids: '', doctor_unknown: '1' }).expect(201);
    expect(calls.find((c) => /^INSERT INTO hospital_reviews/.test(c.sql))!.args.slice(-2)).toEqual([null, '담당 의사 모름'].map((x, i) => (i === 0 ? 0 : x)));
    await post({ ...noBooking, doctor_ids: '99' }).expect(400);
    await post({ ...noBooking, doctor_ids: '' }).expect(400);
  });
  it('validation errors write nothing', async () => {
    calls = [];
    expect((await post({ ...base, rating: '9' }).expect(400)).body.message).toBe('평점을 선택해주세요.');
    expect((await post({ ...base, content: '짧다' }).expect(400)).body.message).toBe('내용을 10자 이상 입력해주세요.');
    expect((await post({ ...base, agree_guidelines: '' }).expect(400)).body.message).toContain('가이드라인');
    expect(calls.some((c) => /^INSERT/.test(c.sql))).toBe(false);
  });
  it('unknown hospital without a booking -> 400', async () => {
    hospitalActive = false;
    await post({ ...base, booking_id: '', hospital_id: '12345' }).expect(400);
  });

  it('photos: sniffed, consent required, stored under reviews/<id>/, rows written', async () => {
    await post(base, [{ field: 'photos_before', buf: JPG }]).expect(400); // no consent
    await post({ ...base, photo_consent: '1' }, [{ field: 'photos_before', buf: Buffer.from('<?php echo 1;') }]).expect(400);
    expect(calls.some((c) => /^INSERT INTO hospital_reviews/.test(c.sql))).toBe(false);
    calls = [];
    const r = await post({ ...base, photo_consent: '1' }, [{ field: 'photos_before', buf: JPG }, { field: 'photos_after', buf: JPG }, { field: 'photos_after', buf: JPG }]).expect(201);
    expect(r.body.photoError).toBeUndefined();
    const files = readdirSync(join(dir, 'reviews', '77'));
    expect(files.filter((f) => f.startsWith('before_'))).toHaveLength(1);
    expect(files.filter((f) => f.startsWith('after_'))).toHaveLength(2);
    const rows = calls.filter((c) => /^INSERT INTO review_images/.test(c.sql));
    expect(rows).toHaveLength(3);
    expect(rows[0].args[1]).toMatch(/^uploads\/reviews\/77\/before_\d+_\d+\.jpg$/);
    expect(rows[0].args[4]).toBe('사진.jpg');
    expect(calls.filter((c) => /^DELETE FROM review_images/.test(c.sql)).map((c) => c.args[1]).sort()).toEqual(['after', 'before']);
  });
  it('more than 2 photos per category is rejected', async () => {
    const three = [1, 2, 3].map(() => ({ field: 'photos_before', buf: JPG }));
    const r = await post({ ...base, photo_consent: '1' }, three);
    expect(r.status).toBeGreaterThanOrEqual(400);
    expect(calls.some((c) => /^INSERT INTO hospital_reviews/.test(c.sql))).toBe(false);
  });

  it('a failing hospital recount is only logged: the edit still succeeds', async () => {
    failRecount = true;
    editRow = { id: 8, hospital_id: 9, rating: 4, title: 'T', content: 'C'.repeat(12), treatments: '코필러', doctor_id: 5, doctor_name: '이원장' };
    const r = await post({ edit_id: '8', rating: '3', title: '수정', content: '수정한 내용이 충분히 깁니다', doctor_ids: '3', agree_guidelines: '1' }).expect(201);
    expect(r.body).toMatchObject({ success: true, id: 8 });
    expect(calls.some((c) => /^UPDATE hospital_reviews SET/.test(c.sql))).toBe(true);
  });
  it('the response does not wait for the admin notices', async () => {
    const f = global.fetch as jest.Mock;
    const prev = f.getMockImplementation()!;
    f.mockImplementation(async (u: any, init: any) => { if (String(u).includes('googleapis')) await new Promise((r) => setTimeout(r, 400)); return prev(u, init); });
    const t = Date.now();
    await post(base).expect(201);
    expect(Date.now() - t).toBeLessThan(300);
    await new Promise((r) => setTimeout(r, 700));
    f.mockImplementation(prev);
  });
  it('edit: back to pending, both hospitals recounted, no admin notice, hospital fixed to the review’s own', async () => {
    editRow = { id: 8, hospital_id: 9, rating: 4, title: 'T', content: 'C'.repeat(12), treatments: '코필러', doctor_id: 5, doctor_name: '이원장' };
    const r = await post({ edit_id: '8', hospital_id: '31', rating: '3', title: '수정', content: '수정한 내용이 충분히 깁니다', doctor_ids: '3', agree_guidelines: '1' }).expect(201);
    expect(r.body).toMatchObject({ success: true, id: 8 });
    const upd = calls.find((c) => /^UPDATE hospital_reviews SET/.test(c.sql))!;
    expect(upd.sql).toContain("review_status='pending', is_active=0");
    expect(upd.args).toEqual([9, '수정', '수정한 내용이 충분히 깁니다', 3, '코필러', 3, '김원장', 8, 'kim']);
    expect(calls.filter((c) => /^UPDATE gangnamunni_hospitals SET/.test(c.sql)).map((c) => c.args[2])).toEqual([9]);
    expect(calls.some((c) => /gangnamunni_user_notifications/.test(c.sql))).toBe(false);
    editRow = null;
    await post({ edit_id: '8', rating: '3', title: 'x', content: 'x'.repeat(12), doctor_ids: '3', agree_guidelines: '1' }).expect(404);
  });
});
