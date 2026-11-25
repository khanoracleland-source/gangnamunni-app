import { Controller, Get, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, ConsultationsEnabledGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { ConsultationsService } from './consultations.service';

const scopeOfReq = (req: ScopedAdminRequest) => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/** Consultation requests. A super admin sees every hospital, a hospital account only its own. */
@Controller('v1/consultations')
export class ConsultationsController {
  constructor(private readonly consultations: ConsultationsService) {}

  @Get()
  @UseGuards(ConsultationsEnabledGuard, AdminGuard)
  list(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.consultations.list(scopeOfReq(req), query);
  }

  @Get('hospitals')
  @UseGuards(ConsultationsEnabledGuard, AdminGuard)
  hospitals(@Req() req: ScopedAdminRequest) {
    return this.consultations.hospitals(scopeOfReq(req));
  }
}
