import { BadRequestException, CallHandler, ExecutionContext, HttpException, Injectable, NestInterceptor } from '@nestjs/common';
import { FileFieldsInterceptor } from '@nestjs/platform-express';
import { tmpdir } from 'os';
import { DOCTOR_ADD_LIMIT, MAX_DOCTORS_FIELD_BYTES } from './hospitals.doctors.logic';
import { MAX_DOCTOR_PHOTO_BYTES } from './hospitals.images.logic';

/** A photo received by the multipart parser and kept in the temporary directory until the service has dealt with it. */
export interface UploadedPhoto {
  fieldname: string;
  originalname: string;
  size: number;
  path: string;
}
export type UploadedPhotos = { photo?: UploadedPhoto[] };

/** Marker of an error thrown by the file filter (an unknown field name). */
class UnknownFieldError extends Error {}

const options = {
  // Files go to the system temporary directory (multer's disk storage, random names); the service removes them when it is done.
  dest: tmpdir(),
  limits: { fileSize: MAX_DOCTOR_PHOTO_BYTES, files: DOCTOR_ADD_LIMIT, fields: 5, fieldSize: MAX_DOCTORS_FIELD_BYTES, parts: DOCTOR_ADD_LIMIT + 5 },
  fileFilter: (req: any, file: { fieldname: string }, cb: (e: Error | null, accept: boolean) => void) => {
    // remembers which field is being read, so a "too many" error can be told apart from an unknown field
    req.__doctorField = file.fieldname;
    if (file.fieldname !== 'photo') return cb(new UnknownFieldError('unknown field'), false);
    cb(null, true);
  },
};

const Multipart = FileFieldsInterceptor([{ name: 'photo', maxCount: DOCTOR_ADD_LIMIT }], options);

/** Reads the multipart body to disk (at most 20 photos, 5 MB each, a `doctors` text of at most 200 KB) and turns every parser failure into the documented 400 body. */
@Injectable()
export class HospitalDoctorsUploadInterceptor implements NestInterceptor {
  private readonly inner = new Multipart();

  async intercept(ctx: ExecutionContext, next: CallHandler) {
    try {
      return await this.inner.intercept(ctx, next);
    } catch (e) {
      throw mapDoctorUploadError(e, ctx.switchToHttp().getRequest());
    }
  }
}

export function mapDoctorUploadError(e: unknown, req: any): unknown {
  if (e instanceof UnknownFieldError) return new BadRequestException({ error: 'upload_failed' });
  if (e instanceof HttpException) {
    const body = e.getResponse() as { message?: unknown } | string;
    const message = String(typeof body === 'string' ? body : body?.message ?? e.message);
    if (e.getStatus() === 413) return new BadRequestException({ error: 'too_large', field: 'photo' });
    if (e.getStatus() === 400) {
      if (/field value too long/i.test(message)) return new BadRequestException({ error: 'invalid_field' });
      if (/too many files/i.test(message)) return new BadRequestException({ error: 'too_many' });
      if (/unexpected/i.test(message) && (req?.__doctorField === 'photo' || /-\s*photo\s*$/i.test(message))) return new BadRequestException({ error: 'too_many' });
      return new BadRequestException({ error: 'upload_failed' });
    }
  }
  return e;
}
