/** Pure parts of registering a hospital by a super admin: the request body, opening hours, file rules, storage paths and the SQL statements. */
import * as path from 'path';
import { DOCTOR_ADD_LIMIT, MAX_NAME, MAX_SPECIALTY, MAX_TEXT, parseExperience } from '../hospitals/hospitals.doctors.logic';
import { checkDocument, DocKind } from '../hospitals/hospitals.documents.logic';
import { checkImage, ImageKind, ImageRuleField } from '../hospitals/hospitals.images.logic';
import { Query } from '../hospitals/hospitals.logic';
import { MAX_DOSAGE, MAX_OPTION_NAME, MAX_PRICE, MAX_UNIT, TREATMENT_ASSIGN_LIMIT } from '../hospitals/hospitals.treatments.logic';
import { checkTreatmentImage } from '../hospitals/hospitals.treatments.images.logic';
import { phoneFormat } from '../hospitals/hospitals.write.logic';

// ---------- limits (the same as the PHP form and the other hospital routes) ----------

export const INTERIOR_MAX = 10;
export const PRICE_ROWS_MAX = 50;
export const TREATMENT_IMAGES_MAX = 20;
/** Size of the `payload` text field. */
export const MAX_PAYLOAD_BYTES = 600 * 1024;
/** Every file of one request: certificates (2), main (1), interior (10), doctor photos (20), treatment detail images (up to 100). */
export const MAX_FILES = 2 + 1 + INTERIOR_MAX + DOCTOR_ADD_LIMIT + 100;
/** The largest single file of any field (the parser's ceiling; each field has its own, smaller or equal, limit below). */
export const MAX_ANY_FILE_BYTES = 10 * 1024 * 1024;
export const LIMITS = { interiorMax: INTERIOR_MAX, certMaxMb: 5, imageMaxMb: 5, interiorMaxMb: 10, treatmentImageMaxMb: 10, doctorPhotoMaxMb: 5 };

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const HTTP_URL = /^https?:\/\/\S+$/i;
const chars = (s: string): number => Array.from(s).length;

// ---------- errors ----------

export interface ApplyErrorBody {
  error: 'invalid_body' | 'invalid_field' | 'duplicate_phone' | 'too_many' | 'file_too_large' | 'bad_file_type';
  field?: string;
  index?: number;
  hospitalName?: string;
}
export type Parsed<T> = { ok: true; value: T } | { ok: false; body: ApplyErrorBody };
const bad = (field: string, index?: number): { ok: false; body: ApplyErrorBody } => ({ ok: false, body: { error: 'invalid_field', field, ...(index !== undefined ? { index } : {}) } });

// ---------- opening hours (same cleaning as the PHP page) ----------

const DAYS = ['월', '화', '수', '목', '금', '토', '일'] as const;
const CLOSED = ['휴무', '공사중', '임시휴업'];
const validTime = (t: string): boolean => {
  const m = /^(\d{2}):(\d{2})$/.exec(t);
  return m !== null && Number(m[1]) <= 23 && Number(m[2]) <= 59;
};
/** "HH:MM~HH:MM" with a valid, increasing pair, or null. */
function range(v: unknown): string | null {
  const m = /^(\d{2}:\d{2})~(\d{2}:\d{2})$/.exec(typeof v === 'string' ? v.trim() : '');
  return m && validTime(m[1]) && validTime(m[2]) && m[1] < m[2] ? `${m[1]}~${m[2]}` : null;
}
function realDate(s: string): boolean {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
  if (!m) return false;
  const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])];
  const date = new Date(Date.UTC(y, mo - 1, d));
  return date.getUTCFullYear() === y && date.getUTCMonth() === mo - 1 && date.getUTCDate() === d;
}

/** Today's date (YYYY-MM-DD) in Korea, where the hospitals are: extra closed days must come after it. */
export const todayKst = (now: Date = new Date()): string => new Date(now.getTime() + 9 * 3600 * 1000).toISOString().slice(0, 10);

/** Each day: a valid range or one of the closed words, anything else becomes 휴무; lunch break or no_lunch_break; extra closed days: real dates after `today`, sorted, without repeats. */
export function normalizeBusinessHours(raw: unknown, today: string = todayKst()): string {
  if (raw === null || typeof raw !== 'object' || Array.isArray(raw)) return '[]';
  const src = raw as Record<string, unknown>;
  const out: Record<string, unknown> = {};
  for (const day of DAYS) {
    const v = typeof src[day] === 'string' ? (src[day] as string).trim() : '';
    out[day] = CLOSED.includes(v) ? v : range(v) ?? '휴무';
  }
  if (src.no_lunch_break === true) out.no_lunch_break = true;
  else {
    const lunch = range(src['점심']);
    if (lunch) out['점심'] = lunch;
  }
  const dates = Array.isArray(src.special_closed_dates) ? src.special_closed_dates : [];
  out.special_closed_dates = Array.from(new Set(dates.map((d) => (typeof d === 'string' ? d.trim() : '')).filter((d) => realDate(d) && d > today))).sort();
  return JSON.stringify(out);
}

