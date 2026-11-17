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
/** Deleting hospitals and the archive of deleted ones. Needs the hospital pages and editing on as well; only the exact value "1" turns it on. */
export const hospitalsDeleteEnabled = (): boolean => hospitalsWriteEnabled() && process.env.ADMIN_HOSPITALS_DELETE === '1';

/** With the delete switch off the delete-check, delete and archive routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class HospitalsDeleteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsDeleteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Approving / rejecting hospital applications (status only). Needs the hospital pages and editing on as well; only the exact value "1" turns it on. */
export const hospitalsReviewEnabled = (): boolean => hospitalsWriteEnabled() && process.env.ADMIN_HOSPITALS_REVIEW === '1';

/** With the review switch off the approve and reject routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class HospitalsReviewEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsReviewEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Uploading hospital images. Needs the hospital pages and editing on as well; only the exact value "1" turns it on. */
export const hospitalsImagesEnabled = (): boolean => hospitalsWriteEnabled() && process.env.ADMIN_HOSPITALS_IMAGES === '1';

/** With the images switch off the upload route answers 404 as if it did not exist; nothing else is looked at. */
@Injectable()
export class HospitalsImagesEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsImagesEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Uploading, replacing and deleting the submitted documents of a hospital. Needs the hospital pages and editing on as well; only the exact value "1" turns it on. */
export const hospitalsDocumentsEnabled = (): boolean => hospitalsWriteEnabled() && process.env.ADMIN_HOSPITALS_DOCUMENTS === '1';

/** With the documents switch off the document routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class HospitalsDocumentsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsDocumentsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Adding doctors to a hospital. Needs the hospital pages and editing on as well; only the exact value "1" turns it on. */
export const hospitalsDoctorsEnabled = (): boolean => hospitalsWriteEnabled() && process.env.ADMIN_HOSPITALS_DOCTORS === '1';

/** With the doctors switch off the add route answers 404 as if it did not exist; nothing else is looked at. */
@Injectable()
export class HospitalsDoctorsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsDoctorsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Assigning treatments to a hospital. Needs the hospital pages and editing on as well; only the exact value "1" turns it on. */
export const hospitalsTreatmentsEnabled = (): boolean => hospitalsWriteEnabled() && process.env.ADMIN_HOSPITALS_TREATMENTS === '1';

/** With the treatments switch off every /v1/hospitals/:id/treatments route answers 404 as if it did not exist; nothing else is looked at. */
@Injectable()
export class HospitalsTreatmentsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsTreatmentsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Removing an assignment (it deletes the price option and detail image rows). Only works while the treatments switch is on; the exact value "1". */
export const hospitalsTreatmentsRemoveEnabled = (): boolean => hospitalsTreatmentsEnabled() && process.env.ADMIN_HOSPITALS_TREATMENTS_REMOVE === '1';

/** With the remove switch off the DELETE route answers 404 as if it did not exist. */
@Injectable()
export class HospitalsTreatmentsRemoveEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsTreatmentsRemoveEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Editing the price options of an assignment. Only works while the treatments switch is on; the exact value "1". */
export const hospitalsTreatmentsPricesEnabled = (): boolean => hospitalsTreatmentsEnabled() && process.env.ADMIN_HOSPITALS_TREATMENTS_PRICES === '1';

/** With the prices switch off the price route answers 404 as if it did not exist. */
@Injectable()
export class HospitalsTreatmentsPricesEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsTreatmentsPricesEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Editing the "before treatment" questions of an assignment. Only works while the treatments switch is on; the exact value "1". */
export const hospitalsTreatmentsQuestionsEnabled = (): boolean => hospitalsTreatmentsEnabled() && process.env.ADMIN_HOSPITALS_TREATMENTS_QUESTIONS === '1';

/** With the questions switch off the questions route answers 404 as if it did not exist. */
@Injectable()
export class HospitalsTreatmentsQuestionsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsTreatmentsQuestionsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Detail images of an assignment (list, add, delete, reorder, review). Only works while the treatments switch is on; the exact value "1". */
export const hospitalsTreatmentsImagesEnabled = (): boolean => hospitalsTreatmentsEnabled() && process.env.ADMIN_HOSPITALS_TREATMENTS_IMAGES === '1';

/** With the images switch off the treatment image routes answer 404 as if they did not exist. */
@Injectable()
export class HospitalsTreatmentsImagesEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsTreatmentsImagesEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Marker header the admin web app sends with every write; a plain cross-site form or fetch cannot set it. */
export const CSRF_HEADER_VALUE = 'admin-next';

const firstValue = (v: string | string[] | undefined): string => (Array.isArray(v) ? v[0] : v || '').split(',')[0].trim().toLowerCase();

/** Content type (one of `types`), marker header and, when sent, an Origin host equal to the host the request was addressed to. */
function csrfAllowed(req: Request, types: string[]): boolean {
  const type = firstValue(req.headers['content-type']).split(';')[0].trim();
  const marker = firstValue(req.headers['x-requested-with']);
  let ok = types.includes(type) && marker === CSRF_HEADER_VALUE;
  const origin = firstValue(req.headers.origin);
  if (ok && origin) {
    let originHost = '';
    try {
      originHost = new URL(origin).host.toLowerCase();
    } catch {
      originHost = '';
    }
    const target = firstValue(req.headers['x-forwarded-host']) || firstValue(req.headers.host);
    ok = originHost !== '' && originHost === target;
  }
  return ok;
}

/**
 * CSRF protection of the writing routes: JSON content type, the marker header, and, when the browser sent an Origin,
 * an Origin host equal to the host the request was addressed to (X-Forwarded-Host when proxied, else Host).
 * Anything else answers 403 {"error":"csrf"}.
 */
@Injectable()
export class CsrfGuard implements CanActivate {
  canActivate(ctx: ExecutionContext): boolean {
    if (!csrfAllowed(ctx.switchToHttp().getRequest<Request>(), ['application/json'])) throw new ForbiddenException({ error: 'csrf' });
    return true;
  }
}

/** Same protection for file uploads: the content type must be multipart/form-data; the marker header and the Origin rule are unchanged. */
@Injectable()
export class MultipartCsrfGuard implements CanActivate {
  canActivate(ctx: ExecutionContext): boolean {
    if (!csrfAllowed(ctx.switchToHttp().getRequest<Request>(), ['multipart/form-data'])) throw new ForbiddenException({ error: 'csrf' });
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
