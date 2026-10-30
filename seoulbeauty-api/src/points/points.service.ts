import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, DbConn, DbPool } from '../db/db.module';
import { fifoExpiring, logItem, PointLog } from './points.logic';
import { pointsVisible } from '../legal/legal.logic';

const n = (v: unknown) => Number(v ?? 0) || 0;
const POINTS_SCHEMA_VERSION = 3; // includes/points_ledger.php POINTS_SCHEMA_VERSION

/**
 * The only piece of includes/points_ledger.php the member API needs: taking back an `earn` (points_revoke_earn ->
 * points_txn('earn_cancel') -> points_lots_apply debit). Everything runs on one dedicated connection because
 * GET_LOCK is connection-scoped, and inside a transaction so the ledger row and the lot changes land together.
 * Returns what happened; it never throws (a ledger problem must not fail the member's request).
 */
@Injectable()
export class PointsService {
  private readonly log = new Logger(PointsService.name);
  constructor(@Inject(DB) private readonly db: DbPool) {}

  private async settings(q: DbConn | DbPool): Promise<Record<string, string>> {
    const [rows] = (await q.query('SELECT skey, sval FROM gangnamunni_point_settings')) as any;
    const out: Record<string, string> = {};
    for (const r of rows as any[]) out[String(r.skey)] = String(r.sval ?? '');
    return out;
  }

  /** All point settings (skey -> sval); {} when the table is missing. */
  async allSettings(): Promise<Record<string, string>> {
    try { return await this.settings(this.db); } catch (err) { this.log.warn(`point settings unreadable: ${(err as Error).message}`); return {}; }
  }

  /** points_v2_on(key): schema v3+, points on, v2 policy on, then the key itself (lots_ready extra for the two job keys). */
  static v2On(s: Record<string, string>, key: string): boolean {
    if (n(s.schema_version) < POINTS_SCHEMA_VERSION || n(s.v2_schema_version) < 1 || n(s.enabled) !== 1) return false;
    if (key !== 'v2_policy_enabled' && n(s.v2_policy_enabled) !== 1) return false;
    if ((key === 'v2_expire_job_enabled' || key === 'v2_point_use_enabled') && n(s.lots_ready) !== 1) return false;
    return n(s[key]) === 1;
  }

  /** points_v2_int(): integer setting with a default. */
  static int(s: Record<string, string>, key: string, def: number): number {
    const v = s[key];
    return v === undefined || v === '' || !Number.isFinite(Number(v)) ? def : Math.trunc(Number(v));
  }

  async v2PolicyOn(): Promise<boolean> { return PointsService.v2On(await this.allSettings(), 'v2_policy_enabled'); }

  /** points_balance_safe(): the last ledger row's balance snapshot (0 on any failure). */
  async balance(memberNo: number): Promise<number> {
    try {
      const [r] = (await this.db.query('SELECT balance FROM gangnamunni_points WHERE member_id = ? ORDER BY id DESC LIMIT 1', [memberNo])) as any;
      return n((r as any[])[0]?.balance);
    } catch (err) { this.log.warn(`balance unavailable: ${(err as Error).message}`); return 0; }
  }

  /** my_points_policy.php: the 소멸 tab's "N일" (v2 policy on: v2_expire_days/90, else expire_days/365). */
  async policy(): Promise<{ visible: boolean; expireDays: number }> {
    if (!pointsVisible()) return { visible: false, expireDays: 0 };
    const s = await this.allSettings();
    const v2 = PointsService.v2On(s, 'v2_policy_enabled');
    return { visible: true, expireDays: v2 ? PointsService.int(s, 'v2_expire_days', 90) : PointsService.int(s, 'expire_days', 365) };
  }

