import type { WriteFailure } from "@/lib/api";
import type { MessageKey } from "@/lib/i18n";

/** The sentence for a refused change that is not about one field. */
export function failureKey(f: WriteFailure): MessageKey {
  if (f.status === 0) return "tmw.err.network";
  if (f.status === 401) return "tmw.err.session";
  if (f.status === 403 && f.error !== "csrf") return "tmw.err.session";
  if (f.status === 404) return "tmw.err.notFound";
  if (f.status === 503) return "tmw.err.unavailable";
  if (f.status === 409 && f.error === "duplicate") return "tmw.err.duplicate";
  return "tmw.err.generic";
}

const REASONS = ["required", "too_long", "bad_format", "range"] as const;

/** The sentence for a field the server refused. */
export function reasonKey(reason: string | undefined): MessageKey {
  const found = REASONS.find((r) => r === reason);
  return found ? (`tmw.err.${found}` as MessageKey) : "tmw.err.bad_format";
}

const IMAGE_ERRORS = ["invalid_image", "too_large", "image_required", "uploads_unavailable", "default_image_unavailable", "upload_failed"] as const;

/** The sentence for a refused image upload; anything it does not know falls back to the general one. */
export function imageFailureKey(f: WriteFailure): MessageKey {
  const found = IMAGE_ERRORS.find((e) => e === f.error);
  return found ? (`tmi.err.${found}` as MessageKey) : failureKey(f);
}
