// Pure rules of the "add / manage treatments" card: checks, filters and what a failed request says. No DOM.

import type { AvailableTreatment, HospitalTreatment, TreatmentPriceOption, WriteFailure } from "./api";
import type { MessageKey } from "./i18n";

/** The most treatments one assignment request may carry. */
export const ASSIGN_LIMIT = 300;
export const MIN_AGE_MAX = 120;
/** The note column shows this many characters. */
export const NOTE_PREVIEW = 20;

/** Key of the pill for treatments without a category. */
export const NO_CATEGORY = "none";
export const ALL_CATEGORIES = "all";

export const categoryKey = (id: number | null): string => (id === null || id <= 0 ? NO_CATEGORY : String(id));

/** The minimum age as typed: empty or 0 means no limit, else a whole number up to 120 (up to three digits). */
export function parseMinAge(value: string): { ok: true; value: number | null } | { ok: false } {
  const text = value.trim();
  if (text === "") return { ok: true, value: null };
  if (!/^\d{1,3}$/.test(text)) return { ok: false };
  const n = Number(text);
  if (n > MIN_AGE_MAX) return { ok: false };
  return { ok: true, value: n === 0 ? null : n };
}

export const minAgeText = (age: number | null): string => (age === null || age <= 0 ? "" : String(age));

/** The first characters of a note, counted as people count them. */
export const notePreview = (note: string): string => Array.from(note).slice(0, NOTE_PREVIEW).join("");

export type CategoryPill = { key: string; name: string | null; count: number };

/** One pill per category of the given treatments, in the order they first appear. */
export function categoryPills(available: readonly AvailableTreatment[]): CategoryPill[] {
  const pills = new Map<string, CategoryPill>();
  for (const item of available) {
    const key = categoryKey(item.categoryId);
    const found = pills.get(key);
    if (found) found.count += 1;
    else pills.set(key, { key, name: key === NO_CATEGORY ? null : item.categoryName, count: 1 });
  }
  return [...pills.values()];
}

/** The treatments the category pill and the search box leave visible. The search looks at name, hashtag and category. */
export function visibleAvailable(available: readonly AvailableTreatment[], category: string, query: string): AvailableTreatment[] {
  const q = query.trim().toLowerCase();
  return available.filter((item) => {
    if (category !== ALL_CATEGORIES && categoryKey(item.categoryId) !== category) return false;
    if (q === "") return true;
    return [item.name, item.hashtag, item.categoryName ?? ""].some((part) => part.toLowerCase().includes(q));
  });
}

/** The price options that are in the way of an unassign: what disappears with it. */
export const removalImpact = (item: HospitalTreatment): { priceOptions: number; images: number } => ({
  priceOptions: item.priceOptions.length,
  images: item.imageCount,
});

/** Body parts are stored joined by "||". */
export const bodyPartText = (bodyPart: string): string => bodyPart.split("||").map((part) => part.trim()).filter(Boolean).join(", ");

/** The part of a price badge before the prices: option, body part, dose. */
export function priceLabel(option: TreatmentPriceOption): string {
  return [option.optionName.trim(), bodyPartText(option.bodyPart), `${option.dosageValue}${option.dosageUnit}`.trim()].filter(Boolean).join(" · ");
}

export type TreatmentAction = "assign" | "promote" | "age" | "remove";

/**
 * What a failed request says. `refresh`: the list on screen is probably out of date (the treatment or hospital
 * changed or vanished), so the card reads it again. `age`: the message belongs to the minimum age box.
 */
export function treatmentFailure(f: WriteFailure, action: TreatmentAction): { key: MessageKey; refresh: boolean; age: boolean } {
  const age = action === "age";
  if (f.status === 0) return { key: "h.e.tr.err.network", refresh: false, age };
  if (f.status === 400) {
    if (f.error === "no_treatments") return { key: "h.e.tr.err.none", refresh: false, age };
    if (f.error === "invalid_treatment") return { key: "h.e.tr.err.invalid", refresh: true, age };
    if (f.error === "invalid_field") return { key: age ? "h.e.tr.err.age" : "h.e.tr.err.server", refresh: false, age };
  }
  if (f.status === 403) return { key: "h.e.tr.err.forbidden", refresh: false, age };
  if (f.status === 404) return { key: action === "remove" ? "h.e.tr.err.removeOff" : "h.e.tr.err.gone", refresh: true, age };
  if (f.status === 503) return { key: f.error === "min_age_unavailable" ? "h.e.tr.err.ageOff" : "h.e.tr.err.unavailable", refresh: false, age };
  return { key: "h.e.tr.err.server", refresh: false, age };
}
