/** Pure helpers for events (no DB): mirrors the rules in event_detail.php / event_api.php. */

/** event_detail.php $category_colors (fallback = brand red). */
const CATEGORY_COLORS: Record<string, string> = {
  피부: '#FF6B9D', 리프팅: '#C56CF0', 보톡스: '#7C5CFC', 필러: '#3DC1D3', 눈: '#63CDDA', 코: '#F97F51',
  지방흡입: '#EAB543', 가슴: '#FD7272', 치아: '#58B19F', 탈모: '#8B5CF6', 레이저: '#06D6A0', 제모: '#FFB8B8',
};
export function categoryColor(category: string | null | undefined): string {
  return (category && CATEGORY_COLORS[category]) || '#AC1830';
}

/** event_detail.php $raffle_type_labels (unknown type → random). */
const RAFFLE_TYPES: Record<string, { label: string; icon: string; desc: string }> = {
  random: { label: '랜덤 추첨', icon: '🎲', desc: '응모자 중 랜덤으로 당첨자를 선정합니다.' },
  first_come: { label: '선착순', icon: '⚡', desc: '선착순으로 당첨자가 결정됩니다.' },
  quiz: { label: '퀴즈 정답', icon: '❓', desc: '퀴즈 정답자 중에서 당첨자를 선정합니다.' },
};
export function raffleTypeInfo(type: string | null | undefined) {
  return RAFFLE_TYPES[type ?? 'random'] ?? RAFFLE_TYPES.random;
}

/** "YYYY-MM-DD" in Korea time (the PHP server's date()). */
export function todayKst(now: Date = new Date()): string {
  return new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Seoul' }).format(now);
}

/** DATE/DATETIME string → "YYYY-MM-DD" or null. */
export function dateOnly(v: unknown): string | null {
  if (v == null || v === '') return null;
  const s = String(v).slice(0, 10);
  return /^\d{4}-\d{2}-\d{2}$/.test(s) && s !== '0000-00-00' ? s : null;
}

export type ClosedReason = 'period' | 'full' | null;

/** Raffle is closed once raffle_end_date has passed or max_entries is reached (event_detail.php). */
export function raffleClosedReason(raffleEnd: string | null, maxEntries: number, entryCount: number, today: string): ClosedReason {
  if (raffleEnd && raffleEnd < today) return 'period';
  if (maxEntries > 0 && entryCount >= maxEntries) return 'full';
  return null;
}

/** Digits only; the PHP client sends `phone.replace(/[^0-9]/g,'')`. Valid = 10–11 digits. */
export function normalizePhone(v: unknown): string | null {
  const d = String(v ?? '').replace(/[^0-9]/g, '');
  return d.length >= 10 && d.length <= 11 ? d : null;
}

/** Practical stand-in for PHP filter_var(FILTER_VALIDATE_EMAIL). Empty is allowed (optional field). */
export function validEmail(v: string): boolean {
  return v === '' || (v.length <= 255 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v));
}

/** should_receive_notification(): admins without an explicit list get the defaults. */
export const DEFAULT_ADMIN_NOTIFICATIONS = [
  'hospital_request', 'ad_request', 'review_new', 'consultation_new', 'appointment_new',
  'review_pending', 'hospital_pending', 'ad_pending', 'test_fail', 'admin_created',
];
export function adminWants(permissions: unknown, type: string): boolean {
  let perms: any = {};
  try { perms = typeof permissions === 'string' && permissions ? JSON.parse(permissions) : {}; } catch { perms = {}; }
  if (!perms || typeof perms !== 'object' || Array.isArray(perms)) perms = {};
  if (!('notifications' in perms)) return DEFAULT_ADMIN_NOTIFICATIONS.includes(type);
  return Array.isArray(perms.notifications) && perms.notifications.includes(type);
}
