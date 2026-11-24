import { Controller, Get, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, adminsEnabled, doctorsEnabled, doctorsWriteEnabled, doctorsOrderEnabled, doctorsDeleteEnabled, bookingsEnabled, bookingsWriteEnabled, paymentsEnabled, adminsWriteEnabled, hospitalsDeleteEnabled, hospitalsDocumentsEnabled, hospitalsDoctorsEnabled, hospitalsEnabled, hospitalsImagesEnabled, hospitalsReviewEnabled, hospitalsOrderEnabled, hospitalsTreatmentsEnabled, hospitalApplyEnabled, hospitalsTreatmentsImagesEnabled, hospitalsTreatmentsPricesEnabled, hospitalsTreatmentsQuestionsEnabled, hospitalsTreatmentsRemoveEnabled, hospitalsWriteEnabled, ScopedAdminRequest, treatmentsEnabled, treatmentsImagesEnabled, treatmentsWriteEnabled, treatmentUnitsEnabled, treatmentUnitsWriteEnabled, moderationEnabled, moderationWriteEnabled } from '../admin-auth/admin.guard';

@Controller('v1/me')
@UseGuards(AdminGuard)
export class MeController {
  /** The signed-in admin (top bar: name + role badge), what it may see, and which pages of this panel are switched on. */
  @Get()
  me(@Req() req: ScopedAdminRequest) {
    const { id, username, name, role, hospitalId, scope } = req.admin;
    return { admin: { id, username, name, role, hospitalId, scope }, features: { hospitals: hospitalsEnabled(), hospitalsWrite: hospitalsWriteEnabled(),
      // the routes behind these flags are super-admin only (403 for other roles); the flags only report the switches
      hospitalsDelete: hospitalsDeleteEnabled(), archive: hospitalsDeleteEnabled(), hospitalsReview: hospitalsReviewEnabled(), hospitalsOrder: hospitalsOrderEnabled(), doctors: doctorsEnabled(), doctorsWrite: doctorsWriteEnabled(), doctorsOrder: doctorsOrderEnabled(), doctorsDelete: doctorsDeleteEnabled(),
      hospitalsImages: hospitalsImagesEnabled(), hospitalsDocuments: hospitalsDocumentsEnabled(), hospitalsDoctors: hospitalsDoctorsEnabled(),
      hospitalsTreatments: hospitalsTreatmentsEnabled(), hospitalsTreatmentsRemove: hospitalsTreatmentsRemoveEnabled(), hospitalsTreatmentsPrices: hospitalsTreatmentsPricesEnabled(),
      hospitalsTreatmentsQuestions: hospitalsTreatmentsQuestionsEnabled(), hospitalsTreatmentsImages: hospitalsTreatmentsImagesEnabled(),
      hospitalApply: hospitalApplyEnabled(), admins: adminsEnabled(), adminsWrite: adminsWriteEnabled(), treatments: treatmentsEnabled(), treatmentsWrite: treatmentsWriteEnabled(), treatmentsImages: treatmentsImagesEnabled(),
      treatmentUnits: treatmentUnitsEnabled(), treatmentUnitsWrite: treatmentUnitsWriteEnabled(),
      bookings: bookingsEnabled(), bookingsWrite: bookingsWriteEnabled(), payments: paymentsEnabled(),
      moderation: moderationEnabled(), moderationWrite: moderationWriteEnabled() } };
  }
}
