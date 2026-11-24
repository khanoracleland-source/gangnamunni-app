// Pure rules of the content review page: the list address and the tabs. No DOM.

import type { ModerationQuery, ModerationState } from "./api";
import { mediaUrl } from "./hospitals";
import type { MessageKey } from "./i18n";

export const MODERATION_SEARCH_MAX = 100;
export type ModerationTab = "reviews" | "community";
export type ModerationView = ModerationQuery & { tab: ModerationTab };

const positive = (v: string | null): number => (v !== null && /^[1-9]\d{0,9}$/.test(v) ? Number(v) : 0);

/** The state of the page from the address; anything missing or wrong becomes the default. Community is only for a super admin. */
export function parseModerationView(params: { get(name: string): string | null }, canSeeCommunity: boolean): ModerationView {
  const status = params.get("status");
  return {
    tab: params.get("tab") === "community" && canSeeCommunity ? "community" : "reviews",
    status: status === "approved" || status === "rejected" ? status : "pending",
    search: (params.get("search") ?? "").trim().slice(0, MODERATION_SEARCH_MAX),
    hospitalId: positive(params.get("hospitalId")),
    page: Math.min(100000, positive(params.get("page")) || 1),
  };
}

/** The page address for a state (defaults are left out). */
export function moderationHref(v: ModerationView): string {
  const params = new URLSearchParams();
  if (v.tab !== "reviews") params.set("tab", v.tab);
  if (v.status !== "pending") params.set("status", v.status);
  if (v.search !== "") params.set("search", v.search);
  if (v.tab === "reviews" && v.hospitalId > 0) params.set("hospitalId", String(v.hospitalId));
  if (v.page > 1) params.set("page", String(v.page));
  const s = params.toString();
  return s === "" ? "/moderation" : `/moderation?${s}`;
}

export const hasModerationFilters = (v: ModerationView): boolean => v.search !== "" || v.hospitalId > 0;

export const STATE_LABEL: Record<ModerationState, MessageKey> = { pending: "mod.st.pending", approved: "mod.st.ok", rejected: "mod.st.no" };
export const STATE_TONE: Record<ModerationState, "warn" | "on" | "err"> = { pending: "warn", approved: "on", rejected: "err" };
export const MODERATION_STATUSES: ModerationState[] = ["pending", "approved", "rejected"];

/** A picture address that is safe to show: a plain http(s) address or a path on this site; a backslash (read as "/" by browsers) is refused. */
export function imageHref(stored: string): string {
  const url = mediaUrl(stored);
  return url === "" || /[\\\s]/.test(url) ? "" : url;
}

export const PER_PAGE = 20;
