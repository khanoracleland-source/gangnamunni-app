// Pure helpers of the price units page: the name rule, the search, the sentences for refused changes. No DOM.

import type { TreatmentUnit, UnitAddStatus, WriteFailure } from "./api";
import type { MessageKey } from "./i18n";

/** Same limit as the server and the units table (VARCHAR(20)), counted in whole characters. */
export const UNIT_NAME_MAX = 20;
export const UNIT_SEARCH_MAX = 40;

/** Why a typed name is refused before it is sent; null = fine. Mirrors the server rule: trimmed, 1..20 characters, no control characters. */
export function unitNameProblem(raw: string): "required" | "invalid" | null {
  const name = raw.trim();
  if (name === "") return "required";
  if (Array.from(name).length > UNIT_NAME_MAX) return "invalid";
  if (/[\u0000-\u001f\u007f]/.test(name)) return "invalid";
  return null;
}

/** The units whose name contains the search text, ignoring case and surrounding spaces. An empty search keeps the list as it is. */
export function filterUnits(units: TreatmentUnit[], search: string): TreatmentUnit[] {
  const q = search.trim().toLocaleLowerCase();
  return q === "" ? units : units.filter((u) => u.name.toLocaleLowerCase().includes(q));
}

/** Reads the search text from the address (`?q=`); anything else is an empty search. */
export function parseUnitsQuery(params: { get(name: string): string | null }): string {
  return (params.get("q") ?? "").slice(0, UNIT_SEARCH_MAX);
}

/** The address of the page for a search text (no `?` when empty). */
export const unitsHref = (q: string): string => (q.trim() === "" ? "/treatment-units" : `/treatment-units?q=${encodeURIComponent(q.trim().slice(0, UNIT_SEARCH_MAX))}`);

export const addToastKey = (status: UnitAddStatus): MessageKey => (status === "restored" ? "tu.toast.restored" : status === "exists" ? "tu.toast.exists" : "tu.toast.created");

/** The sentence for a refused add or remove. A 400 on the name is the "invalid" one; a missing table has its own. */
export function unitFailureKey(f: WriteFailure): MessageKey {
  if (f.status === 0) return "tu.err.network";
  if (f.status === 400) return f.field === "name" && f.reason === "required" ? "tu.err.required" : "tu.err.invalid";
  if (f.status === 401) return "tu.err.session";
  if (f.status === 403 && f.error !== "csrf") return "tu.err.session";
  if (f.status === 404) return "tu.err.notFound";
  if (f.status === 503) return f.error === "table_missing" ? "tu.err.tableMissing" : "tu.err.unavailable";
  return "tu.err.generic";
}
