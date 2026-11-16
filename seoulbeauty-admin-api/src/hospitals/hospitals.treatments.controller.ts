import { Body, Controller, Delete, Get, HttpCode, Param, Patch, Post, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, CsrfGuard, HospitalsTreatmentsEnabledGuard, HospitalsTreatmentsRemoveEnabledGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { parseHospitalId, Scope } from './hospitals.logic';
import { HospitalsTreatmentsService } from './hospitals.treatments.service';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });
const actorOf = (req: ScopedAdminRequest) => ({ id: req.admin.id, role: req.admin.role });

/** Treatments assigned to one hospital; a hospital role sees only its own (any other id: 404). Reads nothing but the database. */
@Controller('v1/hospitals')
@UseGuards(HospitalsTreatmentsEnabledGuard, AdminGuard)
export class HospitalsTreatmentsReadController {
  constructor(private readonly treatments: HospitalsTreatmentsService) {}

  @Get(':id/treatments')
  list(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    return this.treatments.list(scopeOfRequest(req), actorOf(req), parseHospitalId(rawId));
  }
}

/**
 * Assigning treatments and changing promotion / minimum age.
 * Guard order: treatments switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403); then hospital scope (404) and the management rule (403 forbidden_treatments).
 */
@Controller('v1/hospitals')
@UseGuards(HospitalsTreatmentsEnabledGuard, CsrfGuard, AdminGuard)
export class HospitalsTreatmentsWriteController {
  constructor(private readonly treatments: HospitalsTreatmentsService) {}

  @Post(':id/treatments')
  @HttpCode(200)
  assign(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    return this.treatments.assign(scopeOfRequest(req), actorOf(req), parseHospitalId(rawId), body);
  }

  @Patch(':id/treatments/:htId')
  update(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('htId') rawHt: string, @Body() body: unknown) {
    return this.treatments.patch(scopeOfRequest(req), actorOf(req), parseHospitalId(rawId), parseHospitalId(rawHt), body);
  }
}

/** Removing an assignment deletes its price option and detail image rows, so it has its own switch (404 while off). */
@Controller('v1/hospitals')
@UseGuards(HospitalsTreatmentsRemoveEnabledGuard, CsrfGuard, AdminGuard)
export class HospitalsTreatmentsRemoveController {
  constructor(private readonly treatments: HospitalsTreatmentsService) {}

  @Delete(':id/treatments/:htId')
  @HttpCode(200)
  remove(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('htId') rawHt: string) {
    return this.treatments.remove(scopeOfRequest(req), actorOf(req), parseHospitalId(rawId), parseHospitalId(rawHt));
  }
}
