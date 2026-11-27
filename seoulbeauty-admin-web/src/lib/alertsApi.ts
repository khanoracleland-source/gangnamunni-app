// Every request of the alert inbox (CLAUDE.md rule 10: components do not call fetch themselves).
import { getJson, type ApiResult } from "./api";

type Json = Record<string, unknown>;
const obj = (v: unknown): Json => (v !== null && typeof v === "object" && !Array.isArray(v) ? (v as Json) : {});
const num = (v: unknown): number => (typeof v === "number" && Number.isFinite(v) ? v : 0);

/** How many alerts of the signed-in admin are unread (the red dot of the bell). */
export async function fetchAlertsUnread(signal?: AbortSignal): Promise<ApiResult<number>> {
  const res = await getJson("/v1/alerts/unread", signal);
  if (!res.ok) return res;
  return { ok: true, data: Math.max(0, Math.floor(num(obj(res.data).unread))) };
}
