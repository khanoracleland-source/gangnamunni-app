import { Module } from '@nestjs/common';
import { DoctorsController } from './doctors.controller';
import { DoctorsService } from './doctors.service';
import { DoctorEditUploadInterceptor } from './doctors.upload';
import { DoctorsWriteController } from './doctors.write.controller';
import { DoctorsWriteService } from './doctors.write.service';

@Module({
  // the write controller comes first so the fixed `:id/retire` and the PATCH are matched before anything else
  controllers: [DoctorsWriteController, DoctorsController],
  providers: [DoctorsService, DoctorsWriteService, DoctorEditUploadInterceptor],
})
export class DoctorsModule {}
