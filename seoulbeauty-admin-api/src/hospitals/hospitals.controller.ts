import { Controller, Get, NotFoundException, Param, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, HospitalsEnabledGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { HospitalsService } from './hospitals.service';
import { normalizeListQuery, parseHospitalId, Scope } from './hospitals.logic';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/** Approved hospitals: list and detail. Read-only. A hospital account only ever sees the hospital it is bound to. */
@Controller('v1/hospitals')
@UseGuards(HospitalsEnabledGuard, AdminGuard)
export class HospitalsController {
  constructor(private readonly hospitals: HospitalsService) {}

  @Get()
  list(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.hospitals.list(scopeOfRequest(req), normalizeListQuery(query));
  }

  /** Declared before :id so the fixed path is not read as an id. */
  @Get('treatment-options')
  treatmentOptions(@Req() req: ScopedAdminRequest) {
    return this.hospitals.treatmentOptions(scopeOfRequest(req));
  }

  @Get(':id')
  async detail(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    const found = await this.hospitals.detail(scopeOfRequest(req), parseHospitalId(rawId), req.admin.role);
    if (!found) throw new NotFoundException({ error: 'not_found' });
    return found;
  }
}
