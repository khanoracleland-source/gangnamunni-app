import { BadRequestException, ForbiddenException, HttpException, Inject, Injectable, InternalServerErrorException, Logger, NotFoundException } from '@nestjs/common';
import { promises as fs } from 'node:fs';
import { join } from 'node:path';
import { DB, Db } from '../db/db.module';
import { AdminNotifyService } from '../admin-notify/admin-notify.service';
import { PointsService } from '../points/points.service';
import { sniffImage } from '../community/community.write.logic';
import {
  CONTENT_MAX, CONTENT_MIN, orderDoctors, originalName, parseDoctorIds, photoName, REVIEW_PHOTO_MAX_BYTES, REVIEW_PHOTO_SLOTS, safeBackUrl,
  TITLE_MAX, truthy, UNKNOWN_DOCTOR, validateReview,
} from './reviews.write.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;

export interface Member { id: string; name: string }
export interface UploadedPhoto { buffer: Buffer; size: number; originalname?: string }
export interface PhotoFiles { photos_before?: UploadedPhoto[]; photos_after?: UploadedPhoto[] }
export interface FormQuery { bookingId: number; hospitalId: number; editId: number; returnUrl?: unknown }

const MAIN_IMAGE = `COALESCE(
  (SELECT NULLIF(hi.image_url, '') FROM hospital_images hi WHERE hi.hospital_id=h.id AND hi.is_active=1 AND hi.image_type='main' ORDER BY hi.sort_order ASC, hi.id ASC LIMIT 1),
  NULLIF(h.main_image_url, ''),
  (SELECT NULLIF(hi.image_url, '') FROM hospital_images hi WHERE hi.hospital_id=h.id AND hi.is_active=1 AND hi.image_type='logo' ORDER BY hi.sort_order ASC, hi.id ASC LIMIT 1),
  NULLIF(h.profile_image_url, '')) AS main_image_url`;

/** Recomputes review_count / rating from the published reviews (same SQL as the PHP pages). */
export async function recountHospital(db: Db, hospitalId: number) {
  if (hospitalId <= 0) return;
  await db.query(
    `UPDATE gangnamunni_hospitals SET
       review_count=(SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id=? AND is_active=1),
       rating=(SELECT COALESCE(AVG(LEAST(5, GREATEST(1, rating))),0) FROM hospital_reviews WHERE hospital_id=? AND is_active=1)
     WHERE id=?`, [hospitalId, hospitalId, hospitalId]);
}

@Injectable()
export class ReviewsWriteService {
  private readonly log = new Logger(ReviewsWriteService.name);
  private attempts = new Map<string, number[]>();
  constructor(@Inject(DB) private readonly db: Db, private readonly notify: AdminNotifyService, private readonly points: PointsService) {}

