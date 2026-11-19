// Pure helpers of the hospital registration page: the draft the admin fills in, the opening-hours model,
// the checks before sending and the counters shown on the cards. No DOM, no network.

import { WEEKDAYS, type Weekday } from "./hospitals";
import type { MessageKey, Vars } from "./i18n";

// ───────── Sections ─────────

export const SECTION_IDS = ["basic", "info", "treat", "img", "doc", "file"] as const;
export type SectionId = (typeof SECTION_IDS)[number];

export const SECTION_TITLE_KEYS: Record<SectionId, MessageKey> = {
  basic: "hap.s.basic",
  info: "hap.s.info",
  treat: "hap.s.treat",
  img: "hap.s.img",
  doc: "hap.s.doc",
  file: "hap.s.file",
};

// ───────── Opening hours ─────────

export type DayStatus = "normal" | "off" | "construction" | "temporary";
export const DAY_STATUSES: readonly DayStatus[] = ["normal", "off", "construction", "temporary"];
export const DAY_STATUS_KEYS: Record<DayStatus, MessageKey> = {
  normal: "h.hours.open",
  off: "h.hours.closed",
  construction: "h.hours.construction",
  temporary: "h.hours.temporary",
};

export type DayDraft = { status: DayStatus; start: string; end: string };

export type HoursDraft = {
  days: Record<Weekday, DayDraft>;
  noLunch: boolean;
  lunchStart: string;
  lunchEnd: string;
  closedDates: string[];
};

/** Weekdays 10:00~19:00, Saturday 10:00~15:00, Sunday closed; lunch 13:00~14:00 (the old form's starting values). */
export function defaultHours(): HoursDraft {
  const day = (start: string, end: string): DayDraft => ({ status: "normal", start, end });
  return {
    days: {
      "월": day("10:00", "19:00"),
      "화": day("10:00", "19:00"),
      "수": day("10:00", "19:00"),
      "목": day("10:00", "19:00"),
      "금": day("10:00", "19:00"),
      "토": day("10:00", "15:00"),
      "일": { status: "off", start: "", end: "" },
    },
    noLunch: false,
    lunchStart: "13:00",
    lunchEnd: "14:00",
    closedDates: [],
  };
}

export const hoursChanged = (hours: HoursDraft): boolean => JSON.stringify(hours) !== JSON.stringify(defaultHours());

/** The "Apply" button of the bulk setter exists only when the times make sense and a day is chosen. */
export const canApplyBulk = (start: string, end: string, days: ReadonlySet<Weekday>): boolean => start !== "" && end !== "" && start < end && days.size > 0;

/** "월~금" for three or more days in a row, otherwise the days joined with a middle dot. */
export function daysLabel(days: readonly Weekday[]): string {
  const sorted = [...days].sort((a, b) => WEEKDAYS.indexOf(a) - WEEKDAYS.indexOf(b));
  if (sorted.length >= 3) {
    const first = WEEKDAYS.indexOf(sorted[0]);
    if (sorted.every((d, i) => WEEKDAYS.indexOf(d) === first + i)) return `${sorted[0]}~${sorted[sorted.length - 1]}`;
  }
  return sorted.join("·");
}

// ───────── Files ─────────

export type FileRule = { types: readonly string[]; exts: readonly string[]; maxMb: number; label: string };

export const FILE_RULES = {
  main: { types: ["image/jpeg", "image/png"], exts: ["jpg", "jpeg", "png"], maxMb: 5, label: "JPG/PNG" },
  inner: { types: ["image/jpeg", "image/png", "image/webp"], exts: ["jpg", "jpeg", "png", "webp"], maxMb: 10, label: "JPG/PNG/WebP" },
  doc: { types: ["image/jpeg", "image/png", "application/pdf"], exts: ["jpg", "jpeg", "png", "pdf"], maxMb: 5, label: "JPG/PNG/PDF" },
  doctor: { types: ["image/jpeg", "image/png", "image/webp"], exts: ["jpg", "jpeg", "png", "webp"], maxMb: 5, label: "JPG/PNG/WebP" },
  treatment: { types: ["image/jpeg", "image/png", "image/gif", "image/webp"], exts: ["jpg", "jpeg", "png", "gif", "webp"], maxMb: 10, label: "JPG/PNG/GIF/WebP" },
} as const satisfies Record<string, FileRule>;

export const INTERIOR_MAX = 10;

export type FileProblem = "type" | "size";

