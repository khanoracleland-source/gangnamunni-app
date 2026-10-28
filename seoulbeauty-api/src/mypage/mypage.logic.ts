/** Pure helpers for the 마이페이지 pages (ports of mypage.php, my_wishlist.php, my_reviews.php, includes/consultation_view.php). */

export type StatusTone = 'danger' | 'muted' | 'dark';

/** consult_status_view(): label + tone (the colours are the client's business). */
export function consultStatus(st: unknown): { label: string; tone: StatusTone } {
  switch (String(st ?? '')) {
    case 'contact_failed': return { label: '연락 실패', tone: 'danger' };
    case 'completed': return { label: '상담 완료', tone: 'muted' };
    case 'rejected': return { label: '상담 불가', tone: 'muted' };
    default: return { label: '확인 중', tone: 'dark' };
  }
}

/** consult_type_label() */
export function consultTypeLabel(type: unknown): string {
  const map: Record<string, string> = { general: '전화 상담', kakao: '카카오톡 채널 상담', doctor: '방문 상담' };
  return map[String(type ?? '')] ?? '상담';
}

/** my_review_district(): "강남구" -> "강남" (the part before 구/군), '' when none. */
export function reviewDistrict(address: unknown): string {
  const m = /([^\s]+)[구군](?:\s|$)/u.exec(String(address ?? '').trim());
  return m ? m[1] : '';
}

/** my_wishlist.php: second word of the address (or the first when there is only one). */
export function wishlistDistrict(address: unknown): string {
  const parts = String(address ?? '').trim().split(/\s+/u);
  return parts[1] ?? parts[0] ?? '';
}

/** my_wishlist.php: first 20 characters, then "...". */
export function addressShort(address: unknown): string {
  const a = Array.from(String(address ?? '').trim());
  return a.length > 20 ? `${a.slice(0, 20).join('')}...` : a.join('');
}

/** The file id PHP uses for profile photos: letters, digits, "_" and "-" only. */
export function profileFileId(mbId: unknown): string {
  return String(mbId ?? '').replace(/[^A-Za-z0-9_-]/g, '');
}

/** "2026-10-01" -> "2026.10.01" (시술일) */
export function dotDate(d: unknown): string {
  return d ? String(d).slice(0, 10).replace(/-/g, '.') : '';
}

/** tel: href body — digits and "+" only (preg_replace('/[^0-9+]/')). */
export function telDigits(phone: unknown): string {
  return String(phone ?? '').replace(/[^0-9+]/g, '');
}

/** consult_slot_labels(): callback time slots (key -> label). */
export const CONSULT_SLOTS: Record<string, string> = { am: '오전', pm: '오후', eve: '저녁', any: '언제든 괜찮아요' };

/** Timeline labels of gangnamunni_consultation_events.kind ("completed" is the fixed last line). */
export const CONSULT_EVENT_LABELS: Record<string, string> = { no_answer: '병원 연락 · 받지 않음', rerequest: '다시 요청' };

/** consult_dt(): "10.20 (화) 오후 2:02" from a 'YYYY-MM-DD HH:MM:SS' string (KST wall clock, no time-zone maths). */
export function consultDt(v: unknown): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(v ?? ''));
  if (!m) return '';
  const y = Number(m[1]), mo = Number(m[2]), d = Number(m[3]), h = Number(m[4]);
  const w = ['일', '월', '화', '수', '목', '금', '토'][new Date(Date.UTC(y, mo - 1, d)).getUTCDay()];
  return `${m[2]}.${m[3]} (${w}) ${h < 12 ? '오전' : '오후'} ${h % 12 || 12}:${m[5]}`;
}

/** consult_safe_url(): http(s) only, anything else becomes ''. */
export function safeHttpUrl(u: unknown): string {
  const t = String(u ?? '').trim();
  return /^https?:\/\//i.test(t) ? t : '';
}
