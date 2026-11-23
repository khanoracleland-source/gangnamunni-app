import { Body, Controller, Delete, Get, HttpCode, NotFoundException, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, AdminRequest, CsrfGuard, PaymentsFeesEnabledGuard, ScopedAdminRequest, SUPER_ADMIN_ROLE, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { parseRateId } from './payment-fees.logic';
import { PaymentFeesService } from './payment-fees.service';

/**
 * Fee rates per payment method. Reading: any admin who may see payments (login names of who set a rate: super admins only). Writing and removing a not-yet-in-force rate: super admin only.
 * Guard order for writing: switch (404) -> CSRF (403 csrf) -> signed in (401) -> super admin (403).
 */
@Controller('v1/payments/fee-rates')
export class PaymentFeesController {
  constructor(private readonly fees: PaymentFeesService) {}

  @Get()
  @UseGuards(PaymentsFeesEnabledGuard, AdminGuard)
  list(@Req() req: ScopedAdminRequest) {
    return this.fees.list(req.admin.role === SUPER_ADMIN_ROLE);
  }

  @Post()
  @UseGuards(PaymentsFeesEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
  create(@Req() req: ScopedAdminRequest & AdminRequest, @Body() body: unknown) {
    return this.fees.create({ id: req.admin.id, username: req.admin.username, role: req.admin.role }, body);
  }

  /** Removes a rate that is not in force yet (a mistaken entry). Same guards as adding. */
  @Delete(':id')
  @HttpCode(200)
  @UseGuards(PaymentsFeesEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
  remove(@Req() req: ScopedAdminRequest & AdminRequest, @Param('id') rawId: string) {
    const id = parseRateId(rawId);
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    return this.fees.remove({ id: req.admin.id, username: req.admin.username, role: req.admin.role }, id);
  }
}
