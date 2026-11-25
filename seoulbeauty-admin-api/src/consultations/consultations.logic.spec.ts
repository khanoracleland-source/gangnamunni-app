import { listQuery, parseListQuery, scopeClause, splitMessage, toItem, toStats, PER_PAGE } from './consultations.logic';

describe('consultations logic', () => {
  describe('parseListQuery', () => {
    it('empty query is page 1 with no filters', () => {
      expect(parseListQuery({})).toEqual({ hospitalId: 0, status: '', q: '', page: 1 });
    });
    it('rejects an unknown status, a bad hospital and a bad page', () => {
      expect(parseListQuery({ status: 'nope' })).toEqual({ error: 'invalid_query', field: 'status' });
      expect(parseListQuery({ hospital_id: '0' })).toEqual({ error: 'invalid_query', field: 'hospital_id' });
      expect(parseListQuery({ hospital_id: '1; DROP' })).toEqual({ error: 'invalid_query', field: 'hospital_id' });
      expect(parseListQuery({ page: '0' })).toEqual({ error: 'invalid_query', field: 'page' });
      expect(parseListQuery({ page: '100001' })).toEqual({ error: 'invalid_query', field: 'page' });
    });
    it('accepts the five statuses and trims the search', () => {
      for (const st of ['pending', 'approved', 'completed', 'rejected', 'contact_failed']) expect(parseListQuery({ status: st })).toMatchObject({ status: st });
      expect(parseListQuery({ q: '  김\n민지  ' })).toMatchObject({ q: '김 민지' });
      expect((parseListQuery({ q: 'a'.repeat(200) }) as { q: string }).q).toHaveLength(60);
    });
  });

  describe('scopeClause', () => {
    it('all: no hospital limit, one hospital when chosen', () => {
      expect(scopeClause({ scope: 'all', hospitalIds: [] }, 0)).toEqual({ sql: '1 = 1', values: [] });
      expect(scopeClause({ scope: 'all', hospitalIds: [] }, 5)).toEqual({ sql: 'c.hospital_id = ?', values: [5] });
    });
    it('own: always limited to its hospitals; a chosen hospital only narrows it', () => {
      expect(scopeClause({ scope: 'own', hospitalIds: [12] }, 0)).toEqual({ sql: 'c.hospital_id IN (?)', values: [12] });
      expect(scopeClause({ scope: 'own', hospitalIds: [12] }, 99)).toEqual({ sql: 'c.hospital_id IN (?) AND c.hospital_id = ?', values: [12, 99] });
    });
    it('own without a hospital sees nothing, never everything', () => {
      expect(scopeClause({ scope: 'own', hospitalIds: [] }, 0)).toEqual({ sql: '1 = 0', values: [] });
    });
  });

  it('listQuery is parameterised and newest first, with search values as parameters', () => {
    const q = listQuery({ scope: 'own', hospitalIds: [12] }, { hospitalId: 0, status: 'pending', q: "50%_'--", page: 3 });
    expect(q.sql).toContain('ORDER BY c.created_at DESC, c.id DESC LIMIT ? OFFSET ?');
    expect(q.sql).not.toContain("'--");
    expect(q.values).toEqual([12, 'pending', "%50\\%\\_'--%", "%50\\%\\_'--%", "%50\\%\\_'--%", PER_PAGE, 2 * PER_PAGE]);
  });

  describe('splitMessage', () => {
    it('takes the leading form lines out as tags and keeps the member text', () => {
      const raw = '[관심 시술] 보톡스\n[연락 가능 시간] 오후\n[방문 희망 일시] 2026-12-01 14:00\n안녕하세요\n문의드려요';
      expect(splitMessage(raw)).toEqual({ tags: ['[관심 시술] 보톡스', '[연락 가능 시간] 오후', '[방문 희망 일시] 2026-12-01 14:00'], text: '안녕하세요\n문의드려요' });
    });
    it('a tag in the middle of the text stays text; plain text has no tags', () => {
      expect(splitMessage('hello\n[관심 시술] x')).toEqual({ tags: [], text: 'hello\n[관심 시술] x' });
      expect(splitMessage(null)).toEqual({ tags: [], text: '' });
    });
    it('a message made of tags only has empty text', () => {
      expect(splitMessage('[상담 방법] 전화')).toEqual({ tags: ['[상담 방법] 전화'], text: '' });
    });
  });

  it('toItem cuts a long text, counts photos and survives broken photo JSON', () => {
    const item = toItem({ id: '4', hospital_id: 2, hospital_name: 'H', consultation_type: 'kakao', name: 'n', phone: '010', message: 'x'.repeat(500), photo_urls: '["a","b"]', status: 'pending', created_at: '2026-11-25 10:00:00' });
    expect(item).toMatchObject({ id: 4, hospitalId: 2, type: 'kakao', photoCount: 2, status: 'pending' });
    expect(item.preview).toHaveLength(200);
    expect(toItem({ id: 1, photo_urls: 'not json', message: '' }).photoCount).toBe(0);
  });

  it('toStats counts every status and keeps the oldest waiting date', () => {
    expect(toStats([
      { status: 'pending', cnt: '3', oldest: '2026-11-20 09:00:00' }, { status: 'approved', cnt: 2, oldest: null },
      { status: 'weird', cnt: 1, oldest: null }, { status: 'contact_failed', cnt: 1, oldest: null },
    ])).toEqual({ total: 7, pending: 3, approved: 2, completed: 0, rejected: 0, contact_failed: 1, oldestPendingAt: '2026-11-20 09:00:00' });
    expect(toStats([]).oldestPendingAt).toBeNull();
  });
});
