import { createHash } from 'node:crypto';
import { Test } from '@nestjs/testing';
import { MemberNotifyService } from '../src/member-notify/member-notify.service';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { BookingsModule } from '../src/bookings/bookings.module';
import { BookingPaymentService } from '../src/bookings/booking-payment.service';
import { INICIS_FETCH } from '../src/bookings/inicis/inicis-payment.service';
import { PAY_DB } from '../src/bookings/inicis/pay-db';
import { chkFake, inicisConfig, inicisProvider } from '../src/bookings/inicis/inicis.config';
import { inicisHost, parseInicisBody } from '../src/bookings/inicis/inicis.client';
import { isPgProvider, isTestProvider } from '../src/bookings/bookings.logic';

const MID = 'INIpayTest';
const KEY = 'unit-test-hash-key';
const OID = 'BK_20261104120000_7_abcdef012345';

describe('Inicis config knobs', () => {
  const e = (o: Record<string, string>) => ({ INICIS_MID: MID, INICIS_HASHKEY: KEY, ...o }) as any;
  it('timeout: default 10 s, clamped to 3..30 s', () => {
    expect(inicisConfig(e({}))!.timeoutMs).toBe(10_000);
    expect(inicisConfig(e({ INICIS_TIMEOUT_MS: '20000' }))!.timeoutMs).toBe(20_000);
    expect(inicisConfig(e({ INICIS_TIMEOUT_MS: '50' }))!.timeoutMs).toBe(3_000);
    expect(inicisConfig(e({ INICIS_TIMEOUT_MS: '999999' }))!.timeoutMs).toBe(30_000);
    expect(inicisConfig(e({ INICIS_TIMEOUT_MS: 'abc' }))!.timeoutMs).toBe(10_000);
  });
  it('INICIS_EXTRA_TEST_MIDS adds a test MID: test mode accepts it, live mode refuses it', () => {
    expect(inicisConfig(e({ INICIS_MID: 'NEWtestMid', INICIS_EXTRA_TEST_MIDS: 'x, NEWtestMid' }))!.mode).toBe('test');
    expect(inicisConfig(e({ INICIS_MID: 'NEWtestMid' }))).toBeNull();
    expect(inicisConfig(e({ INICIS_MID: 'NEWtestMid', INICIS_EXTRA_TEST_MIDS: 'NEWtestMid', INICIS_MODE: 'live' }))).toBeNull();
  });
});

describe('Inicis config and helpers', () => {
  it('P_CHKFAKE is base64(sha512(amt+oid+timestamp+hashkey)) of the raw digest (the PHP sample: base64_encode(hash(sha512, .., true)))', () => {
    const hex = createHash('sha512').update('1000' + 'INIpayTest2026' + '1700000000000' + 'k').digest('hex');
    expect(chkFake('1000', 'INIpayTest2026', '1700000000000', 'k')).toBe(Buffer.from(hex, 'hex').toString('base64'));
    expect(chkFake(1000, 'INIpayTest2026', 1700000000000, 'k')).toBe(chkFake('1000', 'INIpayTest2026', '1700000000000', 'k'));
  });
  it('config: needs MID + hash key; live never with a test MID; test only with a test MID; https only', () => {
    expect(inicisConfig({})).toBeNull();
    expect(inicisConfig({ INICIS_MID: MID } as any)).toBeNull();
    const ok = inicisConfig({ INICIS_MID: MID, INICIS_HASHKEY: KEY } as any)!;
    expect(ok.mode).toBe('test');
    expect(ok.publicBase).toBe('https://hms.seoulbeauty.app/next');
    expect(inicisProvider(ok)).toBe('inicis_test');
    expect(inicisConfig({ INICIS_MID: MID, INICIS_HASHKEY: KEY, INICIS_MODE: 'live' } as any)).toBeNull();
    expect(inicisConfig({ INICIS_MID: 'REALMID001', INICIS_HASHKEY: KEY } as any)).toBeNull(); // real MID in test mode would charge for real
    const live = inicisConfig({ INICIS_MID: 'REALMID001', INICIS_HASHKEY: KEY, INICIS_MODE: 'live' } as any)!;
    expect(inicisProvider(live)).toBe('inicis');
    expect(inicisConfig({ INICIS_MID: MID, INICIS_HASHKEY: KEY, INICIS_PUBLIC_BASE: 'http://x.test/next' } as any)).toBeNull();
  });
  it('provider values are registered: both are PG rows, only inicis_test is a test row', () => {
    expect(isPgProvider('inicis')).toBe(true); expect(isPgProvider('inicis_test')).toBe(true);
    expect(isTestProvider('inicis')).toBe(false); expect(isTestProvider('inicis_test')).toBe(true);
  });
  it('approval host: only fc / ks / stg; parser keeps "=" inside values', () => {
    expect(inicisHost('fc')).toBe('fcpaypro.inicis.com'); expect(inicisHost('stg')).toBe('stgpaypro.inicis.com');
    for (const bad of ['', 'evil.', 'fc.evil.com/', 'FC', 'fc ']) expect(() => inicisHost(bad)).toThrow();
    expect(parseInicisBody('P_STATUS=00&P_RMESG1=a=b&junk&P_AMT=1000')).toEqual({ P_STATUS: '00', P_RMESG1: 'a=b', P_AMT: '1000' });
  });
});

