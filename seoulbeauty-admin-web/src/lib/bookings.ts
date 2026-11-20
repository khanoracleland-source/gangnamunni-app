// Pure helpers of the bookings screen.
import type { BookingsQuery } from "./api";

export const BOOKING_STATUSES = ["pending", "hospital_checking", "confirmed", "completed", "cancelled", "refunded", "no_show"] as const;
export type BookingStatus = (typeof BOOKING_STATUSES)[number];
export const isBookingStatus = (v: string): v is BookingStatus => (BOOKING_STATUSES as readonly string[]).includes(v);

export type Filters = { hospitalId: number; status: BookingStatus | ""; from: string; to: string; q: string };
export const EMPTY_FILTERS: Filters = { hospitalId: 0, status: "", from: "", to: "", q: "" };

export const toQuery = (f: Filters, page: number): BookingsQuery => ({ ...f, page });
export const queryKey = (q: BookingsQuery): string => JSON.stringify([q.hospitalId, q.status, q.from, q.to, q.q.trim(), q.page]);

/** A day as yyyy-mm-dd, in the time zone the server counts its days in (Korea), whatever the browser's zone is. */
export function todayIso(now: Date = new Date()): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Seoul", year: "numeric", month: "2-digit", day: "2-digit" }).format(now);
}

/** The day `days` before `from` (a yyyy-mm-dd day), as yyyy-mm-dd. Pure calendar arithmetic, no time zone involved. */
export function dayBefore(from: string, days: number): string {
  const [y, m, d] = from.split("-").map(Number);
  const x = new Date(Date.UTC(y, m - 1, d - days));
  return x.toISOString().slice(0, 10);
}

/** The pager's buttons: every page up to 7, otherwise the first, the last and the neighbours of the current one, with null for a gap. */
export function pageButtons(current: number, total: number): (number | null)[] {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const keep = new Set([1, total, current - 1, current, current + 1].filter((n) => n >= 1 && n <= total));
  const out: (number | null)[] = [];
  let prev = 0;
  for (const n of [...keep].sort((a, b) => a - b)) {
    if (n - prev > 1) out.push(null);
    out.push(n);
    prev = n;
  }
  return out;
}
