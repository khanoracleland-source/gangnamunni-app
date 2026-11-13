import { Global, Module } from '@nestjs/common';
import { APP_FILTER, APP_GUARD } from '@nestjs/core';
import { AdminSessionService } from './admin-session.service';
import { AdminPanelGuard, SuperAdminGuard } from './admin.guard';
import { V1ExceptionFilter } from './v1-exception.filter';

@Global()
@Module({
  providers: [AdminSessionService, SuperAdminGuard, { provide: APP_GUARD, useClass: AdminPanelGuard }, { provide: APP_FILTER, useClass: V1ExceptionFilter }],
  exports: [AdminSessionService, SuperAdminGuard],
})
export class AdminAuthModule {}
