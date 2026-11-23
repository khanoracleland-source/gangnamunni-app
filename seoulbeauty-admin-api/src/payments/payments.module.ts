import { Module } from '@nestjs/common';
import { PaymentFeesController } from './payment-fees.controller';
import { PaymentFeesService } from './payment-fees.service';
import { PaymentsController } from './payments.controller';
import { PaymentsService } from './payments.service';

@Module({ controllers: [PaymentsController, PaymentFeesController], providers: [PaymentsService, PaymentFeesService] })
export class PaymentsModule {}
