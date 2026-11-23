import { Controller, Get, NotFoundException, Param, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, DoctorsEnabledGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { parseHospitalId, Scope } from '../hospitals/hospitals.logic';
import { normalizeListQuery, parseDoctorId } from './doctors.logic';
import { DoctorsService } from './doctors.service';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/** Doctors of every hospital: list and detail. Read-only. A hospital account only ever sees the doctors of the hospital it is bound to. */
@Controller('v1/doctors')
@UseGuards(DoctorsEnabledGuard, AdminGuard)
export class DoctorsController {
  constructor(private readonly doctors: DoctorsService) {}

  @Get()
  list(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.doctors.list(scopeOfRequest(req), normalizeListQuery(query));
  }

  /** Fixed paths are declared before :id so they are not read as an id. */
  @Get('hospitals')
  hospitals(@Req() req: ScopedAdminRequest) {
    return this.doctors.hospitals(scopeOfRequest(req));
  }

  @Get('treatment-options')
  async treatmentOptions(@Req() req: ScopedAdminRequest, @Query('hospitalId') rawHospital: unknown) {
    const found = await this.doctors.treatmentOptions(scopeOfRequest(req), parseHospitalId(typeof rawHospital === 'string' ? rawHospital : ''));
    if (!found) throw new NotFoundException({ error: 'not_found' });
    return found;
  }

  /** The reorder list of one hospital (declared before :id). */
  @Get('order')
  async order(@Req() req: ScopedAdminRequest, @Query('hospitalId') rawHospital: unknown) {
    const found = await this.doctors.orderBoard(scopeOfRequest(req), parseHospitalId(typeof rawHospital === 'string' ? rawHospital : ''));
    if (!found) throw new NotFoundException({ error: 'not_found' });
    return found;
  }

  @Get(':id')
  async detail(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    const found = await this.doctors.detail(scopeOfRequest(req), parseDoctorId(rawId));
    if (!found) throw new NotFoundException({ error: 'not_found' });
    return found;
  }
}
