import { Injectable } from '@nestjs/common';
import { TreatmentsService } from '../treatments/treatments.service';
import { BoardAnswer, buildBoard } from './category-board.logic';

/** Reads the treatment master and groups it for the home menu page. */
@Injectable()
export class CategoryBoardService {
  constructor(private readonly reads: TreatmentsService) {}

  async get(): Promise<BoardAnswer> {
    return buildBoard(await this.reads.list());
  }
}
