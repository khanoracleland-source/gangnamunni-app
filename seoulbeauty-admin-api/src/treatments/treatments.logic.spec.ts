import { aliasesQuery, buildAnswer, categoriesQuery, conflictsQuery, defaultImagesQuery, parseAnesthesiaType, parseTableMode, readImageUrl, readRevisitDays, revisitQuery, tableModeQuery, treatmentsQuery } from './treatments.logic';

const cat = (id: number, name: string, sort: number, active = 1, count = 0) => ({ id, name, slug: name.toLowerCase(), icon: 'bi-star', sort_order: sort, is_active: active, treatment_count: count });
const tm = (id: number, category_id: number | null, name: string, extra: Record<string, unknown> = {}) => ({
  id, category_id, category_name: category_id === 1 ? 'Face' : category_id === 2 ? 'Body' : null, sort_order: id, name, hashtag: '#' + name, description: 'd' + id,
  duration_min: 30, recovery_days: 2, anesthesia_type: 'local', repeat_cycle_months: 6, precautions: 'p', avg_price_min: 10, avg_price_max: 20,
  is_active: 1, popularity_score: 5, created_by: 'admin', created_at: '2026-11-18 10:00:00', hospital_count: 3, ...extra,
});

describe('treatment master: statements', () => {
  it('every statement names its columns, and only the setting is a parameter', () => {
    const all = [categoriesQuery(), treatmentsQuery(), aliasesQuery(), defaultImagesQuery(), revisitQuery(), conflictsQuery(), tableModeQuery()];
    for (const q of all) {
      expect(q.sql).not.toMatch(/SELECT\s+\*/i);
      expect(q.sql).not.toMatch(/INSERT|UPDATE|DELETE/);
    }
    expect(tableModeQuery().values).toEqual(['tc_table_mode']);
    expect(all.filter((q) => q.sql !== tableModeQuery().sql).every((q) => q.values.length === 0)).toBe(true);
    expect(treatmentsQuery().sql).toContain('ORDER BY c.sort_order ASC, t.sort_order ASC, t.popularity_score DESC, t.id ASC');
    expect(treatmentsQuery().sql).toContain('FROM gangnamunni_hospital_treatments ht WHERE ht.treatment_id = t.id');
    expect(treatmentsQuery().sql).not.toContain('review');
    expect(categoriesQuery().sql).not.toContain('is_active = 1');
  });
});

describe('treatment master: small readers', () => {
  it('table mode: stored value or excel', () => {
    expect(parseTableMode('row')).toBe('row');
    expect(parseTableMode(' hybrid ')).toBe('hybrid');
    for (const v of [null, undefined, '', 'grid', 0]) expect(parseTableMode(v)).toBe('excel');
  });
  it('anesthesia: stored value or none', () => {
    expect(parseAnesthesiaType('general')).toBe('general');
    for (const v of [null, '', 'x']) expect(parseAnesthesiaType(v)).toBe('none');
  });
  it('image: blank and NULL are null', () => {
    expect(readImageUrl(null)).toBeNull();
    expect(readImageUrl('  ')).toBeNull();
    expect(readImageUrl(' uploads/treatments/a.jpg ')).toBe('uploads/treatments/a.jpg');
  });
  it('revisit days: NULL and 0 are null', () => {
    expect(readRevisitDays(null)).toBeNull();
    expect(readRevisitDays(0)).toBeNull();
    expect(readRevisitDays('14')).toBe(14);
  });
});

