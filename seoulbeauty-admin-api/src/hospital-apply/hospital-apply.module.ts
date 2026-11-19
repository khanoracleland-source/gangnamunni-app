import { Module } from '@nestjs/common';
import { HospitalApplyReadController, HospitalApplyWriteController } from './hospital-apply.controller';
import { HospitalApplyService } from './hospital-apply.service';

@Module({
  controllers: [HospitalApplyReadController, HospitalApplyWriteController],
  providers: [HospitalApplyService],
})
export class HospitalApplyModule {}
