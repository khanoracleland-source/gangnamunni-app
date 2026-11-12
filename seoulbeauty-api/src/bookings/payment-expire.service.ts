import { Inject, Injectable, Logger, OnApplicationBootstrap, OnApplicationShutdown } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { BookingManageService } from './booking-manage.service';
import { BookingPaymentService } from './booking-payment.service';

const n = (v: unknown) => Number(v ?? 0) || 0;
const s = (v: unknown) => (v == null ? '' : String(v));

export interface ExpireSummary {
  dry: boolean; skipped: '' | 'no_column' | 'no_payments' | 'busy'; hours: number;
  /** bookings past the deadline whose member can pay right now: these are (or would be) cancelled */
  targets: number[];
  /** past the deadline too, but checkout is not open for that member (allow-list / payments off): never cancelled */
  notPayable: number[];
  cancelled: number; unchanged: number; failed: number;
}

/**
 * [05-37] 결제 기한 자동 취소 (the job of PHP cron_booking_payment_expire.php, which was never scheduled on the server).
 * A booking the hospital confirmed is cancelled when the member has not paid within payment_deadline_hours (admin setting, default 24)
 * of the confirmation. The member was told so in the "결제를 진행해주세요" notice and gets "결제 기한이 지나 예약이 취소되었어요".
 *
 * Narrower than the PHP cron on purpose — a booking is cancelled only when the member really was able to pay:
 *  - it has a payment order that is still `pending` with an amount (the member was asked to pay); a booking without an order is left alone
 *  - checkout is open for that member right now (PG_PAYMENTS_ENABLED, Inicis configured, INICIS_*_MEMBERS allow-list); while checkout is
 *    limited to test members nobody else's booking is touched
 *  - no "결제 없이 계속하기" row, no paid / refunded order
 * Off by default: PAYMENT_EXPIRE_ENABLED=1 starts the timer (every PAYMENT_EXPIRE_INTERVAL_MIN, default 10).
 * `npm run payment-expire -- --dry-run` lists what a run would cancel and changes nothing.
 */
@Injectable()
export class PaymentExpireService implements OnApplicationBootstrap, OnApplicationShutdown {
  private readonly log = new Logger(PaymentExpireService.name);
  private timer: NodeJS.Timeout | null = null;
  private running = false;
  constructor(@Inject(DB) private readonly db: Db, private readonly manage: BookingManageService) {}

  onApplicationBootstrap() {
    if (process.env.NODE_ENV === 'test' || process.env.PAYMENT_EXPIRE_ENABLED !== '1') return;
    const minutes = Math.min(60, Math.max(1, n(process.env.PAYMENT_EXPIRE_INTERVAL_MIN) || 10));
    const tick = () => { void this.run().catch((e) => this.log.error(`run: ${(e as Error).message}`)); };
    this.timer = setTimeout(() => { tick(); this.timer = setInterval(tick, minutes * 60_000); this.timer.unref(); }, 90_000);
    this.timer.unref();
    this.log.log(`결제 기한 자동 취소: every ${minutes} min`);
  }

  onApplicationShutdown() { if (this.timer) { clearTimeout(this.timer); clearInterval(this.timer); this.timer = null; } }

  private async has(sql: string, values: unknown[]): Promise<boolean> {
    try { const [rows] = (await this.db.query(sql, values)) as any; return (rows as any[]).length > 0; } catch { return false; }
  }

  /** payment_deadline_hours (gangnamunni_settings): whole hours 1–168, anything else = 24. */
  private async hours(): Promise<number> {
    try {
      const [rows] = (await this.db.query("SELECT setting_value FROM gangnamunni_settings WHERE setting_key = 'payment_deadline_hours' LIMIT 1")) as any;
      const v = s((rows as any[])[0]?.setting_value);
      const h = /^\d+$/.test(v) ? Number(v) : 24;
      return h >= 1 && h <= 168 ? h : 24;
    } catch { return 24; }
  }

  async run(dry = false, env: NodeJS.ProcessEnv = process.env): Promise<ExpireSummary> {
    const out: ExpireSummary = { dry, skipped: '', hours: 24, targets: [], notPayable: [], cancelled: 0, unchanged: 0, failed: 0 };
    if (this.running) { out.skipped = 'busy'; return out; }
    this.running = true;
    try {
      if (!(await this.has("SHOW COLUMNS FROM gangnamunni_bookings LIKE 'confirmed_at'", []))) { out.skipped = 'no_column'; return out; }
      if (!(await this.has("SHOW TABLES LIKE 'gangnamunni_platform_payments'", []))) { out.skipped = 'no_payments'; return out; }
      out.hours = await this.hours();
      const [rows] = (await this.db.query(
        `SELECT b.id, b.mb_id FROM gangnamunni_bookings b
          WHERE b.status = 'confirmed' AND b.confirmed_at IS NOT NULL AND b.confirmed_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
            AND EXISTS (SELECT 1 FROM gangnamunni_platform_payments p
                         WHERE p.payment_type = 'booking' AND p.booking_id = b.id AND p.status = 'pending' AND p.amount > 0)
            AND NOT EXISTS (SELECT 1 FROM gangnamunni_platform_payments p
                             WHERE p.payment_type = 'booking' AND p.booking_id = b.id AND p.status IN ('paid', 'partially_refunded', 'refunded'))
          ORDER BY b.id LIMIT 200`, [out.hours])) as any;
      for (const r of rows as any[]) {
        // the member must be able to pay right now, otherwise the deadline means nothing to them
        if (BookingPaymentService.liveAvailable(env, s(r.mb_id))) out.targets.push(n(r.id)); else out.notPayable.push(n(r.id));
      }
      if (dry) return out;
      for (const id of out.targets) {
        try {
          if (await this.manage.expireUnpaid(id, out.hours)) { out.cancelled++; this.log.log(`cancelled booking #${id}: unpaid ${out.hours}h after the confirmation`); }
          else out.unchanged++; // paid, cancelled or changed in the meantime
        } catch (e) { out.failed++; this.log.error(`booking #${id}: ${(e as Error).message}`); }
      }
      if (out.targets.length || out.notPayable.length) this.log.log(`결제 기한 자동 취소 (${out.hours}h): cancelled=${out.cancelled} unchanged=${out.unchanged} failed=${out.failed} not_payable=${out.notPayable.length}`);
      return out;
    } finally { this.running = false; }
  }
}
