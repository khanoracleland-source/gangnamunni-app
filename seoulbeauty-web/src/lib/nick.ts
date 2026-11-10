/** Instagram-style nickname rules — keep in step with account.logic.ts (API) and profile_edit.php (PHP). */
export const NICK_MAX = 30;

/** Keep only what a nickname may contain while typing: Latin letters (lower-cased), digits, "_" and ".". */
export const cleanNick = (v: string): string => v.toLowerCase().replace(/[^a-z0-9_.]/g, "").slice(0, NICK_MAX);

/** null = format is fine. */
export function nickProblem(nick: string): string | null {
  if (nick === "") return "닉네임을 입력해주세요.";
  if (nick.length > NICK_MAX) return `닉네임은 ${NICK_MAX}자 이하로 입력해주세요.`;
  if (!/^[a-zA-Z0-9_.]+$/.test(nick)) return "닉네임은 영문, 숫자, 밑줄(_), 마침표(.)만 사용할 수 있습니다.";
  if (nick.startsWith(".") || nick.endsWith(".") || nick.includes("..")) return "마침표(.)는 처음이나 끝에 올 수 없고, 연속으로 쓸 수 없습니다.";
  return null;
}
