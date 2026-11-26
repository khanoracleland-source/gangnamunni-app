// Pure helpers of the members screen.
import type { MemberFilter, MembersQuery, MemberStatus, WriteFailure } from "./api";
import type { MessageKey } from "./i18n";

export const PER_PAGE_CHOICES = [10, 20, 50, 100] as const;
export const STATUS_FILTERS: readonly MemberFilter[] = ["all", "active", "blocked", "left"];
export const DEFAULT_QUERY: MembersQuery = { search: "", status: "all", sort: "joined", order: "desc", page: 1, perPage: 20 };

export const queryKey = (q: MembersQuery): string => JSON.stringify([q.search.trim(), q.status, q.sort, q.order, q.page, q.perPage]);
export const statusKey = (s: MemberStatus): MessageKey => `mb.st.${s}` as MessageKey;

/** "2026-01-02 03:04:05" -> "2026.01.02" (the stored value, not converted); anything else -> "—". */
export function dayOf(value: string | null): string {
  const m = value ? /^(\d{4})-(\d{2})-(\d{2})/.exec(value) : null;
  return m ? `${m[1]}.${m[2]}.${m[3]}` : "—";
}
/** "2026-01-02 03:04:05" -> "2026.01.02 03:04". */
export function minuteOf(value: string | null): string {
  const m = value ? /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(value) : null;
  return m ? `${m[1]}.${m[2]}.${m[3]} ${m[4]}:${m[5]}` : dayOf(value);
}

/** The password rule the server enforces (letter, digit, special character, 9-20 printable ASCII, no space). */
export const PASSWORD_RE = /^(?=.*[A-Za-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s])[\x21-\x7E]{9,20}$/;

/** The text key for a refused change; `scope` picks the field messages of that dialog. */
export function writeErrorKey(res: WriteFailure, scope: "em" | "pw"): MessageKey {
  if (res.status === 0) return "mb.w.network";
  if (res.status === 401) return "mb.w.forbidden";
  if (res.status === 403) return "mb.w.forbidden";
  if (res.status === 404) return "mb.w.notfound";
  if (res.status === 429) return "mb.w.too_many";
  if (res.status === 503) return "mb.w.unavailable";
  if (res.status === 409) return res.error === "email_taken" ? "mb.em.err.taken" : res.error === "member_left" ? "mb.w.left" : "mb.w.generic";
  if (res.status === 400) {
    const code = scope === "em" ? res.fields.email : res.fields.password;
    if (code === "required") return `mb.${scope}.err.required` as MessageKey;
    if (code === "bad_format") return `mb.${scope}.err.bad_format` as MessageKey;
  }
  return "mb.w.generic";
}

export const BOOKING_STATUSES = ["pending", "hospital_checking", "confirmed", "completed", "cancelled", "refunded", "no_show"] as const;
export const POINT_TYPES = ["earn", "use", "refund", "expire", "admin_add", "admin_sub", "earn_cancel", "admin"] as const;
