import { Body, Controller, HttpCode, Param, Patch, Post, Req, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { AdminGuard, CsrfGuard, DoctorsWriteEnabledGuard, MultipartCsrfGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { UploadedPhotos } from '../hospitals/hospitals.doctors.upload';
import { Scope } from '../hospitals/hospitals.logic';
import { parseDoctorId } from './doctors.logic';
import { DoctorEditUploadInterceptor } from './doctors.upload';
import { DoctorsWriteService } from './doctors.write.service';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/**
 * Editing a doctor (multipart: `doctor` = JSON text, `photo` = optional file) and retiring one (JSON, no body).
 * Guard order: write switch (404) -> CSRF (403 csrf) -> signed in / scope (401, 403); the body is read only after all three passed.
 * Declared before DoctorsController, so `:id/retire` and `:id` are never taken for a read route.
 */
@Controller('v1/doctors')
export class DoctorsWriteController {
  constructor(private readonly doctors: DoctorsWriteService) {}

  @Patch(':id')
  @HttpCode(200)
  @UseGuards(DoctorsWriteEnabledGuard, MultipartCsrfGuard, AdminGuard)
  @UseInterceptors(DoctorEditUploadInterceptor)
  edit(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body('doctor') doctor: unknown, @UploadedFiles() files: UploadedPhotos) {
    return this.doctors.edit(scopeOfRequest(req), parseDoctorId(rawId), doctor, files);
  }

  @Post(':id/retire')
  @HttpCode(200)
  @UseGuards(DoctorsWriteEnabledGuard, CsrfGuard, AdminGuard)
  retire(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    return this.doctors.retire(scopeOfRequest(req), parseDoctorId(rawId));
  }
}
