import { createHash } from 'crypto';
import { likeContains, MAX_PAGE, MAX_SEARCH_LENGTH, num, positiveInt, Query, str } from '../hospitals/hospitals.logic';

export const PER_PAGE = 20;
export const BANNER_VIEWS = ['all', 'visible', 'hidden'] as const;
export type BannerView = (typeof BANNER_VIEWS)[number];

/** Auto-scroll interval, in milliseconds as the site stores it (the PHP page keeps the same bounds). */
export const SCROLL_MIN_MS = 1000;
export const SCROLL_MAX_MS = 60000;
export const SCROLL_DEFAULT_MS = 10000;

export interface BannerQuery {
  view: BannerView;
  search: string;
  page: number;
}

export function normalizeBannerQuery(q: Record<string, unknown> | undefined | null): BannerQuery {
  const get = (k: string): string => (typeof q?.[k] === 'string' ? (q[k] as string) : '');
  const view = (BANNER_VIEWS as readonly string[]).includes(get('view')) ? (get('view') as BannerView) : 'all';
  const search = Array.from(get('search').trim()).slice(0, MAX_SEARCH_LENGTH).join('');
  return { view, search, page: Math.min(MAX_PAGE, Math.max(1, positiveInt(get('page')))) };
}

/** Id of /v1/banners/:id, or 0 when it is not a plain positive integer. */
export const parseBannerId = (raw: unknown): number => (typeof raw === 'string' && /^[1-9]\d{0,9}$/.test(raw) ? positiveInt(raw) : 0);

/** The revision the page was read with; any other shape is refused. */
export const parseRevision = (body: unknown): string | null => {
  const v = body && typeof body === 'object' ? (body as Record<string, unknown>).revision : undefined;
  return typeof v === 'string' && /^[a-f0-9]{64}$/.test(v) ? v : null;
};

type Row = Record<string, unknown>;

