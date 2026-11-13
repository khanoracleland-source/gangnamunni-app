/** Pure parts of GET /v1/dashboard/summary: the SELECT statements and the mapping of their rows to the response. */

export interface Query {
  sql: string;
  values: unknown[];
}

export interface BookingWindow {
  count: number;
  amount: number;
  paidAmount: number;
  refundedAmount: number;
  cancelled: number;
  pending: number;
  /** the window of the same length right before this one */
  prev: { count: number; amount: number; paidAmount: number };
}
export interface DashboardSummary {
  /** KST, "YYYY-MM-DD HH:mm:ss" */
  generatedAt: string;
  members: { total: number; today: number; last7d: number; last30d: number };
  bookings: { d7: BookingWindow; d30: BookingWindow };
  consultations: { d7: number; d30: number };
  catalog: { hospitals: number; hospitalsPending: number; doctors: number; treatments: number };
}

/** gangnamunni_bookings.status: a cancelled booking ('refunded' = cancelled and the payment returned). */
export const BOOKING_CANCELLED_STATUSES = ['cancelled', 'refunded'];
/** gangnamunni_bookings.status: requested by the patient, not yet picked up by the hospital. */
export const BOOKING_PENDING_STATUSES = ['pending'];
/** gangnamunni_platform_payments.status: money was actually received (later refunds are in refunded_amount). */
export const PAYMENT_PAID_STATUSES = ['paid', 'partially_refunded', 'refunded'];
/** gangnamunni_platform_payments.provider values that are not real money (mirror booking_payment_test_providers() in PHP). */
export const PAYMENT_TEST_PROVIDERS = ['mock_booking_payment', 'inicis_test'];

type Row = Record<string, unknown> | undefined | null;
/** SUM()/COUNT() come back as number, string (BIGINT/DECIMAL) or NULL (no rows): always a finite number here. */
export const num = (v: unknown): number => {
  const x = Number(v);
  return Number.isFinite(x) ? x : 0;
};

/**
 * Members: g5_member without withdrawn accounts (mb_leave_date is '' until a member leaves), the same rule as the
 * 탈퇴 filter of the PHP member list. `today` is the calendar day in the connection time zone (KST).
 */
export function membersQuery(): Query {
  return {
    sql: `SELECT COUNT(*) AS total,
       COALESCE(SUM(mb_datetime >= CURDATE()), 0) AS today,
       COALESCE(SUM(mb_datetime >= NOW() - INTERVAL ? DAY), 0) AS last7d,
       COALESCE(SUM(mb_datetime >= NOW() - INTERVAL ? DAY), 0) AS last30d
     FROM g5_member WHERE mb_leave_date = ''`,
    values: [7, 30],
  };
}

/**
 * Bookings CREATED in the last `days` days and in the `days` days before that (prev_*), in one scan of 2 x days.
 * amount = booked price of the bookings that were not cancelled.
 */
export function bookingsQuery(days: number): Query {
  return {
    sql: `SELECT
       COALESCE(SUM(created_at >= NOW() - INTERVAL ? DAY), 0) AS cnt,
       COALESCE(SUM(CASE WHEN created_at >= NOW() - INTERVAL ? DAY AND status NOT IN (?) THEN COALESCE(price, 0) ELSE 0 END), 0) AS amount,
       COALESCE(SUM(created_at >= NOW() - INTERVAL ? DAY AND status IN (?)), 0) AS cancelled,
       COALESCE(SUM(created_at >= NOW() - INTERVAL ? DAY AND status IN (?)), 0) AS pending,
       COALESCE(SUM(created_at < NOW() - INTERVAL ? DAY), 0) AS prev_cnt,
       COALESCE(SUM(CASE WHEN created_at < NOW() - INTERVAL ? DAY AND status NOT IN (?) THEN COALESCE(price, 0) ELSE 0 END), 0) AS prev_amount
     FROM gangnamunni_bookings
     WHERE created_at >= NOW() - INTERVAL ? DAY AND created_at <= NOW()`,
    values: [
      days,
      days, BOOKING_CANCELLED_STATUSES,
      days, BOOKING_CANCELLED_STATUSES,
      days, BOOKING_PENDING_STATUSES,
      days,
      days, BOOKING_CANCELLED_STATUSES,
      days * 2,
    ],
  };
}

