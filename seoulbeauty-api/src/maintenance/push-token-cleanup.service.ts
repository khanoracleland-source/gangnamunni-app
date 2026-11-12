import { Inject, Injectable, Logger, OnApplicationBootstrap, OnApplicationShutdown } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { CleanupSummary, duplicateIds, kstDay, kstHour, TokenRow } from './push-token-cleanup.logic';

const n = (v: unknown) => Number(v ?? 0) || 0;
const IOS_AS_ANDROID = "pt_use = 1 AND pt_platform = 'android' AND (pt_device_info LIKE '%iPhone%' OR pt_device_info LIKE '%iPad%' OR pt_device_info LIKE '%iPod%')";
const EMPTY_MEMBER = "pt_use = 1 AND (mb_id IS NULL OR mb_id = '') AND pt_datetime < DATE_SUB(NOW(), INTERVAL 14 DAY)";
const OLD_INACTIVE = 'pt_use = 0 AND pt_datetime < DATE_SUB(NOW(), INTERVAL 90 DAY)';

/**
 * Daily push-token housekeeping (replaces gangnamunni/cron_push_token_cleanup.php, same four steps in the same order):
 *  1. an iPhone / iPad token stored as android becomes ios
 *  2. an active token without a member, older than 14 days, is switched off
 *  3. of several active tokens of one member on one device only the newest stays on
 *  4. a token that has been off for 90 days is deleted
 * Every step is idempotent, so a second run (or the PHP cron still running next to it) changes nothing more.
 * Off by default: PUSH_TOKEN_CLEANUP_ENABLED=1 starts the in-process timer (once a day in KST hour PUSH_TOKEN_CLEANUP_HOUR, default 0).
 * `npm run push-token-cleanup -- --dry-run` counts what a run would do without changing anything.
 */
@Injectable()
export class PushTokenCleanupService implements OnApplicationBootstrap, OnApplicationShutdown {
  private readonly log = new Logger(PushTokenCleanupService.name);
  private timer: NodeJS.Timeout | null = null;
  private running = false;
  private lastDay = '';
  constructor(@Inject(DB) private readonly db: Db) {}

  onApplicationBootstrap() {
    if (process.env.NODE_ENV === 'test' || process.env.PUSH_TOKEN_CLEANUP_ENABLED !== '1') return;
    const hour = Math.min(23, Math.max(0, n(process.env.PUSH_TOKEN_CLEANUP_HOUR)));
    const tick = () => {
      const now = Date.now();
      if (kstHour(now) !== hour || this.lastDay === kstDay(now)) return;
      this.lastDay = kstDay(now);
      void this.run().catch((e) => this.log.error(`run: ${(e as Error).message}`));
    };
    this.timer = setInterval(tick, 10 * 60_000);
    this.timer.unref();
    this.log.log(`push token cleanup: once a day in KST hour ${hour}`);
  }

  onApplicationShutdown() { if (this.timer) { clearInterval(this.timer); this.timer = null; } }

  private async count(where: string): Promise<number> {
    const [rows] = (await this.db.query(`SELECT COUNT(*) AS c FROM g5_push_tokens WHERE ${where}`)) as any;
    return n((rows as any[])[0]?.c);
  }
  private async exec(sql: string, values: unknown[] = []): Promise<number> {
    const [res] = (await this.db.query(sql, values)) as any;
    return Math.max(0, n((res as any)?.affectedRows));
  }

  async run(dry = false): Promise<CleanupSummary> {
    const out: CleanupSummary = { dry, skipped: '', fixedPlatform: 0, emptyMember: 0, duplicates: 0, deletedInactive: 0, totals: [] };
    if (this.running) { out.skipped = 'busy'; return out; }
    this.running = true;
    try {
      const [t] = (await this.db.query("SHOW TABLES LIKE 'g5_push_tokens'")) as any;
      if (!(t as any[]).length) { out.skipped = 'no_table'; return out; }

      out.fixedPlatform = dry ? await this.count(IOS_AS_ANDROID) : await this.exec(`UPDATE g5_push_tokens SET pt_platform = 'ios' WHERE ${IOS_AS_ANDROID}`);
      out.emptyMember = dry ? await this.count(EMPTY_MEMBER) : await this.exec(`UPDATE g5_push_tokens SET pt_use = 0 WHERE ${EMPTY_MEMBER}`);

      const [rows] = (await this.db.query(
        `SELECT pt_id, mb_id, pt_platform, pt_device_info FROM g5_push_tokens
          WHERE pt_use = 1 AND mb_id IS NOT NULL AND mb_id <> '' ORDER BY pt_datetime DESC, pt_id DESC`)) as any;
      const ids = duplicateIds(rows as TokenRow[]);
      if (dry) out.duplicates = ids.length;
      else for (let i = 0; i < ids.length; i += 500) out.duplicates += await this.exec('UPDATE g5_push_tokens SET pt_use = 0 WHERE pt_use = 1 AND pt_id IN (?)', [ids.slice(i, i + 500)]);

      // a dry run counts the rows that are already off; a real run also deletes old rows that steps 2–3 have just switched off
      out.deletedInactive = dry ? await this.count(OLD_INACTIVE) : await this.exec(`DELETE FROM g5_push_tokens WHERE ${OLD_INACTIVE}`);

      const [sum] = (await this.db.query('SELECT pt_platform, pt_use, COUNT(*) AS cnt FROM g5_push_tokens GROUP BY pt_platform, pt_use ORDER BY pt_platform, pt_use')) as any;
      out.totals = (sum as any[]).map((r) => ({ platform: String(r.pt_platform ?? ''), use: n(r.pt_use), count: n(r.cnt) }));
      this.log.log(`${dry ? '[dry-run] ' : ''}push token cleanup: platform→ios ${out.fixedPlatform}, no member off ${out.emptyMember}, duplicates off ${out.duplicates}, old inactive deleted ${out.deletedInactive}`);
      return out;
    } finally { this.running = false; }
  }
}
