// Pure rules of the "add doctors" card: what a row may hold, how it is checked and how the request is built. No DOM.

import type { DoctorAddFailure, DoctorPayload } from "./api";
import type { MessageKey } from "./i18n";

const MB = 1024 * 1024;
export const DOCTOR_LIMIT = 20;
export const NAME_MAX = 100;
export const SPECIALTY_MAX = 255;
export const TEXT_MAX = 2000;
export const EXPERIENCE_MAX = 80;
export const PHOTO_MAX_BYTES = 5 * MB;
export const PHOTO_ACCEPT = ".jpg,.jpeg,.png,.webp";

const PHOTO_EXT = ["jpg", "jpeg", "png", "webp"];
const PHOTO_MIME = ["image/jpeg", "image/png", "image/webp"];

export type DoctorField = "name" | "specialty" | "experience" | "qualification" | "bio" | "photo";
export const DOCTOR_FIELDS: readonly DoctorField[] = ["name", "specialty", "experience", "qualification", "bio", "photo"];

/** One row of the form as typed. `experience` stays text so an empty box is not forced to 0. */
export type DoctorDraft = {
  key: number;
  name: string;
  specialty: string;
  experience: string;
  qualification: string;
  bio: string;
  photo: File | null;
};

export type RowErrors = Partial<Record<DoctorField, MessageKey>>;

export const blankDoctor = (key: number): DoctorDraft => ({ key, name: "", specialty: "", experience: "", qualification: "", bio: "", photo: null });

/** Characters as people count them (an emoji is one), the same as the server's text length. */
const length = (value: string): number => Array.from(value).length;

const extensionOf = (name: string): string => {
  const dot = name.lastIndexOf(".");
  return dot < 0 ? "" : name.slice(dot + 1).toLowerCase();
};

/** The checks the server makes before it looks inside the file: extension, reported type (may be empty) and size. */
export function checkDoctorPhoto(file: { name: string; type: string; size: number }): "invalid_type" | "too_large" | null {
  if (!PHOTO_EXT.includes(extensionOf(file.name))) return "invalid_type";
  if (file.type !== "" && !PHOTO_MIME.includes(file.type.toLowerCase())) return "invalid_type";
  if (file.size <= 0) return "invalid_type";
  if (file.size > PHOTO_MAX_BYTES) return "too_large";
  return null;
}

/** Years of experience: empty counts as 0, a whole number is kept inside 0..80 (as the old panel does), anything else is wrong. */
export function parseExperience(value: string): number | null {
  const text = value.trim();
  if (text === "") return 0;
  if (!/^[+-]?\d{1,9}$/.test(text)) return null;
  return Math.max(0, Math.min(EXPERIENCE_MAX, Number(text)));
}

/** A row with no name is not saved (it is skipped, photo and all), exactly as in the old panel. */
export const hasName = (row: DoctorDraft): boolean => row.name.trim() !== "";

/** Problems of one named row, by field. */
export function validateRow(row: DoctorDraft): RowErrors {
  const errors: RowErrors = {};
  if (length(row.name.trim()) > NAME_MAX) errors.name = "h.e.doc.err.nameLong";
  if (length(row.specialty.trim()) > SPECIALTY_MAX) errors.specialty = "h.e.doc.err.specialtyLong";
  if (parseExperience(row.experience) === null) errors.experience = "h.e.doc.err.experience";
  if (length(row.qualification.trim()) > TEXT_MAX) errors.qualification = "h.e.doc.err.textLong";
  if (length(row.bio.trim()) > TEXT_MAX) errors.bio = "h.e.doc.err.textLong";
  if (row.photo) {
    const problem = checkDoctorPhoto(row.photo);
    if (problem) errors.photo = problem === "too_large" ? "h.e.doc.err.photoLarge" : "h.e.doc.err.photoType";
  }
  return errors;
}

export type BuiltRequest = {
  doctors: DoctorPayload[];
  photos: File[];
  /** The row each sent element came from: the server's `index` points into this list. */
  rowKeys: number[];
};

/** The request for the named rows: `photo` of an element is the position of its file in `photos`, or null. */
export function buildRequest(rows: readonly DoctorDraft[]): BuiltRequest {
  const doctors: DoctorPayload[] = [];
  const photos: File[] = [];
  const rowKeys: number[] = [];
  for (const row of rows) {
    if (!hasName(row)) continue;
    let photo: number | null = null;
    if (row.photo) {
      photo = photos.length;
      photos.push(row.photo);
    }
    doctors.push({
      name: row.name.trim(),
      specialty: row.specialty.trim(),
      experience: parseExperience(row.experience) ?? 0,
      qualification: row.qualification.trim(),
      bio: row.bio.trim(),
      photo,
    });
    rowKeys.push(row.key);
  }
  return { doctors, photos, rowKeys };
}

const FIELD_ERROR_KEYS: Record<DoctorField, MessageKey> = {
  name: "h.e.doc.err.name",
  specialty: "h.e.doc.err.specialtyLong",
  experience: "h.e.doc.err.experience",
  qualification: "h.e.doc.err.textLong",
  bio: "h.e.doc.err.textLong",
  photo: "h.e.doc.err.photoType",
};

const isField = (value: string): value is DoctorField => (DOCTOR_FIELDS as readonly string[]).includes(value);

/**
 * What a failed request says. `row` (the position in the sent list) and `field` place the message on one input;
 * without them the message belongs to the whole form.
 */
export function addFailure(f: DoctorAddFailure): { key: MessageKey; row: number | null; field: DoctorField | null } {
  const field = isField(f.field) ? f.field : null;
  const row = f.index;
  switch (f.error) {
    case "invalid_field":
      return { key: field ? FIELD_ERROR_KEYS[field] : "h.e.doc.err.invalid", row, field };
    case "invalid_type":
      return { key: "h.e.doc.err.photoType", row, field: "photo" };
    case "too_large":
      return { key: "h.e.doc.err.photoLarge", row, field: "photo" };
    case "too_many":
      return { key: "h.e.doc.err.many", row: null, field: null };
    case "no_doctors":
      return { key: "h.e.doc.err.none", row: null, field: null };
    case "upload_failed":
      return { key: "h.e.doc.err.failed", row: null, field: null };
    default:
      if (f.status === 0) return { key: "h.e.fail.network", row: null, field: null };
      if (f.status === 403) return { key: "h.e.fail.forbidden", row: null, field: null };
      if (f.status === 404) return { key: "h.e.fail.gone", row: null, field: null };
      if (f.status === 503) return { key: "h.e.doc.err.unavailable", row: null, field: null };
      return { key: "h.e.fail.server", row: null, field: null };
  }
}
