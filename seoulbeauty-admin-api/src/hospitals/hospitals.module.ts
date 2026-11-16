import { Module } from '@nestjs/common';
import { HospitalsController } from './hospitals.controller';
import { HospitalsDeleteController, HospitalsDeleteCheckController, HospitalsStatsController } from './hospitals.delete.controller';
import { HospitalsDeleteService } from './hospitals.delete.service';
import { HospitalsService } from './hospitals.service';
import { HospitalsWriteController } from './hospitals.write.controller';
import { HospitalsWriteService } from './hospitals.write.service';

@Module({
  controllers: [HospitalsController, HospitalsStatsController, HospitalsDeleteCheckController, HospitalsDeleteController, HospitalsWriteController],
  providers: [HospitalsService, HospitalsWriteService, HospitalsDeleteService],
})
export class HospitalsModule {}
