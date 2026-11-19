import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { DB, DB_RW } from '../db/db.module';
import { AdminsModule } from './admins.module';
import { NOTIFICATION_TYPES } from './admins.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL5 = 'PHPSESSID=hospital5';
const PLAIN = 'PHPSESSID=plain';
const admin = (id: number, role: string, hospitalId: number | null = null) => ({ id, username: `u${id}`, name: `관리자${id}`, role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const PERMS5 = JSON.stringify({ notifications: ['review_new'] });

const baseRow = (id: number, role: string, active: number, over: Record<string, unknown> = {}) => ({
  id, username: `u${id}`, name: `관리자${id}`, email: '', phone: '', role, hospital_id: role === 'super_admin' ? null : 12, hospital_name: role === 'super_admin' ? null : '서울의원',
  is_active: active, last_login_at: null, permissions: null, ...over,
});

describe('admin API: admin account writes (edit, active state)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const roCalls: { sql: string; values: unknown[] }[] = [];
  const rwCalls: { sql: string; values: unknown[] }[] = [];
  const events: string[] = [];
  let rows: Record<number, Record<string, unknown>>;
  let lockRows: Record<string, unknown>[] | null;
  let rwEmpty = false;
  let rwError: (Error & { code?: string; errno?: number }) | null = null;
  let affected = 1;

  const roStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      roCalls.push({ sql, values });
      if (sql.startsWith('SELECT id, role, is_active, permissions')) return [rows[values[0] as number] ? [rows[values[0] as number]] : []];
      if (sql.includes('deactivated_at')) return [[]];
      if (sql.includes('g5_mb_id')) return [[]];
      if (sql.includes('FROM gangnamunni_admins WHERE id = ?')) return [rows[values[0] as number] ? [rows[values[0] as number]] : []];
      throw new Error(`unexpected read-only sql: ${sql}`);
    }),
  };
  const rwQuery = async (sql: string, values: unknown[] = []) => {
    rwCalls.push({ sql, values });
    events.push(sql);
    if (rwError) throw rwError;
    if (sql.includes('FOR UPDATE')) {
      const id = values[0] as number;
      const defaults = [rows[id], ...Object.values(rows).filter((r) => r.role === 'super_admin' && r.is_active === 1 && r.id !== id)].filter(Boolean);
      return [lockRows ?? defaults];
    }
    return [{ affectedRows: affected }];
  };
  const rwStub = {
    get: () => (rwEmpty ? null : {
      query: jest.fn(rwQuery),
      getConnection: async () => ({
        query: async (sql: string, values: unknown[] = []) => rwQuery(sql, values),
        beginTransaction: async () => { events.push('BEGIN'); },
        commit: async () => { events.push('COMMIT'); },
        rollback: async () => { events.push('ROLLBACK'); },
        release: () => { events.push('RELEASE'); },
      }),
    }),
  };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      phpCalls.push(cookie);
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin(1, 'super_admin') });
      if (cookie.includes('hospital5')) return json({ loggedIn: true, admin: admin(5, 'hospital_admin', 12) });
      if (cookie.includes('plain')) return json({ loggedIn: true, admin: admin(9, 'viewer') });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    jest.spyOn(Logger.prototype, 'warn').mockImplementation(() => undefined);
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_ADMINS_ENABLED = '1';
    process.env.ADMIN_ADMINS_WRITE_ENABLED = '1';
    phpCalls.length = 0; roCalls.length = 0; rwCalls.length = 0; events.length = 0;
    rows = {
      1: baseRow(1, 'super_admin', 1),
      2: baseRow(2, 'super_admin', 1),
      5: baseRow(5, 'hospital_admin', 1, { permissions: PERMS5 }),
      6: baseRow(6, 'hospital_admin', 0),
    };
    lockRows = null; rwEmpty = false; rwError = null; affected = 1;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, AdminsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    delete process.env.ADMIN_PANEL_ENABLED; delete process.env.ADMIN_ADMINS_ENABLED; delete process.env.ADMIN_ADMINS_WRITE_ENABLED;
  });

  const send = (method: 'patch' | 'post', path: string, body: unknown, cookie?: string, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer())[method](path);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r.send(typeof body === 'object' && headers['Content-Type'] !== 'application/json' ? JSON.stringify(body) : (body as object));
  };
  const patch = (id: number | string, body: unknown, cookie: string = SUPER) => send('patch', `/v1/admins/${id}`, body, cookie);
  const active = (id: number | string, body: unknown, cookie: string = SUPER) => send('post', `/v1/admins/${id}/active`, body, cookie);
  const noWrites = () => expect(rwCalls).toHaveLength(0);

  describe('switch, CSRF and roles', () => {
    it.each([undefined, '0', 'true', ''])('ADMIN_ADMINS_WRITE_ENABLED=%p -> 404, nothing asked or written', async (v) => {
      if (v === undefined) delete process.env.ADMIN_ADMINS_WRITE_ENABLED; else process.env.ADMIN_ADMINS_WRITE_ENABLED = v;
      expect((await patch(5, { name: 'x' })).status).toBe(404);
      expect((await active(5, { active: false })).status).toBe(404);
      expect(phpCalls).toHaveLength(0); expect(roCalls).toHaveLength(0); noWrites();
    });
    it('needs the page switch too', async () => {
      process.env.ADMIN_ADMINS_ENABLED = '0';
      expect((await patch(5, { name: 'x' })).status).toBe(404);
    });
    it('without the marker header or JSON content type -> 403 csrf before anything else', async () => {
      const a = await send('patch', '/v1/admins/5', { name: 'x' }, SUPER, { 'Content-Type': 'application/json' });
      expect(a.status).toBe(403); expect(a.body).toEqual({ error: 'csrf' });
      const b = await send('post', '/v1/admins/5/active', { active: false }, SUPER, { 'X-Requested-With': 'admin-next', 'Content-Type': 'text/plain' });
      expect(b.status).toBe(403);
      const c = await send('patch', '/v1/admins/5', { name: 'x' }, SUPER, { ...HEADERS, Origin: 'https://evil.example' });
      expect(c.status).toBe(403);
      expect(phpCalls).toHaveLength(0); noWrites();
    });
    it('not signed in -> 401; a role outside the panel -> 403', async () => {
      expect((await patch(5, { name: 'x' }, '')).status).toBe(401);
      expect((await patch(5, { name: 'x' }, PLAIN)).status).toBe(403);
      noWrites();
    });
    it('a hospital role cannot edit another account (403) nor touch the active state (403)', async () => {
      expect((await patch(6, { name: 'x' }, HOSPITAL5)).status).toBe(403);
      expect((await active(6, { active: true }, HOSPITAL5)).status).toBe(403);
      expect((await active(5, { active: false }, HOSPITAL5)).status).toBe(403);
      noWrites();
    });
    it('a bad id answers 404 without reading', async () => {
      for (const id of ['0', 'abc', '-1', '1e3']) expect((await patch(id, { name: 'x' })).status).toBe(404);
      expect((await active('abc', { active: true })).status).toBe(404);
      expect(roCalls).toHaveLength(0); noWrites();
    });
  });

  describe('edit', () => {
    it('super admin edits another account: whitelisted columns only, parameters only, returns the account', async () => {
      const res = await patch(6, { name: '  새이름 ', phone: '010-0000-1111' });
      expect(res.status).toBe(200);
      expect(rwCalls).toHaveLength(1);
      expect(rwCalls[0].sql).toBe('UPDATE gangnamunni_admins SET name = ?, phone = ? WHERE id = ?');
      expect(rwCalls[0].values).toEqual(['새이름', '010-0000-1111', 6]);
      expect(res.body.item).toMatchObject({ id: 6 });
      expect(JSON.stringify(res.body)).not.toMatch(/password|cred_epoch/i);
    });
    it('a hospital role edits itself', async () => {
      expect((await patch(5, { phone: '02-1' }, HOSPITAL5)).status).toBe(200);
      expect(rwCalls[0].values).toEqual(['02-1', 5]);
    });
    it('notifications are stored as the old panel stores them, compared with what was read', async () => {
      const res = await patch(5, { notifications: [NOTIFICATION_TYPES[1], NOTIFICATION_TYPES[0]] }, HOSPITAL5);
      expect(res.status).toBe(200);
      expect(rwCalls[0].sql).toBe('UPDATE gangnamunni_admins SET permissions = ? WHERE id = ? AND permissions <=> ?');
      expect(rwCalls[0].values).toEqual([JSON.stringify({ notifications: [NOTIFICATION_TYPES[0], NOTIFICATION_TYPES[1]] }), 5, PERMS5]);
    });
    it('role, email, password, hospital and other keys are refused', async () => {
      for (const body of [{ role: 'super_admin' }, { name: 'a', email: 'a@b.c' }, { password: 'x' }, { hospitalId: 3 }, { isActive: true }]) {
        const r = await patch(5, body);
        expect(r.status).toBe(400);
        expect(r.body.error).toBe('invalid');
      }
      noWrites();
    });
    it('invalid values -> 400 with the field; nothing read or written', async () => {
      const r = await patch(5, { name: '' });
      expect(r.body).toEqual({ error: 'invalid', fields: { name: 'required' } });
      expect((await patch(5, {})).status).toBe(400);
      expect((await patch(5, { notifications: ['nope'] })).body.fields).toEqual({ notifications: 'unknown_type' });
      noWrites();
    });
    it('unknown account -> 404; an account that vanished meanwhile -> 404; changed meanwhile -> 409', async () => {
      expect((await patch(99, { name: 'x' })).status).toBe(404);
      noWrites();
      affected = 0;
      const changed = await patch(5, { notifications: [] });
      expect(changed.status).toBe(409); expect(changed.body).toEqual({ error: 'conflict' });
      delete rows[6];
      rows[6] = baseRow(6, 'hospital_admin', 0);
      const gone = jest.spyOn(roStub, 'query');
      gone.mockImplementationOnce(async () => [[rows[6]]]).mockImplementationOnce(async () => [[]]);
      expect((await patch(6, { name: 'x' })).status).toBe(404);
    });
    it('write pool not configured or refused -> 503 write_unavailable', async () => {
      rwEmpty = true;
      expect((await patch(5, { name: 'x' })).body).toEqual({ error: 'write_unavailable' });
      rwEmpty = false;
      rwError = Object.assign(new Error('denied'), { code: 'ER_COLUMNACCESS_DENIED_ERROR', errno: 1143 });
      expect((await patch(5, { name: 'x' })).status).toBe(503);
    });
    it('a lock conflict -> 503 busy, not a 500', async () => {
      rwError = Object.assign(new Error('Deadlock found'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      expect((await patch(5, { name: 'x' })).body).toEqual({ error: 'busy' });
      expect((await active(5, { active: false })).body).toEqual({ error: 'busy' });
    });
    it('logs field names, never values', async () => {
      const spy = jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
      await patch(6, { name: '비밀이름', phone: '010-9999-8888', notifications: [] });
      const line = spy.mock.calls.map((c) => String(c[0])).join('\n');
      expect(line).toContain('action=edit');
      expect(line).toContain('fields=name,phone,permissions');
      expect(line).not.toMatch(/비밀이름|010-9999-8888/);
    });
  });

  describe('active state', () => {
    it('switching off: one transaction, locks the active super admins, starts the clock', async () => {
      const res = await active(5, { active: false });
      expect(res.status).toBe(200);
      expect(events).toEqual([
        'BEGIN',
        "SELECT id, role, is_active FROM gangnamunni_admins WHERE id = ? OR (role = 'super_admin' AND is_active = 1) FOR UPDATE",
        'UPDATE gangnamunni_admins SET is_active = 0, deactivated_at = NOW() WHERE id = ?',
        'COMMIT',
        'RELEASE',
      ]);
      expect(rwCalls[1].values).toEqual([5]);
    });
    it('switching on clears the mark', async () => {
      expect((await active(6, { active: true })).status).toBe(200);
      expect(events).toContain('UPDATE gangnamunni_admins SET is_active = 1, deactivated_at = NULL WHERE id = ?');
    });
    it('switching off an already inactive account keeps (or starts) the first mark', async () => {
      expect((await active(6, { active: false })).status).toBe(200);
      expect(events).toContain('UPDATE gangnamunni_admins SET is_active = 0, deactivated_at = COALESCE(deactivated_at, NOW()) WHERE id = ?');
    });
    it('not your own account (403), nothing updated, rolled back', async () => {
      const r = await active(1, { active: false });
      expect(r.status).toBe(403); expect(r.body).toEqual({ error: 'self_forbidden' });
      expect(events).toEqual(expect.arrayContaining(['ROLLBACK', 'RELEASE']));
      expect(events.some((e) => e.startsWith('UPDATE'))).toBe(false);
    });
    it('the last active super admin stays active (409)', async () => {
      lockRows = [rows[2]];
      const r = await active(2, { active: false });
      expect(r.status).toBe(409); expect(r.body).toEqual({ error: 'last_super_admin' });
      expect(events.some((e) => e.startsWith('UPDATE'))).toBe(false);
      expect(events).toContain('ROLLBACK');
    });
    it('another super admin may be switched off while a second one stays active', async () => {
      expect((await active(2, { active: false })).status).toBe(200);
    });
    it('an exact boolean is required (400); unknown account -> 404', async () => {
      expect((await active(5, { active: 'false' })).body).toEqual({ error: 'invalid', fields: { active: 'bad_format' } });
      expect((await active(5, {})).body).toEqual({ error: 'invalid', fields: { active: 'required' } });
      noWrites();
      lockRows = [];
      expect((await active(99, { active: false })).status).toBe(404);
    });
    it('write pool unavailable -> 503', async () => {
      rwEmpty = true;
      expect((await active(5, { active: false })).body).toEqual({ error: 'write_unavailable' });
    });
  });
});
