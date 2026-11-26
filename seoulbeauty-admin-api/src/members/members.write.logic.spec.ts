import { cleanEmail, EDITABLE_FIELDS, hashPassword, matchesHash, PASSWORD_RE, planEdit, planPassword, RESET_LIMIT_PER_HOUR } from './members.write.logic';

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

  it('checks the new password with the rule of the app and takes nothing else in the body', () => {
    expect(RESET_LIMIT_PER_HOUR).toBe(3);
    expect(planPassword({ password: 'Abcdef1!x' })).toEqual({ ok: true, password: 'Abcdef1!x' });
    for (const bad of ['Abcdef1!', 'abcdefgh1', 'Abcdefghi!', '12345678!', 'Abcdef 1!x', 'Abcdéf1!xy', `Abcdef1!${'x'.repeat(13)}`, '', 123, null, ['Abcdef1!x']]) {
      if (typeof bad === 'string') expect(PASSWORD_RE.test(bad)).toBe(false);
      expect(planPassword({ password: bad })).toEqual({ ok: false, errors: { password: 'bad_format' } });
    }
    expect(planPassword({ password: 'Abcdef1!x', email: 'a@b.kr' })).toEqual({ ok: false, errors: { email: 'not_allowed' } });
    expect(planPassword({})).toEqual({ ok: false, errors: { password: 'required' } });
    const proto = planPassword(JSON.parse('{"password":"Abcdef1!x","__proto__":{"x":1}}'));
    expect(Object.entries(proto.ok ? {} : proto.errors)).toEqual([['__proto__', 'not_allowed']]);
    for (const body of [null, undefined, 'Abcdef1!x', [], 5]) expect(planPassword(body)).toEqual({ ok: false, errors: { body: 'bad_format' } });
  });

  it('writes the same hash the app writes (Gnuboard create_hash), checked against the app\'s own function', () => {
    // Produced by the app's createHash('Abcdef1!x', salt) for this fixed salt; if the formats ever drift apart members could not sign in.
    const salt = 'c2FsdC1zYWx0LXNhbHQtc2FsdC0xMjM0';
    expect(hashPassword('Abcdef1!x', salt)).toBe('sha256:12000:c2FsdC1zYWx0LXNhbHQtc2FsdC0xMjM0:tnpX1gkf46RWatIxsJZvZNjqofbOD/aN');
    const h = hashPassword('Abcdef1!x');
    expect(h).toMatch(/^sha256:12000:[A-Za-z0-9+/=]{32}:[A-Za-z0-9+/=]{32}$/);
    expect(matchesHash('Abcdef1!x', h)).toBe(true);
    expect(matchesHash('Abcdef1!y', h)).toBe(false);
    expect(hashPassword('Abcdef1!x')).not.toBe(h);
  });
});
