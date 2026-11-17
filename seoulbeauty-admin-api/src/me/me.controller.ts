import { Controller, Get, Req, UseGuards } from '@nestjs/common';
import { AdminGuard, hospitalsDeleteEnabled, hospitalsDoctorsEnabled, hospitalsEnabled, hospitalsImagesEnabled, hospitalsTreatmentsEnabled, hospitalsTreatmentsImagesEnabled, hospitalsTreatmentsPricesEnabled, hospitalsTreatmentsQuestionsEnabled, hospitalsTreatmentsRemoveEnabled, hospitalsWriteEnabled, ScopedAdminRequest } from '../admin-auth/admin.guard';

@Controller('v1/me')
@UseGuards(AdminGuard)
export class MeController {
  /** The signed-in admin (top bar: name + role badge), what it may see, and which pages of this panel are switched on. */
  @Get()
  me(@Req() req: ScopedAdminRequest) {
    const { id, username, name, role, hospitalId, scope } = req.admin;
    return { admin: { id, username, name, role, hospitalId, scope }, features: { hospitals: hospitalsEnabled(), hospitalsWrite: hospitalsWriteEnabled(),
      // the routes behind both are super-admin only (403 for other roles); the flags only report the switches
      hospitalsDelete: hospitalsDeleteEnabled(), archive: hospitalsDeleteEnabled(),
      hospitalsImages: hospitalsImagesEnabled(), hospitalsDoctors: hospitalsDoctorsEnabled(),
      hospitalsTreatments: hospitalsTreatmentsEnabled(), hospitalsTreatmentsRemove: hospitalsTreatmentsRemoveEnabled(), hospitalsTreatmentsPrices: hospitalsTreatmentsPricesEnabled(),
      hospitalsTreatmentsQuestions: hospitalsTreatmentsQuestionsEnabled(), hospitalsTreatmentsImages: hospitalsTreatmentsImagesEnabled() } };
  }
}