  /** my_points.php: balance, this month's totals, the nearest expiry and the last 50 rows. Hidden (visible:false) while the menu is paused. */
  async mine(memberNo: number): Promise<{ visible: false } | { visible: true; balance: number; expiry: { date: string; amount: number } | null; monthEarn: number; monthUse: number; logs: PointLog[]; hasMore: boolean }> {
    if (!pointsVisible()) return { visible: false };
    const balance = await this.balance(memberNo);
    let logs: PointLog[] = [];
    try {
      const [rows] = (await this.db.query('SELECT * FROM gangnamunni_points WHERE member_id = ? ORDER BY created_at DESC, id DESC LIMIT 50', [memberNo])) as any;
      logs = (rows as any[]).map(logItem);
    } catch (err) { this.log.warn(`ledger unavailable: ${(err as Error).message}`); }
    let monthEarn = 0, monthUse = 0;
    let expiry: { date: string; amount: number } | null = null;
    try {
      const [sr] = (await this.db.query(
        `SELECT COALESCE(SUM(CASE WHEN type IN ('earn','refund','admin_add','admin') THEN amount ELSE 0 END),0) AS e,
                COALESCE(SUM(CASE WHEN type IN ('use','expire','admin_sub','earn_cancel') THEN amount ELSE 0 END),0) AS u
         FROM gangnamunni_points WHERE member_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')`, [memberNo])) as any;
      monthEarn = n((sr as any[])[0]?.e); monthUse = n((sr as any[])[0]?.u);
      const s = await this.allSettings();
      if (n(s.v2_schema_version) >= 1 && n(s.lots_ready) === 1) {
        // points_next_expiry(): lots still holding points
        const [lr] = (await this.db.query(
          `SELECT expires_at, SUM(remaining) AS amt FROM gangnamunni_point_lots
           WHERE member_id = ? AND remaining > 0 AND expires_at IS NOT NULL AND expires_at >= CURDATE()
           GROUP BY expires_at ORDER BY expires_at LIMIT 1`, [memberNo])) as any;
        const l = (lr as any[])[0];
        if (l && n(l.amt) > 0 && l.expires_at) expiry = { date: String(l.expires_at).slice(0, 10), amount: n(l.amt) };
      } else if (balance > 0) {
        const [nr] = (await this.db.query(
          `SELECT MIN(expires_at) AS d FROM gangnamunni_points
           WHERE member_id = ? AND type IN ('earn','refund','admin_add','admin') AND expires_at IS NOT NULL AND expires_at >= CURDATE()`, [memberNo])) as any;
        const d = (nr as any[])[0]?.d ? String((nr as any[])[0].d).slice(0, 10) : '';
        if (d) {
          const [ar] = (await this.db.query(
            `SELECT COALESCE(SUM(CASE WHEN type IN ('earn','refund','admin_add','admin') AND expires_at IS NOT NULL AND expires_at <= ? THEN amount ELSE 0 END),0) AS c,
                    COALESCE(SUM(CASE WHEN type IN ('use','expire','admin_sub','earn_cancel') THEN amount ELSE 0 END),0) AS u
             FROM gangnamunni_points WHERE member_id = ?`, [d, memberNo])) as any;
          const amount = fifoExpiring(balance, n((ar as any[])[0]?.c), n((ar as any[])[0]?.u));
          if (amount > 0) expiry = { date: d, amount };
        }
      }
    } catch (err) { this.log.warn(`points summary unavailable: ${(err as Error).message}`); }
    return { visible: true, balance, expiry, monthEarn, monthUse, logs, hasMore: logs.length >= 50 };
  }

  /** points_v2_review_revoke_all(): one revoke per reference type; each failure is logged on its own. */
  async revokeReviewEarnings(reviewId: number, reason = '후기 삭제 회수'): Promise<void> {
    if (!(await this.v2PolicyOn())) return;
    for (const refType of ['review', 'review_photo', 'review_receipt', 'review_first']) {
      try { await this.revokeEarn(refType, reviewId, reason); } catch (err) { this.log.error(`revoke ${refType} #${reviewId}: ${(err as Error).message}`); }
    }
  }

