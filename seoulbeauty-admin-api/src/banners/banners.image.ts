import * as path from 'path';
import { checkTreatmentImage, ImageCheck, TreatmentImageKind } from '../hospitals/hospitals.treatments.images.logic';

/** Folder of banner pictures, relative to the upload root (the same folder the PHP page writes to). */
export const BANNER_IMAGE_DIR = 'uploads/ads';
export const MAX_BANNER_IMAGE_BYTES = 5 * 1024 * 1024;

/** Size (<= 5 MB), extension whitelist and real type of an uploaded banner picture. */
export function checkBannerImage(originalName: unknown, size: number, head: Buffer): ImageCheck {
  if (Number.isFinite(size) && size > MAX_BANNER_IMAGE_BYTES) return { ok: false, error: 'too_large' };
  return checkTreatmentImage(originalName, size, head);
}

export interface StoredBannerImage {
  dir: string;
  absolute: string;
  url: string;
}

/** Where a new picture goes: <root>/uploads/ads/ad_<hex>.<ext>; the stored address is the relative path. */
export function buildBannerImagePath(root: string, hex: string, kind: TreatmentImageKind): StoredBannerImage {
  const name = `ad_${hex}.${kind}`;
  const dir = path.resolve(root, BANNER_IMAGE_DIR);
  return { dir, absolute: path.join(dir, name), url: `${BANNER_IMAGE_DIR}/${name}` };
}

/** A stored value that points at one of our own pictures (uploads/ads/<plain name>). */
export const isOwnBannerImage = (v: string): boolean => /^uploads\/ads\/[A-Za-z0-9_][A-Za-z0-9_.-]*$/.test(v) && !v.includes('..');

/** Absolute path of one of our pictures to remove, or null for anything else (other folder, "..", absolute URL) or an unusable root. */
export function removableBannerPath(root: string, stored: string): string | null {
  if (!root || !path.isAbsolute(root) || !isOwnBannerImage(stored)) return null;
  const dir = path.resolve(root, BANNER_IMAGE_DIR);
  const absolute = path.resolve(root, stored);
  return absolute.startsWith(dir + path.sep) ? absolute : null;
}