describe('treatment master: buildAnswer', () => {
  const base = () => ({
    categories: [cat(1, 'Face', 1, 1, 2), cat(2, 'Body', 2, 0, 1), cat(3, 'Empty', 3, 1, 0)],
    treatments: [tm(10, 1, 'Botox'), tm(11, 1, 'Filler', { is_active: 0, category_id: 1 }), tm(12, 2, 'Lipo', { category_id: 2, created_at: null })],
    aliases: [{ id: 1, treatment_id: 10, alias: 'botox', lang: 'en' }, { id: 2, treatment_id: 10, alias: '보톡스', lang: 'ko' }, { id: 3, treatment_id: 99, alias: 'orphan', lang: 'ko' }],
    defaultImages: [{ id: 10, default_image_url: 'uploads/treatments/t.jpg' }, { id: 11, default_image_url: '' }, { id: 12, default_image_url: null }],
    revisit: [{ id: 10, revisit_days: 30, revisit_strict: 1 }, { id: 11, revisit_days: 0, revisit_strict: 1 }, { id: 12, revisit_days: null, revisit_strict: 0 }],
    conflicts: [{ id: 5, tm_a_id: 10, tm_b_id: 12 }],
    tableMode: 'row',
  });

  it('joins everything and counts like the PHP page', () => {
    const a = buildAnswer(base());
    expect(a.stats).toEqual({ total: 3, active: 2, inactive: 1, categories: 2 });
    expect(a.schema).toEqual({ revisit: true, defaultImage: true, conflicts: true });
    expect(a.tableMode).toBe('row');
    expect(a.categories.map((c) => [c.id, c.isActive, c.treatmentCount])).toEqual([[1, true, 2], [2, false, 1], [3, true, 0]]);
    expect(a.conflicts).toEqual([{ id: 5, a: 10, b: 12 }]);
    const botox = a.items[0];
    expect(botox).toEqual({
      id: 10, categoryId: 1, categoryName: 'Face', sortOrder: 10, name: 'Botox', hashtag: '#Botox', description: 'd10', defaultImageUrl: 'uploads/treatments/t.jpg',
      durationMin: 30, recoveryDays: 2, anesthesiaType: 'local', repeatCycleMonths: 6, revisitDays: 30, revisitStrict: true, precautions: 'p', avgPriceMin: 10, avgPriceMax: 20,
      isActive: true, popularityScore: 5, createdBy: 'admin', createdAt: '2026-11-18 10:00:00', aliasCount: 2, hospitalCount: 3,
      aliases: [{ id: 1, alias: 'botox', lang: 'en' }, { id: 2, alias: '보톡스', lang: 'ko' }],
    });
    expect(a.items[1]).toMatchObject({ id: 11, isActive: false, defaultImageUrl: null, revisitDays: null, revisitStrict: false, aliasCount: 0, aliases: [] });
    expect(a.items[2]).toMatchObject({ id: 12, categoryName: 'Body', createdAt: null, defaultImageUrl: null, revisitDays: null });
    expect(a.items.map((i) => i.id)).toEqual([10, 11, 12]);
  });

  it('missing optional columns / table / setting: flags false, fields neutral, nothing thrown', () => {
    const a = buildAnswer({ ...base(), defaultImages: null, revisit: null, conflicts: null, tableMode: null });
    expect(a.schema).toEqual({ revisit: false, defaultImage: false, conflicts: false });
    expect(a.tableMode).toBe('excel');
    expect(a.conflicts).toEqual([]);
    for (const i of a.items) expect(i).toMatchObject({ defaultImageUrl: null, revisitDays: null, revisitStrict: false });
  });

  it('a treatment without a category keeps a null category id and an empty name', () => {
    const a = buildAnswer({ ...base(), treatments: [tm(20, null, 'Loose', { category_name: null })] });
    expect(a.items[0]).toMatchObject({ categoryId: null, categoryName: '' });
    expect(a.stats).toEqual({ total: 1, active: 1, inactive: 0, categories: 2 });
  });

  it('empty database -> empty answer with zero counters', () => {
    const a = buildAnswer({ categories: [], treatments: [], aliases: [], defaultImages: [], revisit: [], conflicts: [], tableMode: undefined });
    expect(a).toEqual({ stats: { total: 0, active: 0, inactive: 0, categories: 0 }, schema: { revisit: true, defaultImage: true, conflicts: true }, tableMode: 'excel', categories: [], items: [], conflicts: [] });
  });
});
