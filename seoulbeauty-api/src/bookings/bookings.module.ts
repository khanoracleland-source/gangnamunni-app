import { Module, OnApplicationShutdown, Inject } from '@nestjs/common';
import { MemberNotifyModule } from '../member-notify/member-notify.module';
import { BookingModule } from '../booking/booking.module';
import { PointsModule } from '../points/points.module';
import { BookingManageService } from './booking-manage.service';
import { BookingPaymentService } from './booking-payment.service';
import { BookingsController } from './bookings.controller';
import { BookingsService } from './bookings.service';
import { INICIS_FETCH, InicisPaymentService } from './inicis/inicis-payment.service';
import { InicisReturnController } from './inicis/inicis-return.controller';
import { createPayPool, PAY_DB } from './inicis/pay-db';

@Module({
  imports: [BookingModule, PointsModule, MemberNotifyModule],
  controllers: [BookingsController, InicisReturnController],
  providers: [BookingsService, BookingManageService, BookingPaymentService, InicisPaymentService, { provide: PAY_DB, useFactory: () => createPayPool() }, { provide: INICIS_FETCH, useFactory: () => fetch }],
})
export class BookingsModule implements OnApplicationShutdown {
  constructor(@Inject(PAY_DB) private readonly pay: { end?: () => Promise<void> } | null) {}
  async onApplicationShutdown() { await this.pay?.end?.(); }
}
