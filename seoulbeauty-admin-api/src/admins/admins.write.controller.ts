import { Body, Controller, Delete, ForbiddenException, HttpCode, NotFoundException, Param, Patch, Post, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, AdminsAccessGuard, AdminsWriteEnabledGuard, CsrfGuard, SUPER_ADMIN_ROLE } from '../admin-auth/admin.guard';
import { parseAdminId, Viewer } from './admins.logic';
import { AdminsWriteService } from './admins.write.service';

const viewerOf = (req: AdminRequest): Viewer => ({ id: req.admin.id, superAdmin: req.admin.role === SUPER_ADMIN_ROLE });
const actorOf = (req: AdminRequest) => ({ id: req.admin.id, role: req.admin.role });

/**
 * Writing admin accounts. Guard order: write switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403).
 * Edit: a super admin may edit any account (and its role and hospital), every other role only its own name, phone and notifications.
 * Add, the active state and delete are for super admins only.
 */
@Controller('v1/admins')
@UseGuards(AdminsWriteEnabledGuard, CsrfGuard, AdminsAccessGuard)
export class AdminsWriteController {
  constructor(private readonly writes: AdminsWriteService) {}

  @Post()
  create(@Req() req: AdminRequest, @Body() body: unknown) {
    const viewer = viewerOf(req);
    if (!viewer.superAdmin) throw new ForbiddenException({ error: 'forbidden' });
    return this.writes.create(actorOf(req), viewer, body);
  }

  @Patch(':id')
  edit(@Req() req: AdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    const id = parseAdminId(rawId);
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const viewer = viewerOf(req);
    if (!viewer.superAdmin && id !== viewer.id) throw new ForbiddenException({ error: 'forbidden' });
    return this.writes.edit(actorOf(req), viewer, id, body);
  }

  @Post(':id/active')
  @HttpCode(200)
  setActive(@Req() req: AdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    const id = parseAdminId(rawId);
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const viewer = viewerOf(req);
    if (!viewer.superAdmin) throw new ForbiddenException({ error: 'forbidden' });
    return this.writes.setActive(actorOf(req), viewer, id, body);
  }

  @Delete(':id')
  @HttpCode(200)
  remove(@Req() req: AdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    const id = parseAdminId(rawId);
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    if (!viewerOf(req).superAdmin) throw new ForbiddenException({ error: 'forbidden' });
    return this.writes.remove(actorOf(req), id, body);
  }
}
