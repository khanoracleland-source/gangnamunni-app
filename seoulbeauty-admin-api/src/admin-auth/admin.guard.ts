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

/** Changing the display order of hospitals in the app. Needs the hospital pages and editing on as well; only the exact value "1" turns it on. */
export const hospitalsOrderEnabled = (): boolean => hospitalsWriteEnabled() && process.env.ADMIN_HOSPITALS_ORDER === '1';

/** With the order switch off the move route answers 404 as if it did not exist; nothing else is looked at. */
@Injectable()
export class HospitalsOrderEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalsOrderEnabled()) throw new NotFoundException({ error: 'not_found' });
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

/** Treatment master pages (/v1/treatments*, /v1/treatment-categories*, /v1/treatment-conflicts*, /v1/treatment-settings*); super admin only. Independent of the hospital switches; only the exact value "1" turns it on. */
export const treatmentsEnabled = (): boolean => process.env.ADMIN_TREATMENTS_ENABLED === '1';
/** Editing the treatment master (treatments, aliases, categories, conflicts, table mode). Needs the treatment pages on as well; only the exact value "1" turns it on. */
export const treatmentsWriteEnabled = (): boolean => treatmentsEnabled() && process.env.ADMIN_TREATMENTS_WRITE === '1';
/** Uploading the default image of a treatment. Needs the treatment pages and editing on as well; only the exact value "1" turns it on. */
export const treatmentsImagesEnabled = (): boolean => treatmentsWriteEnabled() && process.env.ADMIN_TREATMENTS_IMAGES === '1';

/** With ADMIN_TREATMENTS_ENABLED off the treatment master routes answer 404 as if they did not exist; PHP is not asked. */
@Injectable()
export class TreatmentsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !treatmentsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the write switch off the writing treatment master routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class TreatmentsWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !treatmentsWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the images switch off the default image upload route answers 404 as if it did not exist; nothing else is looked at. */
@Injectable()
export class TreatmentsImagesEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !treatmentsImagesEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Unit list of the price forms (/v1/treatment-units); super admin only. Independent of the other switches; only the exact value "1" turns it on. */
export const treatmentUnitsEnabled = (): boolean => process.env.ADMIN_TREATMENT_UNITS_ENABLED === '1';
/** Adding and removing units. Needs the unit page on as well; only the exact value "1" turns it on. */
export const treatmentUnitsWriteEnabled = (): boolean => treatmentUnitsEnabled() && process.env.ADMIN_TREATMENT_UNITS_WRITE === '1';

/** With ADMIN_TREATMENT_UNITS_ENABLED off the unit routes answer 404 as if they did not exist; PHP is not asked. */
@Injectable()
export class TreatmentUnitsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !treatmentUnitsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the write switch off the writing unit routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class TreatmentUnitsWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !treatmentUnitsWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** The doctors pages (/v1/doctors*). Independent of the hospital switches; hospital roles still need ADMIN_HOSPITALS_ENABLED (see scopeOf). Only the exact value "1" turns it on. */
export const doctorsEnabled = (): boolean => process.env.ADMIN_DOCTORS_ENABLED === '1';

/** With ADMIN_DOCTORS_ENABLED off the doctors routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class DoctorsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !doctorsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Editing and retiring doctors. Needs the doctors pages on as well; only the exact value "1" turns it on. */
export const doctorsWriteEnabled = (): boolean => doctorsEnabled() && process.env.ADMIN_DOCTORS_WRITE === '1';

/** With ADMIN_DOCTORS_WRITE off the editing routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class DoctorsWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !doctorsWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Moving a doctor in the display order of the hospital. Needs the doctor editing on as well; only the exact value "1" turns it on. */
export const doctorsOrderEnabled = (): boolean => doctorsWriteEnabled() && process.env.ADMIN_DOCTORS_ORDER === '1';

@Injectable()
export class DoctorsOrderEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !doctorsOrderEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Permanently deleting a retired doctor. Needs the doctor editing on as well; only the exact value "1" turns it on. */
export const doctorsDeleteEnabled = (): boolean => doctorsWriteEnabled() && process.env.ADMIN_DOCTORS_DELETE === '1';

@Injectable()
export class DoctorsDeleteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !doctorsDeleteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Booking management (/v1/bookings*). Independent of the hospital switches; hospital roles still need ADMIN_HOSPITALS_ENABLED (see scopeOf). Only the exact value "1" turns it on. */
export const bookingsEnabled = (): boolean => process.env.ADMIN_BOOKINGS_ENABLED === '1';
/** Assigning doctors and moving a booking to its next status. Needs the booking page on as well; only the exact value "1" turns it on. */
export const bookingsWriteEnabled = (): boolean => bookingsEnabled() && process.env.ADMIN_BOOKINGS_WRITE === '1';

