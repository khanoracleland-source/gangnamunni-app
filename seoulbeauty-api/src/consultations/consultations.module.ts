import { Module } from '@nestjs/common';
import { ConsultationsController } from './consultations.controller';

@Module({ controllers: [ConsultationsController] })
export class ConsultationsModule {}
