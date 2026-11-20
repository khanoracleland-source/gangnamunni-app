import { ASSIGNABLE_ROLES, buildOptions, DEFAULT_NOTIFICATIONS, DELETE_AFTER_DAYS, detailQuery, inactivityQuery, listQuery, NOTIFICATION_TYPES, parseAdminId, readNotifications, ssoQuery, toItem } from './admins.logic';

const SUPER = { id: 1, superAdmin: true };
const OTHER = { id: 5, superAdmin: false };
const row = (extra: Record<string, unknown> = {}) => ({
  id: 5, username: 'hosp5', name: '병원관리자', email: 'a@example.com', phone: '010-1', role: 'hospital_admin', hospital_id: 12, hospital_name: '서울의원',
  is_active: 1, last_login_at: '2026-11-18 09:00:00', permissions: '{"notifications":["review_new","bogus","test_fail"]}', ...extra,
});

describe('admins logic', () => {
  it('never selects the password or the session counter; every value is bound', () => {
    for (const q of [listQuery(SUPER), listQuery(OTHER), detailQuery(9), inactivityQuery([1, 2]), ssoQuery([1])]) {
      expect(listQuery(SUPER).sql).not.toContain('g5_mb_id');
      expect(q.sql).not.toMatch(/password|cred_epoch/i);
      expect(q.sql).not.toMatch(/\d{2,}\s*(--|;)/);
    }
    expect(listQuery(OTHER)).toEqual({ sql: expect.stringContaining('WHERE id = ?'), values: [5] });
    expect(listQuery(SUPER).sql).not.toContain('WHERE');
    expect(detailQuery(9).values).toEqual([9]);
    expect(inactivityQuery([3, 4]).values).toEqual([DELETE_AFTER_DAYS, [3, 4]]);
  });

  it('parseAdminId accepts plain positive integers only', () => {
    expect(parseAdminId('12')).toBe(12);
    for (const bad of ['0', '-1', '1.5', '01', 'abc', '', '12abc', '99999999999', undefined, 12]) expect(parseAdminId(bad)).toBe(0);
  });

  it('notifications: own list limited to known types, in the fixed order; no list = defaults', () => {
    expect(readNotifications('{"notifications":["test_fail","review_new","bogus"]}')).toEqual({ list: ['review_new', 'test_fail'], custom: true });
    expect(readNotifications('{"notifications":[]}')).toEqual({ list: [], custom: true });
    for (const raw of [null, '', '{}', 'not json', '{"notifications":"x"}', '[]']) expect(readNotifications(raw)).toEqual({ list: [...DEFAULT_NOTIFICATIONS], custom: false });
    for (const d of DEFAULT_NOTIFICATIONS) expect(NOTIFICATION_TYPES).toContain(d);
  });

  it('item: no secrets, SSO link only for a super admin, self flag', () => {
    const it1 = toItem(row(), SUPER, undefined, { id: 5, g5_mb_id: 'member1' });
    expect(it1).toMatchObject({ id: 5, username: 'hosp5', role: 'hospital_admin', hospitalId: 12, isActive: true, isSelf: false, ssoMemberId: 'member1', notifications: ['review_new', 'test_fail'], deleteReady: false, deactivatedAt: null });
    expect(Object.keys(it1).join()).not.toMatch(/password|epoch/i);
    const own = toItem(row(), OTHER, undefined, { id: 5, g5_mb_id: 'member1' });
    expect(own.isSelf).toBe(true);
    expect(own.ssoMemberId).toBeNull();
    expect(toItem(row(), SUPER).ssoMemberId).toBeNull(); // no link column / no answer
  });

  it('item: delete is ready only when inactive AND the waiting days are over', () => {
    const off = row({ is_active: 0 });
    expect(toItem(off, SUPER).deleteReady).toBe(false); // no record of when it was switched off
    expect(toItem(off, SUPER, { id: 5, deactivated_at: null, inactive_days: null, ready: 0 })).toMatchObject({ deleteReady: false, deactivatedAt: null, inactiveDays: null });
    expect(toItem(off, SUPER, { id: 5, deactivated_at: '2026-11-17 10:00:00', inactive_days: 2, ready: 0 })).toMatchObject({ deleteReady: false, inactiveDays: 2, deactivatedAt: '2026-11-17 10:00:00' });
    expect(toItem(off, SUPER, { id: 5, deactivated_at: '2026-11-10 10:00:00', inactive_days: 9, ready: 1 })).toMatchObject({ deleteReady: true, inactiveDays: 9 });
    // an active account is never ready, whatever a stale mark says
    expect(toItem(row(), SUPER, { id: 5, deactivated_at: '2026-11-10 10:00:00', inactive_days: 9, ready: 1 })).toMatchObject({ deleteReady: false, deactivatedAt: null });
  });

  it('options: roles and hospitals only for a super admin', () => {
    expect(buildOptions(SUPER, [{ id: 2, name: 'B' }])).toMatchObject({ roles: [...ASSIGNABLE_ROLES], hospitals: [{ id: 2, name: 'B' }], minPasswordLength: 8, deleteAfterDays: 1 });
    expect(buildOptions(OTHER, [{ id: 2, name: 'B' }])).toMatchObject({ roles: [], hospitals: [] });
  });
});
