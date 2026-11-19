import { NOTIFICATION_TYPES } from './admins.logic';
import { AttemptLimiter, cleanEmail, passwordError, parseActive, parseConfirm, permissionsJson, planActive, planCreate, planDelete, planEdit } from './admins.write.logic';

describe('admin accounts: edit plan', () => {
  it('accepts name, phone and notifications; trims; keeps known types in the fixed order', () => {
    const r = planEdit({ name: '  김관리 ', phone: ' 010-1234-5678 ', notifications: [NOTIFICATION_TYPES[2], NOTIFICATION_TYPES[0], NOTIFICATION_TYPES[2]] });
    expect(r).toEqual({ ok: true, plan: { name: '김관리', phone: '010-1234-5678', notifications: [NOTIFICATION_TYPES[0], NOTIFICATION_TYPES[2]] } });
  });
  it('an empty phone and an empty notification list are valid choices', () => {
    expect(planEdit({ phone: '' })).toEqual({ ok: true, plan: { phone: '' } });
    expect(planEdit({ notifications: [] })).toEqual({ ok: true, plan: { notifications: [] } });
  });
  it.each(['role', 'password', 'hospitalId', 'isActive', 'permissions', 'id'])('%s is refused, not ignored', (key) => {
    expect(planEdit({ name: 'a', [key]: 'x' })).toEqual({ ok: false, errors: { [key]: 'not_allowed' } });
  });
  it('refuses a body that is not an object, an empty body, and bad values', () => {
    for (const b of [null, undefined, 'x', 5, [], [{ name: 'a' }]]) expect(planEdit(b)).toEqual({ ok: false, errors: { body: 'bad_format' } });
    expect(planEdit({})).toEqual({ ok: false, errors: { body: 'empty' } });
    expect(planEdit({ name: '   ' })).toEqual({ ok: false, errors: { name: 'required' } });
    expect(planEdit({ name: 5 })).toEqual({ ok: false, errors: { name: 'bad_format' } });
    expect(planEdit({ name: 'a'.repeat(101) })).toEqual({ ok: false, errors: { name: 'too_long' } });
    expect(planEdit({ name: 'a\nb' })).toEqual({ ok: false, errors: { name: 'bad_format' } });
    expect(planEdit({ phone: '1'.repeat(31) })).toEqual({ ok: false, errors: { phone: 'too_long' } });
    expect(planEdit({ phone: null })).toEqual({ ok: false, errors: { phone: 'bad_format' } });
    expect(planEdit({ notifications: 'review_new' })).toEqual({ ok: false, errors: { notifications: 'bad_format' } });
    expect(planEdit({ notifications: [1] })).toEqual({ ok: false, errors: { notifications: 'bad_format' } });
    expect(planEdit({ notifications: ['nope'] })).toEqual({ ok: false, errors: { notifications: 'unknown_type' } });
  });
  it('counts a Korean name by characters, not bytes', () => {
    expect(planEdit({ name: '가'.repeat(100) }).ok).toBe(true);
    expect(planEdit({ name: '가'.repeat(101) }).ok).toBe(false);
  });
  it('stores only the notification list, like the old panel', () => {
    expect(permissionsJson(['a', 'b'])).toBe('{"notifications":["a","b"]}');
  });
});

