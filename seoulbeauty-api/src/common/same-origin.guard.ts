import { CanActivate, ExecutionContext, ForbiddenException, Injectable } from '@nestjs/common';
import type { Request } from 'express';

/**
 * CSRF defence for cookie-authenticated writes: the browser only adds this header from our own
 * fetch() calls, and a cross-site page cannot set it without a CORS preflight (which we refuse).
 * Combined with SameSite=Lax session cookies this blocks form-POST and cross-site fetch CSRF.
 */
@Injectable()
export class SameOriginGuard implements CanActivate {
  canActivate(ctx: ExecutionContext): boolean {
    const req = ctx.switchToHttp().getRequest<Request>();
    if (req.headers['x-requested-with'] !== 'XMLHttpRequest') throw new ForbiddenException('missing X-Requested-With');
    const origin = req.headers.origin;
    const host = req.headers['x-forwarded-host'] || req.headers.host;
    if (origin && host && !origin.endsWith(`//${host}`)) throw new ForbiddenException('cross-origin');
    return true;
  }
}
