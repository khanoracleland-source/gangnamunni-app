// Pure rules of the "edit prices" window: the body part groups, the units, the draft rows and the checks.
// The checks are the ones the server makes, so a refusal from the server is rare and always says which row and field.

import type { TreatmentPriceOption, TreatmentPriceInput, WriteFailure } from "./api";
import type { MessageKey } from "./i18n";

/** Body parts are stored joined by this separator. */
export const BODY_PART_SEP = "||";

export type BodyPartGroup = { id: string; label: string; parts: readonly string[] };

export const BODY_PART_GROUPS: readonly BodyPartGroup[] = [
  { id: "face", label: "얼굴 전체 및 헤어", parts: ["얼굴 전체 (풀페이스)", "헤어라인", "두피"] },
  { id: "upper", label: "상안면부 (눈/이마 주변)", parts: ["이마", "미간", "눈가 (눈웃음 주름)", "눈밑 (다크서클, 눈밑 꺼짐)", "애교살", "관자놀이"] },
  { id: "mid", label: "중안면부 (코/볼/팔자 주변)", parts: ["광대", "앞광대", "옆광대", "코 (콧대, 코끝)", "콧볼 (콧볼 축소 등)", "팔자주름", "인중", "나비존"] },
  { id: "lower", label: "하안면부 (입술/턱 주변)", parts: ["입술 (입술 볼륨)", "립플립", "입꼬리", "거미스마일", "사각턱 (턱 근육)", "자갈턱", "턱끝 (무턱)", "이중턱 (턱밑 뱃살)", "심부볼 (불독살)", "턱선 (마리오넷 주름, 페이스라인)"] },
  {
    id: "body",
    label: "바디 (체형 및 레이저 제모 등)",
    parts: ["목 (목주름)", "승모근 (어깨 라인)", "쇄골", "겨드랑이", "팔 (팔뚝)", "복부 (윗배, 아랫배)", "러브핸들 (옆구리)", "등", "허벅지 (안쪽, 바깥쪽)", "종아리 (종아리 알)", "손등 / 발등", "손바닥", "발바닥"],
  },
];

const partsOf = (groups: readonly BodyPartGroup[]): string[] => groups.flatMap((g) => g.parts);

/** The groups the server sends (from the body part tables); the built-in list when it sends none. */
export function groupsFromServer(items: readonly { label: string; parts: readonly string[] }[]): readonly BodyPartGroup[] {
  const groups = items.filter((g) => g.parts.length > 0).map((g, i) => ({ id: `g${i}`, label: g.label, parts: g.parts }));
  return groups.length > 0 ? groups : BODY_PART_GROUPS;
}

/** The units offered when the server sends none. */
export const DEFAULT_UNITS: readonly string[] = ["Box", "cc", "IU", "kj", "ml", "기타", "라인", "병", "샷", "시린지", "회"];
/** The unit of a new row. */
export const DEFAULT_UNIT = "회";

export const MAX_OPTIONS = 50;
export const MAX_NAME = 100;
export const MAX_PART = 100;
export const MAX_PARTS_TOTAL = 500;
export const MAX_UNIT = 20;
export const MAX_DOSAGE = 1_000_000;
export const MAX_MONEY = 2_000_000_000;

/** One row of the window as it is typed. Numbers stay text until the check. */
export type PriceDraft = {
  /** Stable key of the row on screen. */
  key: number;
  optionName: string;
  bodyParts: string[];
  dosage: string;
  unit: string;
  regular: string;
  price: string;
  representative: boolean;
};

export type DraftField = "optionName" | "bodyParts" | "dosageValue" | "dosageUnit" | "regularPrice" | "price";

let nextKey = 1;
export const newDraft = (): PriceDraft => ({ key: nextKey++, optionName: "", bodyParts: [], dosage: "", unit: DEFAULT_UNIT, regular: "", price: "", representative: false });

/** Splits a stored body part text into its parts. */
export const splitBodyParts = (bodyPart: string): string[] => bodyPart.split(BODY_PART_SEP).map((p) => p.trim()).filter(Boolean);

/** Puts parts in the order of the groups; names the groups do not know keep their place at the end. */
export function orderBodyParts(parts: readonly string[], groups: readonly BodyPartGroup[] = BODY_PART_GROUPS): string[] {
  const known = [...new Set(partsOf(groups))];
  const seen = new Set(parts);
  return [...known.filter((p) => seen.has(p)), ...parts.filter((p, i) => !known.includes(p) && parts.indexOf(p) === i)];
}

