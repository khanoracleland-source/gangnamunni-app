import { BadRequestException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { randomBytes } from 'crypto';
import { constants as fsc, promises as fs } from 'fs';
import { DB_RW, RwDbProvider } from '../db/db.module';
import { buildTreatmentImagePath, checkTreatmentImage, StoredTreatmentImage, TreatmentImageKind } from '../hospitals/hospitals.treatments.images.logic';
import { UploadedTreatmentImages } from '../hospitals/hospitals.treatments.images.upload';
import { FS_UNAVAILABLE, readHead, resolveUploadRoot } from '../hospitals/hospitals.upload.shared';
import { ItemAnswer } from './treatments.write.service';
import { TreatmentsRwBase, WriteActor } from './treatments.rw';
import { TreatmentsService } from './treatments.service';
import { defaultImageQuery } from './treatments.write.logic';

/** Uploading the default image of a treatment: the file goes to <upload root>/uploads/treatments/ (the folder of the PHP page), the address into default_image_url. */
@Injectable()
export class TreatmentsImageService extends TreatmentsRwBase {
  protected readonly log = new Logger('TreatmentsImage');

  constructor(@Inject(DB_RW) protected readonly rw: RwDbProvider, private readonly reads: TreatmentsService) {
    super();
  }

  async upload(actor: WriteActor, id: number, files: UploadedTreatmentImages | undefined): Promise<ItemAnswer> {
    const upload = files?.image?.[0];
    const written: string[] = [];
    try {
      if (id <= 0) throw new NotFoundException({ error: 'not_found' });
      const snap = await this.reads.list();
      this.item(snap, id);
      if (!snap.schema.defaultImage) throw new ServiceUnavailableException({ error: 'default_image_unavailable' });
      if (!upload) throw new BadRequestException({ error: 'image_required' });
      const root = await resolveUploadRoot(this.log);
      const kind = await this.validate(upload.path, upload.originalname);
      const stored = await this.place(root, upload.path, kind, written);
      const q = defaultImageQuery(id, stored.url);
      await this.guarded(() => this.pool().query(q.sql, q.values));
      // From here the address is stored: the file must stay even if reading the answer fails.
      written.length = 0;
      this.audit(actor, 'image_upload', [id]);
      const snapshot = await this.reads.list();
      return { item: this.item(snapshot, id), conflicts: snapshot.conflicts };
    } catch (e) {
      // only what this request wrote is removed
      await Promise.all(written.map((p) => fs.unlink(p).catch(() => undefined)));
      throw e;
    } finally {
      if (upload) await fs.unlink(upload.path).catch(() => undefined);
    }
  }

  /** Size, extension and real type of the file (the extension must agree with the real type). */
  private async validate(tmp: string, originalName: string): Promise<TreatmentImageKind> {
    let head: Buffer;
    let size: number;
    try {
      ({ head, size } = await readHead(tmp));
    } catch {
      throw new BadRequestException({ error: 'upload_failed' });
    }
    const check = checkTreatmentImage(originalName, size, head);
    if (!check.ok) throw new BadRequestException({ error: check.error });
    return check.kind;
  }

  /** Copies the checked file into the folder (a name clash is retried with a new random part). */
  private async place(root: string, tmp: string, kind: TreatmentImageKind, written: string[]): Promise<StoredTreatmentImage> {
    for (let attempt = 0; ; attempt++) {
      const target = buildTreatmentImagePath(root, Math.floor(Date.now() / 1000), randomBytes(4).toString('hex'), kind);
      try {
        try {
          const created = await fs.mkdir(target.dir, { recursive: true, mode: 0o755 });
          if (created !== undefined) await fs.chmod(target.dir, 0o755).catch(() => undefined);
        } catch (e) {
          this.log.warn(`treatment image folder unusable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
          throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
        }
        written.push(target.absolute);
        await fs.copyFile(tmp, target.absolute, fsc.COPYFILE_EXCL);
        await fs.chmod(target.absolute, 0o644);
        return target;
      } catch (e) {
        if (e instanceof ServiceUnavailableException) throw e;
        const code = (e as { code?: string })?.code || '';
        if (code === 'EEXIST' && attempt < 3) {
          written.pop(); // the clashing file is not ours
          continue;
        }
        this.log.warn(`could not store a treatment image: ${code || (e as Error)?.message}`);
        if (FS_UNAVAILABLE.includes(code)) throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
        throw new BadRequestException({ error: 'upload_failed' });
      }
    }
  }
}
