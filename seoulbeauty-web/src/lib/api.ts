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
  wishlisted: boolean;
  /** price line for the effective treatment of the current search (null otherwise) */
  treatment: TreatmentLine | null;
  /** paid keyword slot pinned by the API (tracking token comes from PHP api/ad_slot_tokens.php) */
  adSlot: { bookingId: number; rank: number } | null;
}

export interface TreatmentLine {
  name: string; desc: string | null; priceMin: number; priceMax: number; dosageValue: number | null; dosageUnit: string | null;
}

export interface InfeedAd { id: number; title: string; subtitle: string | null; imageUrl: string | null; linkUrl: string; hospitalId: number | null }

/** GET /v1/hospitals — list page plus the search context the PHP list computes server-side. */
export interface HospitalPage extends Page<Hospital> {
  sort: HospitalSort;
  effectiveTreatment: { id: number; name: string } | null;
  dosageOptions: string[];
  dosage: string | null;
  infeed: { interval: number; ads: InfeedAd[] } | null;
}

/** GET /v1/hospitals/:id/search-view — hospitals.php?hid= */
export interface SearchView {
  hospital: Hospital;
  total: number;
  categories: { id: number; name: string; count: number }[];
  treatments: { id: number; name: string; categoryId: number | null; price: number; dosageValue: number | null; dosageUnit: string | null }[];
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
  treatments: string[];
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
  dosage?: string;
  /** empty = server default (price when a treatment is effective, else manual) */
  sort?: HospitalSort | "";
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

/** Server Components pass the incoming request's Cookie header so SSR sees the member's state (wishlist). */
export type ServerCtx = { cookie?: string };

async function get<T>(path: string, params?: Record<string, string | number | undefined>, ctx?: ServerCtx): Promise<T> {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params || {})) if (v !== undefined && v !== "") qs.set(k, String(v));
  const query = qs.toString(); // not `qs.size`: unsupported before Chrome 113 / Safari 17
  const url = `${base()}${path}${query ? `?${query}` : ""}`;
  const headers: Record<string, string> = { accept: "application/json" };
  if (ctx?.cookie) headers.cookie = ctx.cookie;
  // same-origin: the PHP session cookie travels with /next/api/* so the API can ask PHP who is logged in
  const res = await fetch(url, { headers, credentials: "same-origin", signal: timeoutSignal(8000) });
  if (!res.ok) throw new ApiError(res.status, `${res.status} ${path}`);
  return (await res.json()) as T;
}

async function post<T>(path: string, body: unknown): Promise<T> {
  const url = `${base()}${path}`;
  const res = await fetch(url, {
    method: "POST",
    headers: { accept: "application/json", "content-type": "application/json", "X-Requested-With": "XMLHttpRequest" },
    credentials: "same-origin",
    body: JSON.stringify(body),
    signal: timeoutSignal(10000),
  });
  if (!res.ok) {
    let msg = `${res.status} ${path}`;
    try { const j = await res.json(); if (j?.message) msg = String(j.message); } catch { /* keep */ }
    throw new ApiError(res.status, msg);
  }
  return (await res.json()) as T;
}

export interface WishlistState { wishlisted: boolean; count: number }
export interface ConsultationInput { hospitalId: number; type: "general" | "doctor" | "kakao"; name: string; phone: string; message: string }

export interface HomeAd { id: number; title: string; subtitle: string | null; imageUrl: string | null; linkUrl: string }
export interface HomePromo { id: number; title: string; subtitle: string | null; background: string; icon: string | null; linkUrl: string }
export interface HomeEvent {
  id: number; title: string; category: string | null; imageUrl: string | null; hospitalId: number | null;
  hospitalName: string | null; hospitalAddress: string | null; originalPrice: number | null; discountPrice: number | null;
  discountRate: number | null; hasRaffle: boolean; endDate: string | null;
}
export interface HomeCategory { id: number; name: string; treatments: string[] }
export interface HomeData {
  ads: HomeAd[]; promos: HomePromo[]; events: HomeEvent[]; categories: HomeCategory[];
  settings: { adShowIndex: boolean; adAfterSearch: boolean; adAfterEvents: boolean; promoShowIndex: boolean; tableMode: "excel" | "row" | "hybrid"; adAutoScroll: boolean; promoAutoScroll: boolean; blocks: { promos: boolean; events: boolean } };
  regions: string[][];
  popularKeywords: string[];
}

