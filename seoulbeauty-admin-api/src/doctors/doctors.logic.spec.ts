import {
  assignedTreatmentsQuery, buildDetail, buildList, countQuery, detailQuery, hospitalOptionsQuery, hospitalTreatmentOptionsQuery, listQuery, normalizeListQuery,
  parseDoctorId, statsQuery, toListItem, treatmentNamesQuery,
} from './doctors.logic';

const all = { scope: 'all' as const, hospitalIds: [] };
const own = { scope: 'own' as const, hospitalIds: [12] };
const q = (extra: Record<string, unknown> = {}) => normalizeListQuery(extra);

describe('normalizeListQuery', () => {
  it('knows the inactive filter and reads every stored state as active, inactive or resigned', () => {
    expect(q({ status: 'inactive' }).status).toBe('inactive');
    const l = listQuery(all, q({ status: 'inactive' }), true);
    expect(l.sql).toContain("d.status IS NULL OR d.status NOT IN ('active', 'resigned')");
    expect(l.values).not.toContain('inactive');
    const item = (status: unknown) => toListItem({ id: 1, name: 'a', status }).status;
    expect([item('active'), item('resigned'), item('vacation'), item('leave'), item(null)]).toEqual(['active', 'resigned', 'inactive', 'inactive', 'inactive']);
  });

  it('has defaults and never throws', () => {
    expect(q()).toEqual({ hospitalId: 0, search: '', status: 'all', page: 1, perPage: 15 });
    expect(normalizeListQuery(undefined).perPage).toBe(15);
    expect(q({ hospitalId: '-3', page: '0', perPage: '7', status: 'x' })).toEqual({ hospitalId: 0, search: '', status: 'all', page: 1, perPage: 15 });
  });
  it('accepts the allowed values', () => {
    expect(q({ hospitalId: '12', page: '3', perPage: '50', status: 'resigned', search: '  kim ' })).toEqual({ hospitalId: 12, search: 'kim', status: 'resigned', page: 3, perPage: 50 });
    expect(q({ search: 'x'.repeat(300) }).search).toHaveLength(100);
  });
});

describe('parseDoctorId', () => {
  it('is a plain positive integer or 0', () => {
    expect(parseDoctorId('15')).toBe(15);
    for (const bad of ['0', '-1', '1.5', '1e3', '0x10', ' 5', 'abc', '', undefined]) expect(parseDoctorId(bad)).toBe(0);
  });
});

describe('list SQL', () => {
  it('is parametrised and limited by the scope', () => {
    const l = listQuery(own, q({ search: '50%_off', hospitalId: '12', status: 'active' }), true);
    expect(l.sql).toContain('d.hospital_id IN (?)');
    expect(l.sql).toContain('d.hospital_id = ?');
    expect(l.sql).toContain('d.status = ?');
    expect(l.sql).not.toContain('50%');
    expect(l.values).toEqual([[12], 12, 'active', '%50\\%\\_off%', '%50\\%\\_off%', '%50\\%\\_off%', '%50\\%\\_off%', '%50\\%\\_off%', 0, 15]);
    expect(countQuery(own, q()).values).toEqual([[12]]);
  });
  it('shows the app order first when the column exists, else by name', () => {
    expect(listQuery(all, q(), true).sql).toContain('d.display_order ASC');
    const plain = listQuery(all, q(), false);
    expect(plain.sql).toContain('NULL AS display_order');
    expect(plain.sql).not.toContain('d.display_order ASC');
    expect(listQuery(all, q({ page: '3', perPage: '10' }), true).values).toEqual([20, 10]);
  });
  it('refuses an empty limited scope', () => {
    expect(() => listQuery({ scope: 'own', hospitalIds: [] }, q(), true)).toThrow();
    expect(() => statsQuery({ scope: 'own', hospitalIds: [] })).toThrow();
  });
  it('queries by ids with one placeholder', () => {
    expect(treatmentNamesQuery([1, 2]).values).toEqual([[1, 2]]);
    expect(detailQuery(own, 5, true).values).toEqual([5, [12]]);
    expect(detailQuery(all, 5, false).values).toEqual([5]);
    expect(assignedTreatmentsQuery(5, 12).values).toEqual([5, 12]);
    expect(hospitalTreatmentOptionsQuery(12).values).toEqual([12]);
    expect(hospitalOptionsQuery(own).values).toEqual([[12]]);
  });
});

describe('answers', () => {
  it('maps a row, treats empty photo and a place below 1 as none', () => {
    expect(toListItem({ id: '4', hospital_id: 12, hospital_name: 'H', name: 'Kim', specialty: ' 일반의 ', qualification: 'a\nb', photo_url: '  ', status: 'active', display_order: 0 }, ['t'])).toEqual({
      id: 4, name: 'Kim', hospitalId: 12, hospitalName: 'H', specialty: '일반의', qualification: 'a\nb', photoUrl: null, status: 'active', displayOrder: null, treatments: ['t'], levelCounts: { basic: 0, advanced: 0, expert: 0 },
    });
    expect(toListItem({ id: 1, display_order: 3, photo_url: 'uploads/doctors/a.jpg' }).displayOrder).toBe(3);
  });
  it('joins treatment names to their doctors and reads the totals', () => {
    const list = buildList(all, q(), { total: '9', hospitals: '3', active: '8' }, 9, [{ id: 1, name: 'A' }, { id: 2, name: 'B' }], [{ doctor_id: 1, name: 'x' }, { doctor_id: 1, name: 'y' }]);
    expect(list.stats).toEqual({ total: 9, hospitals: 3, active: 8 });
    expect(list.items.map((i) => i.treatments)).toEqual([['x', 'y'], []]);
    expect(list.total).toBe(9);
  });
  it('detail adds experience, bio and the assigned treatments', () => {
    const d = buildDetail({ id: 1, hospital_id: 12, name: 'A', experience: '7', bio: 'b' }, [{ id: 3, name: 'T', category_name: null }]);
    expect(d).toMatchObject({ id: 1, experience: 7, bio: 'b', treatments: [{ id: 3, name: 'T', category: '' }] });
  });
});
