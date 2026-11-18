import { Module } from '@nestjs/common';
import { TreatmentsController } from './treatments.controller';
import { TreatmentsService } from './treatments.service';
import { TreatmentsWriteController } from './treatments.write.controller';
import { TreatmentsWriteService } from './treatments.write.service';

@Module({ controllers: [TreatmentsController, TreatmentsWriteController], providers: [TreatmentsService, TreatmentsWriteService] })
export class TreatmentsModule {}
