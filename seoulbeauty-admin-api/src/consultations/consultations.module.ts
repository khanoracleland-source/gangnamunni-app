import { Module } from '@nestjs/common';
import { ConsultationsController } from './consultations.controller';
import { ConsultationsService } from './consultations.service';
import { ConsultationsWriteService } from './consultations.write.service';

@Module({
  controllers: [ConsultationsController],
  providers: [ConsultationsService, ConsultationsWriteService],
})
export class ConsultationsModule {}
