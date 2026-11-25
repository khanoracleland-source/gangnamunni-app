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

/**
 * Which pages of this panel the server has switched on. `hospitalsWrite`: hospital data may be changed here (needs `hospitals` on as well).
 * `hospitalsDelete`: a super admin may delete a hospital for good; `archive`: the archive of deleted hospitals is open (the same switch).
 * `treatmentUnits`: the price units page is open (read); `treatmentUnitsWrite`: units may be added and removed here.
 * `treatments`: the treatment master page is open (read); `treatmentsWrite`: it may be changed here; `treatmentsImages`: image files may be uploaded for it.
 */
export type Features = { hospitals: boolean; hospitalsWrite: boolean; hospitalsDelete: boolean; hospitalsReview: boolean; hospitalsOrder: boolean; doctors: boolean; doctorsWrite: boolean; doctorsOrder: boolean; doctorsDelete: boolean; hospitalsImages: boolean; hospitalsDocuments: boolean; hospitalsDoctors: boolean; hospitalsTreatments: boolean; hospitalsTreatmentsRemove: boolean; hospitalsTreatmentsPrices: boolean; hospitalsTreatmentsQuestions: boolean; hospitalsTreatmentsImages: boolean; archive: boolean; treatments: boolean; treatmentsWrite: boolean; treatmentsImages: boolean; treatmentUnits: boolean; treatmentUnitsWrite: boolean; hospitalApply: boolean; admins: boolean; adminsWrite: boolean; bookings: boolean; bookingsWrite: boolean; consultations: boolean; consultationsWrite: boolean; payments: boolean; moderation: boolean; moderationWrite: boolean; moderationEdit: boolean; moderationDelete: boolean; banners: boolean; bannersWrite: boolean; categoryBoard: boolean; categoryBoardWrite: boolean };

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
const isObj = (v: unknown): boolean => v !== null && typeof v === "object" && !Array.isArray(v);
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
      features: {
        hospitals: obj(d.features).hospitals === true,
        hospitalsWrite: obj(d.features).hospitalsWrite === true,
        hospitalsDelete: obj(d.features).hospitalsDelete === true,
        hospitalsReview: obj(d.features).hospitalsReview === true,
        hospitalsOrder: obj(d.features).hospitalsOrder === true,
        doctors: obj(d.features).doctors === true,
        doctorsWrite: obj(d.features).doctorsWrite === true,
        doctorsOrder: obj(d.features).doctorsOrder === true,
        doctorsDelete: obj(d.features).doctorsDelete === true,
        hospitalsImages: obj(d.features).hospitalsImages === true,
        hospitalsDocuments: obj(d.features).hospitalsDocuments === true,
        hospitalsDoctors: obj(d.features).hospitalsDoctors === true,
        hospitalsTreatments: obj(d.features).hospitalsTreatments === true,
        hospitalsTreatmentsRemove: obj(d.features).hospitalsTreatmentsRemove === true,
        hospitalsTreatmentsPrices: obj(d.features).hospitalsTreatmentsPrices === true,
        hospitalsTreatmentsQuestions: obj(d.features).hospitalsTreatmentsQuestions === true,
        hospitalsTreatmentsImages: obj(d.features).hospitalsTreatmentsImages === true,
        archive: obj(d.features).archive === true,
        treatments: obj(d.features).treatments === true,
        treatmentsWrite: obj(d.features).treatmentsWrite === true,
        treatmentsImages: obj(d.features).treatmentsImages === true,
        treatmentUnits: obj(d.features).treatmentUnits === true,
        treatmentUnitsWrite: obj(d.features).treatmentUnitsWrite === true,
        hospitalApply: obj(d.features).hospitalApply === true,
        admins: obj(d.features).admins === true,
        adminsWrite: obj(d.features).adminsWrite === true,
        bookings: obj(d.features).bookings === true,
        bookingsWrite: obj(d.features).bookingsWrite === true,
        consultations: obj(d.features).consultations === true,
        consultationsWrite: obj(d.features).consultationsWrite === true,
        payments: obj(d.features).payments === true,
        moderation: obj(d.features).moderation === true,
        moderationWrite: obj(d.features).moderationWrite === true,
        moderationEdit: obj(d.features).moderationEdit === true,
        moderationDelete: obj(d.features).moderationDelete === true,
        banners: obj(d.features).banners === true,
        bannersWrite: obj(d.features).bannersWrite === true,
        categoryBoard: obj(d.features).categoryBoard === true,
        categoryBoardWrite: obj(d.features).categoryBoardWrite === true,
      },
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
  /** Only applications waiting for approval (super admin). Never sent together with `active`. */
  pending: boolean;
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
  stats: { total: number; active: number; inactive: number; pending: number };
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
  if (query.pending) params.set("pending", "1");
  else if (query.active !== "all") params.set("active", query.active);
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
      stats: { total: num(stats.total), active: num(stats.active), inactive: num(stats.inactive), pending: num(stats.pending) },
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
  /** Only whether each document was handed in (null when the server did not send it); the files open in the old panel. */
  documents: { business: boolean; medical: boolean } | null;
  /** The server lets this admin upload, replace and delete the documents of this hospital. */
  documentsEditable: boolean;
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
      documents: isObj(d.documents) ? { business: bool(documents.business), medical: bool(documents.medical) } : null,
      documentsEditable: d.documentsEditable === true,
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

// ───────── Hospital changes ─────────

/** The hospital fields a PATCH may carry; only the ones that changed are sent. The ad grade is not among them. */
export type HospitalFieldsPatch = Partial<{
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
  categories: string[];
  badges: string[];
  treatmentTags: string[];
  subwayNames: string[];
  businessHours: Record<string, unknown>;
  latitude: number | null;
  longitude: number | null;
  showRating: boolean;
}>;

/**
 * A refused change. `status` 0 = no answer at all; 400 carries per-field codes in `fields`
 * (required, too_long, bad_format, bad_url, duplicate_phone, not_allowed); 409 carries the stored `updatedAt`.
 */
export type WriteFailure = {
  ok: false;
  status: number;
  fields: Record<string, string>;
  updatedAt: string;
  /** The server's error code ("invalid", "blocked", "conflict", ...); "" when it sent none. */
  error: string;
  /** A 400 that names a row of a list (the position in the sent list) and a field of that row. */
  index?: number;
  field?: string;
  /** Present on a 409 "blocked" answer to a delete. */
  blockers: DeleteBlocker[];
  deletableAfter: string;
  /** Treatment master: why a field was refused ("required", "too_long", "bad_format", "range"). */
  reason?: string;
  /** Treatment master: on a 409 "in_use", how many hospital assignments still point at the treatment. */
  hospitalCount?: number;
  /** Admin accounts: on a 429, how long the check stays locked. */
  retryAfterSeconds?: number;
};
export type WriteResult<T> = { ok: true; data: T } | WriteFailure;

/** Sends a change through the same-origin /api rewrite. The two headers are what the server demands of every writing request. */
async function sendJson(method: "PATCH" | "POST" | "PUT" | "DELETE", path: string, body: unknown = {}): Promise<WriteResult<Json>> {
  try {
    const res = await fetch(`${BASE_PATH}/api${path}`, {
      method,
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", "Content-Type": "application/json", "X-Requested-With": "admin-next" },
      body: JSON.stringify(body),
    });
    let data: unknown = null;
    try {
      data = await res.json();
    } catch {
      // An empty or non-JSON body: the status alone says what happened.
    }
    const d = obj(data);
    if (!res.ok) {
      const fields: Record<string, string> = {};
      for (const [name, code] of Object.entries({ ...obj(d.errors), ...obj(d.fields) })) if (typeof code === "string") fields[name] = code;
      return {
        ok: false,
        status: res.status,
        fields,
        updatedAt: str(d.updatedAt),
        error: str(d.error),
        ...(typeof d.index === "number" ? { index: d.index } : {}),
        ...(typeof d.field === "string" ? { field: d.field } : {}),
        ...(typeof d.reason === "string" ? { reason: d.reason } : {}),
        ...(typeof d.retryAfterSeconds === "number" ? { retryAfterSeconds: d.retryAfterSeconds } : {}),
        ...(typeof d.hospitalCount === "number" ? { hospitalCount: d.hospitalCount } : typeof d.count === "number" ? { hospitalCount: d.count } : {}),
        blockers: blockers(d.blockers),
        deletableAfter: str(d.deletableAfter),
      };
    }
    return { ok: true, data: d };
  } catch {
    return { ok: false, status: 0, fields: {}, updatedAt: "", error: "", blockers: [], deletableAfter: "" };
  }
}

/** Saves the changed fields. `expectedUpdatedAt` is the `updatedAt` the page was loaded with: if the hospital changed since, the answer is 409. */
export async function patchHospital(
  id: number,
  expectedUpdatedAt: string | null,
  fields: HospitalFieldsPatch,
): Promise<WriteResult<{ updatedAt: string }>> {
  const res = await sendJson("PATCH", `/v1/hospitals/${encodeURIComponent(String(id))}`, { expectedUpdatedAt, fields });
  return res.ok ? { ok: true, data: { updatedAt: str(res.data.updatedAt) } } : res;
}

// ───────── Hospital images ─────────

/** What the images card shows: every stored image, the current main image address, and how many detail slots are used. */
export type HospitalImageSet = { items: HospitalImage[]; mainUrl: string | null; detailCount: number; detailLimit: number };

function imageSet(v: unknown): HospitalImageSet {
  const d = obj(v);
  const limit = num(d.detailLimit);
  return {
    items: list(d.items)
      .map((raw) => {
        const i = obj(raw);
        return { id: num(i.id), url: str(i.url), type: str(i.type), sortOrder: num(i.sortOrder), isActive: bool(i.isActive) };
      })
      .filter((i) => i.url.trim() !== ""),
    mainUrl: str(d.mainUrl).trim() === "" ? null : str(d.mainUrl),
    detailCount: num(d.detailCount),
    detailLimit: limit > 0 ? limit : 10,
  };
}

export async function hospitalImages(id: number, signal?: AbortSignal): Promise<ApiResult<HospitalImageSet>> {
  const res = await getJson(`/v1/hospitals/${encodeURIComponent(String(id))}/images`, signal);
  return res.ok ? { ok: true, data: imageSet(res.data) } : res;
}

export type ImageUploadFiles = { main?: File; interior: File[] };

export type ImageUploadFailure = {
  ok: false;
  /** 0 = no answer (network down, proxy error page, request cancelled). */
  status: number;
  /** "no_files", "invalid_type", "too_large", "too_many", "upload_failed", ...; "" when the server sent none. */
  error: string;
  /** Which of the two file groups the error is about; "" when unknown. */
  field: "main" | "interior" | "";
  /** On "too_many": how many more detail images the hospital can take. */
  remaining: number | null;
};
export type ImageUploadResult = { ok: true; data: HospitalImageSet & { added: number; updatedAt: string } } | ImageUploadFailure;

/** Sends the chosen files as one multipart request. The browser sets Content-Type itself (it must add the boundary). */
export async function uploadHospitalImages(id: number, files: ImageUploadFiles, signal?: AbortSignal): Promise<ImageUploadResult> {
  const body = new FormData();
  if (files.main) body.append("main", files.main);
  for (const file of files.interior) body.append("interior", file);
  try {
    const res = await fetch(`${BASE_PATH}/api/v1/hospitals/${encodeURIComponent(String(id))}/images`, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", "X-Requested-With": "admin-next" },
      body,
      signal,
    });
    let data: unknown = null;
    try {
      data = await res.json();
    } catch {
      // An empty or non-JSON body: the status alone says what happened.
    }
    const d = obj(data);
    if (!res.ok) {
      const remaining = numOrNull(d.remaining);
      return { ok: false, status: res.status, error: str(d.error), field: d.field === "main" || d.field === "interior" ? d.field : "", remaining };
    }
    return { ok: true, data: { ...imageSet(d), added: num(d.added), updatedAt: str(d.updatedAt) } };
  } catch {
    return { ok: false, status: 0, error: "", field: "", remaining: null };
  }
}

// ───────── Hospital documents ─────────

export type DocumentKey = "business" | "medical";
export type HospitalDocuments = { business: boolean; medical: boolean };
export type DocumentFailure = {
  ok: false;
  /** 0 = no answer (network down, proxy error page, request cancelled). */
  status: number;
  /** "file_required", "invalid_file", "too_large", "upload_failed", "no_document", "forbidden_documents", ...; "" when the server sent none. */
  error: string;
};
export type DocumentResult = { ok: true; documents: HospitalDocuments } | DocumentFailure;

const documentPath = (id: number, doc: DocumentKey): string => `/v1/hospitals/${encodeURIComponent(String(id))}/documents/${doc}`;

function documentsOf(d: Json): HospitalDocuments {
  const documents = obj(d.documents);
  return { business: bool(documents.business), medical: bool(documents.medical) };
}

/** Sends one document file as multipart. The browser sets Content-Type itself (it must add the boundary). */
export async function uploadHospitalDocument(id: number, doc: DocumentKey, file: File, signal?: AbortSignal): Promise<DocumentResult> {
  const body = new FormData();
  body.append("file", file);
  try {
    const res = await fetch(`${BASE_PATH}/api${documentPath(id, doc)}`, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", "X-Requested-With": "admin-next" },
      body,
      signal,
    });
    let data: unknown = null;
    try {
      data = await res.json();
    } catch {
      // An empty or non-JSON body: the status alone says what happened.
    }
    const d = obj(data);
    if (!res.ok) return { ok: false, status: res.status, error: str(d.error) };
    return { ok: true, documents: documentsOf(d) };
  } catch {
    return { ok: false, status: 0, error: "" };
  }
}

/** Removes the stored document. The answer is the fresh document state. */
export async function deleteHospitalDocument(id: number, doc: DocumentKey): Promise<DocumentResult> {
  const res = await sendJson("DELETE", documentPath(id, doc));
  return res.ok ? { ok: true, documents: documentsOf(res.data) } : { ok: false, status: res.status, error: res.error };
}

// ───────── Hospital doctors ─────────

/** What the doctors card shows: every stored doctor and how many can be added in one go. */
export type HospitalDoctorSet = { items: HospitalDoctor[]; count: number; addLimit: number };

