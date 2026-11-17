import { CanActivate, Controller, Delete, ExecutionContext, HttpCode, Injectable, NotFoundException, Param, Post, Req, UploadedFile, UseGuards, UseInterceptors } from '@nestjs/common';
import { AdminGuard, CsrfGuard, HospitalsDocumentsEnabledGuard, MultipartCsrfGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { parseDoc } from './hospitals.documents.logic';
import { HospitalsDocumentsService } from './hospitals.documents.service';
import { HospitalDocumentUploadInterceptor, UploadedDocument } from './hospitals.documents.upload';
import { canSee, parseHospitalId, Scope, seesNothing } from './hospitals.logic';

const scopeOfRequest = (req: ScopedAdminRequest): Scope => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });
const actorOf = (req: ScopedAdminRequest) => ({ id: req.admin.id, role: req.admin.role });

/**
 * Runs after the sign-in guard and before the body is read: a hospital outside the account's scope (or a malformed id) and an unknown document word
 * are 404, so nothing of a rejected request ever reaches the disk. The service repeats these checks and adds the database ones.
 */
@Injectable()
export class HospitalsDocumentsRouteGuard implements CanActivate {
  canActivate(ctx: ExecutionContext): boolean {
    const req = ctx.switchToHttp().getRequest<ScopedAdminRequest>();
    const id = parseHospitalId(req.params?.id);
    const scope = scopeOfRequest(req);
    if (id <= 0 || seesNothing(scope) || !canSee(scope, id) || parseDoc(req.params?.doc) === null) throw new NotFoundException({ error: 'not_found' });
    return true;
  }
}

/**
 * Uploading or replacing a document (multipart, one file in the field `file`).
 * Guard order: documents switch (404) -> multipart CSRF (403 csrf) -> signed in / role (401, 403) -> scope and document word (404);
 * only then the body is read, then the approved hospital (404), the right to write (403 forbidden_documents) and the file (400).
 */
@Controller('v1/hospitals')
@UseGuards(HospitalsDocumentsEnabledGuard, MultipartCsrfGuard, AdminGuard, HospitalsDocumentsRouteGuard)
export class HospitalsDocumentsUploadController {
  constructor(private readonly documents: HospitalsDocumentsService) {}

  @Post(':id/documents/:doc')
  @HttpCode(200)
  @UseInterceptors(HospitalDocumentUploadInterceptor)
  upload(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('doc') rawDoc: string, @UploadedFile() file: UploadedDocument | undefined) {
    return this.documents.upload(actorOf(req), scopeOfRequest(req), parseHospitalId(rawId), rawDoc, file);
  }
}

/** Deleting a document. Guard order: switch (404) -> JSON CSRF (403 csrf) -> signed in / role (401, 403) -> scope and document word (404). */
@Controller('v1/hospitals')
@UseGuards(HospitalsDocumentsEnabledGuard, CsrfGuard, AdminGuard, HospitalsDocumentsRouteGuard)
export class HospitalsDocumentsDeleteController {
  constructor(private readonly documents: HospitalsDocumentsService) {}

  @Delete(':id/documents/:doc')
  @HttpCode(200)
  remove(@Req() req: ScopedAdminRequest, @Param('id') rawId: string, @Param('doc') rawDoc: string) {
    return this.documents.remove(actorOf(req), scopeOfRequest(req), parseHospitalId(rawId), rawDoc);
  }
}
