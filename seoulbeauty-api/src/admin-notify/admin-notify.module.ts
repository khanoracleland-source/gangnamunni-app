import { Module } from '@nestjs/common';
import { AdminNotifyService } from './admin-notify.service';
import { FcmClient } from './fcm.client';

@Module({ providers: [FcmClient, AdminNotifyService], exports: [AdminNotifyService, FcmClient] })
export class AdminNotifyModule {}
