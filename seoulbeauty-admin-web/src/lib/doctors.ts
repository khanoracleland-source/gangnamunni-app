// Pure rules of the doctors page: the list address, the photo address, the form checks and the sentences for refused changes. No DOM.

import type { DoctorCard, DoctorEditFailure, DoctorLevel, DoctorListQuery, DoctorStatusFilter, DoctorTreatment, DoctorTreatmentInput, WriteFailure } from "./api";
import { DOCTOR_PER_PAGE } from "./api";
import { PHP_BASE_URL } from "./config";
import { mediaUrl } from "./hospitals";
import type { MessageKey } from "./i18n";

export const DOCTOR_SEARCH_MAX = 100;
export const DOCTOR_NAME_MAX = 100;
export const DOCTOR_SPECIALTY_MAX = 100;
export const DOCTOR_QUALIFICATION_MAX = 2000;
export const DOCTOR_CERTIFICATION_MAX = 500;
export const DOCTOR_CASES_MAX = 2_147_483_647;
export const DOCTOR_PHOTO_MAX_BYTES = 5 * 1024 * 1024;
export const DOCTOR_PHOTO_ACCEPT = ".jpg,.jpeg,.png,.webp";
/** The choices of the specialty box; a value stored earlier that is not among them stays selectable. */
export const SPECIALTY_CHOICES = ["피부과 전문의", "성형외과 전문의", "마취통증의학과 전문의", "가정의학과 전문의", "일반의"] as const;

const positive = (v: string | null): number => (v !== null && /^[1-9]\d{0,9}$/.test(v) ? Number(v) : 0);

/** The list state from the address; anything missing or wrong becomes the default. */
export function parseDoctorQuery(params: { get(name: string): string | null }): DoctorListQuery {
  const status = params.get("status");
  return {
    hospitalId: positive(params.get("hospitalId")),
    search: (params.get("search") ?? "").trim().slice(0, DOCTOR_SEARCH_MAX),
    status: status === "active" || status === "inactive" || status === "resigned" ? status : "all",
    page: Math.min(100000, positive(params.get("page")) || 1),
    perPage: DOCTOR_PER_PAGE,
  };
}

/** The page address for a list state (defaults are left out). */
export function doctorsHref(q: DoctorListQuery): string {
  const params = new URLSearchParams();
  if (q.hospitalId > 0) params.set("hospitalId", String(q.hospitalId));
  if (q.search !== "") params.set("search", q.search);
  if (q.status !== "all") params.set("status", q.status);
  if (q.page > 1) params.set("page", String(q.page));
  const s = params.toString();
  return s === "" ? "/doctors" : `/doctors?${s}`;
}

export const hasDoctorFilters = (q: DoctorListQuery): boolean => q.hospitalId > 0 || q.search !== "" || q.status !== "all";

/** An https address is shown only when it is on the panel's own media host. */
function sameHost(value: string): boolean {
  try {
    return new URL(value).host === new URL(PHP_BASE_URL, "https://invalid.example").host;
  } catch {
    return false;
  }
}

