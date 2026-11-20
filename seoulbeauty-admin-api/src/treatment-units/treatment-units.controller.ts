import { Body, Controller, Delete, Get, HttpCode, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, CsrfGuard, SuperAdminGuard, TreatmentUnitsEnabledGuard, TreatmentUnitsWriteEnabledGuard } from '../admin-auth/admin.guard';
import { parseHospitalId } from '../hospitals/hospitals.logic';
import { TreatmentUnitsService } from './treatment-units.service';
import { TreatmentUnitsWriteService } from './treatment-units.write.service';

/** The unit list of the price forms (super admin only): the list, adding a unit and removing one (soft delete). */
@Controller('v1/treatment-units')
export class TreatmentUnitsController {
  constructor(private readonly units: TreatmentUnitsService, private readonly writes: TreatmentUnitsWriteService) {}

  @Get()
  @UseGuards(TreatmentUnitsEnabledGuard, SuperAdminGuard)
  list() {
    return this.units.list();
  }

  /** Guard order: write switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). */
  @Post()
  @HttpCode(200)
  @UseGuards(TreatmentUnitsWriteEnabledGuard, CsrfGuard, SuperAdminGuard)
  add(@Req() req: AdminRequest, @Body() body: unknown) {
    return this.writes.add({ id: req.admin.id, name: req.admin.name }, body);
  }

  @Delete(':id')
  @HttpCode(200)
  @UseGuards(TreatmentUnitsWriteEnabledGuard, CsrfGuard, SuperAdminGuard)
  remove(@Req() req: AdminRequest, @Param('id') raw: string) {
    return this.writes.remove({ id: req.admin.id, name: req.admin.name }, parseHospitalId(raw));
  }
}
