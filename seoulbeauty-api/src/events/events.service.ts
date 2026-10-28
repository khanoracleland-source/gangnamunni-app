import { BadRequestException, ConflictException, HttpException, Inject, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { safeLink } from '../home/home.service';
import { adminWants, categoryColor, dateOnly, normalizePhone, raffleClosedReason, raffleTypeInfo, todayKst, validEmail, type ClosedReason } from './events.logic';

export interface EventSummary {
  id: number; title: string; subtitle: string | null; category: string | null; imageUrl: string | null;
  hospitalId: number | null; hospitalName: string | null;
  originalPrice: number | null; discountPrice: number | null; discountRate: number | null;
  hasRaffle: boolean; endDate: string | null;
}
export interface PageAd { id: number; title: string; subtitle: string | null; imageUrl: string | null; linkUrl: string }
export interface EventList {
  items: EventSummary[]; categories: string[]; category: string; raffleOnly: boolean;
  /** includes/ad_banner.php ($ad_page_key = 'events') */
  ads: PageAd[]; adAutoScroll: boolean;
}

export interface RaffleInfo {
  type: string; typeLabel: string; typeIcon: string; typeDesc: string;
  winnerCount: number; entryCount: number; maxEntries: number; endDate: string | null; prize: string | null;
  closed: boolean; closedReason: ClosedReason;
  /** only meaningful for a logged-in member (the response is then private/no-store) */
  entered: boolean;
}
export interface EventDetail {
  id: number; title: string; subtitle: string | null; description: string | null;
  imageUrl: string | null; images: { imageUrl: string; caption: string | null }[];
  category: string | null; categoryColor: string; hospitalId: number | null; hospitalName: string | null;
  originalPrice: number; discountPrice: number; discountRate: number;
  startDate: string | null; endDate: string | null; ended: boolean;
  raffle: RaffleInfo | null;
}
export interface EntryInput { name: string; phone: string; email: string }
export interface Member { id: string; no: number }

const num = (v: unknown): number | null => (v == null || v === '' ? null : Number(v));

@Injectable()
export class EventsService {
  private readonly log = new Logger(EventsService.name);
  private memberIdColumn: boolean | null = null;
  /** One entry at a time per event inside this process (the DB user has no row-lock privilege). */
  private chains = new Map<number, Promise<unknown>>();
  private attempts = new Map<string, number[]>();

  constructor(@Inject(DB) private readonly db: Db) {}

  /** events.php: active events (optionally one category / raffle only), manual order, max 50. */
  async list(category: string, raffleOnly: boolean): Promise<EventList> {
    const where = ['e.is_active = 1'];
    const values: unknown[] = [];
    if (category) { where.push('e.category = ?'); values.push(category); }
    if (raffleOnly) where.push('e.has_raffle = 1');
    const [[rows], [cats], adBlock] = (await Promise.all([
      this.db.query(
        `SELECT e.id, e.title, e.subtitle, e.category, e.image_url, e.hospital_id, e.original_price, e.discount_price, e.discount_rate,
                e.has_raffle, e.end_date, h.name AS hospital_name
           FROM gangnamunni_events e LEFT JOIN gangnamunni_hospitals h ON e.hospital_id = h.id
          WHERE ${where.join(' AND ')}
          ORDER BY e.display_order ASC, e.created_at DESC LIMIT 50`, values),
      this.db.query('SELECT DISTINCT category FROM gangnamunni_events WHERE is_active = 1 ORDER BY category'),
      this.pageAds('events'),
    ])) as any;
    return {
      items: (rows as any[]).map((e) => ({
        id: Number(e.id), title: String(e.title ?? ''), subtitle: e.subtitle || null, category: e.category || null,
        imageUrl: e.image_url || null, hospitalId: num(e.hospital_id), hospitalName: e.hospital_name || null,
        originalPrice: num(e.original_price), discountPrice: num(e.discount_price), discountRate: num(e.discount_rate),
        hasRaffle: !!Number(e.has_raffle ?? 0), endDate: dateOnly(e.end_date),
      })),
      categories: (cats as any[]).map((c) => String(c.category ?? '')).filter(Boolean),
      category, raffleOnly, ads: adBlock.ads, adAutoScroll: adBlock.autoScroll,
    };
  }

  /**
   * includes/ad_banner.php: approved ads for this page (or 'all') whose advertiser has an active slot booking today;
   * switched off by setting ad_show_<page> = 0. Impressions/clicks are tracked by PHP (api_ad_tracking.php).
   */
  private async pageAds(pageKey: string): Promise<{ ads: PageAd[]; autoScroll: boolean }> {
    const [[settings], [ads]] = (await Promise.all([
      this.db.query('SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_key IN (?, ?)', [`ad_show_${pageKey}`, 'ad_auto_scroll']),
      this.db.query(
        `SELECT a.id, a.title, a.subtitle, a.image_url, a.link_url FROM gangnamunni_ads a
          WHERE a.status = 'approved' AND (a.target_page = 'all' OR a.target_page = ?)
            AND EXISTS (SELECT 1 FROM gangnamunni_ad_slot_bookings b
                         WHERE b.advertiser_id = a.advertiser_id AND b.status = 'active' AND b.start_date <= CURDATE()
                           AND (b.end_date IS NULL OR b.end_date >= CURDATE()))
          ORDER BY a.display_order ASC LIMIT 10`, [pageKey]),
    ])) as any;
    const s = new Map<string, string>((settings as any[]).map((r) => [r.setting_key, String(r.setting_value)]));
    if ((s.get(`ad_show_${pageKey}`) ?? '1') !== '1') return { ads: [], autoScroll: false };
    return {
      ads: (ads as any[]).map((a) => ({
        id: Number(a.id), title: String(a.title ?? ''), subtitle: a.subtitle || null,
        // ad_banner.php: 'sample' images are placeholders → text banner
        imageUrl: a.image_url && !String(a.image_url).includes('sample') ? String(a.image_url) : null,
        linkUrl: safeLink(a.link_url),
      })),
      autoScroll: (s.get('ad_auto_scroll') ?? '1') === '1',
    };
  }

  private async hasMemberIdColumn(): Promise<boolean> {
    if (this.memberIdColumn === null) {
      const [r] = (await this.db.query("SHOW COLUMNS FROM gangnamunni_event_entries LIKE 'member_id'")) as any;
      this.memberIdColumn = r.length > 0;
    }
    return this.memberIdColumn;
  }

  private async countEntries(eventId: number): Promise<number> {
    const [r] = (await this.db.query('SELECT COUNT(*) AS cnt FROM gangnamunni_event_entries WHERE event_id = ?', [eventId])) as any;
    return Number(r[0]?.cnt ?? 0);
  }

  private async hasEntered(eventId: number, member: Member | null): Promise<boolean> {
    if (!member?.id) return false; // guests: PHP keeps this in its own session; Next cannot see it (see docs)
    if (await this.hasMemberIdColumn()) {
      const [r] = (await this.db.query('SELECT id FROM gangnamunni_event_entries WHERE event_id = ? AND member_id = ? LIMIT 1', [eventId, member.id])) as any;
      return r.length > 0;
    }
    return false; // legacy schema (no member_id) matched by e-mail, which the session does not carry
  }

  /** event_detail.php: one active event with gallery + raffle state. */
  async detail(id: number, member: Member | null): Promise<EventDetail> {
    const [[rows], [imgs]] = (await Promise.all([
      this.db.query('SELECT * FROM gangnamunni_events WHERE id = ? AND is_active = 1 LIMIT 1', [id]),
      this.db.query('SELECT image_url, caption FROM gangnamunni_event_images WHERE event_id = ? ORDER BY id ASC', [id]).catch(() => [[]]),
    ])) as any;
    const e = (rows as any[])[0];
    if (!e) throw new NotFoundException('event not found');

    const today = todayKst();
    const endDate = dateOnly(e.end_date);
    let raffle: RaffleInfo | null = null;
    if (Number(e.has_raffle ?? 0)) {
      const entryCount = await this.countEntries(id);
      const maxEntries = Number(e.max_entries ?? 0);
      const raffleEnd = dateOnly(e.raffle_end_date);
      const reason = raffleClosedReason(raffleEnd, maxEntries, entryCount, today);
      const t = raffleTypeInfo(e.raffle_type);
      raffle = {
        type: e.raffle_type || 'random', typeLabel: t.label, typeIcon: t.icon, typeDesc: t.desc,
        winnerCount: Number(e.raffle_winner_count ?? 0), entryCount, maxEntries, endDate: raffleEnd,
        prize: e.raffle_prize || null, closed: reason !== null, closedReason: reason,
        entered: await this.hasEntered(id, member),
      };
    }
    return {
      id: Number(e.id), title: String(e.title ?? ''), subtitle: e.subtitle || null, description: e.description || null,
      imageUrl: e.image_url || null,
      images: (imgs as any[]).filter((i) => i.image_url).map((i) => ({ imageUrl: String(i.image_url), caption: i.caption || null })),
      category: e.category || null, categoryColor: categoryColor(e.category),
      hospitalId: num(e.hospital_id) || null, hospitalName: e.hospital_name || null,
      originalPrice: Number(e.original_price ?? 0), discountPrice: Number(e.discount_price ?? 0), discountRate: Number(e.discount_rate ?? 0),
      startDate: dateOnly(e.start_date), endDate, ended: !!endDate && endDate < today,
      raffle,
    };
  }

  private rateLimit(key: string) {
    const now = Date.now();
    const list = (this.attempts.get(key) ?? []).filter((t) => t > now - 600_000);
    if (list.length >= 10) throw new HttpException({ success: false, message: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' }, 429);
    list.push(now);
    this.attempts.set(key, list);
    if (this.attempts.size > 10_000) this.attempts.clear();
  }

  /** event_api.php?action=enter. Member only; rules and messages are the PHP ones. */
  async enter(eventId: number, member: Member, input: Partial<EntryInput>): Promise<{ success: true; entryId: number; message: string }> {
    const name = String(input.name ?? '').trim();
    const email = String(input.email ?? '').trim();
    const phone = normalizePhone(input.phone);
    if (!name || !String(input.phone ?? '').trim()) throw new BadRequestException('이름과 전화번호를 입력해주세요.');
    if (name.length > 50 || !phone) throw new BadRequestException('입력한 내용을 확인해주세요.');
    if (!validEmail(email)) throw new BadRequestException('올바른 이메일 형식을 입력해주세요.');
    this.rateLimit(member.id);

    const previous = this.chains.get(eventId) ?? Promise.resolve();
    const run = previous.catch(() => undefined).then(() => this.enterLocked(eventId, member, name, phone, email));
    this.chains.set(eventId, run);
    try {
      return await run;
    } finally {
      if (this.chains.get(eventId) === run) this.chains.delete(eventId);
    }
  }

  private async enterLocked(eventId: number, member: Member, name: string, phone: string, email: string) {
    const [rows] = (await this.db.query(
      'SELECT id, title, has_raffle, max_entries, raffle_end_date, is_active FROM gangnamunni_events WHERE id = ? LIMIT 1', [eventId])) as any;
    const e = rows[0];
    if (!e) throw new NotFoundException('존재하지 않는 이벤트입니다.');
    if (Number(e.is_active) !== 1) throw new BadRequestException('현재 진행 중이지 않은 이벤트입니다.');
    if (Number(e.has_raffle) !== 1) throw new BadRequestException('이 이벤트는 응모 기능이 없습니다.');
    const raffleEnd = dateOnly(e.raffle_end_date);
    if (raffleEnd && raffleEnd < todayKst()) throw new BadRequestException('응모 기간이 종료되었습니다.');
    const max = Number(e.max_entries ?? 0);
    const hasCol = await this.hasMemberIdColumn();

    // One statement checks "not entered yet" and "not full" and inserts, so a second process cannot slip past.
    const dupSql = hasCol
      ? 'EXISTS (SELECT 1 FROM gangnamunni_event_entries WHERE event_id = ? AND member_id = ?)'
      : 'EXISTS (SELECT 1 FROM gangnamunni_event_entries WHERE event_id = ? AND user_email = ?)';
    const dupVals = hasCol ? [eventId, member.id] : [eventId, email];
    const [ins] = (await this.db.query(
      hasCol
        ? `INSERT INTO gangnamunni_event_entries (event_id, member_id, user_name, user_phone, user_email, status, created_at)
           SELECT ?, ?, ?, ?, ?, 'entered', NOW() FROM DUAL
            WHERE NOT ${dupSql} AND (? = 0 OR (SELECT COUNT(*) FROM gangnamunni_event_entries WHERE event_id = ?) < ?)`
        : `INSERT INTO gangnamunni_event_entries (event_id, user_name, user_phone, user_email, created_at)
           SELECT ?, ?, ?, ?, NOW() FROM DUAL
            WHERE NOT ${dupSql} AND (? = 0 OR (SELECT COUNT(*) FROM gangnamunni_event_entries WHERE event_id = ?) < ?)`,
      [...(hasCol ? [eventId, member.id, name, phone, email] : [eventId, name, phone, email]), ...dupVals, max, eventId, max],
    )) as any;

    if (!ins?.affectedRows) {
      // Nothing inserted: tell the member why (same order as PHP: full, then duplicate).
      if (max > 0 && (await this.countEntries(eventId)) >= max) throw new BadRequestException('응모 정원이 마감되었습니다.');
      throw new ConflictException('이미 이 이벤트에 응모하셨습니다.');
    }
    const entryId = Number(ins.insertId ?? 0);
    await this.notify(eventId, String(e.title ?? '이벤트'), member, name);
    return { success: true as const, entryId, message: '응모가 완료되었습니다!' };
  }

  /**
   * Best effort, like the PHP helpers: in-app rows for the member and for admins that subscribe to
   * 'consultation_new'. FCM push to admins is NOT ported (see docs); a failure here never fails the entry.
   */
  private async notify(eventId: number, title: string, member: Member, userName: string) {
    try {
      await this.db.query(
        'INSERT INTO gangnamunni_user_notifications (member_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)',
        [member.id, 'raffle', `[${title}] 응모가 완료되었습니다`, '이벤트 응모가 정상적으로 접수되었습니다. 추첨 결과를 기다려주세요!', `event_detail.php?id=${eventId}`, 'bi-gift'],
      );
    } catch (err) { this.log.warn(`user notification failed: ${(err as Error).message}`); }
    try {
      const [admins] = (await this.db.query('SELECT id, permissions FROM gangnamunni_admins WHERE is_active = 1')) as any;
      for (const a of admins as any[]) {
        if (!adminWants(a.permissions, 'consultation_new')) continue;
        await this.db.query(
          'INSERT INTO gangnamunni_notifications (admin_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)',
          [a.id, 'consultation_new', `새 응모: ${title}`, `${userName}님이 [${title}] 이벤트에 응모했습니다.`, `admin_events.php?id=${eventId}#entries`, 'bi-chat-dots'],
        );
      }
    } catch (err) { this.log.warn(`admin notification failed: ${(err as Error).message}`); }
  }
}
