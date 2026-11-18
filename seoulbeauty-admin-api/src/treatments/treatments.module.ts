import { Module } from '@nestjs/common';
import { TreatmentsImageController } from './treatments.image.controller';
import { TreatmentsImageService } from './treatments.image.service';
import { TreatmentsController } from './treatments.controller';
import { TreatmentsMasterController } from './treatments.master.controller';
import { TreatmentsMasterService } from './treatments.master.service';
import { TreatmentsService } from './treatments.service';
import { TreatmentsWriteController } from './treatments.write.controller';
import { TreatmentsWriteService } from './treatments.write.service';

@Module({
  controllers: [TreatmentsController, TreatmentsWriteController, TreatmentsMasterController, TreatmentsImageController],
  providers: [TreatmentsService, TreatmentsWriteService, TreatmentsMasterService, TreatmentsImageService],
})
export class TreatmentsModule {}
