import { Body, Controller, HttpCode, Param, Patch, Post, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, CsrfGuard, HospitalsWriteEnabledGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { parseHospitalId, Scope } from './hospitals.logic';
import { HospitalsWriteService } from './hospitals.write.service';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });
const actorOf = (req: ScopedAdminRequest) => ({ id: req.admin.id, role: req.admin.role });

/**
 * Editing approved hospitals. Guard order: write switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403).
 * A hospital account may only edit the hospital it is bound to; activation and review are for super admins only.
 */
@Controller('v1/hospitals')
@UseGuards(HospitalsWriteEnabledGuard, CsrfGuard, AdminGuard)
export class HospitalsWriteController {
  constructor(private readonly writes: HospitalsWriteService) {}

  @Patch(':id')
  update(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    return this.writes.update(actorOf(req), scopeOfRequest(req), parseHospitalId(rawId), body);
  }

  @Post(':id/active')
  @HttpCode(200)
  @UseGuards(SuperAdminRoleGuard)
  setActive(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    return this.writes.setActive(actorOf(req), parseHospitalId(rawId), body);
  }

  @Post(':id/revisions/review')
  @HttpCode(200)
  @UseGuards(SuperAdminRoleGuard)
  review(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    return this.writes.markReviewed(actorOf(req), parseHospitalId(rawId));
  }
}
