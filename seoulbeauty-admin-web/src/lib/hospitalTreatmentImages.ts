// Pure rules of the "treatment detail page images" window: the checks the server also makes, how the images split into what
// patients see and what they do not, and what a refused request says. No DOM.

import type { TreatmentImage, TreatmentImageFailure } from "./api";
import type { MessageKey } from "./i18n";

export const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
export const MAX_URL_LENGTH = 2048;
export const MAX_CAPTION = 200;
export const MAX_REASON = 1000;
/** The changed row keeps its highlight this long after a move (ms). */
export const MOVE_HIGHLIGHT_MS = 1200;

export const IMAGE_ACCEPT = ".jpg,.jpeg,.png,.gif,.webp";
const EXTENSIONS = ["jpg", "jpeg", "png", "gif", "webp"];
const MIMES = ["image/jpeg", "image/png", "image/gif", "image/webp"];

/** Length as people (and the server) count it: code points, not UTF-16 units. */
export const textLength = (text: string): number => Array.from(text).length;

const extensionOf = (name: string): string => {
  const dot = name.lastIndexOf(".");
  return dot < 0 ? "" : name.slice(dot + 1).toLowerCase();
};

export type ImageProblem = "invalid_image" | "too_large";

/**
 * The checks the server makes before it looks inside the file: extension, the type the browser reports (some systems
 * report none; the server then decides from the first bytes) and size (10 MB at most).
 */
export function checkImageFile(file: { name: string; type: string; size: number }): ImageProblem | null {
  if (!EXTENSIONS.includes(extensionOf(file.name))) return "invalid_image";
  if (file.type !== "" && !MIMES.includes(file.type.toLowerCase())) return "invalid_image";
  if (file.size <= 0) return "invalid_image";
  if (file.size > MAX_IMAGE_BYTES) return "too_large";
  return null;
}

