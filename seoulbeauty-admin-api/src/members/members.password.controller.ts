import { Body, Controller, HttpCode, NotFoundException, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, CsrfGuard, MembersPasswordEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { parseMemberId } from './members.logic';
import { MembersWriteService } from './members.write.service';

/**
 * Resetting a member's password. Guard order: password switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). Super admin only.
 * The super admin chooses the new password and tells it to the member; the answer never contains a password.
 */
@Controller('v1/members')
@UseGuards(MembersPasswordEnabledGuard, CsrfGuard, SuperAdminGuard)
export class MembersPasswordController {
  constructor(private readonly writes: MembersWriteService) {}

  @Post(':id/password')
  @HttpCode(200)
  reset(@Req() req: AdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    const id = parseMemberId(rawId);
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const { id: adminId, username, role } = req.admin;
    return this.writes.resetPassword({ id: adminId, username, role, ip: req.ip || '' }, id, body);
  }
}
