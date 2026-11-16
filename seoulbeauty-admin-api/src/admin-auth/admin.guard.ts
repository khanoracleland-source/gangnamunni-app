import { CanActivate, ExecutionContext, ForbiddenException, Injectable, NotFoundException, UnauthorizedException } from '@nestjs/common';
import type { Request, Response } from 'express';
import { AdminSessionService, AdminUser } from './admin-session.service';

export type AdminRequest = Request & { admin: AdminUser };

/** What a signed-in admin may see: every hospital ('all') or only the listed ones ('own'; an empty list means nothing). */
export interface AdminScope {
  scope: 'all' | 'own';
  hospitalIds: number[];
}
export type ScopedAdmin = AdminUser & AdminScope;
export type ScopedAdminRequest = Request & { admin: ScopedAdmin };

export const SUPER_ADMIN_ROLE = 'super_admin';
/** Accounts bound to one hospital (gangnamunni_admins.hospital_id). */
export const HOSPITAL_ROLES = ['hospital_super_admin', 'hospital_admin'];
/** Read per request so the switch needs no code change; anything but the exact value "1" is off. */
export const adminPanelEnabled = (): boolean => process.env.ADMIN_PANEL_ENABLED === '1';
/** Hospital pages (/v1/hospitals*) and the entry of hospital roles. Same rule: only the exact value "1" turns it on. */
export const hospitalsEnabled = (): boolean => process.env.ADMIN_HOSPITALS_ENABLED === '1';
/** Editing hospitals. Needs the hospital pages on as well; only the exact value "1" turns it on. */
export const hospitalsWriteEnabled = (): boolean => hospitalsEnabled() && process.env.ADMIN_HOSPITALS_WRITE === '1';

/**
 * Scope of an admin, or null when the role may not use this panel at all.
 * super_admin sees everything. A hospital role sees only the hospital its account is bound to, and only while the
 * hospital pages are switched on; without a bound hospital the list is empty (fail closed, never "all").
 */
export function scopeOf(admin: AdminUser, hospitalsOn: boolean = hospitalsEnabled()): AdminScope | null {
  if (admin.role === SUPER_ADMIN_ROLE) return { scope: 'all', hospitalIds: [] };
  if (hospitalsOn && HOSPITAL_ROLES.includes(admin.role)) {
    const id = admin.hospitalId;
    return { scope: 'own', hospitalIds: typeof id === 'number' && Number.isInteger(id) && id > 0 ? [id] : [] };
  }
  return null;
}

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

/**
 * A signed-in super admin, or a hospital role while ADMIN_HOSPITALS_ENABLED is on; attaches req.admin with its scope.
 * 401 not signed in, 403 any other role (and hospital roles while the switch is off), 503 when PHP cannot be asked.
 */
@Injectable()
export class AdminGuard implements CanActivate {
  constructor(private readonly sessions: AdminSessionService) {}
  async canActivate(ctx: ExecutionContext): Promise<boolean> {
    if (!adminPanelEnabled()) throw new NotFoundException({ error: 'not_found' });
    const req = ctx.switchToHttp().getRequest<ScopedAdminRequest>();
    const s = await this.sessions.fromCookie(req.headers.cookie);
    if (!s.loggedIn || !s.admin) throw new UnauthorizedException({ error: 'not_logged_in' });
    const scope = scopeOf(s.admin);
    if (!scope) throw new ForbiddenException({ error: 'forbidden' });
    req.admin = { ...s.admin, ...scope };
    return true;
  }
}

/** With ADMIN_HOSPITALS_ENABLED off the hospital routes answer 404 as if they did not exist; PHP is not asked. */
@Injectable()
export class HospitalsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the write switch off the writing hospital routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class HospitalsWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Marker header the admin web app sends with every write; a plain cross-site form or fetch cannot set it. */
export const CSRF_HEADER_VALUE = 'admin-next';

/**
 * CSRF protection of the writing routes: JSON content type, the marker header, and, when the browser sent an Origin,
 * an Origin host equal to the host the request was addressed to (X-Forwarded-Host when proxied, else Host).
 * Anything else answers 403 {"error":"csrf"}.
 */
@Injectable()
export class CsrfGuard implements CanActivate {
  canActivate(ctx: ExecutionContext): boolean {
    const req = ctx.switchToHttp().getRequest<Request>();
    const first = (v: string | string[] | undefined): string => (Array.isArray(v) ? v[0] : v || '').split(',')[0].trim().toLowerCase();
    const type = first(req.headers['content-type']).split(';')[0].trim();
    const marker = first(req.headers['x-requested-with']);
    let ok = type === 'application/json' && marker === CSRF_HEADER_VALUE;
    const origin = first(req.headers.origin);
    if (ok && origin) {
      let originHost = '';
      try {
        originHost = new URL(origin).host.toLowerCase();
      } catch {
        originHost = '';
      }
      const target = first(req.headers['x-forwarded-host']) || first(req.headers.host);
      ok = originHost !== '' && originHost === target;
    }
    if (!ok) throw new ForbiddenException({ error: 'csrf' });
    return true;
  }
}

/** Runs after AdminGuard: only a super admin passes (hospital roles may edit their own hospital but nothing else). */
@Injectable()
export class SuperAdminRoleGuard implements CanActivate {
  canActivate(ctx: ExecutionContext): boolean {
    const req = ctx.switchToHttp().getRequest<ScopedAdminRequest>();
    if (req.admin?.role !== SUPER_ADMIN_ROLE) throw new ForbiddenException({ error: 'forbidden' });
    return true;
  }
}