/** An address the server takes: http(s)://host, at most 2048 bytes, no white space or control characters. */
export function isImageUrl(raw: string): boolean {
  const url = raw.trim();
  if (url === "" || new TextEncoder().encode(url).length > MAX_URL_LENGTH) return false;
  if (/[\s\u0000-\u001f\u007f]/u.test(url)) return false;
  if (!/^https?:\/\/[^/\\?#@]/i.test(url)) return false;
  try {
    const parsed = new URL(url);
    return (parsed.protocol === "http:" || parsed.protocol === "https:") && parsed.hostname !== "";
  } catch {
    return false;
  }
}

export type AddField = "file" | "url" | "caption";
export type AddProblem = { field: AddField; key: MessageKey; vars?: Record<string, number> };

/** The same checks as the server for one new image; the first problem found, or null. A chosen file wins over the typed address. */
export function checkNewImage(file: File | null, url: string, caption: string): AddProblem | null {
  if (file) {
    const problem = checkImageFile(file);
    if (problem === "too_large") return { field: "file", key: "h.e.ti.e.too_large" };
    if (problem === "invalid_image") return { field: "file", key: "h.e.ti.e.invalid_image" };
  } else if (url.trim() === "") {
    return { field: "file", key: "h.e.ti.e.image_required" };
  } else if (!isImageUrl(url)) {
    return { field: "url", key: "h.e.ti.e.invalid_url" };
  }
  const length = textLength(caption.trim());
  if (length > MAX_CAPTION) return { field: "caption", key: "h.e.ti.e.caption_too_long", vars: { n: length } };
  return null;
}

/** The reject reason as the server takes it: trimmed, required, at most 1000 characters. */
export function checkReason(reason: string): MessageKey | null {
  const text = reason.trim();
  if (text === "") return "h.e.ti.e.reason_required";
  if (textLength(text) > MAX_REASON) return "h.e.ti.e.reason_too_long";
  return null;
}

/** "1.2MB" / "340KB" for the chosen file. */
export function sizeLabel(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)}MB`;
  return `${Math.max(1, Math.round(bytes / 1024))}KB`;
}

export type ImageCounts = { total: number; approved: number; pending: number; rejected: number };

export function countImages(items: readonly TreatmentImage[]): ImageCounts {
  let approved = 0;
  let pending = 0;
  let rejected = 0;
  for (const item of items) {
    if (item.status === "approved") approved += 1;
    else if (item.status === "pending") pending += 1;
    else rejected += 1;
  }
  return { total: items.length, approved, pending, rejected };
}

/** A row on screen: the image, its number among all images (upload order as the server lists them) and its place for patients. */
export type ImageRow = { image: TreatmentImage; no: number; patientPlace: number | null };

/** Splits the list into what patients see (approved, in the patients' order) and what they do not (waiting, rejected). */
export function splitImages(items: readonly TreatmentImage[]): { shown: ImageRow[]; hidden: ImageRow[] } {
  const shown: ImageRow[] = [];
  const hidden: ImageRow[] = [];
  items.forEach((image, index) => {
    if (image.status === "approved") shown.push({ image, no: index + 1, patientPlace: shown.length + 1 });
    else hidden.push({ image, no: index + 1, patientPlace: null });
  });
  return { shown, hidden };
}

/** How the patient page treats the image at `place` (1-based) when `approved` images are shown. */
export type PatientRole = "only" | "first" | "after";
export const patientRole = (place: number, approved: number): PatientRole => (approved <= 1 ? "only" : place === 1 ? "first" : "after");

/** What a refused request says: the line to show, and whether the list on screen is probably out of date (it is read again). */
export type ImageFailure = { key: MessageKey; refresh: boolean; field: AddField | null; unauthorized: boolean };

export function imageFailure(f: TreatmentImageFailure): ImageFailure {
  const out = (key: MessageKey, extra: Partial<ImageFailure> = {}): ImageFailure => ({ key, refresh: false, field: null, unauthorized: false, ...extra });
  if (f.status === 0) return out("h.e.ti.e.network");
  if (f.status === 401) return out("h.e.ti.e.generic", { unauthorized: true });
  if (f.status === 400) {
    switch (f.error) {
      case "image_required": return out("h.e.ti.e.image_required", { field: "file" });
      case "invalid_url": return out("h.e.ti.e.invalid_url", { field: "url" });
      case "invalid_image": return out("h.e.ti.e.invalid_image", { field: "file" });
      case "too_large": return out("h.e.ti.e.too_large", { field: "file" });
      case "caption_too_long": return out("h.e.ti.e.caption_too_long_srv", { field: "caption" });
      case "upload_failed": return out("h.e.ti.e.upload_failed");
      case "reason_required": return out("h.e.ti.e.reason_required");
      case "reason_too_long": return out("h.e.ti.e.reason_too_long");
      case "invalid_field": return out("h.e.ti.e.invalid_field");
      default: return out("h.e.ti.e.generic");
    }
  }
  if (f.status === 403) {
    if (f.error === "forbidden_review") return out("h.e.ti.e.forbidden_review");
    if (f.error === "forbidden_treatments") return out("h.e.ti.e.forbidden");
    return out("h.e.ti.e.denied");
  }
  if (f.status === 404) return out("h.e.ti.e.notfound", { refresh: true });
  if (f.status === 409) {
    if (f.error === "cannot_move") return out("h.e.ti.e.cannot_move", { refresh: true });
    if (f.error === "not_approved") return out("h.e.ti.e.not_approved", { refresh: true });
    if (f.error === "already_reviewed") return out("h.e.ti.e.already_reviewed", { refresh: true });
    return out("h.e.ti.e.generic", { refresh: true });
  }
  if (f.status === 503) {
    if (f.error === "images_unavailable") return out("h.e.ti.e.images_unavailable");
    if (f.error === "uploads_unavailable") return out("h.e.ti.e.uploads_unavailable");
    return out("h.e.ti.e.write_unavailable");
  }
  return out("h.e.ti.e.generic");
}

/** True when the treatment row shows the image chip as a button: the window is switched on here and the list says it is on too. */
export const canOpenImages = (feature: boolean, set: { imagesEnabled?: boolean }): boolean => feature && set.imagesEnabled === true;
