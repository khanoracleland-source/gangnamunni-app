import { ALERT_TYPES, countsQuery, dayOf, filterOf, listQuery, markReadQuery, markTabQuery, purgeDaysOf, purgeQuery, safeLink, TAB_TYPES } from './alerts.logic';

describe('alerts logic', () => {
  it('every type is in exactly one tab', () => {
    const all = [...TAB_TYPES.activity, ...TAB_TYPES.pending, ...TAB_TYPES.system];
    expect([...all].sort()).toEqual([...ALERT_TYPES].sort());
    expect(new Set(all).size).toBe(all.length);
  });
  it('follows relative links only', () => {
    for (const ok of ['admin_hospitals.php', 'admin_hospitals.php?id=3&x=1', '/admin/x.php?id=3', 'a/b#c', 'a.php?next=../x']) expect(safeLink(ok)).toBe(ok);
    for (const bad of ['', '#', '  ', 'http://evil.test', 'javascript:alert(1)', 'JaVa:x', '//evil.test', 'a\\b', 'a\nb', 'mailto:a@b.c', '/.//evil.com', '/%2e//evil.com', '/a/..//evil.com', '..//evil.com', './..//evil.com', 'a/%2F/b', null, 5]) expect(safeLink(bad)).toBe('');
  });
  it('reads filters strictly', () => {
    expect(filterOf({ tab: 'x', type: "a' OR 1=1", from: '2026-02-30', to: '2026-11-27' })).toEqual({ tab: 'all', type: '', from: '', to: '2026-11-27' });
    expect(dayOf('2026-11-27')).toBe('2026-11-27');
    expect(dayOf('2026-13-01')).toBe('');
  });
  it('purge days: default 30, 1..3650, nothing else', () => {
    expect(purgeDaysOf(undefined)).toBe(30);
    expect(purgeDaysOf(7)).toBe(7);
    expect(purgeDaysOf('90')).toBe(90);
    for (const bad of [0, -1, 3651, 1.5, 'x', '1; DROP', NaN, {}]) expect(purgeDaysOf(bad)).toBeNull();
  });
  it('every query is bound to the admin and parametrised', () => {
    const q = listQuery(7, { tab: 'pending', type: 'ad_pending', from: '2026-11-01', to: '2026-11-27' }, 2);
    expect(q.sql).toContain('admin_id = ?');
    expect(q.sql).not.toMatch(/ad_pending|2026/);
    expect(q.values[0]).toBe(7);
    expect(q.values.slice(-2)).toEqual([20, 20]);
    for (const x of [markReadQuery(7, 3), markTabQuery(7, 'all'), markTabQuery(7, 'system'), purgeQuery(7, 30), countsQuery(7)]) {
      expect(x.sql).toMatch(/admin_id = \?/);
      expect(x.values).toContain(7);
    }
    expect(purgeQuery(7, 30).sql).toContain('is_read = 1');
    expect(markTabQuery(7, 'activity').values).toEqual([7, ...TAB_TYPES.activity]);
  });
});
