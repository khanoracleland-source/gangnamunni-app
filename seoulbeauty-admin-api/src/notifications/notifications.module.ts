import { Module } from '@nestjs/common';
import { NotificationsController } from './notifications.controller';
import { NotificationsPreviewController } from './notifications.preview.controller';
import { NotificationsService } from './notifications.service';

@Module({
  controllers: [NotificationsPreviewController, NotificationsController],
  providers: [NotificationsService],
  exports: [NotificationsService],
})
export class NotificationsModule {}
