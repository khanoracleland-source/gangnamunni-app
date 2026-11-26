import { cleanEmail, EDITABLE_FIELDS, planEdit } from './members.write.logic';

describe('members write logic', () => {
  it('may edit the e-mail and nothing else', () => {
    expect([...EDITABLE_FIELDS]).toEqual(['email']);
  });

  it('cleans an e-mail: trimmed, lower-cased, never empty', () => {
    expect(cleanEmail('  Kim.Seo@Example.KR ')).toBe('kim.seo@example.kr');
    for (const bad of ['', '   ', 'no-at', 'a@b', 'a b@c.kr', 'a@@b.kr', 'x@y.kr\nBcc: z@y.kr', 'a\u0000@b.kr', 'a@b\u007f.kr', null, undefined, 5, ['a@b.kr'], {}]) expect(cleanEmail(bad)).toBeNull();
    expect(cleanEmail(`${'a'.repeat(250)}@b.kr`)).toBeNull();
  });

  it('accepts exactly {email} and refuses every other key instead of ignoring it', () => {
    expect(planEdit({ email: 'A@B.kr' })).toEqual({ ok: true, email: 'a@b.kr' });
    for (const key of ['name', 'nick', 'phone', 'hp', 'sex', 'birth', 'addr1', 'addr2', 'point', 'memo', 'level', 'mb_level', 'password', 'mb_password', 'leaveDate', 'blocked', 'id', 'mb_id']) {
      const r = planEdit({ email: 'a@b.kr', [key]: 'x' });
      expect(r).toEqual({ ok: false, errors: { [key]: 'not_allowed' } });
    }
    // A body that carries __proto__ / constructor as own keys is refused like any other extra key.
    const proto = planEdit(JSON.parse('{"email":"a@b.kr","__proto__":{"x":1}}'));
    expect(proto.ok).toBe(false);
    expect(Object.entries(proto.ok ? {} : proto.errors)).toEqual([['__proto__', 'not_allowed']]);
    expect(planEdit({ email: 'a@b.kr', constructor: 'x' })).toEqual({ ok: false, errors: { constructor: 'not_allowed' } });
    expect(planEdit({ name: 'x' })).toEqual({ ok: false, errors: { name: 'not_allowed', email: 'required' } });
    expect(planEdit({ email: 'bad' })).toEqual({ ok: false, errors: { email: 'bad_format' } });
    for (const body of [null, undefined, 'a@b.kr', 5, [], [{ email: 'a@b.kr' }]]) expect(planEdit(body)).toEqual({ ok: false, errors: { body: 'bad_format' } });
  });
});
