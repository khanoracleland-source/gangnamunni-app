/** Pure helpers ported from community.php / detail_community.php / includes/community_comments.php (unit tested). */

export type CommunityTab = 'latest' | 'popular' | 'review';
export const COMMUNITY_PER_PAGE = 20;
export const PREVIEW_CHARS = 90;
export const FULL_TEXT_CAP = 2000;

export function normalizeTab(v: unknown): CommunityTab {
  return v === 'popular' || v === 'review' ? v : 'latest';
}

/** `region=서울,부산` -> ['서울','부산'] (trimmed, empty dropped, bounded). */
export function parseRegions(raw: string): string[] {
  return raw.split(',').map((s) => s.trim()).filter(Boolean).slice(0, 10).map((s) => s.slice(0, 50));
}

/** PHP strip_tags() for plain-text previews. */
export function stripTags(html: unknown): string {
  return String(html ?? '').replace(/<[^>]*>/g, '');
}

/** mb_substr($s, 0, n) — counts characters, not UTF-16 units. */
export function chars(s: string, n: number): string {
  const a = Array.from(s);
  return a.length <= n ? s : a.slice(0, n).join('');
}

export function previewOf(content: unknown): { preview: string; fullText: string | null } {
  const plain = stripTags(content);
  const long = Array.from(plain).length > PREVIEW_CHARS;
  return { preview: chars(plain, PREVIEW_CHARS), fullText: long ? chars(plain, FULL_TEXT_CAP) : null };
}

/** includes/functions.php sanitizeImageUrl(). */
export function sanitizeImageUrl(url: unknown): string {
  if (typeof url !== 'string') return '';
  const u = url.trim();
  if (u === '' || u.length > 500) return '';
  if (/[\s\x00-\x1F\x7F"'<>`\\]/.test(u)) return '';
  if (u.includes('..')) return '';
  if (u.startsWith('//')) return '';
  if (/^https?:\/\/[^/?#]+/i.test(u)) return u;
  if (/^[A-Za-z][A-Za-z0-9+.\-]*:/.test(u)) return '';
  if (/^\/?[A-Za-z0-9][A-Za-z0-9/_.\-~%?&=+]*$/.test(u)) return u;
  return '';
}

/** posts.photos / review photo_urls: JSON array of strings, each sanitised (list view). */
export function listPhotos(raw: unknown): string[] {
  return parseJsonArray(raw).map(sanitizeImageUrl).filter(Boolean);
}

/** detail_community.php only accepts absolute http(s) or a root-relative path ("/x", never "//x"). */
export function detailPhotos(raw: unknown): string[] {
  return parseJsonArray(raw).filter((p) => /^(?:\/(?!\/)|https?:\/\/)/i.test(p) && sanitizeImageUrl(p) !== '');
}

function parseJsonArray(raw: unknown): string[] {
  if (raw == null || raw === '') return [];
  try {
    const v = typeof raw === 'string' ? JSON.parse(raw) : raw;
    return Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : [];
  } catch { return []; }
}

export interface RawComment { id: number; parent_id?: number | null; [k: string]: unknown }

/** community_comments_in_thread_order(): old/unknown-parent comments are roots, replies follow their parent. */
export function commentsInThreadOrder<T extends RawComment>(comments: T[]): (T & { parent_id: number })[] {
  const ids = new Set(comments.map((c) => Number(c.id)));
  const roots: (T & { parent_id: number })[] = [];
  const children = new Map<number, (T & { parent_id: number })[]>();
  for (const c of comments) {
    const parent = Number(c.parent_id ?? 0);
    if (parent && parent !== Number(c.id) && ids.has(parent)) {
      const list = children.get(parent) ?? [];
      list.push({ ...c, parent_id: parent });
      children.set(parent, list);
    } else {
      roots.push({ ...c, parent_id: 0 });
    }
  }
  const out: (T & { parent_id: number })[] = [];
  for (const r of roots) {
    out.push(r);
    for (const reply of children.get(Number(r.id)) ?? []) out.push(reply);
  }
  return out;
}

/** `COALESCE(col,'') NOT IN (?,?)` for the blocked author ids; '1=1' when nobody is blocked. */
export function notBlockedSql(column: string, blocked: string[]): { sql: string; values: string[] } {
  if (!/^[a-zA-Z_][a-zA-Z0-9_.]*$/.test(column)) throw new Error('invalid column');
  if (!blocked.length) return { sql: '1=1', values: [] };
  return { sql: `COALESCE(${column}, '') NOT IN (${blocked.map(() => '?').join(',')})`, values: blocked };
}
