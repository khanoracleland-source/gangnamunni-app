import { Body, Controller, Delete, Get, HttpCode, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, CsrfGuard, NotificationsScheduleEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { idOf } from './notifications.logic';
import { NotificationsScheduleService } from './notifications.schedule.service';

/**
 * Planned sends. Guard order: switch (404) -> CSRF on the writing routes (403 csrf) -> signed in / role (401, 403). Super admin only.
 * The planner comes from the session, never from the body.
 */
@Controller('v1/notifications')
@UseGuards(NotificationsScheduleEnabledGuard)
export class NotificationsScheduleController {
  constructor(private readonly svc: NotificationsScheduleService) {}

  @Post('schedule')
  @HttpCode(200)
  @UseGuards(CsrfGuard, SuperAdminGuard)
  create(@Req() req: AdminRequest, @Body() body: unknown) {
    const { id, username, role } = req.admin;
    return this.svc.create({ id, username, role, ip: req.ip || '' }, body);
  }

  @Get('scheduled')
  @UseGuards(SuperAdminGuard)
  list() { return this.svc.list(); }

  @Delete('scheduled/:id')
  @UseGuards(CsrfGuard, SuperAdminGuard)
  cancel(@Param('id') id: string) { return this.svc.cancel(idOf(id)); }
}
