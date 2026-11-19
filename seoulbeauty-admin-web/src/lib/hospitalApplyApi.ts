// The two calls the registration page makes. Until the server side exists they answer from the browser
// (a pretend duplicate check and a pretend save), so the page can be tried out; each function is the one place
// to replace with a real request.

import type { Draft } from "./hospitalApply";

/** True while the page is only a layout preview: it tells the admin that nothing is stored. */
export const LAYOUT_PREVIEW = true;

export type PhoneCheck = { ok: true; duplicate: boolean; hospitalName?: string } | { ok: false };

const wait = (ms: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms));

/** The digits of a phone number, so "02-555-1234" and "025551234" are the same number. */
const digits = (value: string): string => value.replace(/\D/g, "");

export async function checkPhone(phone: string): Promise<PhoneCheck> {
  await wait(450);
  // Preview: one number counts as taken, to show the duplicate state.
  if (digits(phone) === "025551234") return { ok: true, duplicate: true, hospitalName: "서울 라온의원" };
  return { ok: true, duplicate: false };
}

export type SubmitResult = { ok: true; id: number } | { ok: false; reason: "server" };

export async function submitApplication(draft: Draft): Promise<SubmitResult> {
  void draft;
  await wait(900);
  return { ok: true, id: 0 };
}
