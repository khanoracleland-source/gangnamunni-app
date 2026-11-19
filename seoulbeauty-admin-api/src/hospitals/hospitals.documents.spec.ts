import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import { promises as fs, readdirSync } from 'fs';
import * as os from 'os';
import * as path from 'path';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { HospitalsModule } from './hospitals.module';
import { HospitalsService } from './hospitals.service';
import { DB, DB_RW } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const HADMIN = 'PHPSESSID=hadmin12';
const UNBOUND = 'PHPSESSID=unbound';
const EDITOR = 'PHPSESSID=editor';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const MP = { 'X-Requested-With': 'admin-next' };
const JSONH = { 'X-Requested-With': 'admin-next', 'Content-Type': 'application/json' };
const PHP_VIEWER = /^uploads\/hospital_apply\/[A-Za-z0-9_\-]+\/(cert_(?:business|medical)_[A-Za-z0-9]+\.(jpg|jpeg|png|pdf))$/;

const jpeg = (size = 64) => Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(Math.max(0, size - 4), 1)]);
const png = (size = 64) => Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(Math.max(0, size - 8), 2)]);
const pdf = (size = 64) => Buffer.concat([Buffer.from('%PDF-1.7\n', 'latin1'), Buffer.alloc(Math.max(0, size - 9), 3)]);

type Call = { sql: string; values: unknown[] };
type Hosp = { status: string; cert_business: string | null; cert_medical: string | null };

