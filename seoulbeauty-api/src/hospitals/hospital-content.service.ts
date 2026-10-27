import { Inject, Injectable } from '@nestjs/common';
import { DB, Db } from '../db/db.module';

/** Mirrors detail.php (시술목록 / 의료진 / 갤러리 / 특징). Optional columns are probed once, like the PHP does per request. */

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
  /** gangnamunni_doctor_treatments names (detail.php shows these as tags; empty => caller falls back). */
  treatments: string[];
}

interface Flags {
  optionName: boolean;
  bodyPart: boolean;
  regularPrice: boolean;
  representative: boolean;
  doctorDisplayOrder: boolean;
}

/** detail.php format_treatment_body_parts(): "a || (b) || [c]" -> "a, b, c" */
export function formatBodyParts(raw: unknown): string {
  const parts: string[] = [];
  for (let p of String(raw ?? '').split(/\s*\|\|\s*/)) {
    p = p.trim();
    let m;
    while ((m = /^[([]\s*(.*?)\s*[)\]]$/u.exec(p))) p = m[1].trim();
    if (p) parts.push(p);
  }
  return parts.join(', ');
}

/** detail.php $image_url_keys(): url, url without leading "/", path, path without "/", basename. */
export function imageUrlKeys(raw: unknown): string[] {
  const url = String(raw ?? '').trim();
  if (!url) return [];
  let path = '';
  try {
    path = /^[a-z][a-z0-9+.-]*:\/\//i.test(url) || url.startsWith('//') ? new URL(url, 'http://x').pathname : url.split(/[?#]/)[0];
  } catch {
    path = '';
  }
  const base = (v: string) => v.replace(/\/+$/, '').split('/').pop() || '';
  const keys = [url, url.replace(/^\/+/, '')];
  if (path) keys.push(path, path.replace(/^\/+/, ''), base(path));
  else keys.push(base(url));
  return [...new Set(keys.filter(Boolean))];
}

function dosageText(v: unknown, unit: unknown): string {
  const n = Number(v ?? 0);
  if (!(n > 0)) return '';
  return n.toFixed(2).replace(/\.?0+$/, '') + String(unit ?? '').trim();
}

export function toOption(r: Record<string, any>, treatmentName: string): TreatmentOption {
  const optionName = String(r.option_name ?? '').trim();
  const bodyPart = formatBodyParts(r.body_part);
  const dosage = dosageText(r.dosage_value, r.dosage_unit);
  const price = Number(r.price ?? 0);
  const regularPrice = Number(r.regular_price ?? 0);
  const displayName = [treatmentName, optionName, bodyPart, dosage].filter(Boolean).join(' · ') || treatmentName;
  return {
    id: Number(r.id),
    displayName,
    optionName,
    bodyPart,
    dosage,
    regularPrice,
    price,
    discountRate: regularPrice > price && price > 0 ? Math.round(((regularPrice - price) / regularPrice) * 100) : 0,
  };
}

@Injectable()
export class HospitalContentService {
  private flags: Flags | null = null;

  constructor(@Inject(DB) private readonly db: Db) {}

  private async getFlags(): Promise<Flags> {
    if (this.flags) return this.flags;
    const has = async (table: string, col: string) => {
      const [r] = (await this.db.query(`SHOW COLUMNS FROM ${table} LIKE ?`, [col])) as any;
      return r.length > 0;
    };
    const P = 'gangnamunni_hospital_treatment_prices';
    this.flags = {
      optionName: await has(P, 'option_name'),
      bodyPart: await has(P, 'body_part'),
      regularPrice: await has(P, 'regular_price'),
      representative: await has(P, 'is_representative'),
      doctorDisplayOrder: await has('gangnamunni_doctors', 'display_order'),
    };
    return this.flags;
  }

