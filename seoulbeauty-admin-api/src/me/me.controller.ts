import { Controller, Get, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, hospitalsEnabled, ScopedAdminRequest } from '../admin-auth/admin.guard';

@Controller('v1/me')
@UseGuards(AdminGuard)
export class MeController {
  /** The signed-in admin (top bar: name + role badge), what it may see, and which pages of this panel are switched on. */
  @Get()
  me(@Req() req: ScopedAdminRequest) {
    const { id, username, name, role, hospitalId, scope } = req.admin;
    return { admin: { id, username, name, role, hospitalId, scope }, features: { hospitals: hospitalsEnabled() } };
  }
}
