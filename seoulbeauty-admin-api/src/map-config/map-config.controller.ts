import { Controller, Get, UseGuards } from '@nestjs/common';
import { AdminGuard, HospitalsEnabledGuard } from '../admin-auth/admin.guard';
import { MapConfigService } from './map-config.service';

/** Map provider and browser key for the hospital location picker. Read-only; any admin who has the hospital pages. */
@Controller('v1/map-config')
@UseGuards(HospitalsEnabledGuard, AdminGuard)
export class MapConfigController {
  constructor(private readonly map: MapConfigService) {}

  @Get()
  get() {
    return this.map.get();
  }
}
