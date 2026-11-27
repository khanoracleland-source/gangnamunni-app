import { ALERT_TYPES, countsQuery, dayOf, filterOf, listQuery, safeLink, TAB_TYPES } from './alerts.logic';

describe('alerts logic', () => {
  it('every type is in exactly one tab', () => {
    const all = [...TAB_TYPES.activity, ...TAB_TYPES.pending, ...TAB_TYPES.system];
    expect([...all].sort()).toEqual([...ALERT_TYPES].sort());
    expect(new Set(all).size).toBe(all.length);
  });
  it('follows relative links only', () => {
    for (const ok of ['admin_hospitals.php', '/admin/x.php?id=3', 'a/b#c']) expect(safeLink(ok)).toBe(ok);
    for (const bad of ['', '#', '  ', 'http://evil.test', 'javascript:alert(1)', 'JaVa:x', '//evil.test', 'a\\b', 'a\nb', 'mailto:a@b.c', null, 5]) expect(safeLink(bad)).toBe('');
  });
  it('reads filters strictly', () => {
    expect(filterOf({ tab: 'x', type: "a' OR 1=1", from: '2026-02-30', to: '2026-11-27' })).toEqual({ tab: 'all', type: '', from: '', to: '2026-11-27' });
    expect(dayOf('2026-11-27')).toBe('2026-11-27');
    expect(dayOf('2026-13-01')).toBe('');
  });

  it('every query is bound to the admin and parametrised', () => {
    const q = listQuery(7, { tab: 'pending', type: 'ad_pending', from: '2026-11-01', to: '2026-11-27' }, 2);
    expect(q.sql).toContain('admin_id = ?');
    expect(q.sql).not.toMatch(/ad_pending|2026/);
    expect(q.values[0]).toBe(7);
    expect(q.values.slice(-2)).toEqual([20, 20]);
    for (const x of [countsQuery(7)]) {
      expect(x.sql).toMatch(/admin_id = \?/);
      expect(x.values).toContain(7);
    }
  });
});
