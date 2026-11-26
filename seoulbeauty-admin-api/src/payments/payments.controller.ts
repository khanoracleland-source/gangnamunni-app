import { Body, Controller, Get, HttpCode, NotFoundException, Param, Post, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, CsrfGuard, PaymentsEnabledGuard, PaymentsRefundEnabledGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { parsePositiveInt } from '../bookings/bookings.logic';
import { PaymentRefundService } from './payments.refund.service';
import { PaymentsService } from './payments.service';

const scopeOfReq = (req: ScopedAdminRequest) => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/**
 * Booking payments. A super admin sees every hospital, a hospital account only its own (the scope is applied in every query).
 * Reading is always allowed; the full refund (POST :id/refund) needs ADMIN_PAYMENTS_REFUND=1. Gateway syncs are not part of this controller.
 */
@Controller('v1/payments')
export class PaymentsController {
  constructor(private readonly payments: PaymentsService, private readonly refunds: PaymentRefundService) {}

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

  /** The three boxes for this week / month (ADMIN_PAYMENTS_FEES=1 only; otherwise 404). */
  @Get('board')
  @UseGuards(PaymentsEnabledGuard, AdminGuard)
  board(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.payments.board(scopeOfReq(req), query);
  }

  /** The rows of one section: stage=held|counted|received (ADMIN_PAYMENTS_FEES=1 only; otherwise 404). */
  @Get('section')
  @UseGuards(PaymentsEnabledGuard, AdminGuard)
  section(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.payments.section(scopeOfReq(req), query);
  }

  /** Refunds of every payment in the scope, newest first (page=1..). */
  @Get('refunds')
  @UseGuards(PaymentsEnabledGuard, AdminGuard)
  refundHistory(@Req() req: ScopedAdminRequest, @Query('page') rawPage?: string) {
    const page = rawPage === undefined || rawPage === '' ? 1 : parsePositiveInt(rawPage);
    if (page === null || page > 100000) throw new NotFoundException({ error: 'not_found' });
    return this.refunds.history(scopeOfReq(req), { page });
  }

  /** One payment (under item/ so it never shadows fee-rates, refunds or the other fixed routes): amounts, booking, masked gateway id, whether it can be refunded now, and its refund history. */
  @Get('item/:id')
  @UseGuards(PaymentsEnabledGuard, AdminGuard)
  detail(@Req() req: ScopedAdminRequest, @Param('id') id: string) {
    return this.refunds.detail(scopeOfReq(req), id);
  }

  /**
   * Full refund of what the customer paid, through Inicis. Guard order: switch (404) -> CSRF (403 csrf) -> signed in / scope (401, 403).
   * A hospital account can refund only its own hospital's payments (the scope is applied in the query).
   */
  @Post(':id/refund')
  @HttpCode(200)
  @UseGuards(PaymentsRefundEnabledGuard, CsrfGuard, AdminGuard)
  refund(@Req() req: ScopedAdminRequest, @Param('id') id: string, @Body() body: unknown) {
    return this.refunds.refund(scopeOfReq(req), { id: req.admin.id, name: String(req.admin.name || req.admin.username || '').trim() || `admin ${req.admin.id}` }, id, body);
  }
}
