import { Module } from '@nestjs/common';
import { AdminNotifyModule } from '../admin-notify/admin-notify.module';
import { MemberNotifyService } from './member-notify.service';
import { VisitReminderService } from './visit-reminder.service';

@Module({ imports: [AdminNotifyModule], providers: [MemberNotifyService, VisitReminderService], exports: [MemberNotifyService, VisitReminderService] })
export class MemberNotifyModule {}
