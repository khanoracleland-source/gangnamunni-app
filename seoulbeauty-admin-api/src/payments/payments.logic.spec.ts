import { isFail } from '../bookings/bookings.logic';
import {
  byHospitalQuery, countQuery, listQuery, maskPhone, monthCompare, parseCompareQuery, parsePaymentQuery, percentChange, periodsOf, seoulToday, seriesQuery, summaryQuery,
  toItem, weekCompare, TEST_PROVIDERS, PaymentFilters,
} from './payments.logic';

const ALL = { scope: 'all' as const, hospitalIds: [] };
const OWN12 = { scope: 'own' as const, hospitalIds: [12] };
const NONE = { scope: 'own' as const, hospitalIds: [] };
const filters = (o: Partial<PaymentFilters> = {}): PaymentFilters => ({ hospitalId: 0, status: '', from: '2026-11-01', to: '2026-11-20', q: '', page: 1, ...o });

describe('Seoul calendar', () => {
  it('Friday 2026-11-20 is the 5th day of a Monday-to-Sunday week and the 20th of the month', () => {
    const p = periodsOf('2026-11-20');
    expect(p.week).toEqual({ thisStart: '2026-11-16', lastStart: '2026-11-09', lastEnd: '2026-11-15', elapsed: 5 });
    expect(p.month).toMatchObject({ thisStart: '2026-11-01', dayOfMonth: 20, lastStart: '2026-10-01', lastEnd: '2026-10-31', lastDays: 31 });
  });
  it('a Monday is the first day of its week; a Sunday is the seventh', () => {
    expect(periodsOf('2026-11-16').week.elapsed).toBe(1);
    expect(periodsOf('2026-11-22').week).toMatchObject({ thisStart: '2026-11-16', elapsed: 7 });
  });
  it('January looks back to December of the year before; February knows its length', () => {
    expect(periodsOf('2027-01-05').month).toMatchObject({ lastStart: '2026-12-01', lastEnd: '2026-12-31', lastDays: 31 });
    expect(periodsOf('2026-03-10').month).toMatchObject({ lastStart: '2026-02-01', lastEnd: '2026-02-28', lastDays: 28 });
    expect(periodsOf('2028-03-10').month.lastDays).toBe(29);
  });
  it('the Seoul day is not the UTC day', () => {
    expect(seoulToday(new Date('2026-11-19T16:00:00Z'))).toBe('2026-11-20');
    expect(seoulToday(new Date('2026-11-19T14:59:00Z'))).toBe('2026-11-19');
  });
  it('percent change needs something to compare with', () => {
    expect(percentChange(12480000, 10720000)).toBe(16.4);
    expect(percentChange(12480000, 14950000)).toBe(-16.5);
    expect(percentChange(5, 0)).toBeNull();
  });
});

describe('payment queries', () => {
  it('the dates default to this month so far; bad input is a failure, never ignored', () => {
    expect(parsePaymentQuery({}, '2026-11-20')).toMatchObject({ from: '2026-11-01', to: '2026-11-20', status: '', page: 1 });
    for (const bad of [{ status: 'bogus' }, { from: '2026-02-30' }, { hospital_id: '0' }, { hospital_id: 'x' }, { page: '0' }, { q: 'x'.repeat(61) }, { from: '2026-11-21', to: '2026-11-20' }]) {
      expect(isFail(parsePaymentQuery(bad, '2026-11-20'))).toBe(true);
    }
    expect(isFail(parsePaymentQuery({ status: 'held', hospital_id: '12', page: '2', q: '김' }, '2026-11-20'))).toBe(false);
  });
  it('compare takes only the known measures', () => {
    expect(parseCompareQuery({})).toEqual({ hospitalId: 0, metric: 'received' });
    expect(isFail(parseCompareQuery({ metric: 'net' }))).toBe(true);
  });
  it('sandbox payments and unpaid orders are always left out, as bound values', () => {
    const q = listQuery(ALL, filters());
    for (const t of TEST_PROVIDERS) expect(q.values).toContain(t);
    expect(q.values).toEqual(expect.arrayContaining(['paid', 'partially_refunded', 'refunded']));
    expect(q.sql).not.toContain('inicis_test');
    expect(q.sql).toContain("p.payment_type = 'booking'");
  });
  it('user text is only ever a bound value (LIKE wildcards escaped)', () => {
    const q = countQuery(ALL, filters({ q: "x'; DROP TABLE a;-- 100%_" }));
    expect(q.sql).not.toContain('DROP');
    expect(q.values).toContain("%x'; DROP TABLE a;-- 100\\%\\_%");
  });
  it('a hospital account is held to its hospitals in every query; one without a hospital sees nothing', () => {
    for (const q of [listQuery(OWN12, filters()), countQuery(OWN12, filters()), summaryQuery(OWN12, filters()), seriesQuery(OWN12, 0, 'held', '2026-10-01', '2026-11-20')]) {
      expect(q.sql).toContain('p.hospital_id IN (?)');
      expect(q.values).toContain(12);
    }
    expect(byHospitalQuery(OWN12, filters()).sql).toContain('h.id IN (?)');
    expect(summaryQuery(NONE, filters()).sql).toContain('1 = 0');
  });
  it('asking for another hospital never widens an own scope', () => {
    const q = summaryQuery(OWN12, filters({ hospitalId: 99 }));
    expect(q.sql).toContain('p.hospital_id IN (?) AND p.hospital_id = ?');
  });
  it('held and recognized follow the booking status', () => {
    const held = summaryQuery(ALL, filters()).sql;
    expect(held).toContain("b.status = 'completed'");
    expect(listQuery(ALL, filters({ status: 'held' })).sql).toContain("COALESCE(b.status, '') <> 'completed'");
    expect(listQuery(ALL, filters({ status: 'recognized' })).sql).toContain("b.status = 'completed'");
  });
  it('the page is cut by 20 rows', () => {
    expect(listQuery(ALL, filters({ page: 3 })).values.slice(-2)).toEqual([20, 40]);
  });
});

