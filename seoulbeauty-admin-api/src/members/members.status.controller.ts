import { Body, Controller, HttpCode, NotFoundException, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, CsrfGuard, MembersStatusEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { parseMemberId } from './members.logic';
import { MembersWriteService } from './members.write.service';

/**
 * Block, unblock or let a member leave. Guard order: status switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). Super admin only.
 * Answers with the member as it is now.
 */
@Controller('v1/members')
@UseGuards(MembersStatusEnabledGuard, CsrfGuard, SuperAdminGuard)
export class MembersStatusController {
  constructor(private readonly writes: MembersWriteService) {}

  @Post(':id/status')
  @HttpCode(200)
  change(@Req() req: AdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    const id = parseMemberId(rawId);
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const { id: adminId, username, role } = req.admin;
    return this.writes.changeStatus({ id: adminId, username, role, ip: req.ip || '' }, id, body);
  }
}
