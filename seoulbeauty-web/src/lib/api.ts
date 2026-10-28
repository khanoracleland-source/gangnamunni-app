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
  constructor(public status: number, message: string, public field?: string) {
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

async function send<T>(method: "POST" | "PUT" | "DELETE", path: string, body?: unknown, ms = 10000): Promise<T> {
  const url = `${base()}${path}`;
  const isForm = typeof FormData !== "undefined" && body instanceof FormData;
  const headers: Record<string, string> = { accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
  if (!isForm) headers["content-type"] = "application/json"; // multipart: the browser sets the boundary
  const res = await fetch(url, {
    method, headers, credentials: "same-origin",
    body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
    signal: timeoutSignal(ms),
  });
  if (!res.ok) {
    let msg = `${res.status} ${path}`;
    let field: string | undefined;
    try { const j = await res.json(); if (j?.message) msg = String(j.message); if (typeof j?.field === "string") field = j.field; } catch { /* keep */ }
    throw new ApiError(res.status, msg, field);
  }
  return (await res.json()) as T;
}
const post = <T,>(path: string, body: unknown) => send<T>("POST", path, body);

/** /v1/mypage* — 마이페이지 (member only) */
export interface MypageAd { id: number; title: string; subtitle: string | null; imageUrl: string | null; linkUrl: string }
export interface MypageSummary {
  nickname: string; avatarUrl: string | null; bookingCount: number; consultCount: number;
  points: { visible: boolean; balance: number }; ads: { show: boolean; autoScroll: boolean; items: MypageAd[] };
}
export interface MyWishlistItem { id: number; hospitalId: number; name: string; available: boolean; imageUrl: string | null; district: string; address: string }
export interface BookingBase { id: number; hospitalId: number; hospitalName: string; imageUrl: string | null; treatment: string; price: number; dateText: string; status: string }
export interface UpcomingBooking extends BookingBase { chip: string; steps: string[]; activeStep: number; action: "pay" | "change" | null; editsLeft: number; paid: boolean }
export interface CompletedBooking extends BookingBase { isDone: boolean; chip: string; review: { state: "none" | "pending" | "published" | "rejected"; label: string; reviewId: number | null } | null }
export interface CancelledBooking extends BookingBase { chip: string; refundComplete: boolean; steps: string[]; activeStep: number }
export interface MyBookingsData {
  upcoming: UpcomingBooking[]; completed: CompletedBooking[]; cancelled: CancelledBooking[]; showPushHint: boolean;
  expiredNotice: { bookingId: number; hospitalId: number; treatment: string; deadlineHours: number } | null;
}
export interface BookingNotification { at: string; event: string | null; title: string; body: string; inApp: number; pushSent: number; pushFailed: number; pushSkipped: boolean }
interface BookingDetailBase { id: number; hospitalId: number; hospitalName: string; treatment: string; bookerName: string; phone: string; dateText: string; price: number; status: string }
export interface BookingRequestDetail extends BookingDetailBase {
  kind: "request"; statusLabel: string; isActive: boolean; isCompleted: boolean; dday: string; doctorName: string | null; treatmentPrice: number; totalPayment: number;
  summary: { gross: number; points: number; final: number; expectedPoints: number | null } | null;
  review: { state: "none" | "pending" | "published" | "rejected"; label: string; reviewId: number | null } | null;
  notifications: BookingNotification[];
}
export interface BookingCancelDetail extends BookingDetailBase {
  kind: "cancelled"; badge: string; reason: string | null; paid: { total: number; cancelledAt: string | null; refunded: number | null } | null;
}
export type BookingDetail = BookingRequestDetail | BookingCancelDetail;
export interface FaqData { categories: string[]; items: { id: number; category: string; question: string; answer: string }[] }
export interface ConsultationDetail {
  id: number; hospitalId: number; hospitalName: string; status: string; statusLabel: string; statusTone: "danger" | "muted" | "dark"; type: string;
  sub: string; createdLabel: string; events: { label: string; at: string }[]; completed: boolean; completedAt: string;
  hospitalMessage: { text: string; meta: string } | null; slot: string; canRerequest: boolean; showSlots: boolean;
  slots: { key: string; label: string }[]; hospitalPhone: string | null; kakaoUrl: string | null;
}
export interface ReviewDetail {
  id: number; hospitalId: number | null; treatments: string[]; rating: number; ratingValue: number; title: string; content: string;
  images: { src: string; full: string }[]; author: { name: string; avatar: string | null };
  likeCount: number; commentCount: number; viewCount: number; liked: boolean; isMine: boolean;
  hospital: { id: number; name: string; address: string; imageUrl: string | null; rating: number | null; reviewCount: number } | null;
}
export interface MyConsultation {
  id: number; hospitalId: number; hospitalName: string; type: string; typeLabel: string; status: string;
  statusLabel: string; statusTone: "danger" | "muted" | "dark"; dateLabel: string; hospitalPhone: string | null;
}
export interface MyWritableReview { bookingId: number; hospitalId: number; hospitalName: string; treatment: string; imageUrl: string | null; district: string; address: string; doctorName: string | null; bookingDate: string }
export interface MyDoneReview {
  id: number; hospitalId: number; hospitalName: string; imageUrl: string | null; rating: number; ratingValue: number; treatment: string;
  district: string; address: string; doctorName: string | null; bookingDate: string; pending: boolean;
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

/** GET /v1/community — community.php (tabs latest | popular | review) */
export type CommunityTab = "latest" | "popular" | "review";
export interface CommunityItem {
  id: number; kind: "post" | "review"; category: string; title: string; preview: string; fullText: string | null;
  createdAt: string; updatedAt: string | null; authorName: string; authorAvatar: string | null; photos: string[];
  likeCount: number; commentCount: number; viewCount: number; liked: boolean; isMine: boolean;
}
export interface CommunityList {
  tab: CommunityTab; category: string; regions: string[]; q: string;
  page: number; perPage: number; total: number; totalPages: number; items: CommunityItem[];
}
export type ContentType = "community" | "comment" | "review";
export interface CommunityForm {
  categories: string[]; maxPhotos: number; maxPhotoBytes: number;
  post: { id: number; category: string; title: string; content: string; photos: string[] } | null;
}
export interface ReviewFormDoctor { id: number; name: string; specialty: string; photoUrl: string | null; tags: string[] }
/** GET /v1/mypage/review-form — review_write.php */
export interface ReviewForm {
  mode: "create" | "edit"; hospitalId: number; bookingId: number; editId: number; backUrl: string;
  card: { name: string; address: string; imageUrl: string | null; treatment: string; doctorName: string; bookingDate: string } | null;
  treatments: string[]; selectedTreatment: string; doctors: ReviewFormDoctor[]; selectedDoctorIds: number[]; doctorUnknown: boolean;
  review: { rating: number; title: string; content: string } | null;
  limits: { photoSlots: number; photoBytes: number; titleMax: number; contentMin: number; contentMax: number };
}
export interface ReviewFormQuery { booking_id?: number; hospital_id?: number; id?: number; return_url?: string }
export interface BlockedUser { id: number; name: string; createdAt: string }
export interface CommunityListParams { tab?: string; category?: string; region?: string; q?: string; page?: number }
/** GET /v1/community/:id — detail_community.php */
export interface CommunityComment { id: number; parentId: number; authorName: string; content: string; createdAt: string; likeCount: number; liked: boolean; isMine: boolean }
export interface CommunityPost {
  id: number; category: string; title: string; content: string; createdAt: string; authorName: string; authorAvatar: string | null; photos: string[];
  likeCount: number; commentCount: number; viewCount: number; liked: boolean; isAuthor: boolean; comments: CommunityComment[];
}

/** GET /v1/search/suggest (port of search_suggest.php); hrefs are PHP-style `hospitals.php?...`, mapped to Next routes in SearchOverlay. */
export interface SearchSuggest {
  treatments: { name: string; href: string; hint?: string }[];
  hospitals: { id: number; name: string; district: string; address: string; href: string }[];
  offered: { treatment: string; items: { id: number; name: string; district: string; address: string; price: number; href: string }[] };
}

export type NotiIcon = "calendar" | "payment" | "clock" | "gift" | "review" | "bell";
export type NotiFilter = "all" | "booking" | "review" | "benefit";
export interface MyNotification { id: number; type: string; title: string; message: string; isRead: boolean; icon: NotiIcon; time: string; openUrl: string }
export interface MyNotificationsPage { items: MyNotification[]; total: number; page: number; hasMore: boolean; unread: number }

export interface MyProfile { nick: string; name: string; hp: string; birth: string; email: string; photoUrl: string | null; birthChangeDays: number }
export const SETTING_KEYS = ["noti_general", "noti_booking", "noti_event", "noti_promo", "noti_push", "noti_message", "mkt_push", "mkt_sms", "mkt_email", "mkt_night_push"] as const;
export type SettingKey = (typeof SETTING_KEYS)[number];
export type NotificationSettings = Record<SettingKey, number>;

export const api = {
  home: (ctx?: ServerCtx) => get<HomeData>("/v1/home", undefined, ctx),
  wishlist: (id: number) => get<WishlistState>(`/v1/wishlist/hospitals/${id}`),
  mypage: (ctx?: ServerCtx) => get<MypageSummary>("/v1/mypage", undefined, ctx),
  myWishlist: (ctx?: ServerCtx) => get<{ items: MyWishlistItem[] }>("/v1/mypage/wishlist", undefined, ctx),
  myConsultations: (ctx?: ServerCtx) => get<{ items: MyConsultation[]; anyPhone: string | null }>("/v1/mypage/consultations", undefined, ctx),
  myBookings: (ctx?: ServerCtx) => get<MyBookingsData>("/v1/mypage/bookings", undefined, ctx),
  bookingDetail: (id: number, ctx?: ServerCtx) => get<BookingDetail>(`/v1/mypage/bookings/${id}`, undefined, ctx),
  bookingExpireSeen: (id: number) => post<{ success: boolean }>(`/v1/mypage/bookings/${id}/expire-seen`, {}),
  myNotifications: (type: NotiFilter, page: number, ctx?: ServerCtx) => get<MyNotificationsPage>("/v1/mypage/notifications", { type: type === "all" ? undefined : type, page: page > 1 ? page : undefined }, ctx),
  notificationRead: (id: number) => post<{ success: boolean }>(`/v1/mypage/notifications/${id}/read`, {}),
  notificationsReadAll: () => post<{ success: boolean }>("/v1/mypage/notifications/read-all", {}),
  notificationDelete: (id: number) => send<{ success: boolean }>("DELETE", `/v1/mypage/notifications/${id}`),
  myProfile: (ctx?: ServerCtx) => get<MyProfile>("/v1/mypage/profile", undefined, ctx),
  reviewForm: (q: ReviewFormQuery, ctx?: ServerCtx) => get<ReviewForm>("/v1/mypage/review-form", { ...q }, ctx),
  saveReview: (form: FormData) => send<{ success: boolean; id: number; already?: boolean; photoError?: boolean; message: string }>("POST", "/v1/mypage/reviews", form, 60000),
  saveProfile: (form: FormData) => send<{ success: boolean; photoError: string | null; photoUrl: string | null }>("POST", "/v1/mypage/profile", form, 30000),
  notificationSettings: (ctx?: ServerCtx) => get<{ settings: NotificationSettings }>("/v1/mypage/notification-settings", undefined, ctx),
  setNotificationSetting: (field: SettingKey, value: boolean) => post<{ success: boolean }>("/v1/mypage/notification-settings", { field, value: value ? "1" : "0" }),
  faq: (ctx?: ServerCtx) => get<FaqData>("/v1/faq", undefined, ctx),
  consultation: (id: number, ctx?: ServerCtx) => get<ConsultationDetail>(`/v1/mypage/consultations/${id}`, undefined, ctx),
  rerequestConsultation: (id: number, slot: string) => post<{ success: boolean }>(`/v1/mypage/consultations/${id}/rerequest`, { slot }),
  reviewDetail: (id: number, ctx?: ServerCtx) => get<ReviewDetail>(`/v1/reviews/${id}`, undefined, ctx),
  myReviews: (ctx?: ServerCtx) => get<{ writable: MyWritableReview[]; done: MyDoneReview[] }>("/v1/mypage/reviews", undefined, ctx),
  toggleWishlist: (id: number) => post<WishlistState & { message: string }>("/v1/wishlist/toggle", { type: "hospital", targetId: id }),
  consult: (input: ConsultationInput) => post<{ success: boolean; id: number; message: string }>("/v1/consultations", input),
  me: () => get<{ loggedIn: boolean; user: { id: string; no: number; name: string; nickname: string; level: number; phone: string } | null; unreadNotifications: number }>("/v1/me"),
  hospitals: (p: HospitalListParams = {}, ctx?: ServerCtx) => get<HospitalPage>("/v1/hospitals", { ...p, limit: p.limit ?? 20 }, ctx),
  searchView: (id: number, ctx?: ServerCtx) => get<SearchView>(`/v1/hospitals/${id}/search-view`, undefined, ctx),
  events: (p: { category?: string; filter?: string } = {}, ctx?: ServerCtx) => get<EventList>("/v1/events", p, ctx),
  event: (id: number, ctx?: ServerCtx) => get<EventDetail>(`/v1/events/${id}`, undefined, ctx),
  enterEvent: (id: number, input: EventEntryInput) => post<{ success: boolean; entryId: number; message: string }>(`/v1/events/${id}/entries`, input),
  community: (p: CommunityListParams = {}, ctx?: ServerCtx) => get<CommunityList>("/v1/community", { tab: p.tab, category: p.category, region: p.region, q: p.q, page: p.page && p.page > 1 ? p.page : undefined }, ctx),
  /** `view: true` only for the server render of the page (counts one view, like PHP). */
  communityPost: (id: number, o: { view?: boolean } = {}, ctx?: ServerCtx) => get<CommunityPost>(`/v1/community/${id}`, o.view ? { view: 1 } : undefined, ctx),
  toggleCommunityLike: (id: number) => post<{ success: boolean; liked: boolean; count: number }>(`/v1/community/${id}/like`, {}),
  addCommunityComment: (id: number, content: string, parentId: number) => post<{ success: boolean; id: number }>(`/v1/community/${id}/comments`, { content, parentId }),
  communityForm: (id?: number, ctx?: ServerCtx) => get<CommunityForm>("/v1/community/form", id ? { id } : undefined, ctx),
  saveCommunityPost: (id: number | null, form: FormData) => send<{ success: boolean; id: number; message: string }>(id ? "PUT" : "POST", id ? `/v1/community/${id}` : "/v1/community", form, 60000),
  deleteCommunityPost: (id: number) => send<{ success: boolean }>("DELETE", `/v1/community/${id}`),
  reportContent: (contentType: ContentType, contentId: number, reasons: string[], reasonDetail: string) =>
    post<{ success: boolean; message: string }>("/v1/community/reports", { contentType, contentId, reasons, reasonDetail }),
  blockAuthor: (contentType: ContentType, contentId: number) => post<{ success: boolean; message: string }>("/v1/community/blocks", { contentType, contentId }),
  blockedUsers: () => get<{ items: BlockedUser[] }>("/v1/community/blocks"),
  unblockUser: (rowId: number) => send<{ success: boolean }>("DELETE", `/v1/community/blocks/${rowId}`),
  toggleReviewLike: (id: number) => post<{ success: boolean; liked: boolean; count: number }>(`/v1/community/reviews/${id}/like`, {}),
  setCommunityCommentLike: (id: number, commentId: number, liked: boolean) => post<{ success: boolean; liked: boolean; count: number }>(`/v1/community/${id}/comments/${commentId}/like`, { liked }),
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
  mypage: ["mypage"] as const,
  myWishlist: ["mypage", "wishlist"] as const,
  myConsultations: ["mypage", "consultations"] as const,
  myReviews: ["mypage", "reviews"] as const,
  faq: ["faq"] as const,
  myProfile: ["mypage", "profile"] as const,
  notificationSettings: ["mypage", "notification-settings"] as const,
  myNotifications: (type: string) => ["mypage", "notifications", type] as const,
  myBookings: ["mypage", "bookings"] as const,
  bookingDetail: (id: number) => ["mypage", "booking", id] as const,
  consultation: (id: number) => ["mypage", "consultation", id] as const,
  reviewDetail: (id: number) => ["reviewDetail", id] as const,
  home: ["home"] as const,
  events: (category: string, raffle: boolean) => ["events", category, raffle] as const,
  event: (id: number) => ["event", id] as const,
  community: (p: CommunityListParams) => ["community", p.tab || "latest", p.category || "", p.region || "", p.q || "", p.page || 1] as const,
  communityPost: (id: number) => ["communityPost", id] as const,
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
