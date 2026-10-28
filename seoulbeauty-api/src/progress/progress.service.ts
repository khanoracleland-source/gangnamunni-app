import { BadRequestException, ForbiddenException, HttpException, Inject, Injectable, InternalServerErrorException, Logger, NotFoundException } from '@nestjs/common';
import { promises as fs } from 'node:fs';
import { join } from 'node:path';
import { DB, Db } from '../db/db.module';
import { kstNow } from '../bookings/bookings.logic';
import { sniffImage } from '../community/community.write.logic';
import { PointsService } from '../points/points.service';
import {
  awardPoint, cut, dot, dPlus, editable, ENTRY_MAX, ENTRY_MIN, Kind, kindLabel, kindOf, MAX_ENTRY_PHOTOS, PHOTO_MAX_BYTES, photoFile, photoList,
  requiredEntries, STATUS_LABEL, validDate,
} from './progress.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;
export interface Member { id: string; no: number }
export interface UploadedPhoto { buffer: Buffer; size: number }

/** 경과 후기 is a 포인트제도 v2 feature: everything is off (`enabled: false`) unless v2_progress_enabled is on. */
@Injectable()
export class ProgressService {
  private readonly log = new Logger(ProgressService.name);
  private attempts = new Map<string, number[]>();
  constructor(@Inject(DB) private readonly db: Db, private readonly points: PointsService) {}

