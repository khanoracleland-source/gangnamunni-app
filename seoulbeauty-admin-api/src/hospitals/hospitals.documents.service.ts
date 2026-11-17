import { BadRequestException, ForbiddenException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { randomBytes } from 'crypto';
import { constants as fsc, promises as fs } from 'fs';
import * as path from 'path';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import {
  buildDocumentPath, canWriteDocuments, checkDocument, detectDocumentKind, DocKey, DocumentsResult, isRemovableStoredPath, lockDocumentsQuery, parseDoc, storedValueOf,
  StoredDocument, toDocuments, updateDocumentQuery,
} from './hospitals.documents.logic';
import { UploadedDocument } from './hospitals.documents.upload';
import { canSee, Query, Scope, seesNothing } from './hospitals.logic';
import { FS_UNAVAILABLE, readHead, resolveUploadRoot, UNAVAILABLE_CODES, UNAVAILABLE_ERRNO } from './hospitals.upload.shared';

type Row = Record<string, unknown>;
export interface DocumentActor {
  id: number;
  role: string;
}

/** Database problems that mean "the write user or the columns cannot be used right now" (a missing column or grant included) rather than a bug. */
const DOCUMENTS_UNAVAILABLE_CODES = [...UNAVAILABLE_CODES, 'ER_BAD_FIELD_ERROR'];
const DOCUMENTS_UNAVAILABLE_ERRNO = [...UNAVAILABLE_ERRNO, 1054];

/**
 * Uploading, replacing and deleting the two submitted documents of a hospital. Reads of the hospital use the read-only pool; the change is one
 * transaction of the write pool (hospital row locked, ONE document column updated) plus files in <ADMIN_UPLOAD_ROOT>/uploads/hospital_apply/hospital_<id>/.
 * Neither a file name, a path nor file content is ever logged or returned.
 */
@Injectable()
export class HospitalsDocumentsService {
  private readonly log = new Logger('HospitalsDocuments');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider) {}

  /** An approved hospital inside the scope? */
  private async visibleApproved(scope: Scope, id: number): Promise<boolean> {
    if (id <= 0 || seesNothing(scope) || !canSee(scope, id)) return false;
    const [rows] = (await this.ro.query(`SELECT id FROM gangnamunni_hospitals WHERE id = ? AND status = 'approved' LIMIT 1`, [id])) as any;
    return Array.isArray(rows) && rows.length > 0;
  }

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'documents_unavailable' });
    }
    return pool;
  }

  private unavailable(e: unknown): void {
    const err = e as { code?: string; errno?: number; message?: string };
    if (DOCUMENTS_UNAVAILABLE_CODES.includes(err?.code || '') || DOCUMENTS_UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
      this.log.warn(`documents database unavailable: ${err?.code || err?.errno}`);
      throw new ServiceUnavailableException({ error: 'documents_unavailable' });
    }
  }

  /** The common checks of both actions: scope and approved (404), the document word (404), the right to write (403). */
  private async access(actor: DocumentActor, scope: Scope, id: number, rawDoc: unknown): Promise<DocKey> {
    if (!(await this.visibleApproved(scope, id))) throw new NotFoundException({ error: 'not_found' });
    const doc = parseDoc(rawDoc);
    if (!doc) throw new NotFoundException({ error: 'not_found' });
    if (!canWriteDocuments(actor.role, scope, id)) throw new ForbiddenException({ error: 'forbidden_documents' });
    return doc;
  }

  /** Real type, size and extension of the temporary file. */
  private async validate(f: UploadedDocument | undefined) {
    if (!f) throw new BadRequestException({ error: 'file_required' });
    let head: Buffer;
    let size: number;
    try {
      ({ head, size } = await readHead(f.path));
    } catch {
      throw new BadRequestException({ error: 'upload_failed' });
    }
    const check = checkDocument(f.originalname, size, head);
    if (!check.ok) throw new BadRequestException({ error: check.error });
    // detected again on purpose: the stored extension always comes from the real type, never from the name
    if (detectDocumentKind(head) !== check.kind) throw new BadRequestException({ error: 'invalid_file' });
    return check.kind;
  }

  /** Creates the hospital folder when needed, refuses a folder whose real path differs from the expected one (a symbolic link anywhere on the way). */
  private async prepareDir(target: StoredDocument): Promise<void> {
    const created = await fs.mkdir(target.dir, { recursive: true, mode: 0o755 });
    if (created !== undefined) await fs.chmod(target.dir, 0o755).catch(() => undefined);
    const real = await fs.realpath(target.dir);
    if (real !== target.dir) throw Object.assign(new Error('folder is not the expected real path'), { code: 'EPERM' });
  }

  async upload(actor: DocumentActor, scope: Scope, id: number, rawDoc: unknown, file: UploadedDocument | undefined): Promise<DocumentsResult> {
    /** the file this request wrote into the upload root; removed again if anything fails before the commit */
    let written: string | null = null;
    try {
      const doc = await this.access(actor, scope, id, rawDoc);
      const kind = await this.validate(file);
      const root = await fs.realpath(await resolveUploadRoot(this.log)).catch(() => {
        throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
      });
      const target = buildDocumentPath(root, id, doc, randomBytes(16).toString('hex'), kind);
      const pool = this.pool();

      try {
        await this.prepareDir(target);
        written = target.absolute;
        await fs.copyFile(file!.path, target.absolute, fsc.COPYFILE_EXCL);
        await fs.chmod(target.absolute, 0o644);
      } catch (e) {
        const code = (e as { code?: string })?.code || '';
        this.log.warn(`could not store a document: ${code || 'error'}`);
        if (FS_UNAVAILABLE.includes(code)) throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
        throw new BadRequestException({ error: 'upload_failed' });
      }

      let conn;
      try {
        conn = await pool.getConnection();
      } catch (e) {
        this.unavailable(e);
        throw e;
      }
      let oldValue = '';
      let result: DocumentsResult;
      try {
        await conn.beginTransaction();
        const run = async (q: Query): Promise<any> => ((await conn.query(q.sql, q.values)) as any)[0];
        const rows = (await run(lockDocumentsQuery(id))) as Row[];
        const row = Array.isArray(rows) ? rows[0] : undefined;
        if (!row) throw new NotFoundException({ error: 'not_found' });
        oldValue = storedValueOf(row, doc);
        await run(updateDocumentQuery(doc, id, target.url));
        const other: DocKey = doc === 'business' ? 'medical' : 'business';
        result = doc === 'business' ? toDocuments(target.url, storedValueOf(row, other)) : toDocuments(storedValueOf(row, other), target.url);
        await conn.commit();
      } catch (e) {
        await conn.rollback().catch(() => undefined);
        this.unavailable(e);
        throw e;
      } finally {
        conn.release();
      }
      // committed: the new file is what the row points to
      written = null;
      this.log.log(`hospital documents: admin=${actor.id} hospital=${id} doc=${doc} ${oldValue !== '' ? 'replace' : 'upload'}`);
      if (oldValue !== '' && oldValue !== target.url) await this.removeStored(root, oldValue, doc);
      return result;
    } catch (e) {
      // only what this request wrote is removed; every earlier file stays
      if (written) await fs.unlink(written).catch(() => undefined);
      throw e;
    } finally {
      if (file?.path) await fs.unlink(file.path).catch(() => undefined);
    }
  }

  async remove(actor: DocumentActor, scope: Scope, id: number, rawDoc: unknown): Promise<DocumentsResult> {
    const doc = await this.access(actor, scope, id, rawDoc);
    const pool = this.pool();
    let conn;
    try {
      conn = await pool.getConnection();
    } catch (e) {
      this.unavailable(e);
      throw e;
    }
    let oldValue = '';
    let result: DocumentsResult;
    try {
      await conn.beginTransaction();
      const run = async (q: Query): Promise<any> => ((await conn.query(q.sql, q.values)) as any)[0];
      const rows = (await run(lockDocumentsQuery(id))) as Row[];
      const row = Array.isArray(rows) ? rows[0] : undefined;
      if (!row) throw new NotFoundException({ error: 'not_found' });
      oldValue = storedValueOf(row, doc);
      if (oldValue === '') throw new NotFoundException({ error: 'no_document' });
      await run(updateDocumentQuery(doc, id, ''));
      const other: DocKey = doc === 'business' ? 'medical' : 'business';
      result = doc === 'business' ? toDocuments('', storedValueOf(row, other)) : toDocuments(storedValueOf(row, other), '');
      await conn.commit();
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      this.unavailable(e);
      throw e;
    } finally {
      conn.release();
    }
    this.log.log(`hospital documents: admin=${actor.id} hospital=${id} doc=${doc} delete`);
    // the row no longer points to the file; removing it is best-effort (an unusable upload folder only leaves the file behind)
    try {
      await this.removeStored(await fs.realpath(await resolveUploadRoot(this.log)), oldValue, doc);
    } catch {
      this.log.warn('stored document file was not removed (upload folder unavailable)');
    }
    return result;
  }

  /**
   * Removes the file an old value points to - only when the value has the exact viewer shape for this document AND the real path of the file
   * (symbolic links resolved) is a regular file inside <root>/uploads/hospital_apply. Best-effort: a failure is only logged (no path).
   */
  private async removeStored(realRoot: string, value: string, doc: DocKey): Promise<void> {
    try {
      if (!isRemovableStoredPath(value, doc)) {
        this.log.warn('old document value has an unexpected shape; its file was left alone');
        return;
      }
      const base = path.join(realRoot, 'uploads', 'hospital_apply');
      const file = path.resolve(realRoot, value);
      if (!file.startsWith(base + path.sep)) return;
      const st = await fs.lstat(file);
      if (!st.isFile()) return;
      const real = await fs.realpath(file);
      const realBase = await fs.realpath(base);
      if (!real.startsWith(realBase + path.sep)) return;
      await fs.unlink(real);
    } catch (e) {
      this.log.warn(`old document file was not removed: ${(e as { code?: string })?.code || 'error'}`);
    }
  }
}
