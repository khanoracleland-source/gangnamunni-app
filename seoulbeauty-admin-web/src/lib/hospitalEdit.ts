// Pure helpers of the hospital edit form: the draft the admin types into, the opening-hours editor model,
// the check before sending, and the diff that decides which fields are sent. No DOM.

import type { HospitalDetail, HospitalFieldsPatch } from "./api";
import { WEEKDAYS, specialClosedDates, type Weekday } from "./hospitals";
import type { MessageKey } from "./i18n";

export const DAY_KEYS: Record<Weekday, MessageKey> = {
  "월": "h.day.mon",
  "화": "h.day.tue",
  "수": "h.day.wed",
  "목": "h.day.thu",
  "금": "h.day.fri",
  "토": "h.day.sat",
  "일": "h.day.sun",
};

// ───────── Opening hours ─────────
// Stored shape (the old panel writes the same): { "월": "09:00~18:00" | "휴무" | "공사중" | "임시휴업" | "09:00~18:00#biweekly:even|odd", …,
// "점심": "13:00~14:00" or no_lunch_break: true, special_closed_dates: ["YYYY-MM-DD"], special_open_dates: { "YYYY-MM-DD": "10:00~15:00" } }.

export const DAY_STATUSES = ["normal", "off", "construction", "temporary", "biweeklyEven", "biweeklyOdd"] as const;
export type DayStatus = (typeof DAY_STATUSES)[number];

export const DAY_STATUS_KEYS: Record<DayStatus, MessageKey> = {
  normal: "h.hours.open",
  off: "h.hours.closed",
  construction: "h.hours.construction",
  temporary: "h.hours.temporary",
  biweeklyEven: "h.hours.biweekly.even",
  biweeklyOdd: "h.hours.biweekly.odd",
};

export type DayDraft = { status: DayStatus; start: string; end: string };

export type HoursDraft = {
  days: Record<Weekday, DayDraft>;
  noLunch: boolean;
  lunchStart: string;
  lunchEnd: string;
  closedDates: string[];
  /** date -> "HH:MM~HH:MM" */
  openDates: Record<string, string>;
};

const CLOSED_WORDS: Record<string, DayStatus> = { "휴무": "off", "공사중": "construction", "임시휴업": "temporary" };
const CLOSED_STATUS_WORDS: Partial<Record<DayStatus, string>> = { off: "휴무", construction: "공사중", temporary: "임시휴업" };

const pad = (time: string): string => (time.length === 4 ? `0${time}` : time);

/** "09:00~18:00" (older rows may use "-"): the two times, or empty strings when the text is not a range. */
function timeRange(value: string): [string, string] {
  const m = /^(\d{1,2}:\d{2})\s*[~–-]\s*(\d{1,2}:\d{2})$/.exec(value.trim());
  return m ? [pad(m[1]), pad(m[2])] : ["", ""];
}

export function hoursFromStored(stored: Record<string, unknown>): HoursDraft {
  const days = {} as Record<Weekday, DayDraft>;
  for (const day of WEEKDAYS) {
    let value = typeof stored[day] === "string" ? (stored[day] as string).trim() : "";
    let parity = "";
    const m = /^(.*)#biweekly:(even|odd)$/.exec(value);
    if (m) {
      value = m[1].trim();
      parity = m[2];
    }
    const closed = Object.prototype.hasOwnProperty.call(CLOSED_WORDS, value) ? CLOSED_WORDS[value] : null;
    let status: DayStatus = "normal";
    if (parity === "even") status = "biweeklyEven";
    else if (parity === "odd") status = "biweeklyOdd";
    else if (closed) status = closed;
    else if (value === "") status = "off";
    const [start, end] = status === "off" || status === "construction" || status === "temporary" ? ["", ""] : timeRange(value);
    days[day] = { status, start, end };
  }
  const lunchRaw = typeof stored["점심"] === "string" ? stored["점심"] : typeof stored["점심시간"] === "string" ? stored["점심시간"] : "";
  const [lunchStart, lunchEnd] = timeRange(lunchRaw);
  const flag = stored.no_lunch_break;
  const openDates: Record<string, string> = {};
  const open = stored.special_open_dates;
  if (open !== null && typeof open === "object" && !Array.isArray(open)) {
    for (const date of Object.keys(open as Record<string, unknown>).sort()) {
      const range = (open as Record<string, unknown>)[date];
      if (typeof range === "string" && range.trim() !== "") openDates[date] = range.trim();
    }
  }
  return {
    days,
    noLunch: flag === true || flag === 1 || flag === "1",
    lunchStart,
    lunchEnd: lunchEnd || "14:00",
    closedDates: [...specialClosedDates(stored)].sort(),
    openDates,
  };
}

/** The stored form of the editor model, key for key what the old panel writes. */
export function hoursToStored(hours: HoursDraft): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const day of WEEKDAYS) {
    const d = hours.days[day];
    const range = d.start && d.end ? `${d.start}~${d.end}` : "";
    if (d.status === "biweeklyEven" || d.status === "biweeklyOdd") {
      out[day] = range ? `${range}#biweekly:${d.status === "biweeklyEven" ? "even" : "odd"}` : "휴무";
    } else if (d.status !== "normal") {
      out[day] = CLOSED_STATUS_WORDS[d.status] ?? "휴무";
    } else {
      out[day] = range || "휴무";
    }
  }
  if (hours.noLunch) out.no_lunch_break = true;
  else if (hours.lunchStart && hours.lunchEnd) out["점심"] = `${hours.lunchStart}~${hours.lunchEnd}`;
  out.special_closed_dates = [...hours.closedDates].sort();
  const open: Record<string, string> = {};
  for (const date of Object.keys(hours.openDates).sort()) open[date] = hours.openDates[date];
  out.special_open_dates = open;
  return out;
}