// ---------- the payload ----------

export interface PriceInput {
  optionName: string;
  dosageValue: number;
  dosageUnit: string;
  price: number;
}
export interface TreatmentInput {
  treatmentId: number;
  prices: PriceInput[];
}
export interface DoctorRow {
  /** position in the submitted list (names the file field doctorPhoto_<index>) */
  index: number;
  name: string;
  specialty: string;
  experience: number;
  qualification: string;
  bio: string;
}
export interface ApplyInput {
  name: string;
  ownerName: string;
  businessNumber: string;
  phone: string;
  email: string;
  contactName: string;
  contactPhone: string;
  address: string;
  lat: number | null;
  lng: number | null;
  businessHours: string;
  introduction: string;
  website: string;
  snsUrl: string;
  treatments: TreatmentInput[];
  doctors: DoctorRow[];
  /** price rows that were not complete and are left out (not an error) */
  skippedPriceRows: number;
}

/** Optional text: absent / null -> '', a string is trimmed; anything else is wrong (undefined). */
function text(v: unknown): string | undefined {
  if (v === undefined || v === null) return '';
  return typeof v === 'string' ? v.trim() : undefined;
}
const isObject = (v: unknown): v is Record<string, unknown> => v !== null && typeof v === 'object' && !Array.isArray(v);

function coordinate(v: unknown, limit: number): number | null | undefined {
  if (v === undefined || v === null) return null;
  return typeof v === 'number' && Number.isFinite(v) && Math.abs(v) <= limit ? v : undefined;
}

/** A price row is stored only when dosage > 0, unit and price > 0 are all there; an incomplete or empty row is dropped silently (the PHP page does the same). */
function parsePrices(raw: unknown, ti: number): Parsed<{ rows: PriceInput[]; skipped: number }> {
  if (raw === undefined || raw === null) return { ok: true, value: { rows: [], skipped: 0 } };
  if (!Array.isArray(raw) || raw.length > PRICE_ROWS_MAX) return bad('treatments', ti);
  const rows: PriceInput[] = [];
  let skipped = 0;
  for (const r of raw) {
    if (!isObject(r)) return bad('treatments', ti);
    const optionName = text(r.optionName);
    if (optionName === undefined || chars(optionName) > MAX_OPTION_NAME) return bad('treatments', ti);
    const unit = text(r.dosageUnit);
    if (unit === undefined || chars(unit) > MAX_UNIT) return bad('treatments', ti);
    const dosage = typeof r.dosageValue === 'number' && Number.isFinite(r.dosageValue) ? r.dosageValue : 0;
    const price = typeof r.price === 'number' && Number.isFinite(r.price) ? r.price : 0;
    if (r.dosageValue !== undefined && r.dosageValue !== null && r.dosageValue !== '' && typeof r.dosageValue !== 'number') return bad('treatments', ti);
    if (r.price !== undefined && r.price !== null && r.price !== '' && typeof r.price !== 'number') return bad('treatments', ti);
    if (dosage > MAX_DOSAGE || price > MAX_PRICE || (price > 0 && !Number.isInteger(price))) return bad('treatments', ti);
    const dosageValue = Math.round(dosage * 100) / 100;
    if (dosageValue > 0 && unit !== '' && price > 0) rows.push({ optionName, dosageValue, dosageUnit: unit, price });
    else skipped += 1;
  }
  return { ok: true, value: { rows, skipped } };
}

/** First stage: the JSON text, the name and the phone number, so the duplicate-phone question can come before the e-mail check (the order of the PHP page). */
export function parseHead(raw: unknown): Parsed<{ name: string; phone: string }> {
  const json = parseJson(raw);
  if (!json.ok) return json;
  const body = json.value;
  const name = text(body.name);
  if (name === undefined || name === '' || chars(name) > 255) return bad('name');
  const phone = text(body.phone);
  if (phone === undefined || chars(phone) > 20) return bad('phone');
  return { ok: true, value: { name, phone: phone === '' ? '' : phoneFormat(phone) } };
}

function parseJson(raw: unknown): Parsed<Record<string, unknown>> {
  if (typeof raw !== 'string' || raw.trim() === '' || Buffer.byteLength(raw, 'utf8') > MAX_PAYLOAD_BYTES) return { ok: false, body: { error: 'invalid_body' } };
  let body: unknown;
  try {
    body = JSON.parse(raw);
  } catch {
    return { ok: false, body: { error: 'invalid_body' } };
  }
  return isObject(body) ? { ok: true, value: body } : { ok: false, body: { error: 'invalid_body' } };
}

