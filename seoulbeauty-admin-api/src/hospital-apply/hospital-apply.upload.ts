import { BadRequestException, CallHandler, ExecutionContext, HttpException, Injectable, NestInterceptor, PayloadTooLargeException } from '@nestjs/common';
import { AnyFilesInterceptor } from '@nestjs/platform-express';
import { tmpdir } from 'os';
import { FILE_FIELD_RE, MAX_ANY_FILE_BYTES, MAX_FILES, MAX_PAYLOAD_BYTES } from './hospital-apply.logic';

/** A file received by the multipart parser and kept in the temporary directory until the service has dealt with it. */
export interface ApplyUpload {
  fieldname: string;
  originalname: string;
  size: number;
  path: string;
}

/** Marker of an error thrown by the file filter (a field name the form does not have). */
class UnknownFieldError extends Error {}

const options = {
  // Files go to the system temporary directory (random names); the service removes them when it is done.
  dest: tmpdir(),
  limits: { fileSize: MAX_ANY_FILE_BYTES, files: MAX_FILES, fields: 4, fieldSize: MAX_PAYLOAD_BYTES, parts: MAX_FILES + 4 },
  fileFilter: (req: any, file: { fieldname: string }, cb: (e: Error | null, accept: boolean) => void) => {
    // remembers which field is being read, so a "too large" error can name it
    req.__applyField = file.fieldname;
    if (!FILE_FIELD_RE.test(file.fieldname)) return cb(new UnknownFieldError('unknown field'), false);
    cb(null, true);
  },
};

const Multipart = AnyFilesInterceptor(options);

/** Reads the multipart body to disk and turns every parser failure into the documented answer. */
@Injectable()
export class HospitalApplyUploadInterceptor implements NestInterceptor {
  private readonly inner = new Multipart();

  async intercept(ctx: ExecutionContext, next: CallHandler) {
    try {
      return await this.inner.intercept(ctx, next);
    } catch (e) {
      throw mapApplyUploadError(e, ctx.switchToHttp().getRequest());
    }
  }
}

export function mapApplyUploadError(e: unknown, req: any): unknown {
  if (e instanceof UnknownFieldError) return new BadRequestException({ error: 'invalid_body' });
  if (e instanceof HttpException) {
    const body = e.getResponse() as { message?: unknown } | string;
    const message = String(typeof body === 'string' ? body : body?.message ?? e.message);
    if (e.getStatus() === 413 || e instanceof PayloadTooLargeException) return new HttpException({ error: 'file_too_large', field: String(req?.__applyField || '') }, 413);
    if (e.getStatus() === 400) {
      if (/field value too long/i.test(message)) return new BadRequestException({ error: 'invalid_body' });
      if (/too many files|unexpected/i.test(message)) return new BadRequestException({ error: 'too_many' });
      return new BadRequestException({ error: 'invalid_body' });
    }
  }
  return e;
}
