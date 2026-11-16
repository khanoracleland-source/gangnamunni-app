// Pure helpers of the hospitals pages: list state in the URL, stored URLs, opening hours. No DOM.

import { DEFAULT_PER_PAGE, PER_PAGE_OPTIONS, type ActiveFilter, type HospitalListQuery } from "./api";
import { PHP_BASE_URL } from "./config";

export const SEARCH_MAX_LENGTH = 100;

const positiveInt = (value: string | null): number => {
  if (value === null || !/^\d{1,9}$/.test(value)) return 0;
  return Number(value);
};

/** Reads the list state from the address. Anything missing or invalid becomes the default, never an error. */
export function parseListQuery(params: { get(name: string): string | null }): HospitalListQuery {
  const active = params.get("active");
  const perPage = positiveInt(params.get("perPage"));
  return {
    search: (params.get("search") ?? "").trim().slice(0, SEARCH_MAX_LENGTH),
    treatmentId: positiveInt(params.get("treatmentId")),
    active: active === "1" || active === "0" ? (active as ActiveFilter) : "all",
    page: Math.max(1, positiveInt(params.get("page"))),
    perPage: (PER_PAGE_OPTIONS as readonly number[]).includes(perPage) ? perPage : DEFAULT_PER_PAGE,
  };
}

/** The list address for a state; defaults are left out so the plain list is just "/hospitals". */
export function listHref(query: HospitalListQuery): string {
  const params = new URLSearchParams();
  if (query.search) params.set("search", query.search);
  if (query.treatmentId > 0) params.set("treatmentId", String(query.treatmentId));
  if (query.active !== "all") params.set("active", query.active);
  if (query.perPage !== DEFAULT_PER_PAGE) params.set("perPage", String(query.perPage));
  if (query.page > 1) params.set("page", String(query.page));
  const qs = params.toString();
  return qs ? `/hospitals?${qs}` : "/hospitals";
}

export const hasFilters = (query: HospitalListQuery): boolean => query.search !== "" || query.treatmentId > 0 || query.active !== "all";

/** Page numbers to offer: up to `size` of them, around the current page. */
export function pageWindow(page: number, pages: number, size = 5): number[] {
  if (pages <= 0) return [];
  let start = Math.max(1, page - Math.floor(size / 2));
  const end = Math.min(pages, start + size - 1);
  start = Math.max(1, end - size + 1);
  const out: number[] = [];
  for (let i = start; i <= end; i += 1) out.push(i);
  return out;
}

/**
 * Address of a stored image. Full http(s) addresses and site-absolute paths ("/x", not "//host") are used as they are;
 * anything else is a path relative to the old panel's folder. Other schemes (data:, javascript:) give "".
 */
export function mediaUrl(stored: string): string {
  const value = stored.trim();
  if (value === "") return "";
  if (/^https?:\/\//i.test(value)) return value;
  // "//host/path" is a protocol-relative address of another site, not a path on this one.
  if (value.startsWith("//")) return "";
  if (value.startsWith("/")) return value;
  if (/^[a-z][a-z0-9+.-]*:/i.test(value)) return "";
  return `${PHP_BASE_URL}${value.replace(/^(\.\/)+/, "")}`;
}

/**
 * Address for a link the hospital typed in (website, SNS, chat channel). These fields are free text:
 * only a value that starts with http:// or https:// becomes a link; anything else gives "" and is shown as text.
 */
export function externalHref(stored: string): string {
  const value = stored.trim();
  return /^https?:\/\/\S+$/i.test(value) ? value : "";
}

/** The hospital's page in the old panel, opened for editing. */
export const legacyEditUrl = (id: number): string => `${PHP_BASE_URL}admin_hospitals.php?view=${id}&edit=1`;

/** The old panel's image manager of one hospital (edit order, hide, delete): not done in the new panel. */
export const legacyImagesUrl = (id: number): string => `${PHP_BASE_URL}admin_hospital_images.php?hospital_id=${id}`;

/** The old panel's doctor manager, filtered to one hospital (edit, delete, link treatments): not done in the new panel. */
export const legacyDoctorsUrl = (id: number): string => `${PHP_BASE_URL}admin_doctors.php?hospital=${id}`;

/** The old panel's treatment manager of one hospital (prices, images, pre-visit questions): not done in the new panel. */
export const legacyTreatmentsUrl = (id: number): string => `${PHP_BASE_URL}admin_hospital_treatments.php?hospital_id=${id}`;

/** A submitted document, served by the old panel (it checks the viewer's permission itself). */
export const legacyDocumentUrl = (id: number, doc: "business" | "medical"): string =>
  `${PHP_BASE_URL}hospital_document.php?hospital_id=${id}&doc=${doc}`;

// ───────── Opening hours ─────────
// Stored as { "월": "09:00-18:00", …, "점심": "13:00-14:00", no_lunch_break, special_closed_dates, special_open_dates }.
// A weekday value is a time range, a closed word, or a range followed by "#biweekly:even|odd".

export const WEEKDAYS = ["월", "화", "수", "목", "금", "토", "일"] as const;
export type Weekday = (typeof WEEKDAYS)[number];

export type ClosedReason = "closed" | "construction" | "temporary";
const CLOSED_WORDS: Record<string, ClosedReason> = { "": "closed", "휴무": "closed", "공사중": "construction", "임시휴업": "temporary" };

export type DayHours =
  | { open: false; reason: ClosedReason }
  | { open: true; hours: string; biweekly: "" | "even" | "odd" };

export function dayHours(stored: unknown): DayHours {
  let value = (typeof stored === "string" ? stored : "휴무").trim();
  let biweekly: "" | "even" | "odd" = "";
  const m = /^(.*)#biweekly:(even|odd)$/.exec(value);
  if (m) {
    value = m[1].trim();
    biweekly = m[2] as "even" | "odd";
  }
  if (biweekly === "" && Object.prototype.hasOwnProperty.call(CLOSED_WORDS, value)) return { open: false, reason: CLOSED_WORDS[value] };
  return { open: true, hours: value, biweekly };
}

export const hasBusinessHours = (hours: Record<string, unknown>): boolean => Object.keys(hours).length > 0;

/** Lunch break as stored; null = the hospital said it has none; "" = not filled in. */
export function lunchBreak(hours: Record<string, unknown>): string | null {
  const off = hours.no_lunch_break;
  if (off === true || off === 1 || off === "1") return null;
  const value = hours["점심"];
  return typeof value === "string" ? value.trim() : "";
}

export function specialClosedDates(hours: Record<string, unknown>): string[] {
  const value = hours.special_closed_dates;
  const items = Array.isArray(value) ? value : typeof value === "string" && value.trim() !== "" ? [value] : [];
  return items.filter((item): item is string => typeof item === "string" && item.trim() !== "");
}

/** "2026-12-25 (10:00-15:00)" entries, sorted by date. */
export function specialOpenDates(hours: Record<string, unknown>): string[] {
  const value = hours.special_open_dates;
  if (value === null || typeof value !== "object" || Array.isArray(value)) return [];
  return Object.entries(value as Record<string, unknown>)
    .map(([date, range]) => (typeof range === "string" && range.trim() !== "" ? `${date} (${range})` : date))
    .sort();
}
