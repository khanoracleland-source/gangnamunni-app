import { Module } from '@nestjs/common';
import { HospitalsController } from './hospitals.controller';
import { HospitalsService } from './hospitals.service';
import { HospitalsWriteController } from './hospitals.write.controller';
import { HospitalsWriteService } from './hospitals.write.service';

@Module({ controllers: [HospitalsController, HospitalsWriteController], providers: [HospitalsService, HospitalsWriteService] })
export class HospitalsModule {}
