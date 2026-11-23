import { feeOf, orderError, parseFeeBody, parseRateId, rateAt, removable, FeeRate } from './payment-fees.logic';

describe('fee rounding (shared with the PHP revenue page)', () => {
  // The same cases are asserted in the PHP test of the revenue page: amount paid by card/transfer, rate in bp -> fee (rounded down).
  it.each([
    [45000, 143, 643], // 45 000 x 1.43% = 643.5 -> 643
    [45000, 198, 891], // 45 000 x 1.98% = 891
    [45000, 195, 877], // 45 000 x 1.95% = 877.5 -> 877
    [1, 143, 0], [0, 143, 0], [45000, 0, 0], [100, 10000, 100], [999, 143, 14],
  ])('feeOf(%i, %i bp) = %i', (paid, bp, fee) => expect(feeOf(paid, bp)).toBe(fee));
  it('hospital settlement = (paid - fee) + points: 50 000 won, 5 000 points, 45 000 by card', () => {
    const paid = 45000, points = 5000;
    expect(paid - feeOf(paid, 143) + points).toBe(49357);
  });
});

describe('rateAt: the rate follows the payment date', () => {
  const rows: FeeRate[] = [
    { payMethod: 'card', rateBp: 143, effectiveFrom: '2026-01-01' },
    { payMethod: 'card', rateBp: 195, effectiveFrom: '2026-12-01' },
    { payMethod: 'bank_transfer', rateBp: 198, effectiveFrom: '2026-06-01' },
  ];
  it('picks the latest start on or before the day, per method', () => {
    expect(rateAt(rows, 'card', '2026-11-30')).toBe(143);
    expect(rateAt(rows, 'card', '2026-12-01')).toBe(195);
    expect(rateAt(rows, 'card', '2027-03-01')).toBe(195);
    expect(rateAt(rows, 'bank_transfer', '2026-11-30')).toBe(198);
  });
  it('is null before the first rate (no guess)', () => {
    expect(rateAt(rows, 'card', '2025-12-31')).toBeNull();
    expect(rateAt(rows, 'bank_transfer', '2026-05-31')).toBeNull();
    expect(rateAt([], 'card', '2026-11-30')).toBeNull();
  });
});

describe('parseFeeBody', () => {
  const T = '2026-11-23';
  const ok = { payMethod: 'card', rateBp: 143, effectiveFrom: '2026-11-24' };
  it('accepts a valid body (rateBp as integer or digit string)', () => {
    expect(parseFeeBody(ok, T)).toEqual({ ok: true, plan: ok });
    expect(parseFeeBody({ ...ok, rateBp: '198' }, T)).toEqual({ ok: true, plan: { ...ok, rateBp: 198 } });
    expect(parseFeeBody({ ...ok, rateBp: 0 }, T).ok).toBe(true);
    expect(parseFeeBody({ ...ok, rateBp: 10000 }, T).ok).toBe(true);
  });
  it.each([
    [{ ...ok, payMethod: 'virtual' }, 'payMethod'], [{ ...ok, payMethod: undefined }, 'payMethod'],
    [{ ...ok, rateBp: 1.5 }, 'rateBp'], [{ ...ok, rateBp: -1 }, 'rateBp'], [{ ...ok, rateBp: 10001 }, 'rateBp'], [{ ...ok, rateBp: '1.43' }, 'rateBp'], [{ ...ok, rateBp: 'abc' }, 'rateBp'], [{ ...ok, rateBp: null }, 'rateBp'],
    [{ ...ok, effectiveFrom: '2026-02-30' }, 'effectiveFrom'], [{ ...ok, effectiveFrom: '24.11.2026' }, 'effectiveFrom'], [{ ...ok, effectiveFrom: '2019-12-31' }, 'effectiveFrom'], [{ ...ok, effectiveFrom: '2028-01-01' }, 'effectiveFrom'], [{ ...ok, effectiveFrom: '2027-02-22' }, 'effectiveFrom'], // 91 days ahead
    
    [{ ...ok, extra: 1 }, 'extra'],
  ])('refuses %j (%s)', (body, field) => {
    const r = parseFeeBody(body, T);
    expect(r.ok).toBe(false);
    if (!r.ok) expect(Object.keys(r.errors)).toContain(field);
  });
  it('refuses non-objects', () => { for (const b of [null, undefined, 'x', 5, []]) expect(parseFeeBody(b, T).ok).toBe(false); });
});

describe('orderError: history is never rewritten', () => {
  it('the first rate of a method may start on any allowed date', () => expect(orderError(null, '2026-01-01', '2026-11-23')).toBeNull());
  it('a later rate must start after the latest start and from tomorrow', () => {
    expect(orderError('2026-01-01', '2026-11-24', '2026-11-23')).toBeNull();
    expect(orderError('2026-01-01', '2026-11-23', '2026-11-23')).toBe('must_be_future');
    expect(orderError('2026-01-01', '2026-01-01', '2026-11-23')).toBe('not_after_latest');
    expect(orderError('2026-12-01', '2026-11-30', '2026-11-23')).toBe('not_after_latest');
  });
});

describe('removal and ids', () => {
  it('a date at most 90 days ahead is allowed', () => expect(parseFeeBody({ payMethod: 'card', rateBp: 143, effectiveFrom: '2027-02-21' }, '2026-11-23').ok).toBe(true));
  it('only a rate that is not in force yet can be removed', () => {
    expect(removable('2026-11-24', '2026-11-23')).toBe(true);
    expect(removable('2026-11-23', '2026-11-23')).toBe(false);
    expect(removable('2026-01-01', '2026-11-23')).toBe(false);
  });
  it('parseRateId accepts positive digits only', () => {
    expect(parseRateId('12')).toBe(12);
    for (const v of ['0', '-1', '1.5', '1e3', 'abc', '', ' 1', undefined, '0012']) expect(parseRateId(v)).toBe(0);
  });
});
