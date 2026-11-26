import { Body, Controller, NotFoundException, Param, Patch, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, CsrfGuard, MembersWriteEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { parseMemberId } from './members.logic';
import { MembersWriteService } from './members.write.service';

/**
 * Editing a member. Guard order: write switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). Super admin only.
 * The e-mail is the only field that can be changed; any other key in the body answers 400.
 */
@Controller('v1/members')
@UseGuards(MembersWriteEnabledGuard, CsrfGuard, SuperAdminGuard)
export class MembersWriteController {
  constructor(private readonly writes: MembersWriteService) {}

  @Patch(':id')
  edit(@Req() req: AdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    const id = parseMemberId(rawId);
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const { id: adminId, username, role } = req.admin;
    return this.writes.changeEmail({ id: adminId, username, role, ip: req.ip || '' }, id, body);
  }
}
