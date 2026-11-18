import { Module } from '@nestjs/common';
import { TreatmentsController } from './treatments.controller';
import { TreatmentsMasterController } from './treatments.master.controller';
import { TreatmentsMasterService } from './treatments.master.service';
import { TreatmentsService } from './treatments.service';
import { TreatmentsWriteController } from './treatments.write.controller';
import { TreatmentsWriteService } from './treatments.write.service';

@Module({
  controllers: [TreatmentsController, TreatmentsWriteController, TreatmentsMasterController],
  providers: [TreatmentsService, TreatmentsWriteService, TreatmentsMasterService],
})
export class TreatmentsModule {}
