/** Pure parts of the submitted-documents routes (business registration, medical licence): file checks, storage path, queries and the shape of the answer. */
import * as path from 'path';
import { HOSPITAL_ROLES, SUPER_ADMIN_ROLE } from '../admin-auth/admin.guard';
import { extensionOf } from './hospitals.images.logic';
import { Query, Scope } from './hospitals.logic';

export type DocKey = 'business' | 'medical';
export type DocKind = 'jpg' | 'png' | 'pdf';

export const MAX_DOCUMENT_BYTES = 5 * 1024 * 1024;
const EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

/** :doc of the routes -> the document key, or null. Only the two exact words count. */
export function parseDoc(raw: unknown): DocKey | null {
  return raw === 'business' || raw === 'medical' ? raw : null;
}

/** What the first bytes of a file say it is (JPEG FF D8 FF, PNG 89 50 4E 47 0D 0A 1A 0A, PDF "%PDF-"), or null. */
export function detectDocumentKind(head: Buffer): DocKind | null {
  if (head.length >= 3 && head[0] === 0xff && head[1] === 0xd8 && head[2] === 0xff) return 'jpg';
  if (head.length >= 8 && head.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return 'png';
  if (head.length >= 5 && head.subarray(0, 5).toString('latin1') === '%PDF-') return 'pdf';
  return null;
}

export type DocumentCheck = { ok: true; kind: DocKind } | { ok: false; error: 'invalid_file' | 'too_large' };

/** Size, extension whitelist and real type of the file. The extension (only compared, never stored) must agree with the real type. */
export function checkDocument(originalName: unknown, size: number, head: Buffer): DocumentCheck {
  if (!Number.isFinite(size) || size <= 0) return { ok: false, error: 'invalid_file' };
  if (size > MAX_DOCUMENT_BYTES) return { ok: false, error: 'too_large' };
  const ext = extensionOf(originalName);
  if (!EXTENSIONS.includes(ext)) return { ok: false, error: 'invalid_file' };
  const kind = detectDocumentKind(head);
  if (!kind) return { ok: false, error: 'invalid_file' };
  const claimed: DocKind = ext === 'jpeg' ? 'jpg' : (ext as DocKind);
  if (claimed !== kind) return { ok: false, error: 'invalid_file' };
  return { ok: true, kind };
}

/** Shape the PHP document viewer accepts (folder [A-Za-z0-9_-]+, file cert_(business|medical)_[A-Za-z0-9]+.<ext>). */
const STORED_RE = /^uploads\/hospital_apply\/[A-Za-z0-9_-]+\/cert_(business|medical)_[A-Za-z0-9]+\.(?:jpg|jpeg|png|pdf)$/;

/** A stored value that this API may remove from disk: the viewer's shape AND the file name names the same document. No ".." or "/" tricks can match. */
export function isRemovableStoredPath(value: unknown, doc: DocKey): boolean {
  if (typeof value !== 'string') return false;
  const m = STORED_RE.exec(value);
  return m !== null && m[1] === doc;
}

export interface StoredDocument {
  /** Value of cert_business / cert_medical: relative to the PHP admin directory. */
  url: string;
  absolute: string;
  dir: string;
}

/**
 * Where a new file goes: <root>/uploads/hospital_apply/hospital_<id>/cert_<doc>_<32 hex>.<jpg|png|pdf>.
 * Nothing comes from the client: the id is a validated integer, the name is random hex, the extension comes from the real type.
 * Throws when the id or the random part is invalid or the result would leave the folder.
 */
export function buildDocumentPath(root: string, hospitalId: number, doc: DocKey, hex: string, kind: DocKind): StoredDocument {
  if (!Number.isInteger(hospitalId) || hospitalId <= 0) throw new Error('invalid hospital id');
  if (!/^[0-9a-f]{32}$/.test(hex)) throw new Error('invalid file token');
  const base = path.resolve(root);
  const rel = `uploads/hospital_apply/hospital_${hospitalId}`;
  const dir = path.resolve(base, rel);
  const absolute = path.resolve(dir, `cert_${doc}_${hex}.${kind}`);
  if (!dir.startsWith(base + path.sep) || !absolute.startsWith(dir + path.sep)) throw new Error('path leaves the upload root');
  return { url: `${rel}/cert_${doc}_${hex}.${kind}`, absolute, dir };
}

/** May this admin change the documents of this hospital: a super admin (scope "all") or an account bound to exactly this hospital. */
export function canWriteDocuments(role: string, scope: Scope, hospitalId: number): boolean {
  if (!Number.isInteger(hospitalId) || hospitalId <= 0) return false;
  if (role === SUPER_ADMIN_ROLE) return scope.scope === 'all';
  return HOSPITAL_ROLES.includes(role) && scope.scope === 'own' && scope.hospitalIds.includes(hospitalId);
}

const COLUMN: Record<DocKey, 'cert_business' | 'cert_medical'> = { business: 'cert_business', medical: 'cert_medical' };

/** Locks the hospital row and reads both document columns (the answer is built from them). */
export const lockDocumentsQuery = (id: number): Query => ({
  sql: `SELECT id, cert_business, cert_medical FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE`,
  values: [id],
});
/** Sets ONE document column; the column name comes from the closed list above, the value and the id are parameters. updated_at is not touched. */
export const updateDocumentQuery = (doc: DocKey, id: number, value: string): Query => ({
  sql: `UPDATE gangnamunni_hospitals SET ${COLUMN[doc]} = ? WHERE id = ?`,
  values: [value, id],
});
export const storedValueOf = (row: Record<string, unknown> | undefined, doc: DocKey): string => {
  const v = row?.[COLUMN[doc]];
  return typeof v === 'string' ? v.trim() : '';
};

export interface DocumentsResult {
  documents: { business: boolean; medical: boolean };
}
export const toDocuments = (business: string, medical: string): DocumentsResult => ({ documents: { business: business !== '', medical: medical !== '' } });
