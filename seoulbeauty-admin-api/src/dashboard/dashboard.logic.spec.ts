import {
  bookingsQuery, bookingWindow, buildSummary, consultationsQuery, doctorsQuery, hospitalsQuery, kstTimestamp, membersQuery, num, paymentsQuery, treatmentsQuery,
  BOOKING_CANCELLED_STATUSES, BOOKING_PENDING_STATUSES, PAYMENT_PAID_STATUSES, PAYMENT_TEST_PROVIDERS,
} from './dashboard.logic';

const all = [membersQuery(), bookingsQuery(7), bookingsQuery(30), paymentsQuery(7), paymentsQuery(30), consultationsQuery(), hospitalsQuery(), doctorsQuery(), treatmentsQuery()];
const placeholders = (sql: string) => (sql.match(/\?/g) || []).length;

describe('dashboard queries', () => {
  it('are single read-only statements', () => {
    for (const q of all) {
      expect(q.sql.trim()).toMatch(/^SELECT\b/);
      expect(q.sql).not.toMatch(/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE|TRUNCATE|GRANT|CALL|INTO|FOR UPDATE)\b/i);
      expect(q.sql).not.toContain(';');
    }
  });
  it('bind exactly as many values as they have placeholders', () => {
    for (const q of all) expect(q.values).toHaveLength(placeholders(q.sql));
  });
  it('members: withdrawn accounts are left out', () => {
    expect(membersQuery().sql).toContain("FROM g5_member WHERE mb_leave_date = ''");
    expect(membersQuery().values).toEqual([7, 30]);
  });
  it('bookings: window by created_at, scan covers the previous window too', () => {
    const q = bookingsQuery(7);
    expect(q.sql).toContain('FROM gangnamunni_bookings');
    expect(q.sql).toContain('WHERE created_at >= NOW() - INTERVAL ? DAY AND created_at <= NOW()');
    expect(q.values[q.values.length - 1]).toBe(14);
    expect(bookingsQuery(30).values[bookingsQuery(30).values.length - 1]).toBe(60);
    expect(q.values.filter((v) => v === BOOKING_CANCELLED_STATUSES)).toHaveLength(3);
    expect(q.values.filter((v) => v === BOOKING_PENDING_STATUSES)).toHaveLength(1);
  });
  it('payments: booking payments of real providers, by paid_at', () => {
    const q = paymentsQuery(30);
    expect(q.sql).toContain("payment_type = 'booking'");
    expect(q.sql).toContain('provider NOT IN (?) AND status IN (?)');
    expect(q.sql).toContain('paid_at >= NOW() - INTERVAL ? DAY');
    expect(q.sql).toContain('amount - refunded_amount');
    expect(q.values).toEqual([30, 30, 30, PAYMENT_TEST_PROVIDERS, PAYMENT_PAID_STATUSES, 60]);
    expect(PAYMENT_TEST_PROVIDERS).toEqual(['mock_booking_payment', 'inicis_test']);
  });
  it('catalog: the patient-visible rules', () => {
    expect(hospitalsQuery().sql).toContain("is_active = 1 AND COALESCE(is_demo, 0) = 0 AND (status = 'approved' OR status IS NULL)");
    expect(hospitalsQuery().sql).toContain("status = 'pending'");
    expect(doctorsQuery().sql).toContain("FROM gangnamunni_doctors WHERE status = 'active'");
    expect(treatmentsQuery().sql).toContain("status = 'active' AND is_active = 1");
  });
});

describe('dashboard mapping', () => {
  it('num: strings, NULL and junk become finite numbers', () => {
    expect(num('12')).toBe(12);
    expect(num(null)).toBe(0);
    expect(num(undefined)).toBe(0);
    expect(num('abc')).toBe(0);
    expect(num(3.5)).toBe(3.5);
  });
  it('kstTimestamp: UTC + 9 h', () => {
    expect(kstTimestamp(new Date('2026-11-13T07:40:00Z'))).toBe('2026-11-13 16:40:00');
    expect(kstTimestamp(new Date('2026-12-31T15:00:01Z'))).toBe('2027-01-01 00:00:01');
  });
  it('bookingWindow: current and previous window come from the prev_* columns', () => {
    expect(bookingWindow(
      { cnt: '10', amount: '900000', cancelled: 2, pending: 3, prev_cnt: 4, prev_amount: '300000' },
      { paid: '500000', refunded: '50000', prev_paid: '120000' },
    )).toEqual({
      count: 10, amount: 900000, paidAmount: 500000, refundedAmount: 50000, cancelled: 2, pending: 3,
      prev: { count: 4, amount: 300000, paidAmount: 120000 },
    });
  });
  it('buildSummary: missing rows read as zeros, shape stays complete', () => {
    const empty = { members: null, bookings7: null, bookings30: null, payments7: null, payments30: null, consultations: null, hospitals: null, doctors: null, treatments: null };
    const zero = { count: 0, amount: 0, paidAmount: 0, refundedAmount: 0, cancelled: 0, pending: 0, prev: { count: 0, amount: 0, paidAmount: 0 } };
    expect(buildSummary(empty, new Date('2026-11-13T07:40:00Z'))).toEqual({
      generatedAt: '2026-11-13 16:40:00',
      members: { total: 0, today: 0, last7d: 0, last30d: 0 },
      bookings: { d7: zero, d30: zero },
      consultations: { d7: 0, d30: 0 },
      catalog: { hospitals: 0, hospitalsPending: 0, doctors: 0, treatments: 0 },
    });
  });
});
