// Pure helpers of the consultations screen.
import type { ConsultationsQuery } from "./api";

export const CONSULTATION_STATUSES = ["pending", "approved", "completed", "rejected", "contact_failed"] as const;
export type ConsultationStatus = (typeof CONSULTATION_STATUSES)[number];
export const isConsultationStatus = (v: string): v is ConsultationStatus => (CONSULTATION_STATUSES as readonly string[]).includes(v);

export type Filters = { hospitalId: number; status: ConsultationStatus | ""; q: string };
export const EMPTY_FILTERS: Filters = { hospitalId: 0, status: "", q: "" };

export const toQuery = (f: Filters, page: number): ConsultationsQuery => ({ ...f, page });
export const queryKey = (q: ConsultationsQuery): string => JSON.stringify([q.hospitalId, q.status, q.q.trim(), q.page]);

/** A request waiting longer than this is shown as late (the list marks its time). */
export const STALE_AFTER_HOURS = 24;

/** "2026-11-24 10:00:00" is a time stored in Korea; is it more than STALE_AFTER_HOURS before `now`? Unreadable -> false. */
export function isStale(createdAt: string, now: Date = new Date()): boolean {
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(createdAt);
  if (!m) return false;
  const at = Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]) - 9, Number(m[5]));
  return now.getTime() - at > STALE_AFTER_HOURS * 3_600_000;
}

const TAG = /^\[([^\]]+)\]\s*(.*)$/u;
/** The value of a form line such as "[관심 시술] 보톡스" when its label is `label`, else "". */
export function tagValue(tags: readonly string[], label: string): string {
  for (const t of tags) {
    const m = TAG.exec(t);
    if (m && m[1] === label) return m[2].trim();
  }
  return "";
}

export const TYPE_KEYS: Record<string, "cs.type.general" | "cs.type.doctor" | "cs.type.kakao"> = { general: "cs.type.general", doctor: "cs.type.doctor", kakao: "cs.type.kakao" };
