import { Body, Controller, Get, HttpCode, Param, Post, Req, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { AdminGuard, HospitalsDoctorsEnabledGuard, HospitalsEnabledGuard, MultipartCsrfGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { HospitalsDoctorsService } from './hospitals.doctors.service';
import { HospitalDoctorsUploadInterceptor, UploadedPhotos } from './hospitals.doctors.upload';
import { parseHospitalId, Scope } from './hospitals.logic';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/** Doctors of one hospital; a hospital role sees only its own (any other id: 404). */
@Controller('v1/hospitals')
@UseGuards(HospitalsEnabledGuard, AdminGuard)
export class HospitalsDoctorsReadController {
  constructor(private readonly doctors: HospitalsDoctorsService) {}

  @Get(':id/doctors')
  list(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    return this.doctors.list(scopeOfRequest(req), parseHospitalId(rawId));
  }
}

/**
 * Adding doctors (multipart: `doctors` = JSON text of the rows, `photo` = 0..20 files referred to by position).
 * Guard order: doctors switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403); the body is only read after all three passed.
 */
@Controller('v1/hospitals')
@UseGuards(HospitalsDoctorsEnabledGuard, MultipartCsrfGuard, AdminGuard)
export class HospitalsDoctorsWriteController {
  constructor(private readonly doctors: HospitalsDoctorsService) {}

  @Post(':id/doctors')
  @HttpCode(200)
  @UseInterceptors(HospitalDoctorsUploadInterceptor)
  add(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body('doctors') doctors: unknown, @UploadedFiles() files: UploadedPhotos) {
    return this.doctors.add(scopeOfRequest(req), parseHospitalId(rawId), doctors, files);
  }
}