/** With ADMIN_BOOKINGS_ENABLED off the booking routes answer 404 as if they did not exist; PHP is not asked. */
@Injectable()
export class BookingsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !bookingsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the write switch off the writing booking routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class BookingsWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !bookingsWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Consultation requests (/v1/consultations*). Independent of the other switches; hospital roles still need ADMIN_HOSPITALS_ENABLED (see scopeOf). Only the exact value "1" turns it on. */
export const consultationsEnabled = (): boolean => process.env.ADMIN_CONSULTATIONS_ENABLED === '1';

/** With the switch off the consultation routes answer 404 as if they did not exist; PHP is not asked. */
@Injectable()
export class ConsultationsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !consultationsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Changing a consultation's status and writing the hospital's message. Needs the consultation page on as well; only the exact value "1" turns it on. */
export const consultationsWriteEnabled = (): boolean => consultationsEnabled() && process.env.ADMIN_CONSULTATIONS_WRITE === '1';

/** With the write switch off the writing consultation routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class ConsultationsWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !consultationsWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Booking payments and revenue (/v1/payments*), read only. Independent of the other switches; hospital roles still need ADMIN_HOSPITALS_ENABLED (see scopeOf). Only the exact value "1" turns it on. */
export const paymentsEnabled = (): boolean => process.env.ADMIN_PAYMENTS_ENABLED === '1';

/** With ADMIN_PAYMENTS_ENABLED off the payment routes answer 404 as if they did not exist; PHP is not asked. */
@Injectable()
export class PaymentsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !paymentsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Refunding a booking payment through Inicis (POST /v1/payments/:id/refund). Needs ADMIN_PAYMENTS_ENABLED=1, the write user DB_RW_* and the Inicis refund settings. Only the exact value "1" turns it on. */
export const paymentsRefundEnabled = (): boolean => paymentsEnabled() && process.env.ADMIN_PAYMENTS_REFUND === '1';

/** With ADMIN_PAYMENTS_REFUND off the refund route answers 404 as if it did not exist. */
@Injectable()
export class PaymentsRefundEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !paymentsRefundEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Payment fee rates (GET/POST /v1/payments/fee-rates). Needs ADMIN_PAYMENTS_ENABLED=1 as well; the write user DB_RW_* is needed for POST. Only the exact value "1" turns it on. */
export const paymentsFeesEnabled = (): boolean => paymentsEnabled() && process.env.ADMIN_PAYMENTS_FEES === '1';

/** With ADMIN_PAYMENTS_FEES off the fee rate routes answer 404 as if they did not exist. */
@Injectable()
export class PaymentsFeesEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !paymentsFeesEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Registering a hospital by a super admin (/v1/hospital-apply*). Independent of the hospital switches; needs the write user and the upload folder. Only the exact value "1" turns it on. */
export const hospitalApplyEnabled = (): boolean => process.env.ADMIN_HOSPITAL_APPLY_ENABLED === '1';

/** With ADMIN_HOSPITAL_APPLY_ENABLED off every /v1/hospital-apply route answers 404 as if it did not exist; PHP is not asked. */
@Injectable()
export class HospitalApplyEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !hospitalApplyEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Admin accounts page (/v1/admins*). Independent of the hospital switches. Only the exact value "1" turns it on. */
export const adminsEnabled = (): boolean => process.env.ADMIN_ADMINS_ENABLED === '1';

/** With ADMIN_ADMINS_ENABLED off every /v1/admins route answers 404 as if it did not exist; PHP is not asked. */
@Injectable()
export class AdminsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !adminsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Writing admin accounts (edit, active state). Needs the admins page on as well; only the exact value "1" turns it on. */
export const adminsWriteEnabled = (): boolean => adminsEnabled() && process.env.ADMIN_ADMINS_WRITE_ENABLED === '1';

/** With the write switch off the writing /v1/admins routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class AdminsWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !adminsWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/**
 * A signed-in super admin or hospital role; attaches req.admin. The routes narrow it further: only a super admin sees other
 * accounts, every other role sees and edits itself only. 401 not signed in, 403 any other role, 503 when PHP cannot be asked.
 */
@Injectable()
export class AdminsAccessGuard implements CanActivate {
  constructor(private readonly sessions: AdminSessionService) {}
  async canActivate(ctx: ExecutionContext): Promise<boolean> {
    if (!adminPanelEnabled() || !adminsEnabled()) throw new NotFoundException({ error: 'not_found' });
    const req = ctx.switchToHttp().getRequest<AdminRequest>();
    const s = await this.sessions.fromCookie(req.headers.cookie);
    if (!s.loggedIn || !s.admin) throw new UnauthorizedException({ error: 'not_logged_in' });
    if (s.admin.role !== SUPER_ADMIN_ROLE && !HOSPITAL_ROLES.includes(s.admin.role)) throw new ForbiddenException({ error: 'forbidden' });
    req.admin = s.admin;
    return true;
  }
}

/** Members (/v1/members*). Independent of the other switches; super admin only. Only the exact value "1" turns it on. */
export const membersEnabled = (): boolean => process.env.ADMIN_MEMBERS_ENABLED === '1';

