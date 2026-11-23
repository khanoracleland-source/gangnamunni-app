import { adGrade, Query } from './hospitals.logic';

/** Only active, approved hospitals can be shown in the app, so only they have a place in the display order. */
export const ORDER_SET_SQL = `status = 'approved' AND is_active = 1`;
/** The same order the app uses: hospitals with a place first, then the rest by id. */
export const ORDER_BY_SQL = `CASE WHEN display_order IS NULL OR display_order < 1 THEN 1 ELSE 0 END, display_order ASC, id ASC`;

/** The display order of the whole set, locked until the transaction ends so two moves can not interleave. */
export const lockOrderQuery = (): Query => ({
  sql: `SELECT id, display_order FROM gangnamunni_hospitals WHERE ${ORDER_SET_SQL} ORDER BY ${ORDER_BY_SQL} FOR UPDATE`,
  values: [],
});

export const setOrderQuery = (id: number, position: number): Query => ({
  sql: 'UPDATE gangnamunni_hospitals SET display_order = ? WHERE id = ?',
  values: [position, id],
});

export type Move = { kind: 'step'; delta: -1 | 1 } | { kind: 'to'; position: number };
export type ParsedMove = { ok: true; move: Move } | { ok: false; fields: Record<string, string> };

/** Body of a move: { direction: "up" | "down" } or { position: n } (n >= 1). Exactly one of the two. */
export function parseMoveBody(body: unknown): ParsedMove {
  const b = body && typeof body === 'object' && !Array.isArray(body) ? (body as Record<string, unknown>) : {};
  const hasDir = b.direction !== undefined && b.direction !== null;
  const hasPos = b.position !== undefined && b.position !== null;
  if (hasDir === hasPos) return { ok: false, fields: { [hasDir ? 'position' : 'direction']: hasDir ? 'not_both' : 'required' } };
  if (hasDir) {
    if (b.direction === 'up') return { ok: true, move: { kind: 'step', delta: -1 } };
    if (b.direction === 'down') return { ok: true, move: { kind: 'step', delta: 1 } };
    return { ok: false, fields: { direction: 'bad_value' } };
  }
  const p = b.position;
  if (typeof p !== 'number' || !Number.isInteger(p) || p < 1 || p > 1_000_000) return { ok: false, fields: { position: 'bad_value' } };
  return { ok: true, move: { kind: 'to', position: p } };
}

export interface OrderRow {
  id: number;
  /** the stored value; null / below 1 means the hospital has no place yet */
  order: number | null;
}
export interface MovePlan {
  /** the hospital is part of the set */
  found: boolean;
  /** a requested position past the end of the set */
  outOfRange: boolean;
  /** where the hospital ends up (1-based) and how many hospitals the order has */
  position: number;
  total: number;
  /** the rows whose stored value must change so the stored order is 1..N in the new sequence */
  updates: { id: number; position: number }[];
}

/** Moves one hospital inside the current sequence and renumbers the whole set 1..N, touching only rows whose value changes. */
export function planMove(rows: OrderRow[], id: number, move: Move): MovePlan {
  const total = rows.length;
  const from = rows.findIndex((r) => r.id === id);
  if (from < 0) return { found: false, outOfRange: false, position: 0, total, updates: [] };
  let to = from;
  if (move.kind === 'step') to = Math.min(total - 1, Math.max(0, from + move.delta));
  else if (move.position > total) return { found: true, outOfRange: true, position: from + 1, total, updates: [] };
  else to = move.position - 1;
  const seq = rows.slice();
  const [item] = seq.splice(from, 1);
  seq.splice(to, 0, item);
  const updates: { id: number; position: number }[] = [];
  seq.forEach((r, i) => {
    if (r.order !== i + 1) updates.push({ id: r.id, position: i + 1 });
  });
  return { found: true, outOfRange: false, position: to + 1, total, updates };
}

/** Every hospital that has a place in the app order, in that order (the reorder screen shows them all at once). */
export const orderBoardQuery = () => ({
  sql: `SELECT id, name, address, ad_grade, display_order FROM gangnamunni_hospitals WHERE ${ORDER_SET_SQL} ORDER BY ${ORDER_BY_SQL}`,
  values: [] as unknown[],
});

/** Approved hospitals that are switched off: they have no place in the order. */
export const orderInactiveQuery = () => ({
  sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_hospitals WHERE status = 'approved' AND is_active = 0`,
  values: [] as unknown[],
});

export type OrderBoardItem = { id: number; name: string; address: string; adGrade: string; position: number };
export type OrderBoard = { total: number; inactive: number; items: OrderBoardItem[] };

/** Places are the rows' positions 1..N: that is what the first move would store, so what is shown is what a move works on. */
export function toOrderBoard(rows: Record<string, unknown>[], inactive: number): OrderBoard {
  const items = rows.map((r, i) => ({ id: Number(r.id) || 0, name: String(r.name ?? ''), address: String(r.address ?? ''), adGrade: adGrade(r.ad_grade), position: i + 1 }));
  return { total: items.length, inactive: Math.max(0, inactive), items };
}
