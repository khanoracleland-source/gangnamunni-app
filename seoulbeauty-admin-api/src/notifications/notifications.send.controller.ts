import { Body, Controller, HttpCode, Post, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, CsrfGuard, NotificationsSendEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { NotificationsSendService } from './notifications.send.service';

/**
 * Sends a notification to members. Guard order: send switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). Super admin only.
 * The sender comes from the session, never from the body.
 */
@Controller('v1/notifications')
@UseGuards(NotificationsSendEnabledGuard, CsrfGuard, SuperAdminGuard)
export class NotificationsSendController {
  constructor(private readonly sender: NotificationsSendService) {}

  @Post('send')
  @HttpCode(200)
  send(@Req() req: AdminRequest, @Body() body: unknown) {
    const { id, username, role } = req.admin;
    return this.sender.send({ id, username, role, ip: req.ip || '' }, body);
  }
}
