import { CanActivate, ExecutionContext, Injectable, UnauthorizedException } from '@nestjs/common';
import type { Request } from 'express';
import { AuthService, Session } from './auth.service';

export type AuthedRequest = Request & { session: Session };

/** Attaches req.session; with `required` it rejects anonymous requests (401). */
@Injectable()
export class AuthGuard implements CanActivate {
  constructor(private readonly auth: AuthService) {}
  async canActivate(ctx: ExecutionContext): Promise<boolean> {
    const req = ctx.switchToHttp().getRequest<AuthedRequest>();
    req.session = await this.auth.fromCookie(req.headers.cookie);
    return true;
  }
}

@Injectable()
export class RequireAuthGuard extends AuthGuard {
  async canActivate(ctx: ExecutionContext): Promise<boolean> {
    await super.canActivate(ctx);
    const req = ctx.switchToHttp().getRequest<AuthedRequest>();
    if (!req.session.loggedIn) throw new UnauthorizedException('login required');
    return true;
  }
}
