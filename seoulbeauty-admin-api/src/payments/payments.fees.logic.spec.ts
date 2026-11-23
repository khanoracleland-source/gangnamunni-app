import { orderedHeldQuery, byHospitalQuery, byMethodQuery, byTreatmentQuery, figuresOf, FigureRow, listQuery, nextSunday, summaryQuery, toFeeSums, toItem, weeklyQuery } from './payments.logic';

const ALL = { scope: 'all' as const, hospitalIds: [] };
const range = { hospitalId: 0, from: '2026-11-01', to: '2026-11-20' };
const placeholders = (sql: string): number => (sql.match(/\?/g) || []).length;

// The same examples as the PHP page's test (tests/payment_fee_test.php): one rule, two implementations.
const card: FigureRow = { status: 'paid', amount: 45000, refundedAmount: 0, pointsUsed: 5000, payMethod: 'card', feeRateBp: 143, feeAmount: 643, bookingStatus: 'confirmed', visitDate: '2026-12-03' };
const done: FigureRow = { ...card, bookingStatus: 'completed' };
const transfer: FigureRow = { status: 'paid', amount: 45000, refundedAmount: 0, pointsUsed: 0, payMethod: 'bank_transfer', feeRateBp: 198, feeAmount: 891, bookingStatus: 'confirmed', visitDate: '2026-12-03' };

describe('settlement figures (same examples as the PHP page)', () => {
  it('next Sunday is strictly after the date', () => {
    expect(nextSunday('2026-12-03')).toBe('2026-12-06');
    expect(nextSunday('2026-12-06')).toBe('2026-12-13');
    expect(nextSunday('2026-12-07')).toBe('2026-12-13');
    expect(nextSunday('2026-12-29')).toBe('2027-01-03');
    expect(nextSunday('2026-02-30')).toBeNull();
    expect(nextSunday('')).toBeNull();
  });
  it('45 000 by card + 5 000 points -> fee 643, settlement 49 357, counted, no payout date yet', () => {
    expect(figuresOf(card, '2026-12-05')).toEqual({ state: 'counted', fee: 643, settlement: 49357, payoutDate: null, paidNet: 45000 });
  });
  it('treatment done: counted until its Sunday, received from it', () => {
    expect(figuresOf(done, '2026-12-05')).toMatchObject({ state: 'counted', payoutDate: '2026-12-06', settlement: 49357 });
    expect(figuresOf(done, '2026-12-06')).toMatchObject({ state: 'received', payoutDate: '2026-12-06', settlement: 49357 });
  });
  it('a later rate change cannot touch the stored fee', () => expect(figuresOf(card, '2027-01-01').settlement).toBe(49357));
  it('bank transfer 1.98%: fee 891, settlement 44 109', () => expect(figuresOf(transfer, '2026-12-05')).toMatchObject({ fee: 891, settlement: 44109 }));
  it('full refund -> 0; part refund removes that part and its fee (rounded down)', () => {
    expect(figuresOf({ ...card, status: 'refunded', refundedAmount: 45000 }, '2026-12-05')).toMatchObject({ state: 'refunded', fee: 0, settlement: 0 });
    expect(figuresOf({ ...card, status: 'partially_refunded', refundedAmount: 10000 }, '2026-12-05')).toMatchObject({ paidNet: 35000, fee: 500, settlement: 35000 - 500 + 5000 });
  });
  it('no fee recorded: fee and settlement are unknown, never guessed', () => {
    for (const r of [{ ...card, payMethod: '' }, { ...card, feeRateBp: null }, { ...card, feeAmount: null }]) expect(figuresOf(r, '2026-12-05')).toMatchObject({ state: 'counted', fee: null, settlement: null });
  });
  it('a bad visit date gives no payout date', () => expect(figuresOf({ ...done, visitDate: '' }, '2026-12-05').payoutDate).toBeNull());
});

