import { Module } from '@nestjs/common';
import { SessionBridgeModule } from '../session-bridge/session-bridge.module';
import { SocialController } from './social.controller';
import { SocialService } from './social.service';

@Module({ imports: [SessionBridgeModule], controllers: [SocialController], providers: [SocialService], exports: [SocialService] })
export class SocialModule {}
