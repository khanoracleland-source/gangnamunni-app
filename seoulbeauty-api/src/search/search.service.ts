import { Inject, Injectable } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { likeEscape } from '../common/parse';

/** Ports of includes/ad_slot_performance.php (adSlotResolveTreatment), includes/hospital_dosage_filter.php and search_suggest.php. */

export interface ResolvedTreatment { id: number; name: string }
export interface DosageOption { value: number; unit: string; label: string }

/** PHP hospital_dosage_label(): 2.00cc -> "2cc", 0.50cc -> "0.5cc" */
export function dosageLabel(value: number, unit: string): string {
  return String(Number(Number(value).toFixed(2))) + unit;
}

/** PHP adSlotNormalizeKeyword(): trim, collapse whitespace, lower-case. */
export function normalizeKeyword(k: string): string {
  return k.trim().replace(/\s+/gu, ' ').toLowerCase();
}

/** PHP extract_addr_district(): first token ending in 구 (2nd token checked first), without the 구. */
export function addrDistrict(addr: string): string {
  const parts = (addr || '').trim().split(/\s+/u).filter(Boolean);
  const order = parts[1] ? [parts[1], ...parts] : parts;
  for (const p of order) if (p.endsWith('구')) return p.slice(0, -1);
  return '';
}

const VISIBLE = "h.is_active = 1 AND COALESCE(h.is_demo,0) = 0 AND (h.status = 'approved' OR h.status IS NULL)";

@Injectable()
export class SearchService {
  constructor(@Inject(DB) private readonly db: Db) {}

  /**
   * Keyword -> best matching active treatment (name, alias, hashtag), same ranking as PHP.
   * PHP's ORDER BY leaves ties (e.g. 주름 보톡스 / 침샘 보톡스 for "보톡스") to the DB, which is not stable
   * between query plans; we break ties with the site's own treatment order (sort_order, popularity, id).
   */
  async resolveTreatment(keyword: string): Promise<ResolvedTreatment | null> {
    const k = normalizeKeyword(keyword);
    if (!k) return null;
    const like = `%${likeEscape(k)}%`;
    const [rows] = (await this.db.query(
      `SELECT matched.id, matched.name FROM (
         SELECT tm.id, tm.name, tm.sort_order, tm.popularity_score,
                MIN(CASE WHEN tm.name = ? THEN 0 WHEN tm.name LIKE ? THEN 1 WHEN ta.alias = ? THEN 2
                         WHEN tm.hashtag = ? THEN 3 WHEN ta.alias LIKE ? THEN 4 ELSE 5 END) AS match_rank
         FROM gangnamunni_treatment_master tm
         LEFT JOIN gangnamunni_treatment_aliases ta ON ta.treatment_id = tm.id
         WHERE tm.is_active = 1 AND (tm.name = ? OR ta.alias = ? OR tm.hashtag = ? OR tm.name LIKE ? OR ta.alias LIKE ? OR tm.hashtag LIKE ?)
         GROUP BY tm.id, tm.name, tm.sort_order, tm.popularity_score
       ) matched ORDER BY matched.match_rank ASC, CHAR_LENGTH(matched.name) ASC, matched.sort_order ASC, matched.popularity_score DESC, matched.id ASC LIMIT 1`,
      [k, like, k, k, like, k, k, k, like, like, like],
    )) as any;
    return rows[0] ? { id: Number(rows[0].id), name: String(rows[0].name) } : null;
  }

  async treatmentIdByName(name: string): Promise<number> {
    if (!name) return 0;
    const [rows] = (await this.db.query('SELECT id FROM gangnamunni_treatment_master WHERE name = ? AND is_active = 1 LIMIT 1', [name])) as any;
    return rows[0] ? Number(rows[0].id) : 0;
  }

  /** Distinct dosage options offered by visible hospitals for a treatment, sorted by unit then value. */
  async dosageOptions(treatmentId: number): Promise<DosageOption[]> {
    if (!(treatmentId > 0)) return [];
    const [rows] = (await this.db.query(
      `SELECT DISTINCT p.dosage_value, p.dosage_unit
       FROM gangnamunni_hospital_treatment_prices p
       INNER JOIN gangnamunni_hospital_treatments ht ON ht.id = p.hospital_treatment_id
       INNER JOIN gangnamunni_hospitals h ON h.id = ht.hospital_id
       WHERE ht.treatment_id = ? AND ht.status = 'active' AND ht.is_active = 1 AND p.price > 0
         AND p.dosage_value IS NOT NULL AND p.dosage_value > 0 AND p.dosage_unit IS NOT NULL AND TRIM(p.dosage_unit) <> ''
         AND ${VISIBLE}`,
      [treatmentId],
    )) as any;
    const map = new Map<string, DosageOption>();
    for (const r of rows as any[]) {
      const unit = String(r.dosage_unit).trim();
      const value = Number(r.dosage_value);
      const label = dosageLabel(value, unit);
      map.set(label, { value, unit, label });
    }
    return [...map.values()].sort((a, b) => (a.unit < b.unit ? -1 : a.unit > b.unit ? 1 : a.value - b.value));
  }

  /** `?dosage=` is accepted only from the option list, and only when there is a real choice (>1 option). */
  dosageSelected(options: DosageOption[], raw: string): DosageOption | null {
    const r = (raw || '').trim();
    if (!r || options.length <= 1) return null;
    return options.find((o) => o.label === r) ?? null;
  }

