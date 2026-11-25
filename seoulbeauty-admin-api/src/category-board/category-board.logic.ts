import { Query } from '../hospitals/hospitals.logic';
import { TreatmentsAnswer } from '../treatments/treatments.logic';
import { asObject, fieldError, FieldError, isFieldError, parseIdList } from '../treatments/treatments.write.logic';

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

// ───────── writes ─────────
export const ASSIGN_MAX = 200;

/** Body of "add treatments to a category": a non-empty list of distinct treatment ids. */
export function parseAssignBody(body: unknown): number[] | FieldError | { error: 'invalid_body' } {
  const b = asObject(body);
  if (!b) return { error: 'invalid_body' };
  const ids = parseIdList(b.treatmentIds, 'treatmentIds', ASSIGN_MAX);
  if (isFieldError(ids)) return ids;
  return ids.length === 0 ? fieldError('treatmentIds', 'required') : ids;
}

/** Puts one treatment into a category at the given place of that category's list. */
export const assignQuery = (treatmentId: number, categoryId: number, sortOrder: number): Query => ({
  sql: 'UPDATE gangnamunni_treatment_master SET category_id = ?, sort_order = ? WHERE id = ?',
  values: [categoryId, sortOrder, treatmentId],
});
/** Takes a treatment out of a category it still belongs to (a treatment moved elsewhere in the meantime is left alone). */
export const detachQuery = (treatmentId: number, categoryId: number): Query => ({
  sql: 'UPDATE gangnamunni_treatment_master SET category_id = NULL WHERE id = ? AND category_id = ?',
  values: [treatmentId, categoryId],
});
/** Frees every treatment of a category (they then belong to none). */
export const detachAllQuery = (categoryId: number): Query => ({
  sql: 'UPDATE gangnamunni_treatment_master SET category_id = NULL WHERE category_id = ?',
  values: [categoryId],
});
