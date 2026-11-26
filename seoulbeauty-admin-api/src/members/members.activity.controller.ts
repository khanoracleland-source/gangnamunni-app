import { Controller, Get, NotFoundException, Param, Query, UseGuards } from '@nestjs/common';
import { MembersActivityEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { pagingOf } from './members.activity.logic';
import { MembersActivityService } from './members.activity.service';
import { parseMemberId } from './members.logic';

/** What a member did in the app (applications, bookings, points) and what admins did to the member. Read only, super admin only. */
@Controller('v1/members/:id')
@UseGuards(MembersActivityEnabledGuard, SuperAdminGuard)
export class MembersActivityController {
  constructor(private readonly activity: MembersActivityService) {}

  private found<T>(v: T | null): T {
    if (!v) throw new NotFoundException({ error: 'not_found' });
    return v;
  }

  @Get('consultations')
  consultations(@Param('id') id: string, @Query() q: Record<string, unknown>) { return this.activity.consultations(parseMemberId(id), pagingOf(q)).then((v) => this.found(v)); }

  @Get('bookings')
  bookings(@Param('id') id: string, @Query() q: Record<string, unknown>) { return this.activity.bookings(parseMemberId(id), pagingOf(q)).then((v) => this.found(v)); }

  @Get('points')
  points(@Param('id') id: string, @Query() q: Record<string, unknown>) { return this.activity.points(parseMemberId(id), pagingOf(q)).then((v) => this.found(v)); }

  @Get('history')
  history(@Param('id') id: string, @Query() q: Record<string, unknown>) { return this.activity.history(parseMemberId(id), pagingOf(q)).then((v) => this.found(v)); }
}
