import { BadRequestException, HttpException, Inject, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { likeEscape } from '../common/parse';
import {
  COMMUNITY_PER_PAGE, CommunityTab, commentsInThreadOrder, detailPhotos, listPhotos, notBlockedSql, previewOf, sanitizeImageUrl,
} from './community.logic';

export interface Viewer { id: string; name: string }

export interface CommunityItem {
  id: number; kind: 'post' | 'review'; category: string; title: string;
  preview: string; fullText: string | null; createdAt: string; updatedAt: string | null;
  authorName: string; authorAvatar: string | null; photos: string[];
  likeCount: number; commentCount: number; viewCount: number; liked: boolean; isMine: boolean;
}
export interface CommunityList {
  tab: CommunityTab; category: string; regions: string[]; q: string;
  page: number; perPage: number; total: number; totalPages: number; items: CommunityItem[];
}
export interface CommunityComment {
  id: number; parentId: number; authorName: string; content: string; createdAt: string; likeCount: number; liked: boolean; isMine: boolean;
}
export interface CommunityPost {
  id: number; category: string; title: string; content: string; createdAt: string;
  authorName: string; authorAvatar: string | null; photos: string[];
  likeCount: number; commentCount: number; viewCount: number; liked: boolean; isAuthor: boolean;
  comments: CommunityComment[];
}

const n = (v: unknown) => Number(v ?? 0) || 0;
const s = (v: unknown) => (v == null ? '' : String(v));

@Injectable()
export class CommunityService {
  private readonly log = new Logger(CommunityService.name);
  private attempts = new Map<string, number[]>();

  constructor(@Inject(DB) private readonly db: Db) {}

  /** user_blocking.php community_blocked_ids(): everybody the viewer blocked or who blocked the viewer. */
  private async blockedIds(viewer: string | null): Promise<string[]> {
    if (!viewer) return [];
    try {
      const [rows] = (await this.db.query(
        'SELECT blocker_id, blocked_id FROM gangnamunni_blocked_users WHERE blocker_id = ? OR blocked_id = ?', [viewer, viewer])) as any;
      const ids = (rows as any[]).map((r) => (r.blocker_id === viewer ? r.blocked_id : r.blocker_id)).map(s).filter(Boolean);
      return [...new Set(ids)];
    } catch (err) {
      this.log.warn(`blocked lookup failed: ${(err as Error).message}`);
      return [];
    }
  }

  /** community.php. Viewer-specific (blocked authors hidden, own likes) — callers must not cache publicly. */
  async list(viewer: string | null, p: { tab: CommunityTab; category: string; regions: string[]; q: string; page: number }): Promise<CommunityList> {
    const blocked = await this.blockedIds(viewer);
    const offset = (p.page - 1) * COMMUNITY_PER_PAGE;
    const kw = p.q ? `%${likeEscape(p.q)}%` : '';
    let total = 0;
    let items: CommunityItem[] = [];

    if (p.tab === 'review') {
      const nb = notBlockedSql('r.user_id', blocked);
      const where = [`r.is_active = 1`, `r.review_status = 'published'`, nb.sql];
      const vals: unknown[] = [...nb.values];
      if (kw) { where.push('(r.title LIKE ? OR r.content LIKE ?)'); vals.push(kw, kw); }
      const [[cnt], [rows]] = (await Promise.all([
        this.db.query(`SELECT COUNT(*) AS cnt FROM hospital_reviews r WHERE ${where.join(' AND ')}`, vals),
        this.db.query(
          `SELECT r.id, r.user_id, r.title, r.content, r.created_at, r.updated_at, r.user_name AS author_name, r.user_avatar AS author_avatar,
                  r.like_count, r.comment_count, r.view_count, r.rating, r.photo_urls AS photos
             FROM hospital_reviews r WHERE ${where.join(' AND ')} ORDER BY r.created_at DESC LIMIT ? OFFSET ?`,
          [...vals, COMMUNITY_PER_PAGE, offset]),
      ])) as any;
      total = n(cnt[0]?.cnt);
      const ids = (rows as any[]).map((r) => n(r.id));
      // Photos live in review_images (before/after); fall back to photo_urls.
      const imgMap = new Map<number, string[]>();
      if (ids.length) {
        try {
          const [imgs] = (await this.db.query(
            `SELECT review_id, image_url FROM review_images WHERE review_id IN (?)
              ORDER BY review_id, FIELD(photo_category,'before','after'), image_order, id`, [ids])) as any;
          for (const i of imgs as any[]) imgMap.set(n(i.review_id), [...(imgMap.get(n(i.review_id)) ?? []), s(i.image_url)]);
        } catch (err) { this.log.warn(`review_images failed: ${(err as Error).message}`); }
      }
      const liked = await this.likedSet(viewer, ids, 'review');
      items = (rows as any[]).map((r) => {
        const { preview, fullText } = previewOf(r.content);
        const fromImages = (imgMap.get(n(r.id)) ?? []).map(sanitizeImageUrl).filter(Boolean);
        const photos = fromImages.length ? fromImages : listPhotos(r.photos);
        return {
          id: n(r.id), kind: 'review' as const, category: `⭐ ${n(r.rating).toFixed(1)} 시술후기`, title: s(r.title),
          preview, fullText, createdAt: s(r.created_at), updatedAt: r.updated_at ? s(r.updated_at) : null,
          authorName: s(r.author_name) || '익명', authorAvatar: r.author_avatar ? s(r.author_avatar) : null, photos: photos.slice(0, 2),
          likeCount: n(r.like_count), commentCount: n(r.comment_count), viewCount: n(r.view_count), liked: liked.has(n(r.id)), isMine: !!viewer && s(r.user_id) === viewer,
        };
      });
    } else {
      const nb = notBlockedSql('p.mb_id', blocked);
      const where = [`p.status = 'active'`, nb.sql];
      const vals: unknown[] = [...nb.values];
      if (p.category) { where.push('p.category = ?'); vals.push(p.category); }
      if (p.regions.length) {
        where.push(`(${p.regions.map(() => '(p.title LIKE ? OR p.content LIKE ?)').join(' OR ')})`);
        for (const r of p.regions) { const l = `%${likeEscape(r)}%`; vals.push(l, l); }
      }
      if (kw) { where.push('(p.title LIKE ? OR p.content LIKE ?)'); vals.push(kw, kw); }
      const order = p.tab === 'popular' ? 'like_count DESC, p.created_at DESC' : 'p.created_at DESC';
      const [[cnt], [rows]] = (await Promise.all([
        this.db.query(`SELECT COUNT(*) AS cnt FROM gangnamunni_community_posts p WHERE ${where.join(' AND ')}`, vals),
        this.db.query(
          `SELECT p.*,
                  COALESCE((SELECT COUNT(*) FROM gangnamunni_community_likes l WHERE l.post_id = p.id), 0) AS like_count
             FROM gangnamunni_community_posts p WHERE ${where.join(' AND ')} ORDER BY ${order} LIMIT ? OFFSET ?`,
          [...vals, COMMUNITY_PER_PAGE, offset]),
      ])) as any;
      total = n(cnt[0]?.cnt);
      const liked = await this.likedSet(viewer, (rows as any[]).map((r) => n(r.id)), 'post');
      items = (rows as any[]).map((r) => {
        const { preview, fullText } = previewOf(r.content);
        return {
          id: n(r.id), kind: 'post' as const, category: s(r.category), title: s(r.title), preview, fullText,
          createdAt: s(r.created_at), updatedAt: r.updated_at ? s(r.updated_at) : null,
          authorName: s(r.author_name) || '익명', authorAvatar: r.author_avatar ? s(r.author_avatar) : null,
          photos: listPhotos(r.photos).slice(0, 2),
          likeCount: n(r.like_count), commentCount: n(r.comment_count), viewCount: n(r.view_count), liked: liked.has(n(r.id)), isMine: !!viewer && s(r.mb_id) === viewer,
        };
      });
    }
    return {
      tab: p.tab, category: p.tab === 'review' ? '시술후기' : p.category, regions: p.regions, q: p.q,
      page: p.page, perPage: COMMUNITY_PER_PAGE, total, totalPages: Math.max(1, Math.ceil(total / COMMUNITY_PER_PAGE)), items,
    };
  }

  private async likedSet(viewer: string | null, ids: number[], kind: 'post' | 'review'): Promise<Set<number>> {
    if (!viewer || !ids.length) return new Set();
    try {
      const sql = kind === 'review'
        ? 'SELECT review_id AS lid FROM review_likes WHERE user_id = ? AND review_id IN (?)'
        : 'SELECT post_id AS lid FROM gangnamunni_community_likes WHERE user_id = ? AND post_id IN (?)';
      const [rows] = (await this.db.query(sql, [viewer, ids])) as any;
      return new Set((rows as any[]).map((r) => n(r.lid)));
    } catch (err) {
      this.log.warn(`liked lookup failed: ${(err as Error).message}`);
      return new Set();
    }
  }

  /** The post must be active and not by (or blocking) the viewer — otherwise 404, like detail_community.php. */
  private async visiblePost(viewer: string | null, id: number): Promise<any> {
    const nb = notBlockedSql('mb_id', await this.blockedIds(viewer));
    const [rows] = (await this.db.query(
      `SELECT * FROM gangnamunni_community_posts WHERE id = ? AND status = 'active' AND ${nb.sql} LIMIT 1`, [id, ...nb.values])) as any;
    const post = (rows as any[])[0];
    if (!post) throw new NotFoundException('공개된 게시글을 찾을 수 없습니다.');
    return post;
  }

  /** detail_community.php. `countView` is only true for the server-side render of the page (one view per page load). */
  async detail(viewer: Viewer | null, id: number, countView: boolean): Promise<CommunityPost> {
    const post = await this.visiblePost(viewer?.id ?? null, id);
    const viewCount = n(post.view_count);
    if (countView) {
      this.db.query('UPDATE gangnamunni_community_posts SET view_count = view_count + 1 WHERE id = ?', [id])
        .catch((err: Error) => this.log.warn(`view count failed: ${err.message}`));
    }
    const blocked = await this.blockedIds(viewer?.id ?? null);
    let rows: any[];
    try {
      [rows] = (await this.db.query(
        `SELECT c.id, c.mb_id, c.author, c.content, c.created_at, COALESCE(t.parent_id, 0) AS parent_id
           FROM gangnamunni_community_comments c LEFT JOIN gangnamunni_community_comment_threads t ON t.comment_id = c.id
          WHERE c.post_id = ? ORDER BY c.created_at ASC, c.id ASC`, [id])) as any;
    } catch (err) {
      // The thread table is created by PHP on the first reply; until then every comment is top-level.
      this.log.warn(`comments query with threads failed, retrying without: ${(err as Error).message}`);
      try {
        [rows] = (await this.db.query(
          `SELECT c.id, c.mb_id, c.author, c.content, c.created_at, 0 AS parent_id
             FROM gangnamunni_community_comments c WHERE c.post_id = ? ORDER BY c.created_at ASC, c.id ASC`, [id])) as any;
      } catch (err2) {
        this.log.warn(`comments query failed: ${(err2 as Error).message}`);
        rows = [];
      }
    }
    const visible = rows.filter((c) => !blocked.includes(s(c.mb_id)));
    const ordered = commentsInThreadOrder(visible);
    const likes = new Map<number, { count: number; liked: boolean }>();
    if (ordered.length) {
      try {
        const [lr] = (await this.db.query(
          `SELECT l.comment_id, COUNT(*) AS cnt, MAX(l.user_id = ?) AS liked
             FROM gangnamunni_community_comment_likes l JOIN gangnamunni_community_comments c ON c.id = l.comment_id
            WHERE c.post_id = ? GROUP BY l.comment_id`, [viewer?.id ?? '', id])) as any;
        for (const r of lr as any[]) likes.set(n(r.comment_id), { count: n(r.cnt), liked: !!n(r.liked) });
      } catch (err) { this.log.warn(`comment likes failed: ${(err as Error).message}`); }
    }
    let liked = false;
    if (viewer) {
      try {
        const [lr] = (await this.db.query('SELECT id FROM gangnamunni_community_likes WHERE post_id = ? AND user_id = ? LIMIT 1', [id, viewer.id])) as any;
        liked = (lr as any[]).length > 0;
      } catch (err) { this.log.warn(`post like lookup failed: ${(err as Error).message}`); }
    }
    return {
      id: n(post.id), category: s(post.category), title: s(post.title), content: s(post.content), createdAt: s(post.created_at),
      authorName: s(post.author_name) || s(post.author) || '익명', authorAvatar: post.author_avatar ? s(post.author_avatar) : null,
      photos: detailPhotos(post.photos), likeCount: n(post.like_count), commentCount: ordered.length, viewCount, liked,
      isAuthor: !!viewer && viewer.id === s(post.mb_id),
      comments: ordered.map((c) => ({
        id: n(c.id), parentId: n(c.parent_id), authorName: s(c.author) || '익명', content: s(c.content), createdAt: s(c.created_at),
        likeCount: likes.get(n(c.id))?.count ?? 0, liked: likes.get(n(c.id))?.liked ?? false,
        isMine: !!viewer && s(c.mb_id) === viewer.id,
      })),
    };
  }

  /** detail_community.php action=toggle_like. Recounts from the likes table and syncs posts.like_count (as PHP). */
  async togglePostLike(viewer: Viewer, postId: number): Promise<{ success: true; liked: boolean; count: number }> {
    await this.visiblePost(viewer.id, postId);
    const [rows] = (await this.db.query('SELECT id FROM gangnamunni_community_likes WHERE post_id = ? AND user_id = ? LIMIT 1', [postId, viewer.id])) as any;
    let liked: boolean;
    if ((rows as any[]).length) {
      await this.db.query('DELETE FROM gangnamunni_community_likes WHERE post_id = ? AND user_id = ?', [postId, viewer.id]);
      liked = false;
    } else {
      await this.db.query('INSERT IGNORE INTO gangnamunni_community_likes (post_id, user_id) VALUES (?, ?)', [postId, viewer.id]);
      liked = true;
    }
    const [[c]] = (await this.db.query('SELECT COUNT(*) AS c FROM gangnamunni_community_likes WHERE post_id = ?', [postId])) as any;
    const count = n(c?.c);
    await this.db.query('UPDATE gangnamunni_community_posts SET like_count = ? WHERE id = ?', [count, postId]);
    return { success: true as const, liked, count };
  }

  /** set_comment_like: idempotent "set to liked / not liked" (the client sends the state it wants). */
  async setCommentLike(viewer: Viewer, postId: number, commentId: number, liked: boolean): Promise<{ success: true; liked: boolean; count: number }> {
    await this.visiblePost(viewer.id, postId);
    const [rows] = (await this.db.query('SELECT id FROM gangnamunni_community_comments WHERE id = ? AND post_id = ? LIMIT 1', [commentId, postId])) as any;
    if (!(rows as any[]).length) throw new NotFoundException('댓글을 찾을 수 없습니다.');
    if (liked) await this.db.query('INSERT IGNORE INTO gangnamunni_community_comment_likes (comment_id, user_id) VALUES (?, ?)', [commentId, viewer.id]);
    else await this.db.query('DELETE FROM gangnamunni_community_comment_likes WHERE comment_id = ? AND user_id = ?', [commentId, viewer.id]);
    const [[c]] = (await this.db.query('SELECT COUNT(*) AS c FROM gangnamunni_community_comment_likes WHERE comment_id = ?', [commentId])) as any;
    return { success: true as const, liked, count: n(c?.c) };
  }

  private rateLimit(key: string) {
    const now = Date.now();
    const list = (this.attempts.get(key) ?? []).filter((t) => t > now - 600_000);
    if (list.length >= 20) throw new HttpException({ success: false, message: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' }, 429);
    list.push(now);
    this.attempts.set(key, list);
    if (this.attempts.size > 10_000) this.attempts.clear();
  }

  /**
   * community_comment_submit(): 1–500 chars, optional parent which must be a top-level comment of the same post.
   * Not one transaction (the API's DB user has no row-lock/transaction helper here): comment row first, then the
   * thread link, then the counter. A failed thread insert is logged; the comment then shows as a top-level one.
   */
  async addComment(viewer: Viewer, postId: number, input: { content?: unknown; parentId?: unknown }): Promise<{ success: true; id: number }> {
    const content = typeof input.content === 'string' ? input.content.trim() : '';
    const parent = input.parentId == null || input.parentId === '' ? 0 : Number(input.parentId);
    if (!content || Array.from(content).length > 500 || !Number.isInteger(parent) || parent < 0 || parent > 2147483647) {
      throw new BadRequestException('댓글과 답글 대상을 확인해주세요. 댓글은 1~500자로 입력해주세요.');
    }
    this.rateLimit(viewer.id);
    await this.visiblePost(viewer.id, postId);
    if (parent > 0) {
      const [rows] = (await this.db.query(
        `SELECT c.id FROM gangnamunni_community_comments c LEFT JOIN gangnamunni_community_comment_threads t ON t.comment_id = c.id
          WHERE c.id = ? AND c.post_id = ? AND t.comment_id IS NULL LIMIT 1`, [parent, postId])) as any;
      if (!(rows as any[]).length) throw new BadRequestException('답글을 작성할 원래 댓글을 찾을 수 없습니다.');
    }
    const [ins] = (await this.db.query(
      'INSERT INTO gangnamunni_community_comments (post_id, mb_id, author, content) VALUES (?, ?, ?, ?)', [postId, viewer.id, viewer.name, content])) as any;
    const id = n(ins?.insertId);
    if (parent > 0) {
      try { await this.db.query('INSERT INTO gangnamunni_community_comment_threads (comment_id, parent_id) VALUES (?, ?)', [id, parent]); }
      catch (err) { this.log.error(`thread link failed for comment ${id}: ${(err as Error).message}`); }
    }
    try { await this.db.query('UPDATE gangnamunni_community_posts SET comment_count = COALESCE(comment_count, 0) + 1 WHERE id = ?', [postId]); }
    catch (err) { this.log.warn(`comment_count failed for post ${postId}: ${(err as Error).message}`); }
    return { success: true as const, id };
  }
}
