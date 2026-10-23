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
    return list.map((r) => ({
      id: Number(r.id),
      name: r.name,
      specialty: r.specialty ?? null,
      experience: r.experience ?? null,
      description: r.description ?? null,
      qualification: r.qualification ?? null,
      photoUrl: r.photo_url ?? null,
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

  async features(hospitalId: number): Promise<{ icon: string; label: string }[]> {
    const [rows] = (await this.db.query(
      `SELECT feature_icon, feature_label FROM hospital_features WHERE hospital_id = ? AND is_active = 1 ORDER BY sort_order ASC LIMIT 20`,
      [hospitalId],
    )) as any;
    return (rows as any[]).map((r) => ({ icon: String(r.feature_icon ?? ''), label: String(r.feature_label ?? '') }));
  }
}
