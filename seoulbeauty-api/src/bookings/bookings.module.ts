import { Module } from '@nestjs/common';
import { BookingModule } from '../booking/booking.module';
import { PointsModule } from '../points/points.module';
import { BookingManageService } from './booking-manage.service';
import { BookingsController } from './bookings.controller';
import { BookingsService } from './bookings.service';

@Module({ imports: [BookingModule, PointsModule], controllers: [BookingsController], providers: [BookingsService, BookingManageService] })
export class BookingsModule {}
