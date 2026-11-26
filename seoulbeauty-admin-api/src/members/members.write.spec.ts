import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { MembersModule } from './members.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };

describe('admin API: members write (e-mail only)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: { sql: string; values: unknown[] }[] = [];
  const events: string[] = [];
  let members: Record<number, { mb_no: number; mb_id: string; mb_email: string; mb_leave_date?: string }>;
  let rwEmpty = false;
  let rwError: (Error & { code?: string; errno?: number }) | null = null;
  let auditFails = false;
  let commits = 0;
  let rollbacks = 0;

  const roStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      if (sql.includes('WHERE mb_no = ?')) {
        const m = members[values[0] as number];
        return [m ? [{ ...m, mb_name: '김서연', mb_leave_date: '', mb_intercept_date: '' }] : []];
      }
      throw new Error(`unexpected read-only sql: ${sql}`);
    }),
  };
  const conn = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      rwCalls.push({ sql, values });
      events.push(sql.split(' ').slice(0, 3).join(' '));
      if (rwError) throw rwError;
      if (sql.startsWith('SELECT mb_no, mb_id, mb_email, mb_leave_date FROM g5_member WHERE mb_no = ?')) return [members[values[0] as number] ? [members[values[0] as number]] : []];
      if (sql.startsWith('SELECT mb_no FROM g5_member WHERE LOWER(mb_email)')) {
        return [Object.values(members).filter((m) => m.mb_email.toLowerCase() === values[0] && m.mb_no !== values[1]).map((m) => ({ mb_no: m.mb_no }))];
      }
      if (sql.startsWith('UPDATE g5_member SET mb_email = ? WHERE mb_no = ?')) {
        members[values[1] as number].mb_email = values[0] as string;
        return [{ affectedRows: 1 }];
      }
      if (sql.startsWith('INSERT INTO gangnamunni_member_audit')) {
        if (auditFails) throw Object.assign(new Error('table missing'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
        return [{ affectedRows: 1 }];
      }
      throw new Error(`unexpected write sql: ${sql}`);
    }),
    beginTransaction: jest.fn(async () => { events.push('BEGIN'); }),
    commit: jest.fn(async () => { commits += 1; events.push('COMMIT'); }),
    rollback: jest.fn(async () => { rollbacks += 1; events.push('ROLLBACK'); }),
    release: jest.fn(),
  };
  const rwPool = { query: jest.fn(), getConnection: jest.fn(async () => conn) };

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
    process.env.ADMIN_MEMBERS_WRITE = '1';
    members = { 5: { mb_no: 5, mb_id: 'kim01', mb_email: 'Old@Example.kr' }, 6: { mb_no: 6, mb_id: 'lee02', mb_email: 'taken@example.kr' }, 8: { mb_no: 8, mb_id: 'gone08', mb_email: 'gone@example.kr', mb_leave_date: '2026-10-01' } };
    rwPool.getConnection.mockClear();
    rwCalls.length = 0; events.length = 0; rwEmpty = false; rwError = null; auditFails = false; commits = 0; rollbacks = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: { get: () => (rwEmpty ? null : rwPool) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, MembersModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_MEMBERS_ENABLED', 'ADMIN_MEMBERS_WRITE']) delete process.env[k];
  });

  const patch = (p: string, body: unknown, cookie: string | null = SUPER, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).patch(p).set(headers);
    if (cookie) r = r.set('Cookie', cookie);
    return r.send(body as any);
  };

  it('answers 404 while either switch is off, then 403 without the CSRF marker, 401 without a session, 403 for hospital accounts', async () => {
    process.env.ADMIN_MEMBERS_WRITE = '0';
    expect((await patch('/v1/members/5', { email: 'n@e.kr' })).status).toBe(404);
    process.env.ADMIN_MEMBERS_WRITE = '1';
    process.env.ADMIN_MEMBERS_ENABLED = '0';
    expect((await patch('/v1/members/5', { email: 'n@e.kr' })).status).toBe(404);
    process.env.ADMIN_MEMBERS_ENABLED = '1';
    const noMarker = await patch('/v1/members/5', { email: 'n@e.kr' }, SUPER, { 'Content-Type': 'application/json' });
    expect([noMarker.status, noMarker.body]).toEqual([403, { error: 'csrf' }]);
    expect((await patch('/v1/members/5', { email: 'n@e.kr' }, null)).status).toBe(401);
    expect((await patch('/v1/members/5', { email: 'n@e.kr' }, HOSPITAL)).status).toBe(403);
    expect(rwCalls).toHaveLength(0);
    expect(rwPool.getConnection).not.toHaveBeenCalled();
  });

  it('changes the e-mail in one transaction and writes the audit row with who, whom, old and new', async () => {
    const r = await patch('/v1/members/5', { email: ' New@Example.KR ' });
    expect(r.status).toBe(200);
    expect(r.body).toMatchObject({ no: 5, id: 'kim01' });
    expect(members[5].mb_email).toBe('new@example.kr');
    expect(events).toEqual(['BEGIN', 'SELECT mb_no, mb_id,', 'SELECT mb_no FROM', 'UPDATE g5_member SET', 'INSERT INTO gangnamunni_member_audit', 'COMMIT']);
    expect(rwCalls.find((c) => c.sql.startsWith('SELECT mb_no, mb_id'))!.sql).toContain('FOR UPDATE');
    const audit = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_member_audit'))!;
    expect(audit.values.slice(0, 8)).toEqual([7, 'boss', 'super_admin', 5, 'kim01', 'email_change', 'Old@Example.kr', 'new@example.kr']);
    expect(rwCalls.map((c) => c.sql).filter((q) => q.startsWith('UPDATE'))).toEqual(['UPDATE g5_member SET mb_email = ? WHERE mb_no = ?']);
  });

  it('writes nothing for the same address in another letter case', async () => {
    const r = await patch('/v1/members/5', { email: 'OLD@example.KR' });
    expect(r.status).toBe(200);
    expect(rwCalls.some((c) => c.sql.startsWith('UPDATE') || c.sql.startsWith('INSERT'))).toBe(false);
  });

  it('refuses to change the e-mail of a member who has left (409) without writing anything', async () => {
    const r = await patch('/v1/members/8', { email: 'new@example.kr' });
    expect([r.status, r.body]).toEqual([409, { error: 'member_left' }]);
    expect(members[8].mb_email).toBe('gone@example.kr');
    expect(rwCalls.some((c) => c.sql.startsWith('UPDATE') || c.sql.startsWith('INSERT'))).toBe(false);
    expect(commits).toBe(0);
  });

  it('refuses an e-mail another member has (409) and rolls back', async () => {
    const r = await patch('/v1/members/5', { email: 'Taken@example.kr' });
    expect([r.status, r.body]).toEqual([409, { error: 'email_taken' }]);
    expect(members[5].mb_email).toBe('Old@Example.kr');
    expect(rollbacks).toBe(1);
    expect(commits).toBe(0);
  });

  it('refuses every other field with 400 and never reaches the database', async () => {
    for (const body of [{ email: 'n@e.kr', name: 'x' }, { name: 'x' }, { email: 'n@e.kr', mb_level: 10 }, { email: 'n@e.kr', mb_password: 'x' }, { email: 'bad' }, { email: '' }, []]) {
      const r = await patch('/v1/members/5', body);
      expect(r.status).toBe(400);
      expect(r.body.error).toBe('invalid');
    }
    // A bare string is not JSON the body parser accepts: still a 400, never a write.
    expect((await patch('/v1/members/5', '"n@e.kr"')).status).toBe(400);
    expect(rwPool.getConnection).not.toHaveBeenCalled();
  });

  it('answers 404 for unknown or malformed ids', async () => {
    expect((await patch('/v1/members/99', { email: 'n@e.kr' })).status).toBe(404);
    for (const bad of ['0', 'abc', '5%20OR%201=1']) expect((await patch(`/v1/members/${bad}`, { email: 'n@e.kr' })).status).toBe(404);
    expect(rollbacks).toBe(1);
  });

  it('does not change the e-mail when the audit row cannot be written (503, rolled back)', async () => {
    auditFails = true;
    const r = await patch('/v1/members/5', { email: 'new@example.kr' });
    expect([r.status, r.body]).toEqual([503, { error: 'write_unavailable' }]);
    expect(rollbacks).toBe(1);
    expect(commits).toBe(0);
  });

  it('answers 503 write_unavailable without a write user and 503 busy on a lock timeout', async () => {
    rwEmpty = true;
    expect([(await patch('/v1/members/5', { email: 'n@e.kr' })).status]).toEqual([503]);
    rwEmpty = false;
    rwError = Object.assign(new Error('lock wait'), { code: 'ER_LOCK_WAIT_TIMEOUT', errno: 1205 });
    const r = await patch('/v1/members/5', { email: 'n@e.kr' });
    expect([r.status, r.body]).toEqual([503, { error: 'busy' }]);
  });
});