function doctorSet(v: unknown): HospitalDoctorSet {
  const d = obj(v);
  const limit = num(d.addLimit);
  const items = list(d.items)
    .map((raw): HospitalDoctor => {
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
    })
    .filter((doc) => doc.id > 0);
  return { items, count: items.length, addLimit: limit > 0 ? limit : 20 };
}

export async function hospitalDoctors(id: number, signal?: AbortSignal): Promise<ApiResult<HospitalDoctorSet>> {
  const res = await getJson(`/v1/hospitals/${encodeURIComponent(String(id))}/doctors`, signal);
  return res.ok ? { ok: true, data: doctorSet(res.data) } : res;
}

/** One doctor to add. `photo` is the position of the doctor's file among the sent photo files, or null. */
export type DoctorPayload = {
  name: string;
  specialty: string;
  experience: number;
  qualification: string;
  bio: string;
  photo: number | null;
};

export type DoctorAddFailure = {
  ok: false;
  /** 0 = no answer (network down, proxy error page, request cancelled). */
  status: number;
  /** "no_doctors", "too_many", "invalid_field", "invalid_type", "too_large", "upload_failed", ...; "" when the server sent none. */
  error: string;
  /** Position of the doctor in the sent list the error is about; null when the error is about the whole request. */
  index: number | null;
  /** The input of that doctor the error is about; "" when unknown. */
  field: string;
};
export type DoctorAddResult = { ok: true; data: HospitalDoctorSet & { added: number } } | DoctorAddFailure;

/**
 * Sends the doctors as one multipart request: a `doctors` JSON text and one repeated `photo` file field.
 * The browser sets Content-Type itself (it must add the boundary).
 */
export async function addHospitalDoctors(id: number, doctors: DoctorPayload[], photos: File[], signal?: AbortSignal): Promise<DoctorAddResult> {
  const body = new FormData();
  body.append("doctors", JSON.stringify(doctors));
  for (const photo of photos) body.append("photo", photo);
  try {
    const res = await fetch(`${BASE_PATH}/api/v1/hospitals/${encodeURIComponent(String(id))}/doctors`, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", "X-Requested-With": "admin-next" },
      body,
      signal,
    });
    let data: unknown = null;
    try {
      data = await res.json();
    } catch {
      // An empty or non-JSON body: the status alone says what happened.
    }
    const d = obj(data);
    if (!res.ok) {
      const index = numOrNull(d.index);
      return { ok: false, status: res.status, error: str(d.error), index: index !== null && index >= 0 ? Math.floor(index) : null, field: str(d.field) };
    }
    return { ok: true, data: { ...doctorSet(d), added: num(d.added) } };
  } catch {
    return { ok: false, status: 0, error: "", index: null, field: "" };
  }
}

// ───────── Hospital treatments ─────────

/** One price option of an assigned treatment. Prices are whole won; 0 means "not entered". */
export type TreatmentPriceOption = {
  optionName: string;
  bodyPart: string;
  dosageValue: string;
  dosageUnit: string;
  regularPrice: number;
  price: number;
  isRepresentative: boolean;
};

/** A treatment assigned to the hospital. `id` is the assignment (hospital treatment) id, `treatmentId` the catalogue entry. */
export type HospitalTreatment = {
  id: number;
  treatmentId: number;
  name: string;
  hashtag: string;
  categoryId: number | null;
  categoryName: string | null;
  isPromoted: boolean;
  note: string;
  registeredBy: "admin" | "hospital";
  minAge: number | null;
  priceOptions: TreatmentPriceOption[];
  imageCount: number;
  pendingImageCount: number;
  questionCount: number;
  /** The question texts in their order. Only sent to an admin who may edit them while the question window is on; otherwise undefined. */
  questions?: string[];
};

/** A catalogue treatment that can still be assigned. */
export type AvailableTreatment = { id: number; name: string; hashtag: string; categoryId: number | null; categoryName: string | null };

/**
 * What the treatments card shows. The three switches are only sent by the list read: an answer to a change leaves them
 * undefined and the card keeps the ones it already has.
 */
export type HospitalTreatmentSet = {
  items: HospitalTreatment[];
  count: number;
  promotedCount: number;
  available: AvailableTreatment[];
  canManage?: boolean;
  minAgeSupported?: boolean;
  removeEnabled?: boolean;
  /** The price window is switched on. */
  pricesEnabled?: boolean;
  /** The units the price window offers (only sent to an admin who may edit prices). */
  priceUnits?: string[];
  /** The body part groups of the price window, from the body part tables (empty or missing: the window uses its own list). */
  priceBodyParts?: { label: string; parts: string[] }[];
  /** The question window is switched on (and the question texts were read). */
  questionsEnabled?: boolean;
  /** The detail image window is switched on. */
  imagesEnabled?: boolean;
};

function treatmentItem(raw: unknown): HospitalTreatment {
  const d = obj(raw);
  return {
    id: num(d.id),
    treatmentId: num(d.treatmentId),
    name: str(d.name),
    hashtag: str(d.hashtag),
    categoryId: numOrNull(d.categoryId),
    categoryName: typeof d.categoryName === "string" ? d.categoryName : null,
    isPromoted: bool(d.isPromoted),
    note: str(d.note),
    registeredBy: d.registeredBy === "hospital" ? "hospital" : "admin",
    minAge: numOrNull(d.minAge),
    priceOptions: list(d.priceOptions).map((o): TreatmentPriceOption => {
      const p = obj(o);
      return {
        optionName: str(p.optionName),
        bodyPart: str(p.bodyPart),
        dosageValue: text(p.dosageValue),
        dosageUnit: str(p.dosageUnit),
        regularPrice: num(p.regularPrice),
        price: num(p.price),
        isRepresentative: bool(p.isRepresentative),
      };
    }),
    imageCount: num(d.imageCount),
    pendingImageCount: num(d.pendingImageCount),
    questionCount: num(d.questionCount),
    questions: Array.isArray(d.questions) ? d.questions.filter((q): q is string => typeof q === "string") : undefined,
  };
}

function treatmentSet(v: unknown): HospitalTreatmentSet {
  const d = obj(v);
  const items = list(d.items).map(treatmentItem).filter((item) => item.id > 0);
  const available = list(d.available)
    .map((raw): AvailableTreatment => {
      const a = obj(raw);
      return { id: num(a.id), name: str(a.name), hashtag: str(a.hashtag), categoryId: numOrNull(a.categoryId), categoryName: typeof a.categoryName === "string" ? a.categoryName : null };
    })
    .filter((a) => a.id > 0);
  return {
    items,
    count: items.length,
    promotedCount: numOrNull(d.promotedCount) ?? items.filter((item) => item.isPromoted).length,
    available,
    canManage: d.canManage === undefined ? undefined : bool(d.canManage),
    minAgeSupported: d.minAgeSupported === undefined ? undefined : bool(d.minAgeSupported),
    removeEnabled: d.removeEnabled === undefined ? undefined : bool(d.removeEnabled),
    pricesEnabled: d.pricesEnabled === undefined ? undefined : bool(d.pricesEnabled),
    priceUnits: d.priceUnits === undefined ? undefined : list(d.priceUnits).filter((u): u is string => typeof u === "string" && u.trim() !== ""),
    priceBodyParts: d.priceBodyParts === undefined ? undefined : list(d.priceBodyParts).flatMap((g) => {
      const o = g as { label?: unknown; parts?: unknown };
      const parts = list(o.parts).filter((p): p is string => typeof p === "string" && p.trim() !== "");
      return typeof o.label === "string" && o.label.trim() !== "" && parts.length > 0 ? [{ label: o.label, parts }] : [];
    }),
    questionsEnabled: d.questionsEnabled === undefined ? undefined : bool(d.questionsEnabled),
    imagesEnabled: d.imagesEnabled === undefined ? undefined : bool(d.imagesEnabled),
  };
}

const treatmentsPath = (id: number): string => `/v1/hospitals/${encodeURIComponent(String(id))}/treatments`;

/** The assigned treatments, the ones that can still be assigned and what this admin may do. Reads only. */
export async function hospitalTreatments(id: number, signal?: AbortSignal): Promise<ApiResult<HospitalTreatmentSet>> {
  const res = await getJson(treatmentsPath(id), signal);
  return res.ok ? { ok: true, data: treatmentSet(res.data) } : res;
}

/** Assigns catalogue treatments. The answer is the fresh list; `skipped` are the ones that were already assigned. */
export async function assignHospitalTreatments(id: number, treatmentIds: number[]): Promise<WriteResult<HospitalTreatmentSet & { added: number; skipped: number }>> {
  const res = await sendJson("POST", treatmentsPath(id), { treatmentIds });
  return res.ok ? { ok: true, data: { ...treatmentSet(res.data), added: num(res.data.added), skipped: num(res.data.skipped) } } : res;
}

/** Sets the exact promotion state and/or the minimum age (null = no limit) of one assignment. */
export async function patchHospitalTreatment(
  id: number,
  htId: number,
  fields: { isPromoted?: boolean; minAge?: number | null },
): Promise<WriteResult<{ item: HospitalTreatment | null; promotedCount: number | null }>> {
  const res = await sendJson("PATCH", `${treatmentsPath(id)}/${encodeURIComponent(String(htId))}`, fields);
  if (!res.ok) return res;
  const item = treatmentItem(res.data.item);
  return { ok: true, data: { item: item.id > 0 ? item : null, promotedCount: numOrNull(res.data.promotedCount) } };
}

/** One price option as the server takes it. `bodyParts` are the parts as separate names. */
export type TreatmentPriceInput = {
  optionName: string;
  bodyParts: string[];
  dosageValue: number;
  dosageUnit: string;
  regularPrice: number;
  price: number;
  isRepresentative: boolean;
};

/** Replaces all price options of one assignment. The answer carries the assignment with its new options. */
export async function saveTreatmentPrices(
  id: number,
  htId: number,
  options: TreatmentPriceInput[],
): Promise<WriteResult<{ item: HospitalTreatment | null; count: number | null; promotedCount: number | null }>> {
  const res = await sendJson("PUT", `${treatmentsPath(id)}/${encodeURIComponent(String(htId))}/prices`, { options });
  if (!res.ok) return res;
  const item = treatmentItem(res.data.item);
  return { ok: true, data: { item: item.id > 0 ? item : null, count: numOrNull(res.data.count), promotedCount: numOrNull(res.data.promotedCount) } };
}

/** Replaces all pre-treatment questions of one assignment (an empty list deletes them). The answer carries the assignment with its new questions. */
export async function saveTreatmentQuestions(
  id: number,
  htId: number,
  questions: string[],
): Promise<WriteResult<{ item: HospitalTreatment | null; count: number | null }>> {
  const res = await sendJson("PUT", `${treatmentsPath(id)}/${encodeURIComponent(String(htId))}/questions`, { questions });
  if (!res.ok) return res;
  const item = treatmentItem(res.data.item);
  return { ok: true, data: { item: item.id > 0 ? item : null, count: numOrNull(res.data.count) } };
}

// ───────── Treatment detail page images ─────────

export type TreatmentImageStatus = "pending" | "approved" | "rejected";

/** One detail image of a treatment. `url` is stored as the server keeps it (relative to the old panel's folder, or a full address). */
export type TreatmentImage = {
  id: number;
  url: string;
  caption: string;
  status: TreatmentImageStatus;
  uploadedBy: "admin" | "hospital";
  rejectReason: string;
  reviewedAt: string;
  reviewedBy: string;
  sortOrder: number;
};

/** Every image of one treatment in the order the server keeps them, whether the admin may approve / reject, and the treatment's own default image. */
export type TreatmentImageSet = { items: TreatmentImage[]; canReview: boolean; defaultImageUrl: string | null };

function treatmentImage(raw: unknown): TreatmentImage {
  const d = obj(raw);
  return {
    id: num(d.id),
    url: str(d.url),
    caption: str(d.caption),
    status: d.status === "pending" || d.status === "rejected" ? d.status : "approved",
    uploadedBy: d.uploadedBy === "hospital" ? "hospital" : "admin",
    rejectReason: str(d.rejectReason),
    reviewedAt: str(d.reviewedAt),
    reviewedBy: str(d.reviewedBy),
    sortOrder: num(d.sortOrder),
  };
}

const treatmentImageList = (v: unknown): TreatmentImage[] => list(v).map(treatmentImage).filter((i) => i.id > 0);

const treatmentImagesPath = (id: number, htId: number): string => `${treatmentsPath(id)}/${encodeURIComponent(String(htId))}/images`;

/** Reads the images of one treatment. Reads only. */
export async function treatmentImages(id: number, htId: number, signal?: AbortSignal): Promise<ApiResult<TreatmentImageSet>> {
  const res = await getJson(treatmentImagesPath(id, htId), signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const def = str(d.defaultImageUrl).trim();
  return { ok: true, data: { items: treatmentImageList(d.items), canReview: bool(d.canReview), defaultImageUrl: def === "" ? null : def } };
}

/** What a refused image request says: the status (0 = no answer) and the server's error code ("" when it sent none). */
export type TreatmentImageFailure = { ok: false; status: number; error: string };
export type TreatmentImageResult<T> = { ok: true; data: T } | TreatmentImageFailure;

/** What an image change answers: the changed row (when the action has one) and the fresh list. */
export type TreatmentImageChange = { item: TreatmentImage | null; items: TreatmentImage[] };

function imageChange(d: Json): TreatmentImageChange {
  const item = treatmentImage(d.item);
  return { item: item.id > 0 ? item : null, items: treatmentImageList(d.items) };
}

function imageFailure(res: Exclude<WriteResult<Json>, { ok: true }>): TreatmentImageFailure {
  return { ok: false, status: res.status, error: res.error };
}

/**
 * Adds one image: a file OR an address, plus a caption, as one multipart request. When a file is given the address is not sent.
 * The browser sets Content-Type itself (it must add the boundary).
 */
export async function addTreatmentImage(
  id: number,
  htId: number,
  input: { file: File | null; imageUrl: string; caption: string },
  signal?: AbortSignal,
): Promise<TreatmentImageResult<TreatmentImageChange>> {
  const body = new FormData();
  if (input.file) body.append("image", input.file);
  else body.append("imageUrl", input.imageUrl);
  body.append("caption", input.caption);
  try {
    const res = await fetch(`${BASE_PATH}/api${treatmentImagesPath(id, htId)}`, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", "X-Requested-With": "admin-next" },
      body,
      signal,
    });
    let data: unknown = null;
    try {
      data = await res.json();
    } catch {
      // An empty or non-JSON body: the status alone says what happened.
    }
    const d = obj(data);
    if (!res.ok) return { ok: false, status: res.status, error: str(d.error) };
    return { ok: true, data: imageChange(d) };
  } catch {
    return { ok: false, status: 0, error: "" };
  }
}

