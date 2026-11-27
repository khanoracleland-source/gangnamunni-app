/** `npm run notifications-cleanup -- --dry-run`: the daily housekeeping of the notification tables (per-recipient rows older than 90 days). */
import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { NotificationsScheduleService } from './notifications/notifications.schedule.service';

async function main() {
  if (process.env.ADMIN_NOTIFICATIONS_ENABLED !== '1') { console.log('skipped: ADMIN_NOTIFICATIONS_ENABLED is not 1'); return; }
  const dry = process.argv.includes('--dry-run');
  const app = await NestFactory.createApplicationContext(AppModule, { logger: ['error', 'warn'] });
  try {
    const r = await app.get(NotificationsScheduleService).cleanup(Number(process.env.NOTIFICATIONS_ITEMS_KEEP_DAYS || 90), dry);
    console.log(`${dry ? '[dry-run] would remove' : 'removed'}: recipient rows ${r.items}, old planned rows ${r.scheduled}; history rows marked interrupted ${r.interrupted}`);
  } finally { await app.close(); }
}
main().catch((e) => { console.error(e); process.exit(1); });
