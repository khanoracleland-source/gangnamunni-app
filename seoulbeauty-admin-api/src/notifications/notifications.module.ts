import { Module } from '@nestjs/common';
import { FcmClient } from '../bookings/notify/fcm.client';
import { NotificationsController } from './notifications.controller';
import { NotificationsPreviewController } from './notifications.preview.controller';
import { NotificationsScheduleController } from './notifications.schedule.controller';
import { NotificationsScheduleService } from './notifications.schedule.service';
import { NotificationsSendController } from './notifications.send.controller';
import { NotificationsSendService } from './notifications.send.service';
import { NotificationsService } from './notifications.service';

@Module({
  controllers: [NotificationsScheduleController, NotificationsSendController, NotificationsPreviewController, NotificationsController],
  providers: [NotificationsService, NotificationsSendService, NotificationsScheduleService, FcmClient],
  exports: [NotificationsService, NotificationsSendService, NotificationsScheduleService],
})
export class NotificationsModule {}
