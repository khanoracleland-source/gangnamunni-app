import { Body, Controller, HttpCode, Post, UseGuards } from '@nestjs/common';
import { CsrfGuard, NotificationsEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { NotificationsService } from './notifications.service';

/** Counts for the audience and type being composed; writes nothing. Guard order: switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). */
@Controller('v1/notifications')
@UseGuards(NotificationsEnabledGuard, CsrfGuard, SuperAdminGuard)
export class NotificationsPreviewController {
  constructor(private readonly svc: NotificationsService) {}

  @Post('preview')
  @HttpCode(200)
  preview(@Body() body: unknown) { return this.svc.preview(body); }
}
