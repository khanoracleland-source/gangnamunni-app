import { Module } from '@nestjs/common';
import { ConsultationsController } from './consultations.controller';
import { FcmClient } from '../bookings/notify/fcm.client';
import { ConsultationNotifyService } from './consultations.notify.service';
import { ConsultationsService } from './consultations.service';
import { ConsultationsWriteService } from './consultations.write.service';

@Module({
  controllers: [ConsultationsController],
  providers: [ConsultationsService, ConsultationsWriteService, ConsultationNotifyService, FcmClient],
})
export class ConsultationsModule {}
