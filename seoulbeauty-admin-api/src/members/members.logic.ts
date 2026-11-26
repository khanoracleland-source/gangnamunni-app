/** Pure parts of the members page (g5_member): list state, SQL and the shape of the answers. Read only; the password hash is never selected. */
import { likeContains, num, positiveInt, Query, str } from '../hospitals/hospitals.logic';

export const PER_PAGE_CHOICES = [10, 20, 50, 100];
export const DEFAULT_PER_PAGE = 20;
export const MAX_PAGE = 100_000;
export const MAX_SEARCH = 100;

export type MemberStatus = 'all' | 'active' | 'blocked' | 'left';
export type MemberSort = 'joined' | 'lastLogin' | 'no' | 'id' | 'name' | 'point';
export type SortOrder = 'asc' | 'desc';

/** API sort key -> column. The only way a column name ever reaches the SQL text. */
const SORT_COLUMNS: Record<MemberSort, string> = {
  joined: 'mb_datetime',
  lastLogin: 'mb_today_login',
  no: 'mb_no',
  id: 'mb_id',
  name: 'mb_name',
  point: 'mb_point',
};
const isSort = (v: string): v is MemberSort => Object.prototype.hasOwnProperty.call(SORT_COLUMNS, v);

export interface MemberListQuery {
  search: string;
  status: MemberStatus;
  sort: MemberSort;
  order: SortOrder;
  page: number;
  perPage: number;
}

const one = (v: unknown): string => (typeof v === 'string' ? v : typeof v === 'number' ? String(v) : '');

/** The list state from the query string. Anything missing or invalid becomes the default. */
export function normalizeListQuery(q: Record<string, unknown> | undefined | null): MemberListQuery {
  const src = q ?? {};
  const status = one(src.status);
  const sort = one(src.sort);
  const perPage = positiveInt(src.perPage);
  return {
    search: one(src.search).trim().slice(0, MAX_SEARCH),
    status: status === 'active' || status === 'blocked' || status === 'left' ? status : 'all',
    sort: isSort(sort) ? sort : 'joined',
    order: one(src.order) === 'asc' ? 'asc' : 'desc',
    page: Math.min(MAX_PAGE, Math.max(1, positiveInt(src.page))),
    perPage: PER_PAGE_CHOICES.includes(perPage) ? perPage : DEFAULT_PER_PAGE,
  };
}

/** Member number of /v1/members/:id, or 0 when it is not a plain positive integer. */
export const parseMemberId = (raw: unknown): number => (typeof raw === 'string' && /^[1-9]\d{0,9}$/.test(raw) ? positiveInt(raw) : 0);

// The two date columns hold '' until set (a NULL counts as not set).
const LEFT_EMPTY = `(mb_leave_date IS NULL OR mb_leave_date = '')`;
const LEFT_SET = `(mb_leave_date IS NOT NULL AND mb_leave_date <> '')`;
const BLOCK_EMPTY = `(mb_intercept_date IS NULL OR mb_intercept_date = '')`;
const BLOCK_SET = `(mb_intercept_date IS NOT NULL AND mb_intercept_date <> '')`;

/** The three states are exclusive: a member who has left is "left" even if a block date is still there. */
const ACTIVE = `${LEFT_EMPTY} AND ${BLOCK_EMPTY}`;
const BLOCKED = `${LEFT_EMPTY} AND ${BLOCK_SET}`;

function listWhere(q: MemberListQuery): Query {
  const parts: string[] = [];
  const values: unknown[] = [];
  if (q.search) {
    const like = likeContains(q.search);
    parts.push('(mb_id LIKE ? OR mb_name LIKE ? OR mb_nick LIKE ? OR mb_email LIKE ? OR mb_hp LIKE ?)');
    values.push(like, like, like, like, like);
  }
  if (q.status === 'active') parts.push(`(${ACTIVE})`);
  else if (q.status === 'blocked') parts.push(`(${BLOCKED})`);
  else if (q.status === 'left') parts.push(LEFT_SET);
  return { sql: parts.length ? parts.join(' AND ') : '1=1', values };
}

const LIST_COLUMNS = 'mb_no, mb_id, mb_name, mb_nick, mb_email, mb_hp, mb_point, mb_datetime, mb_today_login, mb_leave_date, mb_intercept_date';
const DETAIL_COLUMNS = `${LIST_COLUMNS}, mb_tel, mb_sex, mb_birth, mb_addr1, mb_addr2, mb_login_ip, mb_sms, mb_mailling, mb_memo`;

export function listQuery(q: MemberListQuery): Query {
  const w = listWhere(q);
  const dir = q.order === 'asc' ? 'ASC' : 'DESC';
  return {
    sql: `SELECT ${LIST_COLUMNS} FROM g5_member WHERE ${w.sql} ORDER BY ${SORT_COLUMNS[q.sort]} ${dir}, mb_no DESC LIMIT ?, ?`,
    values: [...w.values, (q.page - 1) * q.perPage, q.perPage],
  };
}

