import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { EventsModule } from '../src/events/events.module';
import { adminWants, categoryColor, dateOnly, normalizePhone, raffleClosedReason, raffleTypeInfo, todayKst, validEmail } from '../src/events/events.logic';

const XHR = { 'X-Requested-With': 'XMLHttpRequest' };

describe('events logic (event_detail.php / event_api.php rules)', () => {
  it('categoryColor / raffleTypeInfo fall back like PHP', () => {
    expect(categoryColor('보톡스')).toBe('#7C5CFC');
    expect(categoryColor('없는카테고리')).toBe('#AC1830');
    expect(categoryColor(null)).toBe('#AC1830');
    expect(raffleTypeInfo('quiz').label).toBe('퀴즈 정답');
    expect(raffleTypeInfo('???').label).toBe('랜덤 추첨');
    expect(raffleTypeInfo(undefined).label).toBe('랜덤 추첨');
  });
  it('todayKst uses Korea time', () => {
    expect(todayKst(new Date('2026-10-27T16:00:00Z'))).toBe('2026-10-28'); // 01:00 KST next day
    expect(todayKst(new Date('2026-10-27T14:59:00Z'))).toBe('2026-10-27');
  });
  it('dateOnly', () => {
    expect(dateOnly('2026-11-05 10:00:00')).toBe('2026-11-05');
    expect(dateOnly('0000-00-00')).toBeNull();
    expect(dateOnly(null)).toBeNull();
  });
  it('raffleClosedReason: period passed beats full; end day itself is still open', () => {
    expect(raffleClosedReason('2026-10-26', 0, 0, '2026-10-27')).toBe('period');
    expect(raffleClosedReason('2026-10-27', 0, 0, '2026-10-27')).toBeNull();
    expect(raffleClosedReason(null, 5, 5, '2026-10-27')).toBe('full');
    expect(raffleClosedReason(null, 5, 4, '2026-10-27')).toBeNull();
    expect(raffleClosedReason(null, 0, 999, '2026-10-27')).toBeNull(); // 0 = unlimited
    expect(raffleClosedReason('2026-10-01', 5, 5, '2026-10-27')).toBe('period');
  });
  it('normalizePhone / validEmail', () => {
    expect(normalizePhone('010-1234-5678')).toBe('01012345678');
    expect(normalizePhone('02-123-4567')).toBe('021234567'.length >= 10 ? '021234567' : null);
    expect(normalizePhone('123')).toBeNull();
    expect(normalizePhone('0101234567890')).toBeNull();
    expect(validEmail('')).toBe(true);
    expect(validEmail('a@b.co')).toBe(true);
    expect(validEmail('a@b')).toBe(false);
  });
  it('adminWants: defaults when no list, explicit list otherwise', () => {
    expect(adminWants('{}', 'consultation_new')).toBe(true);
    expect(adminWants(null, 'consultation_new')).toBe(true);
    expect(adminWants('not json', 'consultation_new')).toBe(true);
    expect(adminWants('{"notifications":["review_new"]}', 'consultation_new')).toBe(false);
    expect(adminWants('{"notifications":["consultation_new"]}', 'consultation_new')).toBe(true);
    expect(adminWants('{"notifications":"x"}', 'consultation_new')).toBe(false);
    expect(adminWants('{}', 'login_fail')).toBe(false);
  });
});

