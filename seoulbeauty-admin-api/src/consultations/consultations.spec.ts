import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { ConsultationsModule } from './consultations.module';
import { DB } from '../db/db.module';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, symlinkSync } from 'fs';
import { tmpdir } from 'os';
import * as path from 'path';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const NOHOSP = 'PHPSESSID=nohospital';
const OTHER = 'PHPSESSID=other';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
type Call = { sql: string; values: unknown[] };

describe('admin API: consultations (read)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const calls: Call[] = [];
  let failWith: unknown = null;
  let historyFail: unknown = null;
  let eventRows: Record<string, unknown>[] = [];
  let photoUrls = '["uploads/consultations/9/a1.jpg","uploads/consultations/9/../../../etc/passwd","uploads/consultations/8/b.jpg","uploads/consultations/9/x.html"]';
  const appRow = () => ({ id: 9, hospital_id: 12, hospital_name: '청담', consultation_type: 'general', name: '박서연', phone: '010-1', message: '[관심 시술] 보톡스\n안녕하세요\n두 번째 줄', photo_urls: photoUrls, status: 'pending', created_at: '2026-11-24 10:00:00' });
  /** The row is returned only when the query's hospital part lets it through, like the real WHERE. */
  const visible = (sql: string, values: unknown[]) => !sql.includes('1 = 0') && (!sql.includes('IN (?)') || values.includes(12));

  const dbStub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      calls.push({ sql, values });
      if (failWith) throw failWith;
      if (sql.includes('c.hospital_message')) {
        if (historyFail) throw historyFail;
        return [visible(sql, values) ? [{ hospital_message: '내일 연락드릴게요', hospital_message_at: '2026-11-24 12:00:00', callback_slot: 'pm' }] : []];
      }
      if (sql.includes('gangnamunni_consultation_events')) {
        if (historyFail) throw historyFail;
        return [eventRows];
      }
      if (sql.includes('SELECT c.id, c.photo_urls') || sql.includes('WHERE c.id = ?')) {
        const row = appRow();
        return [visible(sql, values) && values[0] === 9 ? [sql.includes('SELECT c.id, c.photo_urls') ? { id: row.id, photo_urls: row.photo_urls } : row] : []];
      }
      if (sql.includes('COUNT(*) AS cnt FROM gangnamunni_consultations c WHERE')) return [[{ cnt: 41 }]];
      if (sql.includes('GROUP BY c.status')) return [[{ status: 'pending', cnt: 5, oldest: '2026-11-20 09:00:00' }, { status: 'completed', cnt: 36, oldest: null }]];
      if (sql.includes('FROM gangnamunni_hospitals h')) return [[{ id: 12, name: '청담', cnt: 9, pending_count: 2 }]];
      if (sql.includes('SELECT c.id')) return [[{ id: 9, hospital_id: 12, hospital_name: '청담', consultation_type: 'general', name: '박서연', phone: '010-1', message: '[관심 시술] 보톡스\n안녕', photo_urls: '["a"]', status: 'pending', created_at: '2026-11-24 10:00:00' }]];
      return [[]];
    }),
  };

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (b: unknown) => new Response(JSON.stringify(b), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      if (cookie.includes('nohospital')) return json({ loggedIn: true, admin: admin('hospital_admin', null) });
      if (cookie.includes('other')) return json({ loggedIn: true, admin: admin('content_manager') });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_CONSULTATIONS_ENABLED = '1';
    calls.length = 0;
    failWith = null;
    @Global() @Module({ providers: [{ provide: DB, useValue: dbStub }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, ConsultationsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_CONSULTATIONS_ENABLED']) delete process.env[k];
  });

  const get = (path: string, cookie: string = SUPER) => {
    const r = request(app.getHttpServer()).get(path);
    return cookie ? r.set('Cookie', cookie) : r;
  };

  it('switch off -> 404 on both routes, PHP not asked; no session -> 401; unrelated role -> 403', async () => {
    expect((await get('/v1/consultations', '')).status).toBe(401);
    expect((await get('/v1/consultations', OTHER)).status).toBe(403);
    process.env.ADMIN_CONSULTATIONS_ENABLED = '0';
    expect((await get('/v1/consultations')).status).toBe(404);
    expect((await get('/v1/consultations/hospitals')).status).toBe(404);
    process.env.ADMIN_CONSULTATIONS_ENABLED = '1';
    process.env.ADMIN_PANEL_ENABLED = '0';
    expect((await get('/v1/consultations')).status).toBe(404);
  });

  it('a hospital role is refused while the hospital pages are off', async () => {
    process.env.ADMIN_HOSPITALS_ENABLED = '0';
    expect((await get('/v1/consultations', HOSPITAL)).status).toBe(403);
    expect((await get('/v1/consultations', SUPER)).status).toBe(200);
  });

  it('the super admin list: items cut to what the page needs, counts and paging', async () => {
    const r = await get('/v1/consultations?status=pending&q=%EB%B0%95&page=1');
    expect(r.status).toBe(200);
    expect(r.body).toMatchObject({ total: 41, page: 1, perPage: 20, totalPages: 3 });
    expect(r.body.stats).toEqual({ total: 41, pending: 5, approved: 0, completed: 36, rejected: 0, contact_failed: 0, oldestPendingAt: '2026-11-20 09:00:00' });
    expect(r.body.items[0]).toEqual({ id: 9, hospitalId: 12, hospitalName: '청담', type: 'general', name: '박서연', phone: '010-1', tags: ['[관심 시술] 보톡스'], preview: '안녕', photoCount: 1, status: 'pending', createdAt: '2026-11-24 10:00:00' });
    expect(calls.every((c) => !c.sql.includes('박'))).toBe(true);
  });

  it('a page past the end is brought back to the last page', async () => {
    const r = await get('/v1/consultations?page=99');
    expect(r.body.page).toBe(3);
  });

  it('a hospital account is held to its own hospital in every query, whatever the query string says', async () => {
    const r = await get('/v1/consultations?hospital_id=99', HOSPITAL);
    expect(r.status).toBe(200);
    for (const c of calls) {
      expect(c.sql).toContain('c.hospital_id IN (?)');
      expect(c.values).toContain(12);
    }
    expect(calls.every((c) => !c.sql.includes('12') && !c.sql.includes('99'))).toBe(true);
    expect((await get('/v1/consultations/hospitals', HOSPITAL)).status).toBe(200);
    expect(calls[calls.length - 1].sql).toContain('h.id IN (?)');
  });

  it('a hospital account without a bound hospital sees nothing (1 = 0)', async () => {
    const r = await get('/v1/consultations', NOHOSP);
    expect(r.status).toBe(200);
    expect(calls.every((c) => c.sql.includes('1 = 0'))).toBe(true);
  });

  it('a bad query is a 400, not an SQL error', async () => {
    expect((await get('/v1/consultations?status=nope')).status).toBe(400);
    expect((await get('/v1/consultations?hospital_id=abc')).status).toBe(400);
    expect((await get('/v1/consultations?page=0')).status).toBe(400);
    expect(calls).toHaveLength(0);
  });

  it('the hospital choices', async () => {
    const r = await get('/v1/consultations/hospitals');
    expect(r.body).toEqual({ items: [{ id: 12, name: '청담', consultationCount: 9, pendingCount: 2 }] });
  });

  it('a missing table is a 503 with a name, not a crash', async () => {
    failWith = Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
    const r = await get('/v1/consultations');
    expect(r.status).toBe(503);
    expect(r.body).toMatchObject({ error: 'consultations_unavailable' });
  });

  describe('detail and photos', () => {
    const FULL = '/v1/consultations/9';

    it('the detail has the full text, the hospital message and the record; no photo addresses, no member id', async () => {
      eventRows = [{ kind: 'no_answer', created_at: '2026-11-24 11:00:00' }, { kind: 'weird', created_at: 'x' }, { kind: 'completed', created_at: '2026-11-24 13:00:00' }];
      const r = await get(FULL);
      expect(r.status).toBe(200);
      expect(r.body).toEqual({
        id: 9, hospitalId: 12, hospitalName: '청담', type: 'general', name: '박서연', phone: '010-1', tags: ['[관심 시술] 보톡스'], text: '안녕하세요\n두 번째 줄', photoCount: 4, status: 'pending', createdAt: '2026-11-24 10:00:00',
        historySupported: true, hospitalMessage: '내일 연락드릴게요', hospitalMessageAt: '2026-11-24 12:00:00', callbackSlot: 'pm',
        events: [{ kind: 'no_answer', createdAt: '2026-11-24 11:00:00' }, { kind: 'completed', createdAt: '2026-11-24 13:00:00' }],
      });
      expect(JSON.stringify(r.body)).not.toMatch(/uploads|mb_id/);
      expect(calls.every((c) => !c.sql.includes('mb_id') && !c.sql.includes('SELECT *'))).toBe(true);
    });

    it('an older database without the history parts still answers (historySupported false)', async () => {
      historyFail = Object.assign(new Error('no column'), { code: 'ER_BAD_FIELD_ERROR', errno: 1054 });
      const r = await get(FULL);
      expect(r.status).toBe(200);
      expect(r.body).toMatchObject({ historySupported: false, hospitalMessage: '', hospitalMessageAt: null, callbackSlot: '', events: [] });
      historyFail = Object.assign(new Error('boom'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      expect((await get(FULL)).status).toBe(500);
      historyFail = null;
    });

    it('another hospital, an unbound account, a malformed or unknown id: the same 404, and nothing else is read', async () => {
      process.env.ADMIN_UPLOAD_ROOT = '';
      for (const [url, who] of [[FULL, NOHOSP], ['/v1/consultations/9x', SUPER], ['/v1/consultations/0', SUPER], ['/v1/consultations/77', SUPER]] as const) {
        calls.length = 0;
        const r = await get(url, who);
        expect(r.status).toBe(404);
        expect(r.body).toEqual({ error: 'not_found' });
        expect(calls.every((c) => !c.sql.includes('c.hospital_message') && !c.sql.includes('gangnamunni_consultation_events'))).toBe(true);
      }
      const other = await get('/v1/consultations/9', 'PHPSESSID=hospital12');
      expect(other.status).toBe(200);
    });

    it('detail and photo are off with the switch, closed without a session, closed for an unrelated role', async () => {
      expect((await get(FULL, '')).status).toBe(401);
      expect((await get(`${FULL}/photos/0`, OTHER)).status).toBe(403);
      process.env.ADMIN_CONSULTATIONS_ENABLED = '0';
      expect((await get(FULL)).status).toBe(404);
      expect((await get(`${FULL}/photos/0`)).status).toBe(404);
    });

    describe('photo files', () => {
      let root: string;
      beforeEach(() => {
        root = mkdtempSync(path.join(tmpdir(), 'consult-'));
        mkdirSync(path.join(root, 'uploads/consultations/9'), { recursive: true });
        mkdirSync(path.join(root, 'uploads/consultations/8'), { recursive: true });
        writeFileSync(path.join(root, 'uploads/consultations/9/a1.jpg'), Buffer.from([0xff, 0xd8, 0xff, 0xd9]));
        writeFileSync(path.join(root, 'uploads/consultations/9/x.html'), '<script>1</script>');
        writeFileSync(path.join(root, 'uploads/consultations/8/b.jpg'), 'other');
        process.env.ADMIN_UPLOAD_ROOT = root;
        photoUrls = '["uploads/consultations/9/a1.jpg","uploads/consultations/9/../../../etc/passwd","uploads/consultations/8/b.jpg","uploads/consultations/9/x.html","uploads/consultations/9/link.jpg","uploads/consultations/9/missing.png"]';
      });
      afterEach(() => { rmSync(root, { recursive: true, force: true }); delete process.env.ADMIN_UPLOAD_ROOT; });

      it('streams the image with safe headers, for the super admin and for the hospital that owns it', async () => {
        for (const who of [SUPER, 'PHPSESSID=hospital12']) {
          const r = await get(`${FULL}/photos/0`, who).buffer(true).parse((res, cb) => { const b: Buffer[] = []; res.on('data', (d: Buffer) => b.push(d)); res.on('end', () => cb(null, Buffer.concat(b))); });
          expect(r.status).toBe(200);
          expect(r.headers['content-type']).toBe('image/jpeg');
          expect(r.headers['x-content-type-options']).toBe('nosniff');
          expect(r.headers['cache-control']).toBe('private, no-store');
          expect(r.headers['content-security-policy']).toContain('sandbox');
          expect(r.headers['content-disposition']).toBe('inline; filename="a1.jpg"');
          expect(Buffer.from(r.body).equals(Buffer.from([0xff, 0xd8, 0xff, 0xd9]))).toBe(true);
        }
      });

      it('only this application\'s own plain image files: a path out of the folder, another application\'s file, a non-image, a link out, a missing file, an index off the list are all 404', async () => {
        const secret = path.join(root, 'secret.txt');
        writeFileSync(secret, 'secret');
        symlinkSync(secret, path.join(root, 'uploads/consultations/9/link.jpg'));
        for (const i of ['1', '2', '3', '4', '5', '6', '9']) expect((await get(`${FULL}/photos/${i}`)).status).toBe(404);
        for (const i of ['10', '-1', 'a', '00']) expect((await get(`${FULL}/photos/${i}`)).status).toBe(404);
      });

      it('another hospital\'s application and an unbound account get 404 without a file being read', async () => {
        expect((await get(`${FULL}/photos/0`, NOHOSP)).status).toBe(404);
      });

      it('a missing or unusable upload folder is a 503 with a name', async () => {
        process.env.ADMIN_UPLOAD_ROOT = '';
        const r = await get(`${FULL}/photos/0`);
        expect(r.status).toBe(503);
        expect(r.body).toMatchObject({ error: 'uploads_unavailable' });
      });
    });
  });

  it('/v1/me tells the page whether the consultations are on', async () => {
    expect((await get('/v1/me')).body.features.consultations).toBe(true);
    process.env.ADMIN_CONSULTATIONS_ENABLED = '0';
    expect((await get('/v1/me')).body.features.consultations).toBe(false);
  });
});
