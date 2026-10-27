import { Inject, Injectable } from '@nestjs/common';
import { DB, Db } from '../db/db.module';

/** Mirrors index.php data loading (home_banner_ads, promos, events, treatment categories, settings). */

export interface HomeAd { id: number; title: string; subtitle: string | null; imageUrl: string | null; linkUrl: string }
export interface HomePromo { id: number; title: string; subtitle: string | null; background: string; icon: string | null; linkUrl: string }
export interface HomeEvent {
  id: number; title: string; category: string | null; imageUrl: string | null; hospitalId: number | null;
  hospitalName: string | null; hospitalAddress: string | null; originalPrice: number | null; discountPrice: number | null;
  discountRate: number | null; hasRaffle: boolean; endDate: string | null;
}
export interface HomeCategory { id: number; name: string; treatments: string[] }
export interface HomeSettings {
  adShowIndex: boolean; adAfterSearch: boolean; adAfterEvents: boolean; promoShowIndex: boolean;
  tableMode: 'excel' | 'row' | 'hybrid'; adAutoScroll: boolean; promoAutoScroll: boolean;
  blocks: { promos: boolean; events: boolean };
}
export interface HomeData {
  ads: HomeAd[]; promos: HomePromo[]; events: HomeEvent[]; categories: HomeCategory[]; settings: HomeSettings; regions: string[][];
  /** includes/search_overlay.php 인기 검색어 (settings.popular_search_keywords, max 10; default list when unset). */
  popularKeywords: string[];
}

const POPULAR_DEFAULT = ['리쥬란', '텐써마', '울쎄라', '보톡스', '슈링크', '쥬베룩', '아이 필러', '물광주사'];

