import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DB, DB_RW } from '../db/db.module';
import { MembersModule } from './members.module';
import { planStatus } from './members.write.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };

describe('planStatus', () => {
  it('accepts exactly one known status', () => {
    expect(planStatus({ status: 'blocked' })).toEqual({ ok: true, status: 'blocked' });
    expect(planStatus({ status: 'active' })).toEqual({ ok: true, status: 'active' });
    expect(planStatus({ status: 'left' })).toEqual({ ok: true, status: 'left' });
  });
  it('refuses everything else', () => {
    expect(planStatus(null)).toEqual({ ok: false, errors: { body: 'bad_format' } });
    expect(planStatus([])).toEqual({ ok: false, errors: { body: 'bad_format' } });
    expect(planStatus({})).toEqual({ ok: false, errors: { status: 'required' } });
    expect(planStatus({ status: 'deleted' })).toEqual({ ok: false, errors: { status: 'bad_format' } });
    expect(planStatus({ status: 1 })).toEqual({ ok: false, errors: { status: 'bad_format' } });
    expect(planStatus({ status: 'left', reason: 'x' })).toEqual({ ok: false, errors: { reason: 'not_allowed' } });
  });
});

describe('admin API: members status', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const rwCalls: { sql: string; values: unknown[] }[] = [];
  let members: Record<number, { mb_no: number; mb_id: string; mb_leave_date: string; mb_intercept_date: string }>;
  let rwEmpty = false;
  let commits = 0;
  let rollbacks = 0;

  const roStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      if (sql.includes('WHERE mb_no = ?')) {
        const m = members[values[0] as number];
        return [m ? [{ ...m, mb_name: '김서연', mb_email: 'a@b.kr' }] : []];
      }
      throw new Error(`unexpected read-only sql: ${sql}`);
    }),
  };
  const conn = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      rwCalls.push({ sql, values });
      if (sql.startsWith('SELECT mb_no, mb_id, mb_leave_date')) return [members[values[0] as number] ? [members[values[0] as number]] : []];
      if (sql.startsWith('UPDATE g5_member SET mb_intercept_date = ?')) { members[values[1] as number].mb_intercept_date = values[0] as string; return [{ affectedRows: 1 }]; }
      if (sql.startsWith('UPDATE g5_member SET mb_leave_date = ?')) { members[values[1] as number].mb_leave_date = values[0] as string; return [{ affectedRows: 1 }]; }
      if (sql.startsWith("UPDATE g5_member SET mb_intercept_date = ''")) { members[values[0] as number].mb_intercept_date = ''; return [{ affectedRows: 1 }]; }
      if (sql.startsWith('DELETE FROM sso_tokens')) return [{ affectedRows: 1 }];
      if (sql.startsWith('INSERT INTO gangnamunni_member_audit')) return [{ affectedRows: 1 }];
      throw new Error(`unexpected write sql: ${sql}`);
    }),
    beginTransaction: jest.fn(async () => undefined),
    commit: jest.fn(async () => { commits += 1; }),
    rollback: jest.fn(async () => { rollbacks += 1; }),
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
    process.env.ADMIN_MEMBERS_STATUS = '1';
    members = {
      5: { mb_no: 5, mb_id: 'kim01', mb_leave_date: '', mb_intercept_date: '' },
      6: { mb_no: 6, mb_id: 'lee02', mb_leave_date: '', mb_intercept_date: '20261101' },
      7: { mb_no: 7, mb_id: 'park03', mb_leave_date: '20261001', mb_intercept_date: '' },
    };
    rwPool.getConnection.mockClear();
    rwCalls.length = 0; rwEmpty = false; commits = 0; rollbacks = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: { get: () => (rwEmpty ? null : rwPool) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, MembersModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_MEMBERS_ENABLED', 'ADMIN_MEMBERS_STATUS']) delete process.env[k];
  });

  const change = (id: number, body: unknown, cookie: string | null = SUPER, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post(`/v1/members/${id}/status`).set(headers);
    if (cookie) r = r.set('Cookie', cookie);
    return r.send(body as any);
  };
  const audits = () => rwCalls.filter((c) => c.sql.startsWith('INSERT INTO gangnamunni_member_audit')).map((c) => c.values.slice(0, 8));

  it('is off without its switch, then CSRF, session and role', async () => {
    process.env.ADMIN_MEMBERS_STATUS = '0';
    expect((await change(5, { status: 'blocked' })).status).toBe(404);
    process.env.ADMIN_MEMBERS_STATUS = '1';
    process.env.ADMIN_MEMBERS_ENABLED = '0';
    expect((await change(5, { status: 'blocked' })).status).toBe(404);
    process.env.ADMIN_MEMBERS_ENABLED = '1';
    const noMarker = await change(5, { status: 'blocked' }, SUPER, { 'Content-Type': 'application/json' });
    expect([noMarker.status, noMarker.body]).toEqual([403, { error: 'csrf' }]);
    expect((await change(5, { status: 'blocked' }, null)).status).toBe(401);
    expect((await change(5, { status: 'blocked' }, HOSPITAL)).status).toBe(403);
    expect(rwPool.getConnection).not.toHaveBeenCalled();
  });

  it('blocks an active member: date set, sign-ins ended, journalled', async () => {
    const r = await change(5, { status: 'blocked' });
    expect(r.status).toBe(200);
    expect(members[5].mb_intercept_date).toMatch(/^\d{8}$/);
    const update = rwCalls.find((c) => c.sql.startsWith('UPDATE g5_member'))!;
    expect(update.sql).toContain('mb_cred_epoch = mb_cred_epoch + 1');
    expect(rwCalls.find((c) => c.sql.startsWith('DELETE FROM sso_tokens'))!.values).toEqual(['kim01']);
    expect(audits()).toEqual([[7, 'boss', 'super_admin', 5, 'kim01', 'block', 'active', 'blocked']]);
    expect(commits).toBe(1);
  });

  it('unblocks without ending sign-ins', async () => {
    const r = await change(6, { status: 'active' });
    expect(r.status).toBe(200);
    expect(members[6].mb_intercept_date).toBe('');
    expect(rwCalls.some((c) => c.sql.startsWith('DELETE FROM sso_tokens'))).toBe(false);
    expect(audits()).toEqual([[7, 'boss', 'super_admin', 6, 'lee02', 'unblock', 'blocked', 'active']]);
  });

  it('lets an active member leave (soft), ends sign-ins, journals', async () => {
    const r = await change(5, { status: 'left' });
    expect(r.status).toBe(200);
    expect(members[5].mb_leave_date).toMatch(/^\d{8}$/);
    expect(rwCalls.some((c) => c.sql.startsWith('DELETE FROM sso_tokens'))).toBe(true);
    expect(audits()[0].slice(5)).toEqual(['leave', 'active', 'left']);
  });

  it('refuses a blocked member leaving and a left member changing; same state is a no-op', async () => {
    const blocked = await change(6, { status: 'left' });
    expect([blocked.status, blocked.body]).toEqual([409, { error: 'member_blocked' }]);
    const gone = await change(7, { status: 'active' });
    expect([gone.status, gone.body]).toEqual([409, { error: 'member_left' }]);
    const same = await change(5, { status: 'active' });
    expect(same.status).toBe(200);
    expect(rwCalls.some((c) => c.sql.startsWith('UPDATE') || c.sql.startsWith('DELETE') || c.sql.startsWith('INSERT'))).toBe(false);
  });

  it('answers 400, 404 and 503 without changing anything', async () => {
    const bad = await change(5, { status: 'deleted' });
    expect([bad.status, bad.body.error]).toEqual([400, 'invalid']);
    const extra = await change(5, { status: 'blocked', reason: 'x' });
    expect(extra.status).toBe(400);
    expect((await change(99, { status: 'blocked' })).status).toBe(404);
    expect((await change(0, { status: 'blocked' })).status).toBe(404);
    rwEmpty = true;
    const down = await change(5, { status: 'blocked' });
    expect([down.status, down.body]).toEqual([503, { error: 'write_unavailable' }]);
    expect(members[5].mb_intercept_date).toBe('');
  });

  it('/v1/me reports the flag', async () => {
    const r = await request(app.getHttpServer()).get('/v1/me').set('Cookie', SUPER);
    expect(r.body.features.membersStatus).toBe(true);
  });
});
