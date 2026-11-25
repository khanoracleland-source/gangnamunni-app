import { TreatmentsAnswer } from '../treatments/treatments.logic';

export interface BoardTreatment {
  id: number;
  name: string;
  sortOrder: number;
  isActive: boolean;
}
export interface BoardCategory {
  id: number;
  name: string;
  icon: string;
  sortOrder: number;
  isActive: boolean;
  treatments: BoardTreatment[];
}
export interface BoardAnswer {
  categories: BoardCategory[];
  /** Treatments with no category (or a category that no longer exists); the app does not show them. */
  uncategorized: BoardTreatment[];
  stats: { categories: number; activeCategories: number; treatments: number; uncategorized: number };
}

const treatmentOf = (i: TreatmentsAnswer['items'][number]): BoardTreatment => ({ id: i.id, name: i.name, sortOrder: i.sortOrder, isActive: i.isActive });
/** Same order the home page uses: sort order, then popularity (high first), then id. */
const bySort = (a: TreatmentsAnswer['items'][number], b: TreatmentsAnswer['items'][number]): number =>
  a.sortOrder - b.sortOrder || b.popularityScore - a.popularityScore || a.id - b.id;

/** Groups the treatment master answer by category for the home menu page; categories keep the order the home page shows (sort order, id). */
export function buildBoard(snap: TreatmentsAnswer): BoardAnswer {
  const cats = [...snap.categories].sort((a, b) => a.sortOrder - b.sortOrder || a.id - b.id);
  const known = new Set(cats.map((c) => c.id));
  const items = [...snap.items].sort(bySort);
  const categories: BoardCategory[] = cats.map((c) => ({
    id: c.id,
    name: c.name,
    icon: c.icon,
    sortOrder: c.sortOrder,
    isActive: c.isActive,
    treatments: items.filter((i) => i.categoryId === c.id).map(treatmentOf),
  }));
  const uncategorized = items.filter((i) => i.categoryId === null || !known.has(i.categoryId)).map(treatmentOf);
  return {
    categories,
    uncategorized,
    stats: { categories: categories.length, activeCategories: categories.filter((c) => c.isActive).length, treatments: items.length, uncategorized: uncategorized.length },
  };
}