/** Deletes one image (the stored file goes too, when the server can). The answer is the fresh list. */
export async function deleteTreatmentImage(id: number, htId: number, imageId: number): Promise<TreatmentImageResult<TreatmentImageChange>> {
  const res = await sendJson("DELETE", `${treatmentImagesPath(id, htId)}/${encodeURIComponent(String(imageId))}`);
  return res.ok ? { ok: true, data: imageChange(res.data) } : imageFailure(res);
}

/** Moves one approved image one place up or down among the approved ones. The answer is the fresh list. */
export async function moveTreatmentImage(id: number, htId: number, imageId: number, direction: "up" | "down"): Promise<TreatmentImageResult<TreatmentImageChange>> {
  const res = await sendJson("POST", `${treatmentImagesPath(id, htId)}/${encodeURIComponent(String(imageId))}/move`, { direction });
  return res.ok ? { ok: true, data: imageChange(res.data) } : imageFailure(res);
}

/** Super admin only: approves a pending image, or rejects it with a reason. */
export async function reviewTreatmentImage(
  id: number,
  htId: number,
  imageId: number,
  decision: { decision: "approve" } | { decision: "reject"; reason: string },
): Promise<TreatmentImageResult<TreatmentImageChange>> {
  const res = await sendJson("POST", `${treatmentImagesPath(id, htId)}/${encodeURIComponent(String(imageId))}/review`, decision);
  return res.ok ? { ok: true, data: imageChange(res.data) } : imageFailure(res);
}

/** Takes one treatment off the hospital; its price options and image rows go with it. */
export async function removeHospitalTreatment(
  id: number,
  htId: number,
): Promise<WriteResult<HospitalTreatmentSet & { removed: boolean; deleted: { priceOptions: number; images: number } }>> {
  const res = await sendJson("DELETE", `${treatmentsPath(id)}/${encodeURIComponent(String(htId))}`);
  if (!res.ok) return res;
  const deleted = obj(res.data.deleted);
  return {
    ok: true,
    data: { ...treatmentSet(res.data), removed: res.data.removed !== false, deleted: { priceOptions: num(deleted.priceOptions), images: num(deleted.images) } },
  };
}

/** Super admin only. Sets the exact state asked for, so pressing twice never flips it back. */
export async function setHospitalActive(id: number, active: boolean): Promise<WriteResult<{ isActive: boolean; updatedAt: string }>> {
  const res = await sendJson("POST", `/v1/hospitals/${encodeURIComponent(String(id))}/active`, { active });
  return res.ok ? { ok: true, data: { isActive: bool(res.data.isActive), updatedAt: str(res.data.updatedAt) } } : res;
}

export type OrderBoardItem = { id: number; name: string; address: string; adGrade: AdGrade; position: number };
export type OrderBoard = { total: number; inactive: number; items: OrderBoardItem[] };

