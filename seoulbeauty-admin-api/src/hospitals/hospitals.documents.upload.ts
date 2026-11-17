import { BadRequestException, CallHandler, ExecutionContext, HttpException, Injectable, NestInterceptor } from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { tmpdir } from 'os';
import { MAX_DOCUMENT_BYTES } from './hospitals.documents.logic';

/** The file received by the multipart parser, kept in the temporary directory until the service has dealt with it. */
export interface UploadedDocument {
  fieldname: string;
  originalname: string;
  size: number;
  path: string;
}

/** Marker of an error thrown by the file filter (an unknown field name). */
class UnknownFieldError extends Error {}

const options = {
  // Files go to the system temporary directory (random names); the service removes them when it is done.
  dest: tmpdir(),
  limits: { fileSize: MAX_DOCUMENT_BYTES, files: 1, fields: 2, fieldSize: 1024, parts: 3 },
  fileFilter: (_req: unknown, file: { fieldname: string }, cb: (e: Error | null, accept: boolean) => void) => {
    if (file.fieldname !== 'file') return cb(new UnknownFieldError('unknown field'), false);
    cb(null, true);
  },
};

const Multipart = FileInterceptor('file', options);

/** Reads the multipart body to disk (one file of at most 5 MB) and turns every parser failure into the documented 400 body. */
@Injectable()
export class HospitalDocumentUploadInterceptor implements NestInterceptor {
  private readonly inner = new Multipart();

  async intercept(ctx: ExecutionContext, next: CallHandler) {
    try {
      return await this.inner.intercept(ctx, next);
    } catch (e) {
      throw mapDocumentUploadError(e);
    }
  }
}

export function mapDocumentUploadError(e: unknown): unknown {
  if (e instanceof UnknownFieldError) return new BadRequestException({ error: 'upload_failed' });
  if (e instanceof HttpException) {
    if (e.getStatus() === 413) return new BadRequestException({ error: 'too_large' });
    if (e.getStatus() === 400) return new BadRequestException({ error: 'upload_failed' });
  }
  return e;
}
