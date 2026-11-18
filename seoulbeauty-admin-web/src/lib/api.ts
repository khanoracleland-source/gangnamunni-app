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
 * `treatments`: the treatment master page is open (read); `treatmentsWrite`: it may be changed here; `treatmentsImages`: image files may be uploaded for it.
 */
export type Features = { hospitals: boolean; hospitalsWrite: boolean; hospitalsDelete: boolean; hospitalsReview: boolean; hospitalsImages: boolean; hospitalsDocuments: boolean; hospitalsDoctors: boolean; hospitalsTreatments: boolean; hospitalsTreatmentsRemove: boolean; hospitalsTreatmentsPrices: boolean; hospitalsTreatmentsQuestions: boolean; hospitalsTreatmentsImages: boolean; archive: boolean; treatments: boolean; treatmentsWrite: boolean; treatmentsImages: boolean };

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
      for (const [name, code] of Object.entries(obj(d.fields))) if (typeof code === "string") fields[name] = code;
      return {
        ok: false,
        status: res.status,
        fields,
        updatedAt: str(d.updatedAt),
        error: str(d.error),
        ...(typeof d.index === "number" ? { index: d.index } : {}),
        ...(typeof d.field === "string" ? { field: d.field } : {}),
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
