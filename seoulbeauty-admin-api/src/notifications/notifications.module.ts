import { Module } from '@nestjs/common';
import { FcmClient } from '../bookings/notify/fcm.client';
import { NotificationsController } from './notifications.controller';
import { NotificationsPreviewController } from './notifications.preview.controller';
import { NotificationsSendController } from './notifications.send.controller';
import { NotificationsSendService } from './notifications.send.service';
import { NotificationsService } from './notifications.service';

@Module({
  controllers: [NotificationsSendController, NotificationsPreviewController, NotificationsController],
  providers: [NotificationsService, NotificationsSendService, FcmClient],
  exports: [NotificationsService, NotificationsSendService],
})
export class NotificationsModule {}
