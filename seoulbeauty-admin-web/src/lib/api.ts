import { BASE_PATH } from "./config";

/** "all": every hospital (super admin). "own": only the hospital the account belongs to. */
export type Scope = "all" | "own";

export type Admin = {
  id: number;
  username: string;
  name: string;
  role: string;
  /** The hospital a hospital-role account belongs to; null for everyone else. */
  hospitalId: number | null;
  scope: Scope;
};

/** Which pages of this panel the server has switched on. */
export type Features = { hospitals: boolean };

export type Me = { admin: Admin; features: Features };

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
  members: {
    total: number;
    today: number;
    last7d: number;
    last30d: number;
    /** accounts that left the app (not counted in total) */
    withdrawn: number;
    /** active admin accounts */
    admins: number;
    /** how many of those admins are also a current member (counted once in the overall figure) */
    adminsAlsoMembers: number;
  };
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
/** Text that may arrive as a number (ids, phone numbers stored as digits). */
const text = (v: unknown): string => (typeof v === "string" ? v : typeof v === "number" && Number.isFinite(v) ? String(v) : "");
const bool = (v: unknown): boolean => v === true || v === 1 || v === "1";
/** A switch that counts as on when the server sends nothing for it. */
const boolOr = (v: unknown, fallback: boolean): boolean => (v === null || v === undefined ? fallback : bool(v));
const list = (v: unknown): unknown[] => (Array.isArray(v) ? v : []);
const numOrNull = (v: unknown): number | null => {
  if (v === null || v === undefined || v === "") return null;
  const n = typeof v === "string" ? Number(v) : v;
  return typeof n === "number" && Number.isFinite(n) ? n : null;
};
/** A list of names; an entry may also be an object with a `name`. Empty entries are dropped. */
const names = (v: unknown): string[] =>
  list(v)
    .map((item) => (typeof item === "string" ? item : text(obj(item).name)).trim())
    .filter((item) => item !== "");

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

/**
 * Who is signed in and what the server lets them open. An older server that does not send the newer
 * fields is read the safe way: only a super admin sees everything, and the hospitals page counts as off.
 */
