import type { BoardCategory, BoardData } from "./api";

export const CATEGORY_NAME_MAX = 50;

export type NameProblem = "empty" | "long" | "dup";

const key = (s: string): string => s.trim().replace(/\s+/g, " ").toLowerCase();

/** What is wrong with a category name, or null. `exceptId` is the category being renamed (its own name is no duplicate). */
export function checkCategoryName(name: string, categories: { id: number; name: string }[], exceptId = 0): NameProblem | null {
  const clean = name.trim();
  if (clean === "") return "empty";
  if ([...clean].length > CATEGORY_NAME_MAX) return "long";
  const k = key(clean);
  return categories.some((c) => c.id !== exceptId && key(c.name) === k) ? "dup" : null;
}

/** The place of every shown category in the app's home menu (1, 2, 3 ...); a hidden category has none. */
export function homeRanks(categories: BoardCategory[]): Map<number, number> {
  const ranks = new Map<number, number>();
  let n = 0;
  for (const c of categories) if (c.isActive) ranks.set(c.id, ++n);
  return ranks;
}

/** The order of all category ids with one of them moved a step; null when it cannot move that way. */
export function movedIds(categories: BoardCategory[], id: number, dir: -1 | 1): number[] | null {
  const ids = categories.map((c) => c.id);
  const at = ids.indexOf(id);
  const to = at + dir;
  if (at < 0 || to < 0 || to >= ids.length) return null;
  [ids[at], ids[to]] = [ids[to], ids[at]];
  return ids;
}

/** The order value for a category added at the end of the home menu. */
export const nextCategoryOrder = (categories: BoardCategory[]): number => categories.reduce((m, c) => Math.max(m, c.sortOrder), 0) + 1;

export type PickItem = { id: number; name: string; isActive: boolean; fromId: number; fromName: string; here: boolean };
export type PickFilter = "all" | "none" | "other";

/** Every treatment as a candidate for one category: where it sits now (fromId 0 = nowhere) and whether it is already in this one. */
export function pickItems(data: BoardData, categoryId: number): PickItem[] {
  const out: PickItem[] = [];
  for (const c of data.categories) {
    for (const t of c.treatments) out.push({ id: t.id, name: t.name, isActive: t.isActive, fromId: c.id, fromName: c.name, here: c.id === categoryId });
  }
  for (const t of data.uncategorized) out.push({ id: t.id, name: t.name, isActive: t.isActive, fromId: 0, fromName: "", here: false });
  return out;
}

export const matchesQuery = (name: string, query: string): boolean => {
  const q = query.trim().toLowerCase();
  return q === "" || name.toLowerCase().includes(q);
};

export function filterPick(items: PickItem[], filter: PickFilter, query: string): PickItem[] {
  return items.filter((i) => matchesQuery(i.name, query) && (filter === "all" || (filter === "none" ? i.fromId === 0 : i.fromId !== 0 && !i.here)));
}