describe('rows and sums', () => {
  it('toItem without the switch is unchanged; with it adds method, rate and figures', () => {
    const row = { id: 9, booking_id: 4, hospital_id: 12, amount: '45000', refunded_amount: '0', status: 'paid', booking_status: 'completed', booking_date: '2026-12-03', pay_method: 'card', fee_rate_bp: 143, fee_amount: '643', points_used: 5000 };
    expect(toItem(row)).not.toHaveProperty('settlement');
    expect(toItem(row, '2026-12-06')).toMatchObject({ payMethod: 'card', feeRateBp: 143, fee: 643, settlement: 49357, settlementState: 'received', payoutDate: '2026-12-06' });
    expect(toItem({ ...row, pay_method: null, fee_rate_bp: null, fee_amount: null }, '2026-12-06')).toMatchObject({ payMethod: '', fee: null, settlement: null });
  });
  it('toFeeSums splits counted and received', () => expect(toFeeSums({ fee: '900', settlement: '1000', settlement_received: '400', no_fee: '2' })).toEqual({ fee: 900, settlement: 1000, settlementCounted: 600, settlementReceived: 400, noFee: 2 }));
});

describe('queries: switch off is exactly as before, on adds the fee columns with matching placeholders', () => {
  const today = '2026-11-23';
  it('off: no fee column anywhere', () => {
    for (const q of [summaryQuery(ALL, range), byHospitalQuery(ALL, range), byTreatmentQuery(ALL, range), listQuery(ALL, { ...range, q: '', page: 1 })]) {
      expect(q.sql).not.toMatch(/fee_|settlement|pay_method|points_used/);
      expect(placeholders(q.sql)).toBe(q.values.length);
    }
  });
  it('on: every query keeps placeholders and values in step, and today comes first', () => {
    for (const q of [summaryQuery(ALL, range, today), byHospitalQuery(ALL, range, today), byTreatmentQuery(ALL, range, today), byMethodQuery(ALL, range, today)]) {
      expect(q.sql).toContain('settlement_received');
      expect(placeholders(q.sql)).toBe(q.values.length);
      expect(q.values[0]).toBe(today);
    }
    const l = listQuery(ALL, { ...range, q: '', page: 1 }, true);
    expect(l.sql).toContain('p.fee_rate_bp');
    expect(placeholders(l.sql)).toBe(l.values.length);
  });
  it('weekly: completed bookings with a recorded fee only, Sunday after the visit, newest first, no today needed', () => {
    const q = weeklyQuery(ALL, range);
    expect(q.sql).toContain("b.status = 'completed'");
    expect(q.sql).toContain('8 - DAYOFWEEK(b.booking_date)');
    expect(q.sql).toContain('ORDER BY payout_date DESC');
    expect(placeholders(q.sql)).toBe(q.values.length);
  });
  it('a hospital account stays inside its own hospitals in the new queries too', () => {
    const own = { scope: 'own' as const, hospitalIds: [12] };
    for (const q of [byMethodQuery(own, { ...range, hospitalId: 99 }, today), weeklyQuery(own, { ...range, hospitalId: 99 })]) expect(q.values).toContain(12);
    // asking for another hospital only narrows further (the scope clause is ANDed), never widens
    expect(weeklyQuery(own, { ...range, hospitalId: 99 }).sql).toMatch(/hospital_id IN \(\?\) AND p\.hospital_id = \?/);
  });
  it('every operand of the fee and settlement arithmetic is cast to SIGNED (UNSIGNED columns must not overflow on a odd row)', () => {
    const sql = summaryQuery(ALL, range, '2026-11-23').sql;
    for (const col of ['p.fee_rate_bp', 'COALESCE(p.points_used, 0)', 'p.fee_amount', 'p.refunded_amount']) expect(sql).toContain(`CAST(${col}`);
    expect(sql).toContain("p.pay_method <> ''");
  });
  it('ordered Held: pending payments, test providers out, scoped, not bound to dates', () => {
    const q = orderedHeldQuery({ scope: 'own', hospitalIds: [12] }, 0);
    expect(q.sql).toContain("p.status = 'pending'");
    expect(q.sql).not.toContain('paid_at');
    expect(q.values).toEqual(['mock_booking_payment', 'inicis_test', 12]);
    expect(placeholders(q.sql)).toBe(q.values.length);
  });
});
