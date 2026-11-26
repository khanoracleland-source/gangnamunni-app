import { buildSummary, countQuery, detailQuery, kstDays, listQuery, normalizeListQuery, parseMemberId, statusOf, summaryQuery, toDetail, toListItem } from './members.logic';

describe('members logic', () => {
  it('normalizes the list state and falls back to the defaults', () => {
    expect(normalizeListQuery({})).toEqual({ search: '', status: 'all', sort: 'joined', order: 'desc', page: 1, perPage: 20 });
    expect(normalizeListQuery({ search: '  kim ', status: 'blocked', sort: 'point', order: 'asc', page: '3', perPage: '50' })).toEqual({
      search: 'kim', status: 'blocked', sort: 'point', order: 'asc', page: 3, perPage: 50,
    });
    expect(normalizeListQuery({ status: 'x', sort: 'mb_password', order: 'sideways', page: '-4', perPage: '7' })).toMatchObject({
      status: 'all', sort: 'joined', order: 'desc', page: 1, perPage: 20,
    });
    expect(normalizeListQuery({ search: 'a'.repeat(500) }).search).toHaveLength(100);
    expect(normalizeListQuery({ sort: 'constructor' }).sort).toBe('joined');
    expect(normalizeListQuery({ page: '99999999' }).page).toBe(100_000);
  });

  it('only ever puts a whitelisted column into ORDER BY', () => {
    const q = listQuery(normalizeListQuery({ sort: 'name; DROP TABLE g5_member', order: 'asc; --' }));
    expect(q.sql).toContain('ORDER BY mb_datetime DESC, mb_no DESC');
    expect(q.sql).not.toMatch(/DROP/i);
    expect(listQuery(normalizeListQuery({ sort: 'lastLogin', order: 'asc' })).sql).toContain('ORDER BY mb_today_login ASC, mb_no DESC');
  });

  it('keeps search text out of the SQL and escapes LIKE wildcards', () => {
    const q = listQuery(normalizeListQuery({ search: "50%_' OR 1=1 --", page: '2', perPage: '10' }));
    expect(q.sql).not.toContain('OR 1=1');
    expect(q.sql.match(/LIKE \?/g)).toHaveLength(5);
    expect(q.values.slice(0, 5)).toEqual(Array(5).fill("%50\\%\\_' OR 1=1 --%"));
    expect(q.values.slice(5)).toEqual([10, 10]);
    expect(countQuery(normalizeListQuery({ search: 'kim' })).values).toHaveLength(5);
  });

  it('makes the three states exclusive in SQL and in the answer', () => {
    const where = (status: string) => listQuery(normalizeListQuery({ status })).sql;
    expect(where('active')).toContain("mb_leave_date = '') AND (mb_intercept_date IS NULL OR mb_intercept_date = '')");
    expect(where('blocked')).toContain("mb_intercept_date IS NOT NULL AND mb_intercept_date <> ''");
    expect(where('left')).toContain("mb_leave_date IS NOT NULL AND mb_leave_date <> ''");
    expect(statusOf({ mb_leave_date: '', mb_intercept_date: '' })).toBe('active');
    expect(statusOf({ mb_leave_date: '', mb_intercept_date: '20261126' })).toBe('blocked');
    expect(statusOf({ mb_leave_date: '20261001', mb_intercept_date: '20261126' })).toBe('left');
    expect(statusOf({ mb_leave_date: null, mb_intercept_date: null })).toBe('active');
  });

  it('parses member ids strictly', () => {
    expect(parseMemberId('42')).toBe(42);
    for (const bad of ['0', '-1', '01', 'abc', '1 OR 1', '12345678901', '']) expect(parseMemberId(bad)).toBe(0);
    expect(parseMemberId(undefined)).toBe(0);
  });

  it('counts "today" by the Korean calendar day', () => {
    expect(kstDays(new Date('2026-11-25T15:30:00Z'))).toEqual({ today: '2026-11-26', tomorrow: '2026-11-27' });
    expect(kstDays(new Date('2026-11-26T14:59:59Z')).today).toBe('2026-11-26');
    expect(summaryQuery(new Date('2026-11-25T15:30:00Z')).values).toEqual(['2026-11-26', '2026-11-27', '2026-11-26', '2026-11-27']);
    expect(buildSummary({ total: '10', active: '7', blocked: '2', today_login: '3', today_join: '1' })).toEqual({ total: 10, active: 7, blocked: 2, todayLogin: 3, todayJoin: 1 });
    expect(buildSummary(undefined).total).toBe(0);
  });

  it('shapes a member and reads both stored date formats', () => {
    const row = {
      mb_no: 5, mb_id: 'kim01', mb_name: '김서연', mb_nick: 'seo', mb_email: 'a@b.kr', mb_hp: '010-1111-2222', mb_point: 1200,
      mb_datetime: '2026-01-02 03:04:05', mb_today_login: '0000-00-00 00:00:00', mb_leave_date: '', mb_intercept_date: '2026-11-26',
      mb_tel: '', mb_sex: 'F', mb_birth: '19900131', mb_addr1: 'a1', mb_addr2: 'a2', mb_login_ip: '1.2.3.4', mb_sms: 1, mb_mailling: 0, mb_memo: 'm',
      mb_password: 'sha256:secret',
    };
    expect(toListItem(row)).toEqual({
      no: 5, id: 'kim01', name: '김서연', nick: 'seo', email: 'a@b.kr', phone: '010-1111-2222', point: 1200,
      joinedAt: '2026-01-02 03:04:05', lastLoginAt: null, status: 'blocked',
    });
    const d = toDetail(row);
    expect(d).toMatchObject({ sex: 'F', birth: '1990-01-31', blockedDate: '2026-11-26', leftDate: null, smsConsent: true, mailConsent: false, memo: 'm' });
    expect(JSON.stringify(d)).not.toContain('secret');
    expect(toDetail({ ...row, mb_sex: '', mb_birth: '' })).toMatchObject({ sex: null, birth: null });
  });

  it('never selects the password hash', () => {
    expect(listQuery(normalizeListQuery({})).sql).not.toContain('mb_password');
    expect(detailQuery(1).sql).not.toContain('mb_password');
    expect(detailQuery(1).sql).not.toContain('*');
  });
});
