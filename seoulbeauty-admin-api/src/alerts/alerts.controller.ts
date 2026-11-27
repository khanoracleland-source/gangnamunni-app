import { BadRequestException, Body, Controller, Get, HttpCode, Param, Post, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, AlertsEnabledGuard, CsrfGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { filterOf, idOf, pageOf, TABS, tabOf } from './alerts.logic';
import { AlertsService } from './alerts.service';

const body = (b: unknown): Record<string, unknown> => (b && typeof b === 'object' ? (b as Record<string, unknown>) : {});

/** The admin's own inbox. Guard order: switch (404) -> CSRF on the writing routes (403 csrf) -> signed in / role (401, 403). */
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

  @Post('read-tab')
  @HttpCode(200)
  @UseGuards(CsrfGuard, AdminGuard)
  readTab(@Req() req: ScopedAdminRequest, @Body() b: unknown) {
    const tab = body(b).tab;
    // no tab = all of the admin's own alerts (as in the PHP page); a tab that is not one of ours is refused, never widened to "all"
    if (tab !== undefined && !(TABS as readonly string[]).includes(tab as string)) throw new BadRequestException({ error: 'bad_tab' });
    return this.svc.markTab(req.admin.id, tabOf(tab));
  }

  @Post('purge')
  @HttpCode(200)
  @UseGuards(CsrfGuard, AdminGuard)
  purge(@Req() req: ScopedAdminRequest, @Body() b: unknown) { return this.svc.purge(req.admin.id, body(b).days); }

  @Post(':id/read')
  @HttpCode(200)
  @UseGuards(CsrfGuard, AdminGuard)
  read(@Req() req: ScopedAdminRequest, @Param('id') id: string) { return this.svc.markRead(req.admin.id, idOf(id)); }
}
