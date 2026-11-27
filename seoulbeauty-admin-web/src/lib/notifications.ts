// Pure helpers of the notifications screen (sending to members). No DOM, no requests.
import type { WriteFailure } from "./api";
import type { MessageKey } from "./i18n";

export const NOTIFICATION_TABS = ["send", "history", "scheduled", "auto", "diag"] as const;
export type NotificationTab = (typeof NOTIFICATION_TABS)[number];
export const TAB_LABEL: Record<NotificationTab, MessageKey> = {
  send: "nt.tab.send", history: "nt.tab.history", scheduled: "nt.tab.scheduled", auto: "nt.tab.auto", diag: "nt.tab.diag",
};

export const parseTab = (raw: string | null): NotificationTab => (NOTIFICATION_TABS as readonly string[]).includes(raw ?? "") ? (raw as NotificationTab) : "send";
/** The address of a tab (without the base path; the first tab is the plain page). */
export const notificationsHref = (tab: NotificationTab, id?: number): string => (tab === "send" ? "/notifications" : id && id > 0 ? `/notifications?tab=${tab}&id=${id}` : `/notifications?tab=${tab}`);
/** The send picked in the address (?id=): a positive whole number or none. */
export const parseId = (raw: string | null): number => (raw !== null && /^[1-9]\d{0,9}$/.test(raw) ? Number(raw) : 0);

export const NOTIFICATION_TYPES = ["general", "booking", "community", "review", "event", "raffle", "system", "promotion"] as const;
export type NotificationType = (typeof NOTIFICATION_TYPES)[number];
export const TARGETS = ["all", "recent_7days", "event_entrants", "specific_event", "individual", "selected"] as const;
export type Target = (typeof TARGETS)[number];

export const typeKey = (t: string): MessageKey => ((NOTIFICATION_TYPES as readonly string[]).includes(t) ? `nt.type.${t}` : t.startsWith("booking_") ? "nt.type.booking" : "nt.type.unknown") as MessageKey;
/** Who an automatic notice went to: the member id (target `booking:<id>`), or the admins (`all_admins`). */
export const autoWho = (target: string, admins: string): string => (target === "all_admins" ? admins : target.startsWith("booking:") ? target.slice("booking:".length) : target);
/** Marketing types: the member's 마케팅 푸시 switch decides, and at night also 야간 푸시 (the same rule as the server). */
export const isMarketingType = (t: string): boolean => t === "event" || t === "promotion";

/** One id per press of the send button: the server sends a repeated id only once. 32 letters and digits (the server wants 16-64). */
export function newRequestId(): string {
  const c = typeof crypto !== "undefined" ? crypto : undefined;
  if (c && typeof c.randomUUID === "function") return c.randomUUID().replace(/-/g, "");
  const bytes = new Uint8Array(16);
  if (c) c.getRandomValues(bytes);
  else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
  return Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
}

/** The night window of the server (21:00 up to 08:00 Korean time). */
export const NIGHT_START = 21;
export const NIGHT_END = 8;
export const kstHour = (now: number = Date.now()): number => new Date(now + 9 * 3600_000).getUTCHours();
export const isNightNow = (now: number = Date.now()): boolean => { const h = kstHour(now); return h >= NIGHT_START || h < NIGHT_END; };

/** Why a send, a plan or a cancel was refused: the text of the server's error code (the status when there is no code). */
export function writeErrorKey(res: WriteFailure): MessageKey {
  if (res.status === 0) return "nt.err.network";
  if (res.status === 401 || res.status === 403) return "nt.err.forbidden";
  if (res.status === 404) return "nt.err.notfound";
  if (res.status === 429) return "nt.err.too_many";
  if (res.status === 503) return res.error === "write_unavailable" ? "nt.err.write_unavailable" : "nt.err.not_ready";
  const known: Record<string, MessageKey> = {
    audience_changed: "nt.err.audience_changed", busy: "nt.err.busy", in_progress: "nt.err.in_progress", not_pending: "nt.err.not_pending", too_many_pending: "nt.err.too_many_pending",
    no_recipients: "nt.err.no_recipients", nobody_allowed: "nt.err.nobody_allowed", bad_link: "nt.err.bad_link", title_required: "nt.err.title_required",
    title_too_long: "nt.err.title_too_long", message_too_long: "nt.err.message_too_long", bad_run_at: "nt.err.bad_run_at", run_at_past: "nt.err.run_at_past", run_at_too_far: "nt.err.run_at_too_far",
    members_required: "nt.err.members_required", too_many_members: "nt.err.too_many_members", member_required: "nt.err.member_required", event_required: "nt.err.event_required",
  };
  return known[res.error] ?? "nt.err.generic";
}

export const MAX_SELECTED = 500;
export const MAX_TITLE = 200;
export const MAX_MESSAGE = 1000;
/** Member ids typed one per line or separated by commas, spaces or semicolons: each once, in the order typed. */
export const parseMemberIds = (text: string): string[] => [...new Set(text.split(/[\s,;]+/).map((s) => s.trim()).filter((s) => s !== ""))];
/** Length as the server counts it (code points, not UTF-16 units). */
export const lengthOf = (s: string): number => Array.from(s).length;

// ---- planned time: typed as Korean wall time in a datetime-local input ("YYYY-MM-DDTHH:mm") --------------------------------------
const p2 = (n: number): string => String(n).padStart(2, "0");
/** A moment as Korean wall time, in the format of a datetime-local input. */
export function kstInput(ms: number): string {
  const d = new Date(ms + 9 * 3600_000);
  return `${d.getUTCFullYear()}-${p2(d.getUTCMonth() + 1)}-${p2(d.getUTCDate())}T${p2(d.getUTCHours())}:${p2(d.getUTCMinutes())}`;
}
/** The hour (0-23) of a "YYYY-MM-DDTHH:mm" text; null when it is not one. */
export function hourOfInput(v: string): number | null {
  const m = /^\d{4}-\d{2}-\d{2}T(\d{2}):\d{2}$/.exec(v);
  return m ? Number(m[1]) : null;
}
export const isNightInput = (v: string): boolean => { const h = hourOfInput(v); return h !== null && (h >= NIGHT_START || h < NIGHT_END); };
/** The same text moved to the next 08:00 (the same day when it is before 08:00, else the next day). */
export function morningOf(v: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):/.exec(v);
  if (!m) return v;
  const [y, mo, d, h] = [Number(m[1]), Number(m[2]), Number(m[3]), Number(m[4])];
  const day = new Date(Date.UTC(y, mo - 1, d + (h >= NIGHT_END ? 1 : 0)));
  return `${day.getUTCFullYear()}-${p2(day.getUTCMonth() + 1)}-${p2(day.getUTCDate())}T08:00`;
}
