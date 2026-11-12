import { Module } from '@nestjs/common';
import { PushTokenCleanupService } from './push-token-cleanup.service';

/** Housekeeping jobs that used to be PHP crons. */
@Module({ providers: [PushTokenCleanupService], exports: [PushTokenCleanupService] })
export class MaintenanceModule {}