/** The browser's own view of the file: the type it reports and the extension in the name must both be allowed. */
export function checkFile(file: File, rule: FileRule): FileProblem | null {
  const ext = file.name.includes(".") ? file.name.split(".").pop()!.toLowerCase() : "";
  if (!rule.types.includes(file.type) || !rule.exts.includes(ext)) return "type";
  if (file.size > rule.maxMb * 1024 * 1024) return "size";
  return null;
}

export const acceptOf = (rule: FileRule): string => rule.exts.map((e) => `.${e}`).join(",");

// ───────── Draft ─────────

let keySeq = 0;
export const newKey = (): string => `r${++keySeq}`;

export type PriceRow = { key: string; optionName: string; dosage: string; unit: string; price: string };
export type TreatmentDraft = { id: number; prices: PriceRow[]; images: File[] };
export type DoctorDraft = { key: string; name: string; specialty: string; experience: string; qualification: string; bio: string; photo: File | null };

export type Draft = {
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
  hours: HoursDraft;
  introduction: string;
  website: string;
  snsUrl: string;
  treatments: TreatmentDraft[];
  profileImage: File | null;
  interiorImages: File[];
  doctors: DoctorDraft[];
  certBusiness: File | null;
  certMedical: File | null;
};

export const emptyPrice = (): PriceRow => ({ key: newKey(), optionName: "", dosage: "", unit: "회", price: "" });
export const emptyDoctor = (): DoctorDraft => ({ key: newKey(), name: "", specialty: "", experience: "", qualification: "", bio: "", photo: null });

export function emptyDraft(): Draft {
  return {
    name: "", ownerName: "", businessNumber: "", phone: "", email: "", contactName: "", contactPhone: "",
    address: "", lat: null, lng: null, hours: defaultHours(), introduction: "", website: "", snsUrl: "",
    treatments: [], profileImage: null, interiorImages: [], doctors: [], certBusiness: null, certMedical: null,
  };
}

/** 000-00-00000 while typing: digits only, at most ten. */
export function formatBusinessNumber(raw: string): string {
  const v = raw.replace(/\D/g, "").slice(0, 10);
  if (v.length > 5) return `${v.slice(0, 3)}-${v.slice(3, 5)}-${v.slice(5)}`;
  if (v.length > 3) return `${v.slice(0, 3)}-${v.slice(3)}`;
  return v;
}

// ───────── Price rows ─────────

/** empty: nothing typed (ignored quietly); complete: saved; partial: typed but missing a part, so it is not saved. */
export type PriceState = "empty" | "complete" | "partial";

export function priceState(row: PriceRow): PriceState {
  const dosage = Number(row.dosage);
  const price = Number(row.price);
  const complete = dosage > 0 && row.unit.trim() !== "" && price > 0;
  if (complete) return "complete";
  return row.optionName.trim() === "" && row.dosage.trim() === "" && row.price.trim() === "" ? "empty" : "partial";
}

// ───────── Checks ─────────

export type FieldError = {
  section: SectionId;
  /** The element to focus: the field itself or the button that opens the file chooser. */
  focusId: string;
  message: { key: MessageKey; vars?: Vars };
  /** Short name of the field, used in the summary toast. */
  label: MessageKey;
};

export type Errors = Record<string, FieldError>;

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const URL_START = /^https?:\/\/\S+$/i;

/** What the old form checked on the server: the name, an email that is filled, a website that is filled. */
export function validateDraft(draft: Draft): Errors {
  const errors: Errors = {};
  if (draft.name.trim() === "") errors.name = { section: "basic", focusId: "f-name", message: { key: "hap.err.name" }, label: "hap.fn.name" };
  if (draft.email.trim() !== "" && !EMAIL.test(draft.email.trim())) errors.email = { section: "basic", focusId: "f-email", message: { key: "hap.err.email" }, label: "hap.fn.email" };
  if (draft.website.trim() !== "" && !URL_START.test(draft.website.trim())) errors.website = { section: "info", focusId: "f-website", message: { key: "hap.err.url" }, label: "hap.fn.website" };
  return errors;
}

/** Errors of files that were refused when chosen are kept apart from the form checks: a new check must not wipe them. */
export const isFileErrorKey = (key: string): boolean => key.startsWith("file:");

export function firstErrorKey(errors: Errors): string | null {
  let best: string | null = null;
  let bestRank = Infinity;
  for (const key of Object.keys(errors)) {
    const rank = SECTION_IDS.indexOf(errors[key].section);
    if (rank < bestRank) {
      best = key;
      bestRank = rank;
    }
  }
  return best;
}

export function errorSections(errors: Errors): SectionId[] {
  const set = new Set(Object.values(errors).map((e) => e.section));
  return SECTION_IDS.filter((id) => set.has(id));
}

// ───────── What is filled ─────────

