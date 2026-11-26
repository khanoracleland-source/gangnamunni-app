import { Module } from '@nestjs/common';
import { PaymentFeesController } from './payment-fees.controller';
import { PaymentFeesService } from './payment-fees.service';
import { PaymentsController } from './payments.controller';
import { PaymentRefundService } from './payments.refund.service';
import { PaymentsService } from './payments.service';

@Module({ controllers: [PaymentsController, PaymentFeesController], providers: [PaymentsService, PaymentFeesService, PaymentRefundService] })
export class PaymentsModule {}
