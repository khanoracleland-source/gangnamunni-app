import { Body, Controller, Delete, Get, HttpCode, Param, Post, Req, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { AdminGuard, CsrfGuard, HospitalsTreatmentsImagesEnabledGuard, MultipartCsrfGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { parseHospitalId, Scope } from './hospitals.logic';
import { ImageActor, HospitalsTreatmentImagesService } from './hospitals.treatments.images.service';
import { TreatmentImageUploadInterceptor, UploadedTreatmentImages } from './hospitals.treatments.images.upload';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });
const actorOf = (req: ScopedAdminRequest): ImageActor => ({ id: req.admin.id, role: req.admin.role, name: req.admin.name || req.admin.username || '' });

/** Detail images of one treatment of a hospital; a hospital role sees only its own hospital (any other id: 404). Reads nothing but the database. */
@Controller('v1/hospitals')
@UseGuards(HospitalsTreatmentsImagesEnabledGuard, AdminGuard)
export class HospitalsTreatmentImagesReadController {
  constructor(private readonly images: HospitalsTreatmentImagesService) {}

  @Get(':id/treatments/:htId/images')
  list(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('htId') rawHt: string) {
    return this.images.list(scopeOfRequest(req), actorOf(req), parseHospitalId(rawId), parseHospitalId(rawHt));
  }
}

/**
 * Adding an image (multipart: `image` 0..1 file or `imageUrl` text, `caption`).
 * Guard order: images switch (404) -> multipart CSRF (403 csrf) -> signed in / role (401, 403); the body is only read after all three passed,
 * then hospital scope (404), the management rule (403 forbidden_treatments) and the fields (400).
 */
@Controller('v1/hospitals')
@UseGuards(HospitalsTreatmentsImagesEnabledGuard, MultipartCsrfGuard, AdminGuard)
export class HospitalsTreatmentImagesUploadController {
  constructor(private readonly images: HospitalsTreatmentImagesService) {}

  @Post(':id/treatments/:htId/images')
  @HttpCode(200)
  @UseInterceptors(TreatmentImageUploadInterceptor)
  add(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('htId') rawHt: string, @Body() body: unknown, @UploadedFiles() files: UploadedTreatmentImages) {
    return this.images.add(scopeOfRequest(req), actorOf(req), parseHospitalId(rawId), parseHospitalId(rawHt), body, files);
  }
}

/** Delete, reorder and review. Guard order: images switch (404) -> JSON CSRF (403 csrf) -> signed in / role (401, 403); then scope (404) and the rule of each action. */
@Controller('v1/hospitals')
@UseGuards(HospitalsTreatmentsImagesEnabledGuard, CsrfGuard, AdminGuard)
export class HospitalsTreatmentImagesWriteController {
  constructor(private readonly images: HospitalsTreatmentImagesService) {}

  @Delete(':id/treatments/:htId/images/:imageId')
  @HttpCode(200)
  remove(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('htId') rawHt: string, @Param('imageId') rawImage: string) {
    return this.images.remove(scopeOfRequest(req), actorOf(req), parseHospitalId(rawId), parseHospitalId(rawHt), parseHospitalId(rawImage));
  }

  @Post(':id/treatments/:htId/images/:imageId/move')
  @HttpCode(200)
  move(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('htId') rawHt: string, @Param('imageId') rawImage: string, @Body() body: unknown) {
    return this.images.move(scopeOfRequest(req), actorOf(req), parseHospitalId(rawId), parseHospitalId(rawHt), parseHospitalId(rawImage), body);
  }

  @Post(':id/treatments/:htId/images/:imageId/review')
  @HttpCode(200)
  review(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('htId') rawHt: string, @Param('imageId') rawImage: string, @Body() body: unknown) {
    return this.images.review(scopeOfRequest(req), actorOf(req), parseHospitalId(rawId), parseHospitalId(rawHt), parseHospitalId(rawImage), body);
  }
}
