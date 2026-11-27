// Every request of the notifications screen (CLAUDE.md rule 10: the screens do not call fetch themselves).
import { getJson, sendJson, type ApiResult, type WriteResult } from "./api";
import type { NotificationType, Target } from "./notifications";

type Json = Record<string, unknown>;
const obj = (v: unknown): Json => (v !== null && typeof v === "object" && !Array.isArray(v) ? (v as Json) : {});
const num = (v: unknown): number => (typeof v === "number" && Number.isFinite(v) ? v : 0);
const str = (v: unknown): string => (typeof v === "string" ? v : "");
const list = (v: unknown): unknown[] => (Array.isArray(v) ? v : []);
const bool = (v: unknown): boolean => v === true;

export type MemberHit = { id: string; name: string; nick: string; phoneTail: string; hasPush: boolean };
export type LinkTarget = { kind: "page" | "hospital" | "event"; path: string; label: string };
export type NotificationOptions = {
  types: { value: NotificationType; gate: "toggle" | "marketing" | "none" }[];
  events: { id: number; title: string }[];
  links: LinkTarget[];
  night: boolean;
  nightWindow: { start: number; end: number };
  fcmConfigured: boolean;
  sendEnabled: boolean;
  scheduleEnabled: boolean;
};
export type Audience = { target: Target; type: NotificationType; eventId?: number; memberId?: string; memberIds?: string[] };
export type Preview = {
  resolved: number; inApp: number; pushMembers: number; pushWithTokens: number; pushTokens: number;
  skippedInApp: number; skippedPush: number; nightSkipped: number; marketing: boolean; night: boolean; topic: boolean; settingsAvailable: boolean;
};
export type HistoryItem = {
  id: number; adminId: number; adminUsername: string; target: string; type: string; title: string; message: string; link: string;
  resolved: number; inApp: number; pushSent: number; pushFailed: number; pushSkipped: number; pushError: string; isTest: boolean; requestId: string | null; createdAt: string;
};
export type HistoryList = { available: boolean; total: number; page: number; perPage: number; items: HistoryItem[] };
export type RecipientRow = { memberId: string; channel: string; platform: string; status: string; errorCode: string; at: string };
export type HistoryDetail = HistoryItem & {
  details: unknown;
  itemsAvailable: boolean;
  items: { total: number; page: number; perPage: number; status: string; rows: RecipientRow[] };
};
export type SendInput = Audience & { title: string; message: string; link: string };
export type SendResult = {
  sendId: number; duplicate: boolean; test: boolean; resolved: number; inApp: number; inAppFailed: number;
  pushSent: number; pushFailed: number; pushSkipped: number; nightSkipped: number; deadTokens: number; pushError: string;
};
export type ScheduledItem = {
  id: number; adminId: number; adminUsername: string; status: "pending" | "running" | "done" | "failed" | "cancelled"; runAt: string; sendId: number | null; error: string;
  target: string; type: string; title: string; message: string; link: string; nightPolicy: "skip" | "delay"; memberCount: number | null;
};
export type ScheduledList = { available: boolean; items: ScheduledItem[] };
export type Diagnostics = {
  summary: { total: number; active: number; dead: number; android: number; ios: number; web: number; withoutMember: number; iosMarkedAndroid: number };
  duplicates: unknown[];
  multiDevices: { memberId: string; platform: string; count: number; lastSeen: string }[];
  recent: { memberId: string; platform: string; active: boolean; tokenStart: string; at: string }[];
};
export type AutomaticInfo = { visitReminder: { available: boolean; sentToday: number; sentYesterday: number; lastSentAt: string | null; startsAt: string } };

const link = (v: unknown): LinkTarget => {
  const o = obj(v);
  const kind = o.kind === "hospital" || o.kind === "event" ? o.kind : "page";
  return { kind, path: str(o.path), label: str(o.label) };
};
const historyItem = (v: unknown): HistoryItem => {
  const o = obj(v);
  return {
    id: num(o.id), adminId: num(o.adminId), adminUsername: str(o.adminUsername), target: str(o.target), type: str(o.type), title: str(o.title), message: str(o.message), link: str(o.link),
    resolved: num(o.resolved), inApp: num(o.inApp), pushSent: num(o.pushSent), pushFailed: num(o.pushFailed), pushSkipped: num(o.pushSkipped), pushError: str(o.pushError),
    isTest: bool(o.isTest), requestId: typeof o.requestId === "string" ? o.requestId : null, createdAt: str(o.createdAt),
  };
};
const scheduledItem = (v: unknown): ScheduledItem => {
  const o = obj(v);
  const status = ["pending", "running", "done", "failed", "cancelled"].includes(str(o.status)) ? (str(o.status) as ScheduledItem["status"]) : "failed";
  return {
    id: num(o.id), adminId: num(o.adminId), adminUsername: str(o.adminUsername), status, runAt: str(o.runAt), sendId: o.sendId == null ? null : num(o.sendId), error: str(o.error),
    target: str(o.target), type: str(o.type), title: str(o.title), message: str(o.message), link: str(o.link), nightPolicy: o.nightPolicy === "delay" ? "delay" : "skip",
    memberCount: o.memberCount == null ? null : num(o.memberCount),
  };
};

