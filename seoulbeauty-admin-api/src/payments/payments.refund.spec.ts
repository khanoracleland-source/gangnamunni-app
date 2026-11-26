import { HttpException } from '@nestjs/common';
import { PaymentRefundService } from './payments.refund.service';
import { cutBytes, inicisRefundConfig, maskTid, parseInicisAnswer, parseRefundBody, providerMatchesMode, refundBlock, refundForm, refundUrl, seoulStamp } from './payments.refund.logic';

const ID = 'a'.repeat(64);
const base = { provider: 'inicis', tid: 'INIpayTest0123456789', status: 'paid', amount: 45000, refundedAmount: 0, bookingStatus: 'cancelled', payMethod: 'card' };
const cfg = { mid: 'MID', apiKey: 'KEY', clientIp: '10.0.0.5', mode: 'live' as const, timeoutMs: 5000 };

describe('refund rules (pure)', () => {
  it('allows a paid, cancelled-booking Inicis payment and refunds only what the customer paid', () => {
    expect(refundBlock(base)).toBeNull();
  });
  it.each([
    [{ provider: 'mock_booking_payment' }, 'not_pg'],
    [{ tid: '' }, 'no_tid'],
    [{ status: 'pending' }, 'not_paid'],
    [{ status: 'refunded' }, 'not_paid'],
    [{ payMethod: 'bank_transfer' }, 'method_unsupported'],
    [{ bookingStatus: 'confirmed' }, 'booking_not_cancelled'],
    [{ status: 'partially_refunded', refundedAmount: 45000 }, 'nothing_left'],
  ])('blocks %j as %s', (patch, why) => {
    expect(refundBlock({ ...base, ...patch })).toBe(why);
  });
  it('validates the body: a 64-hex request id and one of the listed reasons', () => {
    expect(parseRefundBody({ requestId: ID, reason: '고객 변심' })).toEqual({ ok: true, requestId: ID, reason: '고객 변심' });
    expect(parseRefundBody({ requestId: 'short', reason: 'x' })).toEqual({ ok: false, errors: { requestId: 'invalid', reason: 'invalid' } });
    expect(parseRefundBody(null).ok).toBe(false);
  });
  it('reads the settings only when complete and keeps live and sandbox apart', () => {
    expect(inicisRefundConfig({ INICIS_MID: 'm', INICIS_INIAPI_KEY: 'k', INICIS_CLIENT_IP: 'not-an-ip' } as any)).toBeNull();
    expect(inicisRefundConfig({ INICIS_MID: 'm', INICIS_INIAPI_KEY: 'k', INICIS_CLIENT_IP: '1.2.3.4', INICIS_MODE: 'live' } as any)?.mode).toBe('live');
    expect(inicisRefundConfig({ INICIS_MID: 'm', INICIS_INIAPI_KEY: 'k', INICIS_CLIENT_IP: '1.2.3.4' } as any)?.mode).toBe('test');
    expect(providerMatchesMode('inicis', 'live')).toBe(true);
    expect(providerMatchesMode('inicis', 'test')).toBe(false);
    expect(providerMatchesMode('inicis_test', 'test')).toBe(true);
    expect(refundUrl('live')).toBe('https://iniapi.inicis.com/api/v1/refund');
    expect(refundUrl('test')).toBe('https://stginiapi.inicis.com/api/v1/refund');
  });
  it('signs the form like the PHP admin: sha512(key+type+paymethod+timestamp+ip+mid+tid)', () => {
    const f = refundForm(cfg, 'TID1', '고객 변심', '20261126120000');
    expect(f).toMatchObject({ type: 'Refund', paymethod: 'Card', clientIp: '10.0.0.5', mid: 'MID', tid: 'TID1', msg: '고객 변심' });
    expect(f.hashData).toBe(require('crypto').createHash('sha512').update('KEYRefundCard2026112612000010.0.0.5MIDTID1').digest('hex'));
    expect(seoulStamp(new Date('2026-11-26T03:00:00Z'))).toBe('20261126120000');
  });
  it('cuts the message at 80 bytes without splitting a character, and masks the id', () => {
    expect(Buffer.byteLength(cutBytes('가'.repeat(40), 80))).toBeLessThanOrEqual(80);
    expect(cutBytes('가나다', 7)).toBe('가나');
    expect(maskTid('INIpayTest0123456789')).toBe('INIp…6789');
  });
  it('tells a clear answer from an unknown one', () => {
    expect(parseInicisAnswer(200, '{"resultCode":"00","cancelDate":"20261126","cancelTime":"120102"}')).toEqual({ kind: 'ok', code: '00', cancelledAt: '20261126120102' });
    expect(parseInicisAnswer(200, '{"resultCode":"01"}')).toEqual({ kind: 'rejected', code: '01' });
    expect(parseInicisAnswer(500, '{"resultCode":"00"}').kind).toBe('unknown');
    expect(parseInicisAnswer(200, 'not json').kind).toBe('unknown');
  });
});