/** A stored picture address: a path of the PHP site ("uploads/ads/x.jpg"), a path on this host, or a http(s) address. Anything else is shown as no picture. */
export const storedImage = (v: unknown): string => {
  const s = str(v).trim();
  if (s === '' || s.length > 500 || /[\s"'<>\\]/.test(s)) return '';
  if (/^https?:\/\/\S/i.test(s) || /^\/(?!\/)/.test(s)) return s;
  return !s.includes('..') && /^[A-Za-z0-9_][A-Za-z0-9_./-]*$/.test(s) ? s : '';
};

/** A link a banner opens: a http(s) address, a path on this host or a path of the site. Anything else (javascript:, data:, //host, white space, quotes, angle brackets, backslashes) is shown as no link. */
export const safeLink = (v: unknown): string => {
  const s = str(v).trim();
  if (s === '' || s.length > 500 || /[\s"'<>\\\u0000-\u001f\u007f]/.test(s)) return '';
  if (/^https?:\/\/[^/?#]/i.test(s)) return s;
  if (s.startsWith('/')) return s.startsWith('//') ? '' : s;
  return /^[a-z][a-z0-9+.-]*:/i.test(s) || s.startsWith('.') ? '' : s;
};

/** A banner as the admin list shows it. `visible` = what the site really does: approved and not switched off for the home page. */
export interface BannerItem {
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
}

const sha = (parts: unknown[]): string => createHash('sha256').update(JSON.stringify(parts)).digest('hex');

/** Fingerprint of what an admin could see and change, so a delete never hits a banner edited in between. */
export const bannerRevision = (r: Row): string =>
  sha([str(r.title), str(r.subtitle), str(r.image_url), str(r.mypage_image_url), str(r.link_url), num(r.display_order), num(r.hospital_id), str(r.status), str(r.updated_at)]);

const iso = (v: unknown): string => {
  if (v instanceof Date) return v.toISOString();
  const s = str(v);
  return s;
};

export function toBannerItem(r: Row): BannerItem {
  const status = str(r.status);
  const homeEnabled = num(r.home_enabled) === 1;
  return {
    id: num(r.id),
    title: str(r.title),
    subtitle: str(r.subtitle),
    image: storedImage(r.image_url),
    mypageImage: storedImage(r.mypage_image_url),
    linkUrl: safeLink(r.link_url),
    displayOrder: num(r.display_order),
    hospitalId: num(r.hospital_id),
    hospitalName: str(r.hospital_name),
    status,
    homeEnabled,
    visible: status === 'approved' && homeEnabled,
    createdAt: iso(r.created_at),
    revision: bannerRevision(r),
  };
}

export interface BannerCounts {
  all: number;
  visible: number;
  hidden: number;
}

export const toBannerCounts = (r: Row | undefined): BannerCounts => ({ all: num(r?.total), visible: num(r?.visible), hidden: num(r?.hidden) });

export interface BannerPage {
  view: BannerView;
  page: number;
  perPage: number;
  hasMore: boolean;
  counts: BannerCounts;
  items: BannerItem[];
}

export function toBannerPage(q: BannerQuery, counts: BannerCounts, rows: Row[]): BannerPage {
  return { view: q.view, page: q.page, perPage: PER_PAGE, hasMore: rows.length > PER_PAGE, counts, items: rows.slice(0, PER_PAGE).map(toBannerItem) };
}

/** The key the site reads to hide one banner from the home page ('0' = hidden); no row means shown. */
export const homeKey = (id: number): string => `home_banner_enabled_${id}`;

const HOME_ENABLED = `NOT EXISTS (SELECT 1 FROM gangnamunni_settings hs WHERE hs.setting_key = CONCAT('home_banner_enabled_', a.id) AND hs.setting_value = '0')`;
const VISIBLE = `(COALESCE(a.status, '') = 'approved' AND ${HOME_ENABLED})`;

function whereOf(q: BannerQuery): { sql: string; values: unknown[] } {
  let sql = '1 = 1';
  const values: unknown[] = [];
  if (q.view === 'visible') sql += ` AND ${VISIBLE}`;
  if (q.view === 'hidden') sql += ` AND NOT ${VISIBLE}`;
  if (q.search !== '') {
    const like = likeContains(q.search);
    sql += ` AND (a.title LIKE ? OR a.subtitle LIKE ? OR a.link_url LIKE ?)`;
    values.push(like, like, like);
  }
  return { sql, values };
}

/** Counts of the three views for the current search (the view itself is not applied). */
export function bannerCountsQuery(q: BannerQuery): Query {
  const w = whereOf({ ...q, view: 'all' });
  return {
    sql: `SELECT COUNT(*) AS total, COALESCE(SUM(${VISIBLE}), 0) AS visible, COALESCE(SUM(NOT ${VISIBLE}), 0) AS hidden FROM gangnamunni_ads a WHERE ${w.sql}`,
    values: w.values,
  };
}

/** One more row than a page, so the caller can tell whether there is a next page. */
export function bannerListQuery(q: BannerQuery): Query {
  const w = whereOf(q);
  return {
    sql: `SELECT a.id, a.hospital_id, h.name AS hospital_name, a.title, a.subtitle, a.image_url, a.mypage_image_url, a.link_url, a.display_order, a.status, a.created_at, a.updated_at,
                 ${HOME_ENABLED} AS home_enabled
            FROM gangnamunni_ads a LEFT JOIN gangnamunni_hospitals h ON h.id = a.hospital_id
           WHERE ${w.sql}
           ORDER BY a.display_order ASC, a.id DESC
           LIMIT ? OFFSET ?`,
    values: [...w.values, PER_PAGE + 1, (q.page - 1) * PER_PAGE],
  };
}

// ---- auto scroll (gangnamunni_theme_settings) -----------------------------------------------------------------------

export const SCROLL_ENABLED_KEY = 'ad_auto_scroll_enabled';
export const SCROLL_INTERVAL_KEY = 'ad_auto_scroll_interval';

export interface ScrollSettings {
  enabled: boolean;
  intervalSeconds: number;
}

export const scrollQuery = (): Query => ({
  sql: 'SELECT setting_key, setting_value FROM gangnamunni_theme_settings WHERE setting_key IN (?, ?)',
  values: [SCROLL_ENABLED_KEY, SCROLL_INTERVAL_KEY],
});

const clampMs = (ms: number): number => Math.min(SCROLL_MAX_MS, Math.max(SCROLL_MIN_MS, ms));

/** The stored values (a missing row is the site default: on, 10 seconds). The stored interval is milliseconds. */
export function toScroll(rows: Row[]): ScrollSettings {
  const get = (k: string): string | undefined => {
    const r = rows.find((x) => str(x.setting_key) === k);
    return r ? str(r.setting_value) : undefined;
  };
  const enabled = (get(SCROLL_ENABLED_KEY) ?? '1') === '1';
  const raw = Number(get(SCROLL_INTERVAL_KEY));
  const ms = Number.isFinite(raw) && raw > 0 ? raw : SCROLL_DEFAULT_MS;
  return { enabled, intervalSeconds: Math.round(clampMs(ms) / 1000) };
}

export type FieldErrors = Record<string, string>;

export function parseScroll(body: unknown): { value: ScrollSettings; errors: FieldErrors } {
  const b = (body && typeof body === 'object' ? body : {}) as Record<string, unknown>;
  const errors: FieldErrors = {};
  if (typeof b.enabled !== 'boolean') errors.enabled = 'invalid';
  const s = b.intervalSeconds;
  const ok = typeof s === 'number' && Number.isInteger(s) && s >= SCROLL_MIN_MS / 1000 && s <= SCROLL_MAX_MS / 1000;
  if (!ok) errors.intervalSeconds = 'range';
  return { value: { enabled: b.enabled === true, intervalSeconds: ok ? (s as number) : SCROLL_DEFAULT_MS / 1000 }, errors };
}

export const upsertThemeQuery = (key: string, value: string): Query => ({
  sql: 'INSERT INTO gangnamunni_theme_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
  values: [key, value],
});

// ---- home switch and delete -----------------------------------------------------------------------------------------

export function parseHomeSwitch(body: unknown): boolean | null {
  const v = body && typeof body === 'object' ? (body as Record<string, unknown>).enabled : undefined;
  return typeof v === 'boolean' ? v : null;
}

export const lockBannerQuery = (id: number): Query => ({
  sql: `SELECT id, hospital_id, title, subtitle, image_url, mypage_image_url, link_url, display_order, status, updated_at FROM gangnamunni_ads WHERE id = ? LIMIT 1 FOR UPDATE`,
  values: [id],
});

export const upsertSettingQuery = (key: string, value: string): Query => ({
  sql: 'INSERT INTO gangnamunni_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
  values: [key, value],
});

export const deleteBannerQuery = (id: number): Query => ({ sql: 'DELETE FROM gangnamunni_ads WHERE id = ?', values: [id] });

export const deleteHomeKeyQuery = (id: number): Query => ({ sql: 'DELETE FROM gangnamunni_settings WHERE setting_key = ?', values: [homeKey(id)] });
