import { Module } from '@nestjs/common';
import { MailModule } from '../mail/mail.module';
import { PasswordLostController } from './password-lost.controller';
import { PasswordLostService } from './password-lost.service';

@Module({ imports: [MailModule], controllers: [PasswordLostController], providers: [PasswordLostService] })
export class PasswordLostModule {}