  /** Hospital thumbnail as in the hospital list: first active main image, else logo, else the hospital's own image columns. */
  private async thumbs(rows: { id: number; main?: string | null; profile?: string | null }[]): Promise<Map<number, string | null>> {
    const out = new Map<number, string | null>();
    const ids = [...new Set(rows.map((r) => r.id))].filter((n) => n > 0);
    if (!ids.length) return out;
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
      if (i.image_type === 'main' && !main.has(hid)) main.set(hid, String(i.image_url));
      if (i.image_type === 'logo' && !logo.has(hid)) logo.set(hid, String(i.image_url));
    }
    for (const r of rows) out.set(r.id, main.get(r.id) || r.main || logo.get(r.id) || r.profile || null);
    return out;
  }

  /** search_suggest.php: treatments (≤4 + dosage shortcut), hospitals offering the first treatment (≤5, cheapest first), hospitals by name (≤3). */
  async suggest(q: string) {
    const out = {
      treatments: [] as { name: string; href: string; hint?: string }[],
      hospitals: [] as { id: number; name: string; district: string; address: string; href: string; thumbnailUrl: string | null }[],
      offered: { treatment: '', items: [] as { id: number; name: string; district: string; address: string; price: number; href: string; thumbnailUrl: string | null }[] },
    };
    const term = q.trim();
    if (!term || [...term].length > 40) return out;
    const like = `%${likeEscape(term)}%`;
    const prefix = `${likeEscape(term)}%`;

    const [tms] = (await this.db.query(
      `SELECT tm.id, tm.name FROM gangnamunni_treatment_master tm WHERE tm.is_active = 1 AND tm.name LIKE ?
       ORDER BY (tm.name LIKE ?) DESC, CHAR_LENGTH(tm.name) ASC LIMIT 4`,
      [like, prefix],
    )) as any;
    let firstTid = 0;
    let firstName = '';
    for (const [i, r] of (tms as any[]).entries()) {
      const name = String(r.name);
      out.treatments.push({ name, href: `hospitals.php?q=${encodeURIComponent(name)}` });
      if (i === 0) {
        firstTid = Number(r.id);
        firstName = name;
        const [d] = (await this.db.query(
          `SELECT p.dosage_value v, p.dosage_unit u, COUNT(*) c
           FROM gangnamunni_hospital_treatment_prices p
           INNER JOIN gangnamunni_hospital_treatments ht ON ht.id = p.hospital_treatment_id
           WHERE ht.treatment_id = ? AND ht.status='active' AND ht.is_active=1 AND p.price>0
             AND p.dosage_value>0 AND TRIM(IFNULL(p.dosage_unit,''))<>''
           GROUP BY p.dosage_value, p.dosage_unit ORDER BY c DESC LIMIT 1`,
          [firstTid],
        )) as any;
        if (d[0]) {
          const lab = dosageLabel(Number(d[0].v), String(d[0].u).trim());
          out.treatments.push({ name: `${name} ${lab}`, href: `hospitals.php?q=${encodeURIComponent(name)}&dosage=${encodeURIComponent(lab)}`, hint: '용량 자동 선택' });
        }
      }
    }

    out.offered.treatment = firstName;
    if (firstTid > 0) {
      const [os] = (await this.db.query(
        `SELECT h.id, h.name, h.address, MAX(h.main_image_url) AS main_image_url, MAX(h.profile_image_url) AS profile_image_url, MIN(p.price) AS price
         FROM gangnamunni_hospital_treatments ht
         INNER JOIN gangnamunni_hospital_treatment_prices p ON p.hospital_treatment_id = ht.id AND p.price > 0
         INNER JOIN gangnamunni_hospitals h ON h.id = ht.hospital_id
         WHERE ht.treatment_id = ? AND ht.status='active' AND ht.is_active=1 AND ${VISIBLE}
         GROUP BY h.id, h.name, h.address ORDER BY price ASC LIMIT 5`,
        [firstTid],
      )) as any;
      const th = await this.thumbs((os as any[]).map((o) => ({ id: Number(o.id), main: o.main_image_url, profile: o.profile_image_url })));
      for (const o of os as any[]) {
        out.offered.items.push({ thumbnailUrl: th.get(Number(o.id)) ?? null,
          id: Number(o.id), name: String(o.name), district: addrDistrict(String(o.address ?? '')), address: String(o.address ?? ''),
          price: Number(o.price), href: `hospitals.php?q=${encodeURIComponent(firstName)}&hid=${Number(o.id)}`,
        });
      }
    }

    const [hs] = (await this.db.query(
      `SELECT h.id, h.name, h.address, h.main_image_url, h.profile_image_url FROM gangnamunni_hospitals h WHERE ${VISIBLE} AND h.name LIKE ?
       ORDER BY (h.name LIKE ?) DESC, CHAR_LENGTH(h.name) ASC LIMIT 3`,
      [like, prefix],
    )) as any;
    const th2 = await this.thumbs((hs as any[]).map((r) => ({ id: Number(r.id), main: r.main_image_url, profile: r.profile_image_url })));
    for (const r of hs as any[]) {
      out.hospitals.push({ thumbnailUrl: th2.get(Number(r.id)) ?? null,
        id: Number(r.id), name: String(r.name), district: addrDistrict(String(r.address ?? '')), address: String(r.address ?? ''),
        href: `hospitals.php?q=${encodeURIComponent(r.name)}&hid=${Number(r.id)}`,
      });
    }
    return out;
  }
}
