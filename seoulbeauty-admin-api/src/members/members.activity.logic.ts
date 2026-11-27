/** Pure parts of the member activity tabs: applications (consultations), bookings, points ledger and the audit journal. Read only. */
import { reportDetailText } from '../common/report-detail';
import { num, positiveInt, Query, str } from '../hospitals/hospitals.logic';
import { statusOf } from './members.logic';

export const ACTIVITY_PER_PAGE = 20;
const MAX_PAGE = 100_000;
const SNIPPET = 300;

export interface Paging { page: number; perPage: number }
export const pagingOf = (src: Record<string, unknown>): Paging => ({ page: Math.min(positiveInt(src.page) || 1, MAX_PAGE), perPage: ACTIVITY_PER_PAGE });
const offsetOf = (p: Paging): number => (p.page - 1) * p.perPage;

export interface Page<T> { items: T[]; total: number; page: number; perPage: number }
const pageOf = <T>(p: Paging, total: number, items: T[]): Page<T> => ({ items, total, page: p.page, perPage: p.perPage });

/** Digits of a Korean mobile number in both spellings the table may hold (010xxxxxxxx / 8210xxxxxxxx); [] when it is not one. */
export function phoneForms(v: unknown): string[] {
  let d = str(v).replace(/[^0-9]/g, '');
  if (/^8210\d{8}$/.test(d)) d = '0' + d.slice(2);
  return /^010\d{8}$/.test(d) ? [d, '82' + d.slice(1)] : [];
}

const PHONE_SQL = "REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', '')";

/** Applications: the member's own (mb_id) plus ones made without signing in with the member's phone number. */
function consultationWhere(mbId: string, phone: unknown, withMbId: boolean): Query {
  const forms = phoneForms(phone);
  const parts: string[] = [];
  const values: unknown[] = [];
  if (withMbId) { parts.push('mb_id = ?'); values.push(mbId); }
  if (forms.length) { parts.push(`${PHONE_SQL} IN (?, ?)`); values.push(...forms); }
  return { sql: parts.length ? `(${parts.join(' OR ')})` : '1 = 0', values };
}

