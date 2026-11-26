import { Module } from '@nestjs/common';
import { MembersActivityController } from './members.activity.controller';
import { MembersActivityService } from './members.activity.service';
import { MembersController } from './members.controller';
import { MembersPasswordController } from './members.password.controller';
import { MembersService } from './members.service';
import { MembersWriteController } from './members.write.controller';
import { MembersWriteService } from './members.write.service';

@Module({
  controllers: [MembersActivityController, MembersWriteController, MembersPasswordController, MembersController],
  providers: [MembersActivityService, MembersService, MembersWriteService],
})
export class MembersModule {}
