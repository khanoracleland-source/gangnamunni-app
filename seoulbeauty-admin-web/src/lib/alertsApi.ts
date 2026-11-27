// Every request of the alert inbox (CLAUDE.md rule 10: components do not call fetch themselves).
import { getJson, sendJson, type ApiResult, type WriteResult } from "./api";
import type { AlertTab } from "./alerts";

type Json = Record<string, unknown>;
const obj = (v: unknown): Json => (v !== null && typeof v === "object" && !Array.isArray(v) ? (v as Json) : {});
const num = (v: unknown): number => (typeof v === "number" && Number.isFinite(v) ? v : 0);
const str = (v: unknown): string => (typeof v === "string" ? v : "");
const list = (v: unknown): unknown[] => (Array.isArray(v) ? v : []);

/** How many alerts of the signed-in admin are unread (the red dot of the bell). */
export async function fetchAlertsUnread(signal?: AbortSignal): Promise<ApiResult<number>> {
  const res = await getJson("/v1/alerts/unread", signal);
  if (!res.ok) return res;
  return { ok: true, data: Math.max(0, Math.floor(num(obj(res.data).unread))) };
}

export type AlertItem = { id: number; type: string; title: string; message: string; link: string; read: boolean; createdAt: string };
export type AlertCounts = { all: number; activity: number; pending: number; system: number; unread: number; read: number; total: number };
export type AlertsList = { page: number; perPage: number; total: number; pages: number; items: AlertItem[]; counts: AlertCounts };
export type AlertQuery = { tab: AlertTab; type: string; from: string; to: string; page: number };

function item(v: unknown): AlertItem {
  const o = obj(v);
  return { id: num(o.id), type: str(o.type), title: str(o.title), message: str(o.message), link: str(o.link), read: o.read === true, createdAt: str(o.createdAt) };
}

/** One page of the signed-in admin's own alerts, with the unread numbers of the tabs. */
export async function fetchAlerts(q: AlertQuery, signal?: AbortSignal): Promise<ApiResult<AlertsList>> {
  const params = new URLSearchParams({ tab: q.tab, page: String(q.page) });
  if (q.type) params.set("type", q.type);
  if (q.from) params.set("from", q.from);
  if (q.to) params.set("to", q.to);
  const res = await getJson(`/v1/alerts?${params.toString()}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const c = obj(d.counts);
  return {
    ok: true,
    data: {
      page: num(d.page) || 1, perPage: num(d.perPage) || 20, total: num(d.total), pages: Math.max(1, num(d.pages)),
      items: list(d.items).map(item),
      counts: { all: num(c.all), activity: num(c.activity), pending: num(c.pending), system: num(c.system), unread: num(c.unread), read: num(c.read), total: num(c.total) },
    },
  };
}

/** Marks one alert of the signed-in admin as read. */
export function markAlertRead(id: number): Promise<WriteResult<Record<string, unknown>>> {
  return sendJson("POST", `/v1/alerts/${id}/read`);
}

/** Marks every unread alert of the signed-in admin in a tab (or all of them) as read; the answer says how many changed. */
export async function markAlertsTabRead(tab: AlertTab): Promise<WriteResult<{ updated: number }>> {
  const res = await sendJson("POST", "/v1/alerts/read-tab", { tab });
  return res.ok ? { ok: true, data: { updated: num(obj(res.data).updated) } } : res;
}

/** Deletes the signed-in admin's alerts that are read and older than the given number of days (1..3650). */
export async function purgeAlerts(days: number): Promise<WriteResult<{ deleted: number }>> {
  const res = await sendJson("POST", "/v1/alerts/purge", { days });
  return res.ok ? { ok: true, data: { deleted: num(obj(res.data).deleted) } } : res;
}
