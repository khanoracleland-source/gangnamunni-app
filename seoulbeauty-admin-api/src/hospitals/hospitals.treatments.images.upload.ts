import { BadRequestException, CallHandler, ExecutionContext, HttpException, Injectable, NestInterceptor } from '@nestjs/common';
import { FileFieldsInterceptor } from '@nestjs/platform-express';
import { tmpdir } from 'os';
import { MAX_TREATMENT_IMAGE_BYTES } from './hospitals.treatments.images.logic';

/** A file received by the multipart parser and kept in the temporary directory until the service has dealt with it. */
export interface UploadedTreatmentImage {
  fieldname: string;
  originalname: string;
  size: number;
  path: string;
}
export type UploadedTreatmentImages = { image?: UploadedTreatmentImage[] };

/** Marker of an error thrown by the file filter (an unknown field name). */
class UnknownFieldError extends Error {}

const options = {
  // Files go to the system temporary directory (multer's disk storage, random names); the service removes them when it is done.
  dest: tmpdir(),
  limits: { fileSize: MAX_TREATMENT_IMAGE_BYTES, files: 1, fields: 4, fieldSize: 64 * 1024, parts: 6 },
  fileFilter: (_req: unknown, file: { fieldname: string }, cb: (e: Error | null, accept: boolean) => void) => {
    if (file.fieldname !== 'image') return cb(new UnknownFieldError('unknown field'), false);
    cb(null, true);
  },
};

const Multipart = FileFieldsInterceptor([{ name: 'image', maxCount: 1 }], options);

/** Reads the multipart body to disk (one file of at most 10 MB plus the text fields) and turns every parser failure into the documented 400 body. */
@Injectable()
export class TreatmentImageUploadInterceptor implements NestInterceptor {
  private readonly inner = new Multipart();

  async intercept(ctx: ExecutionContext, next: CallHandler) {
    try {
      return await this.inner.intercept(ctx, next);
    } catch (e) {
      throw mapTreatmentImageUploadError(e);
    }
  }
}

export function mapTreatmentImageUploadError(e: unknown): unknown {
  if (e instanceof UnknownFieldError) return new BadRequestException({ error: 'upload_failed' });
  if (e instanceof HttpException) {
    if (e.getStatus() === 413) return new BadRequestException({ error: 'too_large' });
    if (e.getStatus() === 400) return new BadRequestException({ error: 'upload_failed' });
  }
  return e;
}
