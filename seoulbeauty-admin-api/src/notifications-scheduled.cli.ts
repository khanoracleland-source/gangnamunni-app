/** `npm run notifications-scheduled`: one pass of the planned sends (run by a timer every minute). */
import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { NotificationsScheduleService } from './notifications/notifications.schedule.service';

async function main() {
  const on = process.env.ADMIN_NOTIFICATIONS_ENABLED === '1' && process.env.ADMIN_NOTIFICATIONS_SEND === '1' && process.env.ADMIN_NOTIFICATIONS_SCHEDULE === '1';
  if (!on) { console.log('skipped: ADMIN_NOTIFICATIONS_ENABLED, ADMIN_NOTIFICATIONS_SEND and ADMIN_NOTIFICATIONS_SCHEDULE must all be 1'); return; }
  const app = await NestFactory.createApplicationContext(AppModule, { logger: ['error', 'warn'] });
  try {
    const r = await app.get(NotificationsScheduleService).runDue();
    console.log(`planned sends: due ${r.due}, sent ${r.sent}, delayed to 08:00 ${r.delayed}, missed ${r.missed}, failed ${r.failed}, skipped ${r.skipped}`);
  } finally { await app.close(); }
}
main().catch((e) => { console.error(e); process.exit(1); });
