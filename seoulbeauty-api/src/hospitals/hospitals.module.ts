import { Module } from '@nestjs/common';
import { HospitalsController } from './hospitals.controller';
import { HospitalsService } from './hospitals.service';
import { ReviewsModule } from '../reviews/reviews.module';

@Module({ imports: [ReviewsModule], controllers: [HospitalsController], providers: [HospitalsService] })
export class HospitalsModule {}
