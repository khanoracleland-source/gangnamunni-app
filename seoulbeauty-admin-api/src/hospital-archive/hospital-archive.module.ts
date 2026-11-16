import { Module } from '@nestjs/common';
import { HospitalArchiveAckController, HospitalArchiveController } from './hospital-archive.controller';
import { HospitalArchiveService } from './hospital-archive.service';

@Module({ controllers: [HospitalArchiveController, HospitalArchiveAckController], providers: [HospitalArchiveService] })
export class HospitalArchiveModule {}