/** The rows a stored treatment starts with; one empty row when it has no price option. */
export function draftsFromOptions(options: readonly TreatmentPriceOption[], groups: readonly BodyPartGroup[] = BODY_PART_GROUPS): PriceDraft[] {
  if (options.length === 0) return [newDraft()];
  return options.map((o) => ({
    key: nextKey++,
    optionName: o.optionName,
    bodyParts: orderBodyParts(splitBodyParts(o.bodyPart), groups),
    dosage: o.dosageValue,
    unit: o.dosageUnit,
    regular: o.regularPrice > 0 ? String(o.regularPrice) : "",
    price: o.price > 0 ? String(o.price) : "",
    representative: o.isRepresentative,
  }));
}

/** The text that says whether anything was changed: the rows without their keys. */
export const draftSignature = (drafts: readonly PriceDraft[]): string =>
  JSON.stringify(drafts.map((d) => [d.optionName, d.bodyParts, d.dosage, d.unit, d.regular, d.price, d.representative]));

/** The units of the unit list: the server's (or the default) ones, plus any unit the rows already hold. */
export function unitChoices(units: readonly string[], drafts: readonly PriceDraft[]): string[] {
  const base = units.length > 0 ? units : DEFAULT_UNITS;
  const out = [...base];
  for (const d of drafts) {
    const unit = d.unit.trim();
    if (unit !== "" && !out.includes(unit)) out.push(unit);
  }
  return out;
}

/** What the body part button says: nothing chosen, the one chosen, or the first and how many more. */
export function bodyPartSummary(parts: readonly string[]): { kind: "none" } | { kind: "one"; first: string } | { kind: "more"; first: string; rest: number } {
  if (parts.length === 0) return { kind: "none" };
  if (parts.length === 1) return { kind: "one", first: parts[0] };
  return { kind: "more", first: parts[0], rest: parts.length - 1 };
}

/** Parts that are saved on a row but that no group lists. */
export const legacyParts = (parts: readonly string[], groups: readonly BodyPartGroup[] = BODY_PART_GROUPS): string[] => {
  const known = partsOf(groups);
  return parts.filter((p) => !known.includes(p));
};

const isBlankText = (v: string): boolean => v.trim() === "";
const moneyValue = (v: string): number => (isBlankText(v) ? 0 : Number(v.trim()));
const isMoneyBlank = (v: string): boolean => isBlankText(v) || /^0+$/.test(v.trim());
const isDosageBlank = (v: string): boolean => isBlankText(v) || /^0*\.?0*$/.test(v.trim());

/** A row nobody touched: nothing typed, and the unit empty or still the new-row unit. It is not sent. */
export const isBlankDraft = (d: PriceDraft): boolean =>
  isBlankText(d.optionName) && d.bodyParts.length === 0 && isDosageBlank(d.dosage) && (isBlankText(d.unit) || d.unit.trim() === DEFAULT_UNIT) && isMoneyBlank(d.regular) && isMoneyBlank(d.price) && !d.representative;

export type PriceErrorCode =
  | "invalid_field"
  | "too_many"
  | "no_options"
  | "price_order"
  | "multiple_representative"
  | "duplicate_option";

/** A refusal: `index` is the row on screen (not the position in the sent list). */
export type PriceError = { code: PriceErrorCode; index: number | null; field: DraftField | null };

export type PriceCheck = { ok: true; options: TreatmentPriceInput[]; rows: number[] } | { ok: false; error: PriceError };

const fail = (code: PriceErrorCode, index: number | null = null, field: DraftField | null = null): PriceCheck => ({ ok: false, error: { code, index, field } });

/**
 * The server's rules, in the server's order. Untouched rows are left out; `rows[i]` is the screen row of `options[i]`,
 * so an answer of the server that names a position can be shown on the right row.
 */
