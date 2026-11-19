import { Controller, Get, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, adminsEnabled, hospitalsDeleteEnabled, hospitalsDocumentsEnabled, hospitalsDoctorsEnabled, hospitalsEnabled, hospitalsImagesEnabled, hospitalsReviewEnabled, hospitalsTreatmentsEnabled, hospitalApplyEnabled, hospitalsTreatmentsImagesEnabled, hospitalsTreatmentsPricesEnabled, hospitalsTreatmentsQuestionsEnabled, hospitalsTreatmentsRemoveEnabled, hospitalsWriteEnabled, ScopedAdminRequest, treatmentsEnabled, treatmentsImagesEnabled, treatmentsWriteEnabled } from '../admin-auth/admin.guard';

@Controller('v1/me')
@UseGuards(AdminGuard)
export class MeController {
  /** The signed-in admin (top bar: name + role badge), what it may see, and which pages of this panel are switched on. */
  @Get()
  me(@Req() req: ScopedAdminRequest) {
    const { id, username, name, role, hospitalId, scope } = req.admin;
    return { admin: { id, username, name, role, hospitalId, scope }, features: { hospitals: hospitalsEnabled(), hospitalsWrite: hospitalsWriteEnabled(),
      // the routes behind these flags are super-admin only (403 for other roles); the flags only report the switches
      hospitalsDelete: hospitalsDeleteEnabled(), archive: hospitalsDeleteEnabled(), hospitalsReview: hospitalsReviewEnabled(),
      hospitalsImages: hospitalsImagesEnabled(), hospitalsDocuments: hospitalsDocumentsEnabled(), hospitalsDoctors: hospitalsDoctorsEnabled(),
      hospitalsTreatments: hospitalsTreatmentsEnabled(), hospitalsTreatmentsRemove: hospitalsTreatmentsRemoveEnabled(), hospitalsTreatmentsPrices: hospitalsTreatmentsPricesEnabled(),
      hospitalsTreatmentsQuestions: hospitalsTreatmentsQuestionsEnabled(), hospitalsTreatmentsImages: hospitalsTreatmentsImagesEnabled(),
      hospitalApply: hospitalApplyEnabled(), admins: adminsEnabled(), treatments: treatmentsEnabled(), treatmentsWrite: treatmentsWriteEnabled(), treatmentsImages: treatmentsImagesEnabled() } };
  }
}