  /** Active treatments with sale prices, grouped per treatment (detail.php tab=events). */
  async treatments(hospitalId: number): Promise<HospitalTreatment[]> {
    const f = await this.getFlags();
    const [rows] = (await this.db.query(
      `SELECT ht.id AS hospital_treatment_id, ht.is_promoted, tm.id AS treatment_id, tm.name, tm.description,
              p.id, ${f.optionName ? 'p.option_name' : "'' AS option_name"}, ${f.bodyPart ? 'p.body_part' : "'' AS body_part"},
              p.dosage_value, p.dosage_unit, ${f.regularPrice ? 'p.regular_price' : 'p.price AS regular_price'}, p.price
       FROM gangnamunni_hospital_treatments ht
       INNER JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
       INNER JOIN gangnamunni_hospital_treatment_prices p ON p.hospital_treatment_id = ht.id AND p.price > 0
       WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1
       ORDER BY ht.is_promoted DESC, tm.name ASC, ${f.representative ? 'p.is_representative DESC, ' : ''}p.sort_order ASC, p.id ASC
       LIMIT 200`,
      [hospitalId],
    )) as any;
    const out: HospitalTreatment[] = [];
    const byId = new Map<number, HospitalTreatment>();
    for (const r of rows as any[]) {
      const tid = Number(r.treatment_id);
      let t = byId.get(tid);
      if (!t) {
        t = { treatmentId: tid, name: r.name, description: r.description ?? null, promoted: !!r.is_promoted, options: [] };
        byId.set(tid, t);
        out.push(t);
      }
      t.options.push(toOption(r, r.name));
    }
    return out;
  }

  /** gangnamunni_doctors (active) first; crawled_doctors as fallback — same as detail.php. */
  async doctors(hospitalId: number): Promise<Doctor[]> {
    const f = await this.getFlags();
    const order = f.doctorDisplayOrder
      ? 'CASE WHEN display_order IS NULL THEN 1 ELSE 0 END ASC, display_order ASC, name COLLATE utf8mb4_unicode_ci ASC, id ASC'
      : 'name COLLATE utf8mb4_unicode_ci ASC, id ASC';
    const [rows] = (await this.db.query(
      `SELECT id, name, specialty, experience, bio AS description, qualification, photo_url
       FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active' ORDER BY ${order} LIMIT 20`,
      [hospitalId],
    )) as any;
    let list = rows as any[];
    if (!list.length) {
      const [c] = (await this.db.query(
        `SELECT id, doctor_name AS name, specialty, NULL AS experience, description, NULL AS qualification, NULL AS photo_url
         FROM crawled_doctors WHERE hospital_id = ? ORDER BY id ASC LIMIT 20`,
        [hospitalId],
      )) as any;
      list = c;
    }
    const ids = list.map((r) => Number(r.id));
    const byDoctor = new Map<number, string[]>();
    if (ids.length && rows.length) {
      // Only real (gangnamunni_doctors) ids have doctor_treatments rows.
      const [dt] = (await this.db.query(
        `SELECT dt.doctor_id, tm.name FROM gangnamunni_doctor_treatments dt
         JOIN gangnamunni_treatment_master tm ON tm.id = dt.treatment_id
         WHERE dt.doctor_id IN (${ids.map(() => '?').join(',')}) AND tm.is_active = 1 ORDER BY dt.doctor_id, tm.name`,
        ids,
      )) as any;
      for (const r of dt as any[]) {
        const k = Number(r.doctor_id);
        if (!byDoctor.has(k)) byDoctor.set(k, []);
        byDoctor.get(k)!.push(String(r.name));
      }
    }
    // Empty strings / 0 come from the import; treat them as "not provided" like the PHP (!empty()).
    const txt = (v: unknown) => (v === null || v === undefined || String(v).trim() === '' || String(v) === '0' ? null : String(v));
    return list.map((r) => ({
      id: Number(r.id),
      name: r.name,
      specialty: txt(r.specialty),
      experience: txt(r.experience),
      description: txt(r.description),
      qualification: txt(r.qualification),
      photoUrl: txt(r.photo_url),
      treatments: byDoctor.get(Number(r.id)) ?? [],
    }));
  }

