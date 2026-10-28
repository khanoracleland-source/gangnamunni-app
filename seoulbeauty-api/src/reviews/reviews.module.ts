import { Module } from '@nestjs/common';
import { AdminNotifyModule } from '../admin-notify/admin-notify.module';
import { PointsModule } from '../points/points.module';
import { ReviewsController } from './reviews.controller';
import { ReviewsService } from './reviews.service';
import { ReviewsWriteController } from './reviews.write.controller';
import { ReviewsWriteService } from './reviews.write.service';

@Module({
  imports: [AdminNotifyModule, PointsModule],
  controllers: [ReviewsWriteController, ReviewsController],
  providers: [ReviewsService, ReviewsWriteService],
  exports: [ReviewsService],
})
export class ReviewsModule {}
