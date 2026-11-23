import { CallHandler, ExecutionContext, Injectable, NestInterceptor } from '@nestjs/common';
import { FileFieldsInterceptor } from '@nestjs/platform-express';
import { tmpdir } from 'os';
import { mapDoctorUploadError } from '../hospitals/hospitals.doctors.upload';
import { MAX_DOCTOR_PHOTO_BYTES } from '../hospitals/hospitals.images.logic';
import { MAX_DOCTOR_FIELD_BYTES } from './doctors.write.logic';

const options = {
  dest: tmpdir(),
  limits: { fileSize: MAX_DOCTOR_PHOTO_BYTES, files: 1, fields: 3, fieldSize: MAX_DOCTOR_FIELD_BYTES, parts: 4 },
  fileFilter: (req: any, file: { fieldname: string }, cb: (e: Error | null, accept: boolean) => void) => {
    req.__doctorField = file.fieldname;
    if (file.fieldname !== 'photo') return cb(new Error('unknown field'), false);
    cb(null, true);
  },
};

const Multipart = FileFieldsInterceptor([{ name: 'photo', maxCount: 1 }], options);

/** Edit body: a `doctor` JSON text plus at most one `photo` file (5 MB), read to the temporary directory. Parser failures become the documented 400 body. */
@Injectable()
export class DoctorEditUploadInterceptor implements NestInterceptor {
  private readonly inner = new Multipart();

  async intercept(ctx: ExecutionContext, next: CallHandler) {
    try {
      return await this.inner.intercept(ctx, next);
    } catch (e) {
      throw mapDoctorUploadError(e, ctx.switchToHttp().getRequest());
    }
  }
}
