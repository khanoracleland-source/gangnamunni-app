import { Module } from '@nestjs/common';
import { APP_GUARD } from '@nestjs/core';
import { ThrottlerGuard, ThrottlerModule } from '@nestjs/throttler';
import { DbModule } from './db/db.module';
import { HospitalsModule } from './hospitals/hospitals.module';
import { AuthModule } from './auth/auth.module';
import { WishlistModule } from './wishlist/wishlist.module';
import { ConsultationsModule } from './consultations/consultations.module';
import { HomeModule } from './home/home.module';
import { SearchModule } from './search/search.module';
import { EventsModule } from './events/events.module';
import { CommunityModule } from './community/community.module';
import { MypageModule } from './mypage/mypage.module';
import { BookingsModule } from './bookings/bookings.module';
import { AccountModule } from './account/account.module';
import { NotificationsModule } from './notifications/notifications.module';
import { FaqModule } from './faq/faq.module';
import { ReviewsModule } from './reviews/reviews.module';
import { ProgressModule } from './progress/progress.module';
import { LegalModule } from './legal/legal.module';
import { BookingModule } from './booking/booking.module';
import { SocialModule } from './social/social.module';
import { LoginModule } from './login/login.module';
import { RegisterModule } from './register/register.module';
import { PasswordLostModule } from './password-lost/password-lost.module';
import { HealthController } from './health/health.controller';

@Module({
  imports: [
    // Per-IP limit (in-memory, per process). Also add limit_req at the nginx/Apache layer.
    ThrottlerModule.forRoot([{ ttl: 60_000, limit: Number(process.env.RATE_LIMIT_PER_MIN || 120) }]),
    DbModule,
    AuthModule,
    HospitalsModule,
    WishlistModule,
    ConsultationsModule,
    HomeModule,
    SearchModule,
    EventsModule,
    CommunityModule,
    MypageModule,
    FaqModule,
    BookingsModule,
    NotificationsModule,
    AccountModule,
    ReviewsModule,
    ProgressModule,
    LegalModule,
    BookingModule,
    PasswordLostModule, RegisterModule, LoginModule, SocialModule,
  ],
  controllers: [HealthController],
  providers: [{ provide: APP_GUARD, useClass: ThrottlerGuard }],
})
export class AppModule {}
