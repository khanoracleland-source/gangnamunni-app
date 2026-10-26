import { Controller, Get, Inject, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { AuthService } from '../auth/auth.service';

@Controller('health')
export class HealthController {
  constructor(@Inject(DB) private readonly db: Db, private readonly auth: AuthService) {}

  @Get()
  async check() {
    try {
      await this.db.query('SELECT 1');
    } catch {
      throw new ServiceUnavailableException({ status: 'db_unreachable' });
    }
    // Not fatal (reads work without it), but visible: login would silently look anonymous otherwise.
    const phpSession = await this.auth.ping();
    return { status: 'ok', phpSession: phpSession ? 'ok' : 'unreachable' };
  }
}