describe('Inicis card payment API', () => {
  let app: INestApplication;
  let memberNotified: any[][] = [];
  let st: any;
  let inicisCalls: { url: string; body: string }[];
  let inicisReply: (url: string) => Promise<{ ok: boolean; status: number; text(): Promise<string> }>;
  const realFetch = global.fetch;
  const env = { ...process.env };
  const base = () => ({
    booking: { id: 7, hospital_id: 3, mb_id: 'kim', phone: '010-1234-5678', status: 'confirmed', booking_date: '2099-01-05', booking_time: '14:00:00', treatment: '보톡스 · 50U', hospital_name: 'A병원', name: '김민', price: 50000, h_name: 'A병원', profile_image_url: '', hospital_address: '서울 강남구 1' },
    order: { id: 31, booking_id: 7, patient_mb_id: 'kim', merchant_uid: OID, status: 'pending', amount: 50000, points_used: 0, provider: null, provider_transaction_id: null } as any,
    sql: [] as { sql: string; args: any[] }[], attempts: [] as any[], events: [] as any[], history: [] as any[], failUpdate: false, locked: true, poolDown: false, attemptCount: undefined as number | undefined,
  });
  const respond = (sql: string, args: any[]): any => {
    st.sql.push({ sql, args });
    if (/GET_LOCK/.test(sql)) return [[{ acquired: st.locked ? 1 : 0 }]];
    if (/RELEASE_LOCK/.test(sql)) return [[{ r: 1 }]];
    if (/FROM gangnamunni_bookings b LEFT JOIN/.test(sql)) return [args[0] === 7 ? [st.booking] : []];
    if (/SELECT status FROM gangnamunni_bookings WHERE id = \?/.test(sql)) return [[{ status: st.booking.status }]];
    if (/payment_type = 'booking' AND booking_id = \?/.test(sql)) return [st.order ? [st.order] : []];
    if (/WHERE merchant_uid = \? AND payment_type = 'booking'/.test(sql)) return [st.order && args[0] === st.order.merchant_uid ? [st.order] : []];
    if (/SELECT id, amount, status FROM gangnamunni_platform_payments WHERE id = \? FOR UPDATE/.test(sql)) return [[{ id: st.order.id, amount: st.order.amount, status: st.order.status }]];
    if (/COUNT\(\*\) AS c FROM gangnamunni_payment_attempts/.test(sql)) return [[{ c: st.attemptCount ?? st.attempts.length }]];
    if (/INSERT INTO gangnamunni_payment_attempts/.test(sql)) { const row = st.attempts.find((x: any) => x.key === args[3]); if (row) Object.assign(row, { outcome: args[5], reason: args[6] }); else st.attempts.push({ key: args[3], outcome: args[5], reason: args[6] }); return [{ affectedRows: 1 }]; } // UNIQUE (provider, pg_payment_key): one auth TID = one row
    if (/INSERT INTO gangnamunni_payment_events/.test(sql)) { st.events.push(args); return [{ affectedRows: 1 }]; }
    if (/INSERT INTO gangnamunni_payment_status_history/.test(sql)) { st.history.push(args); return [{ affectedRows: 1 }]; }
    if (/UPDATE gangnamunni_platform_payments SET provider/.test(sql)) {
      if (st.failUpdate) return [{ affectedRows: 0 }];
      Object.assign(st.order, { provider: args[0], provider_transaction_id: args[1], verified_amount: args[2], status: 'paid', paid_at: args[3] });
      return [{ affectedRows: 1 }];
    }
    return [{ affectedRows: 1 }];
  };
  const conn = () => ({ query: async (sql: string, args: any[] = []) => respond(sql, args), release: () => undefined });
  const fakeDb = () => ({ query: async (sql: string, args: any[] = []) => respond(sql, args), getConnection: async () => { if (st?.poolDown) throw new Error('queue limit reached'); return conn(); } });
  const approveOk = (over: Record<string, string> = {}) => ({ P_STATUS: '00', P_RMESG1: '정상', P_MID: MID, P_OID: OID, P_AMT: '50000', P_APPL_TID: 'INIpayTestTID123', P_APPL_DT: '20260105', P_APPL_TM: '120501', P_TYPE: 'CARD', ...over });
  const reply = (o: Record<string, string>) => async () => ({ ok: true, status: 200, text: async () => Object.entries(o).map(([k, v]) => `${k}=${v}`).join('&') });

  beforeAll(async () => {
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const who = (init?.headers?.cookie || '').includes('sid=kim');
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { id: 'kim', no: 42, name: '김민', nickname: 'n', level: 2, phone: '' } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useFactory: () => fakeDb() }], exports: [DB] }) class FakeDb {}
    memberNotified = [];
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, BookingsModule] })
      .overrideProvider(PAY_DB).useFactory({ factory: () => fakeDb() })
      .overrideProvider(INICIS_FETCH).useValue(async (url: string, init: any) => { inicisCalls.push({ url, body: init.body }); return inicisReply(url); })
      .overrideProvider(MemberNotifyService).useValue({ bookingLater: (...a: any[]) => { memberNotified.push(a); } })
      .compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; await app.close(); });
  beforeEach(() => {
    st = base(); inicisCalls = []; memberNotified = []; inicisReply = reply(approveOk());
    Object.assign(process.env, { INICIS_TEST_MEMBERS: 'kim, tester2', INICIS_MID: MID, INICIS_HASHKEY: KEY, PG_PAYMENTS_ENABLED: '1', DB_PAY_USER: 'u', DB_PAY_PASSWORD: 'p' });
    delete process.env.INICIS_MODE; delete process.env.INICIS_PUBLIC_BASE;
  });
  afterEach(() => { for (const k of ['INICIS_LIVE_MEMBERS', 'INICIS_TEST_MEMBERS', 'INICIS_MID', 'INICIS_HASHKEY', 'PG_PAYMENTS_ENABLED', 'DB_PAY_USER', 'DB_PAY_PASSWORD']) { if (env[k] === undefined) delete process.env[k]; else process.env[k] = env[k]; } });
  const api = () => request(app.getHttpServer());
  const authed = (r: request.Test) => r.set('Cookie', 'sid=kim').set('X-Requested-With', 'XMLHttpRequest');
  const prepare = (body: object = {}) => authed(api().post('/v1/mypage/bookings/7/payment/inicis/prepare')).send(body);
  const authResult = (over: Record<string, string> = {}) => ({ P_STATUS: '00', P_MID: MID, P_OID: OID, P_AMT: '50000', P_AUTH_TID: 'INIpayTestAUTH1', P_IDCNAME: 'fc', ...over });
  const ret = (body: Record<string, string>) => api().post('/v1/payments/inicis/return').type('form').send(body);
  const wrote = () => st.sql.some((q: any) => /UPDATE gangnamunni_platform_payments/.test(q.sql));

  it('live checkout needs MID + hash key + the payment DB account + PG_PAYMENTS_ENABLED', () => {
    expect(BookingPaymentService.liveAvailable()).toBe(true);
    delete process.env.DB_PAY_PASSWORD; expect(BookingPaymentService.liveAvailable()).toBe(false);
    process.env.DB_PAY_PASSWORD = 'p'; process.env.PG_PAYMENTS_ENABLED = '0'; expect(BookingPaymentService.liveAvailable()).toBe(false);
    process.env.PG_PAYMENTS_ENABLED = '1'; delete process.env.INICIS_HASHKEY; expect(BookingPaymentService.liveAvailable()).toBe(false);
  });

  it('test mode is limited to INICIS_TEST_MEMBERS: others see no checkout, cannot prepare, and a return for their order is refused', async () => {
    expect(BookingPaymentService.liveAvailable(process.env, 'kim')).toBe(true);
    expect(BookingPaymentService.liveAvailable(process.env, 'someone')).toBe(false);
    expect(BookingPaymentService.liveAvailable(process.env, '')).toBe(false);
    process.env.INICIS_TEST_MEMBERS = 'other'; // kim is no longer a tester
    await prepare().expect(503);
    const r = await ret(authResult()).expect(303);
    expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=UNAVAILABLE');
    expect(inicisCalls).toHaveLength(0); expect(wrote()).toBe(false);
    process.env.INICIS_TEST_MEMBERS = '';
    await prepare().expect(503);
    // live mode: allow-listed by INICIS_LIVE_MEMBERS; empty = nobody; '*' = everyone
    Object.assign(process.env, { INICIS_MID: 'REALMID001', INICIS_MODE: 'live' });
    expect(BookingPaymentService.liveAvailable(process.env, 'kim')).toBe(false);
    process.env.INICIS_LIVE_MEMBERS = 'kim';
    expect(BookingPaymentService.liveAvailable(process.env, 'kim')).toBe(true);
    expect(BookingPaymentService.liveAvailable(process.env, 'anyone')).toBe(false);
    process.env.INICIS_LIVE_MEMBERS = '*';
    expect(BookingPaymentService.liveAvailable(process.env, 'anyone')).toBe(true);
    expect(BookingPaymentService.liveAvailable(process.env, '')).toBe(false);
    delete process.env.INICIS_MODE; delete process.env.INICIS_LIVE_MEMBERS;
  });

  describe('prepare', () => {
    it('signs the amount and order id from the ledger, not from the client', async () => {
      const r = await prepare({ amount: 1, P_AMT: '1', device: 'WEB' }).expect(201);
      const p = r.body.params;
      expect(p).toMatchObject({ P_MID: MID, P_OID: OID, P_AMT: '50000', P_PAY_TYPE: 'CARD', P_DEVICE_TYPE: 'WEB', P_IDCCODE: 'Y', P_CHARSET: 'UTF-8', P_NEXT_URL: 'https://hms.seoulbeauty.app/next/api/v1/payments/inicis/return', P_CLOSE_URL: 'https://hms.seoulbeauty.app/next/mypage/bookings/7/payment' });
      expect(p.P_CHKFAKE).toBe(chkFake('50000', OID, p.P_TIMESTAMP, KEY));
      expect(p.P_TIMESTAMP).toMatch(/^\d{13}$/);
      expect(r.body.sdkUrl).toBe('https://paypro.inicis.com/std/payment/js/INIPayPro_v2.js');
      expect(JSON.stringify(r.body)).not.toContain(KEY); // the hash key never leaves the server
      expect(wrote()).toBe(false);
    });
    it('bank transfer opens the 계좌이체 window (P_PAY_TYPE BANK); unknown methods are refused', async () => {
      const r = await prepare({ method: 'bank' }).expect(201);
      expect(r.body.params.P_PAY_TYPE).toBe('BANK');
      expect(r.body.params.P_AMT).toBe('50000');
      await prepare({ method: 'card' }).expect(201).expect((x) => expect(x.body.params.P_PAY_TYPE).toBe('CARD'));
      await prepare({ method: 'naverpay' }).expect(400).expect((x) => expect(x.body.code).toBe('METHOD_NOT_SUPPORTED'));
      expect(wrote()).toBe(false);
    });
    it('mobile passes the app scheme for the card apps to return to', async () => {
      const r = await prepare({ device: 'MOBILE' }).expect(201);
      expect(r.body.params.P_DEVICE_TYPE).toBe('MOBILE');
      expect(JSON.parse(r.body.params.P_RESERVED)).toEqual({ app_scheme: 'wellness://payment' });
    });
    it('refuses: not logged in, not configured, not confirmed, not pending, points used, someone else\'s booking', async () => {
      await api().post('/v1/mypage/bookings/7/payment/inicis/prepare').set('X-Requested-With', 'XMLHttpRequest').send({}).expect(401);
      await api().post('/v1/mypage/bookings/7/payment/inicis/prepare').set('Cookie', 'sid=kim').send({}).expect(403); // same-origin guard
      delete process.env.INICIS_MID; await prepare().expect(503); process.env.INICIS_MID = MID;
      st.booking.status = 'pending'; await prepare().expect(409); st.booking.status = 'confirmed';
      st.order.status = 'paid'; await prepare().expect(409); st.order.status = 'pending';
      st.order.points_used = 1000; await prepare().expect(409).expect((r) => expect(r.body.code).toBe('POINTS_NOT_SUPPORTED')); st.order.points_used = 0;
      st.booking.mb_id = 'someone-else'; await prepare().expect(404);
    });
  });

  describe('return (P_NEXT_URL)', () => {
    it('needs no session: approves with Inicis, marks the order paid with evidence, and redirects to the success page', async () => {
      const r = await ret(authResult()).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment/success');
      expect(r.headers['cache-control']).toMatch(/no-store/);
      expect(inicisCalls).toHaveLength(1);
      expect(memberNotified).toEqual([[7, 'paid', { amount: expect.any(Number) }]]); // 설정 > 예약 · 결제 알림: 결제 완료
      expect(inicisCalls[0].url).toBe('https://fcpaypro.inicis.com/payment/v1/rest/payAppl.ini');
      expect(inicisCalls[0].body).toBe(`P_MID=${MID}&P_AUTH_TID=INIpayTestAUTH1&P_AMT=50000&P_CHARSET=UTF-8`);
      expect(st.order).toMatchObject({ status: 'paid', provider: 'inicis_test', provider_transaction_id: 'INIpayTestTID123', verified_amount: 50000, paid_at: '2026-01-05 12:05:01' });
      expect(st.events).toHaveLength(1); expect(st.events[0][2]).toBe('paid:INIpayTestTID123'); expect(st.events[0][4]).toMatch(/^[a-f0-9]{64}$/);
      expect(st.history).toHaveLength(1);
      const order = st.sql.map((q: any) => q.sql.replace(/\s+/g, ' ').slice(0, 24));
      expect(order.filter((x: string) => /START TRANSACTION|COMMIT|ROLLBACK/.test(x))).toEqual(['START TRANSACTION', 'COMMIT']);
      expect(st.attempts.map((a: any) => a.outcome)).toEqual(['paid']);
      expect(st.sql.some((q: any) => /RELEASE_LOCK/.test(q.sql))).toBe(true);
      expect(st.sql.find((q: any) => /GET_LOCK/.test(q.sql)).args[0]).toBe(`payment:${createHash('sha256').update(OID).digest('hex').slice(0, 48)}`); // PHP's lock name
    });
    it('a payment time in the future is replaced by the server time', async () => {
      inicisReply = reply(approveOk({ P_APPL_DT: '29990101' }));
      await ret(authResult()).expect(303);
      expect(st.order.paid_at.startsWith('2999')).toBe(false);
      expect(st.order.paid_at <= new Date(Date.now() + 9 * 3600e3 + 5e3).toISOString().slice(0, 19).replace('T', ' ')).toBe(true);
    });
    it('a second return (reload) approves nothing again', async () => {
      await ret(authResult()).expect(303);
      inicisCalls = [];
      const r = await ret(authResult()).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment/success');
      expect(inicisCalls).toHaveLength(0);
      expect(st.events).toHaveLength(1);
    });
    it('failed authentication with an auth tid never reaches approval and is recorded', async () => {
      const r = await ret(authResult({ P_STATUS: '01' })).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=AUTH_FAILED');
      expect(inicisCalls).toHaveLength(0); expect(wrote()).toBe(false);
      expect(st.attempts.at(-1)).toMatchObject({ outcome: 'rejected', reason: 'AUTH_01' });
    });
    it('a cancelled layer / junk post without an auth tid leaves NO evidence row (public endpoint cannot grow the table)', async () => {
      const r = await ret(authResult({ P_STATUS: '01', P_AUTH_TID: '' })).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=AUTH_FAILED');
      expect(inicisCalls).toHaveLength(0); expect(wrote()).toBe(false); expect(st.attempts).toHaveLength(0);
      const r2 = await ret(authResult({ P_AUTH_TID: '' })).expect(303);
      expect(r2.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=AUTH_INVALID');
      expect(st.attempts).toHaveLength(0);
    });
    it('one card authentication is ONE evidence row, from received to its result', async () => {
      await ret(authResult()).expect(303);
      expect(st.attempts).toEqual([{ key: 'INIpayTestAUTH1', outcome: 'paid', reason: 'CONFIRMED' }]);
    });
    it('30 authentications within the hour: no more, never approved, and the notice says nothing was charged (not BUSY)', async () => {
      st.attemptCount = 30;
      const r = await ret(authResult({ P_AUTH_TID: 'INIpayTestAUTHflood' })).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=TOO_MANY_ATTEMPTS');
      expect(inicisCalls).toHaveLength(0); expect(st.attempts).toHaveLength(0); expect(wrote()).toBe(false);
      expect(st.sql.some((q: any) => /INTERVAL 1 HOUR/.test(q.sql))).toBe(true); // a sliding window: it is not a permanent block
      st.attemptCount = 29;
      expect((await ret(authResult({ P_AUTH_TID: 'INIpayTestAUTHok' })).expect(303)).headers.location).toContain('/payment/success');
    });
    it('prepare refuses with 429 once the order has 30 authentications in the hour (before the customer authenticates a card)', async () => {
      st.attemptCount = 30;
      const r = await prepare({ device: 'WEB' });
      expect(r.status).toBe(429); expect(r.body.code).toBe('TOO_MANY_ATTEMPTS');
    });
    it('payment pool exhausted: answers BUSY (303), not a 500', async () => {
      st.poolDown = true;
      const r = await ret(authResult()).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=BUSY');
      expect(inicisCalls).toHaveLength(0);
    });
    it.each([
      ['amount differs from the ledger', { P_AMT: '1000' }, 'AMOUNT_MISMATCH'],
      ['amount is not a number', { P_AMT: '5e4' }, 'AMOUNT_MISMATCH'],
      ['another merchant id', { P_MID: 'OTHERMID' }, 'MID_MISMATCH'],
      ['an IDC code that is not fc / ks / stg', { P_IDCNAME: 'evil.example.com/x?' }, 'AUTH_INVALID'],
    ])('refuses before approval: %s', async (_n, over, code) => {
      const r = await ret(authResult(over as any)).expect(303);
      expect(r.headers.location).toBe(`/next/mypage/bookings/7/payment?pay_error=${code}`);
      expect(inicisCalls).toHaveLength(0); expect(wrote()).toBe(false);
      expect(st.attempts.at(-1)).toMatchObject({ outcome: 'refused', reason: code });
    });
    it('refuses an order that is not pending, a booking that is no longer confirmed, and an order with points', async () => {
      st.order.status = 'failed'; expect((await ret(authResult()).expect(303)).headers.location).toContain('pay_error=ORDER_STATE'); st.order.status = 'pending';
      st.booking.status = 'cancelled'; expect((await ret(authResult()).expect(303)).headers.location).toContain('pay_error=BOOKING_NOT_CONFIRMED'); st.booking.status = 'confirmed';
      st.order.points_used = 500; expect((await ret(authResult()).expect(303)).headers.location).toContain('pay_error=POINTS_NOT_SUPPORTED');
      expect(inicisCalls).toHaveLength(0);
    });
    it('unknown or malformed order ids are not even looked up', async () => {
      for (const oid of ['', 'x', "BK_1' OR 1=1", 'BK_20261104120000_7_ABCDEF012345', OID + '0']) {
        st.sql = [];
        const r = await ret(authResult({ P_OID: oid })).expect(303);
        expect(r.headers.location).toBe('/next/mypage/bookings?pay_error=UNAVAILABLE');
        expect(st.sql.some((q: any) => /platform_payments/.test(q.sql))).toBe(false);
      }
      const r = await ret(authResult({ P_OID: 'BK_20261104120000_8_abcdef012345' })).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/8/payment?pay_error=UNKNOWN_ORDER');
      expect(inicisCalls).toHaveLength(0);
    });
    it('not configured: nothing is called and nothing is written', async () => {
      delete process.env.INICIS_HASHKEY;
      const r = await ret(authResult()).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=UNAVAILABLE');
      expect(inicisCalls).toHaveLength(0); expect(st.sql).toHaveLength(0);
    });
    it('a busy payment lock redirects without approving', async () => {
      st.locked = false;
      expect((await ret(authResult()).expect(303)).headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=BUSY');
      expect(inicisCalls).toHaveLength(0);
    });
    it('Inicis refuses the approval: the order stays pending', async () => {
      inicisReply = reply({ P_STATUS: '01', P_RMESG1: 'declined' });
      const r = await ret(authResult()).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=REJECTED');
      expect(st.order.status).toBe('pending'); expect(wrote()).toBe(false);
      expect(inicisCalls).toHaveLength(1);
      expect(st.attempts.at(-1)).toMatchObject({ outcome: 'rejected', reason: 'APPROVE_01' });
    });
    it.each([
      ['a different order id', { P_OID: 'BK_20261104120000_9_abcdef012345' }],
      ['a different amount', { P_AMT: '10' }],
      ['a different merchant', { P_MID: 'OTHERMID' }],
      ['no transaction id', { P_APPL_TID: '' }],
    ])('an approval answer with %s is net-cancelled and not booked', async (_n, over) => {
      inicisReply = reply(approveOk(over as any));
      const r = await ret(authResult()).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=RESULT_MISMATCH');
      expect(inicisCalls.map((c) => c.url.split('/').pop())).toEqual(['payAppl.ini', 'payNetCancel.ini']);
      expect(st.order.status).toBe('pending'); expect(wrote()).toBe(false);
    });
    it('approval timeout: outcome unknown, so it is net-cancelled and the order stays pending', async () => {
      inicisReply = async (url) => { if (url.endsWith('payAppl.ini')) throw new Error('timeout'); return reply({ P_STATUS: '00' })(); };
      const r = await ret(authResult()).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=UNRESOLVED');
      expect(inicisCalls.map((c) => c.url.split('/').pop())).toEqual(['payAppl.ini', 'payNetCancel.ini']);
      expect(st.order.status).toBe('pending');
      expect(st.attempts.at(-1)).toMatchObject({ outcome: 'pending', reason: 'UNRESOLVED' });
    });
    it('ledger update fails after approval: rolled back and net-cancelled', async () => {
      st.failUpdate = true;
      const r = await ret(authResult()).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=FINALIZE_FAILED');
      const tx = st.sql.map((q: any) => q.sql.replace(/\s+/g, ' ').slice(0, 24)).filter((x: string) => /START TRANSACTION|COMMIT|ROLLBACK/.test(x));
      expect(tx).toEqual(['START TRANSACTION', 'ROLLBACK']);
      expect(inicisCalls.map((c) => c.url.split('/').pop())).toEqual(['payAppl.ini', 'payNetCancel.ini']);
      const cancelBody = new URLSearchParams(inicisCalls[1].body);
      expect(cancelBody.get('P_AUTH_TID')).toBe('INIpayTestAUTH1'); expect(cancelBody.get('P_OID')).toBe(OID); expect(cancelBody.get('P_AMT')).toBe('50000');
      expect(cancelBody.get('P_CHKFAKE')).toBe(chkFake('50000', OID, cancelBody.get('P_TIMESTAMP')!, KEY));
      expect(st.order.status).toBe('pending');
      expect(st.attempts.at(-1)).toMatchObject({ outcome: 'pending', reason: 'FINALIZE_FAILED' });
    });
    it('ledger update fails AND net cancel fails: user is told the card may be charged (no retry), attempt keeps the reason', async () => {
      st.failUpdate = true;
      inicisReply = async (url) => { if (url.endsWith('payNetCancel.ini')) throw new Error('down'); return reply({ P_STATUS: '00', P_OID: OID, P_AMT: '50000', P_MID: MID, P_APPL_TID: 'INIpayTestTID123', P_APPL_DT: '20260105', P_APPL_TM: '120000', P_TYPE: 'CARD' })(); };
      const r = await ret(authResult()).expect(303);
      expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=FINALIZE_FAILED_UNCANCELLED');
      expect(st.order.status).toBe('pending');
      expect(st.attempts.at(-1)).toMatchObject({ outcome: 'pending', reason: 'FINALIZE_FAILED_UNCANCELLED' });
    });
    it('a net cancel answered with a status other than 00 is NOT treated as done (alert + uncancelled notice)', async () => {
      process.env.INICIS_ALERT_WEBHOOK = 'https://hooks.example.test/x';
      try {
        st.failUpdate = true;
        inicisReply = async (url) => (url.endsWith('payNetCancel.ini') ? reply({ P_STATUS: '01' })() : reply(approveOk())());
        const r = await ret(authResult()).expect(303);
        expect(r.headers.location).toBe('/next/mypage/bookings/7/payment?pay_error=FINALIZE_FAILED_UNCANCELLED');
        const text = (global.fetch as jest.Mock).mock.calls.filter((c) => c[0] === 'https://hooks.example.test/x').map((c) => JSON.parse(c[1].body).text as string).join('\n');
        expect(text).toContain('[inicis][ALERT][NET_CANCEL_UNCONFIRMED]'); expect(text).toContain('[inicis][ALERT][FINALIZE_FAILED_UNCANCELLED]');
        expect(st.attempts.at(-1)).toMatchObject({ outcome: 'pending', reason: 'FINALIZE_FAILED_UNCANCELLED' });
      } finally { delete process.env.INICIS_ALERT_WEBHOOK; }
    });
    it('money-at-risk events go to INICIS_ALERT_WEBHOOK (https only), with order and TID but no key', async () => {
      process.env.INICIS_ALERT_WEBHOOK = 'https://hooks.example.test/x';
      try {
        st.failUpdate = true;
        inicisReply = async (url) => { if (url.endsWith('payNetCancel.ini')) throw new Error('down'); return reply(approveOk())(); };
        await ret(authResult()).expect(303);
        const hooks = (global.fetch as jest.Mock).mock.calls.filter((c) => c[0] === 'https://hooks.example.test/x');
        const text = hooks.map((c) => JSON.parse(c[1].body).text as string).join('\n');
        expect(text).toContain('[inicis][ALERT][NET_CANCEL_FAILED]'); expect(text).toContain('[inicis][ALERT][FINALIZE_FAILED_UNCANCELLED]');
        expect(text).toContain(OID); expect(text).toContain('INIpayTestTID123'); expect(text).not.toContain(KEY);
        (global.fetch as jest.Mock).mockClear();
        process.env.INICIS_ALERT_WEBHOOK = 'http://plain.example.test/x';
        st = { ...base(), failUpdate: true };
        await ret(authResult()).expect(303);
        expect((global.fetch as jest.Mock).mock.calls.some((c) => String(c[0]).includes('plain.example.test'))).toBe(false);
      } finally { delete process.env.INICIS_ALERT_WEBHOOK; }
    });
    it('uses the approval host from P_IDCNAME only for fc / ks / stg', async () => {
      await ret(authResult({ P_IDCNAME: 'ks' })).expect(303);
      expect(inicisCalls[0].url).toBe('https://kspaypro.inicis.com/payment/v1/rest/payAppl.ini');
    });
  });
});
