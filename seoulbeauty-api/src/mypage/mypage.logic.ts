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
