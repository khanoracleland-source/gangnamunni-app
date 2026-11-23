import { lockOrderQuery, ORDER_BY_SQL, ORDER_SET_SQL, orderBoardQuery, orderInactiveQuery, parseMoveBody, planMove, setOrderQuery, toOrderBoard } from './hospitals.order.logic';

const rows = (orders: (number | null)[]) => orders.map((order, i) => ({ id: 10 + i, order }));
const all = { scope: 'all' as const, hospitalIds: [] };

describe('parseMoveBody', () => {
  it('accepts exactly one of direction and position', () => {
    expect(parseMoveBody({ direction: 'up' })).toEqual({ ok: true, move: { kind: 'step', delta: -1 } });
    expect(parseMoveBody({ direction: 'down' })).toEqual({ ok: true, move: { kind: 'step', delta: 1 } });
    expect(parseMoveBody({ position: 3 })).toEqual({ ok: true, move: { kind: 'to', position: 3 } });
  });
  it('rejects anything else', () => {
    expect(parseMoveBody(undefined)).toMatchObject({ ok: false });
    expect(parseMoveBody({})).toMatchObject({ ok: false, fields: { direction: 'required' } });
    expect(parseMoveBody({ direction: 'up', position: 2 })).toMatchObject({ ok: false });
    expect(parseMoveBody({ direction: 'left' })).toMatchObject({ ok: false, fields: { direction: 'bad_value' } });
    for (const position of [0, -1, 1.5, '2', NaN, 2_000_000]) expect(parseMoveBody({ position })).toMatchObject({ ok: false, fields: { position: 'bad_value' } });
    expect(parseMoveBody([])).toMatchObject({ ok: false });
  });
});

describe('planMove', () => {
  it('swaps with the neighbour and writes only the two rows that change', () => {
    const p = planMove(rows([1, 2, 3, 4]), 12, { kind: 'step', delta: -1 });
    expect(p).toMatchObject({ found: true, outOfRange: false, position: 2, total: 4 });
    expect(p.updates).toEqual([{ id: 12, position: 2 }, { id: 11, position: 3 }]);
  });
  it('does nothing at the ends', () => {
    expect(planMove(rows([1, 2, 3]), 10, { kind: 'step', delta: -1 })).toMatchObject({ position: 1, updates: [] });
    expect(planMove(rows([1, 2, 3]), 12, { kind: 'step', delta: 1 })).toMatchObject({ position: 3, updates: [] });
  });
  it('moves to a position and shifts the others', () => {
    const p = planMove(rows([1, 2, 3, 4]), 13, { kind: 'to', position: 1 });
    expect(p.position).toBe(1);
    expect(p.updates).toEqual([{ id: 13, position: 1 }, { id: 10, position: 2 }, { id: 11, position: 3 }, { id: 12, position: 4 }]);
  });
  it('numbers hospitals without a place 1..N on the first move', () => {
    const p = planMove(rows([2, 1, null, null]), 12, { kind: 'step', delta: -1 });
    // current sequence: 11(1), 10(2), 12(-), 13(-) -> 12 moves above 10
    const seq = rows([2, 1, null, null]);
    expect(seq.length).toBe(4);
    expect(p.updates.map((u) => u.position)).toEqual(expect.arrayContaining([2, 3, 4]));
    expect(new Set(p.updates.map((u) => u.id)).size).toBe(p.updates.length);
  });
  it('treats a stored value that is not 1..N as needing a rewrite', () => {
    const p = planMove(rows([5, 9, 12]), 10, { kind: 'step', delta: 1 });
    expect(p.updates).toEqual([{ id: 11, position: 1 }, { id: 10, position: 2 }, { id: 12, position: 3 }]);
  });
  it('reports a missing hospital and a position past the end', () => {
    expect(planMove(rows([1, 2]), 99, { kind: 'step', delta: 1 })).toMatchObject({ found: false, updates: [] });
    expect(planMove(rows([1, 2]), 10, { kind: 'to', position: 3 })).toMatchObject({ found: true, outOfRange: true, updates: [] });
  });
});

describe('queries', () => {
  it('locks the ordered set of active approved hospitals', () => {
    const q = lockOrderQuery();
    expect(q.sql).toContain("status = 'approved' AND is_active = 1");
    expect(q.sql).toContain('FOR UPDATE');
    expect(q.values).toEqual([]);
    expect(setOrderQuery(7, 3)).toEqual({ sql: 'UPDATE gangnamunni_hospitals SET display_order = ? WHERE id = ?', values: [3, 7] });
  });
});

describe('order board', () => {
  it('lists every placed hospital in the app order and numbers them 1..N', () => {
    const q = orderBoardQuery();
    expect(q.sql).toContain(ORDER_SET_SQL);
    expect(q.sql).toContain(ORDER_BY_SQL);
    expect(q.values).toEqual([]);
    const board = toOrderBoard([{ id: 7, name: 'A', address: 'x' }, { id: 3, name: 'B', address: null }], 4);
    expect(board).toEqual({ total: 2, inactive: 4, items: [{ id: 7, name: 'A', address: 'x', adGrade: 'none', position: 1 }, { id: 3, name: 'B', address: '', adGrade: 'none', position: 2 }] });
  });
  it('counts only approved, switched-off hospitals as outside the order', () => {
    expect(orderInactiveQuery().sql).toContain("status = 'approved' AND is_active = 0");
  });
});
