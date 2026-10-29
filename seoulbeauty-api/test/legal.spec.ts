import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { LegalModule } from '../src/legal/legal.module';
import { AGREEMENT_TYPES, plain } from '../src/legal/legal.logic';

describe('legal logic', () => {
  it('plain text normalises line breaks and null', () => {
    expect(plain('a\r\nb\rc')).toBe('a\nb\nc');
    expect(plain(null)).toBe('');
  });
  it('three documents', () => { expect([...AGREEMENT_TYPES]).toEqual(['terms_of_service', 'privacy_policy', 'community_guidelines']); });
});

describe('legal API', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[];
  let config: any; let failConfig: boolean;
  const realFetch = global.fetch;
  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const who = (init?.headers?.cookie || '').includes('sid=kim');
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { id: 'kim', no: 42, name: 'x', nickname: 'n', level: 2, phone: '' } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    const respond = (sql: string, args: any[]): any => {
      calls.push({ sql, args });
      if (/FROM g5_config/.test(sql)) { if (failConfig) throw new Error('boom'); return [[config]]; }
      return [{ affectedRows: 1 }];
    };
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args) } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, LegalModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.MYPAGE_POINTS_VISIBLE; await app.close(); });
  beforeEach(() => { calls = []; config = { cf_stipulation: '약관\r\n1조', cf_privacy: '개인정보' }; failConfig = false; });

  it('register terms: public, cacheable, plain text', async () => {
    const r = await request(app.getHttpServer()).get('/v1/legal/register-terms').expect(200);
    expect(r.headers['cache-control']).toContain('s-maxage=60');
    expect(r.body).toEqual({ terms: '약관\n1조', privacy: '개인정보' });
  });
  it('register terms: a DB failure answers empty texts, not 500', async () => {
    failConfig = true;
    expect((await request(app.getHttpServer()).get('/v1/legal/register-terms').expect(200)).body).toEqual({ terms: '', privacy: '' });
  });
  it('points policy follows MYPAGE_POINTS_VISIBLE', async () => {
    delete process.env.MYPAGE_POINTS_VISIBLE;
    expect((await request(app.getHttpServer()).get('/v1/legal/points-policy').expect(200)).body).toEqual({ visible: false });
    process.env.MYPAGE_POINTS_VISIBLE = 'true';
    expect((await request(app.getHttpServer()).get('/v1/legal/points-policy').expect(200)).body).toEqual({ visible: true });
  });
  it('agree: 401 for visitors, 403 without the header, nothing written', async () => {
    await request(app.getHttpServer()).post('/v1/legal/agree').set('X-Requested-With', 'XMLHttpRequest').expect(401);
    await request(app.getHttpServer()).post('/v1/legal/agree').set('Cookie', 'sid=kim').expect(403);
    expect(calls.length).toBe(0);
  });
  it('agree: one INSERT IGNORE per document with the member id and version', async () => {
    const r = await request(app.getHttpServer()).post('/v1/legal/agree').set({ Cookie: 'sid=kim', 'X-Requested-With': 'XMLHttpRequest', 'User-Agent': 'jest-ua' }).expect(201);
    expect(r.body).toEqual({ success: true });
    expect(r.headers['cache-control']).toContain('no-store');
    const ins = calls.filter((c) => /^INSERT IGNORE INTO gangnamunni_user_agreements/.test(c.sql));
    expect(ins.map((c) => c.args[1])).toEqual(['terms_of_service', 'privacy_policy', 'community_guidelines']);
    expect(ins.every((c) => c.args[0] === 'kim' && c.args[2] === '1.0' && c.args[4] === 'jest-ua')).toBe(true);
  });
});
