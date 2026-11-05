import type { Doctor } from "./api";

// detail.php: doctor_treatments → hospital's first 5 treatments → default labels
export const DEFAULT_DOCTOR_TAGS = ["보톡스", "필러", "리프팅", "스킨부스터"];
export const DOCTOR_TAG_LIMIT = 6;

/** One rule for the list card and the detail page, so a doctor looks the same in both (design 04-01 / 04-02). */
export function doctorView(d: Doctor, hospitalTreatments: string[]) {
  const specialty = (d.specialty ?? "").split(/\r?\n/)[0].trim() || null;   // one line only; the rest belongs to the detail's credential list
  const tags = (d.treatments.length ? d.treatments : hospitalTreatments.length ? hospitalTreatments : DEFAULT_DOCTOR_TAGS).slice(0, DOCTOR_TAG_LIMIT);
  return { specialty, tags };
}

/** Credential lines for the detail page: qualification / experience / description split on line breaks, deduplicated. */
export function doctorCredentials(d: Doctor): string[] {
  const out: string[] = [];
  for (const p of [d.qualification, d.experience, d.description]) {
    for (const l of (p ?? "").split(/\r?\n|·|•/)) { const t = l.trim(); if (t && !out.includes(t)) out.push(t); }
  }
  return out;
}
