import { Module } from '@nestjs/common';
import { TreatmentsService } from '../treatments/treatments.service';
import { CategoryBoardController } from './category-board.controller';
import { CategoryBoardService } from './category-board.service';

@Module({ controllers: [CategoryBoardController], providers: [TreatmentsService, CategoryBoardService] })
export class CategoryBoardModule {}
