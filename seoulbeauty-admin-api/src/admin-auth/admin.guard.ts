import { CanActivate, ExecutionContext, ForbiddenException, Injectable, NotFoundException, UnauthorizedException } from '@nestjs/common';
import type { Request, Response } from 'express';
import { AdminSessionService, AdminUser } from './admin-session.service';

export type AdminRequest = Request & { admin: AdminUser };

export const SUPER_ADMIN_ROLE = 'super_admin';
/** Read per request so the switch needs no code change; anything but the exact value "1" is off. */
export const adminPanelEnabled = (): boolean => process.env.ADMIN_PANEL_ENABLED === '1';

/**
 * Global switch (registered as APP_GUARD): with ADMIN_PANEL_ENABLED off every /v1 route answers 404 as if it did not exist.
 * /health stays reachable. Also marks every /v1 answer, errors included, as uncacheable.
 */
@Injectable()
export class AdminPanelGuard implements CanActivate {
  canActivate(ctx: ExecutionContext): boolean {
    const http = ctx.switchToHttp();
    const path = http.getRequest<Request>().path || '';
    if (path !== '/v1' && !path.startsWith('/v1/')) return true;
    http.getResponse<Response>().setHeader('Cache-Control', 'private, no-store');
    if (!adminPanelEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Only a signed-in super admin passes; attaches req.admin. 401 not signed in, 403 any other role, 503 when PHP cannot be asked. */
@Injectable()
export class SuperAdminGuard implements CanActivate {
  constructor(private readonly sessions: AdminSessionService) {}
  async canActivate(ctx: ExecutionContext): Promise<boolean> {
    // Repeated here so a route is never served with the switch off, whatever the guard order.
    if (!adminPanelEnabled()) throw new NotFoundException({ error: 'not_found' });
    const req = ctx.switchToHttp().getRequest<AdminRequest>();
    const s = await this.sessions.fromCookie(req.headers.cookie);
    if (!s.loggedIn || !s.admin) throw new UnauthorizedException({ error: 'not_logged_in' });
    if (s.admin.role !== SUPER_ADMIN_ROLE) throw new ForbiddenException({ error: 'forbidden' });
    req.admin = s.admin;
    return true;
  }
}
