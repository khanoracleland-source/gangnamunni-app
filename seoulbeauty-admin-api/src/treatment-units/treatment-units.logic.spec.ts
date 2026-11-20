import { buildItems, deactivateQuery, findByNameQuery, insertQuery, listQuery, parseUnitName } from './treatment-units.logic';

describe('treatment units: name rule', () => {
  it('trims and accepts 1..20 whole characters', () => {
    expect(parseUnitName({ name: '  시린지 ' })).toEqual({ name: '시린지' });
    expect(parseUnitName({ name: 'a'.repeat(20) })).toEqual({ name: 'a'.repeat(20) });
    // 20 characters outside the BMP are still 20 characters (the column counts characters, not bytes)
    expect(parseUnitName({ name: '😀'.repeat(20) })).toEqual({ name: '😀'.repeat(20) });
  });
  it.each([
    [{ name: '' }, 'required'],
    [{ name: '   ' }, 'required'],
    [{}, 'required'],
    [{ name: null }, 'required'],
    [{ name: 5 }, 'bad_format'],
    [{ name: 'a'.repeat(21) }, 'too_long'],
    [{ name: '😀'.repeat(21) }, 'too_long'],
    [{ name: 'a\u0007b' }, 'bad_format'],
    [{ name: 'a\nb' }, 'bad_format'],
    [{ name: 'a\u007fb' }, 'bad_format'],
  ])('rejects %j', (body, reason) => {
    expect(parseUnitName(body)).toEqual({ error: 'invalid_field', field: 'name', reason });
  });
  it('a body that is not an object -> invalid_body', () => {
    expect(parseUnitName(null)).toEqual({ error: 'invalid_body' });
    expect(parseUnitName([])).toEqual({ error: 'invalid_body' });
    expect(parseUnitName('x')).toEqual({ error: 'invalid_body' });
  });
});

describe('treatment units: list order and SQL', () => {
  it('Latin and digits first (ignoring case), then Korean; ids stay with their names', () => {
    const rows = [
      { id: 1, name: '회' }, { id: 2, name: 'ml' }, { id: 3, name: '시린지' }, { id: 4, name: 'IU' },
      { id: 5, name: 'cc' }, { id: 6, name: 'Box' }, { id: 7, name: '기타' }, { id: 8, name: '10mg' },
    ];
    expect(buildItems(rows).map((i) => i.name)).toEqual(['10mg', 'Box', 'cc', 'IU', 'ml', '기타', '시린지', '회']);
    expect(buildItems(rows).find((i) => i.name === '시린지')?.id).toBe(3);
  });
  it('drops rows without a usable id or name', () => {
    expect(buildItems([{ id: 'x', name: 'a' }, { id: 2, name: '' }, { id: 3, name: null }, { id: 4, name: 'ok' }])).toEqual([{ id: 4, name: 'ok' }]);
  });
  it('statements are parametrised with named columns', () => {
    expect(listQuery().sql).not.toContain('*');
    expect(findByNameQuery('x"; DROP TABLE y;--')).toEqual({ sql: expect.stringContaining('WHERE name = ?'), values: ['x"; DROP TABLE y;--'] });
    expect(insertQuery('Box').values).toEqual(['Box']);
    expect(deactivateQuery(4)).toEqual({ sql: expect.stringContaining('SET is_active = 0 WHERE id = ?'), values: [4] });
  });
});
