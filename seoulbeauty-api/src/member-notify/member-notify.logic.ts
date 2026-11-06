/** Pure helpers for 예약 · 결제 알림 to the member (port of includes/booking_notifications.php). */
import { normalizePlatform } from '../admin-notify/admin-notify.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;

/** Events the member can be told about. The PHP side raises the admin-driven ones; these are the ones Nest raises. */
export type BookingEvent =
  | 'received'            // 예약 접수 (the member just made the booking)
  | 'hospital_checking' | 'doctor_assigned' | 'doctor_unassigned' | 'confirmed' | 'completed' | 'cancelled' | 'reschedule_required' | 'refunded'
  | 'payment_requested'   // 결제 요청 (the hospital confirmed; a payment order was created)
  | 'paid'                // 결제 완료
  | 'payment_expired'     // 결제 기한 만료 자동 취소
  | 'cancelled_by_member' // 회원 본인 취소
  | 'changed'             // 예약 변경 (applied at once; recheck = it went back to 병원 확인)
  | 'visit_reminder';     // 방문 전날 (설정 > 방문 전 알림)

/** 설정 switch a given event follows. */
export function switchFor(event: BookingEvent): 'noti_booking' | 'noti_visit' {
  return event === 'visit_reminder' ? 'noti_visit' : 'noti_booking';
}

export interface BookingRow { id: unknown; hospital_id?: unknown; hospital_name?: unknown; treatment?: unknown; booking_date?: unknown; booking_time?: unknown; price?: unknown; mb_id?: unknown; phone?: unknown }

/** The PHP default body: "병원명 · 2026-11-07 14:00" (hospital and date/time, whichever exist). */
export function defaultBody(b: BookingRow): string {
  const hospital = s(b.hospital_name).trim();
  const d = dateText(b.booking_date);
  const t = s(b.booking_time).slice(0, 5);
  const when = [d, t].filter(Boolean).join(' ');
  const parts = [hospital, when].filter(Boolean);
  return parts.length ? parts.join(' · ') : (s(b.treatment).trim() || '예약 내역을 확인해주세요.');
}

export function dateText(v: unknown): string {
  if (v instanceof Date) return `${v.getFullYear()}-${String(v.getMonth() + 1).padStart(2, '0')}-${String(v.getDate()).padStart(2, '0')}`;
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s(v));
  return m ? `${m[1]}-${m[2]}-${m[3]}` : '';
}

/** booking_notification_detail_link(): PHP-style links; the app and the Next notifications page both understand them. */
export function detailLink(b: BookingRow, event: BookingEvent): string {
  const id = n(b.id);
  if (id <= 0) return 'my_bookings.php';
  if (['received', 'hospital_checking', 'doctor_assigned', 'confirmed', 'visit_reminder', 'changed'].includes(event)) return 'my_bookings.php';
  if (event === 'reschedule_required') {
    const h = n(b.hospital_id);
    return h > 0 ? `booking.php?id=${h}&rebook=1&source_booking_id=${id}&treatment=${encodeURIComponent(s(b.treatment))}` : `booking_request_detail.php?booking_id=${id}`;
  }
  if (['cancelled', 'refunded', 'payment_expired', 'cancelled_by_member'].includes(event)) return `booking_cancel_detail.php?booking_id=${id}`;
  if (event === 'payment_requested') return `booking_payment.php?booking_id=${id}`;
  return `booking_request_detail.php?booking_id=${id}`;
}

export interface Payload { title: string; body: string; link: string }

/** booking_notification_payload(): the title and body of each event. */
export function payload(b: BookingRow, event: BookingEvent, ctx: { doctorName?: string; deadlineHours?: number; amount?: number; recheck?: boolean } = {}): Payload {
  const body = defaultBody(b);
  const treatment = s(b.treatment).trim();
  const doctor = s(ctx.doctorName).trim();
  const hours = n(ctx.deadlineHours) > 0 ? n(ctx.deadlineHours) : 24;
  const map: Record<BookingEvent, [string, string]> = {
    received: ['예약이 접수되었어요', `${body}\n병원 확인 후 알려드릴게요`],
    hospital_checking: ['예약 요청을 확인 중입니다', body],
    confirmed: ['예약이 확정되었습니다', body],
    completed: ['시술이 완료되었습니다', '방문해주셔서 감사합니다. 예약 내역에서 후기를 남길 수 있습니다.'],
    cancelled: ['병원 사정으로 예약이 취소되었어요', '예약상세에서 병원에서 전달한 취소 사유를 확인할 수 있어요'],
    reschedule_required: ['병원 사정으로 예약 일정 변경이 필요해요', '병원에서 예약 진행이 어려워졌습니다\n다른 일정으로 다시 예약해보세요'],
    refunded: ['취소·환불 처리가 완료되었어요!', '취소·환불처리가 완료되었어요\n예약 취소에서 상세 정보를 확인하세요'],
    doctor_assigned: ['담당의사가 배정되었습니다', [doctor, body].filter(Boolean).join(' · ')],
    doctor_unassigned: ['담당의사 배정이 해제되었습니다', body],
    payment_requested: ['결제를 진행해주세요', `${body}\n${hours}시간 안에 결제하지 않으면 예약이 자동으로 취소돼요`],
    paid: ['결제가 완료되었어요', `${body}${n(ctx.amount) > 0 ? `\n${n(ctx.amount).toLocaleString('ko-KR')}원 결제 완료` : ''}`],
    payment_expired: ['결제 기한이 지나 예약이 취소되었어요', `${body}\n다시 예약해주세요`],
    cancelled_by_member: ['예약이 취소되었어요', body],
    changed: ['예약이 변경되었어요', `${body}${ctx.recheck ? '\n병원 확인 후 다시 알려드릴게요' : ''}`],
    visit_reminder: ['내일 방문 일정이 있어요', [body, treatment].filter(Boolean).join('\n')],
  };
  const [title, text] = map[event] ?? ['예약 상태가 변경되었습니다', body];
  return { title, body: text, link: detailLink(b, event) };
}

/** send_push_to_members() data: what the app reads to open the right page. */
export function memberPushData(link: string | null, bookingId: number, event: string): Record<string, string> {
  const data: Record<string, string> = { type: 'booking_notification', booking_event: event, booking_id: String(bookingId), click_action: 'FLUTTER_NOTIFICATION_CLICK' };
  if (link) { data.url = link; data.webview_url = link; }
  return data;
}

/** send_fcm_v1(): the HTTP v1 message body for a member device. */
export function buildMemberFcmMessage(token: string, platform: unknown, title: string, body: string, data: Record<string, string>) {
  const p = normalizePlatform(platform);
  const message: Record<string, unknown> = { token, notification: { title, body } };
  if (p === 'android') message.android = { priority: 'high', notification: { sound: 'default', channel_id: 'notification' } };
  else if (p === 'ios') message.apns = { payload: { aps: { sound: 'default', badge: 1 } } };
  message.data = data;
  return { message };
}

/** booking_notification_normalize_korean_phone(): 010XXXXXXXX or '' (also +82 10 …). */
export function koreanPhone(v: unknown): string {
  let d = s(v).replace(/[^0-9]/g, '');
  if (/^010\d{8}$/.test(d)) return d;
  if (/^8210\d{8}$/.test(d)) { d = '0' + d.slice(2); return d; }
  return '';
}
