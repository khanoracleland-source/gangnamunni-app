import { Controller, Get, Inject, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';

@Controller('health')
export class HealthController {
  constructor(@Inject(DB) private readonly db: Db) {}

  @Get()
  async check() {
    try {
      await this.db.query('SELECT 1');
      return { status: 'ok' };
    } catch {
      throw new ServiceUnavailableException({ status: 'db_unreachable' });
    }
  }
}
