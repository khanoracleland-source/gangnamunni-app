export const PROFILE_PHOTO_MAX_BYTES = 5 * 1024 * 1024;
export const BIRTH_CHANGE_DAYS = 30;

/** The eight toggles of the 설정 page (design 07-01), in page order. Other columns of the table (noti_general, noti_event, ...) are not editable here. */
export const SETTING_FIELDS = ['noti_booking', 'noti_visit', 'noti_community', 'noti_review', 'mkt_push', 'mkt_sms', 'mkt_email', 'mkt_night_push'] as const;
export type SettingField = (typeof SETTING_FIELDS)[number];
export const SETTING_DEFAULTS: Record<SettingField, number> = {
  noti_booking: 1, noti_visit: 1, noti_community: 1, noti_review: 1, mkt_push: 0, mkt_sms: 0, mkt_email: 0, mkt_night_push: 0,
};
export function isSettingField(v: unknown): v is SettingField { return typeof v === 'string' && (SETTING_FIELDS as readonly string[]).includes(v); }

/** profile_birth_to_input(): stored Ymd / Y-m-d -> "Y-m-d", '' when empty or not a real date. */
export function birthToInput(raw: unknown): string {
  const d = String(raw ?? '').replace(/\D+/g, '');
  if (d.length !== 8 || d === '00000000') return '';
  const y = +d.slice(0, 4), m = +d.slice(4, 6), day = +d.slice(6, 8);
  const t = new Date(Date.UTC(y, m - 1, day));
  if (t.getUTCFullYear() !== y || t.getUTCMonth() !== m - 1 || t.getUTCDate() !== day) return '';
  return `${d.slice(0, 4)}-${d.slice(4, 6)}-${d.slice(6, 8)}`;
}

/** Same rule as sign-up: a real date, not after today, at most 120 years back (today is a KST "YYYY-MM-DD"). */
export function validBirth(input: string, today: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(input) || birthToInput(input) !== input) return false;
  const limit = `${+today.slice(0, 4) - 120}${today.slice(4)}`;
  return input <= today && input >= limit;
}

/** Instagram-style nickname: 1–30 Latin letters, digits, "_" and "."; no "." at the start/end or twice in a row; case-insensitive (stored lower-case). */
export const NICK_MAX = 30;
export function nickProblem(nick: string): string | null {
  if (nick === '') return '닉네임을 입력해주세요.';
  if (nick.length > NICK_MAX) return `닉네임은 ${NICK_MAX}자 이하로 입력해주세요.`;
  if (!/^[a-zA-Z0-9_.]+$/.test(nick)) return '닉네임은 영문, 숫자, 밑줄(_), 마침표(.)만 사용할 수 있습니다.';
  if (nick.startsWith('.') || nick.endsWith('.') || nick.includes('..')) return '마침표(.)는 처음이나 끝에 올 수 없고, 연속으로 쓸 수 없습니다.';
  return null;
}
export const HP_RE = /^[0-9+\-() ]{0,20}$/;

export type ProfileCheck = { field: 'name' | 'birth' | 'nick' | 'hp'; message: string } | null;
/** Validation order of save_all: name, birth, nick (+ the phone, which PHP did not check at all). */
export function checkProfile(name: string, nick: string, hp: string, birth: string, today: string, currentNick?: string): ProfileCheck {
  if (name === '' || name.length > 50) return { field: 'name', message: '이름을 입력해주세요.' };
  if (!validBirth(birth, today)) return { field: 'birth', message: '올바른 생년월일을 입력해주세요.' };
  // an unchanged nickname is not re-checked: older members (sign-up / social login) may have one that breaks today's rules
  const nickBad = nick === currentNick ? null : nickProblem(nick);
  if (nickBad) return { field: 'nick', message: nickBad };
  if (!HP_RE.test(hp)) return { field: 'hp', message: '올바른 휴대폰 번호를 입력해주세요.' };
  return null;
}

/** mime_content_type() of the PHP page: gif is stored with a .jpg name there too. */
export function photoKind(buf: Buffer): { ext: 'jpg' | 'png' | 'webp' } | null {
  if (buf.length >= 3 && buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return { ext: 'jpg' };
  if (buf.length >= 8 && buf.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return { ext: 'png' };
  if (buf.length >= 6 && ['GIF87a', 'GIF89a'].includes(buf.subarray(0, 6).toString('latin1'))) return { ext: 'jpg' };
  if (buf.length >= 12 && buf.subarray(0, 4).toString('latin1') === 'RIFF' && buf.subarray(8, 12).toString('latin1') === 'WEBP') return { ext: 'webp' };
  return null;
}
