import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import * as bcrypt from 'bcryptjs';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { DB, DB_RW } from '../db/db.module';
import { adminsWriteEnabled } from '../admin-auth/admin.guard';
import { AdminsModule } from './admins.module';
import { NOTIFICATION_TYPES } from './admins.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL5 = 'PHPSESSID=hospital5';
const PLAIN = 'PHPSESSID=plain';
const admin = (id: number, role: string, hospitalId: number | null = null) => ({ id, username: `u${id}`, name: `관리자${id}`, role, hospitalId });
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const STORED_HASH = bcrypt.hashSync('old-password-1', 4).replace(/^\$2[ab]\$/, '$2y$');
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
  let missingTable: string | null = null;
  let takenEmails: Record<string, string[]> = {};
  let usernameTaken = false;
  let notifyFails = false;

  const roStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      roCalls.push({ sql, values });
      if (sql.startsWith('SELECT id, role, is_active, permissions')) return [rows[values[0] as number] ? [rows[values[0] as number]] : []];
      if (sql.startsWith('SELECT id, name FROM gangnamunni_hospitals')) return [values[0] === 12 || values[0] === 13 ? [{ id: values[0], name: values[0] === 12 ? '서울의원' : '다른의원' }] : []];
      if (sql.startsWith('SELECT 1 AS hit FROM')) {
        const table = sql.split(' FROM ')[1].split(' ')[0];
        if (missingTable === table) throw Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
        return [takenEmails[table]?.includes(String(values[0]).toLowerCase()) ? [{ hit: 1 }] : []];
      }
      if (sql.startsWith('SELECT id, permissions FROM gangnamunni_admins WHERE is_active = 1')) return [Object.values(rows).filter((r) => r.is_active === 1).map((r) => ({ id: r.id, permissions: r.permissions }))];
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
    if (sql.startsWith('SELECT email, password FROM gangnamunni_admins')) return [rows[values[0] as number] ? [{ email: rows[values[0] as number].email, password: STORED_HASH }] : []];
    if (sql.startsWith('SELECT id FROM gangnamunni_admins WHERE username')) return [usernameTaken ? [{ id: 3 }] : []];
    if (sql.startsWith('INSERT INTO gangnamunni_admins')) {
      rows[10] = baseRow(10, values[5] as string, 1, { username: values[0], name: values[2], email: values[3], phone: values[4], hospital_id: values[6], hospital_name: values[7], permissions: values[9] });
      return [{ affectedRows: 1, insertId: 10 }];
    }
    if (sql.startsWith('INSERT INTO gangnamunni_notifications')) {
      if (notifyFails) throw new Error('notifications down');
      return [{ affectedRows: (values[0] as unknown[]).length }];
    }
    if (sql.includes('FOR UPDATE')) {
      const id = values[1] as number;
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
    lockRows = null; rwEmpty = false; rwError = null; affected = 1; missingTable = null; takenEmails = {}; usernameTaken = false; notifyFails = false;
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

  const send = (method: 'patch' | 'post' | 'delete', path: string, body: unknown, cookie?: string, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer())[method](path);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r.send(typeof body === 'object' && headers['Content-Type'] !== 'application/json' ? JSON.stringify(body) : (body as object));
  };
  const patch = (id: number | string, body: unknown, cookie: string = SUPER) => send('patch', `/v1/admins/${id}`, body, cookie);
  const active = (id: number | string, body: unknown, cookie: string = SUPER) => send('post', `/v1/admins/${id}/active`, body, cookie);
  const noWrites = () => expect(rwCalls).toHaveLength(0);
  const noUpdates = () => expect(rwCalls.filter((c) => /^(UPDATE|INSERT|DELETE)/.test(c.sql))).toHaveLength(0);
  const create = (body: unknown, cookie: string = SUPER) => send('post', '/v1/admins', body, cookie);
  const remove = (id: number | string, body: unknown, cookie: string = SUPER) => send('delete' as any, `/v1/admins/${id}`, body, cookie);

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

  describe('/v1/me flag', () => {
    it('adminsWriteEnabled (reported as features.adminsWrite) needs both admin switches', () => {
      expect(adminsWriteEnabled()).toBe(true);
      process.env.ADMIN_ADMINS_WRITE_ENABLED = '0';
      expect(adminsWriteEnabled()).toBe(false);
      process.env.ADMIN_ADMINS_WRITE_ENABLED = '1'; process.env.ADMIN_ADMINS_ENABLED = '0';
      expect(adminsWriteEnabled()).toBe(false);
    });
  });

  describe('edit', () => {
    it('super admin edits another account: whitelisted columns only, parameters only, returns the account', async () => {
      const res = await patch(6, { name: '  새이름 ', phone: '010-0000-1111' });
      expect(res.status).toBe(200);
      expect(events).toEqual(['BEGIN', expect.stringContaining('FOR UPDATE'), 'UPDATE gangnamunni_admins SET name = ?, phone = ? WHERE id = ?', 'COMMIT', 'RELEASE']);
      expect(rwCalls[1].values).toEqual(['새이름', '010-0000-1111', 6]);
      expect(res.body.item).toMatchObject({ id: 6 });
      expect(res.body.passwordChanged).toBe(false);
      expect(JSON.stringify(res.body)).not.toMatch(/\$2y\$|cred_epoch/i);
    });
    it('a hospital role edits itself', async () => {
      expect((await patch(5, { phone: '02-1' }, HOSPITAL5)).status).toBe(200);
      expect(rwCalls[1].values).toEqual(['02-1', 5]);
    });
    it('notifications are stored as the old panel stores them', async () => {
      const res = await patch(5, { notifications: [NOTIFICATION_TYPES[1], NOTIFICATION_TYPES[0]] }, HOSPITAL5);
      expect(res.status).toBe(200);
      expect(rwCalls[1].sql).toBe('UPDATE gangnamunni_admins SET permissions = ? WHERE id = ?');
      expect(rwCalls[1].values).toEqual([JSON.stringify({ notifications: [NOTIFICATION_TYPES[0], NOTIFICATION_TYPES[1]] }), 5]);
    });
    it('keys that are not editable here are refused', async () => {
      for (const body of [{ name: 'a', password: 'x' }, { isActive: true }, { permissions: '{}' }, { id: 3 }]) {
        const r = await patch(5, body);
        expect(r.status).toBe(400);
        expect(r.body.error).toBe('invalid');
      }
      noWrites();
    });
    describe('email and password', () => {
      const sqlOf = () => rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_admins'))!;
      it('super admin changes another account password without a current password; sessions end in the same statement', async () => {
        const r = await patch(6, { newPassword: 'brand-new-pass' });
        expect(r.status).toBe(200); expect(r.body.passwordChanged).toBe(true);
        expect(sqlOf().sql).toBe('UPDATE gangnamunni_admins SET password = ?, cred_epoch = cred_epoch + 1 WHERE id = ?');
        const [hash, id] = sqlOf().values as [string, number];
        expect(id).toBe(6); expect(hash.startsWith('$2y$')).toBe(true); expect(bcrypt.compareSync('brand-new-pass', hash)).toBe(true);
        expect(rwCalls.some((c) => c.sql.startsWith('SELECT email, password'))).toBe(false);
        expect(JSON.stringify(r.body)).not.toMatch(/\$2y\$/);
      });
      it('a hospital role needs its current password for its own password (missing -> 400, wrong -> 403, right -> 200)', async () => {
        let r = await patch(5, { newPassword: 'brand-new-pass' }, HOSPITAL5);
        expect(r.status).toBe(400); expect(r.body.fields).toEqual({ currentPassword: 'required' });
        r = await patch(5, { newPassword: 'brand-new-pass', currentPassword: 'wrong' }, HOSPITAL5);
        expect(r.status).toBe(403); expect(r.body).toEqual({ error: 'wrong_password' });
        noUpdates();
        r = await patch(5, { newPassword: 'brand-new-pass', currentPassword: 'old-password-1' }, HOSPITAL5);
        expect(r.status).toBe(200); expect(sqlOf().sql).toContain('cred_epoch = cred_epoch + 1');
      });
      it('the fifth wrong answer is followed by 429; a right one is refused until the wait ends', async () => {
        for (let i = 0; i < 5; i++) expect((await patch(5, { newPassword: 'brand-new-pass', currentPassword: 'wrong' }, HOSPITAL5)).status).toBe(403);
        const r = await patch(5, { newPassword: 'brand-new-pass', currentPassword: 'old-password-1' }, HOSPITAL5);
        expect(r.status).toBe(429); expect(r.body.error).toBe('too_many_attempts'); expect(r.body.retryAfterSeconds).toBeGreaterThan(0);
        noUpdates();
      });
      it('own email: needs the current password; an unchanged email needs nothing and writes nothing', async () => {
        rows[5].email = 'me@x.kr';
        let r = await patch(5, { email: 'New@X.kr' }, HOSPITAL5);
        expect(r.status).toBe(400);
        r = await patch(5, { email: 'New@X.kr', currentPassword: 'old-password-1' }, HOSPITAL5);
        expect(r.status).toBe(200);
        expect(sqlOf().sql).toBe('UPDATE gangnamunni_admins SET email = ? WHERE id = ?'); expect(sqlOf().values).toEqual(['new@x.kr', 5]);
        rwCalls.length = 0;
        r = await patch(5, { email: 'ME@x.kr' }, HOSPITAL5);
        expect(r.status).toBe(200); noUpdates();
      });
      it('an email used by someone else -> 409; the check leaves the account itself out', async () => {
        takenEmails = { users: ['used@x.kr'] };
        const r = await patch(6, { email: 'Used@x.kr' });
        expect(r.status).toBe(409); expect(r.body).toEqual({ error: 'conflict', field: 'email' });
        noWrites();
        await patch(6, { email: 'free@x.kr' });
        const own = roCalls.find((c) => c.sql.includes('FROM gangnamunni_admins WHERE LOWER(email)'))!;
        expect(own.sql).toContain('id <> ?'); expect(own.values).toEqual(['free@x.kr', 6]);
      });
      it('the current password is checked before the email lookups and the hash (no enumeration without it)', async () => {
        takenEmails = { users: ['used@x.kr'] };
        const r = await patch(5, { email: 'used@x.kr', currentPassword: 'wrong' }, HOSPITAL5);
        expect(r.status).toBe(403); expect(r.body).toEqual({ error: 'wrong_password' });
        expect(roCalls.some((c) => c.sql.startsWith('SELECT 1 AS hit'))).toBe(false);
        for (let i = 0; i < 4; i++) await patch(5, { newPassword: 'brand-new-pass', currentPassword: 'wrong' }, HOSPITAL5);
        rwCalls.length = 0;
        expect((await patch(5, { newPassword: 'brand-new-pass', currentPassword: 'wrong' }, HOSPITAL5)).status).toBe(429);
        expect(rwCalls).toHaveLength(0);
      });
      it('parallel wrong answers cannot get around the limit (5 answers, then 429)', async () => {
        const rs = await Promise.all(Array.from({ length: 10 }, () => patch(5, { newPassword: 'brand-new-pass', currentPassword: 'wrong' }, HOSPITAL5)));
        expect(rs.filter((r) => r.status === 403)).toHaveLength(5);
        expect(rs.filter((r) => r.status === 429)).toHaveLength(5);
        noUpdates();
      });
      it('a super admin editing own password is not asked for the current one', async () => {
        const r = await patch(1, { newPassword: 'brand-new-pass' });
        expect(r.status).toBe(200); expect(r.body.passwordChanged).toBe(true);
      });
    });
    it('invalid values -> 400 with the field; nothing read or written', async () => {
      const r = await patch(5, { name: '' });
      expect(r.body).toEqual({ error: 'invalid', fields: { name: 'required' } });
      expect((await patch(5, {})).status).toBe(400);
      expect((await patch(5, { notifications: ['nope'] })).body.fields).toEqual({ notifications: 'unknown_type' });
      noWrites();
    });
    it('unknown account -> 404, nothing updated', async () => {
      expect((await patch(99, { name: 'x' })).status).toBe(404);
      noUpdates();
      expect(events).toContain('ROLLBACK');
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
      expect(events).toEqual(['BEGIN', expect.stringContaining('FOR UPDATE'), 'UPDATE gangnamunni_admins SET is_active = 0, deactivated_at = NOW() WHERE id = ?', 'COMMIT', 'RELEASE']);
      expect(rwCalls[0].values).toEqual([1, 5]);
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

  describe('role and hospital (super admin)', () => {
    it('changes the role and hospital in one transaction; the hospital name comes from the database', async () => {
      const res = await patch(6, { role: 'hospital_admin', hospitalId: 13 });
      expect(res.status).toBe(200);
      expect(events).toEqual(['BEGIN', expect.stringContaining('FOR UPDATE'), 'UPDATE gangnamunni_admins SET role = ?, hospital_id = ?, hospital_name = ? WHERE id = ?', 'COMMIT', 'RELEASE']);
      expect(rwCalls[1].values).toEqual(['hospital_admin', 13, '다른의원', 6]);
    });
    it('a hospital admin may move to another hospital without naming the role again', async () => {
      expect((await patch(5, { hospitalId: 13 })).status).toBe(200);
      expect(rwCalls[1].values).toEqual(['hospital_admin', 13, '다른의원', 5]);
    });
    it('becoming a super admin clears the hospital', async () => {
      expect((await patch(5, { role: 'super_admin' })).status).toBe(200);
      expect(rwCalls[1].sql).toContain('role = ?, hospital_id = ?, hospital_name = ?');
      expect(rwCalls[1].values).toEqual(['super_admin', null, null, 5]);
    });
    it('a hospital admin needs a hospital that exists; a super admin takes none', async () => {
      rows[2] = baseRow(2, 'super_admin', 1);
      expect((await patch(2, { role: 'hospital_admin' })).body).toEqual({ error: 'invalid', fields: { hospitalId: 'required' } });
      expect((await patch(5, { hospitalId: 99 })).body).toEqual({ error: 'invalid', fields: { hospitalId: 'not_found' } });
      expect((await patch(5, { role: 'super_admin', hospitalId: 12 })).body).toEqual({ error: 'invalid', fields: { hospitalId: 'not_for_super_admin' } });
      noUpdates();
    });
    it('an unknown role is refused (400); a hospital role may not send role or hospital at all', async () => {
      expect((await patch(5, { role: 'admin' })).body).toEqual({ error: 'invalid', fields: { role: 'unknown_role' } });
      expect((await patch(5, { role: 'super_admin' }, HOSPITAL5)).body).toEqual({ error: 'invalid', fields: { role: 'not_allowed' } });
      expect((await patch(5, { hospitalId: 13 }, HOSPITAL5)).body).toEqual({ error: 'invalid', fields: { hospitalId: 'not_allowed' } });
      noUpdates();
    });
    it('nobody changes their own role; the last active super admin is never demoted', async () => {
      const own = await patch(1, { role: 'hospital_admin', hospitalId: 12 });
      expect(own.status).toBe(403); expect(own.body).toEqual({ error: 'self_forbidden' });
      lockRows = [rows[2]];
      const last = await patch(2, { role: 'hospital_admin', hospitalId: 12 });
      expect(last.status).toBe(409); expect(last.body).toEqual({ error: 'last_super_admin' });
      noUpdates();
    });
  });

  describe('add an admin', () => {
    const body = { username: 'new_user', password: 'longenough1', name: ' 새 관리자 ', email: ' New@Example.com ', phone: '010', role: 'hospital_admin', hospitalId: 12, notifications: [NOTIFICATION_TYPES[0]] };
    it('super admin only; the others get 403 and nothing is written', async () => {
      expect((await create(body, HOSPITAL5)).status).toBe(403);
      expect((await create(body, '')).status).toBe(401);
      noWrites();
    });
    it('adds the account: hashed password ($2y$), hospital name from the database, creator recorded, parameters only', async () => {
      const res = await create(body);
      expect(res.status).toBe(201);
      const insert = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_admins'))!;
      expect(insert.sql).toBe('INSERT INTO gangnamunni_admins (username, password, name, email, phone, role, hospital_id, hospital_name, parent_admin_id, permissions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      const [username, hash, name, email, phone, role, hid, hname, parent, perms] = insert.values as string[];
      expect([username, name, email, phone, role, hid, hname, parent]).toEqual(['new_user', '새 관리자', 'new@example.com', '010', 'hospital_admin', 12, '서울의원', 1]);
      expect(perms).toBe(JSON.stringify({ notifications: [NOTIFICATION_TYPES[0]] }));
      expect(hash).toMatch(/^\$2y\$10\$/);
      expect(await (await import('bcryptjs')).compare('longenough1', hash)).toBe(true);
      expect(JSON.stringify(res.body)).not.toMatch(/longenough1|\$2y\$|password/i);
      expect(res.body.item).toMatchObject({ id: 10, username: 'new_user', role: 'hospital_admin', hospitalId: 12 });
      expect(events.filter((e) => ['BEGIN', 'COMMIT', 'RELEASE'].includes(e))).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
    });
    it('a super admin gets no hospital, whatever is sent; missing notifications mean the defaults', async () => {
      const res = await create({ username: 'boss2', password: 'longenough1', name: '최고', role: 'super_admin', hospitalId: 12 });
      expect(res.status).toBe(201);
      const insert = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_admins'))!;
      expect(insert.values.slice(5, 9)).toEqual(['super_admin', null, null, 1]);
      expect(JSON.parse(insert.values[9] as string).notifications.length).toBeGreaterThan(0);
    });
    it.each([
      [{ ...body, username: 'a b' }, { username: 'bad_format' }],
      [{ ...body, username: 'ab' }, { username: 'bad_format' }],
      [{ ...body, username: undefined }, { username: 'required' }],
      [{ ...body, password: 'short' }, { password: 'too_short' }],
      [{ ...body, password: 'x'.repeat(73) }, { password: 'too_long' }],
      [{ ...body, name: '  ' }, { name: 'required' }],
      [{ ...body, email: 'nope' }, { email: 'bad_format' }],
      [{ ...body, role: 'admin' }, { role: 'unknown_role' }],
      [{ ...body, hospitalId: undefined }, { hospitalId: 'required' }],
      [{ ...body, notifications: ['x'] }, { notifications: 'unknown_type' }],
      [{ ...body, isActive: false }, { isActive: 'not_allowed' }],
    ])('invalid body %#: 400 with the field, nothing asked or written', async (b, fields) => {
      const r = await create(b);
      expect(r.status).toBe(400); expect(r.body).toEqual({ error: 'invalid', fields });
      noWrites();
    });
    it('an unknown hospital -> 400; a taken email (any of the three tables) -> 409; a taken login name -> 409', async () => {
      expect((await create({ ...body, hospitalId: 99 })).body).toEqual({ error: 'invalid', fields: { hospitalId: 'not_found' } });
      for (const table of ['users', 'g5_member', 'gangnamunni_admins']) {
        takenEmails = { [table]: ['new@example.com'] };
        const r = await create(body);
        expect(r.status).toBe(409); expect(r.body).toEqual({ error: 'conflict', field: 'email' });
      }
      takenEmails = {};
      usernameTaken = true;
      const u = await create(body);
      expect(u.status).toBe(409); expect(u.body).toEqual({ error: 'conflict', field: 'username' });
      expect(events).toContain('ROLLBACK');
      expect(rwCalls.some((c) => c.sql.startsWith('INSERT INTO gangnamunni_admins'))).toBe(false);
    });
    it('a table this site does not have counts as nobody there', async () => {
      missingTable = 'g5_member';
      expect((await create(body)).status).toBe(201);
    });
    it('the unique key losing a race -> 409; a refused grant -> 503; no write pool -> 503', async () => {
      rwError = Object.assign(new Error('Duplicate entry'), { code: 'ER_DUP_ENTRY', errno: 1062 });
      expect((await create(body)).body).toEqual({ error: 'conflict' });
      rwError = Object.assign(new Error('denied'), { code: 'ER_TABLEACCESS_DENIED_ERROR', errno: 1142 });
      expect((await create(body)).status).toBe(503);
      rwError = null; rwEmpty = true;
      expect((await create(body)).body).toEqual({ error: 'write_unavailable' });
    });
    it('every active admin who receives "new admin" gets one notice (title and text as in the old panel); no push', async () => {
      rows[5].permissions = JSON.stringify({ notifications: [] });
      await create(body);
      const notice = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_notifications'))!;
      const rowsOut = (notice.values[0] as unknown[][]);
      expect(rowsOut.map((r) => r[0]).sort()).toEqual([1, 2, 6].filter((i) => rows[i].is_active === 1).sort());
      expect(rowsOut[0].slice(1)).toEqual(['admin_created', '새 관리자 등록: 새 관리자', '역할: 병원 관리자 / 병원: 서울의원', 'admin_admins.php', 'bi-person-badge']);
    });
    it('a failing notice does not undo the new account', async () => {
      notifyFails = true;
      expect((await create(body)).status).toBe(201);
    });
    it('never logs the password, the email or the name', async () => {
      const spy = jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
      await create(body);
      const text = spy.mock.calls.map((c) => String(c[0])).join('\n');
      expect(text).toContain('action=create');
      expect(text).not.toMatch(/longenough1|new@example|새 관리자|new_user/);
    });
  });

  describe('delete an admin', () => {
    const inactive = (id: number, days: number) => {
      rows[id] = baseRow(id, 'hospital_admin', 0, { username: `old${id}`, deactivated_at: '2026-11-01 10:00:00', ready: days >= 1 ? 1 : 0, inactive_days: days });
    };
    it('super admin only', async () => {
      inactive(6, 9);
      expect((await remove(6, { confirm: 'old6' }, HOSPITAL5)).status).toBe(403);
      expect((await remove(6, { confirm: 'old6' }, '')).status).toBe(401);
      noWrites();
    });
    it('an account inactive long enough is deleted when its login name is typed; one transaction', async () => {
      inactive(6, 9);
      const res = await remove(6, { confirm: 'old6' });
      expect(res.status).toBe(200); expect(res.body).toEqual({ ok: true });
      expect(events).toEqual(['BEGIN', expect.stringContaining('FOR UPDATE'), 'DELETE FROM gangnamunni_admins WHERE id = ?', 'COMMIT', 'RELEASE']);
      expect(rwCalls[1].values).toEqual([6]);
    });
    it('exactly 1 day counts; switched off today does not (409 with the days left)', async () => {
      inactive(6, 1);
      expect((await remove(6, { confirm: 'old6' })).status).toBe(200);
      inactive(6, 0);
      const early = await remove(6, { confirm: 'old6' });
      expect(early.status).toBe(409); expect(early.body).toEqual({ error: 'not_inactive_long_enough', daysLeft: 1 });
      expect(rwCalls.filter((c) => c.sql.startsWith('DELETE')).length).toBe(1);
    });
    it('an active account is never deleted (409); an account without a switch-off record is not either (409)', async () => {
      expect((await remove(5, { confirm: 'u5' })).body).toEqual({ error: 'still_active' });
      rows[6] = baseRow(6, 'hospital_admin', 0, { username: 'old6', deactivated_at: null, ready: 0, inactive_days: null });
      expect((await remove(6, { confirm: 'old6' })).body).toEqual({ error: 'no_inactive_record' });
      expect(rwCalls.some((c) => c.sql.startsWith('DELETE'))).toBe(false);
    });
    it('the login name must match exactly (400); a missing confirmation is refused before the database', async () => {
      inactive(6, 9);
      expect((await remove(6, { confirm: 'OLD6' })).body).toEqual({ error: 'invalid', fields: { confirm: 'mismatch' } });
      expect((await remove(6, { confirm: ' old6' })).status).toBe(400);
      expect(rwCalls.some((c) => c.sql.startsWith('DELETE'))).toBe(false);
      rwCalls.length = 0;
      expect((await remove(6, {})).body).toEqual({ error: 'invalid', fields: { confirm: 'required' } });
      expect((await remove(6, { confirm: 5 })).status).toBe(400);
      noWrites();
    });
    it('not yourself (403); unknown account -> 404; a bad id -> 404 without reading', async () => {
      expect((await remove(1, { confirm: 'u1' })).body).toEqual({ error: 'self_forbidden' });
      expect((await remove(99, { confirm: 'x' })).status).toBe(404);
      noUpdates();
      rwCalls.length = 0;
      expect((await remove('abc', { confirm: 'x' })).status).toBe(404);
      noWrites();
    });
    it('still referenced -> 409 in_use; busy -> 503; no write pool -> 503', async () => {
      inactive(6, 9);
      const orig = affected;
      rwError = null;
      jest.spyOn(rwStub, 'get').mockReturnValueOnce({
        query: jest.fn(),
        getConnection: async () => ({
          query: async (sql: string, v: unknown[] = []) => { if (sql.startsWith('DELETE')) throw Object.assign(new Error('fk'), { code: 'ER_ROW_IS_REFERENCED_2', errno: 1451 }); return rwQuery(sql, v); },
          beginTransaction: async () => undefined, commit: async () => undefined, rollback: async () => undefined, release: () => undefined,
        }),
      } as any);
      const fk = await remove(6, { confirm: 'old6' });
      expect(fk.status).toBe(409); expect(fk.body).toEqual({ error: 'in_use' });
      affected = orig;
      rwError = Object.assign(new Error('Deadlock'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      expect((await remove(6, { confirm: 'old6' })).body).toEqual({ error: 'busy' });
      rwError = null; rwEmpty = true;
      expect((await remove(6, { confirm: 'old6' })).body).toEqual({ error: 'write_unavailable' });
    });
  });
});
