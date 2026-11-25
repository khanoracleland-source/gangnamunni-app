import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { ConsultationsModule } from './consultations.module';
import { DB, DB_RW } from '../db/db.module';
import { ConsultationNotifyService } from './consultations.notify.service';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const OTHER_HOSPITAL = 'PHPSESSID=hospital99';
const NOHOSP = 'PHPSESSID=nohospital';
const VIEWER = 'PHPSESSID=viewer';
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
type Call = { sql: string; values: unknown[] };

describe('admin API: consultations (write)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  let status = 'pending';
  let failWith: unknown = null;
  let failEvent: unknown = null;
  const later = jest.fn();

  /** The row is found only when the statement's hospital part lets it through, like the real WHERE. */
  const visible = (sql: string, values: unknown[]) => !sql.includes('1 = 0') && (!sql.includes('IN (?)') || values.includes(12));
  const rwQuery = async (sql: string, values: unknown[] = []) => {
    rwCalls.push({ sql, values });
    if (failWith) throw failWith;
    if (sql.startsWith('INSERT INTO gangnamunni_consultation_events') && failEvent) throw failEvent;
    if (sql.startsWith('SELECT id, status FROM gangnamunni_consultations')) return [visible(sql, values) && values[0] === 9 ? [{ id: 9, status }] : []];
    if (sql.startsWith('SELECT id FROM gangnamunni_consultations')) return [visible(sql, values) && values[0] === 9 ? [{ id: 9 }] : []];
    return [{ affectedRows: 1 }];
  };
  const conn = { query: jest.fn(rwQuery), beginTransaction: jest.fn(async () => undefined), commit: jest.fn(async () => undefined), rollback: jest.fn(async () => undefined), release: jest.fn() };
  const rwStub = { get: () => ({ query: jest.fn(rwQuery), getConnection: async () => conn }) };
  const roStub = { query: jest.fn(async () => [[]]) };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (b: unknown) => new Response(JSON.stringify(b), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      if (cookie.includes('hospital99')) return json({ loggedIn: true, admin: admin('hospital_admin', 99) });
      if (cookie.includes('nohospital')) return json({ loggedIn: true, admin: admin('hospital_admin', null) });
      if (cookie.includes('viewer')) return json({ loggedIn: true, admin: admin('viewer', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_CONSULTATIONS_ENABLED = '1';
    process.env.ADMIN_CONSULTATIONS_WRITE = '1';
    rwCalls.length = 0; conn.rollback.mockClear(); conn.commit.mockClear(); conn.release.mockClear(); later.mockClear();
    status = 'pending'; failWith = null; failEvent = null;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, ConsultationsModule] }).overrideProvider(ConsultationNotifyService).useValue({ later }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_CONSULTATIONS_ENABLED', 'ADMIN_CONSULTATIONS_WRITE']) delete process.env[k];
  });

  const post = (path: string, body: unknown, cookie: string = SUPER, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post(path);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r.send(body as object);
  };
  const updates = () => rwCalls.filter((c) => c.sql.startsWith('UPDATE'));

  it('guard order: switch (404) -> CSRF (403) -> session (401) -> role (403); nothing is written before all pass', async () => {
    process.env.ADMIN_CONSULTATIONS_WRITE = '0';
    expect((await post('/v1/consultations/9/status', { status: 'approved' })).status).toBe(404);
    expect((await post('/v1/consultations/9/message', { message: 'a' })).status).toBe(404);
    process.env.ADMIN_CONSULTATIONS_WRITE = '1';
    process.env.ADMIN_CONSULTATIONS_ENABLED = '0';
    expect((await post('/v1/consultations/9/status', { status: 'approved' })).status).toBe(404);
    process.env.ADMIN_CONSULTATIONS_ENABLED = '1';
    expect((await post('/v1/consultations/9/status', { status: 'approved' }, SUPER, { 'Content-Type': 'application/json' })).status).toBe(403);
    expect((await post('/v1/consultations/9/status', { status: 'approved' }, SUPER, { ...HEADERS, Origin: 'https://evil.example' })).body).toEqual({ error: 'csrf' });
    expect((await post('/v1/consultations/9/status', { status: 'approved' }, '')).status).toBe(401);
    expect((await post('/v1/consultations/9/status', { status: 'approved' }, VIEWER)).status).toBe(403);
    expect((await post('/v1/consultations/9/message', { message: 'a' }, VIEWER)).status).toBe(403);
    expect(rwCalls).toHaveLength(0);
  });

  it('the super admin moves an application to each allowed status, inside one transaction', async () => {
    for (const st of ['approved', 'completed', 'rejected', 'contact_failed']) {
      rwCalls.length = 0; conn.commit.mockClear();
      const r = await post('/v1/consultations/9/status', { status: st });
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ id: 9, status: st, changed: true });
      expect(updates()).toHaveLength(1);
      expect(updates()[0].values.slice(0, 2)).toEqual([st, 9]);
      expect(conn.commit).toHaveBeenCalledTimes(1);
      expect(rwCalls[0].sql).toContain('FOR UPDATE');
    }
  });

  it('asking for the status it already has changes nothing', async () => {
    status = 'approved';
    const r = await post('/v1/consultations/9/status', { status: 'approved' });
    expect(r.body).toEqual({ id: 9, status: 'approved', changed: false });
    expect(updates()).toHaveLength(0);
  });

  it('a bad status or body is a 400 and nothing is read or written', async () => {
    for (const b of [{ status: 'pending' }, { status: 'nope' }, { status: 5 }, {}, [], 'approved']) {
      expect((await post('/v1/consultations/9/status', b)).status).toBe(400);
    }
    expect(rwCalls).toHaveLength(0);
  });

  it('a hospital account changes only its own: another hospital, an unbound account, an unknown or malformed id are the same 404 and no UPDATE runs', async () => {
    expect((await post('/v1/consultations/9/status', { status: 'approved' }, HOSPITAL)).status).toBe(200);
    expect(updates()[0].sql).toContain('hospital_id IN (?)');
    for (const [url, who] of [['/v1/consultations/9/status', OTHER_HOSPITAL], ['/v1/consultations/9/status', NOHOSP], ['/v1/consultations/77/status', SUPER], ['/v1/consultations/9x/status', SUPER], ['/v1/consultations/0/status', SUPER]] as const) {
      rwCalls.length = 0; conn.rollback.mockClear();
      const r = await post(url, { status: 'approved' }, who);
      expect(r.status).toBe(404);
      expect(r.body).toEqual({ error: 'not_found' });
      expect(updates()).toHaveLength(0);
    }
    conn.rollback.mockClear();
    await post('/v1/consultations/9/status', { status: 'approved' }, OTHER_HOSPITAL);
    expect(conn.rollback).toHaveBeenCalled();
  });

  it('the hospital message is saved cut to scope; an empty one clears it; the time is set by the database', async () => {
    const r = await post('/v1/consultations/9/message', { message: '  내일 오전에 연락드릴게요\r\n감사합니다\u0000  ' });
    expect(r.status).toBe(200);
    expect(r.body).toEqual({ id: 9, hospitalMessage: '내일 오전에 연락드릴게요\n감사합니다' });
    expect(updates()[0].sql).toContain("hospital_message_at = IF(? = '', NULL, NOW())");
    expect(updates()[0].values.slice(0, 3)).toEqual(['내일 오전에 연락드릴게요\n감사합니다', '내일 오전에 연락드릴게요\n감사합니다', 9]);
    rwCalls.length = 0;
    expect((await post('/v1/consultations/9/message', { message: '   ' })).body).toEqual({ id: 9, hospitalMessage: '' });
    expect(updates()[0].values.slice(0, 2)).toEqual(['', '']);
  });

  it('a message of 1000 characters is kept, 1001 is refused; a non-text message is a 400', async () => {
    expect((await post('/v1/consultations/9/message', { message: '가'.repeat(1000) })).status).toBe(200);
    rwCalls.length = 0;
    const long = await post('/v1/consultations/9/message', { message: '가'.repeat(1001) });
    expect(long.status).toBe(400);
    expect(long.body).toMatchObject({ error: 'too_long', field: 'message' });
    for (const b of [{}, { message: 5 }, { message: null }, []]) expect((await post('/v1/consultations/9/message', b)).status).toBe(400);
    expect(rwCalls).toHaveLength(0);
  });

  it('another hospital cannot write a message either', async () => {
    expect((await post('/v1/consultations/9/message', { message: 'x' }, OTHER_HOSPITAL)).status).toBe(404);
    expect((await post('/v1/consultations/9/message', { message: 'x' }, NOHOSP)).status).toBe(404);
    expect(updates()).toHaveLength(0);
  });

  it('no write right on the database (or the history columns missing) is a 503 with a name, rolled back', async () => {
    failWith = Object.assign(new Error('UPDATE command denied'), { code: 'ER_COLUMNACCESS_DENIED_ERROR', errno: 1143 });
    const r = await post('/v1/consultations/9/message', { message: 'x' });
    expect(r.status).toBe(503);
    expect(r.body).toEqual({ error: 'write_unavailable' });
    expect(conn.rollback).toHaveBeenCalled();
    expect((await post('/v1/consultations/9/status', { status: 'approved' })).status).toBe(503);
  });

  describe('progress record and member notices', () => {
    const events = () => rwCalls.filter((c) => c.sql.startsWith('INSERT INTO gangnamunni_consultation_events'));

    it('completed and contact failed write their record in the same transaction; approved and rejected write none', async () => {
      await post('/v1/consultations/9/status', { status: 'completed' });
      expect(events().map((c) => c.values)).toEqual([[9, 'completed']]);
      rwCalls.length = 0;
      await post('/v1/consultations/9/status', { status: 'contact_failed' });
      expect(events().map((c) => c.values)).toEqual([[9, 'no_answer']]);
      expect(rwCalls.map((c) => c.sql.split(' ')[0])).toEqual(['SELECT', 'UPDATE', 'INSERT']);
      rwCalls.length = 0;
      for (const st of ['approved', 'rejected']) await post('/v1/consultations/9/status', { status: st });
      expect(events()).toHaveLength(0);
    });

    it('a database without that table (or the right to write it) still saves the status; any other problem rolls everything back', async () => {
      failEvent = Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
      const ok = await post('/v1/consultations/9/status', { status: 'completed' });
      expect(ok.status).toBe(200);
      expect(updates()).toHaveLength(1);
      expect(conn.commit).toHaveBeenCalled();
      later.mockClear(); conn.commit.mockClear();
      failEvent = Object.assign(new Error('deadlock'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      expect((await post('/v1/consultations/9/status', { status: 'completed' })).status).toBe(500);
      expect(conn.rollback).toHaveBeenCalled();
      expect(conn.commit).not.toHaveBeenCalled();
    });

    it('the member is told only after a real change to approved or contact failed', async () => {
      await post('/v1/consultations/9/status', { status: 'approved' });
      expect(later).toHaveBeenLastCalledWith(9, 'approved');
      await post('/v1/consultations/9/status', { status: 'contact_failed' });
      expect(later).toHaveBeenLastCalledWith(9, 'contact_failed');
      later.mockClear();
      await post('/v1/consultations/9/status', { status: 'completed' });
      await post('/v1/consultations/9/status', { status: 'rejected' });
      status = 'approved';
      await post('/v1/consultations/9/status', { status: 'approved' });
      await post('/v1/consultations/9/status', { status: 'approved' }, OTHER_HOSPITAL);
      await post('/v1/consultations/9/message', { message: 'x' });
      failWith = Object.assign(new Error('denied'), { code: 'ER_COLUMNACCESS_DENIED_ERROR', errno: 1143 });
      await post('/v1/consultations/9/status', { status: 'contact_failed' });
      expect(later).not.toHaveBeenCalled();
    });
  });

  it('/v1/me tells the page whether writing is on', async () => {
    const me = (cookie: string) => request(app.getHttpServer()).get('/v1/me').set('Cookie', cookie);
    expect((await me(SUPER)).body.features.consultationsWrite).toBe(true);
    process.env.ADMIN_CONSULTATIONS_WRITE = '0';
    expect((await me(SUPER)).body.features.consultationsWrite).toBe(false);
  });
});
