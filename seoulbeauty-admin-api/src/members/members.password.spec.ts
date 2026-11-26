import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { MembersModule } from './members.module';
import { matchesHash } from './members.write.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const PASSWORD = 'Abcdef1!x';

describe('admin API: members password reset', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: { sql: string; values: unknown[] }[] = [];
  const events: string[] = [];
  let members: Record<number, { mb_no: number; mb_id: string; mb_leave_date: string; mb_intercept_date: string }>;
  let recentResets = 0;
  let ssoFails = false;
  let rwEmpty = false;
  let commits = 0;
  let rollbacks = 0;

  const conn = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      rwCalls.push({ sql, values });
      events.push(sql.split(' ').slice(0, 3).join(' '));
      if (sql.startsWith('SELECT mb_no, mb_id, mb_leave_date')) return [members[values[0] as number] ? [members[values[0] as number]] : []];
      if (sql.startsWith('SELECT COUNT(*) AS cnt FROM gangnamunni_member_audit')) return [[{ cnt: recentResets }]];
      if (sql.startsWith('UPDATE g5_member SET mb_password')) return [{ affectedRows: 1 }];
      if (sql.startsWith('DELETE FROM sso_tokens')) {
        if (ssoFails) throw Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
        return [{ affectedRows: 2 }];
      }
      if (sql.startsWith('INSERT INTO gangnamunni_member_audit')) return [{ affectedRows: 1 }];
      throw new Error(`unexpected write sql: ${sql}`);
    }),
    beginTransaction: jest.fn(async () => { events.push('BEGIN'); }),
    commit: jest.fn(async () => { commits += 1; events.push('COMMIT'); }),
    rollback: jest.fn(async () => { rollbacks += 1; events.push('ROLLBACK'); }),
    release: jest.fn(),
  };
  const rwPool = { query: jest.fn(), getConnection: jest.fn(async () => conn) };
  const roStub = { query: jest.fn(async () => { throw new Error('the read-only pool must not be used by a password reset'); }) };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_MEMBERS_ENABLED = '1';
    process.env.ADMIN_MEMBERS_PASSWORD = '1';
    delete process.env.ADMIN_MEMBERS_WRITE;
    members = {
      5: { mb_no: 5, mb_id: 'kim01', mb_leave_date: '', mb_intercept_date: '' },
      6: { mb_no: 6, mb_id: 'lee02', mb_leave_date: '', mb_intercept_date: '20261101' },
      7: { mb_no: 7, mb_id: 'park03', mb_leave_date: '20261001', mb_intercept_date: '' },
    };
    rwPool.getConnection.mockClear();
    rwCalls.length = 0; events.length = 0; recentResets = 0; ssoFails = false; rwEmpty = false; commits = 0; rollbacks = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: { get: () => (rwEmpty ? null : rwPool) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, MembersModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_MEMBERS_ENABLED', 'ADMIN_MEMBERS_PASSWORD', 'ADMIN_MEMBERS_WRITE']) delete process.env[k];
  });

  const reset = (p: string, body: unknown, cookie: string | null = SUPER, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post(p).set(headers);
    if (cookie) r = r.set('Cookie', cookie);
    return r.send(body as any);
  };

  it('is off without its own switch (the e-mail write switch does not turn it on), then CSRF, session and role', async () => {
    process.env.ADMIN_MEMBERS_PASSWORD = '0';
    process.env.ADMIN_MEMBERS_WRITE = '1';
    expect((await reset('/v1/members/5/password', { password: PASSWORD })).status).toBe(404);
    process.env.ADMIN_MEMBERS_PASSWORD = '1';
    process.env.ADMIN_MEMBERS_ENABLED = '0';
    expect((await reset('/v1/members/5/password', { password: PASSWORD })).status).toBe(404);
    process.env.ADMIN_MEMBERS_ENABLED = '1';
    const noMarker = await reset('/v1/members/5/password', { password: PASSWORD }, SUPER, { 'Content-Type': 'application/json' });
    expect([noMarker.status, noMarker.body]).toEqual([403, { error: 'csrf' }]);
    expect((await reset('/v1/members/5/password', { password: PASSWORD }, null)).status).toBe(401);
    expect((await reset('/v1/members/5/password', { password: PASSWORD }, HOSPITAL)).status).toBe(403);
    expect(rwPool.getConnection).not.toHaveBeenCalled();
  });

  it('sets the app-compatible hash, ends every sign-in, journals it, and never shows the password', async () => {
    const r = await reset('/v1/members/5/password', { password: PASSWORD });
    expect([r.status, r.body]).toEqual([200, { ok: true }]);
    expect(events).toEqual(['BEGIN', 'SELECT mb_no, mb_id,', 'SELECT COUNT(*) AS', 'UPDATE g5_member SET', 'DELETE FROM sso_tokens', 'INSERT INTO gangnamunni_member_audit', 'COMMIT']);
    expect(rwCalls[0].sql).toContain('FOR UPDATE');
    const update = rwCalls.find((c) => c.sql.startsWith('UPDATE g5_member'))!;
    expect(update.sql).toBe("UPDATE g5_member SET mb_password = ?, mb_cred_epoch = mb_cred_epoch + 1, mb_lost_certify = '' WHERE mb_no = ?");
    expect(matchesHash(PASSWORD, String(update.values[0]))).toBe(true);
    expect(update.values[1]).toBe(5);
    expect(rwCalls.find((c) => c.sql.startsWith('DELETE FROM sso_tokens'))!.values).toEqual(['kim01']);
    const audit = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_member_audit'))!;
    expect(audit.values.slice(0, 8)).toEqual([7, 'boss', 'super_admin', 5, 'kim01', 'password_reset', null, null]);
    // The plain password is only ever the input of the hash: it is in no query, no journal row and no answer.
    expect(JSON.stringify(rwCalls)).not.toContain(PASSWORD);
    expect(JSON.stringify(r.body)).not.toContain(PASSWORD);
  });

  it('allows a blocked member, refuses one who has left (409) without changing anything', async () => {
    const left = await reset('/v1/members/7/password', { password: PASSWORD });
    expect([left.status, left.body]).toEqual([409, { error: 'member_left' }]);
    expect(rwCalls.some((c) => c.sql.startsWith('UPDATE') || c.sql.startsWith('DELETE') || c.sql.startsWith('INSERT'))).toBe(false);
    expect(commits).toBe(0);
    const blocked = await reset('/v1/members/6/password', { password: PASSWORD });
    expect([blocked.status, blocked.body]).toEqual([200, { ok: true }]);
    expect(commits).toBe(1);
  });

  it('allows three resets per hour and refuses the fourth (429)', async () => {
    recentResets = 2;
    expect((await reset('/v1/members/5/password', { password: PASSWORD })).status).toBe(200);
    recentResets = 3;
    rwCalls.length = 0;
    const r = await reset('/v1/members/5/password', { password: PASSWORD });
    expect([r.status, r.body]).toEqual([429, { error: 'too_many' }]);
    expect(rwCalls.some((c) => c.sql.startsWith('UPDATE'))).toBe(false);
    expect(rollbacks).toBe(1);
    expect(rwCalls.find((c) => c.sql.startsWith('SELECT COUNT(*)'))!.sql).toContain('INTERVAL 1 HOUR');
  });

  it('rolls everything back when the sign-ins cannot be ended (the password is not changed either)', async () => {
    ssoFails = true;
    const r = await reset('/v1/members/5/password', { password: PASSWORD });
    expect([r.status, r.body]).toEqual([503, { error: 'write_unavailable' }]);
    expect(commits).toBe(0);
    expect(rollbacks).toBe(1);
  });

  it('refuses a weak password and every other key (400) before touching the database; unknown ids are 404', async () => {
    for (const body of [{ password: 'short1!' }, { password: 'nodigits!!' }, { password: PASSWORD, name: 'x' }, { name: 'x' }, { email: 'a@b.kr' }, {}, []]) {
      const r = await reset('/v1/members/5/password', body);
      expect([r.status, r.body.error]).toEqual([400, 'invalid']);
    }
    expect(rwPool.getConnection).not.toHaveBeenCalled();
    expect((await reset('/v1/members/99/password', { password: PASSWORD })).status).toBe(404);
    for (const bad of ['0', 'abc', '5%20OR%201=1']) expect((await reset(`/v1/members/${bad}/password`, { password: PASSWORD })).status).toBe(404);
  });

  it('answers 503 without a write user', async () => {
    rwEmpty = true;
    const r = await reset('/v1/members/5/password', { password: PASSWORD });
    expect([r.status, r.body]).toEqual([503, { error: 'write_unavailable' }]);
  });
});
