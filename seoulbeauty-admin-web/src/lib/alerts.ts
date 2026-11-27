// The admin's own alert inbox: shared names and the refresh rhythm of the bell in the top bar.

export const ALERT_TABS = ["all", "activity", "pending", "system"] as const;
export type AlertTab = (typeof ALERT_TABS)[number];

/** Sent on `window` with the new unread count (a number) whenever a page learns it, so the bell updates without waiting for its timer. */
export const ALERT_UNREAD_EVENT = "admin:alerts-unread";

/** How often the bell asks again while the page is visible (it also asks when the tab gets focus). */
export const ALERT_POLL_MS = 60_000;

/** The page of the inbox (reached from the bell, not from the menu). */
export const ALERTS_ROUTE = "/alerts";

import type { IconName } from "@/components/Icon";
import type { MessageKey } from "./i18n";
import { PHP_BASE_URL } from "./config";

/** Colour of a type: activity = accent, waiting for a decision = warn, a fault = down, a notice about the system itself = up. */
export type AlertTone = "ta" | "tw" | "td" | "tu";
type TypeMeta = { label: MessageKey; icon: IconName; tone: AlertTone };

export const ALERT_TYPES: Record<string, TypeMeta> = {
  hospital_request: { label: "ad.nt.hospital_request", icon: "hospital", tone: "ta" },
  ad_request: { label: "ad.nt.ad_request", icon: "megaphone", tone: "ta" },
  review_new: { label: "ad.nt.review_new", icon: "star", tone: "ta" },
  consultation_new: { label: "ad.nt.consultation_new", icon: "chat", tone: "ta" },
  appointment_new: { label: "ad.nt.appointment_new", icon: "calendar", tone: "ta" },
  member_join: { label: "ad.nt.member_join", icon: "userPlus", tone: "ta" },
  mail_incoming: { label: "ad.nt.mail_incoming", icon: "mail", tone: "ta" },
  review_pending: { label: "ad.nt.review_pending", icon: "clipboard", tone: "tw" },
  hospital_pending: { label: "ad.nt.hospital_pending", icon: "building", tone: "tw" },
  ad_pending: { label: "ad.nt.ad_pending", icon: "megaphone", tone: "tw" },
  test_fail: { label: "ad.nt.test_fail", icon: "alert", tone: "td" },
  login_fail: { label: "ad.nt.login_fail", icon: "lock", tone: "td" },
  maintenance_toggle: { label: "ad.nt.maintenance_toggle", icon: "gear", tone: "tu" },
  admin_created: { label: "ad.nt.admin_created", icon: "shield", tone: "tu" },
  ai_key_error: { label: "ad.nt.ai_key_error", icon: "sparkle", tone: "td" },
};
/** The types the type filter offers, per tab (the same grouping the server uses). */
export const TAB_TYPE_LIST: Record<AlertTab, string[]> = {
  all: Object.keys(ALERT_TYPES),
  activity: ["hospital_request", "ad_request", "review_new", "consultation_new", "appointment_new", "member_join", "mail_incoming"],
  pending: ["review_pending", "hospital_pending", "ad_pending"],
  system: ["test_fail", "login_fail", "maintenance_toggle", "admin_created", "ai_key_error"],
};
/** A type this panel does not know (the table also holds other rows, e.g. "ad_slot"): shown with a general label. */
const OTHER: TypeMeta = { label: "alerts.type.other", icon: "bell", tone: "ta" };
export const alertMeta = (type: string): TypeMeta => ALERT_TYPES[type] ?? OTHER;

const FAKE_ORIGIN = "https://alerts.invalid";

/**
 * Where an alert's link leads: the page of the old admin it names. Only a relative path is followed (the server already drops the rest);
 * anything that would leave the old admin's own folder is refused here as well. "" = no link.
 */
export function resolveAlertLink(link: string): string {
  if (link === "") return "";
  try {
    const u = new URL(link, FAKE_ORIGIN + PHP_BASE_URL);
    if (u.origin !== FAKE_ORIGIN) return "";
    // Only pages of the old admin: a path that climbs out of it (dot segments are already folded by URL) or turns into "//host" is refused.
    if (!u.pathname.startsWith(PHP_BASE_URL)) return "";
    return u.pathname + u.search + u.hash;
  } catch {
    return "";
  }
}

const KST = 9 * 3600_000;
const pad = (n: number) => String(n).padStart(2, "0");
const kstDay = (ms: number): string => { const d = new Date(ms + KST); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`; };
/** "YYYY-MM-DD HH:MM:SS" (KST, as the server sends it) to epoch milliseconds; NaN when it is not that shape. */
export function kstMs(value: string): number {
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(value);
  return m ? Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] ?? 0)) - KST : NaN;
}

/** 0 = today, 1 = yesterday, 2 = earlier (days of Korea, whatever the browser's time zone is). */
export function dayGroup(createdAt: string, now: number): 0 | 1 | 2 {
  const day = createdAt.slice(0, 10);
  if (day === kstDay(now)) return 0;
  if (day === kstDay(now - 24 * 3600_000)) return 1;
  return 2;
}

export type AlertTime = { key: "alerts.time.now" | "alerts.time.min" | "alerts.time.hour" | "alerts.time.yesterday" | "alerts.time.date"; vars?: Record<string, string> };
/** The time of an alert as a message key with its values: just now / N min / N hours (today), yesterday HH:MM, or the date. */
export function alertTime(createdAt: string, now: number): AlertTime {
  const group = dayGroup(createdAt, now);
  const ms = kstMs(createdAt);
  if (group === 0 && Number.isFinite(ms)) {
    const min = Math.max(0, Math.floor((now - ms) / 60_000));
    if (min < 1) return { key: "alerts.time.now" };
    if (min < 60) return { key: "alerts.time.min", vars: { n: String(min) } };
    return { key: "alerts.time.hour", vars: { n: String(Math.floor(min / 60)) } };
  }
  if (group === 1) return { key: "alerts.time.yesterday", vars: { time: createdAt.slice(11, 16) } };
  return { key: "alerts.time.date", vars: { date: createdAt.slice(0, 10).replace(/-/g, ".") } };
}
