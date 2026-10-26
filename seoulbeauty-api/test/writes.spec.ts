import { Test } from '@nestjs/testing';
import { INestApplication } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { WishlistModule } from '../src/wishlist/wishlist.module';
import { ConsultationsModule } from '../src/consultations/consultations.module';

const XHR = { 'X-Requested-With': 'XMLHttpRequest' };

describe('writes: wishlist + consultations', () => {
  let app: INestApplication;
  const sql: { q: string; v?: unknown[] }[] = [];
  let wish = new Set<string>();
  const realFetch = global.fetch;
  const db = {
    query: jest.fn(async (q: string, v?: unknown[]) => {
      sql.push({ q, v });
      if (q.includes('SELECT id FROM gangnamunni_wishlist')) return [wish.has(`${v![0]}:${v![1]}`) ? [{ id: 1 }] : []];
      if (q.includes('INSERT IGNORE INTO gangnamunni_wishlist')) { wish.add(`${v![0]}:${v![1]}`); return [{ affectedRows: 1 }]; }
      if (q.includes('DELETE FROM gangnamunni_wishlist')) { wish.delete(`${v![0]}:${v![1]}`); return [{ affectedRows: 1 }]; }
      if (q.includes('COUNT(*) AS cnt FROM gangnamunni_wishlist')) return [[{ cnt: wish.size }]];
      if (q.includes('SELECT name FROM gangnamunni_hospitals')) return [v![0] === 8216 ? [{ name: '플래티넘의원' }] : []];
      if (q.includes('INSERT INTO gangnamunni_consultations')) return [{ insertId: 77 }];
      if (q.includes("SHOW COLUMNS FROM gangnamunni_consultations")) return [[{ Field: 'mb_id' }]];
      if (q.includes('UPDATE gangnamunni_consultations')) return [{ affectedRows: 1 }];
      return [[]];
    }),
  };
  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c = init?.headers?.cookie || '';
      const ok = c.includes('sid=member');
      return new Response(JSON.stringify(ok ? { loggedIn: true, user: { id: 'kim', no: 42, name: '김', nickname: 'k', level: 2, phone: '010-1234-5678' } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    const { Global, Module } = await import('@nestjs/common');
    @Global() @Module({ providers: [{ provide: DB, useValue: db }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AuthModule, WishlistModule, ConsultationsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => { sql.length = 0; wish = new Set(); });

  it('wishlist check: anonymous false + count; member true after toggle', async () => {
    let r = await request(app.getHttpServer()).get('/v1/wishlist/hospitals/8216').expect(200);
    expect(r.body).toEqual({ wishlisted: false, count: 0 });
    r = await request(app.getHttpServer()).post('/v1/wishlist/toggle').set(XHR).set('Cookie', 'sid=member').send({ targetId: 8216 }).expect(201);
    expect(r.body).toMatchObject({ wishlisted: true, count: 1 });
    expect(sql.find((s) => s.q.includes('INSERT IGNORE'))!.v).toEqual([42, 8216]); // member_id = mb_no
    r = await request(app.getHttpServer()).get('/v1/wishlist/hospitals/8216').set('Cookie', 'sid=member').expect(200);
    expect(r.body).toEqual({ wishlisted: true, count: 1 });
    r = await request(app.getHttpServer()).post('/v1/wishlist/toggle').set(XHR).set('Cookie', 'sid=member').send({ targetId: 8216 }).expect(201);
    expect(r.body).toMatchObject({ wishlisted: false, count: 0 });
  });

  it('toggle: anonymous 401, missing header 403, bad target 400', async () => {
    await request(app.getHttpServer()).post('/v1/wishlist/toggle').set(XHR).send({ targetId: 8216 }).expect(401);
    await request(app.getHttpServer()).post('/v1/wishlist/toggle').set('Cookie', 'sid=member').send({ targetId: 8216 }).expect(403);
    await request(app.getHttpServer()).post('/v1/wishlist/toggle').set(XHR).set('Cookie', 'sid=member').send({ targetId: 'x' }).expect(400);
    await request(app.getHttpServer()).post('/v1/wishlist/toggle').set(XHR).set('Cookie', 'sid=member').set('Origin', 'https://evil.example').send({ targetId: 8216 }).expect(403);
  });

  it('consultation: guest ok, member linked, validation, rate limit', async () => {
    const body = { hospitalId: 8216, type: 'doctor', name: '홍길동', phone: '010-1111-2222', message: '[관심 시술] 보톡스' };
    let r = await request(app.getHttpServer()).post('/v1/consultations').set(XHR).send(body).expect(201);
    expect(r.body).toMatchObject({ success: true, id: 77 });
    expect(sql.some((s) => s.q.includes('UPDATE gangnamunni_consultations'))).toBe(false); // guest: no mb_id link
    sql.length = 0;
    r = await request(app.getHttpServer()).post('/v1/consultations').set(XHR).set('Cookie', 'sid=member').send(body).expect(201);
    expect(sql.find((s) => s.q.includes('UPDATE gangnamunni_consultations'))!.v).toEqual(['kim', 77]);
    const ins = sql.find((s) => s.q.includes('INSERT INTO gangnamunni_consultations'))!.v!;
    expect(ins.slice(0, 3)).toEqual([8216, '플래티넘의원', 'doctor']);
    await request(app.getHttpServer()).post('/v1/consultations').set(XHR).send({ ...body, phone: 'abc' }).expect(400);
    await request(app.getHttpServer()).post('/v1/consultations').set(XHR).send({ ...body, hospitalId: 1 }).expect(400);
    await request(app.getHttpServer()).post('/v1/consultations').send(body).expect(403);
    // 5 per 10 min per guest key: used 2 so far (first ok + unknown hospital counts; 400/403 before the check don't)
    for (let i = 0; i < 3; i++) await request(app.getHttpServer()).post('/v1/consultations').set(XHR).send(body).expect(201);
    await request(app.getHttpServer()).post('/v1/consultations').set(XHR).send(body).expect(429);
  });
});
