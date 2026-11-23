import { Test } from '@nestjs/testing';
import { Global, INestApplication, Logger, Module } from '@nestjs/common';
import request from 'supertest';
import { AdminAuthModule } from '../admin-auth/admin-auth.module';
import { DB, DB_RW } from '../db/db.module';
import { PaymentsModule } from './payments.module';

const SUPER = 'PHPSESSID=super';
const HOSPITAL = 'PHPSESSID=hospital12';
const HEADERS = { 'Content-Type': 'application/json', 'X-Requested-With': 'admin-next' };
const admin = (role: string, hospitalId: number | null = null) => ({ id: 7, username: 'boss', name: '김관리', role, hospitalId });
const tomorrow = () => { const d = new Date(Date.now() + 9 * 3600e3 + 86400e3); return d.toISOString().slice(0, 10); };

describe('admin API: payment fee rates', () => {
  let app: INestApplication;
  const realFetch = global.fetch;
  let rwCalls: { sql: string; values: unknown[] }[] = [];
  let events: string[] = [];
  let latest: string | null = null;
  let dup = false;
  let rwEmpty = false;
  let missing = false;
  const roStub = {
    query: jest.fn(async (sql: string) => {
      if (missing) throw Object.assign(new Error('no table'), { code: 'ER_NO_SUCH_TABLE', errno: 1146 });
      if (/FROM gangnamunni_payment_fee_rates/.test(sql)) return [[
        { id: 3, pay_method: 'card', rate_bp: 143, effective_from: '2026-01-01', created_by_name: 'boss', created_at: '2026-01-01 09:00:00' },
        { id: 4, pay_method: 'bank_transfer', rate_bp: 198, effective_from: '2026-01-01', created_by_name: 'boss', created_at: '2026-01-01 09:00:00' },
      ]];
      return [[]];
    }),
  };
  const rwQuery = async (sql: string, values: unknown[] = []) => {
    rwCalls.push({ sql, values });
    if (/^SELECT effective_from/.test(sql)) return [latest ? [{ effective_from: latest }] : []];
    if (/^INSERT/.test(sql)) { if (dup) throw Object.assign(new Error('dup'), { code: 'ER_DUP_ENTRY', errno: 1062 }); return [{ insertId: 11, affectedRows: 1 }]; }
    return [[]];
  };
  const rwStub = {
    get: () => (rwEmpty ? null : { query: jest.fn(rwQuery), getConnection: async () => ({
      query: rwQuery, beginTransaction: async () => { events.push('BEGIN'); }, commit: async () => { events.push('COMMIT'); }, rollback: async () => { events.push('ROLLBACK'); }, release: () => { events.push('RELEASE'); },
    }) }),
  };

  beforeAll(() => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const cookie = init?.headers?.cookie || '';
      const json = (b: unknown) => new Response(JSON.stringify(b), { status: 200, headers: { 'content-type': 'application/json' } });
      if (cookie.includes('super')) return json({ loggedIn: true, admin: admin('super_admin') });
      if (cookie.includes('hospital12')) return json({ loggedIn: true, admin: admin('hospital_super_admin', 12) });
      return json({ loggedIn: false, admin: null });
    }) as any;
  });
  beforeEach(async () => {
    jest.spyOn(Logger.prototype, 'warn').mockImplementation(() => undefined);
    jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
    Object.assign(process.env, { ADMIN_PANEL_ENABLED: '1', ADMIN_HOSPITALS_ENABLED: '1', ADMIN_PAYMENTS_ENABLED: '1', ADMIN_PAYMENTS_FEES: '1' });
    rwCalls = []; events = []; latest = null; dup = false; rwEmpty = false; missing = false;
    @Global() @Module({ providers: [{ provide: DB, useValue: roStub }, { provide: DB_RW, useValue: rwStub }], exports: [DB, DB_RW] }) class TestDbModule {}
    const mod = await Test.createTestingModule({ imports: [TestDbModule, AdminAuthModule, PaymentsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterEach(async () => { await app.close(); jest.restoreAllMocks(); });
  afterAll(() => { global.fetch = realFetch; for (const k of ['ADMIN_PANEL_ENABLED', 'ADMIN_HOSPITALS_ENABLED', 'ADMIN_PAYMENTS_ENABLED', 'ADMIN_PAYMENTS_FEES']) delete process.env[k]; });

  const get = (cookie?: string) => { const r = request(app.getHttpServer()).get('/v1/payments/fee-rates'); return cookie ? r.set('Cookie', cookie) : r; };
  const post = (body: unknown, cookie?: string, headers: Record<string, string> = HEADERS) => {
    let r = request(app.getHttpServer()).post('/v1/payments/fee-rates');
    if (cookie) r = r.set('Cookie', cookie);
    for (const [k, v] of Object.entries(headers)) r = r.set(k, v);
    return r.send(JSON.stringify(body));
  };
  const good = () => ({ payMethod: 'card', rateBp: 195, effectiveFrom: tomorrow() });
  const noInsert = () => expect(rwCalls.filter((c) => /^INSERT/.test(c.sql))).toHaveLength(0);

  it('ADMIN_PAYMENTS_FEES off (or payments off) -> 404 on both routes, nothing is read or written', async () => {
    process.env.ADMIN_PAYMENTS_FEES = '0';
    expect((await get(SUPER)).status).toBe(404);
    expect((await post(good(), SUPER)).status).toBe(404);
    process.env.ADMIN_PAYMENTS_FEES = '1'; process.env.ADMIN_PAYMENTS_ENABLED = '0';
    expect((await get(SUPER)).status).toBe(404);
    expect((await post(good(), SUPER)).status).toBe(404);
    noInsert(); expect(rwCalls).toHaveLength(0);
  });
  it('reading: 401 without a session; a hospital admin may read; the answer lists the rate in force per method', async () => {
    expect((await get()).status).toBe(401);
    const r = await get(HOSPITAL);
    expect(r.status).toBe(200);
    expect(r.body.current.card.rateBp).toBe(143);
    expect(r.body.current.bank_transfer.rateBp).toBe(198);
    expect(r.body.items).toHaveLength(2);
  });
  it('reading: table missing -> 503 payments_unavailable', async () => {
    missing = true;
    const r = await get(SUPER);
    expect(r.status).toBe(503); expect(r.body.error).toBe('payments_unavailable');
  });
  it('writing: CSRF first (403 csrf, even without a session), then 401, then only a super admin (403)', async () => {
    expect((await post(good(), SUPER, { 'Content-Type': 'application/json' })).body.error).toBe('csrf');
    expect((await post(good(), SUPER, { 'Content-Type': 'text/plain', 'X-Requested-With': 'admin-next' })).status).toBe(403);
    expect((await post(good(), SUPER, { ...HEADERS, Origin: 'https://evil.example' })).body.error).toBe('csrf');
    expect((await post(good(), undefined)).status).toBe(401);
    const h = await post(good(), HOSPITAL);
    expect(h.status).toBe(403); expect(h.body.error).toBe('forbidden');
    noInsert(); expect(rwCalls).toHaveLength(0);
  });
  it('writing: a super admin adds a rate; the row is a parameterised INSERT with the actor, in one transaction', async () => {
    latest = '2026-01-01';
    const body = good();
    const r = await post(body, SUPER);
    expect(r.status).toBe(201);
    expect(r.body.item).toMatchObject({ payMethod: 'card', rateBp: 195, effectiveFrom: body.effectiveFrom });
    const ins = rwCalls.find((c) => /^INSERT/.test(c.sql))!;
    expect(ins.sql).not.toContain('195'); expect(ins.sql).not.toContain('boss');
    expect(ins.values).toEqual(['card', 195, body.effectiveFrom, 7, 'boss']);
    expect(events).toEqual(['BEGIN', 'COMMIT', 'RELEASE']);
    expect(rwCalls.some((c) => /^(UPDATE|DELETE)/.test(c.sql))).toBe(false);
  });
  it('writing: the first rate of a method may be dated in the past', async () => {
    const r = await post({ payMethod: 'bank_transfer', rateBp: 198, effectiveFrom: '2026-01-01' }, SUPER);
    expect(r.status).toBe(201);
  });
  it('writing: history rules (not after the latest / not from today) -> 422 and nothing inserted', async () => {
    latest = '2026-12-01';
    let r = await post({ ...good(), effectiveFrom: '2026-11-30' }, SUPER);
    expect(r.status).toBe(422); expect(r.body.errors.effectiveFrom).toBe('not_after_latest');
    latest = '2026-01-01';
    const today = new Date(Date.now() + 9 * 3600e3).toISOString().slice(0, 10);
    r = await post({ ...good(), effectiveFrom: today }, SUPER);
    expect(r.status).toBe(422); expect(r.body.errors.effectiveFrom).toBe('must_be_future');
    noInsert(); expect(events).toContain('ROLLBACK');
  });
  it('writing: bad bodies -> 400 validation, the database is not touched', async () => {
    for (const b of [{ ...good(), payMethod: 'cash' }, { ...good(), rateBp: 10001 }, { ...good(), rateBp: 1.43 }, { ...good(), effectiveFrom: 'tomorrow' }, { ...good(), id: 1 }, {}]) {
      const r = await post(b, SUPER);
      expect(r.status).toBe(400); expect(r.body.error).toBe('validation');
    }
    expect(rwCalls).toHaveLength(0);
  });
  it('writing: duplicate start date -> 409; no write user -> 503 write_unavailable', async () => {
    dup = true;
    expect((await post(good(), SUPER)).status).toBe(409);
    dup = false; rwEmpty = true;
    const r = await post(good(), SUPER);
    expect(r.status).toBe(503); expect(r.body.error).toBe('write_unavailable');
  });
});
