import { listJson, parseExpectedUpdatedAt, PATCH_FIELDS, phoneFormat, phoneNormalize, phoneTaken, planPatch, WRITABLE_COLUMNS } from './hospitals.write.logic';

const ok = (fields: unknown) => {
  const r = planPatch(fields);
  if (!r.ok) throw new Error(`unexpected errors: ${JSON.stringify(r.errors)}`);
  return r.plan;
};
const errors = (fields: unknown) => {
  const r = planPatch(fields);
  if (r.ok) throw new Error('expected errors');
  return r.errors;
};
const value = (fields: unknown, column: string) => ok(fields).sets.find((s) => s.column === column)?.value;

describe('phone formatting (same results as the PHP admin helper)', () => {
  it.each([
    ['0212345678', '02-1234-5678'],
    ['021234567', '02-123-4567'],
    ['02-1234-5678', '02-1234-5678'],
    ['+82 2 1234 5678', '02-1234-5678'],
    ['82-31-123-4567', '031-123-4567'],
    ['0082 10 1234 5678', '010-1234-5678'],
    ['0507-1234-5678', '0507-1234-5678'],
    ['05012345678', '050-1234-5678'],
    ['15881234', '1588-1234'],
    ['16611234', '1661-1234'],
    ['1234567890123', '1-2345-6789-0123'],
    ['12345', '1-2345'],
    ['  031 123 4567 ', '031-123-4567'],
  ])('%s -> %s', (raw, expected) => expect(phoneFormat(raw)).toBe(expected));

  it('text without digits is returned trimmed, not rejected', () => expect(phoneFormat('  문의 요망 ')).toBe('문의 요망'));

  it('normalises to digits with the country code folded into 0', () => {
    expect(phoneNormalize('+82 10-1234-5678')).toBe('01012345678');
    expect(phoneNormalize('0082-2-123-4567')).toBe('021234567');
    expect(phoneNormalize('')).toBe('');
  });

  it('finds a duplicate regardless of formatting; empty input never matches', () => {
    expect(phoneTaken('02-1234-5678', [{ phone: '+82 2 1234 5678' }])).toBe(true);
    expect(phoneTaken('02-1234-5678', [{ phone: '02-1234-5679' }, { phone: '' }])).toBe(false);
    expect(phoneTaken('', [{ phone: '' }])).toBe(false);
  });
});

