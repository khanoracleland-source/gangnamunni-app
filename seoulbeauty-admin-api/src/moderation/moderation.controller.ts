import { BadRequestException, Body, Controller, Get, HttpCode, Param, Post, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, CsrfGuard, ModerationEnabledGuard, ModerationWriteEnabledGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { Scope } from '../hospitals/hospitals.logic';
import { Decision, normalizeModerationQuery, parseItemId, parseRevision } from './moderation.logic';
import { ModerationService } from './moderation.service';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

function revisionOf(body: unknown): string {
  const revision = parseRevision(body);
  if (!revision) throw new BadRequestException({ error: 'invalid', fields: { revision: 'required' } });
  return revision;
}

function decisionOf(raw: string): Decision {
  if (raw === 'approve' || raw === 'reject') return raw;
  throw new BadRequestException({ error: 'invalid' });
}

/**
 * Reviews and community posts waiting for a decision. Guard order: switch (404) -> CSRF on writes (403 csrf) -> signed in / scope (401, 403).
 * A hospital account sees only the reviews written for a treatment booked at its own hospital.
 */
@Controller('v1/moderation')
export class ModerationController {
  constructor(private readonly moderation: ModerationService) {}

  /** Declared before any `:id` route so the fixed path is never read as an id. */
  @Get('hospitals')
  @UseGuards(ModerationEnabledGuard, AdminGuard)
  hospitals(@Req() req: ScopedAdminRequest) {
    return this.moderation.hospitals(scopeOfRequest(req));
  }

  @Get('reviews')
  @UseGuards(ModerationEnabledGuard, AdminGuard)
  reviews(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.moderation.reviews(scopeOfRequest(req), normalizeModerationQuery(query));
  }

  /** Community posts are for a whole-platform (super) admin only; a hospital account gets 403. */
  @Get('community')
  @UseGuards(ModerationEnabledGuard, AdminGuard, SuperAdminRoleGuard)
  community(@Query() query: Record<string, unknown>) {
    return this.moderation.posts(normalizeModerationQuery(query));
  }

  /** Approves or rejects one pending review; body { revision } is the one the page was read with. */
  @Post('reviews/:id/:decision')
  @HttpCode(200)
  @UseGuards(ModerationWriteEnabledGuard, CsrfGuard, AdminGuard)
  decideReview(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('decision') decision: string, @Body() body: unknown) {
    return this.moderation.decideReview(scopeOfRequest(req), parseItemId(rawId), decisionOf(decision), revisionOf(body));
  }
}