describe('admin API: hospital documents (upload, replace, delete)', () => {
  let app: INestApplication;
  let root: string;
  let outside: string;
  const realFetch = global.fetch;
  const phpCalls: string[] = [];
  const rwCalls: Call[] = [];
  const events: string[] = [];
  const logs: string[] = [];
  let hospitals: Record<number, Hosp>;
  let failUpdate: boolean;
  let failLock: boolean;
  let lockEmpty: boolean;
  let hasRw = true;

  const column = (sql: string): 'cert_business' | 'cert_medical' => (sql.includes('SET cert_business') ? 'cert_business' : 'cert_medical');
  const handler = async (sql: string, values: unknown[] = []): Promise<unknown> => {
    if (sql.includes("AND status = 'approved' LIMIT 1")) {
      const id = values[0] as number;
      return [hospitals[id] && hospitals[id].status === 'approved' ? [{ id }] : []];
    }
    if (sql.startsWith('SELECT id, cert_business, cert_medical FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE')) {
      if (failLock) throw Object.assign(new Error('denied'), { code: 'ER_COLUMNACCESS_DENIED_ERROR', errno: 1143 });
      const h = hospitals[values[0] as number];
      return [h && !lockEmpty ? [{ id: values[0], cert_business: h.cert_business, cert_medical: h.cert_medical }] : []];
    }
    if (/^UPDATE gangnamunni_hospitals SET cert_(business|medical) = \? WHERE id = \?$/.test(sql)) {
      if (failUpdate) throw Object.assign(new Error('boom'), { code: 'ER_LOCK_DEADLOCK', errno: 1213 });
      const [value, id] = values as [string, number];
      hospitals[id][column(sql)] = value;
      return [{ affectedRows: 1 }];
    }
    throw new Error(`unexpected sql: ${sql}`);
  };
  const roStub = { query: jest.fn(async (sql: string, values: unknown[] = []) => handler(sql, values)) };
  const rwStub = {
    get: () => (!hasRw ? null : {
      query: jest.fn(),
      getConnection: async () => ({
        query: async (sql: string, values: unknown[] = []) => { rwCalls.push({ sql, values }); return handler(sql, values); },
        beginTransaction: async () => { events.push('BEGIN'); },
        commit: async () => { events.push('COMMIT'); },
        rollback: async () => { events.push('ROLLBACK'); },
        release: () => { events.push('RELEASE'); },
      }),
    }),
  };

  const tmpHex = (): string[] => readdirSync(os.tmpdir()).filter((n) => /^[0-9a-f]{32}$/.test(n));
  let tmpBefore: string[];

  async function boot(): Promise<void> {
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, HospitalsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  }

  beforeAll(() => {
    global.fetch = jest.fn(async (_url: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      phpCalls.push(cookie);
      const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      if (cookie.includes('hadmin12')) return json({ loggedIn: true, admin: admin('hospital_admin', 12) });
      if (cookie.includes('unbound')) return json({ loggedIn: true, admin: admin('hospital_super_admin', null) });
      if (cookie.includes('editor')) return json({ loggedIn: true, admin: admin('editor', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    logs.length = 0;
    const sink = (...a: unknown[]) => { logs.push(a.map(String).join(' ')); };
    jest.spyOn(Logger.prototype, 'log').mockImplementation(sink as any);
    jest.spyOn(Logger.prototype, 'warn').mockImplementation(sink as any);
    jest.spyOn(Logger.prototype, 'error').mockImplementation(sink as any);
    root = await fs.realpath(await fs.mkdtemp(path.join(os.tmpdir(), 'sb-doc-test-')));
    outside = await fs.mkdtemp(path.join(os.tmpdir(), 'sb-doc-outside-'));
    process.env.ADMIN_UPLOAD_ROOT = root;
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_WRITE = '1';
    process.env.ADMIN_HOSPITALS_DOCUMENTS = '1';
    phpCalls.length = 0; rwCalls.length = 0; events.length = 0;
    hospitals = {
      12: { status: 'approved', cert_business: null, cert_medical: null },
      13: { status: 'approved', cert_business: null, cert_medical: null },
      14: { status: 'pending', cert_business: null, cert_medical: null },
    };
    failUpdate = false;
    failLock = false;
    lockEmpty = false;
    hasRw = true;
    tmpBefore = tmpHex();
    await boot();
  });
  afterEach(async () => {
    await app.close();
    jest.restoreAllMocks();
    await fs.chmod(root, 0o755).catch(() => undefined);
    await fs.rm(root, { recursive: true, force: true });
    await fs.rm(outside, { recursive: true, force: true });
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_DOCUMENTS', 'ADMIN_UPLOAD_ROOT']) delete process.env[k];
  });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };
  const upload = (id: number | string, doc: string, file: { data: Buffer; name: string; field?: string } | null, cookie: string | undefined = SUPER, headers: Record<string, string> = MP) => {
    let r = request(app.getHttpServer()).post(`/v1/hospitals/${id}/documents/${doc}`);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    if (file) r = r.attach(file.field ?? 'file', file.data, file.name);
    else r = r.field('note', 'x');
    return r;
  };
  const del = (id: number | string, doc: string, cookie: string | undefined = SUPER, headers: Record<string, string> = JSONH) => {
    let r = request(app.getHttpServer()).delete(`/v1/hospitals/${id}/documents/${doc}`);
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r;
  };
  const f = (name = 'scan.jpg', data = jpeg()) => ({ data, name });
  const writes = () => rwCalls.filter((c) => /^UPDATE/.test(c.sql));
  const applyDir = (id: number | string = 12) => path.join(root, 'uploads', 'hospital_apply', `hospital_${id}`);
  const filesOnDisk = async (id: number | string = 12): Promise<string[]> => fs.readdir(applyDir(id)).catch(() => []);
  const noLeftovers = () => expect(tmpHex().filter((n) => !tmpBefore.includes(n))).toEqual([]);
  const cannotChmod = process.platform === 'win32' || (typeof process.getuid === 'function' && process.getuid() === 0);
  /** an earlier document of hospital 12, written the way the PHP application form does (a folder per applicant) */
  const seedOld = async (doc: 'business' | 'medical', rel = `uploads/hospital_apply/0fb9ed02f4cfc5fa029495aa0539905c/cert_${doc}_1783654260.png`) => {
    const abs = path.join(root, rel);
    await fs.mkdir(path.dirname(abs), { recursive: true });
    await fs.writeFile(abs, png());
    hospitals[12][doc === 'business' ? 'cert_business' : 'cert_medical'] = rel;
    return abs;
  };
  const exists = (p: string) => fs.stat(p).then(() => true, () => false);
  /** records every unlink inside the upload root in `events`, in order with the transaction events */
  const traceUnlinks = () => {
    const real = fs.unlink.bind(fs);
    jest.spyOn(fs, 'unlink').mockImplementation(async (p: any) => { if (String(p).startsWith(root)) events.push(`UNLINK ${path.basename(String(p))}`); return real(p); });
  };

  describe('switches, /v1/me, guard order', () => {
    it.each([undefined, '0', 'true', ''])('ADMIN_HOSPITALS_DOCUMENTS=%p -> POST and DELETE 404 (PHP and database not asked), flag false', async (v) => {
      if (v === undefined) delete process.env.ADMIN_HOSPITALS_DOCUMENTS; else process.env.ADMIN_HOSPITALS_DOCUMENTS = v;
      const a = await upload(12, 'business', f());
      expect(a.status).toBe(404);
      expect(a.body).toEqual({ error: 'not_found' });
      expect((await del(12, 'business')).status).toBe(404);
      expect(phpCalls).toHaveLength(0);
      expect(rwCalls).toHaveLength(0);
      expect((await get('/v1/me', SUPER)).body.features.hospitalsDocuments).toBe(false);
      noLeftovers();
    });

    it.each(['ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_PANEL_ENABLED'])('needs %s as well', async (k) => {
      process.env[k] = '0';
      expect((await upload(12, 'business', f())).status).toBe(404);
      expect((await del(12, 'business')).status).toBe(404);
      expect(rwCalls).toHaveLength(0);
      if (k !== 'ADMIN_PANEL_ENABLED') expect((await get('/v1/me', SUPER)).body.features.hospitalsDocuments).toBe(false);
    });

    it('all on -> /v1/me reports hospitalsDocuments for super admin and hospital roles', async () => {
      expect((await get('/v1/me', SUPER)).body.features.hospitalsDocuments).toBe(true);
      expect((await get('/v1/me', HOSPITAL)).body.features.hospitalsDocuments).toBe(true);
    });

    it('order: switch (404) comes before CSRF (403) which comes before sign-in (401)', async () => {
      process.env.ADMIN_HOSPITALS_DOCUMENTS = '0';
      expect((await upload(12, 'business', f(), undefined, {})).status).toBe(404);
      process.env.ADMIN_HOSPITALS_DOCUMENTS = '1';
      const noMarker = await upload(12, 'business', f(), undefined, {});
      expect(noMarker.status).toBe(403);
      expect(noMarker.body).toEqual({ error: 'csrf' });
      expect(phpCalls).toHaveLength(0);
      expect((await upload(12, 'business', f(), 'PHPSESSID=nobody')).status).toBe(401);
    });

    it('order: sign-in (401/403) comes before scope (404); scope 404 comes before the file is looked at', async () => {
      expect((await upload(13, 'business', f(), 'PHPSESSID=nobody')).status).toBe(401);
      const role = await upload(12, 'business', f(), EDITOR);
      expect(role.status).toBe(403);
      expect(role.body).toEqual({ error: 'forbidden' });
      expect((await del(12, 'business', EDITOR)).status).toBe(403);
      // another hospital AND a bad file AND a bad document word: still only 404
      const r = await upload(13, 'nonsense', f('x.exe', Buffer.from('MZ')), HOSPITAL);
      expect(r.status).toBe(404);
      expect(r.body).toEqual({ error: 'not_found' });
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('CSRF: no marker, JSON type on upload, multipart type on delete, foreign Origin -> 403 csrf; nothing stored', async () => {
      expect((await upload(12, 'business', f(), SUPER, {})).body).toEqual({ error: 'csrf' });
      const json = await request(app.getHttpServer()).post('/v1/hospitals/12/documents/business').set('Cookie', SUPER).set('X-Requested-With', 'admin-next').set('Content-Type', 'application/json').send('{}');
      expect(json.status).toBe(403);
      expect((await del(12, 'business', SUPER, {})).body).toEqual({ error: 'csrf' });
      expect((await del(12, 'business', SUPER, { ...MP, 'Content-Type': 'multipart/form-data; boundary=x' })).status).toBe(403);
      expect((await upload(12, 'business', f(), SUPER, { ...MP, Origin: 'https://evil.example' })).status).toBe(403);
      expect((await del(12, 'business', SUPER, { ...JSONH, Origin: 'https://evil.example' })).status).toBe(403);
      expect(rwCalls).toHaveLength(0);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('document word: only business / medical; anything else -> 404 before the body is read', async () => {
      for (const doc of ['Business', 'cert_business', 'all', '..', '%2e%2e', 'business%00', 'profile']) {
        expect((await upload(12, doc, f())).status).toBe(404);
        expect((await del(12, doc)).status).toBe(404);
      }
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('hospital: unknown, malformed, not approved -> 404', async () => {
      for (const id of [999, 14, 'abc', '0', '-1', '12abc', '012x']) {
        expect((await upload(id, 'business', f())).status).toBe(404);
        expect((await del(id, 'business')).status).toBe(404);
      }
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('hospital accounts: own hospital works (both roles); another hospital -> 404 with nothing written; an unbound account -> 404', async () => {
      expect((await upload(12, 'business', f(), HOSPITAL)).status).toBe(200);
      expect((await upload(12, 'medical', f('b.pdf', pdf()), HADMIN)).status).toBe(200);
      for (const c of [HOSPITAL, HADMIN]) {
        const other = await upload(13, 'business', f(), c);
        expect(other.status).toBe(404);
        expect(other.body).toEqual({ error: 'not_found' });
        expect((await del(13, 'business', c)).status).toBe(404);
      }
      expect(await filesOnDisk(13)).toEqual([]);
      expect((await upload(12, 'business', f(), UNBOUND)).status).toBe(404);
      expect(hospitals[13].cert_business).toBeNull();
      noLeftovers();
    });
  });

  describe('upload', () => {
    it.each([
      ['jpg', 'scan.jpg', jpeg(), 'jpg'], ['jpeg', 'scan.JPEG', jpeg(), 'jpg'], ['png', 'scan.png', png(), 'png'], ['pdf', 'doc.PDF', pdf(), 'pdf'],
    ])('%s: stored under hospital_<id> with a random name, the extension of the real type; the answer has presence flags only', async (_n, name, data, ext) => {
      const r = await upload(12, 'business', { data, name });
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ documents: { business: true, medical: false } });
      const stored = hospitals[12].cert_business as string;
      expect(stored).toMatch(new RegExp(`^uploads/hospital_apply/hospital_12/cert_business_[0-9a-f]{32}\\.${ext}$`));
      expect(PHP_VIEWER.test(stored)).toBe(true);
      expect(await filesOnDisk()).toEqual([path.basename(stored)]);
      expect((await fs.stat(path.join(root, stored))).mode & 0o777).toBe(0o644);
      expect((await fs.stat(applyDir())).mode & 0o777).toBe(0o755);
      expect(await fs.readFile(path.join(root, stored))).toEqual(data);
      expect(JSON.stringify(r.body)).not.toContain('uploads');
      expect(hospitals[12].cert_medical).toBeNull();
      noLeftovers();
    });

    it('medical goes to its own column and the other document is not touched', async () => {
      hospitals[12].cert_business = 'uploads/hospital_apply/abc/cert_business_1.png';
      const r = await upload(12, 'medical', f('l.png', png()));
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ documents: { business: true, medical: true } });
      expect(hospitals[12].cert_business).toBe('uploads/hospital_apply/abc/cert_business_1.png');
      expect(hospitals[12].cert_medical).toMatch(/^uploads\/hospital_apply\/hospital_12\/cert_medical_[0-9a-f]{32}\.png$/);
    });

    it('SQL: a locked read, then ONE parameterised UPDATE of that single column; no revisions, no updated_at, no other table', async () => {
      await upload(12, 'business', f());
      expect(events).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
      expect(rwCalls.map((c) => c.sql)).toEqual([
        'SELECT id, cert_business, cert_medical FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE',
        'UPDATE gangnamunni_hospitals SET cert_business = ? WHERE id = ?',
      ]);
      expect(rwCalls[1].values).toEqual([hospitals[12].cert_business, 12]);
      for (const c of rwCalls) {
        expect(c.sql).not.toMatch(/revisions|updated_at|has_unreviewed/);
        expect(c.sql).not.toContain('uploads/');
      }
    });

    it('client file names never reach the path or the database', async () => {
      const names: [string, Buffer][] = [['../../etc/passwd.jpg', jpeg()], ['..\\..\\x.png', png()], ['cert_medical_evil.jpg', jpeg()], ['a.php.jpg', jpeg()], ['ünï코드.pdf', pdf()]];
      for (const [name, data] of names) {
        expect((await upload(12, 'business', { data, name })).status).toBe(200);
        expect(hospitals[12].cert_business).toMatch(/^uploads\/hospital_apply\/hospital_12\/cert_business_[0-9a-f]{32}\.(jpg|png|pdf)$/);
      }
      const left = await filesOnDisk();
      expect(left).toHaveLength(1); // every replacement removed the earlier file
      expect(left[0]).toMatch(/^cert_business_[0-9a-f]{32}\.(jpg|png|pdf)$/);
      expect(await fs.readdir(root)).toEqual(['uploads']);
      expect(await fs.readdir(path.join(root, 'uploads'))).toEqual(['hospital_apply']);
    });

    it.each([
      ['PNG named .jpg', 'x.jpg', png()], ['JPEG named .png', 'x.png', jpeg()], ['PDF named .jpg', 'x.jpg', pdf()], ['JPEG named .pdf', 'x.pdf', jpeg()],
      ['text named .pdf', 'x.pdf', Buffer.from('hello, not a pdf')], ['SVG', 'x.svg', Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"></svg>')],
      ['SVG named .png', 'x.png', Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"></svg>')], ['GIF', 'x.gif', Buffer.from('GIF89a......')],
      ['WebP', 'x.webp', Buffer.concat([Buffer.from('RIFF'), Buffer.from([1, 2, 3, 4]), Buffer.from('WEBP')])], ['PHP', 'shell.php', Buffer.from('<?php echo 1;')],
      ['PHP named .jpg', 'shell.jpg', Buffer.from('<?php echo 1;')], ['double extension', 'shell.php.jpg', Buffer.from('<?php echo 1;')],
      ['no extension', 'noext', jpeg()], ['executable', 'x.exe', Buffer.from('MZ......')],
    ])('%s -> 400 invalid_file, nothing stored, no database change', async (_n, name, data) => {
      const r = await upload(12, 'business', { data, name });
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'invalid_file' });
      expect(rwCalls).toHaveLength(0);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('size: exactly 5 MB is accepted, one byte more -> 400 too_large', async () => {
      expect((await upload(12, 'business', { data: pdf(5 * 1024 * 1024), name: 'big.pdf' })).status).toBe(200);
      const r = await upload(12, 'medical', { data: pdf(5 * 1024 * 1024 + 1), name: 'big.pdf' });
      expect(r.status).toBe(400);
      expect(r.body).toEqual({ error: 'too_large' });
      expect(hospitals[12].cert_medical).toBeNull();
      expect(await filesOnDisk()).toHaveLength(1);
      noLeftovers();
    });

    it('no file -> 400 file_required; a file in another field or two files -> 400 upload_failed', async () => {
      const none = await upload(12, 'business', null);
      expect(none.status).toBe(400);
      expect(none.body).toEqual({ error: 'file_required' });
      const wrong = await upload(12, 'business', { data: jpeg(), name: 'a.jpg', field: 'image' });
      expect(wrong.status).toBe(400);
      expect(wrong.body).toEqual({ error: 'upload_failed' });
      const two = await request(app.getHttpServer()).post('/v1/hospitals/12/documents/business').set('Cookie', SUPER).set(MP).attach('file', jpeg(), 'a.jpg').attach('file', jpeg(), 'b.jpg');
      expect(two.status).toBe(400);
      expect(two.body).toEqual({ error: 'upload_failed' });
      expect(rwCalls).toHaveLength(0);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });
  });

  describe('replace: order and cleanup', () => {
    it('the old file is removed only AFTER the commit; the new one is in place', async () => {
      const old = await seedOld('business');
      traceUnlinks();
      const r = await upload(12, 'business', f('new.pdf', pdf()));
      expect(r.status).toBe(200);
      const commit = events.indexOf('COMMIT');
      expect(commit).toBeGreaterThan(-1);
      expect(events.indexOf(`UNLINK ${path.basename(old)}`)).toBeGreaterThan(commit);
      expect(await exists(old)).toBe(false);
      expect(await exists(path.join(root, hospitals[12].cert_business as string))).toBe(true);
      expect(logs.some((l) => l.includes('hospital documents: admin=7 hospital=12 doc=business replace'))).toBe(true);
      noLeftovers();
    });

    it('a failed UPDATE -> rollback, the NEW file is removed, the OLD file and value stay, 500 server_error', async () => {
      const old = await seedOld('business');
      const before = hospitals[12].cert_business;
      failUpdate = true;
      const r = await upload(12, 'business', f('new.pdf', pdf()));
      expect(r.status).toBe(500);
      expect(r.body).toEqual({ error: 'server_error' });
      expect(events).toContain('ROLLBACK');
      expect(events).not.toContain('COMMIT');
      expect(events).toContain('RELEASE');
      expect(await exists(old)).toBe(true);
      expect(hospitals[12].cert_business).toBe(before);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('write user without rights -> 503 documents_unavailable, the new file is removed, the old stays', async () => {
      const old = await seedOld('medical');
      failLock = true;
      const r = await upload(12, 'medical', f());
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'documents_unavailable' });
      expect(await exists(old)).toBe(true);
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('no write pool configured -> 503 documents_unavailable, nothing written', async () => {
      hasRw = false;
      const r = await upload(12, 'business', f());
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'documents_unavailable' });
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it('the hospital row vanished between the check and the lock -> 404, the new file is removed', async () => {
      lockEmpty = true;
      const r = await upload(12, 'business', f());
      expect(r.status).toBe(404);
      expect(events).toContain('ROLLBACK');
      expect(await filesOnDisk()).toEqual([]);
      noLeftovers();
    });

    it.each([
      ['a path outside the root', (_r: string, o: string) => [`../${path.basename(o)}/cert_business_aa.png`, path.join(o, 'cert_business_aa.png')]],
      ['a traversal in the folder part', (_r: string, o: string) => [`uploads/hospital_apply/../../${path.basename(o)}/cert_business_aa.png`, path.join(o, 'cert_business_aa.png')]],
      ['a traversal that stays below the root', (r: string) => ['uploads/hospital_apply/../protected/cert_business_aa.png', path.join(r, 'uploads', 'protected', 'cert_business_aa.png')]],
      ['another upload folder', (r: string) => ['uploads/hospitals/12/main_aa.png', path.join(r, 'uploads', 'hospitals', '12', 'main_aa.png')]],
      ['the other document (a medical file in the business column)', (r: string) => ['uploads/hospital_apply/hospital_12/cert_medical_aa.png', path.join(r, 'uploads', 'hospital_apply', 'hospital_12', 'cert_medical_aa.png')]],
      ['an extra folder level', (r: string) => ['uploads/hospital_apply/a/b/cert_business_aa.png', path.join(r, 'uploads', 'hospital_apply', 'a', 'b', 'cert_business_aa.png')]],
      ['a script extension', (r: string) => ['uploads/hospital_apply/a/cert_business_aa.php', path.join(r, 'uploads', 'hospital_apply', 'a', 'cert_business_aa.php')]],
      ['an absolute path', (r: string) => [path.join(r, 'uploads', 'hospital_apply', 'a', 'cert_business_aa.png'), path.join(r, 'uploads', 'hospital_apply', 'a', 'cert_business_aa.png')]],
    ])('old value is %s -> the file it points to is NOT removed, the replacement still succeeds', async (_n, make) => {
      const [value, victim] = make(root, outside);
      await fs.mkdir(path.dirname(victim), { recursive: true });
      await fs.writeFile(victim, 'keep me');
      hospitals[12].cert_business = value;
      const r = await upload(12, 'business', f());
      expect(r.status).toBe(200);
      expect(await fs.readFile(victim, 'utf8')).toBe('keep me');
      expect(hospitals[12].cert_business).not.toBe(value);
    });

    it('old value through a symbolic link folder that leaves the root -> the target is NOT removed', async () => {
      await fs.writeFile(path.join(outside, 'cert_business_aa.png'), 'keep me');
      await fs.mkdir(path.join(root, 'uploads', 'hospital_apply'), { recursive: true });
      await fs.symlink(outside, path.join(root, 'uploads', 'hospital_apply', 'linkdir'));
      hospitals[12].cert_business = 'uploads/hospital_apply/linkdir/cert_business_aa.png';
      expect((await upload(12, 'business', f())).status).toBe(200);
      expect(await fs.readFile(path.join(outside, 'cert_business_aa.png'), 'utf8')).toBe('keep me');
    });

    it('old value that is itself a symbolic link -> the link target is NOT removed', async () => {
      const target = path.join(outside, 'secret.png');
      await fs.writeFile(target, 'keep me');
      await fs.mkdir(path.join(root, 'uploads', 'hospital_apply', 'a'), { recursive: true });
      await fs.symlink(target, path.join(root, 'uploads', 'hospital_apply', 'a', 'cert_business_aa.png'));
      hospitals[12].cert_business = 'uploads/hospital_apply/a/cert_business_aa.png';
      expect((await upload(12, 'business', f())).status).toBe(200);
      expect(await fs.readFile(target, 'utf8')).toBe('keep me');
    });

    it('an old value whose file is already gone is not an error', async () => {
      hospitals[12].cert_business = 'uploads/hospital_apply/a/cert_business_gone.png';
      expect((await upload(12, 'business', f())).status).toBe(200);
    });

    it('the hospital folder being a symbolic link out of the root -> 503 uploads_unavailable, nothing written outside, nothing in the database', async () => {
      await fs.mkdir(path.join(root, 'uploads', 'hospital_apply'), { recursive: true });
      await fs.symlink(outside, path.join(root, 'uploads', 'hospital_apply', 'hospital_12'));
      const r = await upload(12, 'business', f());
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(await fs.readdir(outside)).toEqual([]);
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });
  });

  describe('upload root', () => {
    it.each([
      ['not set', () => { delete process.env.ADMIN_UPLOAD_ROOT; }],
      ['empty', () => { process.env.ADMIN_UPLOAD_ROOT = ''; }],
      ['relative', () => { process.env.ADMIN_UPLOAD_ROOT = 'uploads-here'; }],
      ['missing folder', () => { process.env.ADMIN_UPLOAD_ROOT = path.join(root, 'does-not-exist'); }],
    ])('%s -> 503 uploads_unavailable, nothing in the database, no path in the answer', async (_n, setup) => {
      setup();
      const r = await upload(12, 'business', f());
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(JSON.stringify(r.body)).not.toContain(root);
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });

    it('a read-only folder -> 503 uploads_unavailable', async () => {
      if (cannotChmod) return;
      await fs.chmod(root, 0o555);
      const r = await upload(12, 'business', f());
      await fs.chmod(root, 0o755);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
    });

    it('an unwritable uploads folder -> 503 uploads_unavailable and no database change', async () => {
      if (cannotChmod) return;
      await fs.mkdir(path.join(root, 'uploads'), { recursive: true });
      await fs.chmod(path.join(root, 'uploads'), 0o555);
      const r = await upload(12, 'business', f());
      await fs.chmod(path.join(root, 'uploads'), 0o755);
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'uploads_unavailable' });
      expect(rwCalls).toHaveLength(0);
      noLeftovers();
    });
  });

  describe('delete', () => {
    it('clears only that column, then removes the file (after the commit); the other document stays', async () => {
      const old = await seedOld('business');
      const keepMedical = await seedOld('medical');
      traceUnlinks();
      const r = await del(12, 'business');
      expect(r.status).toBe(200);
      expect(r.body).toEqual({ documents: { business: false, medical: true } });
      expect(hospitals[12].cert_business).toBe('');
      expect(hospitals[12].cert_medical).not.toBe('');
      expect(events.indexOf(`UNLINK ${path.basename(old)}`)).toBeGreaterThan(events.indexOf('COMMIT'));
      expect(await exists(old)).toBe(false);
      expect(await exists(keepMedical)).toBe(true);
      expect(rwCalls.map((c) => c.sql)).toEqual([
        'SELECT id, cert_business, cert_medical FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE',
        'UPDATE gangnamunni_hospitals SET cert_business = ? WHERE id = ?',
      ]);
      expect(rwCalls[1].values).toEqual(['', 12]);
      expect(logs.some((l) => l.includes('hospital documents: admin=7 hospital=12 doc=business delete'))).toBe(true);
    });

    it('hospital accounts delete their own; a second delete or a missing document -> 404 no_document and nothing is written', async () => {
      await seedOld('medical');
      expect((await del(12, 'medical', HOSPITAL)).status).toBe(200);
      const again = await del(12, 'medical', HOSPITAL);
      expect(again.status).toBe(404);
      expect(again.body).toEqual({ error: 'no_document' });
      const never = await del(12, 'business');
      expect(never.status).toBe(404);
      expect(never.body).toEqual({ error: 'no_document' });
      expect(writes()).toHaveLength(1);
      expect(events.filter((e) => e === 'ROLLBACK')).toHaveLength(2);
    });

    it('whitespace-only or empty stored values count as "no document"', async () => {
      hospitals[12].cert_business = '   ';
      expect((await del(12, 'business')).status).toBe(404);
      hospitals[12].cert_medical = '';
      expect((await del(12, 'medical')).status).toBe(404);
      expect(writes()).toHaveLength(0);
    });

    it('a failed UPDATE -> rollback, the file and the value stay, 500', async () => {
      const old = await seedOld('business');
      const before = hospitals[12].cert_business;
      failUpdate = true;
      const r = await del(12, 'business');
      expect(r.status).toBe(500);
      expect(events).toContain('ROLLBACK');
      expect(hospitals[12].cert_business).toBe(before);
      expect(await exists(old)).toBe(true);
    });

    it('write user without rights -> 503 documents_unavailable', async () => {
      await seedOld('business');
      failLock = true;
      const r = await del(12, 'business');
      expect(r.status).toBe(503);
      expect(r.body).toEqual({ error: 'documents_unavailable' });
    });

    it('an unusable upload folder does not undo the delete: the value is cleared (200), the file is left behind and only logged', async () => {
      const old = await seedOld('business');
      process.env.ADMIN_UPLOAD_ROOT = '';
      const r = await del(12, 'business');
      expect(r.status).toBe(200);
      expect(hospitals[12].cert_business).toBe('');
      expect(await exists(old)).toBe(true);
    });

    it('an old value of an unexpected shape is cleared in the database but no file is touched', async () => {
      const victim = path.join(outside, 'cert_business_aa.png');
      await fs.writeFile(victim, 'keep me');
      hospitals[12].cert_business = `../${path.basename(outside)}/cert_business_aa.png`;
      const r = await del(12, 'business');
      expect(r.status).toBe(200);
      expect(hospitals[12].cert_business).toBe('');
      expect(await fs.readFile(victim, 'utf8')).toBe('keep me');
    });

    it('a symbolic link as the stored file is never followed', async () => {
      const target = path.join(outside, 'secret.png');
      await fs.writeFile(target, 'keep me');
      await fs.mkdir(path.join(root, 'uploads', 'hospital_apply', 'a'), { recursive: true });
      await fs.symlink(target, path.join(root, 'uploads', 'hospital_apply', 'a', 'cert_business_aa.png'));
      hospitals[12].cert_business = 'uploads/hospital_apply/a/cert_business_aa.png';
      expect((await del(12, 'business')).status).toBe(200);
      expect(await fs.readFile(target, 'utf8')).toBe('keep me');
    });
  });

  describe('log and answers never carry paths, names or content', () => {
    it('upload, replace, delete and failures', async () => {
      await upload(12, 'business', f('SECRET-NAME.jpg'));
      await upload(12, 'business', f('SECRET-NAME2.pdf', pdf()));
      failUpdate = true;
      const failed = await upload(12, 'medical', f('SECRET-NAME3.png', png()));
      failUpdate = false;
      await del(12, 'business');
      const all = logs.join('\n');
      expect(all).toContain('hospital documents: admin=7 hospital=12 doc=business upload');
      expect(all).toContain('hospital documents: admin=7 hospital=12 doc=business replace');
      expect(all).toContain('hospital documents: admin=7 hospital=12 doc=business delete');
      for (const forbidden of ['SECRET-NAME', 'cert_', 'hospital_apply', root, 'uploads/', '.jpg', '.pdf', '%PDF']) expect(all).not.toContain(forbidden);
      expect(JSON.stringify(failed.body)).not.toMatch(/SECRET|cert_|hospital_apply/);
    });
  });

  describe('detail: documentsEditable', () => {
    const row = { id: 12, status: 'approved', name: 'A', has_cert_business: 1, has_cert_medical: 0 };
    const service = () => new HospitalsService({ query: async () => [[row]] } as any);
    const own = { scope: 'own' as const, hospitalIds: [12] };
    const all = { scope: 'all' as const, hospitalIds: [] };

    it('true for a super admin and for the account bound to this hospital; false for anyone else', async () => {
      expect((await service().detail(all, 12, 'super_admin'))!.documentsEditable).toBe(true);
      expect((await service().detail(own, 12, 'hospital_super_admin'))!.documentsEditable).toBe(true);
      expect((await service().detail(own, 12, 'hospital_admin'))!.documentsEditable).toBe(true);
      expect((await service().detail(own, 12, 'editor'))!.documentsEditable).toBe(false);
      expect((await service().detail(own, 12))!.documentsEditable).toBe(false);
      expect((await service().detail(all, 12, 'hospital_admin'))!.documentsEditable).toBe(false);
    });

    it('false while the switch (or one it depends on) is off; the presence flags stay', async () => {
      for (const k of ['ADMIN_HOSPITALS_DOCUMENTS', 'ADMIN_HOSPITALS_WRITE', 'ADMIN_HOSPITALS_ENABLED']) {
        const prev = process.env[k];
        process.env[k] = '0';
        const d = await service().detail(all, 12, 'super_admin');
        expect(d!.documentsEditable).toBe(false);
        expect(d!.documents).toEqual({ business: true, medical: false });
        process.env[k] = prev;
      }
    });

    it('end to end: GET /v1/hospitals/:id reports it per account', async () => {
      roStub.query.mockImplementation(async () => [[row]] as any);
      try {
        expect((await get('/v1/hospitals/12', SUPER)).body.documentsEditable).toBe(true);
        expect((await get('/v1/hospitals/12', HOSPITAL)).body.documentsEditable).toBe(true);
        process.env.ADMIN_HOSPITALS_DOCUMENTS = '0';
        expect((await get('/v1/hospitals/12', SUPER)).body.documentsEditable).toBe(false);
      } finally {
        roStub.query.mockImplementation(async (sql: string, values: unknown[] = []) => handler(sql, values));
      }
    });
  });
});