/** index.php home_safe_link(): relative, '#' or http(s) only; anything else -> '#'. */
export function safeLink(raw: unknown, fallback = '#'): string {
  const url = String(raw ?? '').trim();
  if (!url || /[\x00-\x1F\x7F]/.test(url)) return fallback;
  if (url[0] === '#' || url[0] === '/') return url.startsWith('//') ? fallback : url;
  const m = /^([a-z][a-z0-9+.-]*):/i.exec(url);
  if (!m) return /^[a-z0-9._~!$&'()*+,;=:@%/?#-]+$/i.test(url) ? url : fallback;
  return ['http', 'https'].includes(m[1].toLowerCase()) ? url : fallback;
}

/** index.php home_safe_background(): hex color or linear/radial-gradient only. */
export function safeBackground(raw: unknown): string {
  const v = String(raw ?? '').trim();
  if (/^#[0-9a-f]{3,8}$/i.test(v)) return v;
  if (/^(?:linear|radial)-gradient\([a-z0-9#%.,()\s-]+\)$/i.test(v)) return v;
  return 'linear-gradient(135deg, #AC1830, #D4455A)';
}

/** includes/site_regions.php (single source in PHP; duplicated here until regions move to the DB). */
export const REGION_GROUPS: string[][] = [
  ['강남', '신사', '압구정', '청담', '교대', '삼성', '송파', '천호', '목동', '강북', '노원'],
  ['인천', '성남', '동탄', '일산', '의정부'],
  ['부산', '대구', '대전', '청주', '광주', '전주', '춘천', '제주'],
];

const num = (v: unknown): number | null => (v === null || v === undefined || v === '' ? null : Number(v));

@Injectable()
export class HomeService {
  constructor(@Inject(DB) private readonly db: Db) {}

  async load(): Promise<HomeData> {
    const [[ads], [promos], [events], [cats], [settings], [theme], [blocks]] = (await Promise.all([
      this.db.query(`SELECT a.id, a.title, a.subtitle, a.image_url, a.link_url FROM gangnamunni_ads a
        WHERE a.status = 'approved'
          AND NOT EXISTS (SELECT 1 FROM gangnamunni_settings s WHERE s.setting_key = CONCAT('home_banner_enabled_', a.id) AND s.setting_value = '0')
        ORDER BY a.display_order ASC LIMIT 10`),
      this.db.query(`SELECT id, title, subtitle, background_gradient, icon, link_url FROM gangnamunni_promos WHERE is_active = 1 ORDER BY display_order ASC LIMIT 10`),
      // e.* : index.php reads optional columns (has_raffle) with !empty(), so don't name them explicitly
      this.db.query(`SELECT e.*, h.name AS hospital_name, h.address AS hospital_address
        FROM gangnamunni_events e LEFT JOIN gangnamunni_hospitals h ON e.hospital_id = h.id
        WHERE e.is_active = 1 AND (e.end_date IS NULL OR e.end_date >= CURDATE())
        ORDER BY e.display_order ASC, e.created_at DESC LIMIT 10`),
      this.db.query(`SELECT c.id, c.name AS cat_name,
          GROUP_CONCAT(m.name ORDER BY m.sort_order ASC, m.popularity_score DESC, m.id ASC SEPARATOR '||') AS treatments
        FROM gangnamunni_treatment_categories c
        LEFT JOIN gangnamunni_treatment_master m ON m.category_id = c.id AND m.is_active = 1
        WHERE c.is_active = 1 GROUP BY c.id, c.name, c.sort_order ORDER BY c.sort_order ASC, c.id ASC`),
      this.db.query(`SELECT setting_key, setting_value FROM gangnamunni_settings
        WHERE setting_key IN ('ad_home_after_search','ad_home_after_events','ad_show_index','promo_show_index','tc_table_mode','popular_search_keywords')`),
      this.db.query(`SELECT setting_key, setting_value FROM gangnamunni_theme_settings WHERE setting_key IN ('ad_auto_scroll_enabled','promo_auto_scroll_enabled')`),
      this.db.query(`SELECT block_key, is_visible FROM figma_block_toggles WHERE block_key IN ('home_promos','home_events')`).catch(() => [[]]),
    ])) as any;

    const s = new Map<string, string>((settings as any[]).map((r) => [r.setting_key, String(r.setting_value)]));
    const t = new Map<string, string>((theme as any[]).map((r) => [r.setting_key, String(r.setting_value)]));
    const b = new Map<string, number>((blocks as any[]).map((r) => [r.block_key, Number(r.is_visible)]));
    const modeRaw = s.get('tc_table_mode') ?? 'excel';
    const popular = (s.get('popular_search_keywords') ?? '').split(/[\r\n,]+/).map((w) => w.trim()).filter(Boolean);
    const tableMode = modeRaw === 'row' || modeRaw === 'hybrid' ? modeRaw : 'excel';

    return {
      ads: (ads as any[]).map((a) => ({ id: Number(a.id), title: String(a.title ?? ''), subtitle: a.subtitle || null, imageUrl: a.image_url || null, linkUrl: safeLink(a.link_url) })),
      promos: (promos as any[]).map((p) => ({ id: Number(p.id), title: String(p.title ?? ''), subtitle: p.subtitle || null, background: safeBackground(p.background_gradient), icon: p.icon || null, linkUrl: safeLink(p.link_url) })),
      events: (events as any[]).map((e) => ({
        id: Number(e.id), title: String(e.title ?? ''), category: e.category || null, imageUrl: e.image_url || null,
        hospitalId: num(e.hospital_id), hospitalName: e.hospital_name || null, hospitalAddress: e.hospital_address || null,
        originalPrice: num(e.original_price), discountPrice: num(e.discount_price), discountRate: num(e.discount_rate),
        hasRaffle: !!Number(e.has_raffle ?? 0), endDate: e.end_date ? String(e.end_date).slice(0, 10) : null,
      })),
      categories: (cats as any[]).map((c) => ({ id: Number(c.id), name: String(c.cat_name), treatments: c.treatments ? String(c.treatments).split('||') : [] })),
      settings: {
        adShowIndex: s.get('ad_show_index') === '1',
        adAfterSearch: s.get('ad_home_after_search') === '1',
        adAfterEvents: s.get('ad_home_after_events') === '1',
        promoShowIndex: s.get('promo_show_index') === '1',
        tableMode,
        adAutoScroll: (t.get('ad_auto_scroll_enabled') ?? '1') === '1',
        promoAutoScroll: (t.get('promo_auto_scroll_enabled') ?? '1') === '1',
        blocks: { promos: (b.get('home_promos') ?? 1) === 1, events: (b.get('home_events') ?? 1) === 1 },
      },
      regions: REGION_GROUPS,
      popularKeywords: (popular.length ? popular : POPULAR_DEFAULT).slice(0, 10),
    };
  }
}
