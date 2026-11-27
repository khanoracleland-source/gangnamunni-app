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
export const notificationsHref = (tab: NotificationTab): string => (tab === "send" ? "/notifications" : `/notifications?tab=${tab}`);

export const NOTIFICATION_TYPES = ["general", "booking", "community", "review", "event", "raffle", "system", "promotion"] as const;
export type NotificationType = (typeof NOTIFICATION_TYPES)[number];
export const TARGETS = ["all", "recent_7days", "event_entrants", "specific_event", "individual", "selected"] as const;
export type Target = (typeof TARGETS)[number];

export const typeKey = (t: string): MessageKey => ((NOTIFICATION_TYPES as readonly string[]).includes(t) ? `nt.type.${t}` : "nt.type.unknown") as MessageKey;
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
