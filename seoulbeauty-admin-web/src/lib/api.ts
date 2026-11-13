import { BASE_PATH } from "./config";

export type Admin = { id: number; username: string; name: string; role: string };

export type BookingPeriod = {
  count: number;
  amount: number;
  paidAmount: number;
  refundedAmount: number;
  cancelled: number;
  pending: number;
  prev: { count: number; amount: number; paidAmount: number };
};

export type DashboardSummary = {
  /** "YYYY-MM-DD HH:MM:SS", KST */
  generatedAt: string;
  members: { total: number; today: number; last7d: number; last30d: number };
  bookings: { d7: BookingPeriod; d30: BookingPeriod };
  consultations: { d7: number; d30: number };
  catalog: { hospitals: number; hospitalsPending: number; doctors: number; treatments: number };
};

export type ApiResult<T> =
  | { ok: true; data: T }
  /** status 0 = the request never got an answer (network down, proxy error page, bad JSON). */
  | { ok: false; status: number };

type Json = Record<string, unknown>;

const obj = (v: unknown): Json => (v !== null && typeof v === "object" ? (v as Json) : {});
const num = (v: unknown): number => {
  const n = typeof v === "string" ? Number(v) : v;
  return typeof n === "number" && Number.isFinite(n) ? n : 0;
};
const str = (v: unknown): string => (typeof v === "string" ? v : "");

/** GET through the same-origin /api rewrite; the admin session cookie goes along. Never cached. */
async function getJson(path: string, signal?: AbortSignal): Promise<ApiResult<unknown>> {
  try {
    const res = await fetch(`${BASE_PATH}/api${path}`, {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
      signal,
    });
    if (!res.ok) return { ok: false, status: res.status };
    return { ok: true, data: await res.json() };
  } catch {
    return { ok: false, status: 0 };
  }
}

export async function fetchMe(signal?: AbortSignal): Promise<ApiResult<Admin>> {
  const res = await getJson("/v1/me", signal);
  if (!res.ok) return res;
  const a = obj(obj(res.data).admin);
  return { ok: true, data: { id: num(a.id), username: str(a.username), name: str(a.name), role: str(a.role) } };
}

function bookingPeriod(v: unknown): BookingPeriod {
  const b = obj(v);
  const prev = obj(b.prev);
  return {
    count: num(b.count),
    amount: num(b.amount),
    paidAmount: num(b.paidAmount),
    refundedAmount: num(b.refundedAmount),
    cancelled: num(b.cancelled),
    pending: num(b.pending),
    prev: { count: num(prev.count), amount: num(prev.amount), paidAmount: num(prev.paidAmount) },
  };
}

/** A missing or non-numeric field becomes 0, so one odd value never blanks the whole page. */
export async function fetchSummary(signal?: AbortSignal): Promise<ApiResult<DashboardSummary>> {
  const res = await getJson("/v1/dashboard/summary", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const members = obj(d.members);
  const bookings = obj(d.bookings);
  const consultations = obj(d.consultations);
  const catalog = obj(d.catalog);
  return {
    ok: true,
    data: {
      generatedAt: str(d.generatedAt),
      members: { total: num(members.total), today: num(members.today), last7d: num(members.last7d), last30d: num(members.last30d) },
      bookings: { d7: bookingPeriod(bookings.d7), d30: bookingPeriod(bookings.d30) },
      consultations: { d7: num(consultations.d7), d30: num(consultations.d30) },
      catalog: {
        hospitals: num(catalog.hospitals),
        hospitalsPending: num(catalog.hospitalsPending),
        doctors: num(catalog.doctors),
        treatments: num(catalog.treatments),
      },
    },
  };
}
