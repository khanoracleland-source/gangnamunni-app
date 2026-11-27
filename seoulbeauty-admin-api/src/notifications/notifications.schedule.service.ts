import { ConflictException, BadRequestException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { isMissing } from './notifications.service';
import { Actor, NotificationsSendService } from './notifications.send.service';
import { dbToMs, MAX_PENDING, msToKst, nextMorning, parseScheduleBody, payloadToBody, shouldDelay, summarize } from './notifications.schedule.logic';
import { numEnv } from './notifications.send.logic';
import type { SchedulePayload } from './notifications.schedule.logic';

type Row = Record<string, unknown>;
const NOT_READY = ['ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_BAD_FIELD_ERROR', 'ER_NO_SUCH_TABLE'];
const notReady = (e: unknown): boolean => NOT_READY.includes(String((e as { code?: string })?.code));
const affected = (r: unknown): number => Number((r as { affectedRows?: number })?.affectedRows ?? 0) || 0;

export interface RunReport { due: number; sent: number; delayed: number; failed: number; missed: number; skipped: number }

/** Planned sends: the admin plans (HTTP), the timer runs them (CLI, one pass per minute or so). */
@Injectable()
export class NotificationsScheduleService {
  private readonly log = new Logger('NotificationsSchedule');

  constructor(@Inject(DB) private readonly db: Db, @Inject(DB_RW) private readonly rw: RwDbProvider, private readonly sender: NotificationsSendService) {}

  async create(actor: Actor, body: unknown, now = Date.now()): Promise<{ id: number; runAt: string; status: 'pending' }> {
    const parsed = parseScheduleBody(body, now);
    if (!parsed.ok) throw new BadRequestException({ error: parsed.error });
    const pool = this.rw.get();
    if (!pool) throw new ServiceUnavailableException({ error: 'write_unavailable' });
    try {
      const [cnt] = (await pool.query("SELECT COUNT(*) AS n FROM gangnamunni_notification_scheduled WHERE status = 'pending'")) as any;
      if (Number((cnt as Row[])[0]?.n ?? 0) >= MAX_PENDING) throw new ConflictException({ error: 'too_many_pending' });
      const [res] = (await pool.query('INSERT INTO gangnamunni_notification_scheduled (admin_id, admin_username, payload, run_at, status) VALUES (?, ?, ?, ?, ?)',
        [actor.id, actor.username, JSON.stringify(parsed.payload), parsed.runAt, 'pending'])) as any;
      return { id: Number(res?.insertId ?? 0), runAt: parsed.runAt.slice(0, 16), status: 'pending' };
    } catch (e) {
      if (notReady(e)) { this.log.warn(`planned sends not ready: ${(e as Error).message}`); throw new ServiceUnavailableException({ error: 'not_ready' }); }
      throw e;
    }
  }

  async list(): Promise<{ items: Record<string, unknown>[]; available: boolean }> {
    try {
      const [rows] = (await this.db.query(
        'SELECT id, admin_id, admin_username, payload, run_at, status, send_id, error FROM gangnamunni_notification_scheduled ORDER BY run_at DESC, id DESC LIMIT 100')) as any;
      return { items: (rows as Row[]).map(summarize), available: true };
    } catch (e) {
      if (!isMissing(e)) throw e;
      return { items: [], available: false };
    }
  }

  async cancel(id: number): Promise<{ id: number; status: 'cancelled' }> {
    if (!(id > 0)) throw new NotFoundException({ error: 'not_found' });
    const pool = this.rw.get();
    if (!pool) throw new ServiceUnavailableException({ error: 'write_unavailable' });
    try {
      const [res] = await pool.query("UPDATE gangnamunni_notification_scheduled SET status = 'cancelled' WHERE id = ? AND status = 'pending'", [id]);
      if (affected(res) === 1) return { id, status: 'cancelled' };
      const [rows] = (await pool.query('SELECT id FROM gangnamunni_notification_scheduled WHERE id = ? LIMIT 1', [id])) as any;
      if ((rows as Row[]).length === 0) throw new NotFoundException({ error: 'not_found' });
      throw new ConflictException({ error: 'not_pending' });
    } catch (e) {
      if (notReady(e)) throw new ServiceUnavailableException({ error: 'not_ready' });
      throw e;
    }
  }

  /**
   * One pass of the timer: the planned sends whose time has come. A row is taken with `UPDATE ... WHERE status = 'pending'`, so two
   * passes never run the same one. A send that is later than SCHEDULED_MAX_LATE_MIN (default 120) is not sent any more ("missed"): an old
   * promotion arriving hours late is worse than none. Night: see `nightPolicy` (skip = the normal rule, delay = wait until 08:00 KST).
   */
  async runDue(fixedNow?: number, sleep?: (ms: number) => Promise<void>): Promise<RunReport> {
    const now = fixedNow ?? Date.now();
    const rep: RunReport = { due: 0, sent: 0, delayed: 0, failed: 0, missed: 0, skipped: 0 };
    const pool = this.rw.get();
    if (!pool) throw new ServiceUnavailableException({ error: 'write_unavailable' });
    const maxLateMs = numEnv('SCHEDULED_MAX_LATE_MIN', 120, 1, 1440) * 60_000;
    const [rows] = (await pool.query(
      "SELECT id, admin_id, admin_username, payload, run_at FROM gangnamunni_notification_scheduled WHERE status = 'pending' AND run_at <= ? ORDER BY run_at, id LIMIT 20", [msToKst(now)])) as any;
    for (const r of rows as Row[]) {
      rep.due++;
      const id = Number(r.id);
      const [took] = await pool.query("UPDATE gangnamunni_notification_scheduled SET status = 'running' WHERE id = ? AND status = 'pending'", [id]);
      if (affected(took) !== 1) { rep.skipped++; continue; }
      // a pass can take minutes (each send up to its deadline): the night and "too late" checks use the time of each row, not the start
      await this.runOne(pool, r, id, fixedNow ?? Date.now(), maxLateMs, rep, sleep);
    }
    return rep;
  }

  private async runOne(pool: RwDb, r: Row, id: number, now: number, maxLateMs: number, rep: RunReport, sleep?: (ms: number) => Promise<void>): Promise<void> {
    const finish = (status: string, error: string, sendId: number | null = null) =>
      pool.query("UPDATE gangnamunni_notification_scheduled SET status = ?, error = ?, send_id = ? WHERE id = ? AND status = 'running'", [status, error.slice(0, 480), sendId, id]);
    const runAt = dbToMs(r.run_at);
    if (runAt !== null && now - runAt > maxLateMs) { await finish('failed', 'missed: too late to send'); rep.missed++; return; }
    const body = payloadToBody(r.payload, id);
    if (!body) { await finish('failed', 'bad payload'); rep.failed++; return; }
    const p = body as unknown as SchedulePayload;
    if (shouldDelay(p.type, p.nightPolicy, now)) {
      await pool.query("UPDATE gangnamunni_notification_scheduled SET status = 'pending', run_at = ? WHERE id = ? AND status = 'running'", [msToKst(nextMorning(now)), id]);
      rep.delayed++;
      return;
    }
    try {
      const res = await this.sender.send({ id: Number(r.admin_id), username: String(r.admin_username ?? ''), role: 'super_admin', ip: 'scheduler' }, body, now, sleep, true);
      await finish('done', '', res.sendId);
      rep.sent++;
    } catch (e) {
      const code = (e as { getResponse?: () => unknown }).getResponse?.() as { error?: string } | undefined;
      const why = code?.error ?? (e as Error).message;
      if (why === 'busy') { await pool.query("UPDATE gangnamunni_notification_scheduled SET status = 'pending' WHERE id = ? AND status = 'running'", [id]); rep.skipped++; return; }
      this.log.error(`planned send #${id}: ${why}`);
      await finish('failed', String(why));
      rep.failed++;
    }
  }

  /** Housekeeping, by hand or by the daily timer: the per-recipient rows older than `days`, old planned rows, history rows a dead process left on "sending". */
  async cleanup(days = 90, dry = false): Promise<{ items: number; scheduled: number; interrupted: number }> {
    const pool = this.rw.get();
    if (!pool) throw new ServiceUnavailableException({ error: 'write_unavailable' });
    const keep = Math.max(7, Math.floor(days) || 90);
    const out = { items: 0, scheduled: 0, interrupted: 0 };
    if (dry) {
      const one = async (sql: string): Promise<number> => Number((((await pool.query(sql, [keep])) as any)[0] as Row[])[0]?.n ?? 0);
      out.items = await one('SELECT COUNT(*) AS n FROM gangnamunni_notification_send_items WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
      out.scheduled = await one("SELECT COUNT(*) AS n FROM gangnamunni_notification_scheduled WHERE status IN ('done','failed','cancelled') AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
      out.interrupted = Number((((await pool.query("SELECT COUNT(*) AS n FROM gangnamunni_notification_sends WHERE push_error = 'sending' AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)")) as any)[0] as Row[])[0]?.n ?? 0);
      return out;
    }
    for (let i = 0; i < 200; i++) {
      const n = affected((await pool.query('DELETE FROM gangnamunni_notification_send_items WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 5000', [keep]))[0]);
      out.items += n;
      if (n < 5000) break;
    }
    out.scheduled = affected((await pool.query("DELETE FROM gangnamunni_notification_scheduled WHERE status IN ('done','failed','cancelled') AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [keep]))[0]);
    out.interrupted = affected((await pool.query("UPDATE gangnamunni_notification_sends SET push_error = 'failed: interrupted' WHERE push_error = 'sending' AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"))[0]);
    return out;
  }
}
