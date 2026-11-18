import { Controller, HttpCode, Param, Post, Req, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { AdminRequest, MultipartCsrfGuard, SuperAdminGuard, TreatmentsImagesEnabledGuard } from '../admin-auth/admin.guard';
import { parseHospitalId } from '../hospitals/hospitals.logic';
import { TreatmentImageUploadInterceptor, UploadedTreatmentImages } from '../hospitals/hospitals.treatments.images.upload';
import { TreatmentsImageService } from './treatments.image.service';
import { WriteActor } from './treatments.rw';

const actorOf = (req: AdminRequest): WriteActor => ({ id: req.admin.id, name: req.admin.name });

/**
 * The default image of a treatment (multipart: one file in the field `image`).
 * Guard order: images switch (404) -> multipart CSRF (403 csrf) -> signed in / super admin (401, 403); the body is read only after all three passed.
 * An image address typed by hand is not uploaded: it goes with the normal change (defaultImageUrl).
 */
@Controller('v1/treatments')
@UseGuards(TreatmentsImagesEnabledGuard, MultipartCsrfGuard, SuperAdminGuard)
export class TreatmentsImageController {
  constructor(private readonly images: TreatmentsImageService) {}

  @Post(':id/image')
  @HttpCode(200)
  @UseInterceptors(TreatmentImageUploadInterceptor)
  upload(@Req() req: AdminRequest, @Param('id') raw: string, @UploadedFiles() files: UploadedTreatmentImages) {
    return this.images.upload(actorOf(req), parseHospitalId(raw), files);
  }
}
