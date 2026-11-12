/** `npm run payment-expire -- --dry-run`: one pass of 결제 기한 자동 취소 by hand (the API process runs it on its own timer when enabled). */
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { PaymentExpireService } from './bookings/payment-expire.service';

async function main() {
  process.env.PAYMENT_EXPIRE_ENABLED = '0'; // no timers in this one-shot process
  process.env.VISIT_REMINDER_ENABLED = '0';
  process.env.PUSH_TOKEN_CLEANUP_ENABLED = '0';
  const app = await NestFactory.createApplicationContext(AppModule, { logger: ['error', 'warn'] });
  try {
    const r = await app.get(PaymentExpireService).run(process.argv.includes('--dry-run'));
    const list = (ids: number[]) => ids.join(', ') || '-';
    if (r.skipped) console.log(`skipped: ${r.skipped}`);
    else if (r.dry) {
      console.log(`[dry-run] deadline ${r.hours}h after the confirmation`);
      console.log(`would cancel ${r.targets.length} booking(s): ${list(r.targets)}`);
      console.log(`past the deadline but left alone (checkout is not open for the member) ${r.notPayable.length}: ${list(r.notPayable)}`);
    } else console.log(`deadline ${r.hours}h: cancelled=${r.cancelled} unchanged=${r.unchanged} failed=${r.failed} not_payable=${r.notPayable.length}`);
    process.exitCode = r.failed ? 1 : 0;
  } finally { await app.close(); }
}
main().catch((e) => { console.error(e); process.exit(1); });
