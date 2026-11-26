import { Controller, Get, NotFoundException, Param, Query, UseGuards } from '@nestjs/common';
import { MembersEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { normalizeListQuery, parseMemberId } from './members.logic';
import { MembersService } from './members.service';

/**
 * Members of the app (g5_member): list, counters and detail. Read only. Guard order: switch (404) -> signed in / role (401, 403).
 * Super admin only; hospital accounts never see members. The password hash is never part of an answer.
 */
@Controller('v1/members')
@UseGuards(MembersEnabledGuard, SuperAdminGuard)
export class MembersController {
  constructor(private readonly members: MembersService) {}

  @Get()
  list(@Query() query: Record<string, unknown>) {
    return this.members.list(normalizeListQuery(query));
  }

  /** Fixed path, declared before :id so it is not read as an id. */
  @Get('summary')
  summary() {
    return this.members.summary();
  }

  @Get(':id')
  async detail(@Param('id') rawId: string) {
    const found = await this.members.detail(parseMemberId(rawId));
    if (!found) throw new NotFoundException({ error: 'not_found' });
    return found;
  }
}
