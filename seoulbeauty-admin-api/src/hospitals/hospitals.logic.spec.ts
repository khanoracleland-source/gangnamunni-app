import {
  adGrade, buildDetail, canSee, countQuery, dateOrNull, detailQuery, groupTreatments, likeContains, listQuery, normalizeListQuery, parseHospitalId,
  parseObject, parseStringList, Scope, seesNothing, statsQuery, toListItem, treatmentOptionsQuery,
} from './hospitals.logic';

const ALL: Scope = { scope: 'all', hospitalIds: [] };
const OWN: Scope = { scope: 'own', hospitalIds: [12] };
const NONE: Scope = { scope: 'own', hospitalIds: [] };

describe('normalizeListQuery', () => {
  it('defaults', () => {
    expect(normalizeListQuery({})).toEqual({ search: '', treatmentId: 0, active: 'all', page: 1, perPage: 20 });
    expect(normalizeListQuery(undefined)).toEqual({ search: '', treatmentId: 0, active: 'all', page: 1, perPage: 20 });
  });
  it.each([['10', 10], ['20', 20], ['50', 50], ['15', 20], ['0', 20], ['100', 20], ['-10', 20], ['abc', 20], ['10.5', 20], ['1e1', 20], [undefined, 20], [['10', '50'], 20]])(
    'perPage %j -> %d', (raw, want) => {
      expect(normalizeListQuery({ perPage: raw }).perPage).toBe(want);
    });
  it.each([['1', 1], ['7', 7], ['0', 1], ['-3', 1], ['abc', 1], ['2.5', 1], ['', 1], [undefined, 1], ['999999999', 100000], ['99999999999999999999', 1], [{ a: 1 }, 1]])(
    'page %j -> %d', (raw, want) => {
      expect(normalizeListQuery({ page: raw }).page).toBe(want);
    });
  it.each([['1', '1'], ['0', '0'], ['all', 'all'], ['', 'all'], ['true', 'all'], ['2', 'all'], [undefined, 'all'], [['1'], 'all']])('active %j -> %s', (raw, want) => {
    expect(normalizeListQuery({ active: raw }).active).toBe(want);
  });
  it.each([['5', 5], ['0', 0], ['-1', 0], ['x', 0], ['5 OR 1=1', 0], ['0x10', 0], [undefined, 0]])('treatmentId %j -> %d', (raw, want) => {
    expect(normalizeListQuery({ treatmentId: raw }).treatmentId).toBe(want);
  });
  it('search is trimmed and cut to 100 characters (not bytes)', () => {
    expect(normalizeListQuery({ search: '  강남  ' }).search).toBe('강남');
    expect(normalizeListQuery({ search: 'a'.repeat(250) }).search).toHaveLength(100);
    expect(Array.from(normalizeListQuery({ search: '병'.repeat(150) }).search)).toHaveLength(100);
    expect(normalizeListQuery({ search: ['a', 'b'] }).search).toBe('');
    expect(normalizeListQuery({ search: { $ne: '' } }).search).toBe('');
  });
});

describe('parseHospitalId', () => {
  it.each([['1', 1], ['120', 120], ['0', 0], ['-1', 0], ['01', 0], ['1.5', 0], ['abc', 0], ['12abc', 0], ['', 0], [' 12', 0], ['99999999999', 0], ['4294967296', 0]])(
    '%j -> %d', (raw, want) => {
      expect(parseHospitalId(raw)).toBe(want);
    });
});

describe('scope', () => {
  it('who sees what', () => {
    expect(seesNothing(ALL)).toBe(false);
    expect(seesNothing(OWN)).toBe(false);
    expect(seesNothing(NONE)).toBe(true);
    expect(canSee(ALL, 99)).toBe(true);
    expect(canSee(OWN, 12)).toBe(true);
    expect(canSee(OWN, 13)).toBe(false);
    expect(canSee(NONE, 12)).toBe(false);
  });
  it('an empty scope never becomes SQL', () => {
    expect(() => statsQuery(NONE)).toThrow();
    expect(() => listQuery(NONE, normalizeListQuery({}))).toThrow();
    expect(() => treatmentOptionsQuery(NONE)).toThrow();
    expect(() => detailQuery(NONE, 12)).toThrow();
  });
  it('a limited scope is part of every statement, as a bound value', () => {
    const q = normalizeListQuery({});
    for (const built of [statsQuery(OWN), countQuery(OWN, q), listQuery(OWN, q), detailQuery(OWN, 12)]) {
      expect(built.sql).toContain('h.id IN (?)');
      expect(built.values).toContainEqual([12]);
    }
    expect(treatmentOptionsQuery(OWN).sql).toContain('ht.hospital_id IN (?)');
    expect(treatmentOptionsQuery(OWN).values).toEqual([[12]]);
    for (const built of [statsQuery(ALL), countQuery(ALL, q), listQuery(ALL, q), detailQuery(ALL, 12), treatmentOptionsQuery(ALL)]) {
      expect(built.sql).not.toContain(' IN (?)');
    }
  });
});