describe('payment rows', () => {
  const row = { id: 9, booking_id: 4, hospital_id: 12, hospital_name: 'H', patient_name: '김', patient_phone: '010-1234-5678', treatment_name: 't', amount: '180000', refunded_amount: '0', status: 'paid', paid_at: '2026-11-20 14:02:00', booking_status: 'confirmed', booking_date: '2026-11-23', booking_time: '10:00:00' };
  it('a paid order whose treatment is not done is held; a completed one is recognized; a refunded one is refunded', () => {
    expect(toItem(row).stage).toBe('held');
    expect(toItem({ ...row, booking_status: 'completed' }).stage).toBe('recognized');
    expect(toItem({ ...row, status: 'refunded', refunded_amount: '180000' }).stage).toBe('refunded');
    expect(toItem({ ...row, booking_status: null }).stage).toBe('held');
  });
  it('the phone is masked and money is a number', () => {
    const it = toItem(row);
    expect(it.patientPhone).toBe('010-****-5678');
    expect(it.amount).toBe(180000);
    expect(it.visitDate).toBe('2026-11-23');
    expect(it.visitTime).toBe('10:00');
    expect(maskPhone('01012345678')).toBe('010-****-5678');
    expect(maskPhone('')).toBe('****');
  });
});

describe('week and month comparison', () => {
  const p = periodsOf('2026-11-20');
  const wk = [
    ['2026-11-09', 1900000], ['2026-11-10', 2200000], ['2026-11-11', 2000000], ['2026-11-12', 2300000], ['2026-11-13', 2320000], ['2026-11-14', 2600000], ['2026-11-15', 1630000],
    ['2026-11-16', 2100000], ['2026-11-17', 2900000], ['2026-11-18', 2400000], ['2026-11-19', 2600000], ['2026-11-20', 2480000],
  ].map(([day, value]) => ({ day: day as string, value: value as number }));
  it('this week runs to today; last week is whole; both are compared on the same days and in full', () => {
    const w = weekCompare(wk, p);
    expect(w.thisWeek.days.map((d) => d.date)).toEqual(['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20']);
    expect(w.lastWeek.days).toHaveLength(7);
    expect(w.totals).toEqual({ thisToDate: 12480000, lastSameDays: 10720000, lastFull: 14950000 });
    expect(w.changeSameDays).toBe(16.4);
    expect(w.changeFull).toBe(-16.5);
  });
  it('days without a payment count as zero', () => {
    const w = weekCompare([], p);
    expect(w.totals).toEqual({ thisToDate: 0, lastSameDays: 0, lastFull: 0 });
    expect(w.changeSameDays).toBeNull();
  });
  it('the month is a running total; last month is read at the same day and in full', () => {
    const m = monthCompare([{ day: '2026-11-01', value: 100 }, { day: '2026-11-20', value: 50 }, { day: '2026-10-01', value: 80 }, { day: '2026-10-20', value: 20 }, { day: '2026-10-31', value: 900 }], p);
    expect(m.thisMonth.cumulative).toHaveLength(20);
    expect(m.thisMonth.cumulative[0]).toEqual({ day: 1, value: 100 });
    expect(m.thisMonth.cumulative[19]).toEqual({ day: 20, value: 150 });
    expect(m.lastMonth.cumulative).toHaveLength(31);
    expect(m.totals).toEqual({ thisToDate: 150, lastSameDay: 100, lastFull: 1000 });
    expect(m.changeSameDay).toBe(50);
    expect(m.changeFull).toBe(-85);
  });
  it('when last month is shorter than today, the same-day figure is its whole total', () => {
    const m = monthCompare([{ day: '2026-02-28', value: 70 }], periodsOf('2026-03-30'));
    expect(m.totals.lastSameDay).toBe(70);
    expect(m.totals.lastFull).toBe(70);
  });
});
