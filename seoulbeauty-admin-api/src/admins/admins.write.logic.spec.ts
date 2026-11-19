import { NOTIFICATION_TYPES } from './admins.logic';
import { parseActive, permissionsJson, planActive, planEdit } from './admins.write.logic';

describe('admin accounts: edit plan', () => {
  it('accepts name, phone and notifications; trims; keeps known types in the fixed order', () => {
    const r = planEdit({ name: '  김관리 ', phone: ' 010-1234-5678 ', notifications: [NOTIFICATION_TYPES[2], NOTIFICATION_TYPES[0], NOTIFICATION_TYPES[2]] });
    expect(r).toEqual({ ok: true, plan: { name: '김관리', phone: '010-1234-5678', notifications: [NOTIFICATION_TYPES[0], NOTIFICATION_TYPES[2]] } });
  });
  it('an empty phone and an empty notification list are valid choices', () => {
    expect(planEdit({ phone: '' })).toEqual({ ok: true, plan: { phone: '' } });
    expect(planEdit({ notifications: [] })).toEqual({ ok: true, plan: { notifications: [] } });
  });
  it.each(['role', 'email', 'password', 'hospitalId', 'isActive', 'permissions', 'id'])('%s is refused, not ignored', (key) => {
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
