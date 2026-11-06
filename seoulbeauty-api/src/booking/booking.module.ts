import { Module } from '@nestjs/common';
import { AdminNotifyModule } from '../admin-notify/admin-notify.module';
import { MemberNotifyModule } from '../member-notify/member-notify.module';
import { BookingController } from './booking.controller';
import { BookingService } from './booking.service';

@Module({ imports: [AdminNotifyModule, MemberNotifyModule], controllers: [BookingController], providers: [BookingService], exports: [BookingService] })
export class BookingModule {}
