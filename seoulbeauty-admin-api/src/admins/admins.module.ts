import { Module } from '@nestjs/common';
import { AdminsController } from './admins.controller';
import { AdminsService } from './admins.service';
import { AdminsWriteController } from './admins.write.controller';
import { AdminsWriteService } from './admins.write.service';

@Module({
  controllers: [AdminsController, AdminsWriteController],
  providers: [AdminsService, AdminsWriteService],
})
export class AdminsModule {}
