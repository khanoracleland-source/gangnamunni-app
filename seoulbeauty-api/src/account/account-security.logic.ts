/** Helpers for the member-security calls that are carried out by PHP (password, e-mail, 회원 탈퇴). */

export const PW_MIN = 6;
/** PBKDF2 cost grows with the input; a sane ceiling (PHP itself only has a minimum). */
export const PW_MAX = 128;
export const REASONS = ['서비스 불만', '사용 빈도 낮음', '다른 서비스 이용', '개인정보 보호', '기타'] as const;
export const REASON_DETAIL_MAX = 500;

/**
 * PHP pages built on init.php can have a `<script>` appended to the end of a JSON reply (the app-bridge shutdown hook, within
 * the first 60 s after a login). Cut it off and parse what is left; null when it is not JSON at all.
 */
export function parsePhpJson(text: string): Record<string, any> | null {
  const cut = text.split('<script')[0].trim();
  try {
    const v = JSON.parse(cut);
    return v && typeof v === 'object' && !Array.isArray(v) ? v : null;
  } catch {
    return null;
  }
}

/** withdraw.php builds the stored reason as "<reason> - <detail>". */
export function fullReason(reason: unknown, detail: unknown): string {
  const r = typeof reason === 'string' ? reason.trim() : '';
  const d = typeof detail === 'string' ? detail.trim().slice(0, REASON_DETAIL_MAX) : '';
  return d ? `${r} - ${d}` : r;
}

export const isEmail = (v: string): boolean => v.length <= 100 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