export async function fetchMe(signal?: AbortSignal): Promise<ApiResult<Me>> {
  const res = await getJson("/v1/me", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const a = obj(d.admin);
  const role = str(a.role);
  const hospitalId = num(a.hospitalId);
  const scope: Scope = a.scope === "all" || a.scope === "own" ? a.scope : role === "super_admin" ? "all" : "own";
  return {
    ok: true,
    data: {
      admin: { id: num(a.id), username: str(a.username), name: str(a.name), role, hospitalId: hospitalId > 0 ? hospitalId : null, scope },
      features: { hospitals: obj(d.features).hospitals === true },
    },
  };
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
      members: {
        total: num(members.total),
        today: num(members.today),
        last7d: num(members.last7d),
        last30d: num(members.last30d),
        withdrawn: num(members.withdrawn),
        admins: num(members.admins),
        adminsAlsoMembers: num(members.adminsAlsoMembers),
      },
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

// ───────── Hospitals ─────────

export const AD_GRADES = ["S", "A", "B", "C", "D", "E", "none"] as const;
export type AdGrade = (typeof AD_GRADES)[number];
const adGrade = (v: unknown): AdGrade => (AD_GRADES as readonly unknown[]).includes(v) ? (v as AdGrade) : "none";

export const PER_PAGE_OPTIONS = [10, 20, 50] as const;
export const DEFAULT_PER_PAGE = 20;
export type ActiveFilter = "all" | "1" | "0";

export type HospitalListQuery = {
  search: string;
  /** 0 = every treatment. */
  treatmentId: number;
  active: ActiveFilter;
  page: number;
  perPage: number;
};

export type HospitalListItem = {
  id: number;
  name: string;
  address: string;
  phone: string;
  categories: string[];
  adGrade: AdGrade;
  isActive: boolean;
  rating: number;
  reviewCount: number;
  showRating: boolean;
  hasUnreviewedChanges: boolean;
  /** "YYYY-MM-DD HH:MM:SS" or "" */
  createdAt: string;
};

export type HospitalList = {
  scope: Scope;
  /** Inside the admin's scope, approved hospitals only, not affected by the filters. */
  stats: { total: number; active: number; inactive: number; unreviewed: number };
  /** Number of hospitals that match the filters. */
  total: number;
  page: number;
  perPage: number;
  items: HospitalListItem[];
};

export async function fetchHospitals(query: HospitalListQuery, signal?: AbortSignal): Promise<ApiResult<HospitalList>> {
  const params = new URLSearchParams();
  if (query.search) params.set("search", query.search);
  if (query.treatmentId > 0) params.set("treatmentId", String(query.treatmentId));
  if (query.active !== "all") params.set("active", query.active);
  params.set("page", String(query.page));
  params.set("perPage", String(query.perPage));
  const res = await getJson(`/v1/hospitals?${params.toString()}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const stats = obj(d.stats);
  return {
    ok: true,
    data: {
      scope: d.scope === "all" ? "all" : "own",
      stats: { total: num(stats.total), active: num(stats.active), inactive: num(stats.inactive), unreviewed: num(stats.unreviewed) },
      total: num(d.total),
      page: Math.max(1, num(d.page)),
      perPage: num(d.perPage) > 0 ? num(d.perPage) : query.perPage,
      items: list(d.items).map((raw) => {
        const h = obj(raw);
        return {
          id: num(h.id),
          name: str(h.name),
          address: str(h.address),
          phone: text(h.phone),
          categories: names(h.categories),
          adGrade: adGrade(h.adGrade),
          isActive: bool(h.isActive),
          rating: num(h.rating),
          reviewCount: num(h.reviewCount),
          showRating: boolOr(h.showRating, true),
          hasUnreviewedChanges: bool(h.hasUnreviewedChanges),
          createdAt: str(h.createdAt),
        };
      }),
    },
  };
}

export type TreatmentGroup = { category: string; items: { id: number; name: string }[] };

/** Treatments that at least one hospital in the admin's scope offers, grouped by category. */
export async function fetchTreatmentOptions(signal?: AbortSignal): Promise<ApiResult<TreatmentGroup[]>> {
  const res = await getJson("/v1/hospitals/treatment-options", signal);
  if (!res.ok) return res;
  const groups = list(obj(res.data).groups)
    .map((raw) => {
      const g = obj(raw);
      const items = list(g.items)
        .map((item) => ({ id: num(obj(item).id), name: text(obj(item).name) }))
        .filter((item) => item.id > 0 && item.name !== "");
      return { category: str(g.category), items };
    })
    .filter((g) => g.items.length > 0);
  return { ok: true, data: groups };
}

export type HospitalImage = { id: number; url: string; type: string; sortOrder: number; isActive: boolean };

export type HospitalDoctor = {
  id: number;
  name: string;
  specialty: string;
  /** Years of experience. */
  experience: number;
  qualification: string;
  bio: string;
  photoUrl: string;
  status: string;
};

export type HospitalRevision = { id: number; field: string; oldValue: string; newValue: string; changedAt: string };

export type HospitalDetail = {
  hospital: {
    id: number;
    name: string;
    ownerName: string;
    businessNumber: string;
    introduction: string;
    address: string;
    phone: string;
    email: string;
    contactName: string;
    contactPhone: string;
    website: string;
    snsUrl: string;
    kakaoChannelUrl: string;
    categories: string[];
    badges: string[];
    treatmentTags: string[];
    subwayNames: string[];
    /** Weekday keys ("월" … "일") plus lunch and special-date entries, as stored. */
    businessHours: Record<string, unknown>;
    latitude: number | null;
    longitude: number | null;
    status: string;
    isActive: boolean;
    adGrade: AdGrade;
    rating: number;
    reviewCount: number;
    showRating: boolean;
    hasUnreviewedChanges: boolean;
    mainImageUrl: string;
    profileImageUrl: string;
    appliedBy: string;
    appliedAt: string;
    createdAt: string;
    updatedAt: string;
  };
  /** Only whether each document was handed in; the files themselves stay in the old panel. */
  documents: { business: boolean; medical: boolean };
  images: HospitalImage[];
  doctors: HospitalDoctor[];
  /** Changes made by the applicant that nobody has reviewed yet. */
  revisions: HospitalRevision[];
};

export async function fetchHospital(id: number, signal?: AbortSignal): Promise<ApiResult<HospitalDetail>> {
  const res = await getJson(`/v1/hospitals/${encodeURIComponent(String(id))}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const h = obj(d.hospital);
  const documents = obj(d.documents);
  return {
    ok: true,
    data: {
      hospital: {
        id: num(h.id),
        name: str(h.name),
        ownerName: str(h.ownerName),
        businessNumber: text(h.businessNumber),
        introduction: str(h.introduction),
        address: str(h.address),
        phone: text(h.phone),
        email: str(h.email),
        contactName: str(h.contactName),
        contactPhone: text(h.contactPhone),
        website: str(h.website),
        snsUrl: str(h.snsUrl),
        kakaoChannelUrl: str(h.kakaoChannelUrl),
        categories: names(h.categories),
        badges: names(h.badges),
        treatmentTags: names(h.treatmentTags),
        subwayNames: names(h.subwayNames),
        businessHours: Array.isArray(h.businessHours) ? {} : obj(h.businessHours),
        latitude: numOrNull(h.latitude),
        longitude: numOrNull(h.longitude),
        status: str(h.status),
        isActive: bool(h.isActive),
        adGrade: adGrade(h.adGrade),
        rating: num(h.rating),
        reviewCount: num(h.reviewCount),
        showRating: boolOr(h.showRating, true),
        hasUnreviewedChanges: bool(h.hasUnreviewedChanges),
        mainImageUrl: str(h.mainImageUrl),
        profileImageUrl: str(h.profileImageUrl),
        appliedBy: text(h.appliedBy),
        appliedAt: str(h.appliedAt),
        createdAt: str(h.createdAt),
        updatedAt: str(h.updatedAt),
      },
      documents: { business: bool(documents.business), medical: bool(documents.medical) },
      images: list(d.images)
        .map((raw) => {
          const i = obj(raw);
          return { id: num(i.id), url: str(i.url), type: str(i.type), sortOrder: num(i.sortOrder), isActive: bool(i.isActive) };
        })
        .filter((i) => i.url.trim() !== ""),
      doctors: list(d.doctors).map((raw) => {
        const doc = obj(raw);
        return {
          id: num(doc.id),
          name: str(doc.name),
          specialty: str(doc.specialty),
          experience: num(doc.experience),
          qualification: str(doc.qualification),
          bio: str(doc.bio),
          photoUrl: str(doc.photoUrl),
          status: str(doc.status),
        };
      }),
      revisions: list(d.revisions).map((raw) => {
        const r = obj(raw);
        return { id: num(r.id), field: str(r.field), oldValue: text(r.oldValue), newValue: text(r.newValue), changedAt: str(r.changedAt) };
      }),
    },
  };
}
