/** Pure parts of the hospital writes: phone formatting, request validation and the shape of what is stored. */

export type FieldError = 'required' | 'too_long' | 'bad_format' | 'bad_url' | 'duplicate_phone' | 'not_allowed';

/** Digits only, with the Korean country code folded into the leading 0 (same rule as the PHP admin helper). */
export function phoneNormalize(raw: unknown): string {
  let digits = String(raw ?? '').trim().replace(/\D+/g, '');
  if (digits.startsWith('0082')) digits = '0' + digits.slice(4);
  else if (digits.startsWith('82')) digits = '0' + digits.slice(2);
  return digits;
}

/** Hyphenated domestic number; text without digits is returned trimmed, never rejected (same rule as the PHP admin helper). */
export function phoneFormat(raw: unknown): string {
  let phone = phoneNormalize(raw);
  if (phone === '') return String(raw ?? '').trim();
  let m: RegExpMatchArray | null;
  if ((m = phone.match(/^(02)(\d{3,4})(\d{4})$/))) return `${m[1]}-${m[2]}-${m[3]}`;
  if ((m = phone.match(/^(050[2-8])(\d{3,4})(\d{4})$/))) return `${m[1]}-${m[2]}-${m[3]}`;
  if ((m = phone.match(/^(0\d{2})(\d{3,4})(\d{4})$/))) return `${m[1]}-${m[2]}-${m[3]}`;
  if ((m = phone.match(/^(1[5-8]\d{2})(\d{4})$/))) return `${m[1]}-${m[2]}`;
  const groups: string[] = [];
  while (phone.length > 4) {
    groups.unshift(phone.slice(-4));
    phone = phone.slice(0, -4);
  }
  if (phone !== '') groups.unshift(phone);
  return groups.join('-');
}

/** True when another hospital (rows = every other hospital that has a phone) already uses this number, ignoring formatting. */
export function phoneTaken(phone: string, others: { phone?: unknown }[]): boolean {
  const needle = phoneNormalize(phone);
  if (needle === '') return false;
  return others.some((o) => phoneNormalize(o.phone) === needle);
}

export const MAX_LIST_ITEMS = 50;
export const MAX_LIST_ITEM_LENGTH = 100;
export const MAX_HOURS_JSON_LENGTH = 4000;

type Kind = 'text' | 'url' | 'email' | 'phone' | 'list' | 'hours' | 'lat' | 'lng' | 'bool';
interface Spec {
  column: string;
  kind: Kind;
  max?: number;
  required?: boolean;
}
/** The only fields a write may touch, and the column each one is stored in. Anything else is rejected as not_allowed. */
export const PATCH_FIELDS: Record<string, Spec> = {
  name: { column: 'name', kind: 'text', max: 255, required: true },
  ownerName: { column: 'owner_name', kind: 'text', max: 100 },
  businessNumber: { column: 'business_number', kind: 'text', max: 20 },
  introduction: { column: 'introduction', kind: 'text', max: 10000 },
  address: { column: 'address', kind: 'text', max: 500 },
  phone: { column: 'phone', kind: 'phone', max: 50 },
  email: { column: 'email', kind: 'email', max: 191 },
  contactName: { column: 'contact_name', kind: 'text', max: 100 },
  contactPhone: { column: 'contact_phone', kind: 'text', max: 20 },
  website: { column: 'website', kind: 'url', max: 255 },
  snsUrl: { column: 'sns_url', kind: 'url', max: 255 },
  categories: { column: 'categories', kind: 'list' },
  badges: { column: 'badges', kind: 'list' },
  treatmentTags: { column: 'treatment_tags', kind: 'list' },
  subwayNames: { column: 'subway_names', kind: 'list' },
  businessHours: { column: 'business_hours', kind: 'hours' },
  latitude: { column: 'latitude', kind: 'lat' },
  longitude: { column: 'longitude', kind: 'lng' },
  showRating: { column: 'show_rating', kind: 'bool' },
};
/** Columns the writing database user may update on gangnamunni_hospitals (everything a write here can set, plus the two switches). */
export const WRITABLE_COLUMNS = [...Object.values(PATCH_FIELDS).map((s) => s.column), 'is_active', 'has_unreviewed_changes', 'updated_at'];

