import { Inject, Injectable, Logger, OnApplicationBootstrap, OnApplicationShutdown } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { MemberNotifyService } from './member-notify.service';
import { kstDate, RunSummary, windowOpen } from './visit-reminder.logic';

const n = (v: unknown) => Number(v ?? 0) || 0;

/**
 * 설정 > 방문 전 알림: 시술 전날 방문 일정 안내 (replaces cron_booking_visit_reminder.php).
 * Every confirmed booking whose 시술일 is tomorrow (KST) is reminded once, at the first run after 10:00 KST.
 *  - 설정 > 방문 전 알림 (noti_visit) off → MemberNotifyService sends nothing (the booking is still marked, so it is not retried)
 *  - once per booking: visit_reminder_sent_at is claimed first (UPDATE … IS NULL), so overlapping runs or a second API
 *    process never send twice
 *  - cancelled / completed / no-show bookings are never targets, nor a booking whose payment order is still unpaid (the
 *    member is being asked to pay, and the deadline cron may still cancel it)
 * Runs on an in-process timer (VISIT_REMINDER_INTERVAL_MIN, default 15; VISIT_REMINDER_ENABLED=0 turns it off, e.g. on a
 * second instance). `npm run visit-reminder -- --dry-run` lists the targets without sending.
 */
@Injectable()
export class VisitReminderService implements OnApplicationBootstrap, OnApplicationShutdown {
  private readonly log = new Logger(VisitReminderService.name);
  private timer: NodeJS.Timeout | null = null;
  private running = false;
  private columnMissingLogged = false;
  constructor(@Inject(DB) private readonly db: Db, private readonly member: MemberNotifyService) {}

  onApplicationBootstrap() {
    if (process.env.NODE_ENV === 'test' || process.env.VISIT_REMINDER_ENABLED === '0') return;
    const minutes = Math.min(60, Math.max(1, n(process.env.VISIT_REMINDER_INTERVAL_MIN) || 15));
    const tick = () => { void this.run().catch((e) => this.log.error(`run: ${(e as Error).message}`)); };
    // first run a minute after start (the DB pool is warm by then), then every `minutes`
    this.timer = setTimeout(() => { tick(); this.timer = setInterval(tick, minutes * 60_000); this.timer.unref(); }, 60_000);
    this.timer.unref();
    this.log.log(`방문 전 알림: every ${minutes} min from 10:00 KST`);
  }

  onApplicationShutdown() { if (this.timer) { clearTimeout(this.timer); clearInterval(this.timer); this.timer = null; } }

  /** One pass. `dry` lists the targets and changes nothing. A date change re-arms a booking (visit_reminder_sent_at back to NULL). */
  async run(dry = false, now = Date.now()): Promise<RunSummary> {
    const out: RunSummary = { date: kstDate(now, 1), skippedWindow: false, reminded: 0, skipped: 0, failed: 0, targets: [] };
    if (!windowOpen(now)) { out.skippedWindow = true; return out; }
    if (this.running) return out;
    this.running = true;
    try {
      if (!(await this.hasColumn())) return out;
      const [rows] = (await this.db.query(
        `SELECT b.id FROM gangnamunni_bookings b
          WHERE b.status = 'confirmed' AND b.visit_reminder_sent_at IS NULL AND b.booking_date = ?
            AND NOT EXISTS (SELECT 1 FROM gangnamunni_platform_payments p
                            WHERE p.payment_type = 'booking' AND p.booking_id = b.id AND p.status NOT IN ('paid', 'partially_refunded'))
          ORDER BY b.id LIMIT 500`, [out.date])) as any;
      out.targets = (rows as any[]).map((r) => n(r.id));
      if (dry) return out;
      for (const id of out.targets) {
        try {
          // claim first: only the run that flips NULL -> now sends
          const [res] = (await this.db.query(
            "UPDATE gangnamunni_bookings SET visit_reminder_sent_at = NOW() WHERE id = ? AND status = 'confirmed' AND visit_reminder_sent_at IS NULL", [id])) as any;
          if (n((res as any).affectedRows) !== 1) { out.skipped++; continue; }
          const r = await this.member.booking(id, 'visit_reminder');
          if (r.inApp) { out.reminded++; this.log.log(`reminded booking #${id} (push ${r.pushSent}/${r.pushSent + r.pushFailed})`); }
          else { out.skipped++; this.log.log(`skipped booking #${id}: ${r.reason || 'not_sent'}`); }
        } catch (e) { out.failed++; this.log.error(`booking #${id}: ${(e as Error).message}`); }
      }
      if (out.targets.length) this.log.log(`방문 전 알림 ${out.date}: reminded=${out.reminded} skipped=${out.skipped} failed=${out.failed}`);
      return out;
    } finally { this.running = false; }
  }

  /** sql/notification_visit_reminder_20261106.sql adds the column; until then the job logs once and does nothing. */
  private async hasColumn(): Promise<boolean> {
    const [rows] = (await this.db.query("SHOW COLUMNS FROM gangnamunni_bookings LIKE 'visit_reminder_sent_at'")) as any;
    const ok = (rows as any[]).length > 0;
    if (!ok && !this.columnMissingLogged) { this.columnMissingLogged = true; this.log.error('gangnamunni_bookings.visit_reminder_sent_at is missing: run sql/notification_visit_reminder_20261106.sql'); }
    return ok;
  }
}
