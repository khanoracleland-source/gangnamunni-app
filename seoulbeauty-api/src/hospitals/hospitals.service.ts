import { Inject, Injectable, NotFoundException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { jsonArray } from '../common/parse';
import { buildHospitalListQuery, HospitalListParams, HospitalSort, SchemaFlags } from './hospital-query';
import { SearchService } from '../search/search.service';

export interface HospitalDto {
  id: number;
  name: string;
  description: string | null;
  introduction: string | null;
  address: string | null;
  phone: string | null;
  profileImageUrl: string | null;
  mainImageUrl: string | null;
  rating: number | null;
  reviewCount: number;
  minPrice: number | null;
  latitude: number | null;
  longitude: number | null;
  categories: string[];
  badges: string[];
  treatmentTags: string[];
  subwayNames: string[];
  businessHours: unknown;
  consultation: { phone: boolean; chat: boolean; general: boolean };
  wishlistCount: number;
  showRating: boolean;
  /** Same priority as PHP hospital_list_content.php: hospital_images main > main_image_url > hospital_images logo > profile_image_url */
  thumbnailUrl: string | null;
  /** only meaningful for a logged-in caller; false otherwise */
  wishlisted: boolean;
  /** price line for the effective treatment (hospital_list_data.php $hospital_treatment_descs); null outside treatment searches */
  treatment: TreatmentLine | null;
  /** paid keyword slot (gangnamunni_ad_slot_bookings) — pinned to the top like PHP; tracking is done by the web side */
  adSlot: { bookingId: number; rank: number } | null;
}

export interface TreatmentLine {
  name: string; desc: string | null; priceMin: number; priceMax: number; dosageValue: number | null; dosageUnit: string | null;
}

export interface InfeedAd { id: number; title: string; subtitle: string | null; imageUrl: string | null; linkUrl: string; hospitalId: number | null }

export type ListInput = Omit<HospitalListParams, 'treatmentId' | 'resolvedFromKeyword' | 'dosage' | 'sort'> & { sort: HospitalSort | ''; dosage: string };

const num = (v: unknown): number | null => (v === null || v === undefined || v === '' ? null : Number(v));

function parseJson(v: unknown): unknown {
  if (v == null || v === '') return null;
  try {
    return typeof v === 'string' ? JSON.parse(v) : v;
  } catch {
    return null;
  }
}

export function toHospitalDto(r: Record<string, any>): HospitalDto {
  return {
    id: Number(r.id),
    name: r.name,
    description: r.description ?? null,
    introduction: r.introduction ?? null,
    address: r.address ?? null,
    phone: r.phone ?? null,
    profileImageUrl: r.profile_image_url ?? null,
    mainImageUrl: r.main_image_url ?? null,
    // Admin can hide the rating (hospitals.show_rating = 0, see detail.php); don't leak it via the API.
    showRating: r.show_rating == null || Number(r.show_rating) === 1,
    rating: r.show_rating == null || Number(r.show_rating) === 1 ? Number(r.rating ?? 0) : (null as any),
    reviewCount: Number(r.review_count ?? 0),
    minPrice: num(r.min_price),
    latitude: num(r.latitude),
    longitude: num(r.longitude),
    categories: jsonArray(r.categories),
    badges: jsonArray(r.badges),
    treatmentTags: jsonArray(r.treatment_tags),
    subwayNames: jsonArray(r.subway_names),
    businessHours: parseJson(r.business_hours),
    consultation: {
      phone: !!r.active_phone_consultation,
      chat: !!r.active_chat_consultation,
      general: !!r.active_general_consultation,
    },
    wishlistCount: Number(r.wishlist_count ?? 0),
    thumbnailUrl: r.__main_img || r.main_image_url || r.__logo_img || r.profile_image_url || null,
    wishlisted: !!Number(r.wishlisted ?? 0),
    treatment: r.__treatment ?? null,
    adSlot: r.__ad_slot ?? null,
  };
}

@Injectable()
export class HospitalsService {
  private flags: SchemaFlags = { hasDisplayOrder: false, hasWishlist: false, hasShowRating: false };
  private flagsLoaded = false;
  private priceCols: { regular: boolean; representative: boolean } | null = null;

  constructor(@Inject(DB) private readonly db: Db, private readonly search: SearchService) {}

  /** Optional columns of gangnamunni_hospital_treatment_prices (PHP probes per request). */
  private async getPriceCols() {
    if (this.priceCols) return this.priceCols;
    const [r] = (await this.db.query("SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'regular_price'")) as any;
    const [p] = (await this.db.query("SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'is_representative'")) as any;
    this.priceCols = { regular: r.length > 0, representative: p.length > 0 };
    return this.priceCols;
  }

  /**
   * Optional-column probing (the PHP does it per request). Lazy and retried: a failed probe
   * (e.g. DB not up yet) is never cached, so flags can't get stuck at the wrong defaults.
   */
  private async getFlags(): Promise<SchemaFlags> {
    if (this.flagsLoaded) return this.flags;
    const [d] = (await this.db.query("SHOW COLUMNS FROM gangnamunni_hospitals LIKE 'display_order'")) as any;
    const [s] = (await this.db.query("SHOW COLUMNS FROM gangnamunni_hospitals LIKE 'show_rating'")) as any;
    const [w] = (await this.db.query(
      "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'gangnamunni_wishlist' LIMIT 1",
    )) as any;
    this.flags = { hasDisplayOrder: d.length > 0, hasShowRating: s.length > 0, hasWishlist: w.length > 0 };
    this.flagsLoaded = true;
    return this.flags;
  }

  /** hospital_images (is_active, image_type main/logo), first by sort_order — mirrors hospital_list_data.php. */
  private async attachImages(rows: Record<string, any>[]): Promise<void> {
    const ids = rows.map((r) => Number(r.id)).filter((n) => Number.isInteger(n) && n > 0);
    if (!ids.length) return;
    const [imgs] = (await this.db.query(
      `SELECT hospital_id, image_url, image_type FROM hospital_images
       WHERE hospital_id IN (${ids.map(() => '?').join(',')}) AND is_active = 1 AND image_type IN ('main','logo')
       ORDER BY hospital_id ASC, sort_order ASC, id ASC`,
      ids,
    )) as any;
    const main = new Map<number, string>();
    const logo = new Map<number, string>();
    for (const i of imgs as any[]) {
      const hid = Number(i.hospital_id);
      if (!i.image_url) continue;
      if (i.image_type === 'main' && !main.has(hid)) main.set(hid, i.image_url);
      if (i.image_type === 'logo' && !logo.has(hid)) logo.set(hid, i.image_url);
    }
    for (const r of rows) {
      r.__main_img = main.get(Number(r.id)) ?? null;
      r.__logo_img = logo.get(Number(r.id)) ?? null;
    }
  }

  /**
   * hospitals.php list. Mirrors hospital_list_data.php: keyword -> treatment resolver, default price sort,
   * dosage whitelist, per-hospital treatment price line, paid keyword slots pinned first (non-price sorts),
   * and the in-feed ad settings for the web side to interleave.
   */
  async list(p: ListInput) {
    // Effective treatment: explicit `treatment` wins; otherwise the keyword may resolve to one.
    let treatmentId = 0;
    let effectiveName = p.treatment;
    let resolvedFromKeyword = false;
    if (p.treatment) {
      treatmentId = await this.search.treatmentIdByName(p.treatment);
    } else if (p.q) {
      const r = await this.search.resolveTreatment(p.q);
      if (r) { treatmentId = r.id; effectiveName = r.name; resolvedFromKeyword = true; }
    }
    const dosageOptions = await this.search.dosageOptions(treatmentId);
    const dosage = this.search.dosageSelected(dosageOptions, p.dosage);
    const sort: HospitalSort = p.sort || (treatmentId > 0 ? 'price' : 'manual');

    const flags = await this.getFlags();
    const q = buildHospitalListQuery({ ...p, sort, treatmentId, resolvedFromKeyword, dosage: dosage ? { value: dosage.value, unit: dosage.unit } : null }, flags);
    const [[rowsRaw], [cnt]] = (await Promise.all([
      this.db.query(q.sql, q.values),
      this.db.query(q.countSql, q.countValues),
    ])) as any;
    let rows = rowsRaw as Record<string, any>[];

    // Paid keyword slots: pinned in rank order on the first page, for non-price sorts (hospital_list_data.php).
    if (effectiveName && treatmentId > 0 && sort !== 'price' && p.offset === 0) {
      rows = await this.pinAdSlots(rows, treatmentId, dosage, p.memberNo);
    }
    await this.attachImages(rows);
    if (effectiveName && rows.length) await this.attachTreatmentLine(rows, effectiveName, sort, dosage);

    const infeed = await this.infeedAds();
    return {
      items: rows.map(toHospitalDto),
      total: Number(cnt[0]?.total ?? 0),
      limit: p.limit,
      offset: p.offset,
      sort,
      effectiveTreatment: treatmentId > 0 ? { id: treatmentId, name: effectiveName } : null,
      dosageOptions: dosageOptions.length > 1 ? dosageOptions.map((o) => o.label) : [],
      dosage: dosage ? dosage.label : null,
      infeed,
    };
  }

  /** Active bookings for the treatment keyword (rank order), fetched regardless of the other filters like PHP. */
  private async pinAdSlots(rows: Record<string, any>[], treatmentId: number, dosage: { value: number; unit: string } | null, memberNo?: number) {
    const [slots] = (await this.db.query(
      `SELECT b.id AS booking_id, b.hospital_id, p.rank_position
       FROM gangnamunni_ad_slot_bookings b
       INNER JOIN gangnamunni_ad_slot_products p ON b.product_id = p.id
       WHERE p.keyword_type = 'treatment' AND p.treatment_id = ? AND p.is_active = 1 AND b.status = 'active'
         AND b.start_date <= CURDATE() AND (b.end_date IS NULL OR b.end_date >= CURDATE())
       ORDER BY p.rank_position ASC`,
      [treatmentId],
    )) as any;
    if (!slots.length) return rows;
    const ctx = new Map<number, { bookingId: number; rank: number }>();
    for (const s of slots as any[]) if (!ctx.has(Number(s.hospital_id))) ctx.set(Number(s.hospital_id), { bookingId: Number(s.booking_id), rank: Number(s.rank_position) });
    const ids = [...ctx.keys()];
    const flags = await this.getFlags();
    const vals: unknown[] = [];
    let dosageWhere = '';
    if (dosage) {
      dosageWhere = ` AND h.id IN (SELECT ht.hospital_id FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_hospital_treatment_prices p ON p.hospital_treatment_id = ht.id AND p.price > 0
          AND ABS(p.dosage_value - ?) < 0.0001 AND p.dosage_unit = ?
        WHERE ht.treatment_id = ? AND ht.status = 'active' AND ht.is_active = 1)`;
      vals.push(dosage.value, dosage.unit, treatmentId);
    }
    const mine = memberNo ? "EXISTS(SELECT 1 FROM gangnamunni_wishlist mw WHERE mw.member_id = ? AND mw.target_type = 'hospital' AND mw.target_id = h.id)" : '0';
    const [hs] = (await this.db.query(
      `SELECT h.id, h.name, h.description, h.introduction, h.address, h.phone, h.profile_image_url,
              h.main_image_url, h.rating, h.review_count, h.latitude, h.longitude, h.categories, h.badges,
              h.treatment_tags, h.subway_names, h.business_hours, h.min_price,
              h.active_phone_consultation, h.active_chat_consultation, h.active_general_consultation,
              ${flags.hasShowRating ? 'h.show_rating' : '1'} AS show_rating,
              ${flags.hasWishlist ? "(SELECT COUNT(*) FROM gangnamunni_wishlist gw WHERE gw.target_type='hospital' AND gw.target_id=h.id)" : '0'} AS wishlist_count,
              ${mine} AS wishlisted
       FROM gangnamunni_hospitals h
       WHERE h.id IN (${ids.map(() => '?').join(',')}) AND h.is_active = 1 AND COALESCE(h.is_demo,0) = 0
         AND (h.status = 'approved' OR h.status IS NULL)${dosageWhere}`,
      [...(memberNo ? [memberNo] : []), ...ids, ...vals],
    )) as any;
    const byId = new Map<number, Record<string, any>>((hs as any[]).map((r) => [Number(r.id), r]));
    const pinned: Record<string, any>[] = [];
    for (const id of ids) {
      const r = byId.get(id);
      if (r) { r.__ad_slot = ctx.get(id); pinned.push(r); }
    }
    if (!pinned.length) return rows;
    return [...pinned, ...rows.filter((r) => !ctx.has(Number(r.id)))];
  }

  /** $hospital_treatment_descs: the effective treatment's display price per hospital (representative option, or cheapest for price sort). */
  private async attachTreatmentLine(rows: Record<string, any>[], treatmentName: string, sort: HospitalSort, dosage: { value: number; unit: string } | null) {
    const cols = await this.getPriceCols();
    const order = sort === 'price' || !cols.representative ? 'p.price ASC, p.sort_order ASC, p.id ASC' : 'p.is_representative DESC, p.price ASC, p.sort_order ASC, p.id ASC';
    const dcond = dosage ? ' AND ABS(p.dosage_value - ?) < 0.0001 AND p.dosage_unit = ?' : '';
    const dvals = dosage ? [dosage.value, dosage.unit] : [];
    const sub = (col: string) => `(SELECT ${col} FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0${dcond} ORDER BY ${order} LIMIT 1)`;
    const ids = rows.map((r) => Number(r.id));
    const [tds] = (await this.db.query(
      `SELECT ht.hospital_id, tm.name AS treatment_name, tm.description, ht.dosage_value, ht.dosage_unit,
              ${sub('p.dosage_value')} AS display_dosage_value, ${sub('p.dosage_unit')} AS display_dosage_unit,
              tm.avg_price_min AS default_min, tm.avg_price_max AS default_max,
              ${sub('p.price')} AS display_sale_price, ${sub(cols.regular ? 'p.regular_price' : 'p.price')} AS display_regular_price
       FROM gangnamunni_hospital_treatments ht
       INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
       WHERE ht.hospital_id IN (${ids.map(() => '?').join(',')}) AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1 AND tm.name = ?`,
      [...dvals, ...dvals, ...dvals, ...dvals, ...ids, treatmentName],
    )) as any;
    const map = new Map<number, TreatmentLine>();
    for (const t of tds as any[]) {
      const sale = Number(t.display_sale_price ?? 0);
      const regular = Number(t.display_regular_price ?? 0);
      const defMin = Number(t.default_min ?? 0);
      const defMax = Number(t.default_max ?? 0);
      const priceMin = sale > 0 ? sale : defMin > 0 ? defMin : defMax;
      const priceMax = sale > 0 ? (regular > sale ? regular : 0) : defMin > 0 && defMax > defMin ? defMax : 0;
      const dv = sale > 0 ? t.display_dosage_value : t.dosage_value;
      const du = sale > 0 ? t.display_dosage_unit : t.dosage_unit;
      map.set(Number(t.hospital_id), {
        name: String(t.treatment_name), desc: t.description ?? null, priceMin, priceMax,
        dosageValue: dv != null && Number(dv) > 0 ? Number(dv) : null, dosageUnit: du ? String(du) : null,
      });
    }
    for (const r of rows) r.__treatment = map.get(Number(r.id)) ?? null;
  }

  /** hospital_list_content.php in-feed ads (approved infeed ads whose advertiser has an active slot booking). */
  private async infeedAds(): Promise<{ interval: number; ads: InfeedAd[] } | null> {
    const [st] = (await this.db.query(
      `SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_key IN ('ad_show_hospitals_infeed','ad_infeed_interval')`,
    )) as any;
    const s = new Map<string, string>((st as any[]).map((r) => [r.setting_key, String(r.setting_value)]));
    if ((s.get('ad_show_hospitals_infeed') ?? '1') !== '1') return null;
    const interval = Number(s.get('ad_infeed_interval') ?? 4) || 0;
    if (interval <= 0) return null;
    const [ads] = (await this.db.query(
      `SELECT a.id, a.title, a.subtitle, a.image_url, a.link_url, h.id AS hospital_id
       FROM gangnamunni_ads a
       LEFT JOIN gangnamunni_advertisers adv ON a.advertiser_id = adv.id
       LEFT JOIN gangnamunni_hospitals h ON h.name = adv.hospital_name
       WHERE a.status = 'approved' AND a.ad_type = 'infeed'
         AND EXISTS (SELECT 1 FROM gangnamunni_ad_slot_bookings b WHERE b.advertiser_id = a.advertiser_id AND b.status = 'active'
                     AND b.start_date <= CURDATE() AND (b.end_date IS NULL OR b.end_date >= CURDATE()))
       ORDER BY a.display_order ASC LIMIT 10`,
    )) as any;
    if (!ads.length) return null;
    return {
      interval,
      ads: (ads as any[]).map((a) => ({
        id: Number(a.id), title: String(a.title ?? ''), subtitle: a.subtitle || null, imageUrl: a.image_url || null,
        linkUrl: String(a.link_url || '#'), hospitalId: a.hospital_id ? Number(a.hospital_id) : null,
      })),
    };
  }

  async detail(id: number) {
    const flags = await this.getFlags();
    const [rows] = (await this.db.query(
      `SELECT h.id, h.name, h.description, h.introduction, h.address, h.phone, h.profile_image_url,
              h.main_image_url, h.rating, h.review_count, h.latitude, h.longitude, h.categories, h.badges,
              h.treatment_tags, h.subway_names, h.business_hours, h.min_price,
              h.active_phone_consultation, h.active_chat_consultation, h.active_general_consultation,
              ${flags.hasShowRating ? 'h.show_rating' : '1'} AS show_rating,
              ${
                flags.hasWishlist
                  ? "(SELECT COUNT(*) FROM gangnamunni_wishlist gw WHERE gw.target_type='hospital' AND gw.target_id=h.id)"
                  : '0'
              } AS wishlist_count
       FROM gangnamunni_hospitals h
       WHERE h.id = ? AND h.is_active = 1 AND COALESCE(h.is_demo,0) = 0
         AND (h.status = 'approved' OR h.status IS NULL) LIMIT 1`,
      [id],
    )) as any;
    if (!rows[0]) throw new NotFoundException('hospital not found');
    await this.attachImages(rows);
    return toHospitalDto(rows[0]);
  }
}
