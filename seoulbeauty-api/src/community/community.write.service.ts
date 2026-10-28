import { BadRequestException, ConflictException, ForbiddenException, HttpException, Inject, Injectable, InternalServerErrorException, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { promises as fs } from 'node:fs';
import { join } from 'node:path';
import { DB, Db } from '../db/db.module';
import { Viewer } from './community.service';
import { MAX_PHOTOS, MAX_PHOTO_BYTES, categoriesFor, encodeReportReasons, sniffImage, uploadName, validatePost } from './community.write.logic';

export interface UploadedFile { buffer: Buffer; size: number; originalname: string }
export interface PostInput { category?: unknown; title?: unknown; content?: unknown; deletePhotos?: unknown }
export type ContentType = 'community' | 'comment' | 'review';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;
const list = (v: unknown): string[] => (Array.isArray(v) ? v.map(String) : v == null || v === '' ? [] : [String(v)]);

const SOURCES: Record<ContentType, { table: string; author: string; where: string }> = {
  community: { table: 'gangnamunni_community_posts', author: 'mb_id', where: "status = 'active'" },
  comment: { table: 'gangnamunni_community_comments', author: 'mb_id', where: '1 = 1' },
  review: { table: 'hospital_reviews', author: 'user_id', where: 'is_active = 1' },
};

@Injectable()
export class CommunityWriteService {
  private readonly log = new Logger(CommunityWriteService.name);
  private attempts = new Map<string, number[]>();

  constructor(@Inject(DB) private readonly db: Db) {}

  private rateLimit(key: string, max: number) {
    const now = Date.now();
    const l = (this.attempts.get(key) ?? []).filter((t) => t > now - 600_000);
    if (l.length >= max) throw new HttpException({ success: false, message: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' }, 429);
    l.push(now);
    this.attempts.set(key, l);
    if (this.attempts.size > 10_000) this.attempts.clear();
  }

  /** community_write.php (GET): category list, limits and — for ?id= — the author's own post (any of active/pending/rejected). */
  async form(viewer: Viewer, id: number | null) {
    let post: { id: number; category: string; title: string; content: string; photos: string[] } | null = null;
    if (id) {
      const row = await this.ownPost(viewer, id);
      post = { id: n(row.id), category: s(row.category), title: s(row.title), content: s(row.content), photos: this.photosOf(row.photos) };
    }
    return { categories: categoriesFor(post?.category), maxPhotos: MAX_PHOTOS, maxPhotoBytes: MAX_PHOTO_BYTES, post };
  }

  private photosOf(raw: unknown): string[] {
    try { const v = typeof raw === 'string' ? JSON.parse(raw) : raw; return Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : []; } catch { return []; }
  }

  private async ownPost(viewer: Viewer, id: number): Promise<any> {
    const [rows] = (await this.db.query("SELECT * FROM gangnamunni_community_posts WHERE id = ? AND status IN ('active','pending','rejected') LIMIT 1", [id])) as any;
    const row = (rows as any[])[0];
    if (!row) throw new NotFoundException('게시글을 찾을 수 없습니다.');
    if (s(row.mb_id) !== viewer.id) throw new ForbiddenException('본인의 글만 수정할 수 있습니다.');
    return row;
  }

  /** Validates and stores the new images (all-or-nothing); returns their public URLs and the paths to undo on failure. */
  private async storePhotos(files: UploadedFile[], slots: number): Promise<{ urls: string[]; paths: string[] }> {
    if (!files.length) return { urls: [], paths: [] };
    if (files.length > slots) throw new BadRequestException('사진은 최대 4장까지 첨부할 수 있습니다.');
    const dir = process.env.COMMUNITY_UPLOAD_DIR;
    if (!dir) throw new ServiceUnavailableException('사진 업로드가 설정되지 않았습니다.');
    const prefix = (process.env.COMMUNITY_UPLOAD_URL || '/hairwang/app/uploads/community/').replace(/\/?$/, '/');
    const checked = files.map((f) => {
      if (f.size > MAX_PHOTO_BYTES) throw new BadRequestException('이미지 크기는 5MB 이하여야 합니다.');
      const kind = sniffImage(f.buffer);
      if (!kind) throw new BadRequestException('허용되지 않는 이미지 형식입니다. (JPG/PNG/GIF/WebP만 가능)');
      return { f, kind };
    });
    const urls: string[] = [];
    const paths: string[] = [];
    try {
      await fs.mkdir(dir, { recursive: true, mode: 0o775 });
      for (let i = 0; i < checked.length; i++) {
        const name = uploadName(i, checked[i].kind.ext);
        const path = join(dir, name);
        await fs.writeFile(path, checked[i].f.buffer, { mode: 0o664, flag: 'wx' });
        paths.push(path);
        urls.push(prefix + name);
      }
    } catch (err) {
      await this.undo(paths);
      this.log.error(`photo save failed: ${(err as Error).message}`);
      throw new InternalServerErrorException('사진 저장에 실패했습니다. 다시 시도해주세요.');
    }
    return { urls, paths };
  }

  private async undo(paths: string[]) {
    await Promise.all(paths.map((p) => fs.unlink(p).catch(() => undefined)));
  }

  /** community_write.php (POST, new): stored as 'pending' until an admin approves it. */
  async create(viewer: Viewer, input: PostInput, files: UploadedFile[]): Promise<{ success: true; id: number; message: string }> {
    const category = s(input.category).trim();
    const title = s(input.title).trim();
    const content = s(input.content).trim();
    const err = validatePost(category, title, content, categoriesFor());
    if (err) throw new BadRequestException(err);
    this.rateLimit(`post:${viewer.id}`, 5);
    const { urls, paths } = await this.storePhotos(files, MAX_PHOTOS);
    try {
      const [ins] = (await this.db.query(
        "INSERT INTO gangnamunni_community_posts (mb_id, author_name, category, title, content, photos, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')",
        [viewer.id, viewer.name, category, title, content, urls.length ? JSON.stringify(urls) : null])) as any;
      return { success: true as const, id: n(ins?.insertId), message: '게시글이 접수되었습니다. 관리자 검토 후 공개됩니다.' };
    } catch (e) {
      await this.undo(paths);
      this.log.error(`post insert failed: ${(e as Error).message}`);
      throw new InternalServerErrorException('게시글 작성에 실패했습니다.');
    }
  }

  /** community_write.php (POST, edit): author only; goes back to 'pending' for review. */
  async update(viewer: Viewer, id: number, input: PostInput, files: UploadedFile[]): Promise<{ success: true; id: number; message: string }> {
    const row = await this.ownPost(viewer, id);
    const category = s(input.category).trim();
    const title = s(input.title).trim();
    const content = s(input.content).trim();
    const err = validatePost(category, title, content, categoriesFor(s(row.category)));
    if (err) throw new BadRequestException(err);
    this.rateLimit(`post:${viewer.id}`, 20);
    const remove = new Set(list(input.deletePhotos));
    const kept = this.photosOf(row.photos).filter((p) => !remove.has(p));
    const { urls, paths } = await this.storePhotos(files, Math.max(0, MAX_PHOTOS - kept.length));
    const all = [...kept, ...urls];
    try {
      const [res] = (await this.db.query(
        "UPDATE gangnamunni_community_posts SET category = ?, title = ?, content = ?, photos = ?, status = 'pending' WHERE id = ? AND mb_id = ? AND status IN ('active','pending','rejected')",
        [category, title, content, all.length ? JSON.stringify(all) : null, id, viewer.id])) as any;
      if (!n(res?.affectedRows)) throw new Error('no row updated');
      return { success: true as const, id, message: '게시글이 접수되었습니다. 관리자 검토 후 공개됩니다.' };
    } catch (e) {
      await this.undo(paths);
      this.log.error(`post update failed: ${(e as Error).message}`);
      throw new InternalServerErrorException('수정에 실패했습니다.');
    }
  }

  /** detail_community.php action=delete: soft delete of the author's own post. */
  async remove(viewer: Viewer, id: number): Promise<{ success: true; message: string }> {
    const [res] = (await this.db.query("UPDATE gangnamunni_community_posts SET status = 'deleted' WHERE id = ? AND mb_id = ? AND status <> 'deleted'", [id, viewer.id])) as any;
    if (!n(res?.affectedRows)) throw new NotFoundException('삭제할 수 없습니다.');
    return { success: true as const, message: '게시글이 삭제되었습니다.' };
  }

  /** The visible content row plus its author member (for reports and blocks). */
  private async contentAndAuthor(type: ContentType, id: number): Promise<{ row: any; authorId: string; mbNo: number; nick: string }> {
    const src = SOURCES[type];
    if (!src) throw new BadRequestException('잘못된 콘텐츠입니다.');
    const [rows] = (await this.db.query(`SELECT * FROM ${src.table} WHERE id = ? AND ${src.where} LIMIT 1`, [id])) as any;
    const row = (rows as any[])[0];
    if (!row) throw new NotFoundException('원본 콘텐츠가 없습니다.');
    const authorId = s(row[src.author]);
    if (!authorId) throw new BadRequestException('실제 작성자 계정을 확인할 수 없습니다.');
    const [members] = (await this.db.query('SELECT mb_no, mb_nick, mb_name FROM g5_member WHERE mb_id = ? LIMIT 1', [authorId])) as any;
    const m = (members as any[])[0];
    if (!m) throw new BadRequestException('작성자 회원을 찾을 수 없습니다.');
    return { row, authorId, mbNo: n(m.mb_no), nick: s(m.mb_nick) || s(m.mb_name) || authorId };
  }

  private contentUrl(type: ContentType, id: number, row: any): string {
    if (type === 'community') return `detail_community.php?id=${id}`;
    if (type === 'comment') return `detail_community.php?id=${n(row.post_id)}#comment-${id}`;
    return `review_detail.php?id=${id}`;
  }

  /** api_report_content.php (POST). No auto-hide: an admin handles the report. 409 when this member already reported it. */
  async report(viewer: Viewer & { no: number }, input: { contentType?: unknown; contentId?: unknown; reasons?: unknown; reasonDetail?: unknown }) {
    const type = s(input.contentType) as ContentType;
    const id = Number(input.contentId);
    if (!Object.prototype.hasOwnProperty.call(SOURCES, type) || !Number.isInteger(id) || id < 1 || id > 2147483647) throw new BadRequestException('유효하지 않은 콘텐츠입니다.');
    const enc = encodeReportReasons(input.reasons, input.reasonDetail);
    if (!enc) throw new BadRequestException('신고 사유를 확인해주세요.');
    this.rateLimit(`report:${viewer.id}`, 10);
    const { row, authorId, mbNo, nick } = await this.contentAndAuthor(type, id);
    if (authorId === viewer.id) throw new BadRequestException('본인의 콘텐츠는 신고할 수 없습니다.');
    const [dup] = (await this.db.query('SELECT id FROM gangnamunni_content_reports WHERE reporter_id = ? AND content_type = ? AND content_id = ? LIMIT 1', [viewer.no, type, id])) as any;
    if ((dup as any[]).length) throw new ConflictException('이미 신고한 콘텐츠입니다');
    let snippet = s(row.content ?? row.message);
    if (Array.from(snippet).length > 500) snippet = Array.from(snippet).slice(0, 497).join('') + '...';
    const [ins] = (await this.db.query(
      `INSERT INTO gangnamunni_content_reports (reporter_id, reporter_name, content_type, content_id, content_url, content_snippet, reported_user_id, reported_user_name, reason, reason_detail)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [viewer.no, viewer.name, type, id, this.contentUrl(type, id, row), snippet, mbNo, nick, enc.reason, enc.reasonDetail])) as any;
    return { success: true as const, reportId: n(ins?.insertId), message: '신고가 접수되었습니다' };
  }

  /** api_block_user.php (POST), by content so no login ids leave the server. Blocks hide content both ways. */
  async block(viewer: Viewer, input: { contentType?: unknown; contentId?: unknown }) {
    const type = s(input.contentType) as ContentType;
    const id = Number(input.contentId);
    if (!Object.prototype.hasOwnProperty.call(SOURCES, type) || !Number.isInteger(id) || id < 1 || id > 2147483647) throw new BadRequestException('유효하지 않은 콘텐츠입니다.');
    const { authorId, nick } = await this.contentAndAuthor(type, id);
    if (authorId === viewer.id) throw new BadRequestException('자기 자신을 차단할 수 없습니다.');
    await this.db.query(
      "INSERT IGNORE INTO gangnamunni_blocked_users (blocker_id, blocked_id, blocked_name, reason) VALUES (?, ?, ?, '')", [viewer.id, authorId, nick]);
    return { success: true as const, message: '사용자를 차단했습니다' };
  }

  async blocks(viewer: Viewer) {
    const [rows] = (await this.db.query('SELECT id, blocked_name, created_at FROM gangnamunni_blocked_users WHERE blocker_id = ? ORDER BY created_at DESC', [viewer.id])) as any;
    return { items: (rows as any[]).map((r) => ({ id: n(r.id), name: s(r.blocked_name) || '알 수 없음', createdAt: s(r.created_at) })) };
  }

  async unblock(viewer: Viewer, rowId: number) {
    const [res] = (await this.db.query('DELETE FROM gangnamunni_blocked_users WHERE id = ? AND blocker_id = ?', [rowId, viewer.id])) as any;
    if (!n(res?.affectedRows)) throw new NotFoundException('차단 내역을 찾을 수 없습니다.');
    return { success: true as const, message: '차단을 해제했습니다' };
  }

  /** api/like_review.php: toggle on the 시술후기 rows shown in the community list. */
  async toggleReviewLike(viewer: Viewer, reviewId: number): Promise<{ success: true; liked: boolean; count: number }> {
    const [rv] = (await this.db.query("SELECT id FROM hospital_reviews WHERE id = ? AND is_active = 1 AND review_status = 'published' LIMIT 1", [reviewId])) as any;
    if (!(rv as any[]).length) throw new NotFoundException('후기를 찾을 수 없습니다.');
    const [ex] = (await this.db.query('SELECT id FROM review_likes WHERE review_id = ? AND user_id = ? LIMIT 1', [reviewId, viewer.id])) as any;
    let liked: boolean;
    if ((ex as any[]).length) {
      await this.db.query('DELETE FROM review_likes WHERE review_id = ? AND user_id = ?', [reviewId, viewer.id]);
      liked = false;
    } else {
      await this.db.query('INSERT INTO review_likes (review_id, user_id) VALUES (?, ?)', [reviewId, viewer.id]);
      liked = true;
    }
    const [[c]] = (await this.db.query('SELECT COUNT(*) AS c FROM review_likes WHERE review_id = ?', [reviewId])) as any;
    const count = n(c?.c);
    await this.db.query('UPDATE hospital_reviews SET like_count = ? WHERE id = ?', [count, reviewId]);
    return { success: true as const, liked, count };
  }
}