  /** points_revoke_earn(): finds the earn row for (ref_type, ref_id) and books an earn_cancel for the same amount. */
  async revokeEarn(refType: string, refId: number, reason: string): Promise<'revoked' | 'skipped' | 'failed'> {
    const [rows] = (await this.db.query("SELECT id, member_id, amount FROM gangnamunni_points WHERE reference_type = ? AND reference_id = ? AND type = 'earn' LIMIT 1", [refType, refId])) as any;
    const earn = (rows as any[])[0];
    if (!earn) return 'skipped';
    const memberNo = n(earn.member_id);
    const amount = n(earn.amount);
    if (memberNo <= 0 || amount <= 0) return 'skipped';
    if (!this.db.getConnection) { this.log.error('point ledger needs a pool connection'); return 'failed'; }

    const conn = await this.db.getConnection();
    const lock = `pts_${memberNo}`;
    let locked = false;
    try {
      const [lk] = (await conn.query('SELECT GET_LOCK(?, 5) AS got', [lock])) as any;
      locked = n((lk as any[])[0]?.got) === 1;
      if (!locked) { this.log.error(`point lock busy for member ${memberNo}`); return 'failed'; }

      await conn.query('START TRANSACTION');
      try {
        // Idempotent: a second delete of the same review books nothing.
        const [dup] = (await conn.query("SELECT id FROM gangnamunni_points WHERE reference_type = ? AND reference_id = ? AND type = 'earn_cancel' LIMIT 1", [refType, refId])) as any;
        if ((dup as any[])[0]) { await conn.query('ROLLBACK'); return 'skipped'; }

        const [bal] = (await conn.query('SELECT balance FROM gangnamunni_points WHERE member_id = ? ORDER BY id DESC LIMIT 1', [memberNo])) as any;
        const cur = n((bal as any[])[0]?.balance);
        const next = cur - amount; // earn_cancel may go negative, like PHP
        const [ins] = (await conn.query(
          'INSERT INTO gangnamunni_points (member_id, type, amount, balance, reason, reference_type, reference_id, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)',
          [memberNo, 'earn_cancel', amount, next, reason, refType, refId])) as any;
        const pointId = n(ins.insertId);

        const s = await this.settings(conn);
        if (n(s.v2_schema_version) >= 1) {
          const [lotRows] = (await conn.query('SELECT id FROM gangnamunni_point_lots WHERE point_id = ? LIMIT 1', [n(earn.id)])) as any;
          // Like points_lots_apply(): only the positive balance that actually disappears is taken from lots.
          await this.consumeLots(conn, memberNo, pointId, Math.max(0, cur) - Math.max(0, next), n((lotRows as any[])[0]?.id));
        }
        await conn.query('COMMIT');
        return 'revoked';
      } catch (err) {
        await conn.query('ROLLBACK').catch(() => undefined);
        throw err;
      }
    } finally {
      if (locked) await conn.query('SELECT RELEASE_LOCK(?)', [lock]).catch(() => undefined);
      conn.release();
    }
  }

  /** points_lots_apply() debit path: the earn's own lot first, then the soonest-expiring lots. */
  private async consumeLots(conn: DbConn, memberNo: number, pointId: number, amount: number, targetLot: number) {
    let left = amount;
    if (left <= 0) return;
    const lots: { id: number; remaining: number }[] = [];
    if (targetLot > 0) {
      const [t] = (await conn.query('SELECT id, remaining FROM gangnamunni_point_lots WHERE id = ? AND member_id = ? AND remaining > 0', [targetLot, memberNo])) as any;
      if ((t as any[])[0]) lots.push({ id: n((t as any[])[0].id), remaining: n((t as any[])[0].remaining) });
    }
    const [rest] = (await conn.query(
      'SELECT id, remaining FROM gangnamunni_point_lots WHERE member_id = ? AND remaining > 0 AND id <> ? ORDER BY (expires_at IS NULL), expires_at, id', [memberNo, targetLot])) as any;
    for (const r of rest as any[]) lots.push({ id: n(r.id), remaining: n(r.remaining) });
    for (const lot of lots) {
      if (left <= 0) break;
      const take = Math.min(left, lot.remaining);
      const [up] = (await conn.query('UPDATE gangnamunni_point_lots SET remaining = remaining - ? WHERE id = ? AND remaining >= ?', [take, lot.id, take])) as any;
      if (n(up.affectedRows) !== 1) continue;
      await conn.query('INSERT INTO gangnamunni_point_lot_allocs (point_id, lot_id, amount) VALUES (?, ?, ?)', [pointId, lot.id, take]);
      left -= take;
    }
    if (left > 0) this.log.warn(`member ${memberNo}: ${left}P not covered by lots (run backfill)`);
  }
}
