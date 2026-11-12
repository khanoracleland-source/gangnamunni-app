import { Global, Module } from '@nestjs/common';
import { AuthService } from './auth.service';
import { AuthGuard, RequireAuthGuard } from './auth.guard';
import { MeController } from './me.controller';
import { NestSessionService } from './nest-session.service';
import { LogoutController } from './logout.controller';

@Global()
@Module({ providers: [NestSessionService, AuthService, AuthGuard, RequireAuthGuard], controllers: [MeController, LogoutController], exports: [NestSessionService, AuthService, AuthGuard, RequireAuthGuard] })
export class AuthModule {}