export interface PatchPlan {
  /** column -> value to bind, in a fixed order */
  sets: { column: string; value: string | number | null }[];
  /** request field names that are written (for the audit log; never values) */
  names: string[];
  /** formatted phone when it is written and not empty (needs the duplicate check) */
  phone: string | null;
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const HTTP_URL = /^https?:\/\//i;
const isObject = (v: unknown): v is Record<string, unknown> => !!v && typeof v === 'object' && !Array.isArray(v);
/** Length in characters (code points), like the browser counts it. */
const len = (s: string): number => Array.from(s).length;

/** The value stored in a tag column: JSON array text, unicode unescaped, as PHP's JSON_UNESCAPED_UNICODE lists are ('[]' when empty). */
export const listJson = (items: string[]): string => JSON.stringify(items);

/** Validates `fields` of a PATCH. Returns the plan, or the per-field errors (the first problem of each field). */
export function planPatch(fields: unknown): { ok: true; plan: PatchPlan } | { ok: false; errors: Record<string, FieldError> } {
  const errors: Record<string, FieldError> = {};
  if (!isObject(fields) || Object.keys(fields).length === 0) return { ok: false, errors: { fields: 'required' } };
  const sets: PatchPlan['sets'] = [];
  const names: string[] = [];
  let phone: string | null = null;

  for (const key of Object.keys(fields)) {
    const spec = Object.prototype.hasOwnProperty.call(PATCH_FIELDS, key) ? PATCH_FIELDS[key] : undefined;
    if (!spec) {
      errors[key] = 'not_allowed';
      continue;
    }
    if (spec.kind === 'lat' || spec.kind === 'lng') continue; // checked together below
    const v = (fields as Record<string, unknown>)[key];
    const bad = (e: FieldError) => {
      errors[key] = e;
    };
    switch (spec.kind) {
      case 'text':
      case 'url':
      case 'email':
      case 'phone': {
        if (typeof v !== 'string') {
          bad(spec.required && (v === null || v === undefined) ? 'required' : 'bad_format');
          break;
        }
        let s = v.trim();
        if (spec.required && s === '') {
          bad('required');
          break;
        }
        if (spec.kind === 'phone' && s !== '') s = phoneFormat(s);
        if (len(s) > (spec.max as number)) {
          bad('too_long');
          break;
        }
        if (spec.kind === 'email' && s !== '' && !EMAIL.test(s)) {
          bad('bad_format');
          break;
        }
        if (spec.kind === 'url' && s !== '' && !HTTP_URL.test(s)) {
          bad('bad_url');
          break;
        }
        if (spec.kind === 'phone' && s !== '') phone = s;
        sets.push({ column: spec.column, value: s });
        names.push(key);
        break;
      }
      case 'list': {
        if (!Array.isArray(v) || v.some((x) => typeof x !== 'string')) {
          bad('bad_format');
          break;
        }
        const items = Array.from(new Set((v as string[]).map((x) => x.trim()).filter((x) => x !== '')));
        if (items.length > MAX_LIST_ITEMS || items.some((x) => len(x) > MAX_LIST_ITEM_LENGTH)) {
          bad('too_long');
          break;
        }
        sets.push({ column: spec.column, value: listJson(items) });
        names.push(key);
        break;
      }
      case 'hours': {
        if (!isObject(v)) {
          bad('bad_format');
          break;
        }
        const json = JSON.stringify(v);
        if (json.length > MAX_HOURS_JSON_LENGTH) {
          bad('too_long');
          break;
        }
        sets.push({ column: spec.column, value: json });
        names.push(key);
        break;
      }
      case 'bool': {
        if (typeof v !== 'boolean') {
          bad('bad_format');
          break;
        }
        sets.push({ column: spec.column, value: v ? 1 : 0 });
        names.push(key);
        break;
      }
    }
  }

  // Coordinates: both numbers in range, or both null; one without the other is incomplete.
  const f = fields as Record<string, unknown>;
  const hasLat = 'latitude' in f;
  const hasLng = 'longitude' in f;
  if (hasLat || hasLng) {
    const coord = (v: unknown, lim: number): number | null | undefined =>
      v === null ? null : typeof v === 'number' && Number.isFinite(v) && Math.abs(v) <= lim ? v : undefined;
    if (hasLat !== hasLng) errors[hasLat ? 'longitude' : 'latitude'] = 'required';
    else {
      const lat = coord(f.latitude, 90);
      const lng = coord(f.longitude, 180);
      if (lat === undefined) errors.latitude = 'bad_format';
      if (lng === undefined) errors.longitude = 'bad_format';
      if (lat !== undefined && lng !== undefined) {
        if ((lat === null) !== (lng === null)) errors[lat === null ? 'latitude' : 'longitude'] = 'required';
        else {
          sets.push({ column: 'latitude', value: lat }, { column: 'longitude', value: lng });
          names.push('latitude', 'longitude');
        }
      }
    }
  }

  if (Object.keys(errors).length > 0) return { ok: false, errors };
  return { ok: true, plan: { sets, names, phone } };
}

/** "YYYY-MM-DD HH:MM:SS" as the detail page returns it, or null. Anything else is undefined (invalid). */
export function parseExpectedUpdatedAt(v: unknown): string | null | undefined {
  if (v === null) return null;
  return typeof v === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(v) ? v : undefined;
}
