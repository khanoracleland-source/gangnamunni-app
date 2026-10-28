/** Pure helpers for my_bookings.php, booking_request_detail.php and booking_cancel_detail.php. */

export const DAYS_KO = ['일', '월', '화', '수', '목', '금', '토'];
const TEST_PROVIDERS = ['mock_booking_payment', 'toss_test', 'portone_test'];
const PG_PROVIDERS = ['toss', 'toss_test'];

export const isTestProvider = (p: unknown) => TEST_PROVIDERS.includes(String(p ?? ''));
export const isPgProvider = (p: unknown) => PG_PROVIDERS.includes(String(p ?? ''));

/** 'YYYY-MM-DD HH:MM:SS' for "now" on the KST wall clock (DB values are KST wall-clock strings). */
export function kstNow(ms = Date.now()): string {
  return new Date(ms + 9 * 3600_000).toISOString().slice(0, 19).replace('T', ' ');
}

/** Has the booking time passed? (PHP: strtotime(date + time) <= time()) */
export function bookingTimePassed(date: unknown, time: unknown, now = kstNow()): boolean {
  const d = String(date ?? '').slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return false;
  const t = /^\d{2}:\d{2}(:\d{2})?$/.test(String(time ?? '')) ? String(time).padEnd(8, ':00').slice(0, 8) : '00:00:00';
  return `${d} ${t}` <= now;
}

function parts(date: unknown, time: unknown) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(date ?? ''));
  const t = /^(\d{2}):(\d{2})/.exec(String(time ?? ''));
  if (!m || !t) return null;
  const y = Number(m[1]), mo = Number(m[2]), d = Number(m[3]), h = Number(t[1]);
  return { y, mo, d, h, min: t[2], dow: DAYS_KO[new Date(Date.UTC(y, mo - 1, d)).getUTCDay()], ampm: h < 12 ? '오전' : '오후', h12: h % 12 || 12 };
}

/** my_bookings cards: "2026.10.28(수) 오후 2:00" */
export function cardDate(date: unknown, time: unknown): string {
  const p = parts(date, time);
  return p ? `${p.y}.${p.mo}.${p.d}(${p.dow}) ${p.ampm} ${p.h12}:${p.min}` : '';
}

/** detail pages: "10월 28일 (수) 오후 2:00"; booking_detail uses "10월 28일(수)" (no space) — the two detail pages here use the spaced form. */
export function detailDate(date: unknown, time: unknown): string {
  const p = parts(date, time);
  return p ? `${p.mo}월 ${p.d}일 (${p.dow}) ${p.ampm} ${p.h12}:${p.min}` : '-';
}

/** cancel detail: "10월 28일 (수) 오후 2:00" from a full datetime. */
export function dateTimeLabel(dt: unknown): string {
  const s = String(dt ?? '');
  return detailDate(s.slice(0, 10), s.slice(11, 16));
}

/** D-3 / D-0 / D+2 from today (KST) to the booking date. */
export function dday(date: unknown, today = kstNow().slice(0, 10)): string {
  const a = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(date ?? ''));
  const b = /^(\d{4})-(\d{2})-(\d{2})/.exec(today);
  if (!a || !b) return '';
  const diff = Math.round((Date.UTC(+a[1], +a[2] - 1, +a[3]) - Date.UTC(+b[1], +b[2] - 1, +b[3])) / 86400000);
  return diff > 0 ? `D-${diff}` : diff === 0 ? 'D-0' : `D+${Math.abs(diff)}`;
}

export type PayState = '' | 'refunded' | 'cancelled' | 'failed' | 'paid' | 'partially_refunded' | 'test_paid' | 'preview' | 'payable' | string;

/** booking_payment_display_state() + booking_payment_state(): only a confirmed booking with a payment row has one. */
export function paymentState(bookingStatus: unknown, p: { status?: unknown; amount?: unknown; provider?: unknown } | undefined): PayState {
  if (String(bookingStatus ?? '') !== 'confirmed' || !p) return '';
  const status = String(p.status ?? '');
  if (['refunded', 'cancelled', 'failed'].includes(status)) return status;
  if (['paid', 'partially_refunded'].includes(status)) return isTestProvider(p.provider) ? 'test_paid' : status;
  if (status === 'preview') return 'preview';
  return status === 'pending' && Number(p.amount ?? 0) > 0 ? 'payable' : '';
}

/** my_bookings.php progress bar of an active booking. */
export function requestSteps(status: unknown, pay: PayState): { steps: string[]; active: number; chip: string } {
  const steps = ['예약 요청 접수', '병원 확인 중', '결제 대기', '예약 확정'];
  let active = 1;
  if (status === 'hospital_checking') active = 2;
  else if (status === 'confirmed') {
    active = ['paid', 'partially_refunded', 'test_paid', 'preview'].includes(pay) ? 4 : 3;
    if (pay === 'preview') steps[3] = '결제 없이 진행';
    if (pay === 'test_paid') steps[3] = '테스트 결제';
    if (pay === 'refunded') steps[2] = '환불 완료';
    if (pay === 'cancelled') steps[2] = '결제 취소';
  }
  const chip = active === 4 ? '예약 확정' : active === 3 ? '결제 대기' : active === 2 ? '병원 확인 중' : '예약 요청 접수';
  return { steps, active, chip };
}

/** Cancelled card: no money taken -> "취소 처리", otherwise the refund track. */
export function cancelSteps(payment: { status?: unknown; provider?: unknown } | undefined): { noCharge: boolean; refundComplete: boolean; steps: string[]; active: number; chip: string } {
  const noCharge = !payment || isTestProvider(payment.provider) || ['pending', 'preview', 'failed', 'cancelled'].includes(String(payment.status ?? ''));
  const refundComplete = noCharge || String(payment?.status ?? '') === 'refunded';
  return {
    noCharge, refundComplete,
    steps: noCharge ? ['예약 취소 접수', '병원 확인 중', '취소 처리', '예약 취소 완료'] : ['예약 취소 접수', '병원 확인 중', '환불 대기', '환불 완료'],
    active: refundComplete ? 4 : 1,
    chip: refundComplete ? (noCharge ? '예약 취소 완료' : '취소·환불 완료') : '취소 접수',
  };
}

/** includes/review_booking.php bookingReviewState() */
export function reviewState(r: { review_status?: unknown; is_active?: unknown } | null | undefined): { state: 'none' | 'pending' | 'published' | 'rejected'; label: string } {
  if (!r) return { state: 'none', label: '' };
  const status = String(r.review_status ?? '');
  if (status === 'published' && Number(r.is_active ?? 0) === 1) return { state: 'published', label: '내 후기 보기' };
  if (status === 'rejected') return { state: 'rejected', label: '후기 반려됨' };
  return { state: 'pending', label: '승인 대기중' };
}

/** points_v2_payment_tier_point() with the settings already read. */
export function paymentTierPoint(price: number, s: { t1Under: number; t1: number; t2Under: number; t2: number; t3: number }): number {
  if (price <= 0) return 0;
  if (price < s.t1Under) return s.t1;
  if (price < s.t2Under) return s.t2;
  return s.t3;
}

/** Which of the three tabs a booking row belongs to. */
export function bookingTab(status: unknown, date: unknown, time: unknown, now = kstNow()): 'upcoming' | 'completed' | 'cancelled' {
  const st = String(status ?? '');
  if (['pending', 'hospital_checking', 'confirmed'].includes(st) && !bookingTimePassed(date, time, now)) return 'upcoming';
  if (st === 'cancelled' || st === 'no_show') return 'cancelled';
  return 'completed';
}
