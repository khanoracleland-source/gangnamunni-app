import { Module } from '@nestjs/common';
import { PasswordLostModule } from '../password-lost/password-lost.module';
import { AccountController } from './account.controller';
import { AccountSecurityController } from './account-security.controller';
import { AccountSecurityNativeService } from './account-security-native.service';
import { AccountSecurityService } from './account-security.service';
import { MailModule } from '../mail/mail.module';
import { AccountService } from './account.service';

@Module({ imports: [PasswordLostModule, MailModule], controllers: [AccountController, AccountSecurityController], providers: [AccountService, AccountSecurityService, AccountSecurityNativeService] })
export class AccountModule {}