/** Super admin only. Every hospital that has a place in the app order, in that order (no paging). */
export async function fetchOrderBoard(signal?: AbortSignal): Promise<ApiResult<OrderBoard>> {
  const res = await getJson("/v1/hospitals/order", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return {
    ok: true,
    data: {
      total: num(d.total),
      inactive: num(d.inactive),
      items: list(d.items).map((raw) => {
        const h = obj(raw);
        return { id: num(h.id), name: str(h.name), address: str(h.address), adGrade: adGrade(h.adGrade), position: num(h.position) };
      }),
    },
  };
}

/** Super admin only. Moves one hospital one step up/down or to a place (1 = first); the server renumbers the rest. */
export async function moveHospital(id: number, move: { direction: "up" | "down" } | { position: number }): Promise<WriteResult<{ position: number; total: number; changed: number }>> {
  const res = await sendJson("POST", `/v1/hospitals/${encodeURIComponent(String(id))}/order`, move);
  return res.ok ? { ok: true, data: { position: num(res.data.position), total: num(res.data.total), changed: num(res.data.changed) } } : res;
}

/** Super admin only. Marks every change the hospital made so far as reviewed. */
export async function reviewHospitalRevisions(id: number): Promise<WriteResult<Record<string, never>>> {
  const res = await sendJson("POST", `/v1/hospitals/${encodeURIComponent(String(id))}/revisions/review`, {});
  return res.ok ? { ok: true, data: {} } : res;
}

/** Super admin only, pending applications only. 409 `already_reviewed`: somebody decided it first. */
export async function approveHospital(id: number): Promise<WriteResult<{ status: string }>> {
  const res = await sendJson("POST", `/v1/hospitals/${encodeURIComponent(String(id))}/approve`, {});
  return res.ok ? { ok: true, data: { status: str(res.data.status) } } : res;
}

/** Super admin only, pending applications only. `reason` is required (400 `reason_required` when empty); 409 `already_reviewed`. */
export async function rejectHospital(id: number, reason: string): Promise<WriteResult<{ status: string }>> {
  const res = await sendJson("POST", `/v1/hospitals/${encodeURIComponent(String(id))}/reject`, { reason });
  return res.ok ? { ok: true, data: { status: str(res.data.status) } } : res;
}

// ───────── Map ─────────

/** Which map the location picker draws. "osm" = no usable map key: the picker falls back to typing the coordinates. */
export type MapConfig = { provider: "kakao" | "osm"; key: string | null };

/**
 * The map provider and the browser key for the location picker. Only a 401 is reported as such (the session is gone);
 * any other failure or an odd answer reads as "osm", so the form always keeps working with the plain coordinate fields.
 */
export async function mapConfig(signal?: AbortSignal): Promise<ApiResult<MapConfig>> {
  const res = await getJson("/v1/map-config", signal);
  if (!res.ok) return res.status === 401 ? res : { ok: true, data: { provider: "osm", key: null } };
  const d = obj(res.data);
  const key = str(d.key).trim();
  return { ok: true, data: d.provider === "kakao" && key !== "" ? { provider: "kakao", key } : { provider: "osm", key: null } };
}

// ───────── Sales card ─────────

export type SalesPeriod = {
  /** "YYYY-MM-DD", KST */
  from: string;
  to: string;
  bookings: { created: number; cancelled: number; active: number };
  sales: { paid: number; refunded: number; net: number };
};

/** The current week (from Monday) and the current month (from the 1st), up to today. */
export type HospitalStats = { week: SalesPeriod; month: SalesPeriod };

function money(v: unknown): { paid: number; refunded: number; net: number } {
  const s = obj(v);
  return { paid: num(s.paid), refunded: num(s.refunded), net: num(s.net) };
}

function salesPeriod(v: unknown): SalesPeriod {
  const p = obj(v);
  const b = obj(p.bookings);
  return {
    from: str(p.from),
    to: str(p.to),
    bookings: { created: num(b.created), cancelled: num(b.cancelled), active: num(b.active) },
    sales: money(p.sales),
  };
}

export async function fetchHospitalStats(id: number, signal?: AbortSignal): Promise<ApiResult<HospitalStats>> {
  const res = await getJson(`/v1/hospitals/${encodeURIComponent(String(id))}/stats`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return { ok: true, data: { week: salesPeriod(d.week), month: salesPeriod(d.month) } };
}

// ───────── Deleting a hospital ─────────

/** Why a hospital cannot be deleted yet. A code this page does not know is kept as "other". */
export type DeleteBlocker =
  | { code: "upcoming_bookings"; count: number; lastAt: string }
  | { code: "open_consultations"; count: number; lastAt: string }
  | { code: "unsettled"; settlements: number; unsettledPayments: number }
  | { code: "other"; raw: string };

function blockers(v: unknown): DeleteBlocker[] {
  return list(v).map((raw): DeleteBlocker => {
    const b = obj(raw);
    const code = str(b.code);
    if (code === "upcoming_bookings") return { code, count: num(b.count), lastAt: str(b.lastAt) };
    if (code === "open_consultations") return { code, count: num(b.count), lastAt: str(b.lastAt) };
    if (code === "unsettled") return { code, settlements: num(b.settlements), unsettledPayments: num(b.unsettledPayments) };
    return { code: "other", raw: code };
  });
}

/** What is sealed into the archive: whole-period figures. */
export type ArchiveSummary = { bookings: number; cancelled: number; sales: { paid: number; refunded: number; net: number } };

export type DeleteCheck = {
  canDelete: boolean;
  name: string;
  blockers: DeleteBlocker[];
  /** "YYYY-MM-DD HH:MM:SS" or "": the earliest moment the date-based blockers are gone. */
  deletableAfter: string;
  stats: ArchiveSummary;
  willDelete: { images: number; doctors: number; treatments: number };
};

/** Super admin only. */
export async function fetchDeleteCheck(id: number, signal?: AbortSignal): Promise<ApiResult<DeleteCheck>> {
  const res = await getJson(`/v1/hospitals/${encodeURIComponent(String(id))}/delete-check`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const stats = obj(d.stats);
  const will = obj(d.willDelete);
  return {
    ok: true,
    data: {
      canDelete: d.canDelete === true,
      name: str(d.name),
      blockers: blockers(d.blockers),
      deletableAfter: str(d.deletableAfter),
      stats: { bookings: num(stats.bookings), cancelled: num(stats.cancelled), sales: money(stats.sales) },
      willDelete: { images: num(will.images), doctors: num(will.doctors), treatments: num(will.treatments) },
    },
  };
}

/**
 * Super admin only. Answers: 400 (`fields.confirmName` = "mismatch"), 409 with `error` "blocked" (see `blockers`)
 * or "conflict" (the hospital changed since the page was loaded).
 */
export async function deleteHospital(id: number, confirmName: string, expectedUpdatedAt: string | null): Promise<WriteResult<{ archiveId: number }>> {
  const res = await sendJson("POST", `/v1/hospitals/${encodeURIComponent(String(id))}/delete`, { confirmName, expectedUpdatedAt });
  return res.ok ? { ok: true, data: { archiveId: num(res.data.archiveId) } } : res;
}

// ───────── Archive of deleted hospitals ─────────

export type ArchiveItem = {
  id: number;
  hospitalId: number;
  name: string;
  deletedAt: string;
  deletedByName: string;
  bookingsTotal: number;
  bookingsCancelled: number;
  salesNet: number;
  /** A month has passed since the deletion and nobody has acknowledged it yet. */
  noticeDue: boolean;
  noticeAckedAt: string;
};

export type ArchiveList = { items: ArchiveItem[]; total: number; dueCount: number; page: number; perPage: number };

function archiveItem(v: unknown): ArchiveItem {
  const a = obj(v);
  return {
    id: num(a.id),
    hospitalId: num(a.hospitalId),
    name: str(a.name),
    deletedAt: str(a.deletedAt),
    deletedByName: str(a.deletedByName),
    bookingsTotal: num(a.bookingsTotal),
    bookingsCancelled: num(a.bookingsCancelled),
    salesNet: num(a.salesNet),
    noticeDue: bool(a.noticeDue),
    noticeAckedAt: str(a.noticeAckedAt),
  };
}

/** Super admin only. A 503 means the archive table has not been created yet. */
export async function fetchArchiveList(page: number, perPage: number, signal?: AbortSignal): Promise<ApiResult<ArchiveList>> {
  const res = await getJson(`/v1/hospital-archive?page=${page}&perPage=${perPage}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return {
    ok: true,
    data: { items: list(d.items).map(archiveItem), total: num(d.total), dueCount: num(d.dueCount), page, perPage },
  };
}

export type ArchiveDetail = ArchiveItem & {
  salesPaid: number;
  salesRefunded: number;
  snapshot: {
    /** Scalar fields of the hospital row as stored, in the order sent. */
    hospital: { key: string; value: string }[];
    doctors: { id: number; name: string; position: string }[];
    images: number;
    treatments: number;
  };
};

/** A stored value as one line of text; nested values are left out. */
function snapshotValue(v: unknown): string | null {
  if (typeof v === "string") return v;
  if (typeof v === "number" && Number.isFinite(v)) return String(v);
  if (typeof v === "boolean") return v ? "1" : "0";
  if (Array.isArray(v)) {
    const parts = v.filter((x): x is string | number => typeof x === "string" || typeof x === "number").map(String);
    return parts.length === v.length ? parts.join(", ") : null;
  }
  return null;
}

export async function fetchArchiveItem(id: number, signal?: AbortSignal): Promise<ApiResult<ArchiveDetail>> {
  const res = await getJson(`/v1/hospital-archive/${encodeURIComponent(String(id))}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const snap = obj(d.snapshot);
  const hospital: { key: string; value: string }[] = [];
  for (const [key, value] of Object.entries(obj(snap.hospital))) {
    const shown = snapshotValue(value);
    if (shown !== null) hospital.push({ key, value: shown });
  }
  // Counts may arrive as a number or as the list itself.
  const count = (v: unknown): number => (Array.isArray(v) ? v.length : num(v));
  return {
    ok: true,
    data: {
      ...archiveItem(d),
      salesPaid: num(d.salesPaid),
      salesRefunded: num(d.salesRefunded),
      snapshot: {
        hospital,
        doctors: list(snap.doctors).map((raw) => {
          const doc = obj(raw);
          return { id: num(doc.id), name: text(doc.name), position: text(doc.position ?? doc.specialty) };
        }),
        images: count(snap.images ?? snap.imagesCount),
        treatments: count(snap.treatments ?? snap.treatmentsCount),
      },
    },
  };
}

/** Super admin only. Marks the one-month notice as seen; repeating it is harmless. */
export async function ackArchive(id: number): Promise<WriteResult<Record<string, never>>> {
  const res = await sendJson("POST", `/v1/hospital-archive/${encodeURIComponent(String(id))}/ack`, {});
  return res.ok ? { ok: true, data: {} } : res;
}

// ───────── Treatment master (super admin) ─────────

export const ANESTHESIA_TYPES = ["none", "local", "sedation", "general"] as const;
export type AnesthesiaType = (typeof ANESTHESIA_TYPES)[number];
const anesthesia = (v: unknown): AnesthesiaType => ((ANESTHESIA_TYPES as readonly unknown[]).includes(v) ? (v as AnesthesiaType) : "none");

export const TABLE_MODES = ["excel", "row", "hybrid"] as const;
export type TableMode = (typeof TABLE_MODES)[number];
const tableMode = (v: unknown): TableMode => ((TABLE_MODES as readonly unknown[]).includes(v) ? (v as TableMode) : "excel");

export type TreatmentAlias = { id: number; alias: string; lang: string };

export type TreatmentItem = {
  id: number;
  categoryId: number;
  categoryName: string;
  sortOrder: number;
  name: string;
  hashtag: string;
  description: string;
  /** Stored address of the default image; null when there is none. May be relative to the old panel's folder. */
  defaultImageUrl: string | null;
  durationMin: number;
  recoveryDays: number;
  anesthesiaType: AnesthesiaType;
  repeatCycleMonths: number;
  revisitDays: number | null;
  revisitStrict: boolean;
  precautions: string;
  /** In units of 10,000 won. */
  avgPriceMin: number;
  avgPriceMax: number;
  isActive: boolean;
  popularityScore: number;
  createdBy: string;
  /** "YYYY-MM-DD HH:MM:SS" or "" */
  createdAt: string;
  aliasCount: number;
  /** Rows of hospital assignments that point at this treatment (any state). */
  hospitalCount: number;
  aliases: TreatmentAlias[];
};

export type TreatmentCategory = {
  id: number;
  name: string;
  slug: string;
  icon: string;
  sortOrder: number;
  isActive: boolean;
  /** Every treatment of the category, inactive ones included. */
  treatmentCount: number;
};

/** A pair of treatments that cannot be booked together on the same day; a < b. */
export type TreatmentConflict = { id: number; a: number; b: number };

export type TreatmentsData = {
  /** `categories` counts active categories only; the other three count every treatment. */
  stats: { total: number; active: number; inactive: number; categories: number };
  /** Optional columns and tables the database has: without them the matching fields are empty and cannot be edited. */
  schema: { revisit: boolean; defaultImage: boolean; conflicts: boolean };
  tableMode: TableMode;
  categories: TreatmentCategory[];
  items: TreatmentItem[];
  conflicts: TreatmentConflict[];
};

export function masterTreatment(raw: unknown): TreatmentItem {
  const r = obj(raw);
  const image = str(r.defaultImageUrl).trim();
  return {
    id: num(r.id),
    categoryId: num(r.categoryId),
    categoryName: text(r.categoryName),
    sortOrder: num(r.sortOrder),
    name: text(r.name),
    hashtag: text(r.hashtag),
    description: str(r.description),
    defaultImageUrl: image === "" ? null : image,
    durationMin: num(r.durationMin),
    recoveryDays: num(r.recoveryDays),
    anesthesiaType: anesthesia(r.anesthesiaType),
    repeatCycleMonths: num(r.repeatCycleMonths),
    revisitDays: numOrNull(r.revisitDays),
    revisitStrict: bool(r.revisitStrict),
    precautions: str(r.precautions),
    avgPriceMin: num(r.avgPriceMin),
    avgPriceMax: num(r.avgPriceMax),
    isActive: bool(r.isActive),
    popularityScore: num(r.popularityScore),
    createdBy: text(r.createdBy),
    createdAt: str(r.createdAt),
    aliasCount: num(r.aliasCount),
    hospitalCount: num(r.hospitalCount),
    aliases: list(r.aliases)
      .map((a) => ({ id: num(obj(a).id), alias: text(obj(a).alias), lang: str(obj(a).lang).toLowerCase() }))
      .filter((a) => a.id > 0),
  };
}

export function masterCategory(raw: unknown): TreatmentCategory {
  const c = obj(raw);
  return { id: num(c.id), name: text(c.name), slug: str(c.slug), icon: str(c.icon), sortOrder: num(c.sortOrder), isActive: bool(c.isActive), treatmentCount: num(c.treatmentCount) };
}

export const masterConflicts = (raw: unknown): TreatmentConflict[] =>
  list(raw)
    .map((x) => {
      const c = obj(x);
      const a = num(c.a);
      const b = num(c.b);
      return { id: num(c.id), a: Math.min(a, b), b: Math.max(a, b) };
    })
    .filter((c) => c.id > 0 && c.a > 0 && c.b > 0 && c.a !== c.b);

/** The whole treatment master in one answer: filtering, searching and sorting happen in the browser. Super admin only (403 for anyone else). */
export async function fetchTreatments(signal?: AbortSignal): Promise<ApiResult<TreatmentsData>> {
  const res = await getJson("/v1/treatments", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const stats = obj(d.stats);
  const schema = obj(d.schema);
  return {
    ok: true,
    data: {
      stats: { total: num(stats.total), active: num(stats.active), inactive: num(stats.inactive), categories: num(stats.categories) },
      schema: { revisit: bool(schema.revisit), defaultImage: bool(schema.defaultImage), conflicts: bool(schema.conflicts) },
      tableMode: tableMode(d.tableMode),
      categories: list(d.categories).map(masterCategory).filter((c) => c.id > 0),
      items: list(d.items).map(masterTreatment).filter((item) => item.id > 0),
      conflicts: masterConflicts(d.conflicts),
    },
  };
}


// ───────── Treatment master: changes (super admin, behind the write switch) ─────────

/** What the form sends. On create every key is sent; on change only the keys that differ. Prices are in units of 10,000 won. */
export type TreatmentInput = Partial<{
  name: string;
  categoryId: number;
  hashtag: string;
  description: string;
  precautions: string;
  /** "" removes the image; an address of the upload folder or an http(s) address sets it. */
  defaultImageUrl: string;
  durationMin: number;
  recoveryDays: number;
  repeatCycleMonths: number;
  avgPriceMin: number;
  avgPriceMax: number;
  anesthesiaType: AnesthesiaType;
  revisitDays: number | null;
  revisitStrict: boolean;
  conflictIds: number[];
}>;

export type TreatmentChange = { item: TreatmentItem; conflicts: TreatmentConflict[] };
export type BulkAction = "activate" | "deactivate";
export type BulkResult = { done: number; skipped: { id: number; reason: string }[]; conflicts: TreatmentConflict[] };

const changed = (res: WriteResult<Json>): WriteResult<TreatmentChange> =>
  res.ok ? { ok: true, data: { item: masterTreatment(obj(res.data).item), conflicts: masterConflicts(obj(res.data).conflicts) } } : res;

const treatmentPath = (id: number): string => `/v1/treatments/${encodeURIComponent(String(id))}`;

export async function createTreatment(input: TreatmentInput): Promise<WriteResult<TreatmentChange>> {
  return changed(await sendJson("POST", "/v1/treatments", input));
}

export async function updateTreatment(id: number, input: TreatmentInput): Promise<WriteResult<TreatmentChange>> {
  return changed(await sendJson("PATCH", treatmentPath(id), input));
}

export async function setTreatmentActive(id: number, active: boolean): Promise<WriteResult<TreatmentChange>> {
  return changed(await sendJson("POST", `${treatmentPath(id)}/active`, { active }));
}

export async function deleteTreatment(id: number): Promise<WriteResult<{ conflicts: TreatmentConflict[] }>> {
  const res = await sendJson("DELETE", treatmentPath(id));
  return res.ok ? { ok: true, data: { conflicts: masterConflicts(obj(res.data).conflicts) } } : res;
}

export async function bulkTreatments(action: BulkAction, ids: number[]): Promise<WriteResult<BulkResult>> {
  const res = await sendJson("POST", "/v1/treatments/bulk", { action, ids });
  if (!res.ok) return res;
  const d = obj(res.data);
  return {
    ok: true,
    data: {
      done: num(d.done),
      skipped: list(d.skipped).map((x) => ({ id: num(obj(x).id), reason: str(obj(x).reason) })),
      conflicts: masterConflicts(d.conflicts),
    },
  };
}

/** Stores the order of the given treatments: position in the list = new order value (1, 2, 3 ...). */
export async function reorderTreatments(ids: number[]): Promise<WriteResult<Json>> {
  return sendJson("PUT", "/v1/treatments/order", { ids });
}

export async function addTreatmentAlias(id: number, alias: string, lang: string): Promise<WriteResult<TreatmentChange>> {
  return changed(await sendJson("POST", `${treatmentPath(id)}/aliases`, { alias, lang }));
}

export async function removeTreatmentAlias(id: number, aliasId: number): Promise<WriteResult<TreatmentChange>> {
  return changed(await sendJson("DELETE", `${treatmentPath(id)}/aliases/${encodeURIComponent(String(aliasId))}`));
}

// ───────── Treatment master: categories, table mode, conflict pairs ─────────

export type CategoryInput = Partial<{ name: string; icon: string; sortOrder: number }>;
export type CategoryChange = { categories: TreatmentCategory[] };

const categories = (res: WriteResult<Json>): WriteResult<CategoryChange> =>
  res.ok ? { ok: true, data: { categories: list(obj(res.data).categories).map(masterCategory).filter((c) => c.id > 0) } } : res;

const categoryPath = (id: number): string => `/v1/treatment-categories/${encodeURIComponent(String(id))}`;

export async function createCategory(input: CategoryInput): Promise<WriteResult<CategoryChange>> {
  return categories(await sendJson("POST", "/v1/treatment-categories", input));
}

export async function updateCategory(id: number, input: CategoryInput): Promise<WriteResult<CategoryChange>> {
  return categories(await sendJson("PATCH", categoryPath(id), input));
}

export async function setCategoryActive(id: number, active: boolean): Promise<WriteResult<CategoryChange>> {
  return categories(await sendJson("POST", `${categoryPath(id)}/active`, { active }));
}

/** A 409 "in_use" carries the number of treatments the category still has in `hospitalCount` (the field the failure type has for a count). */
export async function deleteCategory(id: number): Promise<WriteResult<CategoryChange>> {
  return categories(await sendJson("DELETE", categoryPath(id)));
}

/** Stores the order of all categories: position in the list = new order value (1, 2, 3 ...). */
export async function reorderCategories(ids: number[]): Promise<WriteResult<CategoryChange>> {
  return categories(await sendJson("PUT", "/v1/treatment-categories/order", { ids }));
}

export async function saveTableMode(mode: TableMode): Promise<WriteResult<{ tableMode: TableMode }>> {
  const res = await sendJson("PUT", "/v1/treatment-settings/table-mode", { mode });
  return res.ok ? { ok: true, data: { tableMode: tableMode(obj(res.data).tableMode) } } : res;
}

export type ConflictChange = { conflicts: TreatmentConflict[]; existed: boolean };

export async function addConflict(a: number, b: number): Promise<WriteResult<ConflictChange>> {
  const res = await sendJson("POST", "/v1/treatment-conflicts", { a, b });
  return res.ok ? { ok: true, data: { conflicts: masterConflicts(obj(res.data).conflicts), existed: obj(res.data).existed === true } } : res;
}

export async function removeConflict(id: number): Promise<WriteResult<ConflictChange>> {
  const res = await sendJson("DELETE", `/v1/treatment-conflicts/${encodeURIComponent(String(id))}`);
  return res.ok ? { ok: true, data: { conflicts: masterConflicts(obj(res.data).conflicts), existed: true } } : res;
}

/** Uploads the default image of a treatment (one file, multipart; the browser sets Content-Type itself). Answers like any other change of one treatment. */
export async function uploadTreatmentImage(id: number, file: File): Promise<WriteResult<TreatmentChange>> {
  const body = new FormData();
  body.append("image", file);
  try {
    const res = await fetch(`${BASE_PATH}/api${treatmentPath(id)}/image`, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", "X-Requested-With": "admin-next" },
      body,
    });
    let data: unknown = null;
    try {
      data = await res.json();
    } catch {
      // An empty or non-JSON body: the status alone says what happened.
    }
    const d = obj(data);
    if (!res.ok) return { ok: false, status: res.status, fields: {}, updatedAt: "", error: str(d.error), blockers: [], deletableAfter: "" };
    return changed({ ok: true, data: d });
  } catch {
    return { ok: false, status: 0, fields: {}, updatedAt: "", error: "", blockers: [], deletableAfter: "" };
  }
}

/** One account of the admins page. `ssoMemberId` is filled for a super admin only. */
export type AdminAccount = {
  id: number;
  username: string;
  name: string;
  email: string;
  phone: string;
  role: string;
  hospitalId: number | null;
  hospitalName: string;
  isActive: boolean;
  isSelf: boolean;
  lastLoginAt: string | null;
  ssoMemberId: string | null;
  /** The notification types the account receives. */
  notifications: string[];
  notificationsCustom: boolean;
  /** When the account was switched off; null while active (and for accounts switched off before this was recorded). */
  deactivatedAt: string | null;
  inactiveDays: number | null;
  /** Switched off long enough that it may be deleted. */
  deleteReady: boolean;
};

/** "all": a super admin sees every account. "self": anyone else sees only their own. */
export type AdminListData = { scope: "all" | "self"; deleteAfterDays: number; total: number; items: AdminAccount[] };

function adminAccount(raw: unknown): AdminAccount {
  const a = obj(raw);
  const hospitalId = num(a.hospitalId);
  const days = numOrNull(a.inactiveDays);
  return {
    id: num(a.id),
    username: str(a.username),
    name: str(a.name),
    email: str(a.email),
    phone: text(a.phone),
    role: str(a.role),
    hospitalId: hospitalId > 0 ? hospitalId : null,
    hospitalName: str(a.hospitalName),
    isActive: bool(a.isActive),
    isSelf: bool(a.isSelf),
    lastLoginAt: str(a.lastLoginAt) || null,
    ssoMemberId: str(a.ssoMemberId) || null,
    notifications: names(a.notifications),
    notificationsCustom: bool(a.notificationsCustom),
    deactivatedAt: str(a.deactivatedAt) || null,
    inactiveDays: days === null ? null : Math.max(0, Math.floor(days)),
    deleteReady: bool(a.deleteReady),
  };
}

export async function fetchAdmins(signal?: AbortSignal): Promise<ApiResult<AdminListData>> {
  const res = await getJson("/v1/admins", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return {
    ok: true,
    data: {
      scope: d.scope === "all" ? "all" : "self",
      deleteAfterDays: num(d.deleteAfterDays) > 0 ? num(d.deleteAfterDays) : 1,
      total: num(d.total),
      items: list(d.items).map(adminAccount),
    },
  };
}

/** What the add form sends. `hospitalId` only for a hospital role. */
export type AdminCreateBody = {
  username: string;
  password: string;
  name: string;
  email: string;
  phone: string;
  role: string;
  hospitalId: number | null;
  notifications: string[];
};

/** What the edit form sends: only what changed. `currentPassword` is for a role that is not a super admin changing its own email or password. */
export type AdminEditBody = Partial<{
  name: string;
  phone: string;
  email: string;
  notifications: string[];
  newPassword: string;
  currentPassword: string;
  role: string;
  hospitalId: number | null;
}>;

const adminPath = (id: number) => `/v1/admins/${encodeURIComponent(String(id))}`;

/** Super admin only. 409 `conflict` (field `username` or `email`): already used. */
export async function createAdmin(body: AdminCreateBody): Promise<WriteResult<{ item: AdminAccount }>> {
  const res = await sendJson("POST", "/v1/admins", body);
  return res.ok ? { ok: true, data: { item: adminAccount(res.data.item) } } : res;
}

/** A super admin may edit any account, every other role its own. 403 `wrong_password`, 429 `too_many_attempts`. */
export async function editAdmin(id: number, body: AdminEditBody): Promise<WriteResult<{ item: AdminAccount; passwordChanged: boolean }>> {
  const res = await sendJson("PATCH", adminPath(id), body);
  return res.ok ? { ok: true, data: { item: adminAccount(res.data.item), passwordChanged: res.data.passwordChanged === true } } : res;
}

/** Super admin only. Sets the exact state asked for. 409 `last_super_admin`. */
export async function setAdminActive(id: number, active: boolean): Promise<WriteResult<{ item: AdminAccount }>> {
  const res = await sendJson("POST", `${adminPath(id)}/active`, { active });
  return res.ok ? { ok: true, data: { item: adminAccount(res.data.item) } } : res;
}

/** Super admin only: only an account inactive for long enough, and the login name has to be typed. */
export async function deleteAdmin(id: number, confirm: string): Promise<WriteResult<Record<string, never>>> {
  const res = await sendJson("DELETE", adminPath(id), { confirm });
  return res.ok ? { ok: true, data: {} } : res;
}

// ---- Price units (the units of the treatment price forms) ----

export type TreatmentUnit = { id: number; name: string };
/** `tableReady` false = the units table is missing or not readable on the server (the page says how to create it). */
export type TreatmentUnitsData = { tableReady: boolean; items: TreatmentUnit[] };
export type UnitAddStatus = "created" | "restored" | "exists";
export type UnitAddResult = { unit: TreatmentUnit; status: UnitAddStatus };

const treatmentUnit = (v: unknown): TreatmentUnit => ({ id: num(obj(v).id), name: str(obj(v).name) });

/** The active units in the order the server sends (Latin first, then Korean). Super admin only. */
export async function fetchTreatmentUnits(signal?: AbortSignal): Promise<ApiResult<TreatmentUnitsData>> {
  const res = await getJson("/v1/treatment-units", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return { ok: true, data: { tableReady: d.tableReady !== false, items: list(d.items).map(treatmentUnit).filter((u) => u.id > 0 && u.name !== "") } };
}

/** Adds a unit; a removed one of the same name is switched on again ("restored"), an active one answers "exists". */
export async function addTreatmentUnit(name: string): Promise<WriteResult<UnitAddResult>> {
  const res = await sendJson("POST", "/v1/treatment-units", { name });
  if (!res.ok) return res;
  const status = obj(res.data).status;
  return { ok: true, data: { unit: treatmentUnit(obj(res.data).unit), status: status === "restored" || status === "exists" ? status : "created" } };
}

/** Removes a unit from the lists of new price forms; prices that already carry it keep it. */
export async function removeTreatmentUnit(id: number): Promise<WriteResult<null>> {
  const res = await sendJson("DELETE", `/v1/treatment-units/${encodeURIComponent(String(id))}`);
  return res.ok ? { ok: true, data: null } : res;
}


// ───────── Bookings ─────────

export type BookingAction = "checking" | "confirm" | "cancel" | "complete";
export type BookingRow = {
  id: number; hospitalId: number; hospitalName: string; name: string; phone: string; treatment: string;
  date: string; time: string; status: string; doctorId: number; doctorName: string; photoCount: number; receivedAt: string; actions: BookingAction[];
};
export type BookingStats = Record<"total" | "pending" | "hospital_checking" | "confirmed" | "completed" | "cancelled" | "refunded" | "no_show" | "today", number>;
export type BookingList = { items: BookingRow[]; total: number; page: number; perPage: number; totalPages: number; stats: BookingStats };
export type BookingHospital = { id: number; name: string };
export type BookingDetail = {
  booking: BookingRow & { memo: string; cancelReason: string };
  photos: string[];
  /** `yes` is the member's answer to a question asked before the treatment. */
  answers: { question: string; yes: boolean }[];
  doctors: { id: number; name: string }[];
};
export type BookingsQuery = { hospitalId: number; status: string; from: string; to: string; q: string; page: number };

const BOOKING_ACTIONS: readonly string[] = ["checking", "confirm", "cancel", "complete"];
const STAT_KEYS = ["total", "pending", "hospital_checking", "confirmed", "completed", "cancelled", "refunded", "no_show", "today"] as const;

const bookingRow = (v: unknown): BookingRow => {
  const d = obj(v);
  return {
    id: num(d.id), hospitalId: num(d.hospitalId), hospitalName: str(d.hospitalName), name: str(d.name), phone: text(d.phone), treatment: str(d.treatment),
    date: str(d.bookingDate), time: str(d.bookingTime), status: str(d.status), doctorId: num(d.doctorId), doctorName: str(d.doctorName), photoCount: num(d.photoCount),
    receivedAt: str(d.createdAt), actions: list(d.actions).filter((a): a is BookingAction => typeof a === "string" && BOOKING_ACTIONS.includes(a)),
  };
};

/** The list, the status counts (they follow only the hospital choice) and the page the server settled on. */
export async function fetchBookings(q: BookingsQuery, signal?: AbortSignal): Promise<ApiResult<BookingList>> {
  const p = new URLSearchParams();
  if (q.hospitalId > 0) p.set("hospital_id", String(q.hospitalId));
  if (q.status) p.set("status", q.status);
  if (q.from) p.set("from", q.from);
  if (q.to) p.set("to", q.to);
  if (q.q.trim()) p.set("q", q.q.trim());
  if (q.page > 1) p.set("page", String(q.page));
  const qs = p.toString();
  const res = await getJson(`/v1/bookings${qs ? `?${qs}` : ""}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const stats = {} as BookingStats;
  for (const k of STAT_KEYS) stats[k] = num(obj(d.stats)[k]);
  return {
    ok: true,
    data: { items: list(d.items).map(bookingRow).filter((b) => b.id > 0), total: num(d.total), page: Math.max(1, num(d.page)), perPage: Math.max(1, num(d.perPage)), totalPages: Math.max(1, num(d.totalPages)), stats },
  };
}

export async function fetchBookingHospitals(signal?: AbortSignal): Promise<ApiResult<BookingHospital[]>> {
  const res = await getJson("/v1/bookings/hospitals", signal);
  if (!res.ok) return res;
  return { ok: true, data: list(obj(res.data).items).map((h) => ({ id: num(obj(h).id), name: str(obj(h).name) })).filter((h) => h.id > 0) };
}

export async function fetchBookingDetail(id: number, signal?: AbortSignal): Promise<ApiResult<BookingDetail>> {
  const res = await getJson(`/v1/bookings/${encodeURIComponent(String(id))}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const b = obj(d.booking);
  return {
    ok: true,
    data: {
      booking: { ...bookingRow(b), memo: str(b.memo), cancelReason: str(b.cancelReason) },
      photos: list(d.photos).filter((u): u is string => typeof u === "string"),
      answers: list(d.answers).map((a) => ({ question: str(obj(a).question), yes: num(obj(a).answer) === 1 })),
      doctors: list(d.doctors).map((x) => ({ id: num(obj(x).id), name: str(obj(x).name) })).filter((x) => x.id > 0),
    },
  };
}

/** One step of the booking flow. The reason only counts for "cancel". */
export async function changeBookingStatus(id: number, action: BookingAction, cancelReason = ""): Promise<WriteResult<{ status: string }>> {
  const res = await sendJson("POST", `/v1/bookings/${encodeURIComponent(String(id))}/status`, action === "cancel" ? { action, cancelReason } : { action });
  return res.ok ? { ok: true, data: { status: str(res.data.status) } } : res;
}

/** doctorId 0 removes the assignment. */
export async function assignBookingDoctor(id: number, doctorId: number): Promise<WriteResult<{ doctorName: string }>> {
  const res = await sendJson("POST", `/v1/bookings/${encodeURIComponent(String(id))}/doctor`, { doctorId });
  return res.ok ? { ok: true, data: { doctorName: str(res.data.doctorName) } } : res;
}

// ───────── Consultation requests ─────────

export type ConsultationRow = {
  id: number; hospitalId: number; hospitalName: string; type: string; name: string; phone: string;
  /** The lines the application form put in front of the text, e.g. "[관심 시술] 보톡스". */
  tags: string[]; preview: string; photoCount: number; status: string; createdAt: string;
};
export type ConsultationStats = Record<"total" | "pending" | "approved" | "completed" | "rejected" | "contact_failed", number> & { oldestPendingAt: string | null };
export type ConsultationList = { items: ConsultationRow[]; total: number; page: number; perPage: number; totalPages: number; stats: ConsultationStats };
export type ConsultationHospital = { id: number; name: string; consultationCount: number; pendingCount: number };
export type ConsultationsQuery = { hospitalId: number; status: string; q: string; page: number };

const consultationRow = (v: unknown): ConsultationRow => {
  const d = obj(v);
  return {
    id: num(d.id), hospitalId: num(d.hospitalId), hospitalName: str(d.hospitalName), type: str(d.type), name: str(d.name), phone: text(d.phone),
    tags: list(d.tags).filter((x): x is string => typeof x === "string"), preview: str(d.preview), photoCount: num(d.photoCount), status: str(d.status), createdAt: str(d.createdAt),
  };
};

/** The list, the counts above it (they follow only the hospital choice) and the page the server settled on. */
export async function fetchConsultations(q: ConsultationsQuery, signal?: AbortSignal): Promise<ApiResult<ConsultationList>> {
  const p = new URLSearchParams();
  if (q.hospitalId > 0) p.set("hospital_id", String(q.hospitalId));
  if (q.status) p.set("status", q.status);
  if (q.q.trim()) p.set("q", q.q.trim());
  if (q.page > 1) p.set("page", String(q.page));
  const qs = p.toString();
  const res = await getJson(`/v1/consultations${qs ? `?${qs}` : ""}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const s = obj(d.stats);
  return {
    ok: true,
    data: {
      items: list(d.items).map(consultationRow).filter((c) => c.id > 0),
      total: num(d.total), page: Math.max(1, num(d.page)), perPage: Math.max(1, num(d.perPage)), totalPages: Math.max(1, num(d.totalPages)),
      stats: { total: num(s.total), pending: num(s.pending), approved: num(s.approved), completed: num(s.completed), rejected: num(s.rejected), contact_failed: num(s.contact_failed), oldestPendingAt: typeof s.oldestPendingAt === "string" ? s.oldestPendingAt : null },
    },
  };
}

export async function fetchConsultationHospitals(signal?: AbortSignal): Promise<ApiResult<ConsultationHospital[]>> {
  const res = await getJson("/v1/consultations/hospitals", signal);
  if (!res.ok) return res;
  return {
    ok: true,
    data: list(obj(res.data).items)
      .map((h) => ({ id: num(obj(h).id), name: str(obj(h).name), consultationCount: num(obj(h).consultationCount), pendingCount: num(obj(h).pendingCount) }))
      .filter((h) => h.id > 0),
  };
}

export type ConsultationEvent = { kind: string; createdAt: string };
export type ConsultationDetail = Omit<ConsultationRow, "preview"> & {
  /** The application text without the form lines (those are in `tags`). */
  text: string;
  /** false when the database has no history columns yet; the hospital message and progress then stay empty. */
  historySupported: boolean;
  hospitalMessage: string; hospitalMessageAt: string | null; callbackSlot: string; events: ConsultationEvent[];
};

export async function fetchConsultationDetail(id: number, signal?: AbortSignal): Promise<ApiResult<ConsultationDetail>> {
  const res = await getJson(`/v1/consultations/${encodeURIComponent(String(id))}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return {
    ok: true,
    data: {
      id: num(d.id), hospitalId: num(d.hospitalId), hospitalName: str(d.hospitalName), type: str(d.type), name: str(d.name), phone: text(d.phone),
      tags: list(d.tags).filter((x): x is string => typeof x === "string"), text: str(d.text), photoCount: Math.min(10, num(d.photoCount)), status: str(d.status), createdAt: str(d.createdAt),
      historySupported: d.historySupported === true, hospitalMessage: str(d.hospitalMessage), hospitalMessageAt: typeof d.hospitalMessageAt === "string" ? d.hospitalMessageAt : null,
      callbackSlot: str(d.callbackSlot),
      events: list(d.events).map((e) => ({ kind: str(obj(e).kind), createdAt: str(obj(e).createdAt) })).filter((e) => e.kind),
    },
  };
}

/** One attached photo; the browser asks with the admin's cookie, the server checks scope and file. */
export const consultationPhotoUrl = (id: number, index: number): string => `${BASE_PATH}/api/v1/consultations/${encodeURIComponent(String(id))}/photos/${index}`;

export type ConsultationWriteStatus = "approved" | "completed" | "rejected" | "contact_failed";

/** Moves an application to a new state. `changed` is false when it already was in that state. */
export async function changeConsultationStatus(id: number, status: ConsultationWriteStatus): Promise<WriteResult<{ status: string; changed: boolean }>> {
  const res = await sendJson("POST", `/v1/consultations/${encodeURIComponent(String(id))}/status`, { status });
  return res.ok ? { ok: true, data: { status: str(res.data.status), changed: res.data.changed !== false } } : res;
}

/** The message the patient sees; an empty text clears it. */
export async function saveConsultationMessage(id: number, message: string): Promise<WriteResult<{ hospitalMessage: string }>> {
  const res = await sendJson("POST", `/v1/consultations/${encodeURIComponent(String(id))}/message`, { message });
  return res.ok ? { ok: true, data: { hospitalMessage: str(res.data.hospitalMessage) } } : res;
}

export type BookingsExport = { hospitalIds: number[]; from: string; to: string; status: string };
export type ExcelResult = { ok: true; blob: Blob; filename: string } | { ok: false; status: number; error: string };

/** The Excel file of the chosen hospitals and visit dates, fetched with the admin's session. */
export async function downloadBookingsExcel(q: BookingsExport): Promise<ExcelResult> {
  const p = new URLSearchParams({ from: q.from, to: q.to });
  if (q.hospitalIds.length) p.set("hospital_ids", q.hospitalIds.join(","));
  if (q.status) p.set("status", q.status);
  try {
    const res = await fetch(`${BASE_PATH}/api/v1/bookings/export?${p}`, { credentials: "same-origin", cache: "no-store", headers: { Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" } });
    if (!res.ok) {
      let error = "";
      try {
        error = str(obj(await res.json()).error);
      } catch {
        // No JSON body: the status says enough.
      }
      return { ok: false, status: res.status, error };
    }
    // A session that ran out may be answered with a login page: only a spreadsheet counts as the file.
    if (!(res.headers.get("content-type") ?? "").includes("spreadsheetml")) return { ok: false, status: 0, error: "not_a_file" };
    const name = /filename="?([^";]+)"?/i.exec(res.headers.get("content-disposition") ?? "")?.[1] ?? "bookings.xlsx";
    return { ok: true, blob: await res.blob(), filename: name };
  } catch {
    return { ok: false, status: 0, error: "" };
  }
}

// ───────── Payments (read only) ─────────

export type PaymentStage = "held" | "recognized" | "refunded";
/** Money in won. received - refunded = recognized + held; `stuck` is the part of `held` that no treatment will release (cancelled / refunded / no-show booking). */
export type PaymentSums = { payments: number; received: number; refunded: number; recognized: number; held: number; stuck: number };
export type PaymentRow = {
  id: number; bookingId: number; hospitalId: number; hospitalName: string; patientName: string; patientPhone: string; treatmentName: string;
  amount: number; refundedAmount: number; status: string; paidAt: string; bookingStatus: string; visitDate: string; visitTime: string;
  stage: PaymentStage; needsAttention: boolean;
};
export type PaymentList = { items: PaymentRow[]; total: number; page: number; perPage: number; totalPages: number; summary: PaymentSums; from: string; to: string };
export type PaymentsQuery = { hospitalId: number; from: string; to: string; q: string; page: number };
export type PaymentsRangeQuery = { hospitalId: number; from: string; to: string };
export type PaymentHospitalSums = PaymentSums & { hospitalId: number; hospitalName: string };
export type PaymentTreatmentSums = PaymentSums & { treatmentName: string };
/** `truncated`: the table shows only its first rows; more existed. */
export type PaymentTable<T> = { items: T[]; from: string; to: string; truncated: boolean };

export type CompareMetric = "received" | "recognized" | "held";
export type DayValue = { date: string; value: number };
export type PaymentsWeek = {
  thisWeek: { start: string; days: DayValue[] };
  lastWeek: { start: string; end: string; days: DayValue[] };
  totals: { thisToDate: number; lastSameDays: number; lastFull: number };
  changeSameDays: number | null;
  changeFull: number | null;
};
export type CumulativePoint = { day: number; value: number };
export type PaymentsMonth = {
  thisMonth: { start: string; cumulative: CumulativePoint[] };
  lastMonth: { start: string; end: string; cumulative: CumulativePoint[] };
  totals: { thisToDate: number; lastSameDay: number; lastFull: number };
  changeSameDay: number | null;
  changeFull: number | null;
};
export type PaymentsCompare = { metric: CompareMetric; today: string; week: PaymentsWeek; month: PaymentsMonth };

const paymentSums = (v: unknown): PaymentSums => {
  const d = obj(v);
  return { payments: num(d.payments), received: num(d.received), refunded: num(d.refunded), recognized: num(d.recognized), held: num(d.held), stuck: num(d.stuck) };
};
const paymentStage = (v: unknown): PaymentStage => (v === "recognized" || v === "refunded" ? v : "held");
const paymentRow = (v: unknown): PaymentRow => {
  const d = obj(v);
  return {
    id: num(d.id), bookingId: num(d.bookingId), hospitalId: num(d.hospitalId), hospitalName: str(d.hospitalName), patientName: str(d.patientName), patientPhone: str(d.patientPhone),
    treatmentName: str(d.treatmentName), amount: num(d.amount), refundedAmount: num(d.refundedAmount), status: str(d.status), paidAt: str(d.paidAt), bookingStatus: str(d.bookingStatus),
    visitDate: str(d.visitDate), visitTime: str(d.visitTime), stage: paymentStage(d.stage), needsAttention: d.needsAttention === true,
  };
};
const dayValues = (v: unknown): DayValue[] => list(v).map((x) => ({ date: str(obj(x).date), value: num(obj(x).value) })).filter((x) => x.date !== "");
const points = (v: unknown): CumulativePoint[] => list(v).map((x) => ({ day: num(obj(x).day), value: num(obj(x).value) })).filter((x) => x.day > 0);

function paymentsParams(q: { hospitalId: number; from: string; to: string }): URLSearchParams {
  const p = new URLSearchParams();
  if (q.hospitalId > 0) p.set("hospital_id", String(q.hospitalId));
  if (q.from) p.set("from", q.from);
  if (q.to) p.set("to", q.to);
  return p;
}

/** The ledger rows of the chosen hospital, dates and patient name, with the totals of the same hospital and dates. */
export async function fetchPayments(q: PaymentsQuery, signal?: AbortSignal): Promise<ApiResult<PaymentList>> {
  const p = paymentsParams(q);
  if (q.q.trim()) p.set("q", q.q.trim());
  if (q.page > 1) p.set("page", String(q.page));
  const qs = p.toString();
  const res = await getJson(`/v1/payments${qs ? `?${qs}` : ""}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return {
    ok: true,
    data: {
      items: list(d.items).map(paymentRow).filter((r) => r.id > 0), total: num(d.total), page: Math.max(1, num(d.page)), perPage: Math.max(1, num(d.perPage)),
      totalPages: Math.max(1, num(d.totalPages)), summary: paymentSums(d.summary), from: str(d.from), to: str(d.to),
    },
  };
}

export async function fetchPaymentHospitals(signal?: AbortSignal): Promise<ApiResult<{ id: number; name: string }[]>> {
  const res = await getJson("/v1/payments/hospitals", signal);
  if (!res.ok) return res;
  return { ok: true, data: list(obj(res.data).items).map((h) => ({ id: num(obj(h).id), name: str(obj(h).name) })).filter((h) => h.id > 0) };
}

export async function fetchPaymentsByHospital(q: PaymentsRangeQuery, signal?: AbortSignal): Promise<ApiResult<PaymentTable<PaymentHospitalSums>>> {
  const qs = paymentsParams(q).toString();
  const res = await getJson(`/v1/payments/by-hospital${qs ? `?${qs}` : ""}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return {
    ok: true,
    data: {
      items: list(d.items).map((r) => ({ hospitalId: num(obj(r).hospitalId), hospitalName: str(obj(r).hospitalName), ...paymentSums(r) })).filter((r) => r.hospitalId > 0),
      from: str(d.from), to: str(d.to), truncated: d.truncated === true,
    },
  };
}

export async function fetchPaymentsByTreatment(q: PaymentsRangeQuery, signal?: AbortSignal): Promise<ApiResult<PaymentTable<PaymentTreatmentSums>>> {
  const qs = paymentsParams(q).toString();
  const res = await getJson(`/v1/payments/by-treatment${qs ? `?${qs}` : ""}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return {
    ok: true,
    data: { items: list(d.items).map((r) => ({ treatmentName: str(obj(r).treatmentName), ...paymentSums(r) })), from: str(d.from), to: str(d.to), truncated: d.truncated === true },
  };
}

/** This week against last week (day by day) and this month against last month (running total), for one money measure. */
export async function fetchPaymentsCompare(hospitalId: number, metric: CompareMetric, signal?: AbortSignal): Promise<ApiResult<PaymentsCompare>> {
  const p = new URLSearchParams({ metric });
  if (hospitalId > 0) p.set("hospital_id", String(hospitalId));
  const res = await getJson(`/v1/payments/compare?${p}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const w = obj(d.week);
  const m = obj(d.month);
  const wt = obj(w.totals);
  const mt = obj(m.totals);
  const metricOut: CompareMetric = d.metric === "recognized" || d.metric === "held" ? d.metric : "received";
  return {
    ok: true,
    data: {
      metric: metricOut, today: str(d.today),
      week: {
        thisWeek: { start: str(obj(w.thisWeek).start), days: dayValues(obj(w.thisWeek).days) },
        lastWeek: { start: str(obj(w.lastWeek).start), end: str(obj(w.lastWeek).end), days: dayValues(obj(w.lastWeek).days) },
        totals: { thisToDate: num(wt.thisToDate), lastSameDays: num(wt.lastSameDays), lastFull: num(wt.lastFull) },
        changeSameDays: numOrNull(w.changeSameDays), changeFull: numOrNull(w.changeFull),
      },
      month: {
        thisMonth: { start: str(obj(m.thisMonth).start), cumulative: points(obj(m.thisMonth).cumulative) },
        lastMonth: { start: str(obj(m.lastMonth).start), end: str(obj(m.lastMonth).end), cumulative: points(obj(m.lastMonth).cumulative) },
        totals: { thisToDate: num(mt.thisToDate), lastSameDay: num(mt.lastSameDay), lastFull: num(mt.lastFull) },
        changeSameDay: numOrNull(m.changeSameDay), changeFull: numOrNull(m.changeFull),
      },
    },
  };
}

// ───────── Payments: fees, the three sections and the fee rates (ADMIN_PAYMENTS_FEES) ─────────

export type PayMethod = "card" | "bank_transfer" | "";
export type SectionStage = "held" | "counted" | "received";
export type BoardPeriod = "week" | "month";
export type BoardBox = { count: number; amount: number; noFee: number };
/** The three boxes of one period. A 404 from the server means the fee switch is off: the page keeps its old shape. */
export type PaymentBoard = { period: BoardPeriod; from: string; to: string; today: string; held: BoardBox; counted: BoardBox; received: BoardBox };
export type SectionRow = {
  id: number; bookingId: number; hospitalId: number; hospitalName: string; patientName: string; treatmentName: string; amount: number; status: string; paidAt: string;
  visitDate: string; visitTime: string; payMethod: PayMethod; feeRateBp: number | null; fee: number | null; settlement: number | null; payoutDate: string | null;
};
export type SectionTotals = { payments: number; received: number; fee: number; settlement: number; noFee: number };
export type PaymentSection = { stage: SectionStage; items: SectionRow[]; total: number; page: number; perPage: number; totalPages: number; totals: SectionTotals; from: string; to: string };
export type SectionQuery = { stage: SectionStage; hospitalId: number; from: string; to: string; q: string; page: number };
export type MethodSums = { method: PayMethod; payments: number; received: number; refunded: number; fee: number; settlement: number; noFee: number };
export type WeekSums = { payoutDate: string; payments: number; fee: number; settlement: number };
export type PaymentOverview = { methods: MethodSums[]; weeks: WeekSums[]; orderedHeld: { count: number; amount: number } };

const payMethod = (v: unknown): PayMethod => (v === "card" || v === "bank_transfer" ? v : "");
const boardBox = (v: unknown): BoardBox => ({ count: num(obj(v).count), amount: num(obj(v).amount), noFee: num(obj(v).noFee) });

/** `range` (both dates) replaces the week / month bounds. */
export async function fetchPaymentBoard(hospitalId: number, period: BoardPeriod, signal?: AbortSignal, range?: { from: string; to: string }): Promise<ApiResult<PaymentBoard>> {
  const p = new URLSearchParams({ period });
  if (range && range.from && range.to) { p.set("from", range.from); p.set("to", range.to); }
  if (hospitalId > 0) p.set("hospital_id", String(hospitalId));
  const res = await getJson(`/v1/payments/board?${p}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return { ok: true, data: { period: d.period === "month" ? "month" : "week", from: str(d.from), to: str(d.to), today: str(d.today), held: boardBox(d.held), counted: boardBox(d.counted), received: boardBox(d.received) } };
}

/** `from` / `to` empty = the server shows the whole current month (the same range as the board's month box). */
export async function fetchPaymentSection(q: SectionQuery, signal?: AbortSignal): Promise<ApiResult<PaymentSection>> {
  const p = new URLSearchParams({ stage: q.stage });
  if (q.hospitalId > 0) p.set("hospital_id", String(q.hospitalId));
  if (q.from) p.set("from", q.from);
  if (q.to) p.set("to", q.to);
  if (q.q.trim()) p.set("q", q.q.trim());
  if (q.page > 1) p.set("page", String(q.page));
  const res = await getJson(`/v1/payments/section?${p}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const t = obj(d.totals);
  const items: SectionRow[] = list(d.items).map((v) => {
    const r = obj(v);
    return {
      id: num(r.id), bookingId: num(r.bookingId), hospitalId: num(r.hospitalId), hospitalName: str(r.hospitalName), patientName: str(r.patientName), treatmentName: str(r.treatmentName),
      amount: num(r.amount), status: str(r.status), paidAt: str(r.paidAt), visitDate: str(r.visitDate), visitTime: str(r.visitTime), payMethod: payMethod(r.payMethod),
      feeRateBp: numOrNull(r.feeRateBp), fee: numOrNull(r.fee), settlement: numOrNull(r.settlement), payoutDate: str(r.payoutDate) || null,
    };
  }).filter((r) => r.id > 0);
  return {
    ok: true,
    data: {
      stage: d.stage === "counted" || d.stage === "received" ? d.stage : "held", items, total: num(d.total), page: Math.max(1, num(d.page)), perPage: Math.max(1, num(d.perPage)),
      totalPages: Math.max(1, num(d.totalPages)), totals: { payments: num(t.payments), received: num(t.received), fee: num(t.fee), settlement: num(t.settlement), noFee: num(t.noFee) }, from: str(d.from), to: str(d.to),
    },
  };
}

/** Per payment method and per payout Sunday, for the hospital and dates chosen (taken from the list answer, first page). */
export async function fetchPaymentOverview(q: PaymentsRangeQuery, signal?: AbortSignal): Promise<ApiResult<PaymentOverview>> {
  const qs = paymentsParams(q).toString();
  const res = await getJson(`/v1/payments${qs ? `?${qs}` : ""}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const methods: MethodSums[] = list(d.methods).map((v) => {
    const r = obj(v);
    return { method: payMethod(r.method), payments: num(r.payments), received: num(r.received), refunded: num(r.refunded), fee: num(r.fee), settlement: num(r.settlement), noFee: num(r.noFee) };
  });
  const weeks: WeekSums[] = list(d.weeks).map((v) => ({ payoutDate: str(obj(v).payoutDate), payments: num(obj(v).payments), fee: num(obj(v).fee), settlement: num(obj(v).settlement) })).filter((w) => w.payoutDate !== "");
  return { ok: true, data: { methods, weeks, orderedHeld: { count: num(obj(d.orderedHeld).count), amount: num(obj(d.orderedHeld).amount) } } };
}

export type FeeRateItem = { id: number; payMethod: PayMethod; rateBp: number; effectiveFrom: string; createdByName: string };
export type FeeRates = { today: string; current: Partial<Record<"card" | "bank_transfer", { rateBp: number; effectiveFrom: string | null } | null>>; items: FeeRateItem[] };

export async function fetchFeeRates(signal?: AbortSignal): Promise<ApiResult<FeeRates>> {
  const res = await getJson("/v1/payments/fee-rates", signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const current: FeeRates["current"] = {};
  for (const m of ["card", "bank_transfer"] as const) {
    const c = obj(obj(d.current)[m]);
    current[m] = Object.keys(c).length ? { rateBp: num(c.rateBp), effectiveFrom: str(c.effectiveFrom) || null } : null;
  }
  const items: FeeRateItem[] = list(d.items).map((v) => {
    const r = obj(v);
    return { id: num(r.id), payMethod: payMethod(r.payMethod), rateBp: num(r.rateBp), effectiveFrom: str(r.effectiveFrom), createdByName: str(r.createdByName) };
  }).filter((r) => r.id > 0);
  return { ok: true, data: { today: str(d.today), current, items } };
}
/** Super admin only. Both writers answer with the shared write result: a refused field is in `fields`. */
export async function createFeeRate(input: { payMethod: "card" | "bank_transfer"; rateBp: number; effectiveFrom: string }): Promise<WriteResult<Json>> {
  return sendJson("POST", "/v1/payments/fee-rates", input);
}
export async function deleteFeeRate(id: number): Promise<WriteResult<Json>> {
  return sendJson("DELETE", `/v1/payments/fee-rates/${id}`);
}


// ───────── Doctors (all hospitals) ─────────

/** The doctors list always shows this many cards per page. */
export const DOCTOR_PER_PAGE = 15;
export type DoctorStatusFilter = "all" | "active" | "inactive" | "resigned";
export type DoctorStatus = "active" | "inactive" | "resigned";
export type DoctorListQuery = { hospitalId: number; search: string; status: DoctorStatusFilter; page: number; perPage: number };

export type DoctorCard = {
  id: number;
  name: string;
  hospitalId: number;
  hospitalName: string;
  specialty: string;
  qualification: string;
  /** As stored; shown only through `doctorPhotoUrl`. */
  photoUrl: string | null;
  status: DoctorStatus;
  /** Place among the active doctors of the hospital (1 = first); null = none yet. */
  displayOrder: number | null;
  treatments: string[];
};
export type DoctorListData = { scope: Scope; stats: { total: number; hospitals: number; active: number }; total: number; page: number; perPage: number; items: DoctorCard[] };
export type DoctorTreatmentOption = { id: number; name: string; category: string };
export type DoctorFull = Omit<DoctorCard, "treatments"> & { experience: number; bio: string; treatments: DoctorTreatmentOption[] };
export type DoctorHospitalOption = { id: number; name: string };
export type DoctorOrderItem = { id: number; name: string; specialty: string; photoUrl: string | null; position: number };
export type DoctorOrderBoard = { hospitalId: number; orderable: boolean; total: number; items: DoctorOrderItem[] };

const doctorStatus = (v: unknown): DoctorStatus => (v === "resigned" ? "resigned" : v === "inactive" ? "inactive" : "active");
const photoOf = (v: unknown): string | null => (str(v).trim() === "" ? null : str(v));

function doctorCard(raw: unknown): DoctorCard {
  const d = obj(raw);
  return {
    id: num(d.id),
    name: str(d.name),
    hospitalId: num(d.hospitalId),
    hospitalName: str(d.hospitalName),
    specialty: str(d.specialty),
    qualification: str(d.qualification),
    photoUrl: photoOf(d.photoUrl),
    status: doctorStatus(d.status),
    displayOrder: numOrNull(d.displayOrder),
    treatments: names(d.treatments),
  };
}

const treatmentOption = (raw: unknown): DoctorTreatmentOption => {
  const t = obj(raw);
  return { id: num(t.id), name: str(t.name), category: str(t.category) };
};

function doctorFull(raw: unknown): DoctorFull {
  const d = obj(raw);
  const { treatments: _unused, ...base } = doctorCard(d);
  void _unused;
  return { ...base, experience: num(d.experience), bio: str(d.bio), treatments: list(d.treatments).map(treatmentOption) };
}

export async function fetchDoctors(q: DoctorListQuery, signal?: AbortSignal): Promise<ApiResult<DoctorListData>> {
  const params = new URLSearchParams();
  if (q.hospitalId > 0) params.set("hospitalId", String(q.hospitalId));
  if (q.search !== "") params.set("search", q.search);
  if (q.status !== "all") params.set("status", q.status);
  if (q.page > 1) params.set("page", String(q.page));
  params.set("perPage", String(q.perPage));
  const res = await getJson(`/v1/doctors?${params.toString()}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const s = obj(d.stats);
  return {
    ok: true,
    data: {
      scope: d.scope === "own" ? "own" : "all",
      stats: { total: num(s.total), hospitals: num(s.hospitals), active: num(s.active) },
      total: num(d.total),
      page: Math.max(1, num(d.page)),
      perPage: num(d.perPage) || q.perPage,
      items: list(d.items).map(doctorCard),
    },
  };
}

export async function fetchDoctor(id: number, signal?: AbortSignal): Promise<ApiResult<DoctorFull>> {
  const res = await getJson(`/v1/doctors/${encodeURIComponent(String(id))}`, signal);
  return res.ok ? { ok: true, data: doctorFull(res.data) } : res;
}

export async function fetchDoctorHospitals(signal?: AbortSignal): Promise<ApiResult<DoctorHospitalOption[]>> {
  const res = await getJson("/v1/doctors/hospitals", signal);
  if (!res.ok) return res;
  return { ok: true, data: list(obj(res.data).items).map((raw) => ({ id: num(obj(raw).id), name: str(obj(raw).name) })) };
}

/** The treatments a hospital offers: the only ones a doctor of that hospital may be assigned to. */
export async function fetchDoctorTreatmentOptions(hospitalId: number, signal?: AbortSignal): Promise<ApiResult<DoctorTreatmentOption[]>> {
  const res = await getJson(`/v1/doctors/treatment-options?hospitalId=${encodeURIComponent(String(hospitalId))}`, signal);
  return res.ok ? { ok: true, data: list(obj(res.data).items).map(treatmentOption) } : res;
}

export async function fetchDoctorOrder(hospitalId: number, signal?: AbortSignal): Promise<ApiResult<DoctorOrderBoard>> {
  const res = await getJson(`/v1/doctors/order?hospitalId=${encodeURIComponent(String(hospitalId))}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  return {
    ok: true,
    data: {
      hospitalId: num(d.hospitalId),
      orderable: d.orderable === true,
      total: num(d.total),
      items: list(d.items).map((raw) => {
        const i = obj(raw);
        return { id: num(i.id), name: str(i.name), specialty: str(i.specialty), photoUrl: photoOf(i.photoUrl), position: num(i.position) };
      }),
    },
  };
}

export type DoctorEditInput = { name: string; hospitalId: number; specialty: string; qualification: string; treatmentIds: number[] };
export type DoctorEditFailure = {
  ok: false;
  /** 0 = no answer (network down, proxy error page). */
  status: number;
  /** "name_required", "hospital_required", "invalid_field", "invalid_type", "too_large", "doctor_resigned", "upload_failed", ...; "" when none. */
  error: string;
  field: string;
};
export type DoctorEditResult = { ok: true; data: DoctorFull } | DoctorEditFailure;

/** Saves the doctor: a `doctor` JSON text and at most one `photo` file in one multipart request (the browser adds the boundary itself). */
export async function editDoctor(id: number, input: DoctorEditInput, photo: File | null, signal?: AbortSignal): Promise<DoctorEditResult> {
  const body = new FormData();
  body.append("doctor", JSON.stringify(input));
  if (photo) body.append("photo", photo);
  try {
    const res = await fetch(`${BASE_PATH}/api/v1/doctors/${encodeURIComponent(String(id))}`, {
      method: "PATCH",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", "X-Requested-With": "admin-next" },
      body,
      signal,
    });
    let data: unknown = null;
    try {
      data = await res.json();
    } catch {
      // An empty or non-JSON body: the status alone says what happened.
    }
    const d = obj(data);
    if (!res.ok) return { ok: false, status: res.status, error: str(d.error), field: str(d.field) };
    return { ok: true, data: doctorFull(d) };
  } catch {
    return { ok: false, status: 0, error: "", field: "" };
  }
}

export async function retireDoctor(id: number): Promise<WriteResult<DoctorFull>> {
  const res = await sendJson("POST", `/v1/doctors/${encodeURIComponent(String(id))}/retire`, {});
  return res.ok ? { ok: true, data: doctorFull(res.data) } : res;
}

export async function moveDoctor(id: number, move: { direction: "up" | "down" } | { position: number }): Promise<WriteResult<{ position: number; total: number; changed: number }>> {
  const res = await sendJson("POST", `/v1/doctors/${encodeURIComponent(String(id))}/order`, move);
  return res.ok ? { ok: true, data: { position: num(res.data.position), total: num(res.data.total), changed: num(res.data.changed) } } : res;
}

/** Super admin only; a retired doctor only. Cannot be undone. */
export async function deleteDoctor(id: number): Promise<WriteResult<{ name: string }>> {
  const res = await sendJson("POST", `/v1/doctors/${encodeURIComponent(String(id))}/delete`, { confirm: true });
  return res.ok ? { ok: true, data: { name: str(res.data.name) } } : res;
}

/** Hides a doctor from the app (inactive) or shows the doctor again. Treatments stay assigned; going inactive clears the place in the order. */
export async function setDoctorActive(id: number, active: boolean): Promise<WriteResult<DoctorFull>> {
  const res = await sendJson("POST", `/v1/doctors/${encodeURIComponent(String(id))}/${active ? "activate" : "deactivate"}`, {});
  return res.ok ? { ok: true, data: doctorFull(res.data) } : res;
}

// ---- content review (reviews and community posts waiting for a decision) ------------------------------------------------

export type ModerationState = "pending" | "approved" | "rejected";
export type ModerationCounts = Record<ModerationState, number>;
export type ReviewItem = {
  id: number;
  hospitalId: number;
  hospitalName: string;
  author: string;
  title: string;
  content: string;
  rating: number;
  treatments: string[];
  doctorName: string;
  hasBooking: boolean;
  state: ModerationState;
  createdAt: string;
  images: string[];
  /** Sent back with the decision, so an item edited in the meantime is never decided blind. */
  revision: string;
};
export type PostItem = {
  id: number;
  category: string;
  author: string;
  title: string;
  content: string;
  images: string[];
  state: ModerationState;
  createdAt: string;
  revision: string;
};
export type ModerationPage<T> = { status: ModerationState; page: number; perPage: number; hasMore: boolean; counts: ModerationCounts; items: T[] };
export type ModerationQuery = { status: ModerationState; search: string; hospitalId: number; page: number };
export type ModerationDecision = "approve" | "reject";

const moderationState = (v: unknown): ModerationState => (v === "approved" || v === "rejected" ? v : "pending");

function moderationParams(q: ModerationQuery, withHospital: boolean): string {
  const params = new URLSearchParams();
  params.set("status", q.status);
  if (q.search !== "") params.set("search", q.search);
  if (withHospital && q.hospitalId > 0) params.set("hospitalId", String(q.hospitalId));
  if (q.page > 1) params.set("page", String(q.page));
  return params.toString();
}

function moderationPage<T>(raw: unknown, map: (r: unknown) => T): ModerationPage<T> {
  const d = obj(raw);
  const c = obj(d.counts);
  return {
    status: moderationState(d.status),
    page: Math.max(1, num(d.page)),
    perPage: num(d.perPage) || 20,
    hasMore: d.hasMore === true,
    counts: { pending: num(c.pending), approved: num(c.approved), rejected: num(c.rejected) },
    items: list(d.items).map(map),
  };
}

const reviewItem = (raw: unknown): ReviewItem => {
  const r = obj(raw);
  return {
    id: num(r.id),
    hospitalId: num(r.hospitalId),
    hospitalName: str(r.hospitalName),
    author: str(r.author),
    title: str(r.title),
    content: str(r.content),
    rating: Math.min(5, Math.max(0, num(r.rating))),
    treatments: list(r.treatments).map(str).filter((x) => x !== ""),
    doctorName: str(r.doctorName),
    hasBooking: r.hasBooking === true,
    state: moderationState(r.state),
    createdAt: str(r.createdAt),
    images: list(r.images).map(str).filter((x) => x !== ""),
    revision: str(r.revision),
  };
};

const postItem = (raw: unknown): PostItem => {
  const r = obj(raw);
  return {
    id: num(r.id),
    category: str(r.category),
    author: str(r.author),
    title: str(r.title),
    content: str(r.content),
    images: list(r.images).map(str).filter((x) => x !== ""),
    state: moderationState(r.state),
    createdAt: str(r.createdAt),
    revision: str(r.revision),
  };
};

export async function fetchModerationReviews(q: ModerationQuery, signal?: AbortSignal): Promise<ApiResult<ModerationPage<ReviewItem>>> {
  const res = await getJson(`/v1/moderation/reviews?${moderationParams(q, true)}`, signal);
  return res.ok ? { ok: true, data: moderationPage(res.data, reviewItem) } : res;
}

/** Super admin only: a hospital account is answered 403. */
export async function fetchModerationPosts(q: ModerationQuery, signal?: AbortSignal): Promise<ApiResult<ModerationPage<PostItem>>> {
  const res = await getJson(`/v1/moderation/community?${moderationParams(q, false)}`, signal);
  return res.ok ? { ok: true, data: moderationPage(res.data, postItem) } : res;
}

export async function fetchModerationHospitals(signal?: AbortSignal): Promise<ApiResult<DoctorHospitalOption[]>> {
  const res = await getJson("/v1/moderation/hospitals", signal);
  if (!res.ok) return res;
  return { ok: true, data: list(obj(res.data).items).map((x) => ({ id: num(obj(x).id), name: str(obj(x).name) })).filter((x) => x.id > 0) };
}

/** Approve or reject one pending review. 409 "changed" = edited meanwhile, 409 "already_done" = decided by someone else. */
export async function decideReview(id: number, decision: ModerationDecision, revision: string): Promise<WriteResult<{ id: number; state: ModerationState }>> {
  const res = await sendJson("POST", `/v1/moderation/reviews/${encodeURIComponent(String(id))}/${decision}`, { revision });
  return res.ok ? { ok: true, data: { id: num(obj(res.data).id), state: moderationState(obj(res.data).state) } } : res;
}

/** Same for a community post (super admin only). */
export async function decidePost(id: number, decision: ModerationDecision, revision: string): Promise<WriteResult<{ id: number; state: ModerationState }>> {
  const res = await sendJson("POST", `/v1/moderation/community/${encodeURIComponent(String(id))}/${decision}`, { revision });
  return res.ok ? { ok: true, data: { id: num(obj(res.data).id), state: moderationState(obj(res.data).state) } } : res;
}

/** What a review edit sends: the whole editable state (title, text, rating, treatment names) plus the picture links to drop. The author is never editable. */
export type ReviewEditInput = { title: string; content: string; rating: number; treatments: string[]; removeImages: string[] };
export type PostEditInput = { category: string; title: string; content: string; removeImages: string[] };

/** The treatments a hospital offers: the names the review edit form may add. */
export async function fetchReviewTreatmentOptions(hospitalId: number, signal?: AbortSignal): Promise<ApiResult<string[]>> {
  const res = await getJson(`/v1/moderation/hospitals/${encodeURIComponent(String(hospitalId))}/treatments`, signal);
  return res.ok ? { ok: true, data: list(obj(res.data).items).map(str).filter((x) => x !== "") } : res;
}

/** The categories posts use today: the select of the post edit form (super admin only). */
export async function fetchPostCategories(signal?: AbortSignal): Promise<ApiResult<string[]>> {
  const res = await getJson("/v1/moderation/community/categories", signal);
  return res.ok ? { ok: true, data: list(obj(res.data).items).map(str).filter((x) => x !== "") } : res;
}

const itemId = (id: number): string => encodeURIComponent(String(id));
const savedId = (res: WriteResult<Json>): WriteResult<{ id: number }> => (res.ok ? { ok: true, data: { id: num(obj(res.data).id) } } : res);

/** Saves a review edit. 409 "changed" = edited meanwhile; 400 "invalid" carries per-field codes in `fields`. */
export async function editReview(id: number, revision: string, edit: ReviewEditInput): Promise<WriteResult<{ id: number }>> {
  return savedId(await sendJson("PATCH", `/v1/moderation/reviews/${itemId(id)}`, { revision, ...edit }));
}

/** Same for a community post (super admin only). */
export async function editPost(id: number, revision: string, edit: PostEditInput): Promise<WriteResult<{ id: number }>> {
  return savedId(await sendJson("PATCH", `/v1/moderation/community/${itemId(id)}`, { revision, ...edit }));
}

/** Removes a review for good, with its pictures, likes, comments and reports. Points are not touched. */
export async function deleteReview(id: number, revision: string): Promise<WriteResult<{ id: number }>> {
  return savedId(await sendJson("DELETE", `/v1/moderation/reviews/${itemId(id)}`, { revision }));
}

/** Same for a community post (super admin only). */
export async function deletePost(id: number, revision: string): Promise<WriteResult<{ id: number }>> {
  return savedId(await sendJson("DELETE", `/v1/moderation/community/${itemId(id)}`, { revision }));
}

// ---- banners (home page and my page; super admin only) ---------------------------------------------------------------

export type BannerView = "all" | "visible" | "hidden";
export type BannerQuery = { view: BannerView; search: string; page: number };
export type BannerItem = {
  id: number;
  title: string;
  subtitle: string;
  image: string;
  mypageImage: string;
  linkUrl: string;
  displayOrder: number;
  hospitalId: number;
  hospitalName: string;
  status: string;
  homeEnabled: boolean;
  visible: boolean;
  createdAt: string;
  revision: string;
};
export type BannerCounts = { all: number; visible: number; hidden: number };
export type BannerPageData = { view: BannerView; page: number; perPage: number; hasMore: boolean; counts: BannerCounts; items: BannerItem[] };
export type BannerScroll = { enabled: boolean; intervalSeconds: number };
/** What the banner form sends. `imageUrl` / `mypageImageUrl` are the stored pictures the form keeps; a chosen file replaces them. */
export type BannerInput = {
  title: string;
  subtitle: string;
  linkUrl: string;
  displayOrder: string;
  hospitalId: number;
  imageUrl: string;
  mypageImageUrl: string;
  image: File | null;
  mypageImage: File | null;
};

const bannerPath = (id: number): string => `/v1/banners/${encodeURIComponent(String(id))}`;

const bannerItem = (raw: unknown): BannerItem => {
  const r = obj(raw);
  return {
    id: num(r.id),
    title: str(r.title),
    subtitle: str(r.subtitle),
    image: str(r.image),
    mypageImage: str(r.mypageImage),
    linkUrl: str(r.linkUrl),
    displayOrder: num(r.displayOrder),
    hospitalId: num(r.hospitalId),
    hospitalName: str(r.hospitalName),
    status: str(r.status),
    homeEnabled: r.homeEnabled === true,
    visible: r.visible === true,
    createdAt: str(r.createdAt),
    revision: str(r.revision),
  };
};

export async function fetchBanners(q: BannerQuery, signal?: AbortSignal): Promise<ApiResult<BannerPageData>> {
  const params = new URLSearchParams();
  params.set("view", q.view);
  if (q.search !== "") params.set("search", q.search);
  if (q.page > 1) params.set("page", String(q.page));
  const res = await getJson(`/v1/banners?${params.toString()}`, signal);
  if (!res.ok) return res;
  const d = obj(res.data);
  const c = obj(d.counts);
  const view: BannerView = d.view === "visible" || d.view === "hidden" ? d.view : "all";
  return {
    ok: true,
    data: { view, page: Math.max(1, num(d.page)), perPage: num(d.perPage) || 20, hasMore: d.hasMore === true, counts: { all: num(c.all), visible: num(c.visible), hidden: num(c.hidden) }, items: list(d.items).map(bannerItem) },
  };
}

export async function fetchBannerHospitals(signal?: AbortSignal): Promise<ApiResult<DoctorHospitalOption[]>> {
  const res = await getJson("/v1/banners/hospitals", signal);
  if (!res.ok) return res;
  return { ok: true, data: list(obj(res.data).items).map((x) => ({ id: num(obj(x).id), name: str(obj(x).name) })).filter((x) => x.id > 0) };
}

const bannerScroll = (raw: unknown): BannerScroll => ({ enabled: obj(raw).enabled === true, intervalSeconds: Math.min(60, Math.max(1, num(obj(raw).intervalSeconds) || 10)) });

export async function fetchBannerScroll(signal?: AbortSignal): Promise<ApiResult<BannerScroll>> {
  const res = await getJson("/v1/banners/scroll", signal);
  return res.ok ? { ok: true, data: bannerScroll(res.data) } : res;
}

export async function saveBannerScroll(value: BannerScroll): Promise<WriteResult<BannerScroll>> {
  const res = await sendJson("PUT", "/v1/banners/scroll", value);
  return res.ok ? { ok: true, data: bannerScroll(res.data) } : res;
}

export async function setBannerHome(id: number, enabled: boolean): Promise<WriteResult<{ id: number; homeEnabled: boolean }>> {
  const res = await sendJson("POST", `${bannerPath(id)}/home`, { enabled });
  return res.ok ? { ok: true, data: { id: num(obj(res.data).id), homeEnabled: obj(res.data).homeEnabled === true } } : res;
}

export async function deleteBanner(id: number, revision: string): Promise<WriteResult<{ id: number }>> {
  const res = await sendJson("DELETE", bannerPath(id), { revision });
  return res.ok ? { ok: true, data: { id: num(obj(res.data).id) } } : res;
}

/** Creates (no `revision`) or edits (with the revision the page was read with) one banner. Multipart: the browser sets Content-Type itself. */
export async function saveBanner(input: BannerInput, target: { id: number; revision: string } | null): Promise<WriteResult<{ id: number }>> {
  const body = new FormData();
  body.append("title", input.title);
  body.append("subtitle", input.subtitle);
  body.append("linkUrl", input.linkUrl);
  body.append("displayOrder", input.displayOrder);
  body.append("hospitalId", input.hospitalId > 0 ? String(input.hospitalId) : "");
  body.append("imageUrl", input.imageUrl);
  body.append("mypageImageUrl", input.mypageImageUrl);
  if (target) body.append("revision", target.revision);
  if (input.image) body.append("image", input.image);
  if (input.mypageImage) body.append("mypageImage", input.mypageImage);
  const fail = (status: number, d: Record<string, unknown> = {}): WriteFailure => {
    const fields: Record<string, string> = {};
    for (const [name, code] of Object.entries(obj(d.fields))) if (typeof code === "string") fields[name] = code;
    return { ok: false, status, fields, updatedAt: "", error: str(d.error), blockers: [], deletableAfter: "" };
  };
  try {
    const res = await fetch(`${BASE_PATH}/api${target ? bannerPath(target.id) : "/v1/banners"}`, {
      method: target ? "PATCH" : "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", "X-Requested-With": "admin-next" },
      body,
    });
    let data: unknown = null;
    try {
      data = await res.json();
    } catch {
      // An empty or non-JSON body: the status alone says what happened.
    }
    const d = obj(data);
    return res.ok ? { ok: true, data: { id: num(d.id) } } : fail(res.status, d);
  } catch {
    return fail(0);
  }
}

// ───────── Home menu categories page (super admin, behind the category board switch) ─────────

export type BoardTreatment = { id: number; name: string; sortOrder: number; isActive: boolean };
export type BoardCategory = { id: number; name: string; icon: string; sortOrder: number; isActive: boolean; treatments: BoardTreatment[] };
export type BoardData = {
  /** In the order of the app's home menu. */
  categories: BoardCategory[];
  /** Treatments that belong to no category: the app does not show them. */
  uncategorized: BoardTreatment[];
};

const boardTreatment = (raw: unknown): BoardTreatment => {
  const x = obj(raw);
  return { id: num(x.id), name: text(x.name), sortOrder: num(x.sortOrder), isActive: bool(x.isActive) };
};

const boardData = (raw: unknown): BoardData => {
  const d = obj(raw);
  return {
    categories: list(d.categories)
      .map((c) => {
        const x = obj(c);
        return { id: num(x.id), name: text(x.name), icon: str(x.icon), sortOrder: num(x.sortOrder), isActive: bool(x.isActive), treatments: list(x.treatments).map(boardTreatment).filter((t) => t.id > 0) };
      })
      .filter((c) => c.id > 0),
    uncategorized: list(d.uncategorized).map(boardTreatment).filter((t) => t.id > 0),
  };
};

export async function fetchCategoryBoard(signal?: AbortSignal): Promise<ApiResult<BoardData>> {
  const res = await getJson("/v1/category-board", signal);
  return res.ok ? { ok: true, data: boardData(res.data) } : res;
}

const boardPath = (categoryId: number): string => `/v1/category-board/categories/${encodeURIComponent(String(categoryId))}`;
const boardChange = (res: WriteResult<Json>): WriteResult<BoardData> => (res.ok ? { ok: true, data: boardData(res.data) } : res);

/** Adds existing treatments to a category; one that sits in another category (or in none) is moved. */
export async function assignBoardTreatments(categoryId: number, treatmentIds: number[]): Promise<WriteResult<BoardData>> {
  return boardChange(await sendJson("POST", `${boardPath(categoryId)}/treatments`, { treatmentIds }));
}

/** Takes a treatment out of a category: it then belongs to none. */
export async function detachBoardTreatment(categoryId: number, treatmentId: number): Promise<WriteResult<BoardData>> {
  return boardChange(await sendJson("DELETE", `${boardPath(categoryId)}/treatments/${encodeURIComponent(String(treatmentId))}`));
}

/** Deletes a category; its treatments stay and belong to none. `detached` is how many there were. */
export async function deleteBoardCategory(categoryId: number): Promise<WriteResult<BoardData & { detached: number }>> {
  const res = await sendJson("DELETE", boardPath(categoryId));
  return res.ok ? { ok: true, data: { ...boardData(res.data), detached: num(obj(res.data).detached) } } : res;
}
