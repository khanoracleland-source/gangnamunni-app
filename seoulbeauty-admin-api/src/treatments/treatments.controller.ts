import { Controller, Get, UseGuards } from '@nestjs/common';
import { SuperAdminGuard, TreatmentsEnabledGuard } from '../admin-auth/admin.guard';
import { TreatmentsService } from './treatments.service';

/** The treatment master page (super admin only). Reading only; nothing here writes. */
@Controller('v1/treatments')
@UseGuards(TreatmentsEnabledGuard, SuperAdminGuard)
export class TreatmentsController {
  constructor(private readonly treatments: TreatmentsService) {}

  /** Every treatment with its aliases, every category, every conflict pair, the counters and the home table mode, in one answer. */
  @Get()
  list() {
    return this.treatments.list();
  }
}
