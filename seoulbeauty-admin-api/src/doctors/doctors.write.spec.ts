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

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const MP = { 'X-Requested-With': 'admin-next' };
const jpeg = (size = 64) => Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(Math.max(0, size - 4), 1)]);

type Call = { sql: string; values: unknown[] };
type Doc = { id: number; hospital_id: number; name: string; specialty: string; qualification: string; photo_url: string; status: string; display_order: number | null };

describe('admin API: doctors (edit, retire)', () => {
  let app: INestApplication;
  let root: string;
  const realFetch = global.fetch;
  const rwCalls: Call[] = [];
  const events: string[] = [];
  let doctors: Doc[];
  let offered: number[];
  let failAssign: boolean;
  let hasPool = true;

  const row = (d: Doc) => ({ ...d, hospital_name: `H${d.hospital_id}`, experience: 1, bio: '' });
  const ro = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      if (sql.includes('information_schema.COLUMNS')) return [[{ 1: 1 }]];
      if (sql.startsWith('SELECT id, hospital_id, specialty, status FROM gangnamunni_doctors')) return [doctors.filter((d) => d.id === values[0])];
      if (sql.includes('FROM gangnamunni_doctors d') && sql.includes('WHERE d.id = ?')) {
        const d = doctors.find((x) => x.id === values[0]);
        const scope = Array.isArray(values[1]) ? (values[1] as number[]) : null;
        return [d && (!scope || scope.includes(d.hospital_id)) ? [row(d)] : []];
      }
      if (sql.includes('FROM gangnamunni_doctor_treatments dt')) return [[{ id: 9, name: 'Botox', category_name: 'Skin' }]];
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
      if (sql.includes("FROM gangnamunni_hospitals WHERE id = ? AND status = 'approved'")) return [[13, 12].includes(values[0] as number) ? [{ id: values[0] }] : []];
      if (sql.startsWith('UPDATE gangnamunni_doctors SET status = ?')) { const d = doctors.find((x) => x.id === values[1])!; d.status = values[0] as string; if (sql.includes('display_order = NULL')) d.display_order = null; return [{ affectedRows: 1 }]; }
      if (sql.startsWith('UPDATE gangnamunni_doctors SET status')) { const d = doctors.find((x) => x.id === values[0])!; d.status = 'resigned'; d.display_order = null; return [{ affectedRows: 1 }]; }
      if (sql.startsWith('UPDATE gangnamunni_doctors SET name')) {
        const d = doctors.find((x) => x.id === values[values.length - 1])!;
        [d.name, d.hospital_id, d.specialty, d.qualification] = values as [string, number, string, string];
        return [{ affectedRows: 1 }];
      }
      if (sql.includes('FROM gangnamunni_hospital_treatments')) return [(values[1] as number[]).filter((t) => offered.includes(t)).map((t) => ({ treatment_id: t }))];
      if (sql.startsWith('INSERT INTO gangnamunni_doctor_treatments')) { if (failAssign) throw new Error('boom'); return [{ affectedRows: 1 }]; }
      if (sql.startsWith('UPDATE gangnamunni_doctor_treatments')) return [{ affectedRows: 1 }];
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
    root = await fs.mkdtemp(path.join(os.tmpdir(), 'docw-'));
    process.env.ADMIN_UPLOAD_ROOT = root;
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_DOCTORS_ENABLED = '1';
    process.env.ADMIN_DOCTORS_WRITE = '1';
    rwCalls.length = 0;
    events.length = 0;
    failAssign = false;
    hasPool = true;
    offered = [9, 10];
    doctors = [
      { id: 1, hospital_id: 12, name: 'Kim', specialty: '일반의', qualification: '', photo_url: 'uploads/hospitals/12/doctor_1_0123456789abcdef0123456789abcdef.jpg', status: 'active', display_order: 2 },
      { id: 2, hospital_id: 13, name: 'Lee', specialty: '', qualification: '', photo_url: '', status: 'active', display_order: 1 },
      { id: 3, hospital_id: 12, name: 'Old', specialty: '', qualification: '', photo_url: '', status: 'resigned', display_order: null },
    ];
    await fs.mkdir(path.join(root, 'uploads/hospitals/12'), { recursive: true });
    await fs.writeFile(path.join(root, 'uploads/hospitals/12/doctor_1_0123456789abcdef0123456789abcdef.jpg'), 'old');
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
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_DOCTORS_ENABLED', 'ADMIN_DOCTORS_WRITE', 'ADMIN_UPLOAD_ROOT']) delete process.env[k];
  });

  const body = (o: Record<string, unknown>) => JSON.stringify({ name: 'Kim Min', hospitalId: 12, specialty: '일반의', qualification: 'KMA', treatmentIds: [9], ...o });
  const patch = (id: number | string, doctor: string | null, cookie: string | null = SUPER, photo?: Buffer, headers: Record<string, string> = MP) => {
    let r = request(app.getHttpServer()).patch(`/v1/doctors/${id}`);
    if (cookie) r = r.set('Cookie', cookie);
    r = r.set(headers);
    r = doctor !== null ? r.field('doctor', doctor) : r.field('other', '1');
    if (photo) r = r.attach('photo', photo, { filename: 'a.jpg', contentType: 'image/jpeg' });
    return r;
  };
  const retire = (id: number | string, cookie: string | null = SUPER, headers: Record<string, string> = { 'X-Requested-With': 'admin-next' }) => {
    let r = request(app.getHttpServer()).post(`/v1/doctors/${id}/retire`).set(headers).send({});
    if (cookie) r = r.set('Cookie', cookie);
    return r;
  };

  it('answers 404 while the write switch is off, 403 without the marker header, 401 without a session', async () => {
    process.env.ADMIN_DOCTORS_WRITE = '0';
    expect((await patch(1, body({}))).status).toBe(404);
    expect((await retire(1)).status).toBe(404);
    process.env.ADMIN_DOCTORS_WRITE = '1';
    expect((await patch(1, body({}), SUPER, undefined, {})).status).toBe(403);
    expect((await patch(1, body({}), null)).status).toBe(401);
    expect((await retire(1, SUPER, {})).status).toBe(403);
    expect((await retire(1, null)).status).toBe(401);
    expect(rwCalls).toHaveLength(0);
  });

  it('the me answer tells whether editing is on', async () => {
    const r = await request(app.getHttpServer()).get('/v1/me').set('Cookie', SUPER);
    expect(r.body.features.doctorsWrite).toBe(true);
    process.env.ADMIN_DOCTORS_ENABLED = '0';
    expect((await request(app.getHttpServer()).get('/v1/me').set('Cookie', SUPER)).body.features.doctorsWrite).toBe(false);
  });

  it('edits the doctor and syncs the treatments in one transaction', async () => {
    const r = await patch(1, body({ treatmentIds: [9, 10, 99] }));
    expect(r.status).toBe(200);
    expect(r.body.name).toBe('Kim Min');
    expect(events).toEqual(['begin', 'commit']);
    const update = rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_doctors SET name'))!;
    expect(update.sql).not.toContain('photo_url');
    expect(update.sql).not.toContain('display_order');
    expect(update.values).toEqual(['Kim Min', 12, '일반의', 'KMA', 1]);
    const off = rwCalls.find((c) => c.sql.includes('is_active = 0') && c.sql.includes('NOT IN'))!;
    expect(off.values).toEqual([1, [9, 10]]);
    const ins = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_doctor_treatments'))!;
    expect(ins.values).toEqual([1, 12, 9, 'basic', 1, 12, 10, 'basic']);
  });

  it('an empty treatment list switches every assignment off and inserts nothing', async () => {
    const r = await patch(1, body({ treatmentIds: [] }));
    expect(r.status).toBe(200);
    expect(rwCalls.some((c) => c.sql.startsWith('INSERT INTO gangnamunni_doctor_treatments'))).toBe(false);
    expect(rwCalls.some((c) => c.sql.includes('is_active = 0') && !c.sql.includes('NOT IN'))).toBe(true);
  });

  it('moving to another hospital puts the doctor last there', async () => {
    const r = await patch(1, body({ hospitalId: 13 }));
    expect(r.status).toBe(200);
    expect(rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_doctors SET name'))!.sql).toContain('display_order = NULL');
  });

  it('replaces the photo: the new file is written under the hospital folder, the old one removed', async () => {
    const r = await patch(1, body({}), SUPER, jpeg());
    expect(r.status).toBe(200);
    const update = rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_doctors SET name'))!;
    expect(update.sql).toContain('photo_url = ?');
    const url = update.values[4] as string;
    expect(url).toMatch(/^uploads\/hospitals\/12\/doctor_1_[0-9a-f]{32}\.jpg$/);
    await expect(fs.stat(path.join(root, url))).resolves.toBeTruthy();
    await expect(fs.stat(path.join(root, 'uploads/hospitals/12/doctor_1_0123456789abcdef0123456789abcdef.jpg'))).rejects.toBeTruthy();
  });

  it('rolls back and removes the new photo when a later step fails', async () => {
    failAssign = true;
    const r = await patch(1, body({}), SUPER, jpeg());
    expect(r.status).toBe(500);
    expect(events).toEqual(['begin', 'rollback']);
    expect(await fs.readdir(path.join(root, 'uploads/hospitals/12'))).toEqual(['doctor_1_0123456789abcdef0123456789abcdef.jpg']);
  });

  it('rejects a photo that is not a picture, and bad fields, before touching anything', async () => {
    expect((await patch(1, body({}), SUPER, Buffer.from('not an image at all'))).body).toMatchObject({ error: 'invalid_type', field: 'photo' });
    expect((await patch(1, body({ name: '  ' }))).body).toMatchObject({ error: 'name_required' });
    expect((await patch(1, body({ name: 'x'.repeat(101) }))).body).toMatchObject({ field: 'name' });
    expect((await patch(1, body({ specialty: 'Wizard' }))).body).toMatchObject({ field: 'specialty' });
    expect((await patch(1, body({ qualification: 'q'.repeat(2001) }))).body).toMatchObject({ field: 'qualification' });
    expect((await patch(1, body({ treatmentIds: ['9'] }))).body).toMatchObject({ field: 'treatmentIds' });
    expect((await patch(1, body({ hospitalId: 0 }))).body).toMatchObject({ error: 'hospital_required' });
    expect((await patch(1, 'not json')).status).toBe(400);
    expect((await patch(1, null)).status).toBe(400);
    expect(rwCalls).toHaveLength(0);
  });

  it('keeps the stored specialty acceptable even when it is not one of the choices', async () => {
    doctors[1].specialty = '구 전문의';
    expect((await patch(2, body({ hospitalId: 13, specialty: '구 전문의' }))).status).toBe(200);
  });

  it('a hospital to move to that is not approved is refused (400), a retired doctor cannot be edited (409)', async () => {
    expect((await patch(1, body({ hospitalId: 99 }))).body).toMatchObject({ field: 'hospitalId' });
    expect(events).toContain('rollback');
    expect((await patch(3, body({}))).status).toBe(409);
  });

  it('a hospital account edits only its own doctors and can not move one away', async () => {
    expect((await patch(2, body({ hospitalId: 13 }), HOSPITAL)).status).toBe(404);
    expect((await patch(1, body({ hospitalId: 13 }), HOSPITAL)).status).toBe(404);
    expect((await patch(1, body({}), HOSPITAL)).status).toBe(200);
    expect((await patch('abc', body({}))).status).toBe(404);
    expect((await patch(77, body({}))).status).toBe(404);
  });

  it('answers 503 when there is no write pool', async () => {
    hasPool = false;
    expect((await patch(1, body({}))).status).toBe(503);
    expect((await retire(1)).status).toBe(503);
  });

  it('retires a doctor: resigned, out of the order, assignments off; doing it twice changes nothing more', async () => {
    const r = await retire(1);
    expect(r.status).toBe(200);
    expect(r.body.status).toBe('resigned');
    expect(events).toEqual(['begin', 'commit']);
    expect(rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_doctors SET status'))!.sql).toContain('display_order = NULL');
    expect(rwCalls.some((c) => c.sql.startsWith('UPDATE gangnamunni_doctor_treatments SET is_active = 0'))).toBe(true);
    rwCalls.length = 0;
    expect((await retire(1)).status).toBe(200);
    expect(rwCalls.filter((c) => c.sql.startsWith('UPDATE'))).toHaveLength(0);
  });

  const toggle = (id: number | string, action: 'activate' | 'deactivate', cookie: string | null = SUPER) => {
    let r = request(app.getHttpServer()).post(`/v1/doctors/${id}/${action}`).set({ 'X-Requested-With': 'admin-next' }).send({});
    if (cookie) r = r.set('Cookie', cookie);
    return r;
  };

  it('hides a doctor (inactive: out of the order, treatments kept) and shows the doctor again; repeating changes nothing', async () => {
    const off = await toggle(1, 'deactivate');
    expect(off.status).toBe(200);
    expect(off.body.status).toBe('inactive');
    const upd = rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_doctors SET status = ?'))!;
    expect(upd.values).toEqual(['vacation', 1]);
    expect(upd.sql).toContain('display_order = NULL');
    expect(rwCalls.some((c) => c.sql.startsWith('UPDATE gangnamunni_doctor_treatments'))).toBe(false);
    rwCalls.length = 0;
    expect((await toggle(1, 'deactivate')).status).toBe(200);
    expect(rwCalls.filter((c) => c.sql.startsWith('UPDATE'))).toHaveLength(0);
    const on = await toggle(1, 'activate');
    expect(on.body.status).toBe('active');
    expect(rwCalls.find((c) => c.sql.startsWith('UPDATE gangnamunni_doctors SET status = ?'))!.values).toEqual(['active', 1]);
  });

  it('an inactive doctor can still be edited and retired; a retired one can not be switched', async () => {
    await toggle(1, 'deactivate');
    expect((await patch(1, body({}))).status).toBe(200);
    expect((await retire(1)).body.status).toBe('resigned');
    expect((await toggle(1, 'activate')).status).toBe(409);
    expect((await toggle(3, 'deactivate')).status).toBe(409);
  });

  it('switching respects the switch, the marker header, the session and the scope', async () => {
    expect((await toggle(2, 'deactivate', HOSPITAL)).status).toBe(404);
    expect((await toggle(1, 'deactivate', HOSPITAL)).status).toBe(200);
    expect((await toggle(1, 'deactivate', null)).status).toBe(401);
    expect((await request(app.getHttpServer()).post('/v1/doctors/1/deactivate').set('Cookie', SUPER).send({})).status).toBe(403);
    hasPool = false;
    expect((await toggle(1, 'activate')).status).toBe(503);
  });

  it('retire respects the scope', async () => {
    expect((await retire(2, HOSPITAL)).status).toBe(404);
    expect((await retire(1, HOSPITAL)).status).toBe(200);
    expect((await retire('x')).status).toBe(404);
  });
});
