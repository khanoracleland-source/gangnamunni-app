// Pure rules of the hospital document upload: which file is acceptable, how its size reads, what a failure says. No DOM.

import type { DocumentFailure } from "./api";
import type { MessageKey } from "./i18n";

const MB = 1024 * 1024;
export const DOCUMENT_MAX_BYTES = 5 * MB;
export const DOCUMENT_ACCEPT = ".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf";

const DOCUMENT_EXT = ["jpg", "jpeg", "png", "pdf"];

export type DocumentFileProblem = "invalid_file" | "too_large";

const extensionOf = (name: string): string => {
  const dot = name.lastIndexOf(".");
  return dot < 0 ? "" : name.slice(dot + 1).toLowerCase();
};

/** The checks the server makes before it looks inside the file: extension and size. */
export function checkDocumentFile(file: { name: string; size: number }): DocumentFileProblem | null {
  if (!DOCUMENT_EXT.includes(extensionOf(file.name))) return "invalid_file";
  if (file.size <= 0) return "invalid_file";
  if (file.size > DOCUMENT_MAX_BYTES) return "too_large";
  return null;
}

/** "842KB" below one megabyte (rounded up), "1.2MB" from there. */
export function formatFileSize(bytes: number): string {
  if (bytes < MB) return `${Math.max(1, Math.ceil(bytes / 1024))}KB`;
  return `${(bytes / MB).toFixed(1)}MB`;
}

/** What a failed upload or delete says, as a text key. */
export function documentFailureKey(f: DocumentFailure): MessageKey {
  switch (f.error) {
    case "file_required": return "h.docs.err.file_required";
    case "invalid_file": return "h.docs.err.invalid_file";
    case "too_large": return "h.docs.err.too_large";
    case "upload_failed": return "h.docs.err.upload_failed";
    case "forbidden_documents": return "h.docs.err.forbidden_documents";
    case "documents_unavailable": return "h.docs.err.documents_unavailable";
    case "uploads_unavailable": return "h.docs.err.uploads_unavailable";
    case "no_document": return "h.docs.err.no_document";
    default:
      if (f.status === 0) return "h.e.fail.network";
      if (f.status === 403) return "h.docs.err.forbidden_documents";
      if (f.status === 404) return "h.e.fail.gone";
      if (f.status === 503) return "h.docs.err.documents_unavailable";
      return "h.e.fail.server";
  }
}
