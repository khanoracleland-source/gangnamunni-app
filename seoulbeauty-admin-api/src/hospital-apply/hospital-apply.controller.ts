import { Body, Controller, Get, HttpCode, Post, Query, Req, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { AdminRequest, HospitalApplyEnabledGuard, MultipartCsrfGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { HospitalApplyService } from './hospital-apply.service';
import { ApplyUpload, HospitalApplyUploadInterceptor } from './hospital-apply.upload';

/** Lists for the registration form and the duplicate-phone question. Super admin only; with the switch off every route answers 404. */
@Controller('v1/hospital-apply')
@UseGuards(HospitalApplyEnabledGuard, SuperAdminGuard)
export class HospitalApplyReadController {
  constructor(private readonly apply: HospitalApplyService) {}

  @Get('options')
  options() {
    return this.apply.options();
  }

  @Get('phone-check')
  phoneCheck(@Query('phone') phone: unknown) {
    return this.apply.phoneCheck(phone);
  }
}

/**
 * Registering a hospital (multipart: `payload` = JSON text, the rest are files named by what they are).
 * Guard order: switch (404) -> CSRF (403 csrf) -> signed in / super admin (401, 403); the body is only read after all three passed.
 */
@Controller('v1/hospital-apply')
@UseGuards(HospitalApplyEnabledGuard, MultipartCsrfGuard, SuperAdminGuard)
export class HospitalApplyWriteController {
  constructor(private readonly apply: HospitalApplyService) {}

  @Post()
  @HttpCode(200)
  @UseInterceptors(HospitalApplyUploadInterceptor)
  create(@Req() req: AdminRequest, @Body('payload') payload: unknown, @UploadedFiles() files: ApplyUpload[]) {
    return this.apply.create({ id: req.admin.id, username: req.admin.username }, payload, files);
  }
}