export const namedDoctors = (draft: Draft): DoctorDraft[] => draft.doctors.filter((d) => d.name.trim() !== "");
export const savedPriceCount = (draft: Draft): number => draft.treatments.reduce((n, t) => n + t.prices.filter((p) => priceState(p) === "complete").length, 0);

export function sectionFilled(id: SectionId, draft: Draft): boolean {
  switch (id) {
    case "basic":
      return [draft.name, draft.ownerName, draft.businessNumber, draft.phone, draft.email, draft.contactName, draft.contactPhone].some((v) => v.trim() !== "");
    case "info":
      return [draft.address, draft.introduction, draft.website, draft.snsUrl].some((v) => v.trim() !== "") || draft.lat !== null || hoursChanged(draft.hours);
    case "treat":
      return draft.treatments.length > 0;
    case "img":
      return draft.profileImage !== null || draft.interiorImages.length > 0;
    case "doc":
      return draft.doctors.some((d) => d.name.trim() !== "");
    case "file":
      return draft.certBusiness !== null || draft.certMedical !== null;
  }
}

export const isDirty = (draft: Draft): boolean =>
  SECTION_IDS.some((id) => sectionFilled(id, draft)) || draft.doctors.some((d) => d.specialty !== "" || d.bio !== "" || d.qualification !== "" || d.experience !== "" || d.photo !== null);

export type ApplyCounts = { doctors: number; treatments: number; prices: number; images: number };

/** What would be stored, for the confirmation shown after saving. */
export function applyCounts(draft: Draft): ApplyCounts {
  return {
    doctors: namedDoctors(draft).length,
    treatments: draft.treatments.length,
    prices: savedPriceCount(draft),
    images: (draft.profileImage ? 1 : 0) + draft.interiorImages.length,
  };
}

// ───────── Preview data ─────────
// Stand-ins for the lists the server will provide (treatment master and price units) while the page is not connected.

export type CategoryOption = { id: number; name: string };
export type TreatmentOption = { id: number; name: string; categoryId: number };

export const PREVIEW_CATEGORIES: CategoryOption[] = [
  { id: 1, name: "눈" },
  { id: 2, name: "코" },
  { id: 3, name: "리프팅" },
  { id: 4, name: "주름·보톡스" },
  { id: 5, name: "필러" },
  { id: 6, name: "피부" },
  { id: 7, name: "체형" },
];

const T = (id: number, categoryId: number, name: string): TreatmentOption => ({ id, name, categoryId });
export const PREVIEW_TREATMENTS: TreatmentOption[] = [
  T(101, 1, "쌍꺼풀 수술"), T(102, 1, "눈매교정"), T(103, 1, "눈밑지방 재배치"), T(104, 1, "앞트임·뒤트임"),
  T(201, 2, "코 성형"), T(202, 2, "코끝 성형"), T(203, 2, "비중격 연골"),
  T(301, 3, "울쎄라 리프팅"), T(302, 3, "써마지"), T(303, 3, "실 리프팅"), T(304, 3, "인모드"),
  T(401, 4, "보톡스 주름"), T(402, 4, "사각턱 보톡스"), T(403, 4, "승모근 보톡스"),
  T(501, 5, "입술 필러"), T(502, 5, "이마 필러"), T(503, 5, "턱끝 필러"),
  T(601, 6, "여드름 흉터 치료"), T(602, 6, "피부 재생 레이저"), T(603, 6, "기미·잡티 토닝"), T(604, 6, "물광 주사"),
  T(701, 7, "지방흡입"), T(702, 7, "체형 교정 주사"), T(703, 7, "하체 라인 관리"),
];

export const PREVIEW_UNITS: readonly string[] = ["회", "샷", "cc", "ml", "유닛", "개", "mg", "세션"];

// ───────── Chosen files ─────────

export type FileContext = { key: string; section: SectionId; focusId: string; label: MessageKey };

/** Splits the chosen files into the usable ones and, for the first refused one, the error to show. */
export function sortFiles(files: readonly File[], rule: FileRule, ctx: FileContext): { accepted: File[]; error: FieldError | null } {
  const accepted: File[] = [];
  let error: FieldError | null = null;
  for (const file of files) {
    const problem = checkFile(file, rule);
    if (!problem) {
      accepted.push(file);
      continue;
    }
    if (!error) {
      error = {
        section: ctx.section,
        focusId: ctx.focusId,
        label: ctx.label,
        message: problem === "type" ? { key: "hap.err.fileType", vars: { name: file.name, types: rule.label } } : { key: "hap.err.fileSize", vars: { name: file.name, max: rule.maxMb } },
      };
    }
  }
  return { accepted, error };
}
