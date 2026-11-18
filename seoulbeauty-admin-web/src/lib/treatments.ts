// Pure helpers of the treatment master page: page state in the URL, filtering, sorting, derived counts. No DOM.

import type { TreatmentCategory, TreatmentConflict, TreatmentItem } from "./api";
import { PHP_BASE_URL } from "./config";
import { mediaUrl } from "./hospitals";

export const SEARCH_MAX_LENGTH = 100;
/** Characters of the description shown in the list. */
export const DESCRIPTION_PREVIEW_LENGTH = 50;

export const TREATMENT_TABS = ["treatments", "categories", "conflicts"] as const;
export type TreatmentsTab = (typeof TREATMENT_TABS)[number];

export const TREATMENT_SORT_KEYS = ["order", "hashtag", "name", "category", "duration", "recovery", "price", "aliases", "hospitals", "active"] as const;
export type TreatmentSortKey = (typeof TREATMENT_SORT_KEYS)[number];
export type SortDir = "asc" | "desc";

/** Which count card narrows the list: "all" is no narrowing. */
export type ActiveFilter = "all" | "active" | "inactive";

export type TreatmentsQuery = {
  tab: TreatmentsTab;
  /** 0 = every category. */
  cat: number;
  q: string;
  /** "order" is the stored order (category, then sort order), the default. */
  sort: TreatmentSortKey;
  dir: SortDir;
};

export const DEFAULT_QUERY: TreatmentsQuery = { tab: "treatments", cat: 0, q: "", sort: "order", dir: "asc" };

const positiveInt = (value: string | null): number => (value !== null && /^\d{1,9}$/.test(value) ? Number(value) : 0);

/** Reads the page state from the address. Anything missing or invalid becomes the default, never an error. */
export function parseTreatmentsQuery(params: { get(name: string): string | null }): TreatmentsQuery {
  const tab = params.get("tab");
  const sort = params.get("sort");
  return {
    tab: (TREATMENT_TABS as readonly string[]).includes(tab ?? "") ? (tab as TreatmentsTab) : "treatments",
    cat: positiveInt(params.get("cat")),
    q: (params.get("q") ?? "").trim().slice(0, SEARCH_MAX_LENGTH),
    sort: (TREATMENT_SORT_KEYS as readonly string[]).includes(sort ?? "") ? (sort as TreatmentSortKey) : "order",
    dir: params.get("dir") === "desc" ? "desc" : "asc",
  };
}

/** The page address for a state; defaults are left out so the plain page is just "/treatments". */
export function treatmentsHref(query: Partial<TreatmentsQuery>): string {
  const full = { ...DEFAULT_QUERY, ...query };
  const params = new URLSearchParams();
  if (full.tab !== "treatments") params.set("tab", full.tab);
  if (full.cat > 0) params.set("cat", String(full.cat));
  if (full.q !== "") params.set("q", full.q);
  if (full.sort !== "order") params.set("sort", full.sort);
  if (full.sort !== "order" && full.dir === "desc") params.set("dir", "desc");
  const qs = params.toString();
  return qs ? `/treatments?${qs}` : "/treatments";
}

export const hasTreatmentFilters = (query: TreatmentsQuery, active: ActiveFilter): boolean => query.cat > 0 || query.q !== "" || active !== "all";

export const fold = (value: string): string => value.normalize("NFC").toLowerCase();

/** Does the treatment match the search text? Name, hashtag, description and every alias are searched, case folded. */
export function matchesSearch(item: TreatmentItem, q: string): boolean {
  const needle = fold(q.trim());
  if (needle === "") return true;
  if (fold(item.name).includes(needle) || fold(item.hashtag).includes(needle) || fold(item.description).includes(needle)) return true;
  return item.aliases.some((a) => fold(a.alias).includes(needle));
}

export function filterTreatments(items: TreatmentItem[], query: Pick<TreatmentsQuery, "cat" | "q">, active: ActiveFilter): TreatmentItem[] {
  return items.filter((item) => {
    if (query.cat > 0 && item.categoryId !== query.cat) return false;
    if (active === "active" && !item.isActive) return false;
    if (active === "inactive" && item.isActive) return false;
    return matchesSearch(item, query.q);
  });
}

const collator = new Intl.Collator("ko", { numeric: true, sensitivity: "base" });
const byText = (a: string, b: string): number => collator.compare(a, b);
const byNumber = (a: number, b: number): number => a - b;

