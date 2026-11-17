import { Body, Controller, HttpCode, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, CsrfGuard, HospitalsReviewEnabledGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { parseHospitalId } from './hospitals.logic';
import { reviewerName } from './hospitals.review.logic';
import { HospitalsReviewService } from './hospitals.review.service';

const actorOf = (req: ScopedAdminRequest) => ({ id: req.admin.id, name: reviewerName(req.admin.name, req.admin.username) });

/** Approving or rejecting an application (status only). Guard order: review switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403) -> body. */
@Controller('v1/hospitals')
@UseGuards(HospitalsReviewEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
export class HospitalsReviewController {
  constructor(private readonly reviews: HospitalsReviewService) {}

  @Post(':id/approve')
  @HttpCode(200)
  approve(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    return this.reviews.approve(actorOf(req), parseHospitalId(rawId));
  }

  @Post(':id/reject')
  @HttpCode(200)
  reject(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    return this.reviews.reject(actorOf(req), parseHospitalId(rawId), body);
  }
}