/** GET /v1/events — events.php (+ the page banner from includes/ad_banner.php) */
export interface EventSummary {
  id: number; title: string; subtitle: string | null; category: string | null; imageUrl: string | null;
  hospitalId: number | null; hospitalName: string | null;
  originalPrice: number | null; discountPrice: number | null; discountRate: number | null;
  hasRaffle: boolean; endDate: string | null;
}
export interface PageAd { id: number; title: string; subtitle: string | null; imageUrl: string | null; linkUrl: string }
export interface EventList { items: EventSummary[]; categories: string[]; category: string; raffleOnly: boolean; ads: PageAd[]; adAutoScroll: boolean }
export interface RaffleInfo {
  type: string; typeLabel: string; typeIcon: string; typeDesc: string;
  winnerCount: number; entryCount: number; maxEntries: number; endDate: string | null; prize: string | null;
  closed: boolean; closedReason: "period" | "full" | null; entered: boolean;
}
/** GET /v1/events/:id — event_detail.php */
export interface EventDetail {
  id: number; title: string; subtitle: string | null; description: string | null;
  imageUrl: string | null; images: { imageUrl: string; caption: string | null }[];
  category: string | null; categoryColor: string; hospitalId: number | null; hospitalName: string | null;
  originalPrice: number; discountPrice: number; discountRate: number;
  startDate: string | null; endDate: string | null; ended: boolean; raffle: RaffleInfo | null;
}
export interface EventEntryInput { name: string; phone: string; email: string }

/** GET /v1/search/suggest (port of search_suggest.php); hrefs are PHP-style `hospitals.php?...`, mapped to Next routes in SearchOverlay. */
export interface SearchSuggest {
  treatments: { name: string; href: string; hint?: string }[];
  hospitals: { id: number; name: string; district: string; address: string; href: string }[];
  offered: { treatment: string; items: { id: number; name: string; district: string; address: string; price: number; href: string }[] };
}

export const api = {
  home: (ctx?: ServerCtx) => get<HomeData>("/v1/home", undefined, ctx),
  wishlist: (id: number) => get<WishlistState>(`/v1/wishlist/hospitals/${id}`),
  toggleWishlist: (id: number) => post<WishlistState & { message: string }>("/v1/wishlist/toggle", { type: "hospital", targetId: id }),
  consult: (input: ConsultationInput) => post<{ success: boolean; id: number; message: string }>("/v1/consultations", input),
  me: () => get<{ loggedIn: boolean; user: { id: string; no: number; name: string; nickname: string; level: number; phone: string } | null; unreadNotifications: number }>("/v1/me"),
  hospitals: (p: HospitalListParams = {}, ctx?: ServerCtx) => get<HospitalPage>("/v1/hospitals", { ...p, limit: p.limit ?? 20 }, ctx),
  searchView: (id: number, ctx?: ServerCtx) => get<SearchView>(`/v1/hospitals/${id}/search-view`, undefined, ctx),
  events: (p: { category?: string; filter?: string } = {}, ctx?: ServerCtx) => get<EventList>("/v1/events", p, ctx),
  event: (id: number, ctx?: ServerCtx) => get<EventDetail>(`/v1/events/${id}`, undefined, ctx),
  enterEvent: (id: number, input: EventEntryInput) => post<{ success: boolean; entryId: number; message: string }>(`/v1/events/${id}/entries`, input),
  suggest: (q: string) => get<SearchSuggest>("/v1/search/suggest", { q }),
  hospital: (id: number) => get<HospitalDetail>(`/v1/hospitals/${id}`),
  treatments: (id: number) => get<{ items: HospitalTreatment[] }>(`/v1/hospitals/${id}/treatments`),
  doctors: (id: number) => get<{ items: Doctor[] }>(`/v1/hospitals/${id}/doctors`),
  reviews: (id: number, limit = 20, offset = 0) => get<Page<Review>>(`/v1/hospitals/${id}/reviews`, { limit, offset }),
};

/** Query keys shared by server prefetch and client hooks (React Query). */
export const qk = {
  hospitals: (p: HospitalListParams) => ["hospitals", normalize(p)] as const,
  hospital: (id: number) => ["hospital", id] as const,
  searchView: (id: number) => ["searchView", id] as const,
  reviews: (id: number) => ["reviews", id] as const,
  treatments: (id: number) => ["treatments", id] as const,
  doctors: (id: number) => ["doctors", id] as const,
  wishlist: (id: number) => ["wishlist", id] as const,
  home: ["home"] as const,
  events: (category: string, raffle: boolean) => ["events", category, raffle] as const,
  event: (id: number) => ["event", id] as const,
};

export type DetailTab = "home" | "treatments" | "reviews" | "doctors";
export const DETAIL_TABS: DetailTab[] = ["home", "treatments", "reviews", "doctors"];
/** detail.php?tab= values -> ours (PHP's "events" tab is the treatment list). */
export function toDetailTab(v: string | undefined): DetailTab {
  if (v === "events" || v === "treatments") return "treatments";
  if (v === "reviews" || v === "doctors") return v;
  return "home";
}

export function normalize(p: HospitalListParams): Required<Pick<HospitalListParams, "q" | "category" | "treatment" | "region" | "dosage" | "sort">> {
  // Unknown/absent sort -> "" so the API applies PHP's default (price when a treatment is effective).
  const sort = HOSPITAL_SORTS.includes(p.sort as HospitalSort) ? (p.sort as HospitalSort) : "";
  return { q: p.q || "", category: p.category || "", treatment: p.treatment || "", region: p.region || "", dosage: p.dosage || "", sort };
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
