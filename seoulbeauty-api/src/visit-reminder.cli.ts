/** `npm run visit-reminder -- --dry-run`: one pass of 방문 전 알림 by hand (the API process runs it on its own timer). */
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { VisitReminderService } from './member-notify/visit-reminder.service';

async function main() {
  process.env.VISIT_REMINDER_ENABLED = '0'; // no timer in this one-shot process
  const app = await NestFactory.createApplicationContext(AppModule, { logger: ['error', 'warn', 'log'] });
  try {
    const dry = process.argv.includes('--dry-run');
    const r = await app.get(VisitReminderService).run(dry);
    if (r.skippedWindow) console.log(`before 10:00 KST, nothing to do (targets for ${r.date} are reminded from 10:00)`);
    else if (dry) console.log(`[dry-run] ${r.date}: ${r.targets.length} booking(s) would be reminded: ${r.targets.join(', ') || '-'}`);
    else console.log(`${r.date}: reminded=${r.reminded} skipped=${r.skipped} failed=${r.failed}`);
    process.exitCode = r.failed ? 1 : 0;
  } finally { await app.close(); }
}
main().catch((e) => { console.error(e); process.exit(1); });
