import { Controller, Get, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, AlertsEnabledGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { filterOf, pageOf } from './alerts.logic';
import { AlertsService } from './alerts.service';

/** The admin's own inbox. Guard order: switch (404) -> signed in / role (401, 403). */
@Controller('v1/alerts')
@UseGuards(AlertsEnabledGuard)
export class AlertsController {
  constructor(private readonly svc: AlertsService) {}

  @Get()
  @UseGuards(AdminGuard)
  list(@Req() req: ScopedAdminRequest, @Query() q: Record<string, unknown>) { return this.svc.list(req.admin.id, filterOf(q), pageOf(q.page)); }

  @Get('unread')
  @UseGuards(AdminGuard)
  unread(@Req() req: ScopedAdminRequest) { return this.svc.unread(req.admin.id); }
}
