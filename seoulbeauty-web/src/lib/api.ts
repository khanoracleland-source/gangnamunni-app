/** Typed client for seoulbeauty-api (NestJS). Mirrors the DTOs in seoulbeauty-api/src. */

export type HospitalSort = "manual" | "price" | "rating" | "popular" | "review";
export const HOSPITAL_SORTS: HospitalSort[] = ["manual", "price", "rating", "popular", "review"];

export interface Hospital {
  id: number;
  name: string;
  description: string | null;
  introduction: string | null;
  address: string | null;
  phone: string | null;
  profileImageUrl: string | null;
  mainImageUrl: string | null;
  showRating: boolean;
  rating: number | null;
  reviewCount: number;
  minPrice: number | null;
  latitude: number | null;
  longitude: number | null;
  categories: string[];
  badges: string[];
  treatmentTags: string[];
  subwayNames: string[];
  businessHours: Record<string, unknown> | null;
  consultation: { phone: boolean; chat: boolean; general: boolean };
  wishlistCount: number;
  thumbnailUrl: string | null;
}

export interface HospitalDetail extends Hospital {
  images: string[];
  features: { icon: string; label: string }[];
}

export interface TreatmentOption {
  id: number;
  displayName: string;
  optionName: string;
  bodyPart: string;
  dosage: string;
  regularPrice: number;
  price: number;
  discountRate: number;
}
export interface HospitalTreatment {
  treatmentId: number;
  name: string;
  description: string | null;
  promoted: boolean;
  options: TreatmentOption[];
}
export interface Doctor {
  id: number;
  name: string;
  specialty: string | null;
  experience: string | null;
  description: string | null;
  qualification: string | null;
  photoUrl: string | null;
}

export interface Review {
  id: number;
  hospitalId: number;
  title: string | null;
  userName: string;
  userAvatar: string | null;
  rating: number;
  content: string | null;
  treatments: string[];
  images: string[];
  likeCount: number;
  commentCount: number;
  verified: boolean;
  createdAt: string | null;
}

export interface Page<T> {
  items: T[];
  total: number;
  limit: number;
  offset: number;
}

export interface HospitalListParams {
  q?: string;
  category?: string;
  treatment?: string;
  region?: string;
  sort?: HospitalSort;
  limit?: number;
  offset?: number;
}

export class ApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
  }
}

/** Server: direct to the API. Browser: same-origin /api (see next.config.ts rewrites). */
function base(): string {
  if (typeof window === "undefined") return process.env.API_BASE || "http://127.0.0.1:3110";
  return `${process.env.NEXT_PUBLIC_BASE_PATH ?? "/next"}/api`; // same-origin, rewritten in next.config.ts
}

/** AbortSignal.timeout() is missing in older WebViews (iOS < 16, Chrome < 103): fall back to a manual timer. */
function timeoutSignal(ms: number): AbortSignal | undefined {
  if (typeof AbortController === "undefined") return undefined;
  const ctrl = new AbortController();
  setTimeout(() => ctrl.abort(), ms);
  return ctrl.signal;
}

async function get<T>(path: string, params?: Record<string, string | number | undefined>): Promise<T> {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params || {})) if (v !== undefined && v !== "") qs.set(k, String(v));
  const query = qs.toString(); // not `qs.size`: unsupported before Chrome 113 / Safari 17
  const url = `${base()}${path}${query ? `?${query}` : ""}`;
  const res = await fetch(url, { headers: { accept: "application/json" }, signal: timeoutSignal(8000) });
  if (!res.ok) throw new ApiError(res.status, `${res.status} ${path}`);
  return (await res.json()) as T;
}

export const api = {
  hospitals: (p: HospitalListParams = {}) => get<Page<Hospital>>("/v1/hospitals", { ...p, limit: p.limit ?? 20 }),
  hospital: (id: number) => get<HospitalDetail>(`/v1/hospitals/${id}`),
  treatments: (id: number) => get<{ items: HospitalTreatment[] }>(`/v1/hospitals/${id}/treatments`),
  doctors: (id: number) => get<{ items: Doctor[] }>(`/v1/hospitals/${id}/doctors`),
  reviews: (id: number, limit = 20, offset = 0) => get<Page<Review>>(`/v1/hospitals/${id}/reviews`, { limit, offset }),
};

/** Query keys shared by server prefetch and client hooks (React Query). */
export const qk = {
  hospitals: (p: HospitalListParams) => ["hospitals", normalize(p)] as const,
  hospital: (id: number) => ["hospital", id] as const,
  reviews: (id: number) => ["reviews", id] as const,
  treatments: (id: number) => ["treatments", id] as const,
  doctors: (id: number) => ["doctors", id] as const,
};

export type DetailTab = "home" | "treatments" | "reviews" | "doctors";
export const DETAIL_TABS: DetailTab[] = ["home", "treatments", "reviews", "doctors"];
/** detail.php?tab= values -> ours (PHP's "events" tab is the treatment list). */
export function toDetailTab(v: string | undefined): DetailTab {
  if (v === "events" || v === "treatments") return "treatments";
  if (v === "reviews" || v === "doctors") return v;
  return "home";
}

export function normalize(p: HospitalListParams): Required<Pick<HospitalListParams, "q" | "category" | "treatment" | "region" | "sort">> {
  const sort = HOSPITAL_SORTS.includes(p.sort as HospitalSort) ? (p.sort as HospitalSort) : p.treatment ? "price" : "manual";
  return { q: p.q || "", category: p.category || "", treatment: p.treatment || "", region: p.region || "", sort };
}

const ASSET_BASE = (process.env.NEXT_PUBLIC_ASSET_BASE || "").replace(/\/$/, "");
/** Image URLs in the DB are relative to the PHP site root (e.g. "uploads/hospitals/…"). */
export function assetUrl(path: string | null | undefined): string | null {
  if (!path) return null;
  if (/^(https?:)?\/\//.test(path) || path.startsWith("data:")) return path;
  return `${ASSET_BASE}/${path.replace(/^\/+/, "")}`;
}

/** Same placeholder the PHP list uses. */
export const PLACEHOLDER = `${ASSET_BASE}/assets/images/seoul_beauty_hospital_placeholder.jpeg`;

export function formatWon(n: number | null | undefined): string | null {
  return n && n > 0 ? `${n.toLocaleString("ko-KR")}원` : null;
}
