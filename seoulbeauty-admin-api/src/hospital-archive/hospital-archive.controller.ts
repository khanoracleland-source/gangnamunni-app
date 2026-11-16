import { Controller, Get, HttpCode, Param, Post, Query, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, CsrfGuard, HospitalsDeleteEnabledGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { normalizeListQuery, parseHospitalId } from '../hospitals/hospitals.logic';
import { HospitalArchiveService } from './hospital-archive.service';

/** Archive of deleted hospitals (read). Super admin only; with the delete switch off every route answers 404. */
@Controller('v1/hospital-archive')
@UseGuards(HospitalsDeleteEnabledGuard, AdminGuard, SuperAdminRoleGuard)
export class HospitalArchiveController {
  constructor(private readonly archive: HospitalArchiveService) {}

  @Get()
  list(@Query() query: Record<string, unknown>) {
    return this.archive.list(normalizeListQuery(query));
  }

  @Get(':id')
  detail(@Param('id') rawId: string) {
    return this.archive.detail(parseHospitalId(rawId));
  }
}

/** Acknowledging the one-month notice. Guard order: switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). */
@Controller('v1/hospital-archive')
@UseGuards(HospitalsDeleteEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
export class HospitalArchiveAckController {
  constructor(private readonly archive: HospitalArchiveService) {}

  @Post(':id/ack')
  @HttpCode(200)
  ack(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    return this.archive.ack(parseHospitalId(rawId), req.admin.name || req.admin.username || '');
  }
}
