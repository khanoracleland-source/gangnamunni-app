import { Body, Controller, Delete, HttpCode, Param, Patch, Post, Put, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, CsrfGuard, SuperAdminGuard, TreatmentsWriteEnabledGuard } from '../admin-auth/admin.guard';
import { parseHospitalId } from '../hospitals/hospitals.logic';
import { TreatmentsMasterService } from './treatments.master.service';
import { WriteActor } from './treatments.rw';

const actorOf = (req: AdminRequest): WriteActor => ({ id: req.admin.id, name: req.admin.name });
const id = (raw: string): number => parseHospitalId(raw);

/** Categories, home table mode and conflict pairs. Guard order: write switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). "order" is declared before ":id". */
@Controller('v1')
@UseGuards(TreatmentsWriteEnabledGuard, CsrfGuard, SuperAdminGuard)
export class TreatmentsMasterController {
  constructor(private readonly master: TreatmentsMasterService) {}

  @Post('treatment-categories')
  @HttpCode(200)
  createCategory(@Req() req: AdminRequest, @Body() body: unknown) {
    return this.master.createCategory(actorOf(req), body);
  }

  @Put('treatment-categories/order')
  reorderCategories(@Req() req: AdminRequest, @Body() body: unknown) {
    return this.master.reorderCategories(actorOf(req), body);
  }

  @Patch('treatment-categories/:id')
  updateCategory(@Req() req: AdminRequest, @Param('id') raw: string, @Body() body: unknown) {
    return this.master.updateCategory(actorOf(req), id(raw), body);
  }

  @Post('treatment-categories/:id/active')
  @HttpCode(200)
  setCategoryActive(@Req() req: AdminRequest, @Param('id') raw: string, @Body() body: unknown) {
    return this.master.setCategoryActive(actorOf(req), id(raw), body);
  }

  @Delete('treatment-categories/:id')
  @HttpCode(200)
  removeCategory(@Req() req: AdminRequest, @Param('id') raw: string) {
    return this.master.removeCategory(actorOf(req), id(raw));
  }

  @Put('treatment-settings/table-mode')
  setTableMode(@Req() req: AdminRequest, @Body() body: unknown) {
    return this.master.setTableMode(actorOf(req), body);
  }

  @Post('treatment-conflicts')
  @HttpCode(200)
  addConflict(@Req() req: AdminRequest, @Body() body: unknown) {
    return this.master.addConflict(actorOf(req), body);
  }

  @Delete('treatment-conflicts/:id')
  @HttpCode(200)
  removeConflict(@Req() req: AdminRequest, @Param('id') raw: string) {
    return this.master.removeConflict(actorOf(req), id(raw));
  }
}