/**
 * A new array in the asked order. "order" is the stored order (the order the server sent); every other key is
 * compared on its own field, ties keep the stored order so the result is stable.
 */
export function sortTreatments(items: TreatmentItem[], sort: TreatmentSortKey, dir: SortDir): TreatmentItem[] {
  if (sort === "order" && dir === "asc") return items.slice();
  const sign = dir === "desc" ? -1 : 1;
  const compare = (a: TreatmentItem, b: TreatmentItem): number => {
    switch (sort) {
      case "order":
        return 0;
      case "hashtag":
        return byText(a.hashtag, b.hashtag);
      case "name":
        return byText(a.name, b.name);
      case "category":
        return byText(a.categoryName, b.categoryName);
      case "duration":
        return byNumber(a.durationMin, b.durationMin);
      case "recovery":
        return byNumber(a.recoveryDays, b.recoveryDays);
      case "price":
        return byNumber(a.avgPriceMin, b.avgPriceMin) || byNumber(a.avgPriceMax, b.avgPriceMax);
      case "aliases":
        return byNumber(a.aliasCount, b.aliasCount);
      case "hospitals":
        return byNumber(a.hospitalCount, b.hospitalCount);
      case "active":
        return byNumber(a.isActive ? 1 : 0, b.isActive ? 1 : 0);
    }
  };
  return items
    .map((item, index) => ({ item, index }))
    .sort((x, y) => sign * compare(x.item, y.item) || x.index - y.index)
    .map((x) => x.item);
}

/** The next sort state after a click on a column: a new column starts ascending, the same column turns around, and a third click returns to the stored order. */
export function nextSort(current: Pick<TreatmentsQuery, "sort" | "dir">, key: TreatmentSortKey): Pick<TreatmentsQuery, "sort" | "dir"> {
  if (current.sort !== key) return { sort: key, dir: "asc" };
  if (current.dir === "asc") return { sort: key, dir: "desc" };
  return { sort: "order", dir: "asc" };
}

/** Ids of the treatments that cannot be booked together with `id`, in the order the pairs are stored. */
export function conflictIdsOf(conflicts: TreatmentConflict[], id: number): number[] {
  const out: number[] = [];
  for (const c of conflicts) {
    if (c.a === id) out.push(c.b);
    else if (c.b === id) out.push(c.a);
  }
  return out;
}

export type TreatmentStats = { total: number; active: number; inactive: number; categories: number };

/** The count cards, computed from the rows so they stay right after a row changes. `categories` counts active categories, as the old panel does. */
export function deriveStats(items: TreatmentItem[], categories: TreatmentCategory[]): TreatmentStats {
  const active = items.filter((item) => item.isActive).length;
  return { total: items.length, active, inactive: items.length - active, categories: categories.filter((c) => c.isActive).length };
}

/** How many treatments each category has, from the rows (every treatment, inactive ones included). */
export function countByCategory(items: TreatmentItem[]): Map<number, number> {
  const out = new Map<number, number>();
  for (const item of items) out.set(item.categoryId, (out.get(item.categoryId) ?? 0) + 1);
  return out;
}

/** Address of a treatment's default image: full addresses as they are, a relative path under the old panel's folder; "" when there is none. */
export const imageUrl = (stored: string | null): string => (stored === null ? "" : mediaUrl(stored));

/** The first characters of the description on one line; "…" marks a cut. */
export function descriptionPreview(description: string, max = DESCRIPTION_PREVIEW_LENGTH): string {
  const oneLine = description.replace(/\s+/g, " ").trim();
  if (oneLine.length <= max) return oneLine;
  return `${Array.from(oneLine).slice(0, max).join("").trimEnd()}…`;
}

/** "150~350" in units of 10,000 won; "" when no price range is stored. */
export function priceRange(min: number, max: number): string {
  if (min <= 0 && max <= 0) return "";
  if (min > 0 && max > 0 && min !== max) return `${min}~${max}`;
  return String(min > 0 ? min : max);
}

/** The hospitals list narrowed to the hospitals that offer this treatment. */
export const hospitalsOfTreatmentHref = (id: number): string => `/hospitals?treatmentId=${id}`;

/** The treatment master in the old panel, where changes are made while this page is read-only. */
export const legacyTreatmentsUrl = `${PHP_BASE_URL}admin_treatments.php`;

/** Name of a treatment by id; a placeholder for one that is gone. */
export function treatmentName(items: TreatmentItem[], id: number, missing: (id: number) => string): string {
  const found = items.find((item) => item.id === id);
  return found ? found.name : missing(id);
}
