import { Module } from '@nestjs/common';
import { AdminNotifyModule } from '../admin-notify/admin-notify.module';
import { BookingController } from './booking.controller';
import { BookingService } from './booking.service';

@Module({ imports: [AdminNotifyModule], controllers: [BookingController], providers: [BookingService] })
export class BookingModule {}
