import { Controller, Get, NotFoundException, Param, Query, UseGuards } from '@nestjs/common';
import { NotificationsEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { idOf, pageOf } from './notifications.logic';
import { NotificationsService } from './notifications.service';

const one = (v: unknown): string => (typeof v === 'string' ? v.trim() : '');

/** Read side of the notifications page. Guard order: switch (404) -> signed in / role (401, 403). Super admin only. */
@Controller('v1/notifications')
@UseGuards(NotificationsEnabledGuard, SuperAdminGuard)
export class NotificationsController {
  constructor(private readonly svc: NotificationsService) {}

  @Get('options')
  options() { return this.svc.options(); }

  @Get('history')
  history(@Query() q: Record<string, unknown>) { return this.svc.history(pageOf(q.page), one(q.kind) === 'auto' ? 'auto' : 'admin'); }

  @Get('history/:id')
  async historyDetail(@Param('id') rawId: string, @Query() q: Record<string, unknown>) {
    const found = await this.svc.historyDetail(idOf(rawId), pageOf(q.page), one(q.status));
    if (!found) throw new NotFoundException({ error: 'not_found' });
    return found;
  }

  @Get('link-targets')
  linkTargets(@Query() q: Record<string, unknown>) {
    const kind = one(q.kind);
    return this.svc.linkTargets(one(q.q), kind === 'page' || kind === 'hospital' || kind === 'event' ? kind : '').then((items) => ({ items }));
  }

  @Get('member-search')
  memberSearch(@Query() q: Record<string, unknown>) { return this.svc.searchMembers(q.q).then((items) => ({ items })); }

  @Get('diagnostics')
  diagnostics() { return this.svc.diagnostics(); }

  @Get('automatic')
  automatic() { return this.svc.automatic(); }
}
