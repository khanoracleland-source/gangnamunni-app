import { Controller, Get, UseGuards } from '@nestjs/common';
import { CategoryBoardEnabledGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { CategoryBoardService } from './category-board.service';

/** The home menu categories page (super admin only). Guard order: switch (404) -> signed in / role (401, 403). */
@Controller('v1/category-board')
@UseGuards(CategoryBoardEnabledGuard, SuperAdminGuard)
export class CategoryBoardController {
  constructor(private readonly board: CategoryBoardService) {}

  /** Categories in home menu order, each with its treatments, plus the treatments that belong to no category. */
  @Get()
  get() {
    return this.board.get();
  }
}
