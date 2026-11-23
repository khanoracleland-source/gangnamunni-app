import { Module } from '@nestjs/common';
import { HospitalsController } from './hospitals.controller';
import { HospitalsDeleteController, HospitalsDeleteCheckController, HospitalsStatsController } from './hospitals.delete.controller';
import { HospitalsDeleteService } from './hospitals.delete.service';
import { HospitalsDocumentsDeleteController, HospitalsDocumentsUploadController } from './hospitals.documents.controller';
import { HospitalsDocumentsService } from './hospitals.documents.service';
import { HospitalsDoctorsReadController, HospitalsDoctorsWriteController } from './hospitals.doctors.controller';
import { HospitalsDoctorsService } from './hospitals.doctors.service';
import { HospitalsImagesReadController, HospitalsImagesWriteController } from './hospitals.images.controller';
import { HospitalsImagesService } from './hospitals.images.service';
import { HospitalsTreatmentsPricesController, HospitalsTreatmentsQuestionsController, HospitalsTreatmentsReadController, HospitalsTreatmentsRemoveController, HospitalsTreatmentsWriteController } from './hospitals.treatments.controller';
import { HospitalsTreatmentsService } from './hospitals.treatments.service';
import { HospitalsTreatmentImagesReadController, HospitalsTreatmentImagesUploadController, HospitalsTreatmentImagesWriteController } from './hospitals.treatments.images.controller';
import { HospitalsTreatmentImagesService } from './hospitals.treatments.images.service';
import { HospitalsOrderController, HospitalsOrderListController } from './hospitals.order.controller';
import { HospitalsOrderService } from './hospitals.order.service';
import { HospitalsReviewController } from './hospitals.review.controller';
import { HospitalsReviewService } from './hospitals.review.service';
import { HospitalsService } from './hospitals.service';
import { HospitalsWriteController } from './hospitals.write.controller';
import { HospitalsWriteService } from './hospitals.write.service';

@Module({
  controllers: [HospitalsOrderListController, HospitalsController, HospitalsStatsController, HospitalsDeleteCheckController, HospitalsDeleteController, HospitalsReviewController, HospitalsOrderController, HospitalsWriteController, HospitalsImagesReadController, HospitalsImagesWriteController, HospitalsDocumentsUploadController, HospitalsDocumentsDeleteController, HospitalsDoctorsReadController, HospitalsDoctorsWriteController, HospitalsTreatmentsReadController, HospitalsTreatmentsWriteController, HospitalsTreatmentsRemoveController, HospitalsTreatmentsPricesController, HospitalsTreatmentsQuestionsController, HospitalsTreatmentImagesReadController, HospitalsTreatmentImagesUploadController, HospitalsTreatmentImagesWriteController],
  providers: [HospitalsService, HospitalsWriteService, HospitalsDeleteService, HospitalsReviewService, HospitalsOrderService, HospitalsImagesService, HospitalsDocumentsService, HospitalsDoctorsService, HospitalsTreatmentsService, HospitalsTreatmentImagesService],
})
export class HospitalsModule {}
