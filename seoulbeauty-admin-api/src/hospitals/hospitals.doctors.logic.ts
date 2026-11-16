/** Pure parts of adding doctors to a hospital: checking the submitted rows, storage paths of the photos and the shape of the answer. */
import * as path from 'path';
import { ImageKind } from './hospitals.images.logic';
import { doctorsQuery, num, Query, str } from './hospitals.logic';

type Row = Record<string, unknown>;

/** Doctors per request (counted like the PHP form: every submitted row, blank ones included). */
export const DOCTOR_ADD_LIMIT = 20;
export const MAX_NAME = 100;
export const MAX_SPECIALTY = 255;
export const MAX_TEXT = 2000;
export const MIN_EXPERIENCE = 0;
export const MAX_EXPERIENCE = 80;
/** Size of the `doctors` JSON field. */
export const MAX_DOCTORS_FIELD_BYTES = 200 * 1024;

export type DoctorField = 'name' | 'specialty' | 'experience' | 'qualification' | 'bio' | 'photo';
export type DoctorsErrorCode = 'no_doctors' | 'too_many' | 'invalid_field';
export interface DoctorsError {
  error: DoctorsErrorCode;
  index?: number;
  field?: DoctorField;
}

/** One doctor that will be stored. `index` is the position in the submitted list; `photo` is a position in the uploaded photo list. */
export interface DoctorInput {
  index: number;
  name: string;
  specialty: string;
  experience: number;
  qualification: string;
  bio: string;
  photo: number | null;
}
export type ParsedDoctors = { ok: true; doctors: DoctorInput[] } | { ok: false; body: DoctorsError };

const bad = (index: number | undefined, field: DoctorField | undefined): ParsedDoctors => {
  const body: DoctorsError = { error: 'invalid_field' };
  if (index !== undefined) body.index = index;
  if (field) body.field = field;
  return { ok: false, body };
};
const chars = (s: string): number => Array.from(s).length;

/** Optional text: absent or null -> '', a string is trimmed, anything else is wrong (undefined result). */
function text(v: unknown): string | undefined {
  if (v === undefined || v === null) return '';
  return typeof v === 'string' ? v.trim() : undefined;
}

/** Years: absent / null / '' -> 0; an integer (or a string of digits) is clamped to 0..80 like the PHP form; anything else is wrong (null). */
export function parseExperience(v: unknown): number | null {
  if (v === undefined || v === null) return 0;
  let n: number;
  if (typeof v === 'number') n = v;
  else if (typeof v === 'string') {
    const t = v.trim();
    if (t === '') return 0;
    if (!/^[+-]?\d{1,9}$/.test(t)) return null;
    n = Number(t);
  } else return null;
  if (!Number.isInteger(n)) return null;
  return Math.max(MIN_EXPERIENCE, Math.min(MAX_EXPERIENCE, n));
}

/**
 * The `doctors` field (a JSON text: [{name, specialty, experience, qualification, bio, photo}]) -> doctors to store.
 * Rows without a name are skipped whole (their photo too), as the PHP form does; at least one named doctor is required.
 */
export function parseDoctors(raw: unknown, photoCount: number): ParsedDoctors {
  if (raw === undefined || raw === null || (typeof raw === 'string' && raw.trim() === '')) return { ok: false, body: { error: 'no_doctors' } };
  if (typeof raw !== 'string' || Buffer.byteLength(raw, 'utf8') > MAX_DOCTORS_FIELD_BYTES) return bad(undefined, undefined);
  let list: unknown;
  try {
    list = JSON.parse(raw);
  } catch {
    return bad(undefined, undefined);
  }
  if (!Array.isArray(list)) return bad(undefined, undefined);
  if (list.length > DOCTOR_ADD_LIMIT) return { ok: false, body: { error: 'too_many' } };
  const doctors: DoctorInput[] = [];
  const usedPhotos = new Set<number>();
  for (let index = 0; index < list.length; index += 1) {
    const row = list[index];
    if (row === null || typeof row !== 'object' || Array.isArray(row)) return bad(index, undefined);
    const r = row as Record<string, unknown>;
    const name = text(r.name);
    if (name === undefined) return bad(index, 'name');
    if (name === '') continue;
    if (chars(name) > MAX_NAME) return bad(index, 'name');
    const specialty = text(r.specialty);
    if (specialty === undefined || chars(specialty) > MAX_SPECIALTY) return bad(index, 'specialty');
    const experience = parseExperience(r.experience);
    if (experience === null) return bad(index, 'experience');
    const qualification = text(r.qualification);
    if (qualification === undefined || chars(qualification) > MAX_TEXT) return bad(index, 'qualification');
    const bio = text(r.bio);
    if (bio === undefined || chars(bio) > MAX_TEXT) return bad(index, 'bio');
    let photo: number | null = null;
    if (r.photo !== undefined && r.photo !== null) {
      const p = r.photo;
      if (typeof p !== 'number' || !Number.isInteger(p) || p < 0 || p >= photoCount || usedPhotos.has(p)) return bad(index, 'photo');
      photo = p;
      usedPhotos.add(p);
    }
    doctors.push({ index, name, specialty, experience, qualification, bio, photo });
  }
  if (doctors.length === 0) return { ok: false, body: { error: 'no_doctors' } };
  return { ok: true, doctors };
}

