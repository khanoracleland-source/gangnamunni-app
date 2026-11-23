import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { MeModule } from '../me/me.module';
import { DoctorsModule } from './doctors.module';
import { DB } from '../db/db.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });

describe('admin API: doctors (read)', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  const sqls: { sql: string; values: unknown[] }[] = [];
  const doctors = [
    { id: 1, hospital_id: 12, hospital_name: 'A', name: 'Kim', specialty: '일반의', qualification: 'q', photo_url: '', status: 'active', display_order: 1, experience: 3, bio: 'b' },
    { id: 2, hospital_id: 13, hospital_name: 'B', name: 'Lee', specialty: '', qualification: '', photo_url: 'uploads/doctors/x.jpg', status: 'resigned', display_order: null, experience: 0, bio: '' },
  ];
  const stub = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      sqls.push({ sql, values });
      if (sql.includes('information_schema.COLUMNS')) return [[{ 1: 1 }]];
      if (sql.includes('COUNT(*) AS total')) return [[{ total: 2, hospitals: 2, active: 1 }]];
      if (sql.includes('COUNT(*) AS cnt')) return [[{ cnt: 2 }]];
      if (sql.includes('FROM gangnamunni_doctor_treatments dt') && sql.includes('dt.doctor_id IN')) return [[{ doctor_id: 1, name: 'Botox' }]];
      if (sql.includes('FROM gangnamunni_doctor_treatments dt')) return [[{ id: 9, name: 'Botox', category_name: 'Skin' }]];
      if (sql.includes('FROM gangnamunni_doctors d') && sql.includes('WHERE d.id = ?')) {
        const row = doctors.find((d) => d.id === values[0]);
        const scope = Array.isArray(values[1]) ? (values[1] as number[]) : null;
        return [row && (!scope || scope.includes(row.hospital_id)) ? [row] : []];
      }
      if (sql.includes('FROM gangnamunni_doctors d')) return [doctors];
      if (sql.includes('FROM gangnamunni_hospital_treatments ht')) return [[{ id: 9, name: 'Botox', category_name: 'Skin' }]];
      if (sql.includes('FROM gangnamunni_hospitals WHERE status')) return [[{ id: 12, name: 'A' }]];
      throw new Error(`unexpected sql: ${sql}`);
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
    process.env.ADMIN_PANEL_ENABLED = '1';
    process.env.ADMIN_HOSPITALS_ENABLED = '1';
    process.env.ADMIN_DOCTORS_ENABLED = '1';
    sqls.length = 0;
    @Global() @Module({ providers: [{ provide: DB, useValue: stub }], exports: [DB] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, MeModule, DoctorsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => {
    await app.close();
  });
  afterAll(() => {
    global.fetch = realFetch;
    for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_DOCTORS_ENABLED']) delete process.env[k];
  });

  const get = (p: string, cookie?: string) => { let r = request(app.getHttpServer()).get(p); if (cookie) r = r.set('Cookie', cookie); return r; };

  it('answers 404 for everybody while the switch is off, and 401 without a session while on', async () => {
    process.env.ADMIN_DOCTORS_ENABLED = '0';
    expect((await get('/v1/doctors', SUPER)).status).toBe(404);
    process.env.ADMIN_DOCTORS_ENABLED = '1';
    expect((await get('/v1/doctors')).status).toBe(401);
  });

  it('lists doctors with their treatments, totals and the order', async () => {
    const r = await get('/v1/doctors?perPage=10', SUPER);
    expect(r.status).toBe(200);
    expect(r.body.stats).toEqual({ total: 2, hospitals: 2, active: 1 });
    expect(r.body.total).toBe(2);
    expect(r.body.items[0]).toMatchObject({ id: 1, name: 'Kim', hospitalName: 'A', photoUrl: null, displayOrder: 1, treatments: ['Botox'] });
    expect(r.body.items[1]).toMatchObject({ id: 2, photoUrl: 'uploads/doctors/x.jpg', status: 'resigned', displayOrder: null });
  });

  it('keeps a hospital account to its own hospital', async () => {
    await get('/v1/doctors', HOSPITAL);
    expect(sqls.some((c) => c.sql.includes('d.hospital_id IN (?)') && JSON.stringify(c.values).includes('[12]'))).toBe(true);
    const other = await get('/v1/doctors?hospitalId=13', HOSPITAL);
    expect(other.body.items).toEqual([]);
    expect((await get('/v1/doctors/2', HOSPITAL)).status).toBe(404);
    expect((await get('/v1/doctors/treatment-options?hospitalId=13', HOSPITAL)).status).toBe(404);
    expect((await get('/v1/doctors/1', HOSPITAL)).status).toBe(200);
  });

  it('shows a doctor with experience, bio and assigned treatments; bad ids are 404', async () => {
    const r = await get('/v1/doctors/1', SUPER);
    expect(r.body).toMatchObject({ id: 1, experience: 3, bio: 'b', treatments: [{ id: 9, name: 'Botox', category: 'Skin' }] });
    for (const bad of ['0', 'abc', '99']) expect((await get(`/v1/doctors/${bad}`, SUPER)).status).toBe(404);
  });

  it('offers hospitals and the treatments of one hospital before :id', async () => {
    expect((await get('/v1/doctors/hospitals', SUPER)).body).toEqual({ items: [{ id: 12, name: 'A' }] });
    expect((await get('/v1/doctors/treatment-options?hospitalId=12', SUPER)).body).toEqual({ items: [{ id: 9, name: 'Botox', category: 'Skin' }] });
    expect((await get('/v1/doctors/treatment-options', SUPER)).status).toBe(404);
  });

  it('reports the switch in /v1/me', async () => {
    expect((await get('/v1/me', SUPER)).body.features.doctors).toBe(true);
  });
});
