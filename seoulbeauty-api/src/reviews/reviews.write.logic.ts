/** Pure helpers for writing a 후기 (ports of review_write.php). */

export const REVIEW_PHOTO_SLOTS = 2; // per category (시술 전 / 시술 후)
export const REVIEW_PHOTO_MAX_BYTES = 5 * 1024 * 1024;
export const UNKNOWN_DOCTOR = '담당 의사 모름';
export const TITLE_MAX = 200;
export const CONTENT_MIN = 10;
export const CONTENT_MAX = 2000;

const BACK_ALLOWED = ['my_reviews.php', 'my_bookings.php', 'booking_detail.php', 'booking_request_detail.php', 'review_detail.php', 'reviews.php'];

/** rw_safe_back_url(): only same-site pages from the allowlist (no scheme/host/backslash/control chars); default my_reviews.php. */
export function safeBackUrl(candidate: unknown): string {
  const fallback = 'my_reviews.php';
  const c = typeof candidate === 'string' ? candidate.trim() : '';
  // eslint-disable-next-line no-control-regex
  if (c === '' || /[\x00-\x1F\x7F\\]/.test(c)) return fallback;
  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(c) || c.startsWith('//')) return fallback;
  const q = c.indexOf('?');
  const path = (q === -1 ? c : c.slice(0, q)).replace(/^\/+/, '');
  if (!BACK_ALLOWED.includes(path)) return fallback;
  const query = q === -1 ? '' : c.slice(q + 1).split('#')[0];
  return query ? `${path}?${query}` : path;
}

/** doctor_ids "3,5,3" -> [3,5] (positive, unique, order kept). */
export function parseDoctorIds(v: unknown): number[] {
  const out: number[] = [];
  for (const part of String(v ?? '').split(',')) {
    const id = Number(part.trim());
    if (Number.isInteger(id) && id > 0 && id <= 2147483647 && !out.includes(id)) out.push(id);
  }
  return out;
}

/** Valid doctors in the order the member picked them; name is the joined list cut to 100 characters. */
export function orderDoctors(pickedIds: number[], valid: Map<number, string>): { doctorId: number; doctorName: string } | null {
  const ids = pickedIds.filter((id) => valid.has(id));
  if (!ids.length) return null;
  return { doctorId: ids[0], doctorName: Array.from(ids.map((id) => valid.get(id)!.trim()).join(', ')).slice(0, 100).join('') };
}

export const truthy = (v: unknown) => v === true || v === '1' || v === 'on' || v === 'true' || v === 1;

export interface ReviewInput { hospitalId: number; rating: number; title: string; content: string; doctorUnknown: boolean; agreed: boolean; hasDoctor: boolean }

/** First error (PHP wording) or null. */
export function validateReview(i: ReviewInput): string | null {
  if (i.hospitalId <= 0) return '병원 정보를 확인해주세요.';
  if (!i.doctorUnknown && !i.hasDoctor) return '담당 의사를 입력하거나 ‘담당 의사 모름’을 선택해주세요.';
  if (!Number.isInteger(i.rating) || i.rating < 1 || i.rating > 5) return '평점을 선택해주세요.';
  if (i.title === '') return '제목을 입력해주세요.';
  if (Array.from(i.title).length > TITLE_MAX) return `제목은 ${TITLE_MAX}자 이내로 입력해주세요.`;
  if (Array.from(i.content).length < CONTENT_MIN) return '내용을 10자 이상 입력해주세요.';
  if (Array.from(i.content).length > CONTENT_MAX) return `내용은 ${CONTENT_MAX}자 이내로 입력해주세요.`;
  if (!i.agreed) return '커뮤니티 가이드라인에 동의해주세요.';
  return null;
}

export function photoName(category: 'before' | 'after', ext: string, now = Date.now(), rnd = Math.floor(1000 + Math.random() * 9000)): string {
  return `${category}_${Math.floor(now / 1000)}_${rnd}.${ext}`;
}

/** multer decodes multipart file names as latin1; restore UTF-8 (Korean file names) and cap the length. */
export function originalName(name: unknown): string {
  const raw = typeof name === 'string' ? name : '';
  const fixed = Buffer.from(raw, 'latin1').toString('utf8');
  return Array.from(fixed.includes('�') ? raw : fixed).slice(0, 100).join('');
}
