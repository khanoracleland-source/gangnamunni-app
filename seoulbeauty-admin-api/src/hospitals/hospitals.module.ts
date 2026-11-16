import { Module } from '@nestjs/common';
import { HospitalsController } from './hospitals.controller';
import { HospitalsDeleteController, HospitalsDeleteCheckController, HospitalsStatsController } from './hospitals.delete.controller';
import { HospitalsDeleteService } from './hospitals.delete.service';
import { HospitalsDoctorsReadController, HospitalsDoctorsWriteController } from './hospitals.doctors.controller';
import { HospitalsDoctorsService } from './hospitals.doctors.service';
import { HospitalsImagesReadController, HospitalsImagesWriteController } from './hospitals.images.controller';
import { HospitalsImagesService } from './hospitals.images.service';
import { HospitalsService } from './hospitals.service';
import { HospitalsWriteController } from './hospitals.write.controller';
import { HospitalsWriteService } from './hospitals.write.service';

@Module({
  controllers: [HospitalsController, HospitalsStatsController, HospitalsDeleteCheckController, HospitalsDeleteController, HospitalsWriteController, HospitalsImagesReadController, HospitalsImagesWriteController, HospitalsDoctorsReadController, HospitalsDoctorsWriteController],
  providers: [HospitalsService, HospitalsWriteService, HospitalsDeleteService, HospitalsImagesService, HospitalsDoctorsService],
})
export class HospitalsModule {}