export const consultationCountQuery = (mbId: string, phone: unknown, withMbId = true): Query => {
  const w = consultationWhere(mbId, phone, withMbId);
  return { sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_consultations WHERE ${w.sql}`, values: w.values };
};
export const consultationListQuery = (mbId: string, phone: unknown, p: Paging, withMbId = true): Query => {
  const w = consultationWhere(mbId, phone, withMbId);
  return {
    sql: `SELECT id, hospital_id, hospital_name, consultation_type, message, status, created_at FROM gangnamunni_consultations WHERE ${w.sql} ORDER BY created_at DESC, id DESC LIMIT ?, ?`,
    values: [...w.values, offsetOf(p), p.perPage],
  };
};
export const buildConsultations = (p: Paging, total: number, rows: Record<string, unknown>[]): Page<Record<string, unknown>> =>
  pageOf(p, total, rows.map((r) => ({
    id: num(r.id), hospitalId: num(r.hospital_id) || null, hospitalName: str(r.hospital_name), type: str(r.consultation_type),
    message: str(r.message).slice(0, SNIPPET), status: str(r.status), createdAt: str(r.created_at),
  })));

export const bookingCountQuery = (mbId: string): Query => ({ sql: 'SELECT COUNT(*) AS cnt FROM gangnamunni_bookings WHERE mb_id = ?', values: [mbId] });
export const bookingListQuery = (mbId: string, p: Paging): Query => ({
  sql: `SELECT id, hospital_id, hospital_name, treatment, price, booking_date, booking_time, status, cancel_reason, created_at
        FROM gangnamunni_bookings WHERE mb_id = ? ORDER BY created_at DESC, id DESC LIMIT ?, ?`,
  values: [mbId, offsetOf(p), p.perPage],
});
export const buildBookings = (p: Paging, total: number, rows: Record<string, unknown>[]): Page<Record<string, unknown>> =>
  pageOf(p, total, rows.map((r) => ({
    id: num(r.id), hospitalId: num(r.hospital_id) || null, hospitalName: str(r.hospital_name), treatment: str(r.treatment), price: num(r.price),
    bookingDate: str(r.booking_date), bookingTime: str(r.booking_time), status: str(r.status), cancelReason: str(r.cancel_reason), createdAt: str(r.created_at),
  })));

const CREDIT = ['earn', 'refund', 'admin_add', 'admin'];
const DEBIT = ['use', 'expire', 'admin_sub', 'earn_cancel'];

export const pointBalanceQuery = (no: number): Query => ({ sql: 'SELECT balance FROM gangnamunni_points WHERE member_id = ? ORDER BY id DESC LIMIT 1', values: [no] });
export const pointTotalsQuery = (no: number): Query => ({ sql: 'SELECT type, SUM(amount) AS total FROM gangnamunni_points WHERE member_id = ? GROUP BY type', values: [no] });
export const pointCountQuery = (no: number): Query => ({ sql: 'SELECT COUNT(*) AS cnt FROM gangnamunni_points WHERE member_id = ?', values: [no] });
export const pointListQuery = (no: number, p: Paging): Query => ({
  sql: `SELECT id, type, amount, balance, reason, reference_type, reference_id, expires_at, created_at
        FROM gangnamunni_points WHERE member_id = ? ORDER BY created_at DESC, id DESC LIMIT ?, ?`,
  values: [no, offsetOf(p), p.perPage],
});

export interface PointTotals { earned: number; used: number; expired: number; refunded: number; adjustedIn: number; adjustedOut: number }
export function buildPointTotals(rows: Record<string, unknown>[]): PointTotals {
  const by: Record<string, number> = {};
  for (const r of rows) by[str(r.type)] = num(r.total);
  return {
    earned: (by.earn || 0) - (by.earn_cancel || 0), used: by.use || 0, expired: by.expire || 0, refunded: by.refund || 0,
    adjustedIn: (by.admin_add || 0) + (by.admin || 0), adjustedOut: by.admin_sub || 0,
  };
}

export interface PointsAnswer extends Page<Record<string, unknown>> { balance: number; totals: PointTotals }
export function buildPoints(p: Paging, total: number, balanceRows: Record<string, unknown>[], totalRows: Record<string, unknown>[], rows: Record<string, unknown>[]): PointsAnswer {
  return {
    ...pageOf(p, total, rows.map((r) => ({
      id: num(r.id), type: str(r.type), direction: CREDIT.includes(str(r.type)) ? 'in' : DEBIT.includes(str(r.type)) ? 'out' : 'other',
      amount: num(r.amount), balance: r.balance === null || r.balance === undefined ? null : num(r.balance), reason: str(r.reason),
      referenceType: str(r.reference_type), referenceId: str(r.reference_id), expiresAt: str(r.expires_at), createdAt: str(r.created_at),
    }))),
    // The ledger is what the app shows; g5_member.mb_point is the old Gnuboard number, it is not used anywhere.
    balance: balanceRows.length ? num(balanceRows[0].balance) : 0, totals: buildPointTotals(totalRows),
  };
}

export const historyCountQuery = (no: number): Query => ({ sql: 'SELECT COUNT(*) AS cnt FROM gangnamunni_member_audit WHERE member_no = ?', values: [no] });
export const historyListQuery = (no: number, p: Paging): Query => ({
  sql: `SELECT id, admin_username, admin_role, action, before_value, after_value, ip, created_at
        FROM gangnamunni_member_audit WHERE member_no = ? ORDER BY created_at DESC, id DESC LIMIT ?, ?`,
  values: [no, offsetOf(p), p.perPage],
});
/** A password reset never carries a value (the writer stores null), and none is returned even if one were stored. */
export const buildHistory = (p: Paging, total: number, rows: Record<string, unknown>[]): Page<Record<string, unknown>> =>
  pageOf(p, total, rows.map((r) => {
    const secret = str(r.action) === 'password_reset';
    return {
      id: num(r.id), adminUsername: str(r.admin_username), adminRole: str(r.admin_role), action: str(r.action),
      before: secret || r.before_value === null ? null : str(r.before_value), after: secret || r.after_value === null ? null : str(r.after_value),
      ip: str(r.ip), createdAt: str(r.created_at),
    };
  }));

/** Reports shown under one blocked member (the newest few; the real number comes with the item). */
export const BLOCK_REPORTS_PER_USER = 5;

/** Members this member blocked in the app (blocker_id holds the blocker's login id, newest first). */
export const blockCountQuery = (mbId: string): Query => ({ sql: 'SELECT COUNT(*) AS cnt FROM gangnamunni_blocked_users WHERE blocker_id = ?', values: [mbId] });
export const blockListQuery = (mbId: string, p: Paging): Query => ({
  sql: `SELECT id, blocked_id, blocked_name, reason, created_at FROM gangnamunni_blocked_users WHERE blocker_id = ? ORDER BY created_at DESC, id DESC LIMIT ?, ?`,
  values: [mbId, offsetOf(p), p.perPage],
});
/** The blocked members as they are now (name, state); a member who was removed from the table has no row. */
export const blockedMembersQuery = (ids: string[]): Query => ({
  sql: `SELECT mb_no, mb_id, mb_nick, mb_name, mb_leave_date, mb_intercept_date FROM g5_member WHERE mb_id IN (${ids.map(() => '?').join(', ')})`,
  values: ids,
});
/** What the blocker reported about those members: this is the "why" (reports carry the reason, the content and its state). */
export const blockReportsQuery = (reporterNo: number, targetNos: number[]): Query => ({
  sql: `SELECT id, reported_user_id, content_type, content_id, content_snippet, reason, reason_detail, status, created_at
        FROM gangnamunni_content_reports WHERE reporter_id = ? AND reported_user_id IN (${targetNos.map(() => '?').join(', ')})
        ORDER BY created_at DESC, id DESC LIMIT 500`,
  values: [reporterNo, ...targetNos],
});
export const blockReportCountsQuery = (reporterNo: number, targetNos: number[]): Query => ({
  sql: `SELECT reported_user_id, COUNT(*) AS cnt FROM gangnamunni_content_reports WHERE reporter_id = ? AND reported_user_id IN (${targetNos.map(() => '?').join(', ')}) GROUP BY reported_user_id`,
  values: [reporterNo, ...targetNos],
});

export function buildBlocks(
  p: Paging, total: number, rows: Record<string, unknown>[], members: Record<string, unknown>[],
  reports: Record<string, unknown>[], counts: Record<string, unknown>[],
): Page<Record<string, unknown>> {
  const byId = new Map(members.map((m) => [str(m.mb_id), m]));
  const countOf = new Map(counts.map((c) => [num(c.reported_user_id), num(c.cnt)]));
  const reportsOf = new Map<number, Record<string, unknown>[]>();
  for (const r of reports) {
    const key = num(r.reported_user_id);
    const list = reportsOf.get(key) || [];
    if (list.length < BLOCK_REPORTS_PER_USER) {
      list.push({
        id: num(r.id), contentType: str(r.content_type), contentId: num(r.content_id), snippet: str(r.content_snippet).slice(0, SNIPPET),
        reason: str(r.reason), detail: reportDetailText(r.reason_detail, SNIPPET), status: str(r.status), createdAt: str(r.created_at),
      });
    }
    reportsOf.set(key, list);
  }
  return pageOf(p, total, rows.map((r) => {
    const m = byId.get(str(r.blocked_id));
    const no = m ? num(m.mb_no) : 0;
    return {
      id: num(r.id), userNo: no || null, userId: str(r.blocked_id),
      name: m ? str(m.mb_nick) || str(m.mb_name) || str(r.blocked_name) : str(r.blocked_name),
      status: m ? statusOf(m) : null, reason: str(r.reason), blockedAt: str(r.created_at),
      reportCount: countOf.get(no) || 0, reports: reportsOf.get(no) || [],
    };
  }));
}
