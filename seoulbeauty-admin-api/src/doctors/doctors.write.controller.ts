import { BadRequestException, Body, Controller, HttpCode, Param, Patch, Post, Req, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { AdminGuard, CsrfGuard, DoctorsDeleteEnabledGuard, DoctorsOrderEnabledGuard, DoctorsWriteEnabledGuard, MultipartCsrfGuard, ScopedAdminRequest, SuperAdminRoleGuard } from '../admin-auth/admin.guard';
import { UploadedPhotos } from '../hospitals/hospitals.doctors.upload';
import { Scope } from '../hospitals/hospitals.logic';
import { parseDoctorId } from './doctors.logic';
import { isDeleteConfirmed } from './doctors.order.logic';
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

  /** Body { direction: "up" | "down" } or { position: n }. The hospital account moves only doctors of its own hospital. */
  @Post(':id/order')
  @HttpCode(200)
  @UseGuards(DoctorsOrderEnabledGuard, CsrfGuard, AdminGuard)
  move(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    return this.doctors.move(scopeOfRequest(req), req.admin.id, parseDoctorId(rawId), body);
  }

  /** Permanent delete of a retired doctor; super admin only, body { confirm: true }. */
  @Post(':id/delete')
  @HttpCode(200)
  @UseGuards(DoctorsDeleteEnabledGuard, CsrfGuard, AdminGuard, SuperAdminRoleGuard)
  remove(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    if (!isDeleteConfirmed(body)) throw new BadRequestException({ error: 'invalid', fields: { confirm: 'required' } });
    return this.doctors.remove(scopeOfRequest(req), req.admin.id, parseDoctorId(rawId));
  }
}
