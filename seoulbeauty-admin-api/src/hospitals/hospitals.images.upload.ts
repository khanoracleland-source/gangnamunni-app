import { BadRequestException, CallHandler, ExecutionContext, HttpException, Injectable, NestInterceptor } from '@nestjs/common';
import { FileFieldsInterceptor } from '@nestjs/platform-express';
import { tmpdir } from 'os';
import { DETAIL_LIMIT, MAX_FILES, MAX_INTERIOR_BYTES } from './hospitals.images.logic';

/** A file received by the multipart parser and kept in the temporary directory until the service has dealt with it. */
export interface UploadedImage {
  fieldname: string;
  originalname: string;
  size: number;
  path: string;
}
export type UploadedImages = { main?: UploadedImage[]; interior?: UploadedImage[] };

const FIELDS = ['main', 'interior'];
const fieldOf = (req: any): 'main' | 'interior' => (req?.__imageField === 'main' ? 'main' : 'interior');

/** Marker of an error thrown by the file filter (an unknown field name). */
class UnknownFieldError extends Error {}

const options = {
  // Files go to the system temporary directory (multer's disk storage, random names); the service removes them when it is done.
  dest: tmpdir(),
  limits: { fileSize: MAX_INTERIOR_BYTES, files: MAX_FILES, fields: 5, parts: MAX_FILES + 5 },
  fileFilter: (req: any, file: { fieldname: string }, cb: (e: Error | null, accept: boolean) => void) => {
    // remembers which field is being read, so a size error can name it
    req.__imageField = file.fieldname;
    if (!FIELDS.includes(file.fieldname)) return cb(new UnknownFieldError('unknown field'), false);
    cb(null, true);
  },
};

const Multipart = FileFieldsInterceptor([{ name: 'main', maxCount: 1 }, { name: 'interior', maxCount: DETAIL_LIMIT }], options);

/** Reads the multipart body to disk (at most 11 files, 10 MB each) and turns every parser failure into the documented 400 body. */
@Injectable()
export class HospitalImagesUploadInterceptor implements NestInterceptor {
  private readonly inner = new Multipart();

  async intercept(ctx: ExecutionContext, next: CallHandler) {
    try {
      return await this.inner.intercept(ctx, next);
    } catch (e) {
      throw mapUploadError(e, ctx.switchToHttp().getRequest());
    }
  }
}

export function mapUploadError(e: unknown, req: any): unknown {
  const field = fieldOf(req);
  if (e instanceof UnknownFieldError) return new BadRequestException({ error: 'upload_failed', field });
  if (e instanceof HttpException) {
    const body = e.getResponse() as { message?: unknown } | string;
    const message = String(typeof body === 'string' ? body : body?.message ?? e.message);
    if (e.getStatus() === 413) return new BadRequestException({ error: 'too_large', field });
    if (e.getStatus() === 400) {
      if (/too many files/i.test(message)) return new BadRequestException({ error: 'too_many', field: 'interior' });
      const named = /unexpected[^-]*-\s*(main|interior)\s*$/i.exec(message);
      if (named) return new BadRequestException({ error: 'too_many', field: named[1].toLowerCase() });
      return new BadRequestException({ error: 'upload_failed', field });
    }
  }
  return e;
}
