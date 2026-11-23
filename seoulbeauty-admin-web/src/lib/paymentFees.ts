// Pure helpers of the fee / section screens. No DOM.

import type { PayMethod } from "./api";

/** 143 -> "1.43", 200 -> "2", 150 -> "1.5". */
export function bpToPercent(bp: number): string {
  const s = (bp / 100).toFixed(2);
  return s.replace(/\.?0+$/, "");
}

/** "1.43" -> 143. Plain percent with at most two decimals between 0 and 100; anything else is null. */
export function percentToBp(text: string): number | null {
  const m = /^(\d{1,3})(?:\.(\d{1,2}))?$/.exec(text.trim());
  if (!m) return null;
  const bp = Number(m[1]) * 100 + (m[2] ? Number(m[2].padEnd(2, "0")) : 0);
  return bp <= 10000 ? bp : null;
}

/** The dictionary key of a payment method's name; an unrecorded method reads as unknown. */
export const methodKey = (m: PayMethod): "pay.method.card" | "pay.method.bank_transfer" | "pay.method.unknown" => (m === "card" ? "pay.method.card" : m === "bank_transfer" ? "pay.method.bank_transfer" : "pay.method.unknown");

/** "2026-11-23" plus n days (calendar days, no time zone). */
export function addDays(iso: string, n: number): string {
  const [y, m, d] = iso.split("-").map(Number);
  const t = new Date(Date.UTC(y, m - 1, d + n));
  return `${t.getUTCFullYear()}-${String(t.getUTCMonth() + 1).padStart(2, "0")}-${String(t.getUTCDate()).padStart(2, "0")}`;
}
