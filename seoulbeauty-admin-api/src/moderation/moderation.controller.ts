import { BadRequestException, Body, Controller, Delete, Get, HttpCode, Param, Patch, Post, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, CsrfGuard, ModerationDeleteEnabledGuard, ModerationEditEnabledGuard, ModerationEnabledGuard, ModerationWriteEnabledGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { Scope } from '../hospitals/hospitals.logic';
import { Decision, FieldErrors, normalizeModerationQuery, parseItemId, parsePostEdit, parseReviewEdit, parseRevision } from './moderation.logic';
import { ModerationService } from './moderation.service';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

function revisionOf(body: unknown): string {
  const revision = parseRevision(body);
  if (!revision) throw new BadRequestException({ error: 'invalid', fields: { revision: 'required' } });
  return revision;
}

function checked<T>(parsed: { value: T; errors: FieldErrors }): T {
  if (Object.keys(parsed.errors).length > 0) throw new BadRequestException({ error: 'invalid', fields: parsed.errors });
  return parsed.value;
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

  /** Treatments the hospital offers: what the edit form of a review may add. */
  @Get('hospitals/:id/treatments')
  @UseGuards(ModerationEnabledGuard, AdminGuard)
  hospitalTreatments(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    return this.moderation.reviewTreatmentOptions(scopeOfRequest(req), parseItemId(rawId));
  }

  /** Categories posts use today: the select of the post edit form (super admin only). */
  @Get('community/categories')
  @UseGuards(ModerationEnabledGuard, AdminGuard, SuperAdminRoleGuard)
  categories() {
    return this.moderation.postCategories();
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

  /** Same for a community post; a whole-platform (super) admin only. */
  @Post('community/:id/:decision')
  @HttpCode(200)
  @UseGuards(ModerationWriteEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
  decidePost(@Param('id') rawId: string, @Param('decision') decision: string, @Body() body: unknown) {
    return this.moderation.decidePost(parseItemId(rawId), decisionOf(decision), revisionOf(body));
  }
  /** Edits one review; body { revision, title, content, rating, treatments[], removeImages[] }. The same reach as a decision. */
  @Patch('reviews/:id')
  @UseGuards(ModerationEditEnabledGuard, CsrfGuard, AdminGuard)
  editReview(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    const revision = revisionOf(body);
    return this.moderation.editReview(scopeOfRequest(req), parseItemId(rawId), checked(parseReviewEdit(body)), revision);
  }

  /** Edits one community post; body { revision, category, title, content, removeImages[] }. A whole-platform (super) admin only. */
  @Patch('community/:id')
  @UseGuards(ModerationEditEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
  editPost(@Param('id') rawId: string, @Body() body: unknown) {
    const revision = revisionOf(body);
    return this.moderation.editPost(parseItemId(rawId), checked(parsePostEdit(body)), revision);
  }

  /** Deletes one review for good; body { revision }. */
  @Delete('reviews/:id')
  @UseGuards(ModerationDeleteEnabledGuard, CsrfGuard, AdminGuard)
  deleteReview(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    return this.moderation.deleteReview(scopeOfRequest(req), parseItemId(rawId), revisionOf(body));
  }

  /** Deletes one community post for good; body { revision }. A whole-platform (super) admin only. */
  @Delete('community/:id')
  @UseGuards(ModerationDeleteEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
  deletePost(@Param('id') rawId: string, @Body() body: unknown) {
    return this.moderation.deletePost(parseItemId(rawId), revisionOf(body));
  }
}