export function countQuery(q: MemberListQuery): Query {
  const w = listWhere(q);
  return { sql: `SELECT COUNT(*) AS cnt FROM g5_member WHERE ${w.sql}`, values: w.values };
}

export const detailQuery = (id: number): Query => ({ sql: `SELECT ${DETAIL_COLUMNS} FROM g5_member WHERE mb_no = ? LIMIT 1`, values: [id] });

/** Calendar days in Korea time: today and tomorrow as YYYY-MM-DD (range bounds, so the date columns stay usable by an index). */
export function kstDays(now: Date = new Date()): { today: string; tomorrow: string } {
  const k = new Date(now.getTime() + 9 * 3_600_000);
  return { today: k.toISOString().slice(0, 10), tomorrow: new Date(k.getTime() + 86_400_000).toISOString().slice(0, 10) };
}

/** Counters of the whole table; not affected by the list filters. */
export function summaryQuery(now: Date = new Date()): Query {
  const { today, tomorrow } = kstDays(now);
  const count = (cond: string) => `COALESCE(SUM(CASE WHEN ${cond} THEN 1 ELSE 0 END), 0)`;
  return {
    sql: `SELECT COUNT(*) AS total,
       ${count(ACTIVE)} AS active,
       ${count(BLOCKED)} AS blocked,
       ${count('mb_today_login >= ? AND mb_today_login < ?')} AS today_login,
       ${count('mb_datetime >= ? AND mb_datetime < ?')} AS today_join
     FROM g5_member`,
    values: [today, tomorrow, today, tomorrow],
  };
}

// ---- the shape of the answers ----------------------------------------------------------------------------------

type Row = Record<string, unknown>;

export interface MemberListItem {
  no: number;
  id: string;
  name: string;
  nick: string;
  email: string;
  phone: string;
  point: number;
  joinedAt: string | null;
  lastLoginAt: string | null;
  status: 'active' | 'blocked' | 'left';
}
export interface MemberDetail extends MemberListItem {
  tel: string;
  sex: 'M' | 'F' | null;
  birth: string | null;
  addr1: string;
  addr2: string;
  loginIp: string;
  smsConsent: boolean;
  mailConsent: boolean;
  memo: string;
  blockedDate: string | null;
  leftDate: string | null;
}
export interface MemberList {
  total: number;
  page: number;
  perPage: number;
  items: MemberListItem[];
}
export interface MemberSummary {
  total: number;
  active: number;
  blocked: number;
  todayLogin: number;
  todayJoin: number;
}

/** A DATETIME as stored, or null when it is empty or the zero date. */
const dateTimeOrNull = (v: unknown): string | null => {
  const s = str(v).trim();
  return s === '' || s.startsWith('0000') ? null : s;
};
/** A YYYYMMDD or YYYY-MM-DD value (the two formats found in the table) as YYYY-MM-DD; null when empty or not a date. */
const ymdOrNull = (v: unknown): string | null => {
  const d = str(v).replace(/\D/g, '');
  return d.length === 8 && !d.startsWith('0000') ? `${d.slice(0, 4)}-${d.slice(4, 6)}-${d.slice(6, 8)}` : null;
};

export function statusOf(r: Row): 'active' | 'blocked' | 'left' {
  if (str(r.mb_leave_date).trim() !== '') return 'left';
  if (str(r.mb_intercept_date).trim() !== '') return 'blocked';
  return 'active';
}

export const toListItem = (r: Row): MemberListItem => ({
  no: num(r.mb_no),
  id: str(r.mb_id),
  name: str(r.mb_name),
  nick: str(r.mb_nick),
  email: str(r.mb_email),
  phone: str(r.mb_hp),
  point: num(r.mb_point),
  joinedAt: dateTimeOrNull(r.mb_datetime),
  lastLoginAt: dateTimeOrNull(r.mb_today_login),
  status: statusOf(r),
});

export const toDetail = (r: Row): MemberDetail => {
  const sex = str(r.mb_sex);
  const birth = str(r.mb_birth).trim();
  return {
    ...toListItem(r),
    tel: str(r.mb_tel),
    sex: sex === 'M' || sex === 'F' ? sex : null,
    birth: ymdOrNull(birth) ?? (birth === '' ? null : birth),
    addr1: str(r.mb_addr1),
    addr2: str(r.mb_addr2),
    loginIp: str(r.mb_login_ip),
    smsConsent: num(r.mb_sms) === 1,
    mailConsent: num(r.mb_mailling) === 1,
    memo: str(r.mb_memo),
    blockedDate: ymdOrNull(r.mb_intercept_date),
    leftDate: ymdOrNull(r.mb_leave_date),
  };
};

export const buildList = (q: MemberListQuery, total: number, rows: Row[]): MemberList => ({
  total,
  page: q.page,
  perPage: q.perPage,
  items: rows.map(toListItem),
});

export const buildSummary = (r: Row | undefined): MemberSummary => ({
  total: num(r?.total),
  active: num(r?.active),
  blocked: num(r?.blocked),
  todayLogin: num(r?.today_login),
  todayJoin: num(r?.today_join),
});
