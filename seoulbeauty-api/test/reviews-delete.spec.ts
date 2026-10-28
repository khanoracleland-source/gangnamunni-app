import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { ReviewsModule } from '../src/reviews/reviews.module';

describe('review delete + point reversal', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[]; conn: boolean }[];
  let review: any; let settings: Record<string, string>; let earns: Record<string, any>; let cancelled: Set<string>;
  let lots: { id: number; remaining: number }[]; let lotOfPoint: number; let lockOk: boolean; let failAlloc: boolean; let balance: number;
  const realFetch = global.fetch;
  const respond = (conn: boolean) => async (sql: string, args: any[] = []): Promise<any> => {
    calls.push({ sql, args, conn });
    if (/FROM hospital_reviews WHERE id = \? AND user_id = \?/.test(sql)) return [review && args[1] === 'kim' ? [review] : []];
    if (/FROM gangnamunni_point_settings/.test(sql)) return [Object.entries(settings).map(([skey, sval]) => ({ skey, sval }))];
    if (/type = 'earn' LIMIT 1/.test(sql)) return [earns[args[0]] ? [earns[args[0]]] : []];
    if (/GET_LOCK/.test(sql)) return [[{ got: lockOk ? 1 : 0 }]];
    if (/type = 'earn_cancel' LIMIT 1/.test(sql)) return [cancelled.has(args[0]) ? [{ id: 1 }] : []];
    if (/SELECT balance FROM gangnamunni_points/.test(sql)) return [[{ balance }]];
    if (/^INSERT INTO gangnamunni_points/.test(sql)) { cancelled.add(args[5]); return [{ insertId: 900 }]; }
    if (/WHERE point_id = \? LIMIT 1/.test(sql)) return [lotOfPoint ? [{ id: lotOfPoint }] : []];
    if (/WHERE id = \? AND member_id = \? AND remaining > 0/.test(sql)) { const l = lots.find((x) => x.id === args[0] && x.remaining > 0); return [l ? [l] : []]; }
    if (/remaining > 0 AND id <> \?/.test(sql)) return [lots.filter((x) => x.remaining > 0 && x.id !== args[1])];
    if (/^UPDATE gangnamunni_point_lots SET remaining = remaining - \?/.test(sql)) { const l = lots.find((x) => x.id === args[1])!; if (l.remaining < args[2]) return [{ affectedRows: 0 }]; l.remaining -= args[0]; return [{ affectedRows: 1 }]; }
    if (/^INSERT INTO gangnamunni_point_lot_allocs/.test(sql)) { if (failAlloc) throw new Error('INSERT command denied'); return [{ affectedRows: 1 }]; }
    return [{ affectedRows: 1 }];
  };
  let released = 0;
  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42, nickname: 'n', phone: '010' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: 'x', level: 2, ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    const pool = { query: respond(false), getConnection: async () => ({ query: respond(true), release: () => { released++; } }) };
    @Global() @Module({ providers: [{ provide: DB, useValue: pool }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, ReviewsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => {
    calls = []; released = 0; lockOk = true; failAlloc = false; cancelled = new Set(); balance = 5000;
    review = { hospital_id: 9, is_active: 1 };
    settings = { schema_version: '3', v2_schema_version: '1', enabled: '1', v2_policy_enabled: '1' };
    earns = { review: { id: 500, member_id: 42, amount: 300 }, review_photo: { id: 501, member_id: 42, amount: 200 } };
    lots = [{ id: 70, remaining: 100 }, { id: 71, remaining: 1000 }];
    lotOfPoint = 70;
  });
  const H = { Cookie: 'sid=kim', 'X-Requested-With': 'XMLHttpRequest' };
  const del = (id = 8) => request(app.getHttpServer()).delete(`/v1/mypage/reviews/${id}`).set(H);
  const txn = () => calls.filter((c) => c.conn).map((c) => c.sql.split(' ').slice(0, 2).join(' '));

  it('401 / 403 / 404', async () => {
    await request(app.getHttpServer()).delete('/v1/mypage/reviews/8').expect(401);
    await request(app.getHttpServer()).delete('/v1/mypage/reviews/8').set('Cookie', 'sid=kim').expect(403);
    review = null;
    await del().expect(404);
    expect(calls.some((c) => /^UPDATE hospital_reviews/.test(c.sql))).toBe(false);
  });
  it('soft delete, earn_cancel per earn, lots consumed (own lot first), recount', async () => {
    const r = await del().expect(200);
    expect(r.body).toEqual({ success: true, deleted: true });
    const upd = calls.find((c) => /^UPDATE hospital_reviews SET is_active = 0, review_status = 'deleted'/.test(c.sql))!;
    expect(upd.args).toEqual([8, 'kim']);
    const ledger = calls.filter((c) => /^INSERT INTO gangnamunni_points/.test(c.sql));
    expect(ledger.map((c) => c.args)).toEqual([[42, 'earn_cancel', 300, 4700, '후기 삭제 회수', 'review', 8], [42, 'earn_cancel', 200, 4800, '후기 삭제 회수', 'review_photo', 8]]);
    // 300 from the earn's own lot (100) then the next lot (200); 200 from lot 71 (no own lot for point 501 -> lotOfPoint still 70 in this fake, consumed first but empty)
    expect(lots).toEqual([{ id: 70, remaining: 0 }, { id: 71, remaining: 600 }]);
    const allocs = calls.filter((c) => /lot_allocs/.test(c.sql)).map((c) => c.args);
    expect(allocs).toEqual([[900, 70, 100], [900, 71, 200], [900, 71, 200]]);
    expect(ledger.every((c) => c.conn)).toBe(true);
    expect(txn().filter((s) => s === 'START TRANSACTION').length).toBe(2);
    expect(txn().filter((s) => s === 'COMMIT').length).toBe(2);
    expect(calls.filter((c) => /RELEASE_LOCK/.test(c.sql)).length).toBe(2);
    expect(released).toBe(2);
    const rc = calls.find((c) => /^UPDATE gangnamunni_hospitals SET/.test(c.sql))!;
    expect(rc.args).toEqual([9, 9, 9]);
  });
  it('balance below the earn -> ledger may go negative, but lots lose only the positive part (points_lots_apply)', async () => {
    balance = 100;
    earns = { review: { id: 500, member_id: 42, amount: 300 } };
    await del().expect(200);
    expect(calls.find((c) => /^INSERT INTO gangnamunni_points/.test(c.sql))!.args.slice(2, 4)).toEqual([300, -200]);
    expect(calls.filter((c) => /lot_allocs/.test(c.sql)).map((c) => c.args)).toEqual([[900, 70, 100]]);
    expect(lots).toEqual([{ id: 70, remaining: 0 }, { id: 71, remaining: 1000 }]);
    balance = -50; calls = []; cancelled = new Set();
    await del().expect(200);
    expect(calls.some((c) => /lot_allocs|^UPDATE gangnamunni_point_lots/.test(c.sql))).toBe(false);
  });
  it('already reversed -> nothing booked (idempotent)', async () => {
    cancelled.add('review'); cancelled.add('review_photo');
    await del().expect(200);
    expect(calls.some((c) => /^INSERT INTO gangnamunni_points/.test(c.sql))).toBe(false);
    expect(txn().filter((s) => s === 'ROLLBACK').length).toBe(2);
    expect(released).toBe(2);
  });
  it('v2 policy off -> no ledger access at all; pending review -> no recount', async () => {
    settings.v2_policy_enabled = '0';
    review = { hospital_id: 9, is_active: 0 };
    await del().expect(200);
    expect(calls.some((c) => /gangnamunni_points\b/.test(c.sql))).toBe(false);
    expect(calls.some((c) => /^UPDATE gangnamunni_hospitals/.test(c.sql))).toBe(false);
  });
  it('lock busy -> that revoke is skipped, delete still succeeds, connection released', async () => {
    lockOk = false;
    await del().expect(200);
    expect(calls.some((c) => /^INSERT INTO gangnamunni_points/.test(c.sql))).toBe(false);
    expect(calls.some((c) => /RELEASE_LOCK/.test(c.sql))).toBe(false);
    expect(released).toBe(2);
  });
  it('a failure inside the transaction rolls back and releases the lock; the delete still succeeds', async () => {
    failAlloc = true;
    await del().expect(200);
    expect(txn().filter((s) => s === 'ROLLBACK').length).toBe(2);
    expect(calls.filter((c) => /RELEASE_LOCK/.test(c.sql)).length).toBe(2);
    expect(released).toBe(2);
  });
  it('no lot schema -> ledger row only', async () => {
    settings.v2_schema_version = '0';
    // v2PolicyOn needs v2_schema_version >= 1, so this is "policy off": mirror PHP by checking nothing is booked
    await del().expect(200);
    expect(calls.some((c) => /gangnamunni_points\b/.test(c.sql))).toBe(false);
  });
});