type Call = { sql: string; values: unknown[] };
function setup(row: Record<string, unknown> | null, extra: { prev?: Record<string, unknown> | null; open?: boolean } = {}) {
  const calls: Call[] = [];
  const conn = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      calls.push({ sql, values });
      if (sql.includes('GET_LOCK')) return [[{ acquired: 1 }]];
      if (sql.includes('FROM gangnamunni_platform_payments p')) return [row ? [row] : []];
      if (sql.includes('SELECT payment_id, amount, status FROM gangnamunni_payment_refund_requests')) return [extra.prev ? [extra.prev] : []];
      if (sql.includes("status = 'pending' LIMIT 1")) return [extra.open ? [{ request_id: 'x' }] : []];
      return [{ affectedRows: 1 }];
    }),
    beginTransaction: jest.fn(async () => undefined), commit: jest.fn(async () => undefined), rollback: jest.fn(async () => undefined), release: jest.fn(),
  };
  const notify = { later: jest.fn() };
  const svc = new PaymentRefundService({ query: jest.fn() } as any, { get: () => ({ query: jest.fn(), getConnection: async () => conn }) } as any, { get: () => notify } as any);
  return { svc, conn, calls, notify };
}
const paid = { id: 9, booking_id: 4, hospital_id: 12, hospital_name: 'H', patient_name: '김', patient_phone: '010-1234-5678', treatment_name: 't', amount: '45000', refunded_amount: '0', status: 'paid', paid_at: '2026-11-24 14:02:00', refunded_at: null, provider: 'inicis', provider_transaction_id: 'INIpayTest0123456789', merchant_uid: 'M-9', booking_status: 'cancelled', booking_date: '2026-12-01', booking_time: '10:00:00' };
const ALL = { scope: 'all' as const, hospitalIds: [] };
const actor = { id: 7, name: '김관리' };

