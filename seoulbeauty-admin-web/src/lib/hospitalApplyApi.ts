// The three calls the registration page makes: the lists it offers (treatments, units), the duplicate check of the
// phone number and the registration itself (one multipart request: a `payload` JSON text and the chosen files).

import { BASE_PATH } from "./config";
import { priceState, type CategoryOption, type DayStatus, type Draft, type TreatmentOption } from "./hospitalApply";
import { WEEKDAYS } from "./hospitals";

type Json = Record<string, unknown>;

const obj = (v: unknown): Json => (v !== null && typeof v === "object" && !Array.isArray(v) ? (v as Json) : {});
const num = (v: unknown): number => (typeof v === "number" && Number.isFinite(v) ? v : 0);
const str = (v: unknown): string => (typeof v === "string" ? v : "");
const list = (v: unknown): unknown[] => (Array.isArray(v) ? v : []);

const HEADERS = { Accept: "application/json", "X-Requested-With": "admin-next" } as const;

async function readJson(res: Response): Promise<Json> {
  try {
    return obj(await res.json());
  } catch {
    // An empty or non-JSON body (a proxy error page, say): the status alone says what happened.
    return {};
  }
}

// ───────── Lists ─────────

export type ApplyOptions = {
  categories: CategoryOption[];
  treatments: TreatmentOption[];
  units: string[];
  /** The server is in test mode: what is registered is marked as demo data. */
  devMode: boolean;
};

export type OptionsResult = { ok: true; data: ApplyOptions } | { ok: false; status: number };

export async function fetchApplyOptions(signal?: AbortSignal): Promise<OptionsResult> {
  try {
    const res = await fetch(`${BASE_PATH}/api/v1/hospital-apply/options`, { credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" }, signal });
    if (!res.ok) return { ok: false, status: res.status };
    const d = await readJson(res);
    const categories = list(d.categories)
      .map((c) => ({ id: num(obj(c).id), name: str(obj(c).name) }))
      .filter((c) => c.id > 0 && c.name !== "");
    const treatments = list(d.treatments)
      .map((t) => ({ id: num(obj(t).id), name: str(obj(t).name), categoryId: num(obj(t).categoryId) }))
      .filter((t) => t.id > 0 && t.name !== "");
    const units = list(d.units).filter((u): u is string => typeof u === "string" && u.trim() !== "");
    return { ok: true, data: { categories, treatments, units, devMode: d.devMode === true } };
  } catch {
    return { ok: false, status: 0 };
  }
}

// ───────── Phone check ─────────

export type PhoneCheck = { ok: true; duplicate: boolean; hospitalName?: string } | { ok: false };

export async function checkPhone(phone: string, signal?: AbortSignal): Promise<PhoneCheck> {
  try {
    const res = await fetch(`${BASE_PATH}/api/v1/hospital-apply/phone-check?phone=${encodeURIComponent(phone)}`, { credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" }, signal });
    if (!res.ok) return { ok: false };
    const d = await readJson(res);
    return d.duplicate === true ? { ok: true, duplicate: true, hospitalName: str(d.hospitalName) } : { ok: true, duplicate: false };
  } catch {
    return { ok: false };
  }
}

// ───────── Registration ─────────

const CLOSED_WORDS: Record<Exclude<DayStatus, "normal">, string> = { off: "휴무", construction: "공사중", temporary: "임시휴업" };

/** The opening hours as the server reads them: a range or a closed word per day, the lunch break and the extra closed dates. */
function hoursPayload(hours: Draft["hours"]): Json {
  const out: Json = {};
  for (const day of WEEKDAYS) {
    const d = hours.days[day];
    out[day] = d.status === "normal" ? (d.start && d.end ? `${d.start}~${d.end}` : "휴무") : CLOSED_WORDS[d.status];
  }
  if (hours.noLunch) out.no_lunch_break = true;
  else if (hours.lunchStart && hours.lunchEnd) out["점심"] = `${hours.lunchStart}~${hours.lunchEnd}`;
  out.special_closed_dates = [...hours.closedDates].sort();
  return out;
}

/** A number from a text box; not a number is sent as 0. */
const whole = (v: string): number => {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
};
/** Whole won and whole years: a decimal typed by mistake is rounded instead of refused by the server. */
const rounded = (v: string): number => Math.round(whole(v));

/** The JSON text and the files of one registration. A doctor without a name is not sent (so the file names stay in step with the list sent). */
export function buildApplyBody(draft: Draft): FormData {
  const doctors = draft.doctors.filter((d) => d.name.trim() !== "");
  const payload = {
    name: draft.name.trim(),
    ownerName: draft.ownerName.trim(),
    businessNumber: draft.businessNumber.trim(),
    phone: draft.phone.trim(),
    email: draft.email.trim(),
    contactName: draft.contactName.trim(),
    contactPhone: draft.contactPhone.trim(),
    address: draft.address.trim(),
    lat: draft.lat,
    lng: draft.lng,
    businessHours: hoursPayload(draft.hours),
    introduction: draft.introduction.trim(),
    website: draft.website.trim(),
    snsUrl: draft.snsUrl.trim(),
    treatments: draft.treatments.map((t) => ({
      treatmentId: t.id,
      prices: t.prices.filter((p) => priceState(p) === "complete").map((p) => ({ optionName: p.optionName.trim(), dosageValue: whole(p.dosage), dosageUnit: p.unit.trim(), price: rounded(p.price) })),
    })),
    doctors: doctors.map((d) => ({
      name: d.name.trim(),
      specialty: d.specialty.trim(),
      experienceYears: d.experience.trim() === "" ? 0 : rounded(d.experience),
      qualification: d.qualification.trim(),
      bio: d.bio.trim(),
    })),
  };
  const body = new FormData();
  body.append("payload", JSON.stringify(payload));
  if (draft.certBusiness) body.append("certBusiness", draft.certBusiness);
  if (draft.certMedical) body.append("certMedical", draft.certMedical);
  if (draft.profileImage) body.append("profileImage", draft.profileImage);
  for (const file of draft.interiorImages) body.append("interiorImages", file);
  doctors.forEach((d, i) => {
    if (d.photo) body.append(`doctorPhoto_${i}`, d.photo);
  });
  for (const t of draft.treatments) for (const file of t.images) body.append(`treatmentImages_${t.id}`, file);
  return body;
}

export type SubmitFailure = {
  ok: false;
  /** 0 = no answer at all. */
  status: number;
  /** The server's code: invalid_body, invalid_field, duplicate_phone, too_many, file_too_large, bad_file_type, write_unavailable, uploads_unavailable; "" when none. */
  error: string;
  /** The field (or file field) the server names. */
  field: string;
  hospitalName: string;
};
export type SubmitResult = { ok: true; id: number } | SubmitFailure;

export async function submitApplication(draft: Draft, signal?: AbortSignal): Promise<SubmitResult> {
  try {
    const res = await fetch(`${BASE_PATH}/api/v1/hospital-apply`, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      // No Content-Type: the browser sets it, with the multipart boundary.
      headers: HEADERS,
      body: buildApplyBody(draft),
      signal,
    });
    const d = await readJson(res);
    if (!res.ok) return { ok: false, status: res.status, error: str(d.error), field: str(d.field), hospitalName: str(d.hospitalName) };
    return { ok: true, id: num(d.id) };
  } catch {
    return { ok: false, status: 0, error: "", field: "", hospitalName: "" };
  }
}
