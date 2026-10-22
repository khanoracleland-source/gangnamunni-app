import { Module } from '@nestjs/common';
import { APP_GUARD } from '@nestjs/core';
import { ThrottlerGuard, ThrottlerModule } from '@nestjs/throttler';
import { DbModule } from './db/db.module';
import { HospitalsModule } from './hospitals/hospitals.module';
import { HealthController } from './health/health.controller';

@Module({
  imports: [
    // Per-IP limit (in-memory, per process). Also add limit_req at the nginx/Apache layer.
    ThrottlerModule.forRoot([{ ttl: 60_000, limit: Number(process.env.RATE_LIMIT_PER_MIN || 120) }]),
    DbModule,
    HospitalsModule,
  ],
  controllers: [HealthController],
  providers: [{ provide: APP_GUARD, useClass: ThrottlerGuard }],
})
export class AppModule {}