describe('admin accounts: active state plan', () => {
  const t = (over: Partial<{ id: number; role: string; isActive: boolean }> = {}) => ({ id: 5, role: 'hospital_admin', isActive: true, ...over });
  it('parses only an exact boolean', () => {
    expect(parseActive({ active: true })).toBe(true);
    expect(parseActive({ active: false })).toBe(false);
    for (const b of [{ active: 1 }, { active: 'true' }, {}, null, undefined, 'x']) expect(parseActive(b)).toBeNull();
  });
  it('unknown account; own account', () => {
    expect(planActive(1, undefined, [1], false)).toEqual({ ok: false, error: 'not_found' });
    expect(planActive(5, t(), [1], false)).toEqual({ ok: false, error: 'self_forbidden' });
  });
  it('the last active super admin cannot be switched off, but may be switched on / left alone', () => {
    const sup = t({ id: 2, role: 'super_admin' });
    expect(planActive(1, sup, [2], false)).toEqual({ ok: false, error: 'last_super_admin' });
    expect(planActive(1, sup, [1, 2], false)).toEqual({ ok: true, noChange: false });
    expect(planActive(1, { ...sup, isActive: false }, [1], false)).toEqual({ ok: true, noChange: true });
    expect(planActive(1, { ...sup, isActive: false }, [1], true)).toEqual({ ok: true, noChange: false });
  });
  it('an ordinary account may always be switched either way; the same state is a no-change', () => {
    expect(planActive(1, t(), [1], false)).toEqual({ ok: true, noChange: false });
    expect(planActive(1, t({ isActive: false }), [1], true)).toEqual({ ok: true, noChange: false });
    expect(planActive(1, t(), [1], true)).toEqual({ ok: true, noChange: true });
  });
});

describe('admin accounts: role and hospital in an edit', () => {
  it('only a super admin may send them; values are checked', () => {
    expect(planEdit({ role: 'hospital_admin', hospitalId: 3 }, true)).toEqual({ ok: true, plan: { role: 'hospital_admin', hospitalId: 3 } });
    expect(planEdit({ hospitalId: null }, true)).toEqual({ ok: true, plan: { hospitalId: null } });
    expect(planEdit({ role: 'hospital_admin' })).toEqual({ ok: false, errors: { role: 'not_allowed' } });
    expect(planEdit({ hospitalId: 3 })).toEqual({ ok: false, errors: { hospitalId: 'not_allowed' } });
    expect(planEdit({ role: 'admin' }, true)).toEqual({ ok: false, errors: { role: 'unknown_role' } });
    expect(planEdit({ role: 5 }, true)).toEqual({ ok: false, errors: { role: 'bad_format' } });
    for (const v of ['3', 0, -1, 1.5, true]) expect(planEdit({ hospitalId: v }, true)).toEqual({ ok: false, errors: { hospitalId: 'bad_format' } });
  });
});

describe('admin accounts: add plan', () => {
  const ok = { username: 'new_user', password: 'longenough1', name: '이름' };
  it('defaults: hospital admin needs a hospital; email and phone optional; notifications default', () => {
    expect(planCreate(ok)).toEqual({ ok: false, errors: { hospitalId: 'required' } });
    const r = planCreate({ ...ok, hospitalId: 4 });
    expect(r).toMatchObject({ ok: true, plan: { role: 'hospital_admin', hospitalId: 4, email: '', phone: '' } });
  });
  it('a super admin ignores a hospital', () => {
    expect(planCreate({ ...ok, role: 'super_admin', hospitalId: 4 })).toMatchObject({ ok: true, plan: { role: 'super_admin', hospitalId: null } });
  });
  it('trims, lower-cases the email, counts password characters (8) and bytes (72)', () => {
    expect(planCreate({ ...ok, role: 'super_admin', email: ' A@B.CO ', name: ' 이름 ' })).toMatchObject({ ok: true, plan: { email: 'a@b.co', name: '이름' } });
    expect(planCreate({ ...ok, role: 'super_admin', password: '가나다라마바사아' }).ok).toBe(true);
    expect(planCreate({ ...ok, role: 'super_admin', password: '가나다라마바사' })).toEqual({ ok: false, errors: { password: 'too_short' } });
    expect(planCreate({ ...ok, role: 'super_admin', password: '가'.repeat(25) })).toEqual({ ok: false, errors: { password: 'too_long' } });
  });
  it('a password with a NUL byte is refused (bcrypt implementations disagree about it)', () => {
    expect(planCreate({ ...ok, role: 'super_admin', password: 'longenough\u00001' })).toEqual({ ok: false, errors: { password: 'bad_format' } });
  });
  it('refuses a body that is not an object and unknown keys', () => {
    expect(planCreate(null)).toEqual({ ok: false, errors: { body: 'bad_format' } });
    expect(planCreate({ ...ok, role: 'super_admin', parentAdminId: 1 })).toEqual({ ok: false, errors: { parentAdminId: 'not_allowed' } });
  });
});

