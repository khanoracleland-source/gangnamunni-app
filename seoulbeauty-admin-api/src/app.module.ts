import { Module } from '@nestjs/common';
import { APP_GUARD } from '@nestjs/core';
import { ThrottlerGuard, ThrottlerModule } from '@nestjs/throttler';
import { DbModule } from './db/db.module';
import { AdminAuthModule } from './admin-auth/admin-auth.module';
import { MeModule } from './me/me.module';
import { AdminsModule } from './admins/admins.module';
import { DashboardModule } from './dashboard/dashboard.module';
import { HospitalsModule } from './hospitals/hospitals.module';
import { DoctorsModule } from './doctors/doctors.module';
import { HospitalApplyModule } from './hospital-apply/hospital-apply.module';
import { HospitalArchiveModule } from './hospital-archive/hospital-archive.module';
import { MapConfigModule } from './map-config/map-config.module';
import { TreatmentsModule } from './treatments/treatments.module';
import { TreatmentUnitsModule } from './treatment-units/treatment-units.module';
import { BookingsModule } from './bookings/bookings.module';
import { ConsultationsModule } from './consultations/consultations.module';
import { PaymentsModule } from './payments/payments.module';
import { BannersModule } from './banners/banners.module';
import { CategoryBoardModule } from './category-board/category-board.module';
import { ModerationModule } from './moderation/moderation.module';
import { MembersModule } from './members/members.module';
import { HealthController } from './health/health.controller';

@Module({
  imports: [
    // Per-IP limit (in-memory, per process).
    ThrottlerModule.forRoot([{ ttl: 60_000, limit: Number(process.env.RATE_LIMIT_PER_MIN || 120) }]),
    DbModule,
    AdminAuthModule,
    MeModule,
    DashboardModule,
    AdminsModule,
    HospitalsModule,
    DoctorsModule,
    HospitalApplyModule,
    HospitalArchiveModule,
    MapConfigModule,
    TreatmentsModule,
    TreatmentUnitsModule,
    BookingsModule,
    ConsultationsModule,
    PaymentsModule,
    ModerationModule,
    BannersModule,
    CategoryBoardModule,
    MembersModule,
  ],
  controllers: [HealthController],
  providers: [{ provide: APP_GUARD, useClass: ThrottlerGuard }],
})
export class AppModule {}
