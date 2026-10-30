import { Module } from '@nestjs/common';
import { MailModule } from '../mail/mail.module';
import { SessionBridgeModule } from '../session-bridge/session-bridge.module';
import { SocialController } from './social.controller';
import { SocialRegisterService } from './social-register.service';
import { SocialService } from './social.service';

@Module({ imports: [MailModule, SessionBridgeModule], controllers: [SocialController], providers: [SocialService, SocialRegisterService], exports: [SocialService] })
export class SocialModule {}
