import {
  availableQuery, buildItems, canManageTreatments, formatDosage, imageCountsQuery, parseAssignBody, parsePatchBody, priceOptionsQuery, TREATMENT_ASSIGN_LIMIT,
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
});
