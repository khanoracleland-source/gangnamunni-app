import { Controller, Get, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, SuperAdminGuard } from '../admin-auth/admin.guard';

@Controller('v1/me')
@UseGuards(SuperAdminGuard)
export class MeController {
  /** The signed-in super admin, for the top bar (name + role badge). */
  @Get()
  me(@Req() req: AdminRequest) {
    const { id, username, name, role } = req.admin;
    return { admin: { id, username, name, role } };
  }
}
