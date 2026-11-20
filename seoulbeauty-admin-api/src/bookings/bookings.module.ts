import { Module } from '@nestjs/common';
import { BookingsController } from './bookings.controller';
import { BookingsService } from './bookings.service';
import { BookingsWriteService } from './bookings.write.service';
import { BookingNotifyService } from './notify/booking-notify.service';
import { FcmClient } from './notify/fcm.client';

@Module({
  controllers: [BookingsController],
  providers: [BookingsService, BookingsWriteService, BookingNotifyService, FcmClient],
})
export class BookingsModule {}
