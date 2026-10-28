import { BadRequestException, Inject, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { promises as fs } from 'node:fs';
import { join } from 'node:path';
import { DB, Db } from '../db/db.module';
import { safeLink } from '../home/home.service';
import { addressShort, CONSULT_EVENT_LABELS, CONSULT_SLOTS, consultDt, consultStatus, consultTypeLabel, dotDate, profileFileId, reviewDistrict, safeHttpUrl, StatusTone, telDigits, wishlistDistrict } from './mypage.logic';

export interface MemberCtx { id: string; no: number; nickname: string; phone: string }
export interface MypageAd { id: number; title: string; subtitle: string | null; imageUrl: string | null; linkUrl: string }
export interface MypageSummary {
  nickname: string; avatarUrl: string | null; bookingCount: number; consultCount: number;
  points: { visible: boolean; balance: number };
  ads: { show: boolean; autoScroll: boolean; items: MypageAd[] };
}

const n = (v: unknown) => Number(v ?? 0) || 0;
const s = (v: unknown) => (v == null ? '' : String(v));

@Injectable()
export class MypageService {
  private readonly log = new Logger(MypageService.name);
  constructor(@Inject(DB) private readonly db: Db) {}

  /** Query that may hit a table/column the prod DB does not have yet: log it and use the fallback. */
  private async safe<T>(label: string, run: () => Promise<T>, fallback: T): Promise<T> {
    try { return await run(); } catch (e) { this.log.error(`${label}: ${(e as Error).message}`); return fallback; }
  }

  private async avatar(mbId: string): Promise<string | null> {
    const dir = process.env.PROFILE_PHOTO_DIR;
    const id = profileFileId(mbId);
    if (!dir || !id) return null;
    const prefix = (process.env.PROFILE_PHOTO_URL || '/hairwang/gangnamunni/uploads/profile/').replace(/\/?$/, '/');
    for (const ext of ['jpg', 'jpeg', 'png', 'webp']) {
      try {
        const st = await fs.stat(join(dir, `${id}.${ext}`));
        if (st.isFile()) return `${prefix}${encodeURIComponent(id)}.${ext}?v=${Math.floor(st.mtimeMs / 1000)}`;
      } catch { /* try the next extension */ }
    }
    return null;
  }

  /** mypage.php: counters, the optional point balance and the (mypage-specific) ad carousel. */
  async summary(m: MemberCtx): Promise<MypageSummary> {
    const phone = m.phone.replace(/[^0-9]/g, '');
    const pointsVisible = process.env.MYPAGE_POINTS_VISIBLE === 'true';
    const [avatarUrl, bookingCount, consultCount, balance, adsRaw, settings, theme] = await Promise.all([
      this.avatar(m.id),
      this.safe('booking count', async () => {
        const [r] = (await this.db.query(
          `SELECT COUNT(*) AS c FROM gangnamunni_bookings
           WHERE mb_id = ? OR ((mb_id IS NULL OR mb_id = '') AND ? <> '' AND REPLACE(REPLACE(phone, '-', ''), ' ', '') = ?)`, [m.id, phone, phone])) as any;
        return n((r as any[])[0]?.c);
      }, 0),
      this.safe('consult count', async () => {
        const [r] = (await this.db.query('SELECT COUNT(*) AS c FROM gangnamunni_consultations WHERE mb_id = ?', [m.id])) as any;
        return n((r as any[])[0]?.c);
      }, 0),
      pointsVisible && m.no > 0
        ? this.safe('point balance', async () => {
            const [r] = (await this.db.query('SELECT balance FROM gangnamunni_points WHERE member_id = ? ORDER BY id DESC LIMIT 1', [m.no])) as any;
            return n((r as any[])[0]?.balance);
          }, 0)
        : Promise.resolve(0),
      this.safe('ads', async () => {
        const [r] = (await this.db.query(
          `SELECT a.* FROM gangnamunni_ads a
           WHERE a.status = 'approved'
             AND NOT EXISTS (SELECT 1 FROM gangnamunni_settings st WHERE st.setting_key = CONCAT('home_banner_enabled_', a.id) AND st.setting_value = '0')
           ORDER BY a.display_order ASC LIMIT 10`)) as any;
        return r as any[];
      }, [] as any[]),
      this.safe('ad setting', async () => {
        const [r] = (await this.db.query("SELECT setting_value FROM gangnamunni_settings WHERE setting_key = 'ad_show_index' LIMIT 1")) as any;
        return s((r as any[])[0]?.setting_value);
      }, ''),
      this.safe('ad theme', async () => {
        const [r] = (await this.db.query("SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = 'ad_auto_scroll_enabled' LIMIT 1")) as any;
        return (r as any[])[0] ? s((r as any[])[0].setting_value) : '1';
      }, '1'),
    ]);
    const items = adsRaw.map((a) => ({
      id: n(a.id), title: s(a.title), subtitle: a.subtitle ? s(a.subtitle) : null,
      imageUrl: s(a.mypage_image_url) || s(a.image_url) || null, // mypage-specific image first (ad_banner_image_for_page)
      linkUrl: safeLink(a.link_url),
    }));
    return {
      nickname: m.nickname || m.id, avatarUrl, bookingCount, consultCount,
      points: { visible: pointsVisible, balance },
      ads: { show: settings === '1' && items.length > 0, autoScroll: theme === '1', items },
    };
  }

  /** my_wishlist.php (hospitals only). */
  async wishlist(m: MemberCtx) {
    const [rows] = (await this.db.query(
      `SELECT w.id, w.target_id, h.name AS target_name, h.main_image_url AS target_image, h.address AS target_address,
              CASE WHEN h.id IS NOT NULL AND h.is_active = 1 AND COALESCE(h.is_demo, 0) = 0 AND (h.status = 'approved' OR h.status IS NULL) THEN 1 ELSE 0 END AS hospital_available
       FROM gangnamunni_wishlist w LEFT JOIN gangnamunni_hospitals h ON w.target_id = h.id
       WHERE w.member_id = ? AND w.target_type = 'hospital' ORDER BY w.created_at DESC`, [m.no])) as any;
    const items = rows as any[];
    const ids = [...new Set(items.map((i) => n(i.target_id)).filter((x) => x > 0))];
    const main = new Map<number, string>();
    const logo = new Map<number, string>();
    if (ids.length) {
      const [imgs] = (await this.db.query(
        `SELECT hospital_id, image_url, image_type FROM hospital_images
         WHERE hospital_id IN (${ids.map(() => '?').join(',')}) AND is_active = 1 AND image_type IN ('main','logo')
         ORDER BY hospital_id ASC, sort_order ASC, id ASC`, ids)) as any;
      for (const r of imgs as any[]) {
        const hid = n(r.hospital_id);
        if (!r.image_url) continue;
        if (r.image_type === 'logo') { if (!logo.has(hid)) logo.set(hid, s(r.image_url)); } else if (!main.has(hid)) main.set(hid, s(r.image_url));
      }
    }
    return {
      items: items.map((i) => {
        const hid = n(i.target_id);
        const available = !!n(i.hospital_available);
        const addr = s(i.target_address).trim();
        return {
          id: n(i.id), hospitalId: hid, name: i.target_name ? s(i.target_name) : '(삭제됨)', available,
          imageUrl: main.get(hid) || s(i.target_image) || logo.get(hid) || null,
          district: available && addr ? wishlistDistrict(addr) : '', address: available ? addressShort(addr) : '',
        };
      }),
    };
  }

  /** my_consultations.php (latest 100). */
  async consultations(m: MemberCtx) {
    const rows = await this.safe('consultations', async () => {
      const [r] = (await this.db.query(
        `SELECT c.id, c.hospital_id, c.hospital_name, c.consultation_type, c.status, c.created_at, h.phone AS h_phone
         FROM gangnamunni_consultations c LEFT JOIN gangnamunni_hospitals h ON h.id = c.hospital_id
         WHERE c.mb_id = ? ORDER BY c.created_at DESC, c.id DESC LIMIT 100`, [m.id])) as any;
      return r as any[];
    }, [] as any[]);
    const items = rows.map((r) => {
      const st = s(r.status) || 'pending';
      const v = consultStatus(st);
      const created = s(r.created_at);
      const mmdd = /(\d{2})-(\d{2})/.exec(created.slice(5, 10));
      return {
        id: n(r.id), hospitalId: n(r.hospital_id), hospitalName: s(r.hospital_name),
        type: s(r.consultation_type), typeLabel: consultTypeLabel(r.consultation_type), status: st,
        statusLabel: v.label, statusTone: v.tone as StatusTone, dateLabel: mmdd ? `${mmdd[1]}.${mmdd[2]}` : '',
        hospitalPhone: s(r.h_phone).trim() ? telDigits(r.h_phone) : null,
      };
    });
    const anyPhone = rows.map((r) => s(r.h_phone).trim()).find((p) => p !== '') ?? '';
    return { items, anyPhone: anyPhone || null };
  }

  /** my_consultation_detail.php: one of the member's own consultations (anybody else's is a 404). */
  async consultation(m: MemberCtx, id: number) {
    // kakao_channel_url may not exist in every environment: try with it, then without (like the PHP page).
    let row: any;
    for (const col of [', h.kakao_channel_url AS h_kakao_channel_url', '']) {
      try {
        const [r] = (await this.db.query(
          `SELECT c.*, h.phone AS h_phone${col} FROM gangnamunni_consultations c LEFT JOIN gangnamunni_hospitals h ON h.id = c.hospital_id
           WHERE c.id = ? AND c.mb_id = ? LIMIT 1`, [id, m.id])) as any;
        row = (r as any[])[0];
        break;
      } catch (e) { this.log.warn(`consultation detail (${col ? 'kakao' : 'plain'}): ${(e as Error).message}`); }
    }
    if (!row) throw new NotFoundException('상담을 찾을 수 없습니다.');
    const events = await this.safe('consultation events', async () => {
      const [r] = (await this.db.query('SELECT kind, created_at FROM gangnamunni_consultation_events WHERE consultation_id = ? ORDER BY id ASC', [id])) as any;
      return r as any[];
    }, [] as any[]);
    const st = s(row.status) || 'pending';
    const v = consultStatus(st);
    const slot = s(row.callback_slot);
    const slotLabel = CONSULT_SLOTS[slot] ?? null;
    const sub = consultTypeLabel(row.consultation_type) + (slotLabel && slot !== 'any' ? ` · ${slotLabel}` : '') + ` · ${s(row.name).trim()} ${s(row.phone).trim()}`;
    const doneAt = [...events].reverse().find((e) => s(e.kind) === 'completed');
    const phone = s(row.h_phone).trim();
    const kakao = safeHttpUrl(row.h_kakao_channel_url);
    const msgAt = consultDt(row.hospital_message_at).replace(/^\d\d\.\d\d \(.\) /u, '');
    return {
      id: n(row.id), hospitalId: n(row.hospital_id), hospitalName: s(row.hospital_name), status: st, statusLabel: v.label, statusTone: v.tone as StatusTone,
      type: s(row.consultation_type), sub: sub.trim(), createdLabel: consultDt(row.created_at),
      events: events.filter((e) => s(e.kind) !== 'completed' && CONSULT_EVENT_LABELS[s(e.kind)]).map((e) => ({ label: CONSULT_EVENT_LABELS[s(e.kind)], at: consultDt(e.created_at) })),
      completed: st === 'completed', completedAt: st === 'completed' ? consultDt(doneAt?.created_at) : '',
      hospitalMessage: s(row.hospital_message).trim() ? { text: s(row.hospital_message), meta: `${s(row.hospital_name)} · ${msgAt}` } : null,
      slot: slotLabel ? slot : '', canRerequest: st === 'contact_failed', showSlots: st === 'contact_failed' || slot !== '',
      slots: Object.entries(CONSULT_SLOTS).map(([key, label]) => ({ key, label })),
      hospitalPhone: phone ? telDigits(phone) : null,
      kakaoUrl: st !== 'contact_failed' && st !== 'completed' && s(row.consultation_type) === 'kakao' && kakao ? kakao : null,
    };
  }

  /** my_consultation_detail.php POST: only from contact_failed, with a callback slot. The UPDATE re-checks the state. */
  async rerequest(m: MemberCtx, id: number, slotRaw: unknown) {
    const slot = typeof slotRaw === 'string' ? slotRaw : '';
    if (!Object.prototype.hasOwnProperty.call(CONSULT_SLOTS, slot)) throw new BadRequestException('다시 연락받을 시간을 선택해주세요.');
    const [own] = (await this.db.query('SELECT status FROM gangnamunni_consultations WHERE id = ? AND mb_id = ? LIMIT 1', [id, m.id])) as any;
    if (!(own as any[]).length) throw new NotFoundException('상담을 찾을 수 없습니다.');
    if (s((own as any[])[0].status) !== 'contact_failed') throw new BadRequestException('지금은 다시 요청할 수 없는 상태예요.');
    const [res] = (await this.db.query(
      "UPDATE gangnamunni_consultations SET status = 'pending', callback_slot = ? WHERE id = ? AND mb_id = ? AND status = 'contact_failed'", [slot, id, m.id])) as any;
    if (n((res as any)?.affectedRows) === 1) {
      await this.safe('rerequest event', () => this.db.query("INSERT INTO gangnamunni_consultation_events (consultation_id, kind) VALUES (?, 'rerequest')", [id]), null);
      return { success: true as const };
    }
    throw new BadRequestException('지금은 다시 요청할 수 없는 상태예요.');
  }

  /** my_reviews.php: reviews still writable (completed bookings without one) and the member's own reviews. */
  async reviews(m: MemberCtx) {
    const [done, writable] = await Promise.all([
      this.safe('my reviews', async () => {
        const [r] = (await this.db.query(
          `SELECT r.id, r.hospital_id, r.rating, r.treatments, r.doctor_name, r.review_status, r.is_active,
                  h.name AS hospital_name, h.main_image_url, h.address, b.booking_date, b.treatment AS booking_treatment
           FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id LEFT JOIN gangnamunni_bookings b ON r.booking_id = b.id
           WHERE r.user_id = ? AND (r.is_active = 1 OR r.review_status IN ('pending','rejected')) ORDER BY r.created_at DESC`, [m.id])) as any;
        return r as any[];
      }, [] as any[]),
      this.safe('writable reviews', async () => {
        const [r] = (await this.db.query(
          `SELECT b.id, b.hospital_id, b.hospital_name, b.treatment, b.booking_date, h.main_image_url, h.address,
                  TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS doctor_name
           FROM gangnamunni_bookings b
           LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
           LEFT JOIN appointments ap ON b.hms_appointment_id = ap.id
           LEFT JOIN doctors doc ON ap.doctor_id = doc.id
           LEFT JOIN users u ON doc.user_id = u.id
           WHERE b.mb_id = ? AND b.status = 'completed'
             AND NOT EXISTS (SELECT 1 FROM hospital_reviews r WHERE r.user_id COLLATE utf8mb4_unicode_ci = b.mb_id AND r.booking_id = b.id)
           ORDER BY b.booking_date DESC`, [m.id])) as any;
        return r as any[];
      }, [] as any[]),
    ]);
    return {
      writable: writable.map((w) => ({
        bookingId: n(w.id), hospitalId: n(w.hospital_id), hospitalName: s(w.hospital_name), treatment: s(w.treatment),
        imageUrl: w.main_image_url ? s(w.main_image_url) : null, district: reviewDistrict(w.address), address: s(w.address),
        doctorName: s(w.doctor_name).trim() || null, bookingDate: dotDate(w.booking_date),
      })),
      done: done.map((r) => ({
        id: n(r.id), hospitalId: n(r.hospital_id), hospitalName: s(r.hospital_name),
        imageUrl: r.main_image_url ? s(r.main_image_url) : null, rating: Math.max(0, Math.min(5, Math.round(n(r.rating)))), ratingValue: Number(n(r.rating).toFixed(1)),
        treatment: s(r.booking_treatment) || s(r.treatments) || '-', district: reviewDistrict(r.address), address: s(r.address),
        doctorName: s(r.doctor_name).trim() || null, bookingDate: dotDate(r.booking_date),
        pending: s(r.review_status) === 'pending' && !n(r.is_active),
      })),
    };
  }
}
