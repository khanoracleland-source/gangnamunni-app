/** Pure helpers for 설정 > 방문 전 알림 (port of cron_booking_visit_reminder.php). */

/** KST: not before 10:00 of the day before the visit. */
export const VISIT_REMINDER_FROM_HOUR = 10;

/** "2026-11-07": the KST date `days` from now. */
export function kstDate(ms = Date.now(), days = 0): string {
  return new Date(ms + 9 * 3600_000 + days * 86_400_000).toISOString().slice(0, 10);
}

/** KST hour (0-23) of the given moment. */
export function kstHour(ms = Date.now()): number {
  return new Date(ms + 9 * 3600_000).getUTCHours();
}

/** The reminder window is open from 10:00 KST; before that the run does nothing (the member is not woken early). */
export function windowOpen(ms = Date.now()): boolean {
  return kstHour(ms) >= VISIT_REMINDER_FROM_HOUR;
}

export interface RunSummary { date: string; skippedWindow: boolean; reminded: number; skipped: number; failed: number; targets: number[] }
