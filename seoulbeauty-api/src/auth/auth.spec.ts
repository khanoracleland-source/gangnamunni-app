import { Test } from '@nestjs/testing';
import { INestApplication } from '@nestjs/common';
import request from 'supertest';
import { AuthModule } from './auth.module';

describe('/v1/me via PHP session authority', () => {
  let app: INestApplication;
  const calls: string[] = [];
  const realFetch = global.fetch;
  beforeAll(async () => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      calls.push(cookie);
      if (cookie.includes('boom')) return new Response('oops', { status: 500 });
      const ok = cookie.includes('G5PHPSESSID=good');
      return new Response(JSON.stringify(ok ? { loggedIn: true, user: { id: 'u1', name: '홍길동', nickname: '길동', level: 2 } } : { loggedIn: false, user: null }), { status: 200, headers: { 'content-type': 'application/json' } });
    }) as any;
    const mod = await Test.createTestingModule({ imports: [AuthModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });

  it('no cookie -> anonymous without calling PHP', async () => {
    const r = await request(app.getHttpServer()).get('/v1/me').expect(200);
    expect(r.body).toEqual({ loggedIn: false, user: null });
    expect(calls).toHaveLength(0);
    expect(r.headers['cache-control']).toContain('no-store');
  });
  it('forwards cookie, returns user, caches per cookie', async () => {
    const r = await request(app.getHttpServer()).get('/v1/me').set('Cookie', 'G5PHPSESSID=good').expect(200);
    expect(r.body.user).toMatchObject({ id: 'u1', nickname: '길동' });
    await request(app.getHttpServer()).get('/v1/me').set('Cookie', 'G5PHPSESSID=good').expect(200);
    expect(calls.filter((c) => c === 'G5PHPSESSID=good')).toHaveLength(1);
  });
  it('fresh lookup bypasses the cache', async () => {
    const { AuthService } = await import('./auth.service');
    const svc = new AuthService();
    await svc.fromCookie('G5PHPSESSID=good');
    const before = calls.length;
    await svc.fromCookie('G5PHPSESSID=good', true);
    expect(calls.length).toBe(before + 1);
  });
  it('php error -> anonymous (fail closed), not cached', async () => {
    await request(app.getHttpServer()).get('/v1/me').set('Cookie', 'x=boom').expect(200).expect({ loggedIn: false, user: null });
    await request(app.getHttpServer()).get('/v1/me').set('Cookie', 'x=boom');
    expect(calls.filter((c) => c === 'x=boom')).toHaveLength(2);
  });
});