export function checkPrices(drafts: readonly PriceDraft[]): PriceCheck {
  const options: TreatmentPriceInput[] = [];
  const rows: number[] = [];
  for (let i = 0; i < drafts.length; i += 1) {
    const d = drafts[i];
    if (isBlankDraft(d)) continue;
    const dosageText = d.dosage.trim();
    if (!/^(\d+(\.\d{1,2})?|\.\d{1,2})$/.test(dosageText)) return fail("invalid_field", i, "dosageValue");
    const dosageValue = Number(dosageText);
    if (!(dosageValue > 0) || dosageValue > MAX_DOSAGE) return fail("invalid_field", i, "dosageValue");
    const unit = d.unit.trim();
    if (unit === "" || unit.length > MAX_UNIT) return fail("invalid_field", i, "dosageUnit");
    const optionName = d.optionName.trim();
    if (optionName.length > MAX_NAME) return fail("invalid_field", i, "optionName");
    for (const part of d.bodyParts) {
      if (part.trim() === "" || part.length > MAX_PART || part.includes(BODY_PART_SEP)) return fail("invalid_field", i, "bodyParts");
    }
    if (d.bodyParts.join(BODY_PART_SEP).length > MAX_PARTS_TOTAL) return fail("invalid_field", i, "bodyParts");
    for (const [field, text] of [["regularPrice", d.regular], ["price", d.price]] as const) {
      if (!isBlankText(text) && (!/^\d+$/.test(text.trim()) || Number(text.trim()) > MAX_MONEY)) return fail("invalid_field", i, field);
    }
    options.push({ optionName, bodyParts: [...d.bodyParts], dosageValue, dosageUnit: unit, regularPrice: moneyValue(d.regular), price: moneyValue(d.price), isRepresentative: d.representative });
    rows.push(i);
  }
  if (options.length > MAX_OPTIONS) return fail("too_many");
  if (options.length === 0) return fail("no_options");
  for (let i = 0; i < options.length; i += 1) {
    const o = options[i];
    if (o.regularPrice > 0 && o.price > 0 && o.regularPrice < o.price) return fail("price_order", rows[i], "regularPrice");
  }
  const reps = rows.filter((_, i) => options[i].isRepresentative);
  if (reps.length > 1) return fail("multiple_representative", reps[1]);
  const seen = new Set<string>();
  for (let i = 0; i < options.length; i += 1) {
    const o = options[i];
    const key = [o.optionName.toLowerCase(), o.bodyParts.join(BODY_PART_SEP).toLowerCase(), String(o.dosageValue), o.dosageUnit.toLowerCase()].join("|");
    if (seen.has(key)) return fail("duplicate_option", rows[i]);
    seen.add(key);
  }
  return { ok: true, options, rows };
}

/** The message of a refusal (from the check or from the server). */
export function priceErrorKey(code: PriceErrorCode, field: DraftField | null): MessageKey {
  switch (code) {
    case "invalid_field":
      if (field === "dosageValue") return "h.e.tp.err.dosage";
      if (field === "dosageUnit") return "h.e.tp.err.unit";
      if (field === "optionName") return "h.e.tp.err.name";
      if (field === "bodyParts") return "h.e.tp.err.parts";
      return "h.e.tp.err.money";
    case "too_many":
      return "h.e.tp.err.tooMany";
    case "no_options":
      return "h.e.tp.err.none";
    case "price_order":
      return "h.e.tp.err.order";
    case "multiple_representative":
      return "h.e.tp.err.rep";
    case "duplicate_option":
      return "h.e.tp.err.dup";
  }
}

const CODES: readonly string[] = ["invalid_field", "too_many", "no_options", "price_order", "multiple_representative", "duplicate_option"];
const FIELDS: readonly string[] = ["optionName", "bodyParts", "dosageValue", "dosageUnit", "regularPrice", "price"];

/**
 * What a refused save says. A named row is mapped back to the screen row through `rows`. `refresh`: the list on screen
 * is probably out of date, so the card reads it again.
 */
export function priceSaveFailure(f: WriteFailure, rows: readonly number[]): { key: MessageKey; row: PriceError | null; refresh: boolean } {
  if (f.status === 0) return { key: "h.e.tr.err.network", row: null, refresh: false };
  if (f.status === 400 && CODES.includes(f.error)) {
    const code = f.error as PriceErrorCode;
    const field = f.field && FIELDS.includes(f.field) ? (f.field as DraftField) : null;
    const index = typeof f.index === "number" && f.index >= 0 && f.index < rows.length ? rows[f.index] : null;
    return { key: priceErrorKey(code, field), row: { code, index, field }, refresh: false };
  }
  if (f.status === 403) return { key: "h.e.tr.err.forbidden", refresh: false, row: null };
  if (f.status === 404) return { key: "h.e.tp.err.gone", refresh: true, row: null };
  if (f.status === 503) return { key: f.error === "price_columns_unavailable" ? "h.e.tp.err.columns" : "h.e.tr.err.unavailable", refresh: false, row: null };
  return { key: "h.e.tp.err.server", refresh: false, row: null };
}

/** True when the treatment shows the "edit prices" button. */
export const canEditPrices = (set: { canManage?: boolean; pricesEnabled?: boolean }): boolean => set.canManage === true && set.pricesEnabled === true;
