import { Body, Controller, Delete, HttpCode, Param, Patch, Post, Put, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, CsrfGuard, SuperAdminGuard, TreatmentsWriteEnabledGuard } from '../admin-auth/admin.guard';
import { parseHospitalId } from '../hospitals/hospitals.logic';
import { TreatmentsWriteService, WriteActor } from './treatments.write.service';

const actorOf = (req: AdminRequest): WriteActor => ({ id: req.admin.id, name: req.admin.name });
const id = (raw: string): number => parseHospitalId(raw);

/**
 * Editing the treatment master (super admin only). Guard order: write switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403).
 * The fixed paths (bulk, order) are declared before the ":id" ones so they are never read as an id.
 */
@Controller('v1/treatments')
@UseGuards(TreatmentsWriteEnabledGuard, CsrfGuard, SuperAdminGuard)
export class TreatmentsWriteController {
  constructor(private readonly writes: TreatmentsWriteService) {}

  @Post()
  @HttpCode(200)
  create(@Req() req: AdminRequest, @Body() body: unknown) {
    return this.writes.create(actorOf(req), body);
  }

  @Post('bulk')
  @HttpCode(200)
  bulk(@Req() req: AdminRequest, @Body() body: unknown) {
    return this.writes.bulk(actorOf(req), body);
  }

  @Put('order')
  reorder(@Req() req: AdminRequest, @Body() body: unknown) {
    return this.writes.reorder(actorOf(req), body);
  }

  @Patch(':id')
  update(@Req() req: AdminRequest, @Param('id') raw: string, @Body() body: unknown) {
    return this.writes.update(actorOf(req), id(raw), body);
  }

  @Post(':id/active')
  @HttpCode(200)
  setActive(@Req() req: AdminRequest, @Param('id') raw: string, @Body() body: unknown) {
    return this.writes.setActive(actorOf(req), id(raw), body);
  }

  @Delete(':id')
  @HttpCode(200)
  remove(@Req() req: AdminRequest, @Param('id') raw: string) {
    return this.writes.remove(actorOf(req), id(raw));
  }

  @Post(':id/aliases')
  @HttpCode(200)
  addAlias(@Req() req: AdminRequest, @Param('id') raw: string, @Body() body: unknown) {
    return this.writes.addAlias(actorOf(req), id(raw), body);
  }

  @Delete(':id/aliases/:aliasId')
  @HttpCode(200)
  removeAlias(@Req() req: AdminRequest, @Param('id') raw: string, @Param('aliasId') rawAlias: string) {
    return this.writes.removeAlias(actorOf(req), id(raw), id(rawAlias));
  }
}
