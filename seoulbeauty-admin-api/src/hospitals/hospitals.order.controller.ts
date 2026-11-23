import { Body, Controller, Get, HttpCode, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, CsrfGuard, HospitalsOrderEnabledGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { parseHospitalId } from './hospitals.logic';
import { HospitalsOrderService } from './hospitals.order.service';
import { HospitalsService } from './hospitals.service';

/** Moving a hospital in the app's default order. Guard order: order switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403) -> body. */
@Controller('v1/hospitals')
@UseGuards(HospitalsOrderEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
export class HospitalsOrderController {
  constructor(private readonly orders: HospitalsOrderService) {}

  @Post(':id/order')
  @HttpCode(200)
  move(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    return this.orders.move(req.admin.id, parseHospitalId(rawId), body);
  }
}

/** The reorder screen's list. Declared in the module before the hospital controller so "order" is not read as an id. */
@Controller('v1/hospitals')
@UseGuards(HospitalsOrderEnabledGuard, AdminGuard, SuperAdminRoleGuard)
export class HospitalsOrderListController {
  constructor(private readonly hospitals: HospitalsService) {}

  @Get('order')
  board() {
    return this.hospitals.orderBoard();
  }
}
