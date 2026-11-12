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

/**
 * ACCOUNT_NEST_ACTIONS="password,email,withdraw": the actions Nest carries out itself. Anything not listed is still forwarded to PHP
 * (api/account_security.php, api/withdraw.php, notification_settings.php), so each one can be switched and switched back on its own.
 */
export type AccountAction = 'password' | 'email' | 'withdraw';
export function nestActions(raw: string | undefined = process.env.ACCOUNT_NEST_ACTIONS): Set<AccountAction> {
  const all: AccountAction[] = ['password', 'email', 'withdraw'];
  return new Set((raw || '').split(',').map((v) => v.trim().toLowerCase()).filter((v): v is AccountAction => (all as string[]).includes(v)));
}

export const EMAIL_CODE_TTL_MS = 180_000;
export const EMAIL_CODE_MAX_TRIES = 5;
export function emailCodeMail(code: string): { subject: string; html: string } {
  return { subject: '[서울미녀] 이메일 인증 코드', html: `이메일 변경 인증 코드: <b>${code}</b><br><br>3분 이내에 입력해주세요.` };
}

/** notification_settings.php withdraw_social: a social member's rows keyed by the numeric member number ... */
export const WITHDRAW_BY_MB_NO: readonly (readonly [string, string])[] = [
  ['gangnamunni_wishlist', 'member_id'], ['gangnamunni_points', 'member_id'], ['gangnamunni_user_actions', 'member_id'],
  ['gangnamunni_coupon_claims', 'member_id'], ['gangnamunni_chat_rooms', 'member_id'], ['gangnamunni_content_reports', 'reported_user_id'],
  ['gangnamunni_members', 'mb_no'],
];
/** ... and by the login id. A table or column that does not exist is skipped (same as PHP). */
export const WITHDRAW_BY_MB_ID: readonly (readonly [string, string])[] = [
  ['gangnamunni_notification_settings', 'member_id'], ['gangnamunni_user_agreements', 'user_id'], ['gangnamunni_user_notifications', 'member_id'],
  ['gangnamunni_bookings', 'mb_id'], ['hospital_reviews', 'user_id'], ['gangnamunni_community_comments', 'mb_id'],
  ['gangnamunni_community_likes', 'user_id'], ['gangnamunni_community_posts', 'mb_id'], ['gangnamunni_members', 'mb_id'],
  ['gangnamunni_withdrawals', 'mb_id'], ['g5_point', 'mb_id'], ['g5_scrap', 'mb_id'], ['g5_memo', 'me_recv_mb_id'], ['g5_memo', 'me_send_mb_id'],
];
export const WITHDRAW_STAFF_ROLES = ['hospital_super_admin', 'hospital_admin', 'hospital_sub_admin', 'hospital_staff'] as const;
