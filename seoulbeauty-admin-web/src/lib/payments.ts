// Pure helpers of the booking payments screen.
import type { CompareMetric, PaymentsQuery } from "./api";
import { formatNumber } from "./format";

/** The list is narrowed by hospital, payment dates and the patient's name only. */
export type Filters = { hospitalId: number; from: string; to: string; q: string };

export const COMPARE_METRICS: readonly CompareMetric[] = ["received", "recognized", "held"];
/** A range of this many days or more is refused by the server. */
export const MAX_RANGE_DAYS = 366;

const parseDay = (iso: string): number => {
  const [y, m, d] = iso.split("-").map(Number);
  return Date.UTC(y, m - 1, d);
};

/** The first of the month of `today` (a yyyy-mm-dd day) up to `today`: what the server uses when no dates are sent. */
export const defaultFilters = (today: string): Filters => ({ hospitalId: 0, from: `${today.slice(0, 7)}-01`, to: today, q: "" });

export const toQuery = (f: Filters, page: number): PaymentsQuery => ({ ...f, page });
export const queryKey = (q: PaymentsQuery): string => JSON.stringify([q.hospitalId, q.from, q.to, q.q.trim(), q.page]);

export type RangeProblem = "missing" | "order" | "long";
/** What is wrong with the chosen dates, if anything, before asking the server. */
export function rangeProblem(from: string, to: string): RangeProblem | null {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(from) || !/^\d{4}-\d{2}-\d{2}$/.test(to)) return "missing";
  if (from > to) return "order";
  if ((parseDay(to) - parseDay(from)) / 86400000 >= MAX_RANGE_DAYS) return "long";
  return null;
}

/** 1234000 -> "1,234,000" (the unit is written by the screen: "원" in a heading, nothing in a table cell). */
export const won = (n: number): string => formatNumber(n);
/** A zero in a table cell reads as a dash, so only real amounts catch the eye. */
export const wonOrDash = (n: number): string => (n === 0 ? "—" : formatNumber(n));
/** An amount on a chart axis, in millions with at most one decimal: 12480000 -> 12.5. */
export const millions = (n: number): number => Math.round(n / 100000) / 10;

/** "2026-11-20 14:02:00" -> "11-20 14:02"; anything else -> "—". */
export function monthDayTime(v: string): string {
  const m = /^\d{4}-(\d{2}-\d{2})[ T](\d{2}:\d{2})/.exec(v);
  return m ? `${m[1]} ${m[2]}` : "—";
}
/** "2026-11-23" -> "11-23"; anything else -> "—". */
export function monthDay(v: string): string {
  const m = /^\d{4}-(\d{2}-\d{2})/.exec(v);
  return m ? m[1] : "—";
}

/** "2026-11-20" + 3 -> "2026-11-23" (calendar days, no time zone involved). */
export function addDaysIso(iso: string, days: number): string {
  const d = new Date(parseDay(iso) + days * 86400000);
  return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, "0")}-${String(d.getUTCDate()).padStart(2, "0")}`;
}

/** The top of a chart axis: the next 1, 2, 2.5, 5 or 10 times a power of ten above `max` (at least 1). */
export function niceMax(max: number): number {
  if (!(max > 0)) return 1;
  const pow = 10 ** Math.floor(Math.log10(max));
  for (const m of [1, 2, 2.5, 5, 10]) if (m * pow >= max) return m * pow;
  return 10 * pow;
}

/** An axis label: millions from one million up ("12.5백만" is written by the screen), plain thousands below. */
export const axisIsMillions = (top: number): boolean => top >= 1000000;
