import { Controller, ForbiddenException, Get, NotFoundException, Param, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, AdminsAccessGuard, AdminsEnabledGuard, SUPER_ADMIN_ROLE } from '../admin-auth/admin.guard';
import { parseAdminId, Viewer } from './admins.logic';
import { AdminsService } from './admins.service';

const viewerOf = (req: AdminRequest): Viewer => ({ id: req.admin.id, superAdmin: req.admin.role === SUPER_ADMIN_ROLE });

/**
 * Admin accounts, read-only. Guard order: switch (404) -> signed in / role (401, 403).
 * A super admin sees every account; any other role sees only its own (another id answers 403).
 * The password hash and the session counter are never part of an answer.
 */
@Controller('v1/admins')
@UseGuards(AdminsEnabledGuard, AdminsAccessGuard)
export class AdminsController {
  constructor(private readonly admins: AdminsService) {}

  @Get()
  list(@Req() req: AdminRequest) {
    return this.admins.list(viewerOf(req));
  }

  /** Declared before :id so the fixed path is not read as an id. */
  @Get('options')
  options(@Req() req: AdminRequest) {
    return this.admins.options(viewerOf(req));
  }

  @Get(':id')
  async detail(@Req() req: AdminRequest, @Param('id') rawId: string) {
    const id = parseAdminId(rawId);
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const viewer = viewerOf(req);
    if (!viewer.superAdmin && id !== viewer.id) throw new ForbiddenException({ error: 'forbidden' });
    const found = await this.admins.detail(viewer, id);
    if (!found) throw new NotFoundException({ error: 'not_found' });
    return found;
  }
}
