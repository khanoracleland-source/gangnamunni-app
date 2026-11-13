import { Module } from '@nestjs/common';
import { PushTokenController, PushTokenEnabledGuard } from './push-token.controller';
import { PushTokenService } from './push-token.service';

@Module({ controllers: [PushTokenController], providers: [PushTokenService, PushTokenEnabledGuard] })
export class PushTokenModule {}