export async function fetchNotificationOptions(signal?: AbortSignal): Promise<ApiResult<NotificationOptions>> {
  const res = await getJson("/v1/notifications/options", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const w = obj(d.nightWindow);
  return {
    ok: true,
    data: {
      types: list(d.types).map((t) => ({ value: str(obj(t).value) as NotificationType, gate: (["toggle", "marketing"].includes(str(obj(t).gate)) ? str(obj(t).gate) : "none") as "toggle" | "marketing" | "none" })),
      events: list(d.events).map((e) => ({ id: num(obj(e).id), title: str(obj(e).title) })),
      links: list(d.links).map(link),
      night: bool(d.night), nightWindow: { start: num(w.start), end: num(w.end) },
      fcmConfigured: bool(d.fcmConfigured), sendEnabled: bool(d.sendEnabled), scheduleEnabled: bool(d.scheduleEnabled),
    },
  };
}

/** The audience as the server reads it (only the fields of its target). */
const audienceBody = (a: Audience): Json => ({
  target: a.target, type: a.type,
  ...(a.target === "specific_event" ? { eventId: a.eventId } : {}),
  ...(a.target === "individual" ? { memberId: a.memberId } : {}),
  ...(a.target === "selected" ? { memberIds: a.memberIds } : {}),
});

export async function previewNotification(a: Audience): Promise<WriteResult<Preview>> {
  const res = await sendJson("POST", "/v1/notifications/preview", audienceBody(a));
  if (!res.ok) return res;
  const d = res.data;
  return {
    ok: true,
    data: {
      resolved: num(d.resolved), inApp: num(d.inApp), pushMembers: num(d.pushMembers), pushWithTokens: num(d.pushWithTokens), pushTokens: num(d.pushTokens),
      skippedInApp: num(d.skippedInApp), skippedPush: num(d.skippedPush), nightSkipped: num(d.nightSkipped), marketing: bool(d.marketing), night: bool(d.night), topic: bool(d.topic), settingsAvailable: bool(d.settingsAvailable),
    },
  };
}

export async function fetchHistory(page: number, kind: "admin" | "auto", signal?: AbortSignal): Promise<ApiResult<HistoryList>> {
  const res = await getJson(`/v1/notifications/history?page=${encodeURIComponent(String(page))}&kind=${kind}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return { ok: true, data: { available: bool(d.available), total: num(d.total), page: num(d.page), perPage: num(d.perPage), items: list(d.items).map(historyItem) } };
}

export async function fetchHistoryDetail(id: number, page: number, status: string, signal?: AbortSignal): Promise<ApiResult<HistoryDetail>> {
  const q = new URLSearchParams({ page: String(page) });
  if (status) q.set("status", status);
  const res = await getJson(`/v1/notifications/history/${encodeURIComponent(String(id))}?${q.toString()}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const it = obj(d.items);
  return {
    ok: true,
    data: {
      ...historyItem(d), details: d.details ?? null, itemsAvailable: bool(d.itemsAvailable),
      items: {
        total: num(it.total), page: num(it.page), perPage: num(it.perPage), status: str(it.status),
        rows: list(it.rows).map((r) => { const o = obj(r); return { memberId: str(o.memberId), channel: str(o.channel), platform: str(o.platform), status: str(o.status), errorCode: str(o.errorCode), at: str(o.at) }; }),
      },
    },
  };
}

export async function fetchLinkTargets(q: string, kind: "" | "page" | "hospital" | "event", signal?: AbortSignal): Promise<ApiResult<LinkTarget[]>> {
  const params = new URLSearchParams({ q });
  if (kind) params.set("kind", kind);
  const res = await getJson(`/v1/notifications/link-targets?${params.toString()}`, signal);
  if (!res.ok) return res;
  return { ok: true, data: (Array.isArray(res.data) ? res.data : list(obj(res.data).items)).map(link) };
}

export async function fetchMemberSearch(q: string, signal?: AbortSignal): Promise<ApiResult<MemberHit[]>> {
  const res = await getJson(`/v1/notifications/member-search?${new URLSearchParams({ q }).toString()}`, signal);
  if (!res.ok) return res;
  return { ok: true, data: list(obj(res.data).items).map((r) => { const o = obj(r); return { id: str(o.id), name: str(o.name), nick: str(o.nick), phoneTail: str(o.phoneTail), hasPush: bool(o.hasPush) }; }).filter((m) => m.id !== "") };
}

export async function fetchDiagnostics(signal?: AbortSignal): Promise<ApiResult<Diagnostics>> {
  const res = await getJson("/v1/notifications/diagnostics", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const s = obj(d.summary);
  return {
    ok: true,
    data: {
      summary: { total: num(s.total), active: num(s.active), dead: num(s.dead), android: num(s.android), ios: num(s.ios), web: num(s.web), withoutMember: num(s.withoutMember), iosMarkedAndroid: num(s.iosMarkedAndroid) },
      duplicates: list(d.duplicates),
      multiDevices: list(d.multiDevices).map((r) => { const o = obj(r); return { memberId: str(o.memberId), platform: str(o.platform), count: num(o.count), lastSeen: str(o.lastSeen) }; }),
      recent: list(d.recent).map((r) => { const o = obj(r); return { memberId: str(o.memberId), platform: str(o.platform), active: bool(o.active), tokenStart: str(o.tokenStart), at: str(o.at) }; }),
    },
  };
}

export async function fetchAutomatic(signal?: AbortSignal): Promise<ApiResult<AutomaticInfo>> {
  const res = await getJson("/v1/notifications/automatic", signal);
  if (!res.ok) return res;
  const v = obj(obj(res.data).visitReminder);
  return { ok: true, data: { visitReminder: { available: bool(v.available), sentToday: num(v.sentToday), sentYesterday: num(v.sentYesterday), lastSentAt: typeof v.lastSentAt === "string" ? v.lastSentAt : null, startsAt: str(v.startsAt) } } };
}

const sendResult = (d: Json): SendResult => ({
  sendId: num(d.sendId), duplicate: bool(d.duplicate), test: bool(d.test), resolved: num(d.resolved), inApp: num(d.inApp), inAppFailed: num(d.inAppFailed),
  pushSent: num(d.pushSent), pushFailed: num(d.pushFailed), pushSkipped: num(d.pushSkipped), nightSkipped: num(d.nightSkipped), deadTokens: num(d.deadTokens), pushError: str(d.pushError),
});

/** Sends now. `requestId` is made once per press (newRequestId); `expectedResolved` is the recipient count the admin saw in the preview. */
export async function sendNotification(input: SendInput, requestId: string, expectedResolved: number, test = false): Promise<WriteResult<SendResult>> {
  const res = await sendJson("POST", "/v1/notifications/send", { ...audienceBody(input), title: input.title, message: input.message, link: input.link, requestId, ...(test ? { test: true } : { expectedResolved }) });
  return res.ok ? { ok: true, data: sendResult(res.data) } : res;
}

/** Plans a send; `runAt` is "YYYY-MM-DDTHH:mm" in Korean time. */
export async function scheduleNotification(input: SendInput, runAt: string, nightPolicy: "skip" | "delay"): Promise<WriteResult<{ id: number; runAt: string }>> {
  const res = await sendJson("POST", "/v1/notifications/schedule", { ...audienceBody(input), title: input.title, message: input.message, link: input.link, runAt, nightPolicy });
  return res.ok ? { ok: true, data: { id: num(res.data.id), runAt: str(res.data.runAt) } } : res;
}

export async function fetchScheduled(signal?: AbortSignal): Promise<ApiResult<ScheduledList>> {
  const res = await getJson("/v1/notifications/scheduled", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return { ok: true, data: { available: bool(d.available), items: list(d.items).map(scheduledItem) } };
}

export async function cancelScheduled(id: number): Promise<WriteResult<{ id: number }>> {
  const res = await sendJson("DELETE", `/v1/notifications/scheduled/${encodeURIComponent(String(id))}`);
  return res.ok ? { ok: true, data: { id: num(res.data.id) } } : res;
}
