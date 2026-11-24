import { Controller, Get, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, ModerationEnabledGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { Scope } from '../hospitals/hospitals.logic';
import { normalizeModerationQuery } from './moderation.logic';
import { ModerationService } from './moderation.service';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/**
 * Reviews and community posts waiting for a decision. Guard order: switch (404) -> signed in / scope (401, 403).
 * A hospital account sees only the reviews written for a treatment booked at its own hospital.
 */
@Controller('v1/moderation')
@UseGuards(ModerationEnabledGuard, AdminGuard)
export class ModerationController {
  constructor(private readonly moderation: ModerationService) {}

  /** Declared before any `:id` route so the fixed path is never read as an id. */
  @Get('hospitals')
  hospitals(@Req() req: ScopedAdminRequest) {
    return this.moderation.hospitals(scopeOfRequest(req));
  }

  @Get('reviews')
  reviews(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.moderation.reviews(scopeOfRequest(req), normalizeModerationQuery(query));
  }

  /** Community posts are for a whole-platform (super) admin only; a hospital account gets 403. */
  @Get('community')
  @UseGuards(SuperAdminRoleGuard)
  community(@Query() query: Record<string, unknown>) {
    return this.moderation.posts(normalizeModerationQuery(query));
  }
}
