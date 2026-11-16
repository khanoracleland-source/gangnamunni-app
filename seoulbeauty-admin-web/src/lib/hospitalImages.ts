// Pure rules of the hospital image upload card: which files are acceptable and how many fit. No DOM.

import type { ImageUploadFailure } from "./api";
import type { MessageKey } from "./i18n";

const MB = 1024 * 1024;
export const MAIN_MAX_BYTES = 5 * MB;
export const INTERIOR_MAX_BYTES = 10 * MB;
export const DETAIL_LIMIT = 10;

export const MAIN_ACCEPT = ".jpg,.jpeg,.png";
export const INTERIOR_ACCEPT = ".jpg,.jpeg,.png,.webp";

const MAIN_EXT = ["jpg", "jpeg", "png"];
const INTERIOR_EXT = ["jpg", "jpeg", "png", "webp"];
const MAIN_MIME = ["image/jpeg", "image/png"];
const INTERIOR_MIME = ["image/jpeg", "image/png", "image/webp"];

export type FileProblem = "invalid_type" | "too_large";

const extensionOf = (name: string): string => {
  const dot = name.lastIndexOf(".");
  return dot < 0 ? "" : name.slice(dot + 1).toLowerCase();
};

/**
 * The same checks the server makes before it looks inside the file: extension, the type the browser reports
 * (some systems report none; the server then decides from the file's first bytes) and size.
 */
export function checkImageFile(file: { name: string; type: string; size: number }, kind: "main" | "interior"): FileProblem | null {
  const extensions = kind === "main" ? MAIN_EXT : INTERIOR_EXT;
  const mimes = kind === "main" ? MAIN_MIME : INTERIOR_MIME;
  if (!extensions.includes(extensionOf(file.name))) return "invalid_type";
  if (file.type !== "" && !mimes.includes(file.type.toLowerCase())) return "invalid_type";
  if (file.size <= 0) return "invalid_type";
  if (file.size > (kind === "main" ? MAIN_MAX_BYTES : INTERIOR_MAX_BYTES)) return "too_large";
  return null;
}

/** How many more detail images fit, counting the ones already stored and the ones chosen but not sent yet. */
export const slotsLeft = (stored: number, chosen: number, limit: number = DETAIL_LIMIT): number => Math.max(0, limit - stored - chosen);

export const sameFile = (a: File, b: File): boolean => a.name === b.name && a.size === b.size && a.lastModified === b.lastModified;

/** What a failed upload says, as a text key and the group it belongs to. */
export function uploadFailureKey(f: ImageUploadFailure): { key: MessageKey; field: "main" | "interior" | "all" } {
  const field = f.field === "" ? "all" : f.field;
  switch (f.error) {
    case "invalid_type": return { key: field === "main" ? "h.e.img.err.typeMain" : "h.e.img.err.typeInterior", field };
    case "too_large": return { key: field === "main" ? "h.e.img.err.largeMain" : "h.e.img.err.largeInterior", field };
    case "too_many": return { key: "h.e.img.err.many", field: "interior" };
    case "no_files": return { key: "h.e.img.err.none", field: "all" };
    case "upload_failed": return { key: "h.e.img.err.failed", field: "all" };
    default:
      if (f.status === 0) return { key: "h.e.fail.network", field: "all" };
      if (f.status === 403) return { key: "h.e.fail.forbidden", field: "all" };
      if (f.status === 404) return { key: "h.e.fail.gone", field: "all" };
      if (f.status === 503) return { key: "h.e.img.err.unavailable", field: "all" };
      return { key: "h.e.fail.server", field: "all" };
  }
}