/** With ADMIN_MEMBERS_ENABLED off the members routes answer 404 as if they did not exist; PHP is not asked. */
@Injectable()
export class MembersEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !membersEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Editing members (the e-mail). Needs the members page on as well; only the exact value "1" turns it on. */
export const membersWriteEnabled = (): boolean => membersEnabled() && process.env.ADMIN_MEMBERS_WRITE === '1';

/** With the write switch off the writing members routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class MembersWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !membersWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Resetting a member's password. Needs the members page on as well; only the exact value "1" turns it on. */
export const membersPasswordEnabled = (): boolean => membersEnabled() && process.env.ADMIN_MEMBERS_PASSWORD === '1';

/** With the password switch off the reset route answers 404 as if it did not exist; nothing else is looked at. */
@Injectable()
export class MembersPasswordEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !membersPasswordEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Blocking, unblocking and letting a member leave. Needs the members page on as well; only the exact value "1" turns it on. */
export const membersStatusEnabled = (): boolean => membersEnabled() && process.env.ADMIN_MEMBERS_STATUS === '1';

@Injectable()
export class MembersStatusEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !membersStatusEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Member activity tabs (applications, bookings, points, history). Read only; needs the members page on as well. */
export const membersActivityEnabled = (): boolean => membersEnabled() && process.env.ADMIN_MEMBERS_ACTIVITY === '1';

@Injectable()
export class MembersActivityEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !membersActivityEnabled()) throw new NotFoundException({ error: 'not_found' });
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

/** Review and community post moderation (/v1/moderation*). Independent of the other switches; hospital roles still need ADMIN_HOSPITALS_ENABLED (see scopeOf). Only the exact value "1" turns it on. */
export const moderationEnabled = (): boolean => process.env.ADMIN_MODERATION_ENABLED === '1';
/** Approving and rejecting. Needs the moderation page on as well; only the exact value "1" turns it on. */
export const moderationWriteEnabled = (): boolean => moderationEnabled() && process.env.ADMIN_MODERATION_WRITE === '1';

/** Editing a review or post (text, rating, treatments, removing pictures). Needs the moderation page on; only the exact value "1" turns it on. */
export const moderationEditEnabled = (): boolean => moderationEnabled() && process.env.ADMIN_MODERATION_EDIT === '1';
/** Deleting a review or post for good. A switch of its own because it cannot be undone; only the exact value "1" turns it on. */
export const moderationDeleteEnabled = (): boolean => moderationEnabled() && process.env.ADMIN_MODERATION_DELETE === '1';

/** Advertising banners of the home page and my page (/v1/banners*, super admin only). Only the exact value "1" turns it on. */
export const bannersEnabled = (): boolean => process.env.ADMIN_BANNERS_ENABLED === '1';
/** Changing banners (scroll settings, home switch, delete). Needs the banner page on as well; only the exact value "1" turns it on. */
export const bannersWriteEnabled = (): boolean => bannersEnabled() && process.env.ADMIN_BANNERS_WRITE === '1';

/** With ADMIN_BANNERS_ENABLED off the banner routes answer 404 as if they did not exist. */
@Injectable()
export class BannersEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !bannersEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the write switch off the banner writing routes answer 404 as if they did not exist. */
@Injectable()
export class BannersWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !bannersWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Home menu categories page (/v1/category-board*, super admin only). Needs the treatment master pages on as well; only the exact value "1" turns it on. */
export const categoryBoardEnabled = (): boolean => treatmentsEnabled() && process.env.ADMIN_CATEGORY_BOARD === '1';
/** Changing the home menu categories (treatment assignment, category delete). Needs the treatment master editing on as well; only the exact value "1" turns it on. */
export const categoryBoardWriteEnabled = (): boolean => categoryBoardEnabled() && treatmentsWriteEnabled();

/** With the category page switch off its routes answer 404 as if they did not exist. */
@Injectable()
export class CategoryBoardEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !categoryBoardEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the write switches off the category page's writing routes answer 404 as if they did not exist. */
@Injectable()
export class CategoryBoardWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !categoryBoardWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With ADMIN_MODERATION_ENABLED off the moderation routes answer 404 as if they did not exist; PHP is not asked. */
@Injectable()
export class ModerationEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !moderationEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the write switch off the approve and reject routes answer 404 as if they did not exist; nothing else is looked at. */
@Injectable()
export class ModerationWriteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !moderationWriteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the edit switch off the edit routes answer 404 as if they did not exist. */
@Injectable()
export class ModerationEditEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !moderationEditEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** With the delete switch off the delete routes answer 404 as if they did not exist. */
@Injectable()
export class ModerationDeleteEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !moderationDeleteEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/** Notifications to members (/v1/notifications*). Independent of the other switches; super admin only. Only the exact value "1" turns it on. */
export const notificationsEnabled = (): boolean => process.env.ADMIN_NOTIFICATIONS_ENABLED === '1';

/** With ADMIN_NOTIFICATIONS_ENABLED off the notification routes answer 404 as if they did not exist; PHP is not asked. */
@Injectable()
export class NotificationsEnabledGuard implements CanActivate {
  canActivate(): boolean {
    if (!adminPanelEnabled() || !notificationsEnabled()) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}
