import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import { promises as fs } from 'fs';
import * as os from 'os';
import * as path from 'path';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DoctorsModule } from './doctors.module';
import { DB, DB_RW } from '../db/db.module';
import { planMove } from '../hospitals/hospitals.order.logic';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const H = { 'X-Requested-With': 'admin-next' };

type Call = { sql: string; values: unknown[] };
type Doc = { id: number; hospital_id: number; name: string; specialty: string; photo_url: string; status: string; display_order: number | null };

describe('admin API: doctors (display order, permanent delete)', () => {
  let app: INestApplication;
  let root: string;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  const events: string[] = [];
  let doctors: Doc[];
  let tables: string[];
  let fkBlocks: boolean;
  let hasPool = true;
  let hasColumn = true;
  let deadlock = false;

  const sortSet = (hid: number) =>
    doctors.filter((d) => d.hospital_id === hid && d.status === 'active').sort((a, b) => (a.display_order ?? 1e9) - (b.display_order ?? 1e9) || a.name.localeCompare(b.name) || a.id - b.id);

  const ro = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      if (sql.includes('information_schema.COLUMNS')) return [hasColumn ? [{ 1: 1 }] : []];
      if (sql.startsWith('SELECT id, hospital_id, specialty, status FROM gangnamunni_doctors')) return [doctors.filter((d) => d.id === values[0])];
      if (sql.startsWith('SELECT id, name, specialty, photo_url FROM gangnamunni_doctors')) return [sortSet(values[0] as number)];
      if (sql.includes('FROM gangnamunni_doctors d') && sql.includes('WHERE d.id = ?')) {
        const d = doctors.find((x) => x.id === values[0]);
        return [d ? [{ ...d, hospital_name: 'H', experience: 0, bio: '', qualification: '' }] : []];
      }
      if (sql.includes('FROM gangnamunni_doctor_treatments dt')) return [[]];
      throw new Error(`unexpected ro sql: ${sql}`);
    }),
  };
  const conn = {
    beginTransaction: jest.fn(async () => { events.push('begin'); }),
    commit: jest.fn(async () => { events.push('commit'); }),
    rollback: jest.fn(async () => { events.push('rollback'); }),
    release: jest.fn(() => undefined),
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      rwCalls.push({ sql, values });
      if (sql.includes('FROM gangnamunni_doctors WHERE id = ? LIMIT 1 FOR UPDATE')) return [doctors.filter((d) => d.id === values[0])];
      if (sql.includes("WHERE hospital_id = ? AND status = 'active'") && sql.includes('FOR UPDATE')) {
        if (deadlock) throw Object.assign(new Error('deadlock'), { errno: 1213, code: 'ER_LOCK_DEADLOCK' });
        return [sortSet(values[0] as number)];
      }
      if (sql.startsWith('UPDATE gangnamunni_doctors SET display_order = ?')) { doctors.find((d) => d.id === values[1])!.display_order = values[0] as number; return [{ affectedRows: 1 }]; }
      if (sql.includes('information_schema.COLUMNS')) return [tables.includes(values[0] as string) ? [{ 1: 1 }] : []];
      if (sql.startsWith('UPDATE `')) return [{ affectedRows: 1 }];
      if (sql.startsWith('DELETE FROM `')) return [{ affectedRows: 1 }];
      if (sql.startsWith('DELETE FROM gangnamunni_doctors')) {
        if (fkBlocks) throw Object.assign(new Error('fk'), { errno: 1451, code: 'ER_ROW_IS_REFERENCED_2' });
        const i = doctors.findIndex((d) => d.id === values[0]);
        doctors.splice(i, 1);
        return [{ affectedRows: 1 }];
      }
      throw new Error(`unexpected rw sql: ${sql}`);
    }),
  };

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
    root = await fs.mkdtemp(path.join(os.tmpdir(), 'docm-'));
    Object.assign(process.env, { ADMIN_UPLOAD_ROOT: root, ADMIN_PANEL_ENABLED: '1', ADMIN_HOSPITALS_ENABLED: '1', ADMIN_DOCTORS_ENABLED: '1', ADMIN_DOCTORS_WRITE: '1', ADMIN_DOCTORS_ORDER: '1', ADMIN_DOCTORS_DELETE: '1' });
    rwCalls.length = 0;
    events.length = 0;
    fkBlocks = false;
    hasPool = true;
    hasColumn = true;
    deadlock = false;
    tables = ['gangnamunni_bookings', 'hospital_reviews', 'gangnamunni_doctor_treatments', 'gangnamunni_doctor_schedules'];
    const photo = 'uploads/doctors/doctor_4_0123456789ab.jpg';
    doctors = [
      { id: 1, hospital_id: 12, name: 'A', specialty: '', photo_url: '', status: 'active', display_order: 1 },
      { id: 2, hospital_id: 12, name: 'B', specialty: '', photo_url: '', status: 'active', display_order: 2 },
      { id: 3, hospital_id: 12, name: 'C', specialty: '', photo_url: '', status: 'active', display_order: null },
      { id: 4, hospital_id: 12, name: 'D', specialty: '', photo_url: photo, status: 'resigned', display_order: null },
      { id: 5, hospital_id: 13, name: 'E', specialty: '', photo_url: '', status: 'active', display_order: 1 },
    ];
    await fs.mkdir(path.join(root, 'uploads/doctors'), { recursive: true });
    await fs.writeFile(path.join(root, photo), 'x');
    @Global() @Module({ providers: [{ provide: DB, useValue: ro }, { provide: DB_RW, useValue: { get: () => (hasPool ? { getConnection: async () => conn } : null) } }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, DoctorsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => {
    await app.close();
    await fs.rm(root, { recursive: true, force: true });
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_DOCTORS_ENABLED', 'ADMIN_DOCTORS_WRITE', 'ADMIN_DOCTORS_ORDER', 'ADMIN_DOCTORS_DELETE', 'ADMIN_UPLOAD_ROOT']) delete process.env[k];
  });

  const post = (p: string, body: unknown, cookie: string | null = SUPER, headers: Record<string, string> = H) => {
    let r = request(app.getHttpServer()).post(p).set(headers).send(body as object);
    if (cookie) r = r.set('Cookie', cookie);
    return r;
  };
  const get = (p: string, cookie = SUPER) => request(app.getHttpServer()).get(p).set('Cookie', cookie);

  it('both switches answer 404 while off, then csrf 403 and session 401', async () => {
    process.env.ADMIN_DOCTORS_ORDER = '0';
    process.env.ADMIN_DOCTORS_DELETE = '0';
    expect((await post('/v1/doctors/1/order', { direction: 'up' })).status).toBe(404);
    expect((await post('/v1/doctors/4/delete', { confirm: true })).status).toBe(404);
    process.env.ADMIN_DOCTORS_ORDER = '1';
    process.env.ADMIN_DOCTORS_DELETE = '1';
    expect((await post('/v1/doctors/1/order', { direction: 'up' }, SUPER, {})).status).toBe(403);
    expect((await post('/v1/doctors/4/delete', { confirm: true }, SUPER, {})).status).toBe(403);
    expect((await post('/v1/doctors/1/order', { direction: 'up' }, null)).status).toBe(401);
    expect((await post('/v1/doctors/4/delete', { confirm: true }, null)).status).toBe(401);
    expect(rwCalls).toHaveLength(0);
  });

  it('delete and order need the write switch too, and me tells the flags', async () => {
    expect((await get('/v1/me')).body.features).toMatchObject({ doctorsWrite: true, doctorsOrder: true, doctorsDelete: true });
    process.env.ADMIN_DOCTORS_WRITE = '0';
    expect((await get('/v1/me')).body.features).toMatchObject({ doctorsWrite: false, doctorsOrder: false, doctorsDelete: false });
    expect((await post('/v1/doctors/1/order', { direction: 'up' })).status).toBe(404);
  });

  it('the board lists the active doctors of one hospital as positions 1..N', async () => {
    hasColumn = false;
    expect((await get('/v1/doctors/order?hospitalId=12')).body.orderable).toBe(false);
    hasColumn = true;
    const r = await get('/v1/doctors/order?hospitalId=12');
    expect(r.status).toBe(200);
    expect(r.body).toMatchObject({ hospitalId: 12, orderable: true, total: 3 });
    expect(r.body.items.map((i: any) => [i.id, i.position])).toEqual([[1, 1], [2, 2], [3, 3]]);
    expect((await get('/v1/doctors/order')).status).toBe(404);
    expect((await get('/v1/doctors/order?hospitalId=12', HOSPITAL)).status).toBe(200);
    expect((await get('/v1/doctors/order?hospitalId=13', HOSPITAL)).status).toBe(404);
  });

  it('moves a doctor to a position and renumbers only that hospital, in one transaction', async () => {
    const r = await post('/v1/doctors/3/order', { position: 1 });
    expect(r.status).toBe(200);
    expect(r.body).toMatchObject({ ok: true, id: 3, position: 1, total: 3, changed: 3 });
    expect(events).toEqual(['begin', 'commit']);
    expect(sortSet(12).map((d) => [d.id, d.display_order])).toEqual([[3, 1], [1, 2], [2, 3]]);
    expect(doctors.find((d) => d.id === 5)!.display_order).toBe(1);
    expect((await post('/v1/doctors/3/order', { direction: 'down' })).body).toMatchObject({ position: 2 });
  });

  it('refuses bad moves without writing: bad body, past the end, retired doctor, unknown id', async () => {
    hasColumn = false;
    expect((await post('/v1/doctors/1/order', { direction: 'up' })).body).toMatchObject({ error: 'order_unavailable' });
    hasColumn = true;
    expect((await post('/v1/doctors/1/order', {})).status).toBe(400);
    expect((await post('/v1/doctors/1/order', { position: 9 })).body).toMatchObject({ fields: { position: 'out_of_range' } });
    expect(events).toContain('rollback');
    expect((await post('/v1/doctors/4/order', { direction: 'up' })).status).toBe(409);
    expect((await post('/v1/doctors/99/order', { direction: 'up' })).status).toBe(404);
    expect((await post('/v1/doctors/x/order', { direction: 'up' })).status).toBe(404);
    expect(rwCalls.some((c) => c.sql.startsWith('UPDATE'))).toBe(false);
  });

  it('takes the locks set-first and answers a deadlock with a retryable 409 busy', async () => {
    await post('/v1/doctors/2/order', { direction: 'up' });
    const firstLock = rwCalls.findIndex((c) => c.sql.includes('FOR UPDATE'));
    expect(rwCalls[firstLock].sql).toContain("status = 'active'");
    deadlock = true;
    const r = await post('/v1/doctors/2/order', { direction: 'up' });
    expect(r.status).toBe(409);
    expect(r.body).toEqual({ error: 'busy' });
    expect(events[events.length - 1]).toBe('rollback');
  });

  it('a hospital account moves only inside its own hospital; 503 without a write pool', async () => {
    expect((await post('/v1/doctors/5/order', { direction: 'up' }, HOSPITAL)).status).toBe(404);
    expect((await post('/v1/doctors/2/order', { direction: 'up' }, HOSPITAL)).status).toBe(200);
    hasPool = false;
    expect((await post('/v1/doctors/2/order', { direction: 'up' })).status).toBe(503);
  });

  it('deletes a retired doctor: references cleared, relations removed, photo file removed', async () => {
    const r = await post('/v1/doctors/4/delete', { confirm: true });
    expect(r.status).toBe(200);
    expect(r.body).toEqual({ ok: true, id: 4, name: 'D' });
    expect(events).toEqual(['begin', 'commit']);
    const sqls = rwCalls.map((c) => c.sql);
    expect(sqls).toEqual(expect.arrayContaining([
      'UPDATE `gangnamunni_bookings` SET doctor_id = NULL WHERE doctor_id = ?',
      'UPDATE `hospital_reviews` SET doctor_id = NULL WHERE doctor_id = ?',
      'DELETE FROM `gangnamunni_doctor_treatments` WHERE doctor_id = ?',
      'DELETE FROM `gangnamunni_doctor_schedules` WHERE doctor_id = ?',
      'DELETE FROM gangnamunni_doctors WHERE id = ? LIMIT 1',
    ]));
    expect(doctors.some((d) => d.id === 4)).toBe(false);
    await expect(fs.stat(path.join(root, 'uploads/doctors/doctor_4_0123456789ab.jpg'))).rejects.toBeTruthy();
  });

  it('skips a table that has no doctor_id column', async () => {
    tables = ['gangnamunni_bookings'];
    expect((await post('/v1/doctors/4/delete', { confirm: true })).status).toBe(200);
    expect(rwCalls.some((c) => c.sql.includes('doctor_schedules'))).toBe(false);
  });

  it('refuses: not confirmed, active doctor, hospital account, unknown id', async () => {
    expect((await post('/v1/doctors/4/delete', {})).status).toBe(400);
    expect((await post('/v1/doctors/4/delete', { confirm: 'yes' })).status).toBe(400);
    expect((await post('/v1/doctors/1/delete', { confirm: true })).body).toMatchObject({ error: 'not_resigned' });
    expect((await post('/v1/doctors/4/delete', { confirm: true }, HOSPITAL)).status).toBe(403);
    expect((await post('/v1/doctors/99/delete', { confirm: true })).status).toBe(404);
    expect(doctors.some((d) => d.id === 4)).toBe(true);
    expect(rwCalls.some((c) => c.sql.startsWith('DELETE'))).toBe(false);
  });

  it('a foreign key that still points at the doctor rolls everything back (409) and keeps the photo', async () => {
    fkBlocks = true;
    const r = await post('/v1/doctors/4/delete', { confirm: true });
    expect(r.status).toBe(409);
    expect(r.body).toEqual({ error: 'has_references' });
    expect(events).toEqual(['begin', 'rollback']);
    await expect(fs.stat(path.join(root, 'uploads/doctors/doctor_4_0123456789ab.jpg'))).resolves.toBeTruthy();
  });

  it('a stored photo that is not one of ours (https, other folder) is never touched', async () => {
    doctors[3].photo_url = 'https://cdn.example.com/a.jpg';
    expect((await post('/v1/doctors/4/delete', { confirm: true })).status).toBe(200);
    doctors.push({ id: 6, hospital_id: 12, name: 'F', specialty: '', photo_url: '../../etc/doctor_6_0123456789ab.jpg', status: 'resigned', display_order: null });
    expect((await post('/v1/doctors/6/delete', { confirm: true })).status).toBe(200);
  });

  it('a picture whose file name belongs to another doctor is never removed', async () => {
    const other = 'uploads/doctors/doctor_9_0123456789ab.jpg';
    await fs.writeFile(path.join(root, other), 'x');
    doctors[3].photo_url = other;
    expect((await post('/v1/doctors/4/delete', { confirm: true })).status).toBe(200);
    await expect(fs.stat(path.join(root, other))).resolves.toBeTruthy();
  });

  it('planMove keeps working for doctor rows (shared logic)', () => {
    expect(planMove([{ id: 1, order: 1 }, { id: 2, order: null }], 2, { kind: 'to', position: 1 }).updates).toEqual([{ id: 2, position: 1 }, { id: 1, position: 2 }]);
  });
});
