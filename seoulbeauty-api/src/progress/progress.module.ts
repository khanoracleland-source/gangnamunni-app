import { Module } from '@nestjs/common';
import { PointsModule } from '../points/points.module';
import { ProgressController } from './progress.controller';
import { ProgressService } from './progress.service';

@Module({ imports: [PointsModule], controllers: [ProgressController], providers: [ProgressService] })
export class ProgressModule {}
