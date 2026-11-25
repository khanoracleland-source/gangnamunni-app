import { Module } from '@nestjs/common';
import { TreatmentsService } from '../treatments/treatments.service';
import { CategoryBoardController } from './category-board.controller';
import { CategoryBoardService } from './category-board.service';
import { CategoryBoardWriteController } from './category-board.write.controller';

@Module({ controllers: [CategoryBoardController, CategoryBoardWriteController], providers: [TreatmentsService, CategoryBoardService] })
export class CategoryBoardModule {}