/** The `payload` JSON text -> what will be stored (second stage: the whole form). */
export function parsePayload(raw: unknown): Parsed<ApplyInput> {
  const json = parseJson(raw);
  if (!json.ok) return json;
  const body = json.value;

  const field = (key: string, max: number): string | null => {
    const v = text(body[key]);
    return v === undefined || chars(v) > max ? null : v;
  };
  const name = field('name', 255);
  if (name === null || name === '') return bad('name');
  const ownerName = field('ownerName', 100);
  if (ownerName === null) return bad('ownerName');
  const businessNumber = field('businessNumber', 20);
  if (businessNumber === null) return bad('businessNumber');
  const phoneRaw = field('phone', 20);
  if (phoneRaw === null) return bad('phone');
  const email = field('email', 191);
  if (email === null || (email !== '' && !EMAIL.test(email))) return bad('email');
  const contactName = field('contactName', 100);
  if (contactName === null) return bad('contactName');
  const contactPhone = field('contactPhone', 20);
  if (contactPhone === null) return bad('contactPhone');
  const address = field('address', 500);
  if (address === null) return bad('address');
  const introduction = field('introduction', MAX_TEXT);
  if (introduction === null) return bad('introduction');
  const website = field('website', 255);
  if (website === null || (website !== '' && !HTTP_URL.test(website))) return bad('website');
  // the SNS field is free text (a handle or an address), as on the PHP page: only its length is limited
  const snsUrl = field('snsUrl', 255);
  if (snsUrl === null) return bad('snsUrl');

  // coordinates: both numbers in range, or both empty
  const lat = coordinate(body.lat, 90);
  const lng = coordinate(body.lng, 180);
  if (lat === undefined || lng === undefined || (lat === null) !== (lng === null)) return bad('lat');

  const treatments: TreatmentInput[] = [];
  let skippedPriceRows = 0;
  if (body.treatments !== undefined && body.treatments !== null) {
    if (!Array.isArray(body.treatments) || body.treatments.length > TREATMENT_ASSIGN_LIMIT) return bad('treatments');
    const seen = new Set<number>();
    for (let i = 0; i < body.treatments.length; i += 1) {
      const t: unknown = body.treatments[i];
      if (!isObject(t)) return bad('treatments', i);
      const id = t.treatmentId;
      if (typeof id !== 'number' || !Number.isInteger(id) || id <= 0 || seen.has(id)) return bad('treatments', i);
      seen.add(id);
      const prices = parsePrices(t.prices, i);
      if (!prices.ok) return prices;
      skippedPriceRows += prices.value.skipped;
      treatments.push({ treatmentId: id, prices: prices.value.rows });
    }
  }

  const doctors: DoctorRow[] = [];
  if (body.doctors !== undefined && body.doctors !== null) {
    if (!Array.isArray(body.doctors) || body.doctors.length > DOCTOR_ADD_LIMIT) return bad('doctors');
    for (let index = 0; index < body.doctors.length; index += 1) {
      const d: unknown = body.doctors[index];
      if (!isObject(d)) return bad('doctors', index);
      const dn = text(d.name);
      if (dn === undefined || chars(dn) > MAX_NAME) return bad('doctors', index);
      // a row without a name is not a doctor: left out whole, as the PHP page does
      if (dn === '') continue;
      const specialty = text(d.specialty);
      if (specialty === undefined || chars(specialty) > MAX_SPECIALTY) return bad('doctors', index);
      const experience = parseExperience(d.experienceYears);
      if (experience === null) return bad('doctors', index);
      const qualification = text(d.qualification);
      if (qualification === undefined || chars(qualification) > MAX_TEXT) return bad('doctors', index);
      const bio = text(d.bio);
      if (bio === undefined || chars(bio) > MAX_TEXT) return bad('doctors', index);
      doctors.push({ index, name: dn, specialty, experience, qualification, bio });
    }
  }

  return {
    ok: true,
    value: {
      name, ownerName, businessNumber, phone: phoneRaw === '' ? '' : phoneFormat(phoneRaw), email, contactName, contactPhone, address,
      lat, lng, businessHours: normalizeBusinessHours(body.businessHours), introduction, website, snsUrl, treatments, doctors, skippedPriceRows,
    },
  };
}

// ---------- files ----------

/** Names of the file fields of the form. Anything else is refused by the parser. */
export const FILE_FIELD_RE = /^(certBusiness|certMedical|profileImage|interiorImages|doctorPhoto_\d{1,2}|treatmentImages_\d{1,10})$/;

export type FieldKind = 'certBusiness' | 'certMedical' | 'profileImage' | 'interiorImages' | 'doctorPhoto' | 'treatmentImages';
export const kindOfField = (name: string): FieldKind => (name.startsWith('doctorPhoto_') ? 'doctorPhoto' : name.startsWith('treatmentImages_') ? 'treatmentImages' : (name as FieldKind));

