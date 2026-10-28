/** Display helpers ported from community.php / detail_community.php (API timestamps are KST "YYYY-MM-DD HH:MM:SS"). */

function kst(s: string | null | undefined): number | null {
  if (!s) return null;
  const t = Date.parse(`${s.replace(" ", "T")}+09:00`);
  return Number.isNaN(t) ? null : t;
}

/** community_time_display(): 방금 전 / n분 전 / n시간 전 / n일 전 / mm.dd, plus "(수정됨)" when edited. */
export function timeDisplay(created: string, updated: string | null, now = Date.now()): string {
  const c = kst(created);
  if (c == null) return "";
  const diff = Math.max(0, Math.floor((now - c) / 1000));
  let text: string;
  if (diff < 60) text = "방금 전";
  else if (diff < 3600) text = `${Math.floor(diff / 60)}분 전`;
  else if (diff < 86400) text = `${Math.floor(diff / 3600)}시간 전`;
  else if (diff < 604800) text = `${Math.floor(diff / 86400)}일 전`;
  else text = created.slice(5, 10).replace("-", ".");
  if (updated && updated !== created) text += " (수정됨)";
  return text;
}

/** community_comment_age() */
export function commentAge(created: string, now = Date.now()): string {
  const c = kst(created);
  const s = Math.max(0, Math.floor((now - (c ?? now)) / 1000));
  if (s < 60) return "방금 전";
  if (s < 3600) return `${Math.floor(s / 60)}분 전`;
  if (s < 86400) return `${Math.floor(s / 3600)}시간 전`;
  return `${Math.floor(s / 86400)}일 전`;
}

/** date('y.m.d') */
export function shortDate(created: string): string {
  return created.slice(2, 10).replace(/-/g, ".");
}

export const num = (n: number) => n.toLocaleString("en-US");
