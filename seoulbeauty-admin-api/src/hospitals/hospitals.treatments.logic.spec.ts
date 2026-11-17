import {
  availableQuery, buildItems, buildUnits, canManageTreatments, clearPriceSummaryQuery, DEFAULT_PRICE_UNITS, formatDosage, imageCountsQuery, insertPriceQuery, parseAssignBody,
  parsePatchBody, parsePricesBody, priceOptionsQuery, sortUnits, TREATMENT_ASSIGN_LIMIT, unitsQuery,
} from './hospitals.treatments.logic';

describe('hospital treatments: pure rules', () => {
  describe('canManageTreatments', () => {
    it('a super admin always may', () => {
      for (const m of ['', 'both', 'hospital_only', 'admin_only', 'nonsense']) expect(canManageTreatments('super_admin', m)).toBe(true);
    });
    it('a hospital account needs hospital_only or both', () => {
      for (const role of ['hospital_super_admin', 'hospital_admin']) {
        expect(canManageTreatments(role, 'both')).toBe(true);
        expect(canManageTreatments(role, ' hospital_only ')).toBe(true);
        expect(canManageTreatments(role, 'admin_only')).toBe(false);
        expect(canManageTreatments(role, '')).toBe(false);
        expect(canManageTreatments(role, 'BOTH')).toBe(false);
      }
    });
    it('any other admin needs admin_only or both', () => {
      expect(canManageTreatments('manager', 'admin_only')).toBe(true);
      expect(canManageTreatments('manager', 'both')).toBe(true);
      expect(canManageTreatments('manager', 'hospital_only')).toBe(false);
    });
  });

  describe('parseAssignBody', () => {
    it('keeps distinct positive integers in the order sent', () => {
      expect(parseAssignBody({ treatmentIds: [5, 3, 5, 9, 3] })).toEqual({ ok: true, ids: [5, 3, 9] });
    });
    it.each([undefined, null, 'x', [], {}, { treatmentIds: [] }, { treatmentIds: 'abc' }, { treatmentIds: [0] }, { treatmentIds: [-1] }, { treatmentIds: [1.5] }, { treatmentIds: ['1'] },
      { treatmentIds: [1, null] }, { treatmentIds: [2147483648] }, { treatmentIds: [NaN] }])('%p -> no_treatments', (b) => {
      expect(parseAssignBody(b)).toEqual({ ok: false, body: { error: 'no_treatments' } });
    });
    it('the limit is 300 entries', () => {
      expect(parseAssignBody({ treatmentIds: Array.from({ length: TREATMENT_ASSIGN_LIMIT }, (_, i) => i + 1) }).ok).toBe(true);
      expect(parseAssignBody({ treatmentIds: Array.from({ length: TREATMENT_ASSIGN_LIMIT + 1 }, (_, i) => i + 1) }).ok).toBe(false);
    });
  });

  describe('parsePatchBody', () => {
    it('isPromoted must be a boolean', () => {
      expect(parsePatchBody({ isPromoted: true })).toEqual({ ok: true, patch: { isPromoted: 1 } });
      expect(parsePatchBody({ isPromoted: false })).toEqual({ ok: true, patch: { isPromoted: 0 } });
      for (const v of [1, 'true', null, 0]) expect(parsePatchBody({ isPromoted: v })).toEqual({ ok: false, body: { error: 'invalid_field', field: 'isPromoted' } });
    });
    it('minAge: null and 0 mean no limit, integers 1..120 are kept, anything else is wrong', () => {
      expect(parsePatchBody({ minAge: null })).toEqual({ ok: true, patch: { minAge: null } });
      expect(parsePatchBody({ minAge: 0 })).toEqual({ ok: true, patch: { minAge: null } });
      expect(parsePatchBody({ minAge: 19 })).toEqual({ ok: true, patch: { minAge: 19 } });
      expect(parsePatchBody({ minAge: 120 })).toEqual({ ok: true, patch: { minAge: 120 } });
      for (const v of [121, -1, 1.5, '19', '', true, [], {}]) expect(parsePatchBody({ minAge: v })).toEqual({ ok: false, body: { error: 'invalid_field', field: 'minAge' } });
    });
    it('both fields together; neither is invalid_field without a field', () => {
      expect(parsePatchBody({ isPromoted: true, minAge: 18 })).toEqual({ ok: true, patch: { isPromoted: 1, minAge: 18 } });
      for (const b of [{}, null, [], 'x', { other: 1 }]) expect(parsePatchBody(b)).toEqual({ ok: false, body: { error: 'invalid_field' } });
    });
  });

  it('formatDosage prints like the PHP page', () => {
    expect(formatDosage(100)).toBe('100');
    expect(formatDosage('100.00')).toBe('100');
    expect(formatDosage(0.5)).toBe('0.5');
    expect(formatDosage(1.25)).toBe('1.25');
    expect(formatDosage(10)).toBe('10');
    expect(formatDosage(null)).toBe('0');
    expect(formatDosage('abc')).toBe('0');
  });

  it('IN lists use one placeholder per id and never contain a value', () => {
    for (const q of [priceOptionsQuery([4, 5, 6]), imageCountsQuery([4, 5, 6])]) {
      expect(q.sql).toContain('IN (?,?,?)');
      expect(q.values).toEqual([4, 5, 6]);
      expect(q.sql).not.toMatch(/IN \([^?)]*\d/);
    }
    expect(availableQuery(12).values).toEqual([12]);
  });

  it('buildItems fills the optional parts with empty / 0 and maps flags', () => {
    const items = buildItems({
      rows: [
        { id: 1, treatment_id: 9, treatment_name: '보톡스', hashtag: '#a', category_id: 2, category_name: '주사', is_promoted: 1, note: 'n', registered_by: 'hospital', min_age: 19 },
        { id: 2, treatment_id: 8, treatment_name: null, hashtag: null, category_id: null, category_name: null, is_promoted: 0, note: null, registered_by: 'x', min_age: 0 },
      ],
      prices: [{ hospital_treatment_id: 1, option_name: '', body_part: '이마', dosage_value: '50.00', dosage_unit: '유닛', regular_price: 100000, price: 80000, is_representative: 1 }],
      images: [{ hospital_treatment_id: 1, cnt: 3, pending: 1 }],
      questions: [{ hospital_treatment_id: 1, cnt: 2 }],
    });
    expect(items[0]).toEqual({
      id: 1, treatmentId: 9, name: '보톡스', hashtag: '#a', categoryId: 2, categoryName: '주사', isPromoted: true, note: 'n', registeredBy: 'hospital', minAge: 19,
      priceOptions: [{ optionName: '', bodyPart: '이마', dosageValue: '50', dosageUnit: '유닛', regularPrice: 100000, price: 80000, isRepresentative: true }],
      imageCount: 3, pendingImageCount: 1, questionCount: 2,
    });
    expect(items[1]).toEqual({
      id: 2, treatmentId: 8, name: '', hashtag: '', categoryId: null, categoryName: null, isPromoted: false, note: '', registeredBy: 'admin', minAge: null,
      priceOptions: [], imageCount: 0, pendingImageCount: 0, questionCount: 0,
    });
  });

  describe('price options: parsePricesBody', () => {
    const o = (x: Record<string, unknown> = {}) => ({ optionName: '', bodyParts: [], dosageValue: 1, dosageUnit: '회', regularPrice: 0, price: 0, isRepresentative: false, ...x });
    const bad = (body: unknown) => {
      const r = parsePricesBody(body);
      if (r.ok) throw new Error('expected a failure');
      return r.body;
    };
    it('normalises a row: trimmed text, body parts joined with ||, empty prices become 0', () => {
      const r = parsePricesBody({ options: [{ optionName: ' a ', bodyParts: [' 이마 ', '미간'], dosageValue: 50, dosageUnit: ' 샷 ', regularPrice: null, price: 80000, isRepresentative: true }] });
      expect(r).toEqual({ ok: true, options: [{ optionName: 'a', bodyPart: '이마||미간', dosageValue: 50, dosageUnit: '샷', regularPrice: 0, price: 80000, isRepresentative: true }] });
    });
    it('skips blank rows, keeps the position of the others for errors', () => {
      expect(bad({ options: [o({ dosageValue: 0, dosageUnit: '' }), {}, o({ dosageValue: -1 })] })).toEqual({ error: 'invalid_field', index: 2, field: 'dosageValue' });
      const ok = parsePricesBody({ options: [{}, o({ optionName: 'x' })] });
      expect(ok.ok && ok.options).toHaveLength(1);
    });
    it.each([undefined, null, 'x', [], {}, { options: 'a' }, { options: [] }, { options: [{}] }, { options: [{ optionName: '  ', bodyParts: [''] }] }])('no_options for %p', (b) => {
      expect(bad(b)).toEqual({ error: 'no_options' });
    });
    it('more than 50 options -> too_many (also when most are blank); exactly 50 is fine', () => {
      expect(bad({ options: Array.from({ length: 51 }, () => ({})) })).toEqual({ error: 'too_many' });
      expect(parsePricesBody({ options: Array.from({ length: 50 }, (_, i) => o({ dosageValue: i + 1 })) }).ok).toBe(true);
    });
    it('a non-object row -> invalid_field with its index', () => {
      expect(bad({ options: [o(), 5] })).toEqual({ error: 'invalid_field', index: 1 });
      expect(bad({ options: [null] })).toEqual({ error: 'invalid_field', index: 0 });
    });
    it('optionName: text only, at most 100 characters', () => {
      expect(bad({ options: [o({ optionName: 5 })] })).toEqual({ error: 'invalid_field', index: 0, field: 'optionName' });
      expect(bad({ options: [o({ optionName: 'a'.repeat(101) })] })).toEqual({ error: 'invalid_field', index: 0, field: 'optionName' });
      expect(parsePricesBody({ options: [o({ optionName: '가'.repeat(100) })] }).ok).toBe(true);
    });
    it('bodyParts: strings only, not empty, <=100 each, no ||, joined <=500', () => {
      const five = Array.from({ length: 5 }, () => 'a'.repeat(99)); // 495 characters + 4 separators = 503
      for (const parts of ['이마', [1], ['이마', ' '], ['a||b'], ['a'.repeat(101)], five]) {
        expect(bad({ options: [o({ bodyParts: parts })] })).toEqual({ error: 'invalid_field', index: 0, field: 'bodyParts' });
      }
      expect(parsePricesBody({ options: [o({ bodyParts: five.slice(0, 4) })] }).ok).toBe(true);
    });
    it('dosageValue: number > 0, <= 1000000, at most two decimals, required for a filled row', () => {
      for (const v of [0, -1, '5', null, undefined, NaN, 1000000.01, 1.005, 0.001]) {
        expect(bad({ options: [o({ optionName: 'x', dosageValue: v })] })).toEqual({ error: 'invalid_field', index: 0, field: 'dosageValue' });
      }
      for (const v of [0.01, 1.25, 1000000, 100]) expect(parsePricesBody({ options: [o({ dosageValue: v })] }).ok).toBe(true);
    });
    it('dosageUnit: required, at most 20 characters', () => {
      for (const u of ['', 3, 'u'.repeat(21)]) expect(bad({ options: [o({ optionName: 'x', dosageUnit: u })] })).toEqual({ error: 'invalid_field', index: 0, field: 'dosageUnit' });
      expect(parsePricesBody({ options: [o({ dosageUnit: 'u'.repeat(20) })] }).ok).toBe(true);
    });
    it.each([['regularPrice'], ['price']])('%s: whole won 0..2000000000; fraction, negative, text, too large -> invalid_field', (field) => {
      for (const v of [1.5, -1, '100', 2_000_000_001, true]) expect(bad({ options: [o({ [field]: v })] })).toEqual({ error: 'invalid_field', index: 0, field });
      for (const v of [0, null, undefined, '', 1, 2_000_000_000]) expect(parsePricesBody({ options: [o({ [field]: v })] }).ok).toBe(true);
    });
    it('price_order: list price below a positive sale price; equal, zero or missing prices pass', () => {
      expect(bad({ options: [o({ regularPrice: 9999, price: 10000 })] })).toEqual({ error: 'price_order', index: 0 });
      for (const [reg, price] of [[10000, 10000], [10001, 10000], [0, 10000], [10000, 0], [0, 0]]) expect(parsePricesBody({ options: [o({ regularPrice: reg, price })] }).ok).toBe(true);
    });
    it('representative: one at most; none is fine; a blank row cannot hold it', () => {
      expect(bad({ options: [o({ isRepresentative: true }), o({ dosageValue: 2, isRepresentative: true })] })).toEqual({ error: 'multiple_representative' });
      expect(parsePricesBody({ options: [o(), o({ dosageValue: 2 })] }).ok).toBe(true);
      expect(parsePricesBody({ options: [o({ isRepresentative: true }), { isRepresentative: true }] }).ok).toBe(true);
      expect(bad({ options: [o({ isRepresentative: 'yes' })] })).toEqual({ error: 'invalid_field', index: 0 });
    });
    it('duplicate key = lower(name) | lower(parts) | dosage as a number | lower(unit); the second row is reported', () => {
      expect(bad({ options: [o({ optionName: 'Abc', bodyParts: ['이마'], dosageUnit: 'CC' }), o({ dosageValue: 2 }), o({ optionName: 'aBC', bodyParts: ['이마'], dosageUnit: 'cc' })] })).toEqual({ error: 'duplicate_option', index: 2 });
      expect(bad({ options: [o({ dosageValue: 1.5 }), o({ dosageValue: 1.50 })] })).toEqual({ error: 'duplicate_option', index: 1 });
      const ok = parsePricesBody({ options: [o({ bodyParts: ['a'] }), o({ bodyParts: ['b'] }), o({ dosageValue: 2 }), o({ dosageUnit: 'cc' }), o({ optionName: 'n' }), o({ bodyParts: ['a', 'b'] })] });
      expect(ok.ok && ok.options).toHaveLength(6);
    });
    it('the order of options is kept (it becomes sort_order)', () => {
      const r = parsePricesBody({ options: [o({ dosageValue: 3 }), {}, o({ dosageValue: 1 }), o({ dosageValue: 2 })] });
      expect(r.ok && r.options.map((x) => x.dosageValue)).toEqual([3, 1, 2]);
    });
  });

  describe('price units', () => {
    it('Latin letters and digits first (case-insensitive), then Korean', () => {
      expect(sortUnits(['회', 'ml', 'Box', '샷', 'IU', 'cc', 'kj', '기타', '라인', '병', '시린지'])).toEqual(['Box', 'cc', 'IU', 'kj', 'ml', '기타', '라인', '병', '샷', '시린지', '회']);
      expect(sortUnits(['b', 'B', 'a'])).toEqual(['a', 'B', 'b']);
      expect(sortUnits(['10ml', '가', '2ml'])).toEqual(['10ml', '2ml', '가']);
    });
    it('does not change the input; the standard list is already in order', () => {
      const input = ['회', 'cc'];
      sortUnits(input);
      expect(input).toEqual(['회', 'cc']);
      expect(sortUnits(DEFAULT_PRICE_UNITS)).toEqual(DEFAULT_PRICE_UNITS);
    });
    it('buildUnits: table order does not matter, blanks and repeats drop out, no usable row -> standard list', () => {
      expect(buildUnits([{ name: '회' }, { name: ' ' }, { name: 'ml' }, { name: 'ml' }, { name: null }])).toEqual(['ml', '회']);
      expect(buildUnits([])).toEqual(DEFAULT_PRICE_UNITS);
      expect(buildUnits([{ name: '' }])).toEqual(DEFAULT_PRICE_UNITS);
    });
  });

  describe('price option statements: parameters only', () => {
    it('units, insert and summary reset', () => {
      expect(unitsQuery().sql).toBe('SELECT name FROM gangnamunni_treatment_units WHERE is_active = 1 ORDER BY id ASC');
      const evil = "x'; DROP TABLE a;--";
      const q = insertPriceQuery(7, { optionName: evil, bodyPart: '이마', dosageValue: 2.5, dosageUnit: '샷', regularPrice: 10, price: 5, isRepresentative: true }, 3);
      expect(q.sql).not.toContain('DROP');
      expect(q.sql.match(/\?/g)).toHaveLength(9);
      expect(q.values).toEqual([7, evil, '이마', 2.5, '샷', 10, 5, 1, 3]);
      expect(clearPriceSummaryQuery(12, 7)).toEqual({ sql: expect.stringContaining('WHERE id = ? AND hospital_id = ?'), values: [7, 12] });
    });
  });
});
