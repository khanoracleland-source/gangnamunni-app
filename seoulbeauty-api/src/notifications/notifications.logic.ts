import { bookingTimePassed, kstNow } from '../bookings/bookings.logic';

export type NotiFilter = 'all' | 'booking' | 'review' | 'benefit';
export const NOTI_LIMIT = 20;

const GROUPS: Record<NotiFilter, string[]> = {
  all: [],
  booking: ['booking', 'payment'],
  review: ['review', 'community', 'comment'],
  benefit: ['event', 'raffle', 'system', 'promotion', 'general', 'point', 'coupon', 'benefit'],
};

export function notiFilter(v: unknown): NotiFilter {
  return v === 'booking' || v === 'review' || v === 'benefit' ? v : 'all';
}
export function filterTypes(f: NotiFilter): string[] { return GROUPS[f]; }

export type NotiIcon = 'calendar' | 'payment' | 'clock' | 'gift' | 'review' | 'bell';

/** notification_display_icon() of my_notifications.php. */
export function displayIcon(type: string, title: string, message: string, fallback: string): NotiIcon {
  const text = `${title} ${message}`;
  if (type === 'booking' && /결제|입금|환불/u.test(text)) return 'payment';
  if (type === 'booking' && /내일|오늘|리마인드|Reminder/i.test(text)) return 'clock';
  if (type === 'booking') return 'calendar';
  if (type === 'review') return 'review';
  if (/포인트|이벤트|혜택|쿠폰|소멸|발급/u.test(text)) return 'gift';
  if (['raffle', 'event', 'promotion', 'point', 'coupon', 'benefit'].includes(type)) return 'gift';
  if (type === 'system' || type === 'general') return 'bell';
  const map: Record<string, NotiIcon> = {
    'bi-credit-card': 'payment', 'bi-clock': 'clock', 'bi-pencil-square': 'review', 'bi-gift': 'gift', 'bi-bell': 'bell', 'bi-calendar-check': 'calendar',
  };
  return map[fallback] ?? 'bell';
}

/** notification_extract_booking_id(): booking_id / source_booking_id in the link's query string. */
export function extractBookingId(link: unknown): number {
  const l = String(link ?? '');
  const q = l.indexOf('?');
  if (q < 0) return 0;
  const params = new URLSearchParams(l.slice(q + 1).split('#')[0]);
  for (const k of ['booking_id', 'source_booking_id']) {
    const v = params.get(k);
    if (v) return Math.max(0, parseInt(v, 10) || 0);
  }
  return 0;
}

export type BookingTabName = 'active' | 'completed' | 'cancelled';

export function bookingTabOf(b: { status: unknown; booking_date: unknown; booking_time: unknown }, now = kstNow()): BookingTabName {
  const status = String(b.status ?? '');
  if (status === 'cancelled' || status === 'no_show') return 'cancelled';
  if (['pending', 'hospital_checking', 'confirmed'].includes(status) && !bookingTimePassed(b.booking_date, b.booking_time, now)) return 'active';
  return 'completed';
}

/** Where a booking notification opens (the Next 내 예약 tab); other types are not clickable. */
export function openUrl(type: string, link: unknown, booking: { status: unknown; booking_date: unknown; booking_time: unknown } | undefined, now = kstNow()): string {
  if (type !== 'booking') return '';
  const tab = booking ? bookingTabOf(booking, now)
    : String(link ?? '').includes('booking_cancel_detail.php') ? 'cancelled' : 'active';
  return tab === 'active' ? '/mypage/bookings' : `/mypage/bookings?tab=${tab}`;
}

const ms = (kst: string) => Date.UTC(+kst.slice(0, 4), +kst.slice(5, 7) - 1, +kst.slice(8, 10), +kst.slice(11, 13) || 0, +kst.slice(14, 16) || 0, +kst.slice(17, 19) || 0);

/** notification_time_label(): 방금 / N분 전 / N시간 전 / 어제 / N일 전 / MM.DD (both times in KST). */
export function timeLabel(created: unknown, now = kstNow()): string {
  const c = String(created ?? '');
  if (!/^\d{4}-\d{2}-\d{2}/.test(c)) return '';
  const diff = Math.floor((ms(now) - ms(c)) / 1000);
  if (diff < 60) return '방금';
  if (diff < 3600) return `${Math.floor(diff / 60)}분 전`;
  const sameDay = c.slice(0, 10) === now.slice(0, 10);
  if (diff < 86400 && sameDay) return `${Math.floor(diff / 3600)}시간 전`;
  const days = Math.floor((ms(`${now.slice(0, 10)} 00:00:00`) - ms(`${c.slice(0, 10)} 00:00:00`)) / 86400000);
  if (days <= 1) return days === 1 ? '어제' : `${Math.floor(diff / 3600)}시간 전`;
  if (days < 7) return `${days}일 전`;
  return `${c.slice(5, 7)}.${c.slice(8, 10)}`;
}