export interface DoctorPhotoPath {
  /** Value of gangnamunni_doctors.photo_url: relative to the PHP admin directory. */
  url: string;
  /** Absolute file path inside the upload root. */
  absolute: string;
  /** Absolute directory of the hospital. */
  dir: string;
}

/**
 * Where the photo of a stored doctor goes: <root>/uploads/hospitals/<hospital>/doctor_<doctor id>_<32 hex>.<jpg|png|webp>.
 * Nothing in it comes from the client: both ids are validated integers, the name part is random hex, the extension comes from the real type.
 * Throws when an id, the random part or the result would leave the root.
 */
export function buildDoctorPhotoPath(root: string, hospitalId: number, doctorId: number, hex: string, kind: ImageKind): DoctorPhotoPath {
  if (!Number.isInteger(hospitalId) || hospitalId <= 0) throw new Error('invalid hospital id');
  if (!Number.isInteger(doctorId) || doctorId <= 0) throw new Error('invalid doctor id');
  if (!/^[0-9a-f]{32}$/.test(hex)) throw new Error('invalid file token');
  const base = path.resolve(root);
  const rel = `uploads/hospitals/${hospitalId}`;
  const dir = path.resolve(base, rel);
  const file = `doctor_${doctorId}_${hex}.${kind}`;
  const absolute = path.resolve(dir, file);
  if (!dir.startsWith(base + path.sep) || !absolute.startsWith(dir + path.sep)) throw new Error('path leaves the upload root');
  return { url: `${rel}/${file}`, absolute, dir };
}

export interface DoctorItem {
  id: number;
  name: string;
  specialty: string;
  experience: number;
  qualification: string;
  bio: string;
  photoUrl: string | null;
  status: string;
}
export interface DoctorsResult {
  items: DoctorItem[];
  count: number;
  addLimit: number;
}

/** Rows of gangnamunni_doctors (ordered by id) -> the list shown on the card. An empty photo_url is null; the url is otherwise returned as stored. */
export function summarizeDoctors(rows: Row[]): DoctorsResult {
  const items: DoctorItem[] = rows.map((r) => ({
    id: num(r.id),
    name: str(r.name),
    specialty: str(r.specialty),
    experience: Math.max(0, Math.trunc(num(r.experience))),
    qualification: str(r.qualification),
    bio: str(r.bio),
    photoUrl: str(r.photo_url).trim() === '' ? null : str(r.photo_url),
    status: str(r.status),
  }));
  return { items, count: items.length, addLimit: DOCTOR_ADD_LIMIT };
}

export const doctorRowsQuery = (hospitalId: number): Query => doctorsQuery(hospitalId);
export const insertDoctorQuery = (hospitalId: number, d: DoctorInput): Query => ({
  sql: `INSERT INTO gangnamunni_doctors (hospital_id, name, specialty, experience, qualification, bio, photo_url, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, '', 'active', NOW(), NOW())`,
  values: [hospitalId, d.name, d.specialty, d.experience, d.qualification, d.bio],
});
export const updateDoctorPhotoQuery = (doctorId: number, hospitalId: number, url: string): Query => ({
  sql: `UPDATE gangnamunni_doctors SET photo_url = ? WHERE id = ? AND hospital_id = ?`,
  values: [url, doctorId, hospitalId],
});