  private rateLimit(key: string, max: number) {
    const now = Date.now();
    const l = (this.attempts.get(key) ?? []).filter((t) => t > now - 600_000);
    if (l.length >= max) throw new HttpException({ success: false, message: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' }, 429);
    l.push(now);
    this.attempts.set(key, l);
    if (this.attempts.size > 10_000) this.attempts.clear();
  }

  private async settings() { return this.points.allSettings(); }
  private on(st: Record<string, string>) { return PointsService.v2On(st, 'v2_progress_enabled'); }
  private today() { return kstNow().slice(0, 10); }

  /** pr_load(): the member's own, not-deleted progress review with its entry count. */
  private async load(m: Member, id: number): Promise<any> {
    const [rows] = (await this.db.query(
      `SELECT p.*, h.name AS hospital_name, (SELECT COUNT(*) FROM gangnamunni_progress_entries e WHERE e.progress_id = p.id) AS entry_count
         FROM gangnamunni_progress_reviews p LEFT JOIN gangnamunni_hospitals h ON h.id = p.hospital_id
        WHERE p.id = ? AND p.member_id = ? AND p.status <> 'deleted' LIMIT 1`, [id, m.no])) as any;
    const r = (rows as any[])[0];
    if (!r) throw new NotFoundException('경과 후기를 찾을 수 없습니다.');
    return r;
  }

  private card(st: Record<string, string>, p: any) {
    const kind = kindOf(p.kind);
    const need = requiredEntries(st, kind);
    const count = n(p.entry_count);
    return {
      id: n(p.id), title: s(p.title), kind, kindLabel: kindLabel(kind), status: s(p.status), statusLabel: STATUS_LABEL[s(p.status)] ?? s(p.status),
      hospitalName: s(p.hospital_name), treatment: s(p.treatment_name), procedureDate: s(p.procedure_date).slice(0, 10),
      entryCount: count, need, percent: Math.min(100, Math.round((count / Math.max(1, need)) * 100)),
    };
  }

  /** my_progress_reviews.php (GET): rules, completed bookings to pick from, and the member's own list. */
  async list(m: Member) {
    const st = await this.settings();
    if (!this.on(st)) return { enabled: false as const };
    const [bk] = (await this.db.query(
      "SELECT id, hospital_id, hospital_name, treatment, booking_date FROM gangnamunni_bookings WHERE mb_id = ? AND status = 'completed' ORDER BY booking_date DESC, id DESC LIMIT 50", [m.id])) as any;
    const [rows] = (await this.db.query(
      `SELECT p.*, h.name AS hospital_name, (SELECT COUNT(*) FROM gangnamunni_progress_entries e WHERE e.progress_id = p.id) AS entry_count
         FROM gangnamunni_progress_reviews p LEFT JOIN gangnamunni_hospitals h ON h.id = p.hospital_id
        WHERE p.member_id = ? AND p.status <> 'deleted' ORDER BY p.id DESC`, [m.no])) as any;
    return {
      enabled: true as const,
      rules: { procedure: { need: requiredEntries(st, 'procedure'), point: awardPoint(st, 'procedure') }, surgery: { need: requiredEntries(st, 'surgery'), point: awardPoint(st, 'surgery') } },
      today: this.today(),
      bookings: (bk as any[]).map((b) => ({ id: n(b.id), label: `${s(b.booking_date).slice(0, 10)} ${s(b.hospital_name)} · ${s(b.treatment)}` })),
      items: (rows as any[]).map((p) => this.card(st, p)),
    };
  }

  /** my_progress_reviews.php (POST action=create) */
  async create(m: Member, body: Record<string, unknown>): Promise<{ success: true; id: number }> {
    const st = await this.settings();
    if (!this.on(st)) throw new ForbiddenException('경과 후기는 준비 중입니다.');
    this.rateLimit(`progress:${m.id}`, 20);
    if (m.no <= 0) throw new BadRequestException('회원 정보를 확인할 수 없습니다.');
    const bookingId = n(body.booking_id);
    const kind = kindOf(body.kind);
    let title = cut(body.title, 100);
    let treatment = cut(body.treatment_name, 100);
    let pdate = validDate(body.procedure_date, this.today());
    let hospitalId: number | null = null;
    if (bookingId > 0) {
      const [rows] = (await this.db.query("SELECT hospital_id, treatment, booking_date FROM gangnamunni_bookings WHERE id = ? AND mb_id = ? AND status = 'completed' LIMIT 1", [bookingId, m.id])) as any;
      const b = (rows as any[])[0];
      if (!b) throw new BadRequestException('선택한 예약을 찾을 수 없습니다.');
      hospitalId = n(b.hospital_id);
      if (!treatment) treatment = s(b.treatment);
      if (!pdate) pdate = s(b.booking_date).slice(0, 10);
    }
    if (!treatment) throw new BadRequestException('시술·수술명을 입력해주세요.');
    if (!pdate) throw new BadRequestException('시술일을 입력해주세요.');
    if (!title) title = `${treatment} 경과 후기`;
    try {
      const [r] = (await this.db.query(
        "INSERT INTO gangnamunni_progress_reviews (member_id, mb_id, hospital_id, booking_id, treatment_name, kind, procedure_date, title, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'writing')",
        [m.no, m.id, hospitalId, bookingId > 0 ? bookingId : null, treatment, kind, pdate, title])) as any;
      return { success: true, id: Number(r.insertId) };
    } catch (e) {
      this.log.error(`progress create failed: ${(e as Error).message}`);
      throw new InternalServerErrorException('저장하지 못했습니다. 잠시 후 다시 시도해주세요.');
    }
  }

  /** progress_review.php (GET) */
  async detail(m: Member, id: number) {
    const st = await this.settings();
    if (!this.on(st)) return { enabled: false as const };
    const p = await this.load(m, id);
    const resubmitDays = PointsService.int(st, 'v2_progress_resubmit_days', 30);
    const [rows] = (await this.db.query('SELECT id, entry_date, content, photo_urls FROM gangnamunni_progress_entries WHERE progress_id = ? ORDER BY entry_date, id', [id])) as any;
    const pdate = s(p.procedure_date).slice(0, 10);
    const card = this.card(st, p);
    return {
      enabled: true as const,
      ...card,
      rejectReason: s(p.reject_reason) || '기준 미충족',
      awardedPoint: n(p.awarded_point),
      editable: editable(card.status, s(p.created_at), resubmitDays),
      resubmitDays,
      today: this.today(),
      limits: { entryMin: ENTRY_MIN, entryMax: ENTRY_MAX, photos: MAX_ENTRY_PHOTOS, photoBytes: PHOTO_MAX_BYTES },
      entries: (rows as any[]).map((e, i) => ({
        id: n(e.id), round: i + 1, date: s(e.entry_date).slice(0, 10), dateLabel: dot(s(e.entry_date)), dPlus: dPlus(s(e.entry_date).slice(0, 10), pdate), content: s(e.content),
        photos: photoList(e.photo_urls).map((_, pi) => `/v1/mypage/progress/photo/${n(e.id)}/${pi}`),
      })),
    };
  }

  private async editableOrFail(m: Member, id: number, st: Record<string, string>) {
    const p = await this.load(m, id);
    const ok = editable(s(p.status), s(p.created_at), PointsService.int(st, 'v2_progress_resubmit_days', 30));
    return { p, ok };
  }

  /** progress_review.php (POST action=add_entry): one entry per date, 20+ chars, up to 5 photos stored as JSON paths. */
  async addEntry(m: Member, id: number, body: Record<string, unknown>, files: UploadedPhoto[]): Promise<{ success: true; id: number; photoError?: boolean }> {
    const st = await this.settings();
    if (!this.on(st)) throw new ForbiddenException('경과 후기는 준비 중입니다.');
    this.rateLimit(`progress:${m.id}`, 20);
    const { p, ok } = await this.editableOrFail(m, id, st);
    if (!ok) throw new BadRequestException('지금은 경과를 추가할 수 없습니다.');
    const content = s(body.content).trim();
    const today = this.today();
    const edate = validDate(body.entry_date === undefined || body.entry_date === '' ? today : body.entry_date, today);
    const pdate = s(p.procedure_date).slice(0, 10);
    if (Array.from(content).length < ENTRY_MIN) throw new BadRequestException('경과 내용은 20자 이상 작성해주세요.');
    if (Array.from(content).length > ENTRY_MAX) throw new BadRequestException(`경과 내용은 ${ENTRY_MAX}자 이내로 작성해주세요.`);
    if (!edate) throw new BadRequestException('기록 날짜를 확인해주세요.');
    if (pdate && edate < pdate) throw new BadRequestException('기록 날짜는 시술일 이후여야 합니다.');
    if (files.length > MAX_ENTRY_PHOTOS) throw new BadRequestException('사진은 최대 5장까지 첨부할 수 있습니다.');
    const checked = files.map((f) => {
      if (f.size > PHOTO_MAX_BYTES) throw new BadRequestException('각 사진은 5MB 이하만 첨부할 수 있습니다.');
      const kind = sniffImage(f.buffer);
      if (!kind) throw new BadRequestException('허용되지 않는 이미지 형식입니다. (JPG/PNG/GIF/WebP만 가능)');
      return { f, ext: kind.ext };
    });
    const [dup] = (await this.db.query('SELECT COUNT(*) c FROM gangnamunni_progress_entries WHERE progress_id = ? AND entry_date = ?', [id, edate])) as any;
    if (n((dup as any[])[0]?.c) > 0) throw new BadRequestException('같은 날짜의 경과가 이미 있습니다.');
    let eid: number;
    try {
      const [r] = (await this.db.query('INSERT INTO gangnamunni_progress_entries (progress_id, entry_date, content) VALUES (?, ?, ?)', [id, edate, content])) as any;
      eid = Number(r.insertId);
    } catch (e) {
      this.log.error(`progress entry insert failed: ${(e as Error).message}`);
      throw new InternalServerErrorException('저장하지 못했습니다. 잠시 후 다시 시도해주세요.');
    }
    let photoError = false;
    if (checked.length) {
      const root = process.env.PROGRESS_UPLOAD_DIR;
      if (!root) { this.log.error('PROGRESS_UPLOAD_DIR is not set; progress photos were not saved'); photoError = true; }
      else {
        const dir = join(root, String(eid));
        const written: string[] = [];
        try {
          await fs.mkdir(dir, { recursive: true, mode: 0o775 });
          const rel: string[] = [];
          for (let i = 0; i < checked.length; i++) {
            const name = `${Math.floor(Date.now() / 1000)}_${1000 + Math.floor(Math.random() * 9000)}_${i}.${checked[i].ext}`;
            await fs.writeFile(join(dir, name), checked[i].f.buffer, { mode: 0o664, flag: 'wx' });
            written.push(join(dir, name));
            rel.push(`uploads/progress/${eid}/${name}`);
          }
          await this.db.query('UPDATE gangnamunni_progress_entries SET photo_urls = ?, photo_consent = ? WHERE id = ?', [JSON.stringify(rel), body.photo_consent === '1' || body.photo_consent === 1 || body.photo_consent === true ? 1 : 0, eid]);
        } catch (e) {
          this.log.error(`progress photo save failed (entry ${eid}): ${(e as Error).message}`);
          await Promise.all(written.map((f) => fs.unlink(f).catch(() => undefined)));
          photoError = true;
        }
      }
    }
    return { success: true, id: eid, ...(photoError ? { photoError } : {}) };
  }

  /** progress_review.php (POST action=delete_entry) */
  async deleteEntry(m: Member, id: number, entryId: number): Promise<{ success: true }> {
    const st = await this.settings();
    if (!this.on(st)) throw new ForbiddenException('경과 후기는 준비 중입니다.');
    const { ok } = await this.editableOrFail(m, id, st);
    if (!ok) throw new BadRequestException('지금은 경과를 삭제할 수 없습니다.');
    await this.db.query('DELETE FROM gangnamunni_progress_entries WHERE id = ? AND progress_id = ?', [entryId, id]);
    return { success: true };
  }

  /** progress_review.php (POST action=submit): needs the required number of entries; then it waits for review. */
  async submit(m: Member, id: number): Promise<{ success: true; message: string }> {
    const st = await this.settings();
    if (!this.on(st)) throw new ForbiddenException('경과 후기는 준비 중입니다.');
    const { p, ok } = await this.editableOrFail(m, id, st);
    if (!ok) throw new BadRequestException('지금은 작성 완료할 수 없습니다.');
    const need = requiredEntries(st, kindOf(p.kind));
    if (n(p.entry_count) < need) throw new BadRequestException(`경과를 ${need}회 이상 기록해야 작성 완료할 수 있습니다.`);
    await this.db.query("UPDATE gangnamunni_progress_reviews SET status = 'submitted', submitted_at = NOW(), reject_reason = NULL WHERE id = ? AND member_id = ? AND status IN ('writing','rejected')", [id, m.no]);
    return { success: true, message: '작성 완료되었습니다. 검수 후 영업일 기준 3일 내 적립됩니다.' };
  }

  /** progress_review.php (POST action=delete): soft delete, then the awarded points (if any) are taken back. */
  async remove(m: Member, id: number): Promise<{ success: true }> {
    const st = await this.settings();
    if (!this.on(st)) throw new ForbiddenException('경과 후기는 준비 중입니다.');
    const p = await this.load(m, id);
    if (s(p.status) === 'submitted') throw new BadRequestException('검수 중인 경과 후기는 삭제할 수 없습니다.');
    await this.db.query("UPDATE gangnamunni_progress_reviews SET status = 'deleted' WHERE id = ? AND member_id = ?", [id, m.no]);
    try { await this.points.revokeEarn('progress', id, '경과 후기 삭제 회수'); } catch (e) { this.log.error(`progress revoke #${id}: ${(e as Error).message}`); }
    return { success: true };
  }

  /** progress_photo.php (member branch): the owner's own entry photo, served from the private uploads dir. */
  async photo(m: Member, entryId: number, index: number): Promise<{ path: string; mime: string; name: string }> {
    const [rows] = (await this.db.query(
      'SELECT e.photo_urls, p.member_id, p.status FROM gangnamunni_progress_entries e JOIN gangnamunni_progress_reviews p ON p.id = e.progress_id WHERE e.id = ? LIMIT 1', [entryId])) as any;
    const r = (rows as any[])[0];
    if (!r || n(r.member_id) !== m.no || s(r.status) === 'deleted') throw new NotFoundException('사진을 찾을 수 없습니다.');
    const rel = photoList(r.photo_urls)[index] ?? '';
    const name = photoFile(rel, entryId);
    const root = process.env.PROGRESS_UPLOAD_DIR;
    if (!name || !root) throw new NotFoundException('사진을 찾을 수 없습니다.');
    const path = join(root, String(entryId), name);
    let head: Buffer;
    try {
      const fh = await fs.open(path, 'r');
      try { head = (await fh.read(Buffer.alloc(16), 0, 16, 0)).buffer; } finally { await fh.close(); }
    } catch { throw new NotFoundException('사진을 찾을 수 없습니다.'); }
    const kind = sniffImage(head);
    if (!kind) throw new NotFoundException('사진을 찾을 수 없습니다.');
    return { path, mime: kind.mime, name };
  }
}
