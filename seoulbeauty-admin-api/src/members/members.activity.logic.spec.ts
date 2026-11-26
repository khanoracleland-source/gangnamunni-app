import { buildHistory, buildPointTotals, buildPoints, consultationListQuery, pagingOf, phoneForms } from './members.activity.logic';

describe('member activity logic', () => {
  it('paging defaults and clamps', () => {
    expect(pagingOf({})).toEqual({ page: 1, perPage: 20 });
    expect(pagingOf({ page: '3' }).page).toBe(3);
    expect(pagingOf({ page: '-1' }).page).toBe(1);
  });
  it('phone forms', () => {
    expect(phoneForms('010-1234-5678')).toEqual(['01012345678', '821012345678']);
    expect(phoneForms('+82 10 1234 5678')).toEqual(['01012345678', '821012345678']);
    expect(phoneForms('02-123')).toEqual([]);
  });
  it('consultation query is parameterised and never matches everything', () => {
    const q = consultationListQuery('kim01', '', { page: 2, perPage: 20 });
    expect(q.sql).toContain('mb_id = ?');
    expect(q.values).toEqual(['kim01', 20, 20]);
    expect(consultationListQuery('x', '', { page: 1, perPage: 20 }, false).sql).toContain('1 = 0');
  });
  it('point totals and direction', () => {
    expect(buildPointTotals([{ type: 'earn', total: '500' }, { type: 'earn_cancel', total: '100' }, { type: 'use', total: '200' }, { type: 'admin_add', total: '50' }]))
      .toMatchObject({ earned: 400, used: 200, adjustedIn: 50 });
    const a = buildPoints({ page: 1, perPage: 20 }, 1, [{ balance: 300 }], [], [{ id: 1, type: 'use', amount: 200, balance: 300, reason: 'booking' }]);
    expect(a.balance).toBe(300);
    expect(a.items[0].direction).toBe('out');
  });
  it('history never returns password values', () => {
    const h = buildHistory({ page: 1, perPage: 20 }, 2, [
      { id: 1, action: 'password_reset', before_value: 'secret', after_value: 'secret2' },
      { id: 2, action: 'email_change', before_value: 'a@b.kr', after_value: 'c@d.kr' }]);
    expect(h.items[0]).toMatchObject({ before: null, after: null });
    expect(h.items[1]).toMatchObject({ before: 'a@b.kr', after: 'c@d.kr' });
  });
});
