/** `npm run push-token-cleanup -- --dry-run`: one pass of the push-token housekeeping by hand. */
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { PushTokenCleanupService } from './maintenance/push-token-cleanup.service';

async function main() {
  process.env.PUSH_TOKEN_CLEANUP_ENABLED = '0'; // no timers in this one-shot process
  process.env.VISIT_REMINDER_ENABLED = '0';
  const app = await NestFactory.createApplicationContext(AppModule, { logger: ['error', 'warn'] });
  try {
    const r = await app.get(PushTokenCleanupService).run(process.argv.includes('--dry-run'));
    if (r.skipped) console.log(`skipped: ${r.skipped}`);
    else {
      console.log(`${r.dry ? '[dry-run] would change' : 'changed'}: platform→ios ${r.fixedPlatform}, no member off ${r.emptyMember}, duplicates off ${r.duplicates}, old inactive deleted ${r.deletedInactive}`);
      console.log(`--- tokens ${r.dry ? 'now' : 'after'} ---`);
      for (const t of r.totals) console.log(`${t.platform || '(none)'} / use=${t.use}: ${t.count}`);
    }
  } finally { await app.close(); }
}
main().catch((e) => { console.error(e); process.exit(1); });
