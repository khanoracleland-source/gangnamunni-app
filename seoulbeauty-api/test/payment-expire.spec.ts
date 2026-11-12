import { PaymentExpireService } from '../src/bookings/payment-expire.service';

/** Checkout configured and open for the listed members (BookingPaymentService.liveAvailable reads exactly these). */
const ENV = (members: string): NodeJS.ProcessEnv => ({
  PG_PAYMENTS_ENABLED: '1', INICIS_MID: 'seoulbtapp', INICIS_HASHKEY: 'k'.repeat(32), INICIS_MODE: 'live', INICIS_LIVE_MEMBERS: members,
  INICIS_PUBLIC_BASE: 'https://h.test/next', DB_PAY_USER: 'sb_pay', DB_PAY_PASSWORD: 'x',
});

describe('PaymentExpireService', () => {
  const make = (o: { column?: boolean; payments?: boolean; previews?: boolean; hours?: string; rows?: any[]; expire?: (id: number) => Promise<boolean> } = {}) => {
    const calls: { sql: string; values?: unknown[] }[] = []; const expired: number[] = [];
    const db = { query: async (sql: string, values?: unknown[]) => {
      calls.push({ sql, values });
      if (/SHOW COLUMNS/.test(sql)) return [o.column === false ? [] : [{}]];
      if (/SHOW TABLES LIKE 'gangnamunni_platform_payments'/.test(sql)) return [o.payments === false ? [] : [{}]];
      if (/SHOW TABLES LIKE 'gangnamunni_booking_payment_previews'/.test(sql)) return [o.previews === false ? [] : [{}]];
      if (/payment_deadline_hours/.test(sql)) return [o.hours === undefined ? [] : [{ setting_value: o.hours }]];
      if (/FROM gangnamunni_bookings b/.test(sql)) return [o.rows ?? []];
      throw new Error(`unexpected query: ${sql}`);
    } };
    const manage = { expireUnpaid: async (id: number, hours: number) => { expired.push(id); return o.expire ? o.expire(id) : hours > 0; } };
    return { svc: new PaymentExpireService(db as any, manage as any), calls, expired };
  };
  const rows = [{ id: 11, mb_id: 'kim' }, { id: 12, mb_id: 'lee' }, { id: 13, mb_id: '' }];

  it('the checkout configuration used by these tests is really "available"', async () => {
    const { BookingPaymentService } = await import('../src/bookings/booking-payment.service');
    expect(BookingPaymentService.liveAvailable(ENV('kim'), 'kim')).toBe(true);
    expect(BookingPaymentService.liveAvailable(ENV('kim'), 'lee')).toBe(false);
    expect(BookingPaymentService.liveAvailable(ENV('*'), '')).toBe(false);
    expect(BookingPaymentService.liveAvailable({ ...ENV('*'), PG_PAYMENTS_ENABLED: '0' }, 'kim')).toBe(false);
  });
  it('dry run: lists who would be cancelled and who is left alone, changes nothing', async () => {
    const { svc, calls, expired } = make({ rows, hours: '48' });
    const r = await svc.run(true, ENV('kim'));
    expect(r).toMatchObject({ dry: true, skipped: '', hours: 48, targets: [11], notPayable: [12, 13], cancelled: 0 });
    expect(expired).toEqual([]);
    const q = calls.find((c) => /FROM gangnamunni_bookings b/.test(c.sql))!;
    expect(q.values).toEqual([48]);
    expect(q.sql).toMatch(/b\.status = 'confirmed' AND b\.confirmed_at IS NOT NULL AND b\.confirmed_at <= DATE_SUB\(NOW\(\), INTERVAL \? HOUR\)/);
    expect(q.sql).toMatch(/p\.status = 'pending' AND p\.amount > 0/);
    expect(q.sql).toMatch(/NOT EXISTS \(SELECT 1 FROM gangnamunni_platform_payments p[\s\S]*'paid', 'partially_refunded', 'refunded'/);
    expect(q.sql).toMatch(/NOT EXISTS \(SELECT 1 FROM gangnamunni_booking_payment_previews/);
    expect(calls.every((c) => /^\s*(SELECT|SHOW)/.test(c.sql))).toBe(true);
  });
  it('real run: only members who can pay are cancelled; open to everyone still skips a booking without a member', async () => {
    let { svc, expired } = make({ rows });
    let r = await svc.run(false, ENV('kim'));
    expect(expired).toEqual([11]);
    expect(r).toMatchObject({ hours: 24, cancelled: 1, unchanged: 0, failed: 0, notPayable: [12, 13] });
    ({ svc, expired } = make({ rows }));
    r = await svc.run(false, ENV('*'));
    expect(expired).toEqual([11, 12]);
    expect(r.notPayable).toEqual([13]);
  });
  it('payments off or checkout not configured: nobody is cancelled', async () => {
    const { svc, expired } = make({ rows });
    const r = await svc.run(false, { ...ENV('*'), PG_PAYMENTS_ENABLED: '0' });
    expect(expired).toEqual([]);
    expect(r).toMatchObject({ targets: [], notPayable: [11, 12, 13], cancelled: 0 });
  });
  it('counts unchanged and failed bookings and goes on', async () => {
    const { svc, expired } = make({ rows: [{ id: 1, mb_id: 'a' }, { id: 2, mb_id: 'b' }, { id: 3, mb_id: 'c' }], expire: async (id) => { if (id === 2) throw new Error('lock'); return id === 3; } });
    const r = await svc.run(false, ENV('*'));
    expect(expired).toEqual([1, 2, 3]);
    expect(r).toMatchObject({ cancelled: 1, unchanged: 1, failed: 1 });
  });
  it('deadline hours: 1–168 from the settings, anything else 24', async () => {
    for (const [v, want] of [['1', 1], ['168', 168], ['0', 24], ['169', 24], ['abc', 24], ['12.5', 24], [undefined, 24]] as const) {
      expect((await make({ hours: v as any }).svc.run(true, ENV('*'))).hours).toBe(want);
    }
  });
  it('missing column / payments table: skipped; no previews table: the query has no previews clause', async () => {
    expect((await make({ column: false }).svc.run()).skipped).toBe('no_column');
    expect((await make({ payments: false }).svc.run()).skipped).toBe('no_payments');
    const { svc, calls } = make({ previews: false });
    await svc.run(true, ENV('*'));
    expect(calls.find((c) => /FROM gangnamunni_bookings b/.test(c.sql))!.sql).not.toMatch(/payment_previews/);
  });
});