  /**
   * Interior gallery (detail.php): hospital_images minus logo/main rows, minus any image whose
   * URL matches a logo (profile_image_url or a logo row, compared by url/path/basename) or main_image_url.
   */
  async images(hospitalId: number, mainImageUrl: string | null, profileImageUrl: string | null): Promise<string[]> {
    const [[rows], [logos]] = (await Promise.all([
      this.db.query(
        `SELECT image_url FROM hospital_images
         WHERE hospital_id = ? AND is_active = 1 AND image_type <> 'logo' AND image_type <> 'main'
         ORDER BY sort_order ASC, id ASC LIMIT 30`,
        [hospitalId],
      ),
      this.db.query(`SELECT image_url FROM hospital_images WHERE hospital_id = ? AND is_active = 1 AND image_type = 'logo'`, [hospitalId]),
    ])) as any;
    const logoKeys = new Set<string>();
    for (const u of [profileImageUrl, ...(logos as any[]).map((r) => r.image_url)]) for (const k of imageUrlKeys(u)) logoKeys.add(k);
    return (rows as any[])
      .map((r) => String(r.image_url || ''))
      .filter((u) => u && u !== mainImageUrl && !imageUrlKeys(u).some((k) => logoKeys.has(k)));
  }

  /**
   * includes/hospital_search_view.php (hospitals.php?hid=): every active treatment of the hospital with its
   * cheapest option, plus the category chips. Sorted cheapest first (no price last), then by name.
   */
  async searchView(hid: number) {
    const [rows] = (await this.db.query(
      `SELECT tm.id AS tmid, tm.name, tm.category_id, tc.name AS cat_name,
              (SELECT p.price FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0 ORDER BY p.price ASC LIMIT 1) AS price,
              (SELECT p.dosage_value FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0 ORDER BY p.price ASC LIMIT 1) AS dv,
              (SELECT p.dosage_unit FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0 ORDER BY p.price ASC LIMIT 1) AS du
       FROM gangnamunni_hospital_treatments ht
       INNER JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id AND tm.is_active = 1
       LEFT JOIN gangnamunni_treatment_categories tc ON tc.id = tm.category_id
       WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1`,
      [hid],
    )) as any;
    const cats = new Map<number, { id: number; name: string; count: number }>();
    const treatments = (rows as any[]).map((r) => {
      const cid = Number(r.category_id ?? 0);
      if (cid) {
        const c = cats.get(cid) ?? { id: cid, name: String(r.cat_name ?? ''), count: 0 };
        c.count++;
        cats.set(cid, c);
      }
      const dv = Number(r.dv ?? 0);
      const du = String(r.du ?? '').trim();
      return { id: Number(r.tmid), name: String(r.name), categoryId: cid || null, price: Number(r.price ?? 0), dosageValue: dv > 0 ? dv : null, dosageUnit: du || null };
    });
    treatments.sort((a, b) => {
      const pa = a.price || Number.MAX_SAFE_INTEGER;
      const pb = b.price || Number.MAX_SAFE_INTEGER;
      return pa - pb || (a.name < b.name ? -1 : a.name > b.name ? 1 : 0);
    });
    return { total: treatments.length, categories: [...cats.values()], treatments };
  }

  async features(hospitalId: number): Promise<{ icon: string; label: string }[]> {
    const [rows] = (await this.db.query(
      `SELECT feature_icon, feature_label FROM hospital_features WHERE hospital_id = ? AND is_active = 1 ORDER BY sort_order ASC LIMIT 20`,
      [hospitalId],
    )) as any;
    return (rows as any[]).map((r) => ({ icon: String(r.feature_icon ?? ''), label: String(r.feature_label ?? '') }));
  }
}
