import { Module } from '@nestjs/common';
import { SessionBridgeService } from './session-bridge.service';

@Module({ providers: [SessionBridgeService], exports: [SessionBridgeService] })
export class SessionBridgeModule {}
