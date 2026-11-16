import { Controller, Get, HttpCode, Param, Post, Req, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { AdminGuard, HospitalsEnabledGuard, HospitalsImagesEnabledGuard, MultipartCsrfGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { parseHospitalId, Scope } from './hospitals.logic';
import { HospitalsImagesService } from './hospitals.images.service';
import { HospitalImagesUploadInterceptor, UploadedImages } from './hospitals.images.upload';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/** Images of one hospital; a hospital role sees only its own (any other id: 404). */
@Controller('v1/hospitals')
@UseGuards(HospitalsEnabledGuard, AdminGuard)
export class HospitalsImagesReadController {
  constructor(private readonly images: HospitalsImagesService) {}

  @Get(':id/images')
  list(@Req() req: ScopedAdminRequest, @Param('id') rawId: string) {
    return this.images.list(scopeOfRequest(req), parseHospitalId(rawId));
  }
}

/**
 * Adding images (multipart: main 0..1, interior 0..10). Guard order: images switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403);
 * the body is only read after all three passed.
 */
@Controller('v1/hospitals')
@UseGuards(HospitalsImagesEnabledGuard, MultipartCsrfGuard, AdminGuard)
export class HospitalsImagesWriteController {
  constructor(private readonly images: HospitalsImagesService) {}

  @Post(':id/images')
  @HttpCode(200)
  @UseInterceptors(HospitalImagesUploadInterceptor)
  upload(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @UploadedFiles() files: UploadedImages) {
    return this.images.upload(scopeOfRequest(req), parseHospitalId(rawId), files);
  }
}
