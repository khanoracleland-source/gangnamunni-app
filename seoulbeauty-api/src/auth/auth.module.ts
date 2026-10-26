import { Global, Module } from '@nestjs/common';
import { AuthService } from './auth.service';
import { AuthGuard, RequireAuthGuard } from './auth.guard';
import { MeController } from './me.controller';

@Global()
@Module({ providers: [AuthService, AuthGuard, RequireAuthGuard], controllers: [MeController], exports: [AuthService, AuthGuard, RequireAuthGuard] })
export class AuthModule {}
