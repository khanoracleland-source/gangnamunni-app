import { Module } from '@nestjs/common';
import { PasswordLostModule } from '../password-lost/password-lost.module';
import { AccountController } from './account.controller';
import { AccountSecurityController } from './account-security.controller';
import { AccountSecurityService } from './account-security.service';
import { AccountService } from './account.service';

@Module({ imports: [PasswordLostModule], controllers: [AccountController, AccountSecurityController], providers: [AccountService, AccountSecurityService] })
export class AccountModule {}