/** Real type of a file against the rule of its field: the extension kept for it (`jpg`...) or an error code. */
export type FileVerdict = { ok: true; ext: string } | { ok: false; error: 'file_too_large' | 'bad_file_type' };
export function judgeFile(kind: FieldKind, originalName: string, size: number, head: Buffer): FileVerdict {
  const map = (r: { ok: true; kind: string } | { ok: false; error: string }): FileVerdict =>
    r.ok ? { ok: true, ext: r.kind } : { ok: false, error: r.error === 'too_large' ? 'file_too_large' : 'bad_file_type' };
  switch (kind) {
    case 'certBusiness':
    case 'certMedical':
      return map(checkDocument(originalName, size, head));
    case 'treatmentImages':
      return map(checkTreatmentImage(originalName, size, head));
    default: {
      const rule: ImageRuleField = kind === 'profileImage' ? 'main' : kind === 'interiorImages' ? 'interior' : 'doctor';
      return map(checkImage(rule, originalName, size, head));
    }
  }
}

export type ApplyFileType = DocKind | ImageKind | 'gif';

export interface ApplyStored {
  /** Value stored in the database: relative to the PHP admin directory. */
  url: string;
  absolute: string;
  dir: string;
}

/**
 * Where a file of the form goes before the hospital has an id: <root>/uploads/hospital_apply/admin_<admin id>/<prefix>_<32 hex>.<ext>
 * (the folder the PHP page uses). Nothing in it comes from the client: the admin id is a validated integer, the name part is random hex,
 * the extension comes from the real type. `cert_business_` / `cert_medical_` names also match the shape the PHP document viewer accepts.
 */
export function buildApplyPath(root: string, adminId: number, prefix: 'cert_business' | 'cert_medical' | 'profile' | 'interior', hex: string, ext: string): ApplyStored {
  if (!Number.isInteger(adminId) || adminId <= 0) throw new Error('invalid admin id');
  if (!/^[0-9a-f]{32}$/.test(hex)) throw new Error('invalid file token');
  if (!/^(jpg|png|webp|pdf)$/.test(ext)) throw new Error('invalid extension');
  const base = path.resolve(root);
  const rel = `uploads/hospital_apply/admin_${adminId}`;
  const dir = path.resolve(base, rel);
  const absolute = path.resolve(dir, `${prefix}_${hex}.${ext}`);
  if (!dir.startsWith(base + path.sep) || !absolute.startsWith(dir + path.sep)) throw new Error('path leaves the upload root');
  return { url: `${rel}/${prefix}_${hex}.${ext}`, absolute, dir };
}

// ---------- SQL ----------

export interface HospitalValues {
  input: ApplyInput;
  appliedBy: string;
  reviewedBy: string;
  profileUrl: string;
  certBusiness: string;
  certMedical: string;
  isDemo: 0 | 1;
}

/** The new hospital: approved and switched off (inactive), nothing to review. Every value is a parameter. */
export const insertHospitalQuery = (v: HospitalValues): Query => ({
  sql: `INSERT INTO gangnamunni_hospitals (name, owner_name, business_number, phone, email, contact_name, contact_phone, address, latitude, longitude, categories, treatment_tags,
    business_hours, introduction, website, sns_url, profile_image_url, main_image_url, cert_business, cert_medical, applied_by, applied_at, status, is_active, ad_grade,
    reviewed_by, reviewed_at, is_demo, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '[]', '[]', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'approved', 0, 'none', ?, NOW(), ?, NOW())`,
  values: [
    v.input.name, v.input.ownerName, v.input.businessNumber, v.input.phone, v.input.email, v.input.contactName, v.input.contactPhone, v.input.address, v.input.lat, v.input.lng,
    v.input.businessHours, v.input.introduction, v.input.website, v.input.snsUrl, v.profileUrl, v.profileUrl, v.certBusiness, v.certMedical, v.appliedBy, v.reviewedBy, v.isDemo,
  ],
});

export const optionCategoriesQuery = (): Query => ({ sql: 'SELECT id, name FROM gangnamunni_treatment_categories ORDER BY sort_order ASC, id ASC', values: [] });
export const optionTreatmentsQuery = (): Query => ({
  sql: 'SELECT id, name, category_id FROM gangnamunni_treatment_master WHERE is_active = 1 ORDER BY sort_order ASC, id ASC',
  values: [],
});
export const devModeQuery = (): Query => ({ sql: `SELECT setting_value FROM gangnamunni_settings WHERE setting_key = 'dev_mode' LIMIT 1`, values: [] });
export const phoneHoldersQuery = (): Query => ({ sql: `SELECT id, name, phone FROM gangnamunni_hospitals WHERE phone IS NOT NULL AND phone <> ''`, values: [] });
