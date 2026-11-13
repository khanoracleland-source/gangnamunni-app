// Pure formatting helpers: no DOM, no locale lookups, same output on server and client.

/** 1234567 -> "1,234,567". Anything that is not a finite number -> "—". */
export function formatNumber(value: number): string {
  if (!Number.isFinite(value)) return "—";
  const rounded = Math.round(value);
  const digits = Math.abs(rounded).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  return rounded < 0 ? `-${digits}` : digits;
}

/**
 * Change of `current` against `previous` in percent.
 * null when there is nothing to compare with (previous period is zero or invalid).
 */
export function percentChange(current: number, previous: number): number | null {
  if (!Number.isFinite(current) || !Number.isFinite(previous) || previous <= 0) return null;
  return ((current - previous) / previous) * 100;
}

/** part / total in percent; null when total is zero. */
export function percentOf(part: number, total: number): number | null {
  if (!Number.isFinite(part) || !Number.isFinite(total) || total <= 0) return null;
  return (part / total) * 100;
}

/** 12.345 -> "12.3%"; null -> "—". Sign is dropped: direction is shown separately. */
export function formatPercent(value: number | null, fractionDigits = 1): string {
  if (value === null || !Number.isFinite(value)) return "—";
  return `${Math.abs(value).toFixed(fractionDigits)}%`;
}

export type Direction = "up" | "down" | "flat" | "none";

/** Direction of a percent change after rounding to what is displayed (0.04% reads as flat). */
export function changeDirection(value: number | null, fractionDigits = 1): Direction {
  if (value === null || !Number.isFinite(value)) return "none";
  const shown = Number(value.toFixed(fractionDigits));
  if (shown > 0) return "up";
  if (shown < 0) return "down";
  return "flat";
}

/** Date -> "HH:MM:SS" in the viewer's local time. */
export function formatClock(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

/** Values this long (digits, commas and unit together) are drawn in the smaller size so they never clip. */
export const LONG_VALUE_LENGTH = 12;

export function isLongValue(text: string): boolean {
  return text.length >= LONG_VALUE_LENGTH;
}
