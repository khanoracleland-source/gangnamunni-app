import { Body, Controller, Delete, HttpCode, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, CategoryBoardWriteEnabledGuard, CsrfGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { parseHospitalId } from '../hospitals/hospitals.logic';
import { CategoryBoardService } from './category-board.service';

const actorOf = (req: AdminRequest) => ({ id: req.admin.id, name: req.admin.name });
const id = (raw: string): number => parseHospitalId(raw);

/** Writes of the home menu categories page. Guard order: write switches (404) -> CSRF (403 csrf) -> signed in / role (401, 403). */
@Controller('v1/category-board')
@UseGuards(CategoryBoardWriteEnabledGuard, CsrfGuard, SuperAdminGuard)
export class CategoryBoardWriteController {
  constructor(private readonly board: CategoryBoardService) {}

  @Post('categories/:id/treatments')
  @HttpCode(200)
  assign(@Req() req: AdminRequest, @Param('id') raw: string, @Body() body: unknown) {
    return this.board.assign(actorOf(req), id(raw), body);
  }

  @Delete('categories/:id/treatments/:treatmentId')
  @HttpCode(200)
  detach(@Req() req: AdminRequest, @Param('id') raw: string, @Param('treatmentId') tid: string) {
    return this.board.detach(actorOf(req), id(raw), id(tid));
  }
}
