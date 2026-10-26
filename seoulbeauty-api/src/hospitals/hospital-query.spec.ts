import { buildHospitalListQuery, HospitalListParams } from './hospital-query';

const base: HospitalListParams = { q: '', category: '', treatment: '', treatmentId: 0, regions: [], sort: 'manual', limit: 20, offset: 0 };
const flags = { hasDisplayOrder: true, hasWishlist: true, hasShowRating: true };
const count = (s: string, c = '?') => s.split(c).length - 1;

describe('buildHospitalListQuery', () => {
  it('always applies the public-visibility filters', () => {
    const q = buildHospitalListQuery(base, flags);
    expect(q.sql).toContain('h.is_active = 1');
    expect(q.sql).toContain('COALESCE(h.is_demo, 0) = 0');
    expect(q.sql).not.toContain('raw_data');
  });

  it('placeholders always equal values (injection-safe)', () => {
    const evil = "x' OR 1=1 -- %_\\";
    for (const sort of ['manual', 'price', 'rating', 'popular', 'review'] as const) {
      const q = buildHospitalListQuery({ ...base, q: evil, category: evil, treatment: evil, treatmentId: 5, regions: [evil, 'a'], sort }, flags);
      expect(count(q.sql)).toBe(q.values.length);
      expect(count(q.countSql)).toBe(q.countValues.length);
      expect(q.sql).not.toContain('OR 1=1');
    }
  });

  it('member: wishlisted EXISTS placeholder comes first', () => {
    const q = buildHospitalListQuery({ ...base, q: 'x', memberNo: 42 }, flags);
    expect(q.sql.indexOf('mw.member_id = ?')).toBeLessThan(q.sql.indexOf('h.name LIKE ?'));
    expect(q.values[0]).toBe(42);
    expect(count(q.sql)).toBe(q.values.length);
  });
  it('escapes LIKE wildcards', () => {
    const q = buildHospitalListQuery({ ...base, q: '50%_off' }, flags);
    expect(q.values[0]).toBe('%50\\%\\_off%');
  });

  it('price sort with treatment uses treatment price subquery', () => {
    const q = buildHospitalListQuery({ ...base, sort: 'price', treatmentId: 7, treatment: 'Botox' }, flags);
    expect(q.sql).toContain('gangnamunni_hospital_treatment_prices');
    expect(q.values.slice(-4)).toEqual([7, 7, 20, 0]);
  });

  it('falls back when optional columns are missing', () => {
    const q = buildHospitalListQuery(base, { hasDisplayOrder: false, hasWishlist: false, hasShowRating: false });
    expect(q.sql).toContain('ORDER BY h.id ASC');
    expect(q.sql).toContain('0 AS wishlist_count');
  });
});