  private rateLimit(key: string, max: number) {
    const now = Date.now();
    const l = (this.attempts.get(key) ?? []).filter((t) => t > now - 600_000);
    if (l.length >= max) throw new HttpException({ success: false, message: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' }, 429);
    l.push(now);
    this.attempts.set(key, l);
    if (this.attempts.size > 10_000) this.attempts.clear();
  }

  /** Own, published review (the only kind review_write.php lets you edit). */
  private async ownEditable(mbId: string, id: number): Promise<any | null> {
    const [rows] = (await this.db.query('SELECT * FROM hospital_reviews WHERE id = ? AND user_id = ? AND is_active = 1 LIMIT 1', [id, mbId])) as any;
    return (rows as any[])[0] ?? null;
  }

  private async ownBooking(mbId: string, bookingId: number): Promise<any | null> {
    const [rows] = (await this.db.query(
      `SELECT b.hospital_id, b.hospital_name AS name, b.treatment, b.booking_date, h.address, ${MAIN_IMAGE},
              TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS doctor_name
         FROM gangnamunni_bookings b
         LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
         LEFT JOIN appointments ap ON b.hms_appointment_id = ap.id
         LEFT JOIN doctors doc ON ap.doctor_id = doc.id
         LEFT JOIN users u ON doc.user_id = u.id
        WHERE b.id = ? AND b.mb_id = ? LIMIT 1`, [bookingId, mbId])) as any;
    return (rows as any[])[0] ?? null;
  }

  private async hospitalCard(hospitalId: number): Promise<any | null> {
    const [rows] = (await this.db.query(`SELECT h.name, h.address, ${MAIN_IMAGE} FROM gangnamunni_hospitals h WHERE h.id = ? LIMIT 1`, [hospitalId])) as any;
    return (rows as any[])[0] ?? null;
  }

  private async bookingReview(bookingId: number): Promise<any | null> {
    const [rows] = (await this.db.query('SELECT id, review_status, is_active FROM hospital_reviews WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [bookingId])) as any;
    return (rows as any[])[0] ?? null;
  }

  /** review_write.php (GET): hospital card, the hospital's doctors and treatments, and — for ?id= — the member's own review. */
  async form(m: Member, q: FormQuery) {
    let edit: any = null;
    if (q.editId > 0) {
      edit = await this.ownEditable(m.id, q.editId);
      if (!edit) throw new NotFoundException('수정할 수 있는 후기가 아닙니다.');
    }
    let card: any = null;
    let bookingId = 0;
    if (q.bookingId > 0) {
      card = await this.ownBooking(m.id, q.bookingId);
      if (card) bookingId = q.bookingId;
    }
    if (!card) {
      const hid = edit ? n(edit.hospital_id) : q.hospitalId;
      if (hid > 0) card = await this.hospitalCard(hid);
      if (card) card.hospital_id = hid;
    }
    const hospitalId = n(card?.hospital_id) || (edit ? n(edit.hospital_id) : q.hospitalId);

    let doctors: any[] = [];
    let treatments: string[] = [];
    if (hospitalId > 0) {
      const [d] = (await this.db.query(
        `SELECT d.id, d.name, d.specialty, d.photo_url,
                (SELECT GROUP_CONCAT(DISTINCT tc.name ORDER BY tc.sort_order SEPARATOR '||')
                   FROM gangnamunni_doctor_treatments dt
                   JOIN gangnamunni_treatment_master tm ON dt.treatment_id=tm.id
                   JOIN gangnamunni_treatment_categories tc ON tm.category_id=tc.id
                  WHERE dt.doctor_id=d.id AND dt.is_active=1) AS treatment_tags
           FROM gangnamunni_doctors d WHERE d.hospital_id=? AND d.status='active' ORDER BY d.id ASC`, [hospitalId])) as any;
      doctors = d as any[];
      const [t] = (await this.db.query(
        `SELECT DISTINCT tm.name, tc.sort_order, tm.id
           FROM gangnamunni_hospital_treatments ht
           JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
           LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
          WHERE ht.hospital_id = ? AND ht.status = 'active' AND tm.is_active = 1
          ORDER BY COALESCE(tc.sort_order, 999999) ASC, tm.name ASC, tm.id ASC`, [hospitalId])) as any;
      treatments = (t as any[]).map((r) => s(r.name)).filter(Boolean);
    }
    // What the member actually had done: the booking's treatment (kept even if it is not in the master list), or the review's own.
    let selected = edit ? s(edit.treatments).trim() : s(card?.treatment).trim();
    if (!edit && selected) treatments = Array.from(new Set([selected, ...treatments]));

    const editNames = s(edit?.doctor_name).split(',').map((x) => x.trim()).filter(Boolean);
    const unknown = !!edit && s(edit.doctor_name).trim() === UNKNOWN_DOCTOR;
    const cardDoctor = s(card?.doctor_name).trim();
    const selectedIds: number[] = [];
    if (!unknown) {
      if (edit && n(edit.doctor_id) > 0) selectedIds.push(n(edit.doctor_id));
      for (const d of doctors) {
        const id = n(d.id);
        const name = s(d.name).trim();
        if (!selectedIds.includes(id) && (editNames.includes(name) || (!selectedIds.length && !edit && cardDoctor !== '' && cardDoctor === name))) selectedIds.push(id);
      }
    }
    return {
      mode: edit ? 'edit' : 'create',
      hospitalId, bookingId, editId: edit ? n(edit.id) : 0,
      backUrl: safeBackUrl(q.returnUrl),
      card: card ? {
        name: s(card.name), address: s(card.address), imageUrl: s(card.main_image_url) || null,
        treatment: s(card.treatment), doctorName: cardDoctor, bookingDate: s(card.booking_date).slice(0, 10),
      } : null,
      treatments, selectedTreatment: selected,
      doctors: doctors.map((d) => ({
        id: n(d.id), name: s(d.name), specialty: s(d.specialty) || '전문의', photoUrl: s(d.photo_url) || null,
        tags: s(d.treatment_tags).split('||').filter(Boolean).slice(0, 4),
      })),
      selectedDoctorIds: selectedIds, doctorUnknown: unknown,
      review: edit ? { rating: n(edit.rating), title: s(edit.title), content: s(edit.content) } : null,
      limits: { photoSlots: REVIEW_PHOTO_SLOTS, photoBytes: REVIEW_PHOTO_MAX_BYTES, titleMax: TITLE_MAX, contentMin: CONTENT_MIN, contentMax: CONTENT_MAX },
    };
  }

  private checkPhotos(files: UploadedPhoto[] | undefined) {
    const list = files ?? [];
    if (list.length > REVIEW_PHOTO_SLOTS) throw new BadRequestException('시술 전·후 사진은 각각 최대 2장까지 첨부할 수 있습니다.');
    return list.map((f) => {
      if (f.size > REVIEW_PHOTO_MAX_BYTES) throw new BadRequestException('각 사진은 5MB 이하만 첨부할 수 있습니다.');
      const kind = sniffImage(f.buffer);
      if (!kind) throw new BadRequestException('허용되지 않는 이미지 형식입니다. (JPG/PNG/GIF/WebP만 가능)');
      return { f, kind };
    });
  }

  /** rw_save_review_photos(): a new upload replaces that category's rows; files go to uploads/reviews/<id>/. */
  private async savePhotos(reviewId: number, category: 'before' | 'after', items: { f: UploadedPhoto; kind: { ext: string } }[]): Promise<boolean> {
    if (!items.length) return true;
    const root = process.env.REVIEW_UPLOAD_DIR;
    if (!root) { this.log.error('REVIEW_UPLOAD_DIR is not set; review photos were not saved'); return false; }
    const dir = join(root, String(reviewId));
    const written: string[] = [];
    try {
      await fs.mkdir(dir, { recursive: true, mode: 0o775 });
      const rows: [number, string, number, number, string, string][] = [];
      for (let i = 0; i < items.length; i++) {
        const name = photoName(category, items[i].kind.ext, Date.now(), 1000 + Math.floor(Math.random() * 9000) + i);
        await fs.writeFile(join(dir, name), items[i].f.buffer, { mode: 0o664, flag: 'wx' });
        written.push(join(dir, name));
        rows.push([reviewId, `uploads/reviews/${reviewId}/${name}`, i, items[i].f.size, originalName(items[i].f.originalname), category]);
      }
      await this.db.query('DELETE FROM review_images WHERE review_id = ? AND photo_category = ?', [reviewId, category]);
      for (const r of rows) {
        await this.db.query('INSERT INTO review_images (review_id, image_url, image_order, file_size, original_name, photo_category) VALUES (?, ?, ?, ?, ?, ?)', r);
      }
      return true;
    } catch (err) {
      this.log.error(`review photo save failed (review ${reviewId}): ${(err as Error).message}`);
      await Promise.all(written.map((p) => fs.unlink(p).catch(() => undefined)));
      return false;
    }
  }

  private async announce(hospitalId: number, userName: string, rating: number) {
    try {
      const [h] = (await this.db.query('SELECT name FROM gangnamunni_hospitals WHERE id = ? LIMIT 1', [hospitalId])) as any;
      await this.notify.reviewCreated(s((h as any[])[0]?.name), userName, rating);
    } catch (e) { this.log.error(`admin notice failed: ${(e as Error).message}`); }
  }

  /** review_write.php (POST): create, or edit when `editId` is given. Either way the review waits for admin approval. */
  async submit(m: Member, body: Record<string, unknown>, files: PhotoFiles): Promise<{ success: true; id: number; already?: boolean; photoError?: boolean; message: string }> {
    this.rateLimit(`review:${m.id}`, Number(process.env.REVIEW_RATE_MAX) || 10);
    const editId = n(body.edit_id);
    const title = s(body.title).trim();
    const content = s(body.content).trim();
    const rating = Number(body.rating);
    const doctorUnknown = truthy(body.doctor_unknown);
    const agreed = truthy(body.agree_guidelines);
    const before = this.checkPhotos(files.photos_before);
    const after = this.checkPhotos(files.photos_after);
    if (before.length + after.length > 0 && !truthy(body.photo_consent)) throw new BadRequestException('사진 첨부 시 동의가 필요합니다.');

    // The hospital (and treatment) always come from the member's own data, never from what the form posts.
    let hospitalId = n(body.hospital_id);
    let treatments = '';
    let bookingId = 0;
    let edit: any = null;
    if (editId > 0) {
      edit = await this.ownEditable(m.id, editId);
      if (!edit) throw new NotFoundException('수정할 수 있는 후기가 아닙니다.');
      treatments = s(edit.treatments).trim();
      hospitalId = n(edit.hospital_id);
    } else if (n(body.booking_id) > 0) {
      const bk = await this.ownBooking(m.id, n(body.booking_id));
      if (!bk) throw new ForbiddenException('본인의 예약만 후기를 작성할 수 있습니다.');
      bookingId = n(body.booking_id);
      hospitalId = n(bk.hospital_id);
      treatments = s(bk.treatment).trim();
      const existing = await this.bookingReview(bookingId);
      if (existing) return { success: true, id: n(existing.id), already: true, message: '이미 후기를 작성한 예약입니다.' };
    }

    // Doctors must belong to the hospital and be active; the member's order is kept.
    const picked = parseDoctorIds(body.doctor_ids);
    let doctorId = 0;
    let doctorName = doctorUnknown ? UNKNOWN_DOCTOR : '';
    if (!doctorUnknown && picked.length && hospitalId > 0) {
      const [rows] = (await this.db.query(`SELECT id, name FROM gangnamunni_doctors WHERE id IN (${picked.map(() => '?').join(',')}) AND hospital_id = ? AND status = 'active'`, [...picked, hospitalId])) as any;
      const valid = new Map<number, string>((rows as any[]).map((r) => [n(r.id), s(r.name)]));
      const o = orderDoctors(picked, valid);
      if (o) { doctorId = o.doctorId; doctorName = o.doctorName; }
    }
    const err = validateReview({ hospitalId, rating, title, content, doctorUnknown, agreed, hasDoctor: doctorName !== '' });
    if (err) throw new BadRequestException(err);

    if (!edit) {
      const [h] = (await this.db.query('SELECT name FROM gangnamunni_hospitals WHERE id = ? AND is_active = 1 LIMIT 1', [hospitalId])) as any;
      if (!(h as any[])[0]) throw new BadRequestException('병원 정보를 확인해주세요.');
    }

    let reviewId: number;
    try {
      if (edit) {
        await this.db.query(
          `UPDATE hospital_reviews SET hospital_id=?, title=?, content=?, rating=?, treatments=?, doctor_id=NULLIF(?,0), doctor_name=?, review_status='pending', is_active=0, updated_at=NOW()
            WHERE id=? AND user_id=?`, [hospitalId, title, content, rating, treatments, doctorId, doctorName, editId, m.id]);
        reviewId = editId;
      } else {
        const [r] = (await this.db.query(
          `INSERT INTO hospital_reviews (hospital_id, user_id, user_name, title, content, rating, treatments, booking_id, doctor_id, doctor_name, review_status, is_active, created_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), NULLIF(?,0), ?, 'pending', 0, NOW())`,
          [hospitalId, m.id, m.name, title, content, rating, treatments, bookingId, doctorId, doctorName])) as any;
        reviewId = Number(r.insertId);
      }
    } catch (e) {
      this.log.error(`review save failed: ${(e as Error).message}`);
      throw new InternalServerErrorException(edit ? '수정 중 오류가 발생했습니다.' : '등록 중 오류가 발생했습니다.');
    }

    // The hospital's rating is a derived value: a failed recount is logged but never fails (or half-fails) the save.
    if (edit) {
      for (const hid of new Set([n(edit.hospital_id), hospitalId])) {
        await recountHospital(this.db, hid).catch((e) => this.log.error(`hospital ${hid} recount failed: ${(e as Error).message}`));
      }
    }
    const okB = await this.savePhotos(reviewId, 'before', before);
    const okA = await this.savePhotos(reviewId, 'after', after);

    // Admin notices (in-app, push, log) go out after the response: slow FCM must not keep the member waiting.
    if (!edit) {
      void this.announce(hospitalId, m.name, rating);
    }
    return {
      success: true, id: reviewId, ...(okB && okA ? {} : { photoError: true }),
      message: edit ? '후기가 수정되었습니다. 관리자 승인 후 다시 공개됩니다.' : '후기가 등록되었습니다. 관리자 승인 후 공개됩니다.',
    };
  }

  /** my_reviews.php (POST action=delete): soft delete of the member's own review, then the point reversal and the hospital recount. */
  async remove(m: Member, id: number): Promise<{ success: true; deleted: boolean }> {
    const [rows] = (await this.db.query('SELECT hospital_id, is_active FROM hospital_reviews WHERE id = ? AND user_id = ? LIMIT 1', [id, m.id])) as any;
    const r = (rows as any[])[0];
    if (!r) throw new NotFoundException('후기를 찾을 수 없습니다.');
    try {
      await this.db.query("UPDATE hospital_reviews SET is_active = 0, review_status = 'deleted' WHERE id = ? AND user_id = ?", [id, m.id]);
    } catch (e) {
      this.log.error(`review delete failed: ${(e as Error).message}`);
      throw new InternalServerErrorException('삭제 중 오류가 발생했습니다.');
    }
    // Derived data after the fact: each step logs its own failure and never undoes the delete (PHP behaves the same).
    await this.points.revokeReviewEarnings(id, '후기 삭제 회수');
    if (n(r.is_active) === 1) {
      await recountHospital(this.db, n(r.hospital_id)).catch((e) => this.log.error(`hospital ${n(r.hospital_id)} recount failed: ${(e as Error).message}`));
    }
    return { success: true, deleted: true };
  }
}