describe('events API (mock DB)', () => {
  let app: INestApplication;
  const sql: { q: string; v?: unknown[] }[] = [];
  let entries: { event: number; member: string }[] = [];
  const realFetch = global.fetch;
  const ev = (o: Record<string, unknown>) => ({
    id: 5, title: '보톡스 특가', subtitle: '부제', description: '설명', image_url: 'uploads/e.jpg', hospital_id: 9, hospital_name: '플래티넘의원',
    category: '보톡스', original_price: 100000, discount_price: 59000, discount_rate: 41, start_date: '2026-10-01', end_date: '2099-12-31',
    has_raffle: 1, raffle_type: 'random', raffle_prize: '상품권', raffle_winner_count: 3, max_entries: 2, raffle_end_date: '2099-12-31', is_active: 1, ...o,
  });
  let eventRow: Record<string, unknown> = ev({});
  let failUserNotification = false;
  let adSettings: { setting_key: string; setting_value: string }[] = [];
  const db = {
    query: jest.fn(async (q: string, v?: unknown[]) => {
      sql.push({ q, v });
      if (q.includes('FROM gangnamunni_events e LEFT JOIN')) return [[{ ...eventRow, hospital_name: '플래티넘의원' }]];
      if (q.includes('FROM gangnamunni_settings')) return [adSettings];
      if (q.includes('FROM gangnamunni_ads a')) return [[
        { id: 11, title: '배너', subtitle: null, image_url: 'uploads/ad.jpg', link_url: 'detail.php?id=1' },
        { id: 12, title: '샘플', subtitle: 's', image_url: 'uploads/sample1.jpg', link_url: 'javascript:alert(1)' },
      ]];
      if (q.includes('SELECT DISTINCT category')) return [[{ category: '보톡스' }, { category: '' }, { category: '필러' }]];
      if (q.includes('SELECT * FROM gangnamunni_events')) return [v![0] === 5 ? [eventRow] : []];
      if (q.includes('FROM gangnamunni_event_images')) return [[{ image_url: 'uploads/e2.jpg', caption: 'c' }]];
      if (q.includes('SELECT id, title, has_raffle')) return [v![0] === 5 ? [eventRow] : []];
      if (q.includes('SHOW COLUMNS FROM gangnamunni_event_entries')) return [[{ Field: 'member_id' }]];
      if (q.includes('COUNT(*) AS cnt FROM gangnamunni_event_entries')) return [[{ cnt: entries.filter((e) => e.event === v![0]).length }]];
      if (q.includes('SELECT id FROM gangnamunni_event_entries')) return [entries.some((e) => e.event === v![0] && e.member === v![1]) ? [{ id: 1 }] : []];
      if (q.startsWith('INSERT INTO gangnamunni_event_entries')) {
        const [event, member] = v as [number, string];
        const max = Number(v![v!.length - 1]);
        const dup = entries.some((e) => e.event === event && e.member === member);
        const full = max > 0 && entries.filter((e) => e.event === event).length >= max;
        if (dup || full) return [{ affectedRows: 0, insertId: 0 }];
        entries.push({ event, member });
        return [{ affectedRows: 1, insertId: entries.length }];
      }
      if (q.includes('INSERT INTO gangnamunni_user_notifications')) { if (failUserNotification) throw new Error('denied'); return [{ affectedRows: 1 }]; }
      if (q.includes('FROM gangnamunni_admins')) return [[{ id: 1, permissions: '{}' }, { id: 2, permissions: '{"notifications":["review_new"]}' }]];
      if (q.includes('INSERT INTO gangnamunni_notifications')) return [{ affectedRows: 1 }];
      return [[]];
    }),
  };
  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42 } : c.includes('sid=lee') ? { id: 'lee', no: 43 } : c.includes('sid=park') ? { id: 'park', no: 44 } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { ...who, name: '이름', nickname: 'n', level: 2, phone: '010-1111-2222' } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: db }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AuthModule, EventsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => { sql.length = 0; entries = []; eventRow = ev({}); failUserNotification = false; adSettings = []; });
  const body = { name: '홍길동', phone: '010-1234-5678', email: 'a@b.co' };

  it('list: filters are parameterised, empty categories dropped, public cache header', async () => {
    const r = await request(app.getHttpServer()).get('/v1/events').query({ category: "보톡스' OR 1=1 --", filter: 'raffle' }).expect(200);
    expect(r.headers['cache-control']).toContain('s-maxage=30');
    expect(r.body.categories).toEqual(['보톡스', '필러']);
    expect(r.body.raffleOnly).toBe(true);
    expect(r.body.items[0]).toMatchObject({ id: 5, hasRaffle: true, hospitalName: '플래티넘의원', endDate: '2099-12-31', discountRate: 41 });
    const listSql = sql.find((s) => s.q.includes('LEFT JOIN'))!;
    expect(listSql.q).toContain('e.category = ?');
    expect(listSql.q).toContain('e.has_raffle = 1');
    expect(listSql.q).not.toContain('OR 1=1');
    expect(listSql.v).toEqual(["보톡스' OR 1=1 --"]);
  });

  it('list: page banner ads follow ad_banner.php (sample image → text, unsafe link neutralised, switch off, auto-scroll)', async () => {
    let r = await request(app.getHttpServer()).get('/v1/events').expect(200);
    expect(r.body.ads).toEqual([
      { id: 11, title: '배너', subtitle: null, imageUrl: 'uploads/ad.jpg', linkUrl: 'detail.php?id=1' },
      { id: 12, title: '샘플', subtitle: 's', imageUrl: null, linkUrl: '#' },
    ]);
    expect(r.body.adAutoScroll).toBe(true);
    const adSql = sql.find((s) => s.q.includes('FROM gangnamunni_ads a'))!;
    expect(adSql.q).toContain('gangnamunni_ad_slot_bookings');
    expect(adSql.v).toEqual(['events']);
    adSettings = [{ setting_key: 'ad_auto_scroll', setting_value: '0' }];
    r = await request(app.getHttpServer()).get('/v1/events').expect(200);
    expect(r.body.adAutoScroll).toBe(false);
    adSettings = [{ setting_key: 'ad_show_events', setting_value: '0' }];
    r = await request(app.getHttpServer()).get('/v1/events').expect(200);
    expect(r.body.ads).toEqual([]);
  });

  it('detail: anonymous sees raffle info, no-store, entered=false; member sees entered after entering', async () => {
    let r = await request(app.getHttpServer()).get('/v1/events/5').expect(200);
    expect(r.headers['cache-control']).toBe('private, no-store');
    expect(r.body).toMatchObject({ id: 5, categoryColor: '#7C5CFC', ended: false, hospitalId: 9 });
    expect(r.body.images).toEqual([{ imageUrl: 'uploads/e2.jpg', caption: 'c' }]);
    expect(r.body.raffle).toMatchObject({ typeLabel: '랜덤 추첨', winnerCount: 3, entryCount: 0, maxEntries: 2, closed: false, entered: false, prize: '상품권' });
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send(body).expect(201);
    r = await request(app.getHttpServer()).get('/v1/events/5').set('Cookie', 'sid=kim').expect(200);
    expect(r.body.raffle).toMatchObject({ entered: true, entryCount: 1 });
    r = await request(app.getHttpServer()).get('/v1/events/5').expect(200);
    expect(r.body.raffle.entered).toBe(false);
  });

  it('detail: 404 for unknown/inactive, 400 for a bad id; ended event; non-raffle has no raffle block', async () => {
    await request(app.getHttpServer()).get('/v1/events/404').expect(404);
    await request(app.getHttpServer()).get('/v1/events/abc').expect(400);
    eventRow = ev({ end_date: '2000-01-01', has_raffle: 0 });
    const r = await request(app.getHttpServer()).get('/v1/events/5').expect(200);
    expect(r.body.ended).toBe(true);
    expect(r.body.raffle).toBeNull();
  });

  it('detail: raffle closed by period / by capacity', async () => {
    eventRow = ev({ raffle_end_date: '2000-01-01' });
    let r = await request(app.getHttpServer()).get('/v1/events/5').expect(200);
    expect(r.body.raffle).toMatchObject({ closed: true, closedReason: 'period' });
    eventRow = ev({ max_entries: 1 });
    entries.push({ event: 5, member: 'x' });
    r = await request(app.getHttpServer()).get('/v1/events/5').expect(200);
    expect(r.body.raffle).toMatchObject({ closed: true, closedReason: 'full' });
  });

  it('enter: guards (401 anonymous, 403 header/origin) and validation (400)', async () => {
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).send(body).expect(401);
    await request(app.getHttpServer()).post('/v1/events/5/entries').set('Cookie', 'sid=kim').send(body).expect(403);
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').set('Origin', 'https://evil.example').send(body).expect(403);
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send({ ...body, name: '' }).expect(400);
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send({ ...body, phone: '123' }).expect(400);
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send({ ...body, email: 'nope' }).expect(400);
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send({ ...body, name: 'x'.repeat(51) }).expect(400);
    expect(sql.some((s) => s.q.startsWith('INSERT INTO gangnamunni_event_entries'))).toBe(false);
  });

  it('enter: stores digits + mb_id, notifies member and only subscribed admins; duplicate 409', async () => {
    const r = await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send(body).expect(201);
    expect(r.body).toMatchObject({ success: true, entryId: 1, message: '응모가 완료되었습니다!' });
    const ins = sql.find((s) => s.q.startsWith('INSERT INTO gangnamunni_event_entries'))!;
    expect(ins.v!.slice(0, 5)).toEqual([5, 'kim', '홍길동', '01012345678', 'a@b.co']);
    expect(sql.filter((s) => s.q.includes('INSERT INTO gangnamunni_user_notifications'))).toHaveLength(1);
    const adminRows = sql.filter((s) => s.q.includes('INSERT INTO gangnamunni_notifications'));
    expect(adminRows).toHaveLength(1);
    expect(adminRows[0].v![0]).toBe(1);
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send(body).expect(409);
  });

  it('enter: capacity (max_entries=2) closes for the third member; unlimited when 0', async () => {
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send(body).expect(201);
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=lee').send(body).expect(201);
    const r = await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=park').send(body).expect(400);
    expect(r.body.message).toBe('응모 정원이 마감되었습니다.');
    eventRow = ev({ max_entries: 0 });
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=park').send(body).expect(201);
  });

  it('enter: concurrent requests for the last seat admit exactly one', async () => {
    eventRow = ev({ max_entries: 1 });
    const res = await Promise.all(['kim', 'lee', 'park'].map((m) => request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', `sid=${m}`).send(body)));
    expect(res.map((x) => x.status).sort()).toEqual([201, 400, 400]);
    expect(entries).toHaveLength(1);
  });

  it('enter: closed period, inactive, no-raffle, unknown event', async () => {
    eventRow = ev({ raffle_end_date: '2000-01-01' });
    let r = await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send(body).expect(400);
    expect(r.body.message).toBe('응모 기간이 종료되었습니다.');
    eventRow = ev({ is_active: 0 });
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send(body).expect(400);
    eventRow = ev({ has_raffle: 0 });
    r = await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send(body).expect(400);
    expect(r.body.message).toBe('이 이벤트는 응모 기능이 없습니다.');
    await request(app.getHttpServer()).post('/v1/events/404/entries').set(XHR).set('Cookie', 'sid=kim').send(body).expect(404);
  });

  it('enter: a failing notification never fails the entry', async () => {
    failUserNotification = true;
    await request(app.getHttpServer()).post('/v1/events/5/entries').set(XHR).set('Cookie', 'sid=kim').send(body).expect(201);
    expect(entries).toHaveLength(1);
  });
});