describe('list statements', () => {
  it('always approved only, newest first, paged with bound values', () => {
    const built = listQuery(ALL, normalizeListQuery({ page: '3', perPage: '10' }));
    expect(built.sql).toContain("h.status = 'approved'");
    expect(built.sql).toContain('ORDER BY h.created_at DESC, h.id DESC');
    expect(built.values).toEqual([20, 10]);
  });
  it('filters are bound, never concatenated', () => {
    const q = normalizeListQuery({ search: "50%_o'ff\\", treatmentId: '7', active: '0' });
    const built = countQuery(OWN, q);
    const p = "%50\\%\\_o'ff\\\\%";
    expect(built.values).toEqual([[12], p, p, p, p, p, 7]);
    expect(built.sql).not.toContain("o'ff");
    expect(built.sql).toContain('ht.treatment_id = ?');
    expect(built.sql).toContain('COALESCE(h.is_active, 0) = 0');
    expect(countQuery(ALL, normalizeListQuery({ active: '1' })).sql).toContain('h.is_active = 1');
    expect(countQuery(ALL, normalizeListQuery({})).sql).not.toContain('is_active');
  });
  it('the counters ignore the list filters', () => {
    expect(statsQuery(ALL).values).toEqual([]);
    expect(statsQuery(ALL).sql).not.toContain('LIKE');
  });
  it('likeContains escapes wildcards', () => {
    expect(likeContains('a%b_c\\')).toBe('%a\\%b\\_c\\\\%');
  });
});

describe('column decoding', () => {
  it.each([
    ['["피부","성형"]', ['피부', '성형']],
    [['피부', ' 성형 ', '', null, 3, { a: 1 }], ['피부', '성형', '3']],
    ['피부, 성형,,  치과 ', ['피부', '성형', '치과']],
    ['피부', ['피부']],
    ['', []], [null, []], [undefined, []], ['[]', []], ['{"a":1}', []], ['null', []], ['[broken', ['[broken']], [42, []],
  ])('parseStringList(%j)', (raw, want) => {
    expect(parseStringList(raw)).toEqual(want);
  });
  it.each([
    ['{"mon":"09:00-18:00"}', { mon: '09:00-18:00' }], [{ mon: 'x' }, { mon: 'x' }],
    ['', {}], [null, {}], ['[1,2]', {}], [[1], {}], ['not json', {}], ['"text"', {}], ['3', {}],
  ])('parseObject(%j)', (raw, want) => {
    expect(parseObject(raw)).toEqual(want);
  });
  it('adGrade / dateOrNull', () => {
    expect(['S', 'A', 'B', 'C', 'D', 'E', 'none', null, '', 'Z', 's'].map(adGrade)).toEqual(['S', 'A', 'B', 'C', 'D', 'E', 'none', 'none', 'none', 'none', 'none']);
    expect(dateOrNull('2026-11-01 10:00:00')).toBe('2026-11-01 10:00:00');
    expect([null, undefined, '', '0000-00-00 00:00:00'].map(dateOrNull)).toEqual([null, null, null, null]);
  });
});

describe('row mapping', () => {
  it('list item: NULL columns become safe defaults', () => {
    expect(toListItem({ id: '5', name: 'A', address: null, phone: null, categories: null, ad_grade: null, is_active: null, rating: null, review_count: null,
      show_rating: null, has_unreviewed_changes: null, created_at: null }))
      .toEqual({ id: 5, name: 'A', address: '', phone: '', categories: [], adGrade: 'none', isActive: false, rating: 0, reviewCount: 0, showRating: true,
        hasUnreviewedChanges: false, createdAt: null });
  });
  it('treatments are grouped in row order; no category -> 기타', () => {
    expect(groupTreatments([
      { id: 1, name: '보톡스', category_name: '쁘띠' }, { id: 2, name: '필러', category_name: '쁘띠' },
      { id: 3, name: '제모', category_name: null }, { id: 4, name: '기타시술', category_name: '' }, { id: 5, name: '리프팅', category_name: '레이저' },
    ])).toEqual([
      { category: '쁘띠', items: [{ id: 1, name: '보톡스' }, { id: 2, name: '필러' }] },
      { category: '기타', items: [{ id: 3, name: '제모' }, { id: 4, name: '기타시술' }] },
      { category: '레이저', items: [{ id: 5, name: '리프팅' }] },
    ]);
  });
  it('detail: unknown extra columns of a row never reach the response', () => {
    const d = buildDetail({
      hospital: { id: 1, name: 'A', status: 'approved', ai_own_api_key: 'sk-secret', cert_business: 'uploads/certs/b.pdf', cert_medical: 'm.pdf', password: 'x',
        has_cert_business: 1, has_cert_medical: 0, latitude: '37.5', longitude: null },
      optional: null, images: [], doctors: [], revisions: [],
    });
    const text = JSON.stringify(d);
    for (const leaked of ['sk-secret', 'b.pdf', 'm.pdf', 'ai_own', 'cert', 'password']) expect(text).not.toContain(leaked);
    expect(d.documents).toEqual({ business: true, medical: false });
    expect(d.hospital.latitude).toBe(37.5);
    expect(d.hospital.longitude).toBeNull();
    expect(d.hospital.kakaoChannelUrl).toBe('');
    expect(d.hospital.businessHours).toEqual({});
  });
});
