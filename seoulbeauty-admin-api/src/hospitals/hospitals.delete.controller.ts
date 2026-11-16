import { Body, Controller, Get, HttpCode, NotFoundException, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, CsrfGuard, HospitalsDeleteEnabledGuard, HospitalsEnabledGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { parseHospitalId, Scope } from './hospitals.logic';
import { HospitalsDeleteService } from './hospitals.delete.service';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/** Sales card of one hospital: super admin sees any, a hospital role only its own (same 404 rules as the detail). */
@Controller('v1/hospitals')
@UseGuards(HospitalsEnabledGuard, AdminGuard)
export class HospitalsStatsController {
  constructor(private readonly deletes: HospitalsDeleteService) {}

  @Get(':id/stats')
  async stats(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    const found = await this.deletes.stats(scopeOfRequest(req), parseHospitalId(rawId));
    if (!found) throw new NotFoundException({ error: 'not_found' });
    return found;
  }
}

/** What stands in the way of deleting a hospital. Super admin only; delete switch off -> 404. */
@Controller('v1/hospitals')
@UseGuards(HospitalsDeleteEnabledGuard, AdminGuard, SuperAdminRoleGuard)
export class HospitalsDeleteCheckController {
  constructor(private readonly deletes: HospitalsDeleteService) {}

  @Get(':id/delete-check')
  async check(@Param('id') rawId: string) {
    const found = await this.deletes.check(parseHospitalId(rawId));
    if (!found) throw new NotFoundException({ error: 'not_found' });
    return found;
  }
}

/** Deleting a hospital. Guard order: switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). */
@Controller('v1/hospitals')
@UseGuards(HospitalsDeleteEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
export class HospitalsDeleteController {
  constructor(private readonly deletes: HospitalsDeleteService) {}

  @Post(':id/delete')
  @HttpCode(200)
  delete(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    return this.deletes.delete({ id: req.admin.id, name: req.admin.name || req.admin.username || '' }, parseHospitalId(rawId), body);
  }
}
