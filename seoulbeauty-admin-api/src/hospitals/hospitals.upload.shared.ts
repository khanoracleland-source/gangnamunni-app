/** Parts shared by the hospital upload routes (images, doctors): upload root, temporary file head and "unavailable" error classes. */
import { Logger, ServiceUnavailableException } from '@nestjs/common';
import { constants as fsc, promises as fs } from 'fs';
import * as path from 'path';

/** Database problems that mean "the write user cannot be used right now" rather than a bug. */
export const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR', 'ER_NO_SUCH_TABLE',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
export const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040, 1146];
/** File system problems that mean "the upload folder cannot be used" rather than a bad file. */
export const FS_UNAVAILABLE = ['EACCES', 'EPERM', 'EROFS', 'ENOENT', 'ENOTDIR'];

/** Database errors of the write user -> 503 write_unavailable; anything else is left to the caller. */
export function rethrowIfWriteUnavailable(e: unknown, log: Logger): void {
  const err = e as { code?: string; errno?: number; message?: string };
  if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
    log.warn(`write database unavailable: ${err.message}`);
    throw new ServiceUnavailableException({ error: 'write_unavailable' });
  }
}

/** The upload root when it is an absolute path to a folder this process can write to; otherwise 503 (the reason goes to the log only). */
export async function resolveUploadRoot(log: Logger): Promise<string> {
  const root = process.env.ADMIN_UPLOAD_ROOT || '';
  try {
    if (!root || !path.isAbsolute(root)) throw new Error('ADMIN_UPLOAD_ROOT is empty or not an absolute path');
    const st = await fs.stat(root);
    if (!st.isDirectory()) throw new Error('ADMIN_UPLOAD_ROOT is not a directory');
    await fs.access(root, fsc.W_OK | fsc.X_OK);
    return path.resolve(root);
  } catch (e) {
    log.warn(`upload root unusable: ${(e as { code?: string; message?: string })?.code || (e as Error)?.message}`);
    throw new ServiceUnavailableException({ error: 'uploads_unavailable' });
  }
}

/** First 16 bytes and real size of a temporary file; throws when it cannot be read. */
export async function readHead(file: string): Promise<{ head: Buffer; size: number }> {
  const fh = await fs.open(file, 'r');
  try {
    const buf = Buffer.alloc(16);
    const { bytesRead } = await fh.read(buf, 0, 16, 0);
    return { head: buf.subarray(0, bytesRead), size: (await fh.stat()).size };
  } finally {
    await fh.close();
  }
}
