import { Controller, Get, UseGuards } from '@nestjs/common';
import { SuperAdminGuard } from '../admin-auth/admin.guard';
import { DashboardService } from './dashboard.service';

@Controller('v1/dashboard')
@UseGuards(SuperAdminGuard)
export class DashboardController {
  constructor(private readonly dashboard: DashboardService) {}

  /** Platform-wide figures (members, bookings, payments, consultations, catalog). Read-only. */
  @Get('summary')
  summary() {
    return this.dashboard.summary();
  }
}
