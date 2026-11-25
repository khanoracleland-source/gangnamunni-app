import { buildDetail, buildList, levelOf, treatmentNamesQuery, assignedTreatmentsQuery } from './doctors.logic';
import { assignDetailedTreatmentsQuery, parseEdit } from './doctors.write.logic';

const form = (extra: Record<string, unknown>) => ({ name: 'Kim', hospitalId: 12, specialty: '일반의', qualification: '', treatmentIds: [], ...extra });
const ok = (extra: Record<string, unknown>) => {
  const r = parseEdit(form(extra), '일반의');
  if (!r.ok) throw new Error(`expected ok, got ${JSON.stringify(r.body)}`);
  return r.edit;
};
const bad = (extra: Record<string, unknown>) => {
  const r = parseEdit(form(extra), '일반의');
  return r.ok ? null : r.body;
};
const item = (o: Record<string, unknown> = {}) => ({ id: 3, level: 'expert', caseCount: 12, certification: ' Board 2024 ', ...o });

describe('doctor treatment expertise: input', () => {
  it('without "treatments" nothing changes (ids only, no details)', () => {
    const e = ok({ treatmentIds: [9, 9, 10] });
    expect(e.treatmentIds).toEqual([9, 10]);
    expect(e.treatmentDetails).toEqual([]);
  });

  it('reads level, cases and a trimmed certification; the id joins the wanted ids once', () => {
    const e = ok({ treatmentIds: [3, 9], treatments: [item()] });
    expect(e.treatmentDetails).toEqual([{ id: 3, level: 'expert', caseCount: 12, certification: 'Board 2024' }]);
    expect(e.treatmentIds).toEqual([3, 9]);
  });

  it('a missing certification is an empty one', () => {
    expect(ok({ treatments: [item({ certification: undefined })] }).treatmentDetails[0].certification).toBe('');
    expect(ok({ treatments: [item({ certification: null })] }).treatmentDetails[0].certification).toBe('');
  });

  it('rejects an unknown level, bad case counts and a long or odd certification', () => {
    expect(bad({ treatments: [item({ level: 'master' })] })).toEqual({ error: 'invalid_level', field: 'treatments' });
    expect(bad({ treatments: [item({ level: 1 })] })).toEqual({ error: 'invalid_level', field: 'treatments' });
    for (const caseCount of [-3, 1.5, '4', null, 2_147_483_648]) {
      expect(bad({ treatments: [item({ caseCount })] })).toEqual({ error: 'invalid_case_count', field: 'treatments' });
    }
    expect(bad({ treatments: [item({ certification: 'x'.repeat(501) })] })).toEqual({ error: 'invalid_certification', field: 'treatments' });
    expect(bad({ treatments: [item({ certification: 'a\u0000b' })] })).toEqual({ error: 'invalid_certification', field: 'treatments' });
    expect(bad({ treatments: [item({ certification: 5 })] })).toEqual({ error: 'invalid_certification', field: 'treatments' });
    expect(ok({ treatments: [item({ certification: '전'.repeat(500) })] }).treatmentDetails).toHaveLength(1);
  });

  it('the ids and the detailed treatments together may not pass the limit', () => {
    const ids = Array.from({ length: 300 }, (_, i) => i + 1);
    const details = Array.from({ length: 300 }, (_, i) => item({ id: i + 1000 }));
    expect(bad({ treatmentIds: ids, treatments: details })).toEqual({ error: 'invalid_field', field: 'treatments' });
    expect(ok({ treatmentIds: ids, treatments: details.slice(0, 200) }).treatmentIds).toHaveLength(500);
  });

  it('rejects a list that is not a list, wrong ids and the same treatment twice', () => {
    expect(bad({ treatments: 'x' })).toEqual({ error: 'invalid_field', field: 'treatments' });
    expect(bad({ treatments: [null] })).toEqual({ error: 'invalid_field', field: 'treatments' });
    expect(bad({ treatments: [item({ id: 0 })] })).toEqual({ error: 'invalid_field', field: 'treatments' });
    expect(bad({ treatments: [item({ id: '3' })] })).toEqual({ error: 'invalid_field', field: 'treatments' });
    expect(bad({ treatments: [item(), item()] })).toEqual({ error: 'invalid_field', field: 'treatments' });
  });
});

describe('doctor treatment expertise: SQL and answers', () => {
  it('writes every value through a placeholder and stores an empty certification as NULL', () => {
    const q = assignDetailedTreatmentsQuery(5, 12, [
      { id: 3, level: 'expert', caseCount: 12, certification: 'Board' },
      { id: 4, level: 'basic', caseCount: 0, certification: '' },
    ]);
    expect(q.sql).not.toContain('Board');
    expect(q.sql.match(/\(\?, \?, \?, \?, \?, \?, 1\)/g)).toHaveLength(2);
    expect(q.values).toEqual([5, 12, 3, 12, 'expert', 'Board', 5, 12, 4, 0, 'basic', null]);
    expect(q.sql).toContain('ON DUPLICATE KEY UPDATE');
  });

  it('the reads carry level, cases and certification', () => {
    expect(treatmentNamesQuery([1]).sql).toContain('dt.speciality_level');
    const sql = assignedTreatmentsQuery(5, 12).sql;
    for (const col of ['dt.speciality_level', 'dt.case_count', 'dt.certification']) expect(sql).toContain(col);
  });

  it('an unknown stored level counts as basic', () => {
    expect(['expert', 'advanced', 'basic', 'weird', null].map(levelOf)).toEqual(['expert', 'advanced', 'basic', 'basic', 'basic']);
  });

  it('the list counts the treatments of every doctor per level', () => {
    const all = { scope: 'all' as const, hospitalIds: [] };
    const q = { hospitalId: 0, search: '', status: 'all' as const, page: 1, perPage: 15 };
    const list = buildList(all, q, undefined, 2, [{ id: 1, name: 'A' }, { id: 2, name: 'B' }], [
      { doctor_id: 1, name: 'x', speciality_level: 'expert' },
      { doctor_id: 1, name: 'y', speciality_level: 'basic' },
      { doctor_id: 1, name: 'z', speciality_level: 'expert' },
    ]);
    expect(list.items[0].levelCounts).toEqual({ basic: 1, advanced: 0, expert: 2 });
    expect(list.items[1].levelCounts).toEqual({ basic: 0, advanced: 0, expert: 0 });
  });

  it('the detail gives each treatment its level, cases and certification', () => {
    const d = buildDetail({ id: 1, hospital_id: 12, name: 'A' }, [
      { id: 3, name: 'T', category_name: 'C', speciality_level: 'advanced', case_count: '7', certification: 'Board' },
      { id: 4, name: 'U', category_name: null, speciality_level: null, case_count: -2, certification: null },
    ]);
    expect(d.treatments).toEqual([
      { id: 3, name: 'T', category: 'C', level: 'advanced', caseCount: 7, certification: 'Board' },
      { id: 4, name: 'U', category: '', level: 'basic', caseCount: 0, certification: '' },
    ]);
  });
});
