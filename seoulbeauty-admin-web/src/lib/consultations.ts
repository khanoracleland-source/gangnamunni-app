// Pure helpers of the consultations screen.
import type { ConsultationsQuery } from "./api";

export const CONSULTATION_STATUSES = ["pending", "approved", "completed", "rejected", "contact_failed"] as const;
export type ConsultationStatus = (typeof CONSULTATION_STATUSES)[number];
export const isConsultationStatus = (v: string): v is ConsultationStatus => (CONSULTATION_STATUSES as readonly string[]).includes(v);

export type Filters = { hospitalId: number; status: ConsultationStatus | ""; createdDays: number; q: string };
export const EMPTY_FILTERS: Filters = { hospitalId: 0, status: "", createdDays: 0, q: "" };

/** The list a link from the dashboard opens: status and the requested-at window. Anything unknown is dropped. */
export function filtersFromSearch(p: URLSearchParams): Filters {
  const status = p.get("status") ?? "";
  const days = Number(p.get("created_days") ?? "");
  return {
    ...EMPTY_FILTERS,
    status: isConsultationStatus(status) ? status : "",
    createdDays: Number.isInteger(days) && days > 0 && days <= 365 ? days : 0,
  };
}

export const toQuery = (f: Filters, page: number): ConsultationsQuery => ({ ...f, page });
export const queryKey = (q: ConsultationsQuery): string => JSON.stringify([q.hospitalId, q.status, q.createdDays, q.q.trim(), q.page]);

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

export const MESSAGE_MAX = 1000;
export type ConsultationAction = "approve" | "reject" | "contact_failed" | "complete";
export const ACTION_STATUS = { approve: "approved", reject: "rejected", contact_failed: "contact_failed", complete: "completed" } as const;

/** What the member's notice says (always Korean), as the server writes it. */
export const PUSH_APPROVED = { title: "상담신청이 확인되었습니다" } as const;
export const PUSH_FAILED = { title: "병원에서 연락드렸지만 받지 않으셨어요", body: "편한 시간을 골라 다시 요청해주세요." } as const;

/** Which buttons a request in `status` offers: a waiting one is answered, an approved or unreachable one is finished. */
// The server accepts any state from any state; the screen deliberately offers only the steps of the legacy page.
export function actionsFor(status: string): ConsultationAction[] {
  if (status === "pending") return ["approve", "reject", "contact_failed"];
  if (status === "approved" || status === "contact_failed") return ["complete"];
  return [];
}
/** The hospital message can be written while the request is open to the patient's re-request. */
export const canEditMessage = (status: string): boolean => status === "pending" || status === "contact_failed";
/** Length the way the server counts it (code points, not UTF-16 units). */
export const messageLength = (v: string): number => Array.from(v.replace(/\r\n?/g, "\n").trim()).length;
