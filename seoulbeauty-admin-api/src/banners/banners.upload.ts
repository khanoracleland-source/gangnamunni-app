import { BadRequestException, CallHandler, ExecutionContext, Injectable, NestInterceptor } from '@nestjs/common';
import { FileFieldsInterceptor } from '@nestjs/platform-express';
import { tmpdir } from 'os';
import { mapTreatmentImageUploadError } from '../hospitals/hospitals.treatments.images.upload';
import { MAX_BANNER_IMAGE_BYTES } from './banners.image';

/** A file received by the multipart parser and kept in the temporary directory until the service has dealt with it. */
export interface UploadedBannerImage {
  fieldname: string;
  originalname: string;
  size: number;
  path: string;
}
export type UploadedBannerImages = { image?: UploadedBannerImage[]; mypageImage?: UploadedBannerImage[] };

class UnknownFieldError extends Error {}

const FIELDS = ['image', 'mypageImage'];

const options = {
  dest: tmpdir(),
  limits: { fileSize: MAX_BANNER_IMAGE_BYTES, files: 2, fields: 12, fieldSize: 64 * 1024, parts: 16 },
  fileFilter: (_req: unknown, file: { fieldname: string }, cb: (e: Error | null, accept: boolean) => void) => {
    if (!FIELDS.includes(file.fieldname)) return cb(new UnknownFieldError('unknown field'), false);
    cb(null, true);
  },
};

const Multipart = FileFieldsInterceptor(FIELDS.map((name) => ({ name, maxCount: 1 })), options);

/** Reads the multipart body to disk (up to two pictures of 5 MB plus the text fields) and turns every parser failure into the documented 400 body. */
@Injectable()
export class BannerUploadInterceptor implements NestInterceptor {
  private readonly inner = new Multipart();

  async intercept(ctx: ExecutionContext, next: CallHandler) {
    try {
      return await this.inner.intercept(ctx, next);
    } catch (e) {
      if (e instanceof UnknownFieldError) throw new BadRequestException({ error: 'upload_failed' });
      throw mapTreatmentImageUploadError(e);
    }
  }
}