/**
 * Booking payments by paid_at, same row filter as the PHP revenue page (admin_booking_revenue.php): real providers only,
 * statuses where money was received. paid = net of refunds; refunded = refunds of the payments paid in the window.
 */
export function paymentsQuery(days: number): Query {
  return {
    sql: `SELECT
       COALESCE(SUM(CASE WHEN paid_at >= NOW() - INTERVAL ? DAY THEN amount - refunded_amount ELSE 0 END), 0) AS paid,
       COALESCE(SUM(CASE WHEN paid_at >= NOW() - INTERVAL ? DAY THEN refunded_amount ELSE 0 END), 0) AS refunded,
       COALESCE(SUM(CASE WHEN paid_at < NOW() - INTERVAL ? DAY THEN amount - refunded_amount ELSE 0 END), 0) AS prev_paid
     FROM gangnamunni_platform_payments
     WHERE payment_type = 'booking' AND funds_recipient = 'hospital'
       AND provider NOT IN (?) AND status IN (?)
       AND paid_at >= NOW() - INTERVAL ? DAY AND paid_at <= NOW()`,
    values: [days, days, days, PAYMENT_TEST_PROVIDERS, PAYMENT_PAID_STATUSES, days * 2],
  };
}

/** 상담 신청 created in the last 7 / 30 days. */
export function consultationsQuery(): Query {
  return {
    sql: `SELECT COALESCE(SUM(created_at >= NOW() - INTERVAL ? DAY), 0) AS d7, COUNT(*) AS d30
     FROM gangnamunni_consultations WHERE created_at >= NOW() - INTERVAL ? DAY`,
    values: [7, 30],
  };
}

/** visible = what a patient can find in the hospital list (active, not a demo, approved or legacy NULL status). */
export function hospitalsQuery(): Query {
  return {
    sql: `SELECT
       COALESCE(SUM(is_active = 1 AND COALESCE(is_demo, 0) = 0 AND (status = 'approved' OR status IS NULL)), 0) AS visible,
       COALESCE(SUM(status = 'pending'), 0) AS pending
     FROM gangnamunni_hospitals`,
    values: [],
  };
}

export function doctorsQuery(): Query {
  return { sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_doctors WHERE status = 'active'`, values: [] };
}

export function treatmentsQuery(): Query {
  return { sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_hospital_treatments WHERE status = 'active' AND is_active = 1`, values: [] };
}

/** "YYYY-MM-DD HH:mm:ss" in KST (UTC+9, no daylight saving). */
export function kstTimestamp(now: Date = new Date()): string {
  return new Date(now.getTime() + 9 * 3_600_000).toISOString().slice(0, 19).replace('T', ' ');
}

export function bookingWindow(booking: Row, payment: Row): BookingWindow {
  return {
    count: num(booking?.cnt),
    amount: num(booking?.amount),
    paidAmount: num(payment?.paid),
    refundedAmount: num(payment?.refunded),
    cancelled: num(booking?.cancelled),
    pending: num(booking?.pending),
    prev: { count: num(booking?.prev_cnt), amount: num(booking?.prev_amount), paidAmount: num(payment?.prev_paid) },
  };
}

export interface SummaryRows {
  members: Row;
  bookings7: Row;
  bookings30: Row;
  payments7: Row;
  payments30: Row;
  consultations: Row;
  hospitals: Row;
  doctors: Row;
  treatments: Row;
}

/** A missing row (table not installed, no data) simply reads as zeros. */
export function buildSummary(r: SummaryRows, now: Date = new Date()): DashboardSummary {
  return {
    generatedAt: kstTimestamp(now),
    members: { total: num(r.members?.total), today: num(r.members?.today), last7d: num(r.members?.last7d), last30d: num(r.members?.last30d) },
    bookings: { d7: bookingWindow(r.bookings7, r.payments7), d30: bookingWindow(r.bookings30, r.payments30) },
    consultations: { d7: num(r.consultations?.d7), d30: num(r.consultations?.d30) },
    catalog: {
      hospitals: num(r.hospitals?.visible),
      hospitalsPending: num(r.hospitals?.pending),
      doctors: num(r.doctors?.cnt),
      treatments: num(r.treatments?.cnt),
    },
  };
}
