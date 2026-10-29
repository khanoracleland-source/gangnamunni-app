import type { BookingHours } from "./api";

/** Pure rules of the booking screen (same as booking.php's date picker and time grid). */
const pad = (n: number) => (n < 10 ? `0${n}` : `${n}`);
export const WEEKDAYS = ["일", "월", "화", "수", "목", "금", "토"];

/** Date from 'YYYY-MM-DD' at local midnight. */
export function parseDay(iso: string): Date {
  const [y, m, d] = iso.split("-").map(Number);
  return new Date(y, m - 1, d);
}
export function fmtDay(d: Date): string { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }

/** Which occurrence of its weekday (0-based, resets each month) a date is: "even" | "odd". */
export const monthParity = (day: number): "even" | "odd" => (Math.floor((day - 1) / 7) % 2 === 0 ? "even" : "odd");

function dayHours(h: BookingHours, iso: string) {
  const open = h.specialOpen[iso];
  if (open) return { open: open.open, close: open.close };
  if (!h.hasWeekly) return null;
  const d = parseDay(iso);
  const w = h.weekly[d.getDay()];
  if (!w) return null;
  if (w.biweekly && w.biweekly !== monthParity(d.getDate())) return null;
  return { open: w.open, close: w.close };
}

/** Bookable days: from today to one month ahead (end exclusive), minus closed weekdays and temporary closures. */
export function bookableDays(h: BookingHours, today: string): string[] {
  const start = parseDay(today);
  const end = new Date(start); end.setMonth(end.getMonth() + 1);
  const out: string[] = [];
  for (const cur = new Date(start); cur < end; cur.setDate(cur.getDate() + 1)) {
    const iso = fmtDay(cur);
    if (h.specialClosed.includes(iso)) continue;
    if (!h.hasWeekly || dayHours(h, iso)) out.push(iso);
  }
  return out;
}

export interface Slot { value: string; label: string; disabled: boolean }
/** Half-hour slots of a day: inside opening hours, lunch removed, past times of today disabled. `closed` when the day has no hours. */
export function timeSlots(h: BookingHours, iso: string, now: string): { closed: boolean; slots: Slot[] } {
  const dh = dayHours(h, iso);
  if (h.hasWeekly && !dh) return { closed: true, slots: [] };
  const today = now.slice(0, 10);
  const nowMin = Number(now.slice(11, 13)) * 60 + Number(now.slice(14, 16));
  const range = dh ?? { open: 540, close: 1081 }; // no hours at all: the old 09:00-18:00 rule
  const slots: Slot[] = [];
  for (let m = 0; m < 24 * 60; m += 30) {
    if (m < range.open || m >= range.close) continue;
    if (h.lunchStartMin !== null && h.lunchEndMin !== null && !h.noLunch && m >= h.lunchStartMin && m < h.lunchEndMin) continue;
    const hh = Math.floor(m / 60), mm = m % 60;
    const value = `${pad(hh)}:${pad(mm)}`;
    const label = `${hh < 12 ? "오전" : "오후"} ${pad(hh % 12 || 12)}:${pad(mm)}`;
    slots.push({ value, label, disabled: iso === today && m <= nowMin });
  }
  return { closed: false, slots };
}

export function formatDateTime(date: string, time: string): string {
  const d = parseDay(date);
  const hour = Number(time.slice(0, 2));
  return `${d.getMonth() + 1}월 ${d.getDate()}일 (${WEEKDAYS[d.getDay()]}) ${hour < 12 ? "오전" : "오후"} ${hour % 12 || 12}:${time.slice(3, 5)}`;
}

export function dday(date: string, today: string): number {
  return Math.max(0, Math.round((parseDay(date).getTime() - parseDay(today).getTime()) / 86400000));
}

/** "은/는" after a Korean word (booking.php koTopic). */
export function koTopic(word: string): string {
  const c = word ? word.charCodeAt(word.length - 1) : 0;
  if (c >= 0xac00 && c <= 0xd7a3) return word + ((c - 0xac00) % 28 ? "은" : "는");
  return `${word}은(는)`;
}

export const won = (n: number) => `${Math.round(n).toLocaleString("ko-KR")}원`;
/** "50,000~90,000원" / "50,000원" / "" like the treatment sheet. */
export function priceRange(min: number, max: number): string {
  if (!(min > 0)) return "";
  return max > min ? `${min.toLocaleString("ko-KR")}~${max.toLocaleString("ko-KR")}원` : won(min);
}
