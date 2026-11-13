import { Module } from '@nestjs/common';
import { APP_GUARD } from '@nestjs/core';
import { ThrottlerGuard, ThrottlerModule } from '@nestjs/throttler';
import { DbModule } from './db/db.module';
import { AdminAuthModule } from './admin-auth/admin-auth.module';
import { MeModule } from './me/me.module';
import { DashboardModule } from './dashboard/dashboard.module';
import { HealthController } from './health/health.controller';

@Module({
  imports: [
    // Per-IP limit (in-memory, per process).
    ThrottlerModule.forRoot([{ ttl: 60_000, limit: Number(process.env.RATE_LIMIT_PER_MIN || 120) }]),
    DbModule,
    AdminAuthModule,
    MeModule,
    DashboardModule,
  ],
  controllers: [HealthController],
  providers: [{ provide: APP_GUARD, useClass: ThrottlerGuard }],
})
export class AppModule {}