/** Only the folders the panel itself writes to (and https addresses) are shown as a picture; anything else gives "" and the initials are drawn. */
export function doctorPhotoUrl(stored: string | null): string {
  const value = (stored ?? "").trim();
  if (value === "") return "";
  if (/^https:\/\//i.test(value)) return sameHost(value) ? value : "";
  if (/^uploads\/(hospitals\/\d+|doctors)\/[\w.-]+$/.test(value)) return mediaUrl(value);
  return "";
}

/** The first character shown when there is no picture. */
export const doctorInitial = (name: string): string => Array.from(name.trim())[0] ?? "?";

const chars = (v: string): number => Array.from(v).length;
const CONTROL = /[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/;

export type DoctorFormValues = { name: string; hospitalId: number; specialty: string; qualification: string };
export type DoctorFormErrors = Partial<Record<"name" | "hospitalId" | "specialty" | "qualification" | "photo" | "treatments", MessageKey>>;

/** The checks the server makes, so a wrong form is stopped before it is sent. `currentSpecialty` is the value stored now. */
export function validateDoctorForm(v: DoctorFormValues, currentSpecialty: string, photo: { name: string; type: string; size: number } | null): DoctorFormErrors {
  const e: DoctorFormErrors = {};
  const name = v.name.trim();
  if (name === "") e.name = "d.e.nameRequired";
  else if (chars(name) > DOCTOR_NAME_MAX || CONTROL.test(name)) e.name = "d.e.nameLong";
  if (v.hospitalId <= 0) e.hospitalId = "d.e.hospital";
  const specialty = v.specialty.trim();
  if (chars(specialty) > DOCTOR_SPECIALTY_MAX || (specialty !== currentSpecialty && !(SPECIALTY_CHOICES as readonly string[]).includes(specialty))) e.specialty = "d.e.specialty";
  const q = v.qualification.trim();
  if (chars(q) > DOCTOR_QUALIFICATION_MAX || CONTROL.test(q)) e.qualification = "d.e.qualification";
  if (photo) {
    const ext = photo.name.slice(photo.name.lastIndexOf(".") + 1).toLowerCase();
    if (!["jpg", "jpeg", "png", "webp"].includes(ext) || (photo.type !== "" && !["image/jpeg", "image/png", "image/webp"].includes(photo.type.toLowerCase())) || photo.size <= 0) e.photo = "d.e.photoType";
    else if (photo.size > DOCTOR_PHOTO_MAX_BYTES) e.photo = "d.e.photoLarge";
  }
  return e;
}

/** What a refused save says: a sentence for the whole form and, when the server names one, the field it belongs to. */
export function editFailure(f: DoctorEditFailure): { key: MessageKey; field: "name" | "hospitalId" | "specialty" | "qualification" | "photo" | "treatments" | null } {
  switch (f.error) {
    case "name_required": return { key: "d.e.nameRequired", field: "name" };
    case "hospital_required": return { key: "d.e.hospital", field: "hospitalId" };
    case "invalid_type": return { key: "d.e.photoType", field: "photo" };
    case "too_large": return { key: "d.e.photoLarge", field: "photo" };
    case "doctor_resigned": return { key: "d.e.resigned", field: null };
    case "invalid_level":
    case "invalid_case_count":
    case "invalid_certification": return { key: "d.ex.fixFields", field: "treatments" };
    case "invalid_field": {
      const field = f.field === "name" || f.field === "hospitalId" || f.field === "specialty" || f.field === "qualification" || f.field === "photo" || f.field === "treatments" ? f.field : null;
      const key: MessageKey = field === "name" ? "d.e.nameLong" : field === "hospitalId" ? "d.e.hospital" : field === "specialty" ? "d.e.specialty" : field === "qualification" ? "d.e.qualification" : field === "photo" ? "d.e.photoType" : field === "treatments" ? "d.ex.fixFields" : "d.e.invalid";
      return { key, field };
    }
    default:
      if (f.status === 0) return { key: "d.e.network", field: null };
      if (f.status === 401 || f.status === 403) return { key: "d.e.forbidden", field: null };
      if (f.status === 404) return { key: "d.e.gone", field: null };
      if (f.status === 503) return { key: "d.e.unavailable", field: null };
      return { key: "d.e.server", field: null };
  }
}

/** The sentence for a refused retire, move or delete. */
export function actionFailureKey(f: WriteFailure): MessageKey {
  if (f.status === 0) return "d.e.network";
  if (f.status === 401 || (f.status === 403 && f.error !== "csrf")) return "d.e.forbidden";
  if (f.status === 404) return "d.e.gone";
  if (f.status === 409 && f.error === "not_resigned") return "d.e.notResigned";
  if (f.status === 409 && f.error === "has_references") return "d.e.hasRefs";
  if (f.status === 409 && f.error === "doctor_resigned") return "d.e.resigned";
  if (f.status === 409 && f.error === "not_active") return "d.e.notActive";
  if (f.status === 409 && f.error === "order_unavailable") return "d.e.orderUnavailable";
  if (f.status === 409) return "d.e.busy";
  if (f.status === 503) return "d.e.unavailable";
  return "d.e.server";
}

export const isActiveDoctor = (d: Pick<DoctorCard, "status">): boolean => d.status === "active";
export const statusLabelKey = (s: DoctorStatusFilter): MessageKey => (s === "active" ? "d.status.active" : s === "inactive" ? "d.status.inactive" : s === "resigned" ? "d.status.resigned" : "d.status.all");

// ---- Level, cases and certification of the assigned treatments ----

/** The levels, the most skilled first (the order of the table and of the list card). */
export const DOCTOR_LEVELS: readonly DoctorLevel[] = ["expert", "advanced", "basic"];
const RANK: Record<DoctorLevel, number> = { expert: 0, advanced: 1, basic: 2 };
export const levelLabelKey = (l: DoctorLevel): MessageKey => (l === "expert" ? "d.ex.lv.expert" : l === "advanced" ? "d.ex.lv.advanced" : "d.ex.lv.basic");

/** One row of the assigned table while it is edited; `cases` is the text of the box so a half-typed value is not lost. */
export type TreatmentRow = { id: number; name: string; category: string; level: DoctorLevel; cases: string; certification: string };
export type TreatmentRowErrors = { cases?: MessageKey; certification?: MessageKey };

export const rowFromTreatment = (t: DoctorTreatment): TreatmentRow => ({ id: t.id, name: t.name, category: t.category, level: t.level, cases: String(t.caseCount), certification: t.certification });
/** A treatment that has just been ticked starts like the server starts it: basic, no cases, no certification. */
export const newRow = (o: { id: number; name: string; category: string }): TreatmentRow => ({ id: o.id, name: o.name, category: o.category, level: "basic", cases: "0", certification: "" });

/** The most skilled first, then by category and name as the server lists them; a row keeps its place while it is edited. */
export function sortRows(rows: TreatmentRow[], order: readonly number[]): TreatmentRow[] {
  const place = new Map(order.map((id, i) => [id, i]));
  return [...rows].sort((a, b) => (place.get(a.id) ?? 1e9) - (place.get(b.id) ?? 1e9));
}
/** The order the table opens in: level first, then the stored order (category, name). */
export function openingOrder(items: readonly DoctorTreatment[]): number[] {
  return items.map((t, i) => ({ id: t.id, i, r: RANK[t.level] })).sort((a, b) => a.r - b.r || a.i - b.i).map((x) => x.id);
}

const caseCount = (text: string): number | null => (/^\d{1,10}$/.test(text.trim()) && Number(text.trim()) <= DOCTOR_CASES_MAX ? Number(text.trim()) : null);

/** The checks the server makes on each row, keyed by treatment id. */
export function validateRows(rows: readonly TreatmentRow[]): Record<number, TreatmentRowErrors> {
  const out: Record<number, TreatmentRowErrors> = {};
  for (const r of rows) {
    const e: TreatmentRowErrors = {};
    if (caseCount(r.cases) === null) e.cases = "d.ex.casesErr";
    const cert = r.certification.trim();
    if (chars(cert) > DOCTOR_CERTIFICATION_MAX || CONTROL.test(cert)) e.certification = "d.ex.certErr";
    if (e.cases || e.certification) out[r.id] = e;
  }
  return out;
}

/** The rows as the server wants them (call only when `validateRows` found nothing). */
export function rowsToInput(rows: readonly TreatmentRow[]): DoctorTreatmentInput[] {
  return rows.map((r) => ({ id: r.id, level: r.level, caseCount: caseCount(r.cases) ?? 0, certification: r.certification.trim() }));
}

/** Has any level, case count or certification changed against what was loaded? (Which rows are there is checked apart.) */
export function rowsChanged(rows: readonly TreatmentRow[], loaded: readonly DoctorTreatment[]): boolean {
  const before = new Map(loaded.map((t) => [t.id, t]));
  return rows.some((r) => {
    const t = before.get(r.id);
    return t !== undefined && (r.level !== t.level || r.cases.trim() !== String(t.caseCount) || r.certification.trim() !== t.certification.trim());
  });
}

/** The list card shows the levels as "Expert 3 · Advanced 5 · Basic 5": only the levels that have any, most skilled first. */
export const levelSummary = (c: Record<DoctorLevel, number>): { level: DoctorLevel; n: number }[] => DOCTOR_LEVELS.map((level) => ({ level, n: c[level] })).filter((x) => x.n > 0);