// ───────── Draft ─────────

export type Draft = {
  name: string;
  ownerName: string;
  businessNumber: string;
  introduction: string;
  address: string;
  phone: string;
  email: string;
  contactName: string;
  contactPhone: string;
  website: string;
  snsUrl: string;
  categories: string[];
  badges: string[];
  treatmentTags: string[];
  subwayNames: string[];
  hours: HoursDraft;
  latitude: string;
  longitude: string;
  showRating: boolean;
};

export type TextKey = "name" | "ownerName" | "businessNumber" | "introduction" | "address" | "phone" | "email" | "contactName" | "contactPhone" | "website" | "snsUrl";
export type ListKey = "categories" | "badges" | "treatmentTags" | "subwayNames";

const TEXT_KEYS: readonly TextKey[] = ["name", "ownerName", "businessNumber", "introduction", "address", "phone", "email", "contactName", "contactPhone", "website", "snsUrl"];
const LIST_KEYS: readonly ListKey[] = ["categories", "badges", "treatmentTags", "subwayNames"];

/** Order in which fields appear on the page: the first field with an error is the one that gets focus. */
export const FIELD_ORDER: readonly string[] = [
  "name", "ownerName", "businessNumber", "introduction",
  "address", "phone", "email", "contactName", "contactPhone", "website", "snsUrl",
  "categories", "badges", "treatmentTags", "subwayNames",
  "businessHours", "latitude", "longitude", "showRating",
];

export function draftFromHospital(h: HospitalDetail["hospital"]): Draft {
  return {
    name: h.name,
    ownerName: h.ownerName,
    businessNumber: h.businessNumber,
    introduction: h.introduction,
    address: h.address,
    phone: h.phone,
    email: h.email,
    contactName: h.contactName,
    contactPhone: h.contactPhone,
    website: h.website,
    snsUrl: h.snsUrl,
    categories: h.categories,
    badges: h.badges,
    treatmentTags: h.treatmentTags,
    subwayNames: h.subwayNames,
    hours: hoursFromStored(h.businessHours),
    latitude: h.latitude === null ? "" : String(h.latitude),
    longitude: h.longitude === null ? "" : String(h.longitude),
    showRating: h.showRating,
  };
}

const sameJson = (a: unknown, b: unknown): boolean => JSON.stringify(a) === JSON.stringify(b);

/** Only what differs from the loaded hospital. Empty object = nothing to save. */
export function buildPatch(original: Draft, draft: Draft): HospitalFieldsPatch {
  const patch: HospitalFieldsPatch = {};
  for (const key of TEXT_KEYS) {
    if (draft[key] !== original[key]) patch[key] = draft[key].trim();
  }
  for (const key of LIST_KEYS) {
    if (!sameJson(draft[key], original[key])) patch[key] = draft[key];
  }
  if (!sameJson(hoursToStored(draft.hours), hoursToStored(original.hours))) patch.businessHours = hoursToStored(draft.hours);
  // The two coordinates are saved together: both a number, or both empty.
  if (draft.latitude.trim() !== original.latitude.trim() || draft.longitude.trim() !== original.longitude.trim()) {
    patch.latitude = draft.latitude.trim() === "" ? null : Number(draft.latitude.trim());
    patch.longitude = draft.longitude.trim() === "" ? null : Number(draft.longitude.trim());
  }
  if (draft.showRating !== original.showRating) patch.showRating = draft.showRating;
  return patch;
}

/** Codes of problems found before sending (the server's own codes come back from the request). */
export type ClientCode = "required" | "bad_format" | "range" | "pair";

const COORD = /^-?\d{1,3}(\.\d+)?$/;

/** Problems the browser can see without asking: a missing name and coordinates that are not a pair of valid numbers. */
export function validateDraft(original: Draft, draft: Draft): Record<string, ClientCode> {
  const errors: Record<string, ClientCode> = {};
  if (draft.name.trim() === "") errors.name = "required";
  const lat = draft.latitude.trim();
  const lng = draft.longitude.trim();
  if (lat !== original.latitude.trim() || lng !== original.longitude.trim()) {
    if (lat !== "" && !COORD.test(lat)) errors.latitude = "bad_format";
    else if (lat !== "" && Math.abs(Number(lat)) > 90) errors.latitude = "range";
    if (lng !== "" && !COORD.test(lng)) errors.longitude = "bad_format";
    else if (lng !== "" && Math.abs(Number(lng)) > 180) errors.longitude = "range";
    if (!errors.latitude && !errors.longitude) {
      if (lat === "" && lng !== "") errors.latitude = "pair";
      else if (lng === "" && lat !== "") errors.longitude = "pair";
    }
  }
  return errors;
}

/** Text for a refused or unanswered change request (anything that is not a field problem or a conflict). */
export function failureKey(status: number): MessageKey {
  if (status === 403) return "h.e.fail.forbidden";
  if (status === 404) return "h.e.fail.gone";
  if (status === 503) return "h.e.fail.unavailable";
  if (status === 0) return "h.e.fail.network";
  return "h.e.fail.server";
}