describe('PaymentRefundService.refund', () => {
  const realFetch = global.fetch;
  const env = { ...process.env };
  beforeEach(() => { Object.assign(process.env, { INICIS_MID: 'MID', INICIS_INIAPI_KEY: 'KEY', INICIS_CLIENT_IP: '10.0.0.5', INICIS_MODE: 'live' }); });
  afterEach(() => { global.fetch = realFetch; process.env = { ...env }; });
  const answer = (b: unknown, status = 200) => { global.fetch = jest.fn(async () => new Response(JSON.stringify(b), { status })) as any; return global.fetch as jest.Mock; };

  it('cancels at Inicis and records the 45,000 the customer paid everywhere', async () => {
    const f = answer({ resultCode: '00', cancelDate: '20261126', cancelTime: '120102' });
    const { svc, conn, calls, notify } = setup(paid);
    await expect(svc.refund(ALL, actor, '9', { requestId: ID, reason: '고객 변심' })).resolves.toEqual({ ok: true, refunded: 45000, status: 'refunded' });
    expect(f).toHaveBeenCalledTimes(1);
    expect(String(f.mock.calls[0][0])).toBe('https://iniapi.inicis.com/api/v1/refund');
    expect(String(f.mock.calls[0][1].body)).toContain('tid=INIpayTest0123456789');
    const update = calls.find((c) => c.sql.startsWith('UPDATE gangnamunni_platform_payments'));
    expect(update?.values).toEqual([45000, 'refunded', 9]);
    expect(calls.some((c) => c.sql.includes('INSERT INTO gangnamunni_payment_refund_requests') && c.values[2] === 45000)).toBe(true);
    expect(calls.some((c) => c.sql.includes('UPDATE gangnamunni_bookings') && c.values[0] === 4)).toBe(true);
    expect(calls.some((c) => c.sql.includes('INSERT INTO gangnamunni_refund_audit') && c.values[2] === 45000 && c.values[4] === '김관리')).toBe(true);
    expect(conn.commit).toHaveBeenCalled();
    expect(calls.some((c) => c.sql.includes('RELEASE_LOCK'))).toBe(true);
    expect(conn.release).toHaveBeenCalled();
    expect(notify.later).toHaveBeenCalledWith(4, 'refunded');
  });

  it('never calls Inicis while the booking is not cancelled', async () => {
    const f = answer({ resultCode: '00' });
    const { svc } = setup({ ...paid, booking_status: 'confirmed' });
    await expect(svc.refund(ALL, actor, '9', { requestId: ID, reason: '고객 변심' })).rejects.toMatchObject({ response: { error: 'refund_blocked', reason: 'booking_not_cancelled' } });
    expect(f).not.toHaveBeenCalled();
  });

  it('refuses a live payment against the sandbox gateway', async () => {
    process.env.INICIS_MODE = 'test';
    const f = answer({ resultCode: '00' });
    const { svc } = setup(paid);
    await expect(svc.refund(ALL, actor, '9', { requestId: ID, reason: '고객 변심' })).rejects.toMatchObject({ response: { reason: 'mode_mismatch' } });
    expect(f).not.toHaveBeenCalled();
  });

  it('answers 404 for a payment outside the admin scope and sends nothing', async () => {
    const f = answer({ resultCode: '00' });
    const { svc, calls } = setup(null);
    await expect(svc.refund({ scope: 'own', hospitalIds: [5] }, actor, '9', { requestId: ID, reason: '고객 변심' })).rejects.toMatchObject({ status: 404 });
    const q = calls.find((c) => c.sql.includes('FROM gangnamunni_platform_payments p'));
    expect(q?.sql).toContain('p.hospital_id IN (?)');
    expect(q?.values).toEqual([9, 5]);
    expect(f).not.toHaveBeenCalled();
  });

  it('marks the request failed and leaves the ledger alone when Inicis refuses', async () => {
    answer({ resultCode: '01' });
    const { svc, calls, conn } = setup(paid);
    await expect(svc.refund(ALL, actor, '9', { requestId: ID, reason: '고객 변심' })).rejects.toMatchObject({ status: 422, response: { error: 'rejected', code: '01' } });
    expect(calls.some((c) => c.sql.includes("status = 'failed'"))).toBe(true);
    expect(calls.some((c) => c.sql.startsWith('UPDATE gangnamunni_platform_payments'))).toBe(false);
    expect(conn.commit).not.toHaveBeenCalled();
  });

  it('keeps the request pending (no ledger change) when the answer is unknown', async () => {
    global.fetch = jest.fn(async () => { throw new Error('timeout'); }) as any;
    const { svc, calls } = setup(paid);
    await expect(svc.refund(ALL, actor, '9', { requestId: ID, reason: '고객 변심' })).rejects.toMatchObject({ status: 502, response: { error: 'outcome_unknown' } });
    expect(calls.some((c) => c.sql.includes("status = 'failed'"))).toBe(false);
    expect(calls.some((c) => c.sql.startsWith('UPDATE gangnamunni_platform_payments'))).toBe(false);
  });

  it('blocks a new request while an earlier one is unresolved', async () => {
    const f = answer({ resultCode: '00' });
    const { svc } = setup(paid, { open: true });
    await expect(svc.refund(ALL, actor, '9', { requestId: ID, reason: '고객 변심' })).rejects.toMatchObject({ response: { error: 'reconcile_required' } });
    expect(f).not.toHaveBeenCalled();
  });

  it('is idempotent: a request id that already succeeded refunds nothing again', async () => {
    const f = answer({ resultCode: '00' });
    const { svc } = setup({ ...paid, status: 'refunded', refunded_amount: '45000', booking_status: 'refunded' }, { prev: { payment_id: 9, amount: 45000, status: 'succeeded' } });
    await expect(svc.refund(ALL, actor, '9', { requestId: ID, reason: '고객 변심' })).resolves.toMatchObject({ ok: true, refunded: 45000 });
    expect(f).not.toHaveBeenCalled();
  });

  it('answers 503 when the Inicis settings are missing and 400 for a bad body', async () => {
    delete process.env.INICIS_INIAPI_KEY;
    const { svc } = setup(paid);
    await expect(svc.refund(ALL, actor, '9', { requestId: ID, reason: '고객 변심' })).rejects.toBeInstanceOf(HttpException);
    await expect(svc.refund(ALL, actor, '9', { requestId: 'x', reason: '' })).rejects.toMatchObject({ status: 400 });
  });
});
