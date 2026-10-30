import { Module } from '@nestjs/common';
import { SessionBridgeModule } from '../session-bridge/session-bridge.module';
import { LoginController } from './login.controller';
import { LoginService } from './login.service';

@Module({ imports: [SessionBridgeModule], controllers: [LoginController], providers: [LoginService] })
export class LoginModule {}