describe('admin accounts: delete plan', () => {
  const t = (over = {}) => ({ id: 6, isActive: false, deactivatedAt: '2026-11-01 10:00:00', ready: true, inactiveDays: 9, ...over });
  it('needs a long enough inactive time, never your own, and reads the confirmation text', () => {
    expect(planDelete(1, undefined, 5)).toEqual({ ok: false, error: 'not_found' });
    expect(planDelete(6, t(), 5)).toEqual({ ok: false, error: 'self_forbidden' });
    expect(planDelete(1, t({ isActive: true }), 5)).toEqual({ ok: false, error: 'still_active' });
    expect(planDelete(1, t({ deactivatedAt: null }), 5)).toEqual({ ok: false, error: 'no_inactive_record' });
    expect(planDelete(1, t({ ready: false, inactiveDays: 2 }), 5)).toEqual({ ok: false, error: 'not_inactive_long_enough', daysLeft: 3 });
    expect(planDelete(1, t(), 5)).toEqual({ ok: true });
    expect(parseConfirm({ confirm: 'x' })).toBe('x');
    for (const b of [{}, { confirm: 1 }, null, 'x']) expect(parseConfirm(b)).toBeNull();
  });
});

describe('admin accounts: email and password in an edit', () => {
  it('lowercases and trims the email; an empty email is allowed; a bad one is refused', () => {
    expect(planEdit({ email: '  Kim@Example.COM ' })).toEqual({ ok: true, plan: { email: 'kim@example.com' } });
    expect(planEdit({ email: '' })).toEqual({ ok: true, plan: { email: '' } });
    expect(planEdit({ email: 'nope' })).toEqual({ ok: false, errors: { email: 'bad_format' } });
    expect(cleanEmail(5)).toBeNull();
  });
  it('the new password needs 8 to 72 bytes and no NUL; the current password alone is not a change', () => {
    expect(passwordError('short')).toBe('too_short');
    expect(passwordError('a'.repeat(73))).toBe('too_long');
    expect(passwordError('abcdefg\0h')).toBe('bad_format');
    expect(passwordError(7)).toBe('bad_format');
    expect(passwordError('long-enough-1')).toBeNull();
    expect(planEdit({ newPassword: 'long-enough-1', currentPassword: 'old' })).toEqual({ ok: true, plan: { newPassword: 'long-enough-1', currentPassword: 'old' } });
    expect(planEdit({ currentPassword: 'old' })).toEqual({ ok: false, errors: { body: 'empty' } });
    expect(planEdit({ newPassword: 'x' })).toEqual({ ok: false, errors: { newPassword: 'too_short' } });
  });
});

describe('admin accounts: wrong-password limiter', () => {
  it('locks after 5 misses for the window, per account, and clears on success', () => {
    const l = new AttemptLimiter(5, 60_000);
    for (let i = 0; i < 4; i++) l.miss(1, 1000);
    expect(l.waitSeconds(1, 1000)).toBe(0);
    l.miss(1, 1000);
    expect(l.waitSeconds(1, 1000)).toBeGreaterThan(0);
    expect(l.waitSeconds(2, 1000)).toBe(0);
    expect(l.waitSeconds(1, 1000 + 61_000)).toBe(0);
    l.miss(3, 0); l.clear(3);
    for (let i = 0; i < 4; i++) l.miss(3, 0);
    expect(l.waitSeconds(3, 0)).toBe(0);
  });
});
