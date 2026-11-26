import { Module } from '@nestjs/common';
import { MembersController } from './members.controller';
import { MembersPasswordController } from './members.password.controller';
import { MembersService } from './members.service';
import { MembersWriteController } from './members.write.controller';
import { MembersWriteService } from './members.write.service';

@Module({
  controllers: [MembersWriteController, MembersPasswordController, MembersController],
  providers: [MembersService, MembersWriteService],
})
export class MembersModule {}
