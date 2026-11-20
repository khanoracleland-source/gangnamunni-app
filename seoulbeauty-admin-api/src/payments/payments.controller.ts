import { Controller, Get, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, PaymentsEnabledGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { PaymentsService } from './payments.service';

const scopeOfReq = (req: ScopedAdminRequest) => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/**
 * Booking payments, read only. A super admin sees every hospital, a hospital account only its own (the scope is applied in every query).
 * Refunds and gateway syncs are not part of this controller.
 */
@Controller('v1/payments')
export class PaymentsController {
  constructor(private readonly payments: PaymentsService) {}

  @Get()
  @UseGuards(PaymentsEnabledGuard, AdminGuard)
  list(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.payments.list(scopeOfReq(req), query);
  }

  @Get('hospitals')
  @UseGuards(PaymentsEnabledGuard, AdminGuard)
  hospitals(@Req() req: ScopedAdminRequest) {
    return this.payments.hospitals(scopeOfReq(req));
  }

  @Get('by-hospital')
  @UseGuards(PaymentsEnabledGuard, AdminGuard)
  byHospital(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.payments.byHospital(scopeOfReq(req), query);
  }

  @Get('by-treatment')
  @UseGuards(PaymentsEnabledGuard, AdminGuard)
  byTreatment(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.payments.byTreatment(scopeOfReq(req), query);
  }

  @Get('compare')
  @UseGuards(PaymentsEnabledGuard, AdminGuard)
  compare(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.payments.compare(scopeOfReq(req), query);
  }
}
