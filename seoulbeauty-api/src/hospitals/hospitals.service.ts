import { Inject, Injectable, NotFoundException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { jsonArray } from '../common/parse';
import { buildHospitalListQuery, HospitalListParams, SchemaFlags } from './hospital-query';

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
}

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
  };
}

@Injectable()
export class HospitalsService {
  private flags: SchemaFlags = { hasDisplayOrder: false, hasWishlist: false, hasShowRating: false };
  private flagsLoaded = false;

  constructor(@Inject(DB) private readonly db: Db) {}

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

  async list(p: Omit<HospitalListParams, 'treatmentId'>) {
    let treatmentId = 0;
    if (p.treatment) {
      const [rows] = (await this.db.query(
        'SELECT id FROM gangnamunni_treatment_master WHERE name = ? AND is_active = 1 LIMIT 1',
        [p.treatment],
      )) as any;
      treatmentId = rows[0] ? Number(rows[0].id) : 0;
    }
    const flags = await this.getFlags();
    const q = buildHospitalListQuery({ ...p, treatmentId }, flags);
    const [[rows], [cnt]] = (await Promise.all([
      this.db.query(q.sql, q.values),
      this.db.query(q.countSql, q.countValues),
    ])) as any;
    return {
      items: (rows as any[]).map(toHospitalDto),
      total: Number(cnt[0]?.total ?? 0),
      limit: p.limit,
      offset: p.offset,
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
    return toHospitalDto(rows[0]);
  }
}
