import { likeEscape } from '../common/parse';

export type HospitalSort = 'manual' | 'price' | 'rating' | 'popular' | 'review';
export const HOSPITAL_SORTS: HospitalSort[] = ['manual', 'price', 'rating', 'popular', 'review'];

export interface HospitalListParams {
  q: string;
  category: string;
  treatment: string; // exact treatment name
  treatmentId: number; // effective treatment id: `treatment`, or resolved from `q` (0 = none)
  /** treatmentId came from the keyword resolver -> the keyword also matches that treatment's hospitals */
  resolvedFromKeyword?: boolean;
  /** whitelisted dosage option (only with treatmentId) */
  dosage?: { value: number; unit: string } | null;
  regions: string[];
  sort: HospitalSort;
  limit: number;
  offset: number;
  /** mb_no of the logged-in member → adds `wishlisted` per row (0 = anonymous) */
  memberNo?: number;
}

export interface SchemaFlags {
  hasDisplayOrder: boolean;
  hasWishlist: boolean;
  hasShowRating: boolean;
}

export interface BuiltQuery {
  sql: string;
  values: unknown[];
  countSql: string;
  countValues: unknown[];
}

const like = (s: string) => `%${likeEscape(s)}%`;

/**
 * Mirrors includes/hospital_list_data.php (PHP) but fully parameterised.
 * Differences (intentional): LIKE wildcards are escaped, no string-interpolated SQL.
 * Not yet ported: region_mix. Ad-slot pinning and in-feed ads live in HospitalsService.list().
 */
export function buildHospitalListQuery(p: HospitalListParams, f: SchemaFlags): BuiltQuery {
  const where: string[] = [
    'h.is_active = 1',
    'COALESCE(h.is_demo, 0) = 0',
    "(h.status = 'approved' OR h.status IS NULL)",
  ];
  const wv: unknown[] = [];
  const dosageCond = (alias: string) => (p.dosage ? ` AND ABS(${alias}.dosage_value - ?) < 0.0001 AND ${alias}.dosage_unit = ?` : '');
  const dosageVals = () => (p.dosage ? [p.dosage.value, p.dosage.unit] : []);

  if (p.q) {
    const resolved = p.resolvedFromKeyword && p.treatmentId > 0;
    where.push(`(
      h.name LIKE ? OR h.address LIKE ? OR h.treatment_tags LIKE ?
      OR h.id IN (
        SELECT ht.hospital_id FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        WHERE tm.name LIKE ? AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1
      )${resolved ? ` OR h.id IN (
        SELECT ht.hospital_id FROM gangnamunni_hospital_treatments ht
        WHERE ht.treatment_id = ? AND ht.status = 'active' AND ht.is_active = 1
      )` : ''}
    )`);
    wv.push(like(p.q), like(p.q), like(p.q), like(p.q));
    if (resolved) wv.push(p.treatmentId);
  }
  if (p.category) {
    where.push('h.treatment_tags LIKE ?');
    wv.push(like(p.category));
  }
  if (p.dosage && p.treatmentId > 0) {
    where.push(`h.id IN (
      SELECT ht.hospital_id FROM gangnamunni_hospital_treatments ht
      INNER JOIN gangnamunni_hospital_treatment_prices p ON p.hospital_treatment_id = ht.id AND p.price > 0${dosageCond('p')}
      WHERE ht.treatment_id = ? AND ht.status = 'active' AND ht.is_active = 1
    )`);
    wv.push(...dosageVals(), p.treatmentId);
  }
  if (p.treatment) {
    where.push(`h.id IN (
      SELECT ht.hospital_id FROM gangnamunni_hospital_treatments ht
      INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
      WHERE tm.name = ? AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1
    )`);
    wv.push(p.treatment);
  }
  if (p.regions.length) {
    where.push('(' + p.regions.map(() => 'h.address LIKE ?').join(' OR ') + ')');
    for (const r of p.regions) wv.push(like(r));
  }

  const wishlist = f.hasWishlist
    ? "(SELECT COUNT(*) FROM gangnamunni_wishlist gw WHERE gw.target_type = 'hospital' AND gw.target_id = h.id)"
    : '0';

  let order: string;
  let orderValues: unknown[] = [];
  switch (p.sort) {
    case 'rating':
      order = 'h.rating DESC, h.review_count DESC, h.id ASC';
      break;
    case 'review':
      order = 'h.review_count DESC, h.rating DESC, h.id ASC';
      break;
    case 'popular':
      order = 'wishlist_count DESC, h.rating DESC, h.review_count DESC, h.id ASC';
      break;
    case 'price':
      if (p.treatmentId > 0) {
        order = `COALESCE(
          NULLIF((
            SELECT tp.price FROM gangnamunni_hospital_treatments sht
            INNER JOIN gangnamunni_hospital_treatment_prices tp
              ON tp.hospital_treatment_id = sht.id AND tp.price > 0${dosageCond('tp')}
            WHERE sht.hospital_id = h.id AND sht.treatment_id = ?
              AND sht.status = 'active' AND sht.is_active = 1
            ORDER BY tp.price ASC, tp.sort_order ASC, tp.id ASC LIMIT 1
          ), 0),
          NULLIF((
            SELECT COALESCE(NULLIF(stm.avg_price_min, 0), NULLIF(stm.avg_price_max, 0))
            FROM gangnamunni_treatment_master stm WHERE stm.id = ? AND stm.is_active = 1 LIMIT 1
          ), 0),
          2147483647
        ) ASC, h.rating DESC, h.id ASC`;
        orderValues = [...dosageVals(), p.treatmentId, p.treatmentId];
      } else {
        order = 'COALESCE(NULLIF(h.min_price, 0), 2147483647) ASC, h.rating DESC, h.id ASC';
      }
      break;
    default:
      order = f.hasDisplayOrder
        ? 'CASE WHEN h.display_order IS NULL OR h.display_order < 1 THEN 1 ELSE 0 END, h.display_order ASC, h.id ASC'
        : 'h.id ASC';
  }

  const whereSql = 'WHERE ' + where.join(' AND ');
  // raw_data is large internal JSON; never select it.
  const cols = `h.id, h.name, h.description, h.introduction, h.address, h.phone, h.profile_image_url,
    h.main_image_url, h.rating, h.review_count, h.latitude, h.longitude, h.categories, h.badges,
    h.treatment_tags, h.subway_names, h.business_hours, h.min_price,
    h.active_phone_consultation, h.active_chat_consultation, h.active_general_consultation,
    ${f.hasShowRating ? 'h.show_rating' : '1'} AS show_rating`;

  const mine = p.memberNo
    ? "EXISTS(SELECT 1 FROM gangnamunni_wishlist mw WHERE mw.member_id = ? AND mw.target_type = 'hospital' AND mw.target_id = h.id)"
    : '0';
  return {
    sql: `SELECT ${cols}, ${wishlist} AS wishlist_count, ${mine} AS wishlisted
          FROM gangnamunni_hospitals h ${whereSql}
          ORDER BY ${order} LIMIT ? OFFSET ?`,
    values: [...(p.memberNo ? [p.memberNo] : []), ...wv, ...orderValues, p.limit, p.offset],
    countSql: `SELECT COUNT(*) AS total FROM gangnamunni_hospitals h ${whereSql}`,
    countValues: wv,
  };
}