describe('planPatch', () => {
  it('stores lists like the PHP admin: JSON array text, unicode unescaped, trimmed, no empties, no repeats', () => {
    expect(value({ categories: [' 피부 ', '', '성형', '피부'] }, 'categories')).toBe('["피부","성형"]');
    expect(value({ badges: [] }, 'badges')).toBe('[]');
    expect(listJson(['a/b'])).toBe('["a/b"]');
    expect(value({ treatmentTags: ['보톡스'], subwayNames: ['강남역'] }, 'subway_names')).toBe('["강남역"]');
  });

  it('stores business hours as a JSON object text, empty as {}', () => {
    expect(value({ businessHours: { mon: { open: '09:00', close: '18:00' }, note: '점심 12시' } }, 'business_hours')).toBe(
      '{"mon":{"open":"09:00","close":"18:00"},"note":"점심 12시"}',
    );
    expect(value({ businessHours: {} }, 'business_hours')).toBe('{}');
  });

  it('formats the phone and reports it for the duplicate check; empty phone is stored empty and not checked', () => {
    const p = ok({ phone: '+82 2 1234 5678' });
    expect(p.sets).toEqual([{ column: 'phone', value: '02-1234-5678' }]);
    expect(p.phone).toBe('02-1234-5678');
    const e = ok({ phone: '  ' });
    expect(e.sets).toEqual([{ column: 'phone', value: '' }]);
    expect(e.phone).toBeNull();
  });

  it('stores show_rating as 0/1 and coordinates as numbers or both NULL', () => {
    expect(value({ showRating: true }, 'show_rating')).toBe(1);
    expect(value({ showRating: false }, 'show_rating')).toBe(0);
    expect(ok({ latitude: 37.5, longitude: 127.03 }).sets).toEqual([{ column: 'latitude', value: 37.5 }, { column: 'longitude', value: 127.03 }]);
    expect(ok({ latitude: null, longitude: null }).sets).toEqual([{ column: 'latitude', value: null }, { column: 'longitude', value: null }]);
  });

  it('trims text and reports only the written field names', () => {
    const p = ok({ name: '  서울의원 ', address: ' 강남 ' });
    expect(p.sets).toEqual([{ column: 'name', value: '서울의원' }, { column: 'address', value: '강남' }]);
    expect(p.names).toEqual(['name', 'address']);
  });

  it('rejects fields that are not editable here', () => {
    expect(errors({ adGrade: 'A', status: 'approved', isActive: true, rating: 5, kakaoChannelUrl: 'https://x.y', id: 3, updatedAt: 'x', cert_business: 'x' })).toEqual({
      adGrade: 'not_allowed', status: 'not_allowed', isActive: 'not_allowed', rating: 'not_allowed', kakaoChannelUrl: 'not_allowed',
      id: 'not_allowed', updatedAt: 'not_allowed', cert_business: 'not_allowed',
    });
    expect(errors({ name: 'ok', __proto__x: 1, constructor: 'x', toString: 'x' })).toEqual({ __proto__x: 'not_allowed', constructor: 'not_allowed', toString: 'not_allowed' });
  });

  it('requires a non-empty fields object', () => {
    expect(errors({})).toEqual({ fields: 'required' });
    expect(errors(undefined)).toEqual({ fields: 'required' });
    expect(errors([])).toEqual({ fields: 'required' });
  });

  it('validates each field', () => {
    expect(errors({ name: '   ' })).toEqual({ name: 'required' });
    expect(errors({ name: null })).toEqual({ name: 'required' });
    expect(errors({ name: 'x'.repeat(256) })).toEqual({ name: 'too_long' });
    expect(errors({ businessNumber: '1'.repeat(21) })).toEqual({ businessNumber: 'too_long' });
    expect(errors({ contactPhone: '1'.repeat(21) })).toEqual({ contactPhone: 'too_long' });
    expect(errors({ email: `${'a'.repeat(190)}@x.co` })).toEqual({ email: 'too_long' });
    expect(errors({ name: 5 })).toEqual({ name: 'bad_format' });
    expect(errors({ introduction: 'x'.repeat(10001) })).toEqual({ introduction: 'too_long' });
    expect(errors({ email: 'nope' })).toEqual({ email: 'bad_format' });
    expect(ok({ email: '' }).sets).toEqual([{ column: 'email', value: '' }]);
    expect(errors({ website: 'ftp://x.y', snsUrl: 'javascript:alert(1)' })).toEqual({ website: 'bad_url', snsUrl: 'bad_url' });
    expect(ok({ website: 'HTTPS://example.com', snsUrl: '' }).sets).toHaveLength(2);
    expect(errors({ categories: 'a,b' })).toEqual({ categories: 'bad_format' });
    expect(errors({ categories: [1] })).toEqual({ categories: 'bad_format' });
    expect(errors({ badges: ['x'.repeat(101)] })).toEqual({ badges: 'too_long' });
    expect(errors({ badges: Array.from({ length: 51 }, (_, i) => `b${i}`) })).toEqual({ badges: 'too_long' });
    expect(errors({ businessHours: [] })).toEqual({ businessHours: 'bad_format' });
    expect(errors({ businessHours: 'x' })).toEqual({ businessHours: 'bad_format' });
    expect(errors({ businessHours: { a: 'x'.repeat(5000) } })).toEqual({ businessHours: 'too_long' });
    expect(errors({ showRating: 1 })).toEqual({ showRating: 'bad_format' });
  });

  it('validates coordinates: range, numbers only, both or neither', () => {
    expect(errors({ latitude: 91, longitude: 0 })).toEqual({ latitude: 'bad_format' });
    expect(errors({ latitude: 0, longitude: -181 })).toEqual({ longitude: 'bad_format' });
    expect(errors({ latitude: '37.5', longitude: 127 })).toEqual({ latitude: 'bad_format' });
    expect(errors({ latitude: 37.5 })).toEqual({ longitude: 'required' });
    expect(errors({ longitude: 127 })).toEqual({ latitude: 'required' });
    expect(errors({ latitude: null, longitude: 127 })).toEqual({ latitude: 'required' });
    expect(errors({ latitude: 37, longitude: null })).toEqual({ longitude: 'required' });
  });

  it('reports every bad field at once', () => {
    expect(errors({ name: '', email: 'x', adGrade: 'S' })).toEqual({ name: 'required', email: 'bad_format', adGrade: 'not_allowed' });
  });

  it('only maps to columns the write user may update (never ad_grade / status / rating / cert_*)', () => {
    const columns = Object.values(PATCH_FIELDS).map((s) => s.column);
    for (const c of columns) expect(WRITABLE_COLUMNS).toContain(c);
    for (const banned of ['ad_grade', 'status', 'rating', 'review_count', 'cert_business', 'cert_medical', 'id', 'applied_by', 'kakao_channel_url', 'created_at']) {
      expect(WRITABLE_COLUMNS).not.toContain(banned);
    }
  });
});

describe('parseExpectedUpdatedAt', () => {
  it('accepts a stored timestamp or null only', () => {
    expect(parseExpectedUpdatedAt('2026-11-01 11:00:00')).toBe('2026-11-01 11:00:00');
    expect(parseExpectedUpdatedAt(null)).toBeNull();
    for (const v of [undefined, '', '2026-11-01', '2026-11-01T11:00:00Z', 5, {}]) expect(parseExpectedUpdatedAt(v)).toBeUndefined();
  });
});
