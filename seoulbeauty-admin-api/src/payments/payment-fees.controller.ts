import { Body, Controller, Get, Post, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, AdminRequest, CsrfGuard, PaymentsFeesEnabledGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { PaymentFeesService } from './payment-fees.service';

/**
 * Fee rates per payment method. Reading: any admin who may see payments. Writing: super admin only.
 * Guard order for writing: switch (404) -> CSRF (403 csrf) -> signed in (401) -> super admin (403).
 */
@Controller('v1/payments/fee-rates')
export class PaymentFeesController {
  constructor(private readonly fees: PaymentFeesService) {}

  @Get()
  @UseGuards(PaymentsFeesEnabledGuard, AdminGuard)
  list() {
    return this.fees.list();
  }

  @Post()
  @UseGuards(PaymentsFeesEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
  create(@Req() req: ScopedAdminRequest & AdminRequest, @Body() body: unknown) {
    return this.fees.create({ id: req.admin.id, username: req.admin.username, role: req.admin.role }, body);
  }
}
