import { Module } from '@nestjs/common';
import { CommunityController } from './community.controller';
import { CommunityService } from './community.service';
import { CommunityWriteController } from './community.write.controller';
import { CommunityWriteService } from './community.write.service';

// Write controller first: its fixed paths (form, blocks, reports) must match before `:id`.
@Module({ controllers: [CommunityWriteController, CommunityController], providers: [CommunityService, CommunityWriteService] })
export class CommunityModule {}
