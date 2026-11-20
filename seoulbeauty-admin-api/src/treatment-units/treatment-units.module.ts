import { Module } from '@nestjs/common';
import { TreatmentUnitsController } from './treatment-units.controller';
import { TreatmentUnitsService } from './treatment-units.service';
import { TreatmentUnitsWriteService } from './treatment-units.write.service';

@Module({
  controllers: [TreatmentUnitsController],
  providers: [TreatmentUnitsService, TreatmentUnitsWriteService],
})
export class TreatmentUnitsModule {}
