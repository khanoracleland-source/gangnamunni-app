import { Module } from '@nestjs/common';
import { MailModule } from '../mail/mail.module';
import { SessionBridgeModule } from '../session-bridge/session-bridge.module';
import { RegisterController } from './register.controller';
import { RegisterService } from './register.service';

@Module({ imports: [MailModule, SessionBridgeModule], controllers: [RegisterController], providers: [RegisterService] })
export class RegisterModule {}
