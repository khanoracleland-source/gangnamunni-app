import { Module } from '@nestjs/common';
import { AccountController } from './account.controller';
import { AccountSecurityController } from './account-security.controller';
import { AccountSecurityService } from './account-security.service';
import { AccountService } from './account.service';

@Module({ controllers: [AccountController, AccountSecurityController], providers: [AccountService, AccountSecurityService] })
export class AccountModule {}
