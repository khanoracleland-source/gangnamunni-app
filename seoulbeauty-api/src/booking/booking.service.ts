import { HttpException, Inject, Injectable, Logger, NotFoundException, UnauthorizedException } from '@nestjs/common';
import { AdminNotifyService } from '../admin-notify/admin-notify.service';
import { DB, Db } from '../db/db.module';
import { kstNow } from '../bookings/bookings.logic';
import {
  BOOKING_WORD, ActiveRow, NoshowRow, RevisitRule, findActiveSame, memberAge, noshowStatus, parseHours, revisitStatus, scheduleError,
} from './booking.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;
const NAME_MAX = 100, PHONE_MAX = 30, MEMO_MAX = 2000;

/** 12.5 -> "12.5", 3.00 -> "3" (PHP rtrim(rtrim(number_format(v, 2, '.', ''), '0'), '.')). */
export const fmtDose = (v: unknown) => Number(v ?? 0).toFixed(2).replace(/0+$/, '').replace(/\.$/, '');

/** booking.php answers a refused booking with 422 {success:false, code, message, ...meta}. */
export class BookingRefused extends HttpException {
  constructor(message: string, code = '', meta: Record<string, unknown> = {}) {
    super({ success: false, code, message, ...meta }, 422);
  }
}

export interface Actor { loggedIn: boolean; id: string }
export interface BookingInput {
  hospital_id?: unknown; name?: unknown; phone?: unknown; date?: unknown; time?: unknown; memo?: unknown;
  selection_type?: unknown; package_id?: unknown; treatments?: unknown; pre_answers?: unknown;
  confirm_duplicate?: unknown; confirm_revisit?: unknown;
}

@Injectable()
export class BookingService {
  private readonly log = new Logger(BookingService.name);
  /** per member (or IP) attempts, so a script cannot flood the clinics with requests. */
  static attempts = new Map<string, number[]>();
  private cols = new Map<string, boolean>();
  constructor(@Inject(DB) private readonly db: Db, private readonly notify: AdminNotifyService) {}

  private async setting(key: string, def: string): Promise<string> {
    try {
      const [rows] = (await this.db.query('SELECT setting_value FROM gangnamunni_settings WHERE setting_key = ? LIMIT 1', [key])) as any;
      return (rows as any[])[0] ? s((rows as any[])[0].setting_value) : def;
    } catch (e) { this.log.error(`setting ${key}: ${(e as Error).message}`); return def; }
  }

  /** SHOW COLUMNS check, cached: the optional columns of booking.php (min_age, revisit_days, option_name, body_part). */
  private async hasCol(table: string, col: string): Promise<boolean> {
    const k = `${table}.${col}`;
    if (this.cols.has(k)) return this.cols.get(k)!;
    let ok = false;
    try {
      const [rows] = (await this.db.query(`SHOW COLUMNS FROM ${table} LIKE ?`, [col])) as any;
      ok = (rows as any[]).length > 0;
    } catch { ok = false; }
    this.cols.set(k, ok);
    return ok;
  }

  private async loginRequired(actor: Actor): Promise<boolean> {
    if (actor.loggedIn) return false;
    const [req, phone] = await Promise.all([this.setting('booking_require_login', '1'), this.setting('booking_phone_auth', '0')]);
    return req === '1' && phone !== '1';
  }

  private async member(id: string) {
    if (!id) return null;
    const [rows] = (await this.db.query('SELECT mb_id, mb_name, mb_hp, mb_tel, mb_birth FROM g5_member WHERE mb_id = ? LIMIT 1', [id])) as any;
    return (rows as any[])[0] ?? null;
  }

  private async hospital(id: number) {
    const [rows] = (await this.db.query('SELECT id, name, profile_image_url, business_hours, hms_hospital_id FROM gangnamunni_hospitals WHERE id = ? LIMIT 1', [id])) as any;
    return (rows as any[])[0] ?? null;
  }

  /** Up to ten photos for the top slider; the hospital's main image when it has no gallery. */
  private async images(hospitalId: number, fallback: string): Promise<string[]> {
    try {
      const [rows] = (await this.db.query('SELECT image_url FROM gangnamunni_hospital_images WHERE hospital_id = ? ORDER BY display_order ASC LIMIT 10', [hospitalId])) as any;
      const list = (rows as any[]).map((r) => s(r.image_url)).filter(Boolean);
      return list.length ? list : fallback ? [fallback] : [];
    } catch (e) { this.log.error(`images: ${(e as Error).message}`); return fallback ? [fallback] : []; }
  }

  private async treatments(hospitalId: number) {
    const minAge = await this.hasCol('gangnamunni_hospital_treatments', 'min_age');
    const [rows] = (await this.db.query(
      `SELECT ht.id AS hospital_treatment_id, tm.id AS treatment_id, tm.name AS name, ht.price_min, ht.price_max, ${minAge ? 'ht.min_age' : 'NULL AS min_age'},
         (SELECT MIN(p.price) FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0) AS opt_min,
         (SELECT MAX(p.price) FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0) AS opt_max
       FROM gangnamunni_hospital_treatments ht JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
       WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 ORDER BY ht.is_promoted DESC, tm.name ASC`, [hospitalId])) as any;
    const list = (rows as any[]).map((r) => ({
      hospital_treatment_id: n(r.hospital_treatment_id), treatment_id: n(r.treatment_id), name: s(r.name),
      price_min: n(r.opt_min) || n(r.price_min), price_max: n(r.opt_max) || n(r.price_max), min_age: n(r.min_age),
      options: [] as any[],
    }));
    if (!list.length) return list;
    const [hasName, hasPart] = await Promise.all([this.hasCol('gangnamunni_hospital_treatment_prices', 'option_name'), this.hasCol('gangnamunni_hospital_treatment_prices', 'body_part')]);
    const ids = list.map((t) => t.hospital_treatment_id);
    const [opts] = (await this.db.query(
      `SELECT id, hospital_treatment_id, ${hasName ? 'option_name' : "'' AS option_name"}, ${hasPart ? 'body_part' : "'' AS body_part"}, dosage_value, dosage_unit, price
       FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id IN (${ids.map(() => '?').join(',')}) AND price > 0 ORDER BY sort_order ASC, id ASC`, ids)) as any;
    for (const o of opts as any[]) {
      const optionName = s(o.option_name).trim();
      const part = s(o.body_part).replace(/\|\|/g, ', ').trim();
      const partDisplay = part ? `(${part})` : '';
      const dosage = fmtDose(o.dosage_value) + s(o.dosage_unit);
      const label = [optionName, partDisplay].filter(Boolean).join(' · ') || dosage;
      list.find((t) => t.hospital_treatment_id === n(o.hospital_treatment_id))?.options.push({ id: n(o.id), name: label, option_name: optionName, body_part: partDisplay, dosage, price: n(o.price) });
    }
    return list;
  }

  private async packages(hospitalId: number) {
    try {
      const [rows] = (await this.db.query(
        `SELECT p.id, p.name, p.description, p.package_price, p.original_price, GROUP_CONCAT(DISTINCT tm.name ORDER BY pi.sort_order SEPARATOR ', ') AS item_names
         FROM gangnamunni_treatment_packages p
         JOIN gangnamunni_treatment_package_items pi ON pi.package_id = p.id
         JOIN gangnamunni_treatment_master tm ON tm.id = pi.treatment_id
         JOIN gangnamunni_hospital_treatments ht ON ht.hospital_id = p.hospital_id AND ht.treatment_id = pi.treatment_id AND ht.status = 'active' AND ht.is_active = 1
         WHERE p.hospital_id = ? AND p.is_active = 1 AND p.package_price > 0 AND p.package_price <= p.original_price
           AND NOT EXISTS (SELECT 1 FROM gangnamunni_treatment_package_items pi_bad
             LEFT JOIN gangnamunni_hospital_treatments ht_bad ON ht_bad.hospital_id = p.hospital_id AND ht_bad.treatment_id = pi_bad.treatment_id AND ht_bad.status = 'active' AND ht_bad.is_active = 1
             WHERE pi_bad.package_id = p.id AND ht_bad.id IS NULL)
         GROUP BY p.id, p.name, p.description, p.package_price, p.original_price ORDER BY p.created_at DESC`, [hospitalId])) as any;
      return (rows as any[]).map((r) => ({ id: n(r.id), name: s(r.name), description: s(r.description), package_price: n(r.package_price), original_price: n(r.original_price), item_names: s(r.item_names) }));
    } catch (e) { this.log.error(`packages: ${(e as Error).message}`); return []; }
  }

  private async preQuestions(hospitalId: number): Promise<Record<string, string[]>> {
    const out: Record<string, string[]> = {};
    try {
      const [rows] = (await this.db.query(
        `SELECT tm.name, q.question FROM gangnamunni_treatment_questions q JOIN gangnamunni_hospital_treatments ht ON ht.id = q.hospital_treatment_id
         JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 ORDER BY q.sort_order, q.id`, [hospitalId])) as any;
      for (const r of rows as any[]) (out[s(r.name)] ||= []).push(s(r.question));
    } catch { /* the table is optional */ }
    return out;
  }

  /** booking_noshow_collect(): the policy numbers and the member's recent no-show / own-cancel rows (newest first). null = policy off. */
  async noshowCollect(mbId: string): Promise<{ limit: number; months: number; withCancel: boolean; rows: NoshowRow[] } | null> {
    if (!mbId) return null;
    const limit = Math.min(n(await this.setting('noshow_block_count', '3')), 50);
    const months = Math.min(n(await this.setting('noshow_block_months', '3')), 36);
    if (limit <= 0 || months <= 0) return null;
    let withCancel = (await this.setting('noshow_count_cancel', '1')) === '1';
    if (withCancel && !(await this.hasCol('gangnamunni_bookings', 'cancelled_by'))) withCancel = false;
    const cond = "status = 'no_show'" + (withCancel ? " OR (status IN ('cancelled','refunded') AND cancelled_by = 'member' AND cancelled_at IS NOT NULL)" : '');
    try {
      const [rows] = (await this.db.query(
        `SELECT booking_date, booking_time, hospital_name, status, IF(status = 'no_show', booking_date, DATE(cancelled_at)) AS ev
         FROM gangnamunni_bookings WHERE mb_id = ? AND (${cond})
         HAVING ev <= CURDATE() AND ev > DATE_SUB(CURDATE(), INTERVAL ? MONTH) ORDER BY ev DESC, booking_time DESC LIMIT 50`, [mbId, months])) as any;
      const list: NoshowRow[] = (rows as any[]).map((r) => ({
        booking_date: dateStr(r.booking_date), booking_time: s(r.booking_time), hospital_name: s(r.hospital_name), status: s(r.status), ev: dateStr(r.ev),
      }));
      return { limit, months, withCancel, rows: list };
    } catch (e) { this.log.error(`noshow: ${(e as Error).message}`); return null; }
  }

  private async noshow(mbId: string) {
    const c = await this.noshowCollect(mbId);
    return c ? noshowStatus(c.limit, c.months, c.withCancel, c.rows) : null;
  }

  /** GET: everything the booking screen needs. 401 when the site requires a login and there is none. */
  async context(actor: Actor, hospitalId: number) {
    if (await this.loginRequired(actor)) throw new UnauthorizedException('login required');
    const h = await this.hospital(hospitalId);
    if (!h) throw new NotFoundException('hospital not found');
    const hours = parseHours(h.business_hours);
    const m = actor.loggedIn ? await this.member(actor.id) : null;
    const phone = s(m?.mb_hp).trim() || s(m?.mb_tel).trim();
    const [treatments, packages, pre, noshow, images] = await Promise.all([this.treatments(hospitalId), this.packages(hospitalId), this.preQuestions(hospitalId), this.noshow(actor.loggedIn ? actor.id : ''), this.images(hospitalId, s(h.profile_image_url))]);
    return {
      hospital: { id: n(h.id), name: s(h.name), image: s(h.profile_image_url), images },
      /** KST wall clock: the screen never trusts the phone's clock for "today" and past slots. */
      now: kstNow(),
      hours,
      treatments, packages, preQuestions: pre,
      member: { loggedIn: actor.loggedIn, name: s(m?.mb_name).trim(), phone, hasSavedPhone: phone !== '', age: m ? memberAge(m.mb_birth) : null },
      noshow,
    };
  }

  private throttle(key: string) {
    const now = Date.now();
    const list = (BookingService.attempts.get(key) ?? []).filter((t) => now - t < 600_000);
    if (list.length >= 30) throw new HttpException({ success: false, code: 'rate_limited', message: '요청이 너무 많아요. 잠시 후 다시 시도해주세요.' }, 429);
    list.push(now);
    BookingService.attempts.set(key, list);
  }

  /** The POST handler of booking.php. `precheck` stops after every refusal check and saves nothing. */
  async create(actor: Actor, ip: string, input: BookingInput, precheck: boolean) {
    this.throttle(actor.loggedIn ? `m:${actor.id}` : `ip:${ip}`);
    if (await this.loginRequired(actor)) throw new UnauthorizedException('login required');
    const hospitalId = Math.max(0, Math.floor(n(input.hospital_id)));
    const h = hospitalId ? await this.hospital(hospitalId) : null;
    if (!h) throw new NotFoundException('hospital not found');
    const hours = parseHours(h.business_hours);

    const m = actor.loggedIn ? await this.member(actor.id) : null;
    const text = (v: unknown) => (typeof v === 'string' || typeof v === 'number' ? String(v).trim() : '');
    const name = m ? s(m.mb_name).trim() : text(input.name);
    let phone = text(input.phone);
    if (m && input.phone === undefined) phone = s(m.mb_hp).trim() || s(m.mb_tel).trim();
    const date = text(input.date), time = text(input.time), memo = text(input.memo);

    if ([...name].length > NAME_MAX || [...phone].length > PHONE_MAX || [...memo].length > MEMO_MAX) throw new BookingRefused('입력한 내용이 허용 길이를 초과했습니다.');
    if (phone === '') throw new BookingRefused('연락처를 입력해주세요.');
    if (!/^[0-9+()\-\s]{7,30}$/.test(phone)) throw new BookingRefused('연락처 형식을 확인해주세요.');
    if (!name || !date || !time) throw new BookingRefused('필수 항목을 모두 입력해주세요.');
    const sched = scheduleError(hours, date, time);
    if (sched) throw new BookingRefused(sched);

    // --- what was chosen, priced again from the DB (the client's prices are never trusted)
    const selection = text(input.selection_type) === 'package' ? 'package' : 'treatments';
    const packageId = Math.max(0, Math.floor(n(input.package_id)));
    let requested: any[] = Array.isArray(input.treatments) ? input.treatments : [];
    if (requested.length > 20) throw new BookingRefused('시술은 한 번에 최대 20개까지 선택할 수 있습니다.');
    let treatment = '';
    let quantity = 1;
    let price: number | null = null;
    let packageTreatments: any[] = [];

    if (selection === 'package') {
      const [pr] = (await this.db.query(
        `SELECT p.id, p.name, p.package_price FROM gangnamunni_treatment_packages p
         WHERE p.id = ? AND p.hospital_id = ? AND p.is_active = 1 AND p.package_price > 0 AND p.package_price <= p.original_price
           AND NOT EXISTS (SELECT 1 FROM gangnamunni_treatment_package_items pi_bad
             LEFT JOIN gangnamunni_hospital_treatments ht_bad ON ht_bad.hospital_id = p.hospital_id AND ht_bad.treatment_id = pi_bad.treatment_id AND ht_bad.status = 'active' AND ht_bad.is_active = 1
             WHERE pi_bad.package_id = p.id AND ht_bad.id IS NULL) LIMIT 1`, [packageId, hospitalId])) as any;
      const pkg = packageId > 0 ? (pr as any[])[0] : null;
      if (packageId <= 0) throw new BookingRefused('유효한 패키지를 선택해주세요.');
      if (!pkg) throw new BookingRefused('선택한 패키지는 현재 예약할 수 없습니다.');
      const [items] = (await this.db.query(
        `SELECT DISTINCT tm.name, pi.sort_order FROM gangnamunni_treatment_package_items pi JOIN gangnamunni_treatment_master tm ON tm.id = pi.treatment_id
         JOIN gangnamunni_hospital_treatments ht ON ht.hospital_id = ? AND ht.treatment_id = pi.treatment_id AND ht.status = 'active' AND ht.is_active = 1
         WHERE pi.package_id = ? ORDER BY pi.sort_order ASC`, [hospitalId, packageId])) as any;
      packageTreatments = (items as any[]).map((i) => ({ name: s(i.name), qty: 1, package_id: packageId, package_name: s(pkg.name) }));
      if (!packageTreatments.length) throw new BookingRefused('패키지에 예약 가능한 시술이 없습니다.');
      treatment = `[패키지] ${s(pkg.name)}`;
      quantity = 1;
      price = n(pkg.package_price) * 10000;
    }

    let treatmentsArr: any[];
    if (requested.length) {
      const hasName = await this.hasCol('gangnamunni_hospital_treatment_prices', 'option_name');
      const seen = new Set<string>();
      const verified: any[] = [];
      let sum = 0, found = false;
      for (const it of requested) {
        const tn = s(it && typeof it === 'object' ? (it as any).name : '').trim();
        const optionId = Math.max(0, Math.floor(n(it && typeof it === 'object' ? (it as any).option_id : 0)));
        if (tn === '' || seen.has(tn)) continue;
        seen.add(tn);
        const [ur] = (await this.db.query(
          `SELECT ht.price_min AS ht_min, p.id AS option_id, p.price AS option_price, ${hasName ? 'p.option_name' : "'' AS option_name"}, p.dosage_value, p.dosage_unit,
             (SELECT COUNT(*) FROM gangnamunni_hospital_treatment_prices pc WHERE pc.hospital_treatment_id = ht.id AND pc.price > 0) AS option_count
           FROM gangnamunni_hospital_treatments ht JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
           LEFT JOIN gangnamunni_hospital_treatment_prices p ON p.hospital_treatment_id = ht.id AND p.price > 0 AND (p.id = ? OR ? = 0)
           WHERE ht.hospital_id = ? AND tm.name = ? AND ht.status = 'active' AND ht.is_active = 1 ORDER BY p.sort_order ASC, p.id ASC LIMIT 1`,
          [optionId, optionId, hospitalId, tn])) as any;
        const r = (ur as any[])[0];
        if (!r) continue;
        if (n(r.option_count) > 1 && optionId <= 0) continue;
        if (optionId > 0 && n(r.option_id) !== optionId) continue;
        const unit = Math.trunc(n(r.option_price) || n(r.ht_min));
        if (unit > 0) {
          sum += unit; found = true;
          const dosage = fmtDose(r.dosage_value) + s(r.dosage_unit);
          const label = s(r.option_name).trim() || dosage;
          verified.push({ name: tn, qty: 1, option_id: n(r.option_id), option_name: [...label].slice(0, 100).join(''), dosage });
        }
      }
      if (found && verified.length === requested.length) {
        const summary = verified.map((v) => v.name + (v.option_name ? ` · ${v.option_name}` : '')).join(', ');
        treatmentsArr = [...packageTreatments, ...verified];
        quantity = (selection === 'package' ? 1 : 0) + verified.length;
        treatment = (selection === 'package' ? `${treatment}, ` : '') + summary;
        price = (price ?? 0) + sum;
      } else throw new BookingRefused('선택한 시술 중 현재 예약할 수 없는 항목이 있습니다.');
    } else if (selection === 'package') treatmentsArr = packageTreatments;
    else throw new BookingRefused('최소 1개 이상의 시술 또는 패키지를 선택해주세요.');
    treatment = [...treatment].slice(0, 100).join('');

    const names = [...new Set(treatmentsArr.map((t) => s(t.name).trim()).filter(Boolean))];
    await this.ageCheck(hospitalId, names, m);
    if (actor.loggedIn) await this.historyChecks(actor.id, hospitalId, names, input);
    await this.conflictCheck(names);

    if (precheck) return { success: true, precheck: true };

    const [ins] = (await this.db.query(
      `INSERT INTO gangnamunni_bookings (hospital_id, hospital_name, name, phone, mb_id, treatment, treatments_json, quantity, booking_date, booking_time, memo, price, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())`,
      [hospitalId, s(h.name), name, phone, actor.loggedIn ? actor.id : '', treatment, JSON.stringify(treatmentsArr), quantity, date, time, memo, price])) as any;
    const bookingId = n(ins.insertId);
    if (!bookingId) throw new BookingRefused(`${BOOKING_WORD} 중 오류가 발생했습니다. 다시 시도해주세요.`);

    await this.saveAnswers(bookingId, hospitalId, input.pre_answers);
    await this.notify.bookingCreated(bookingId, s(h.name), name, date, time);
    const hmsId = await this.syncHms(bookingId, h, name, phone, date, time, treatment, memo);
    return { success: true, booking_id: bookingId, hms_appointment_id: hmsId, detail_url: `/mypage/bookings/${bookingId}?new=1` };
  }

  private async ageCheck(hospitalId: number, names: string[], m: any) {
    if (!names.length || !(await this.hasCol('gangnamunni_hospital_treatments', 'min_age'))) return;
    const [rows] = (await this.db.query(
      `SELECT MAX(ht.min_age) AS max_min_age FROM gangnamunni_hospital_treatments ht JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
       WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 AND ht.min_age IS NOT NULL AND ht.min_age > 0 AND tm.name IN (${names.map(() => '?').join(',')})`,
      [hospitalId, ...names])) as any;
    const required = n((rows as any[])[0]?.max_min_age);
    if (required <= 0) return;
    const age = m ? memberAge(m.mb_birth) : null;
    if (age === null) throw new BookingRefused('나이 제한이 있는 시술이라 생년월일 등록이 필요해요.', 'age_unknown', { min_age: required });
    if (age < required) throw new BookingRefused(`만 ${required}세 미만은 이 시술을 앱에서 예약할 수 없어요.`, 'age_restricted', { min_age: required });
  }

  private async activeRows(mbId: string, hospitalId: number, same: boolean): Promise<ActiveRow[]> {
    const [rows] = (await this.db.query(
      `SELECT id, hospital_name, treatment, treatments_json, booking_date, booking_time, status FROM gangnamunni_bookings
       WHERE mb_id = ? AND hospital_id ${same ? '=' : '<>'} ? AND status IN ('pending','hospital_checking','confirmed') AND booking_date >= CURDATE()
       ORDER BY booking_date ASC, booking_time ASC LIMIT 50`, [mbId, hospitalId])) as any;
    return (rows as any[]).map((r) => ({ ...r, booking_date: dateStr(r.booking_date), booking_time: s(r.booking_time) }));
  }

  /** no-show, other-hospital duplicate, strict revisit, same-hospital duplicate, recommended revisit — in that order. */
  private async historyChecks(mbId: string, hospitalId: number, names: string[], input: BookingInput) {
    let rules: RevisitRule[] = [];
    let done: any[] = [];
    if (names.length && (await this.hasCol('gangnamunni_treatment_master', 'revisit_days'))) {
      const [rr] = (await this.db.query(
        `SELECT tm.name AS name, tm.revisit_days, tm.revisit_strict FROM gangnamunni_treatment_master tm
         WHERE tm.revisit_days IS NOT NULL AND tm.revisit_days > 0 AND tm.name IN (${names.map(() => '?').join(',')})`, names)) as any;
      rules = (rr as any[]).map((r) => ({ name: s(r.name), days: n(r.revisit_days), strict: n(r.revisit_strict) === 1 }));
      if (rules.length) {
        const [dr] = (await this.db.query(
          "SELECT booking_date, treatment, treatments_json FROM gangnamunni_bookings WHERE mb_id = ? AND status = 'completed' AND booking_date <= CURDATE() ORDER BY booking_date DESC LIMIT 300", [mbId])) as any;
        done = (dr as any[]).map((r) => ({ ...r, booking_date: dateStr(r.booking_date) }));
      }
    }
    const revisit = revisitStatus(rules, done);
    const noshow = await this.noshow(mbId);
    if (noshow) throw new BookingRefused('지금은 예약할 수 없어요.', 'noshow_blocked', { noshow });
    const other = findActiveSame(await this.activeRows(mbId, hospitalId, false), names);
    if (other) throw new BookingRefused('다른 병원에 같은 시술 예약이 있어요.', 'other_hospital_duplicate', { existing: other });
    if (revisit.strict) throw new BookingRefused('아직 예약할 수 없어요.', 'revisit_blocked', { revisit: revisit.strict });
    if (!isOne(input.confirm_duplicate)) {
      const same = findActiveSame(await this.activeRows(mbId, hospitalId, true), names);
      if (same) throw new BookingRefused('이미 예약된 시술이 있어요.', 'duplicate_booking', { existing: same });
    }
    if (revisit.warn && !isOne(input.confirm_revisit)) throw new BookingRefused('아직 재시술 권장 시기가 아니에요.', 'revisit_warning', { revisit: revisit.warn });
  }

  /** Treatments an admin marked "hard to book on the same day". The table is optional. */
  private async conflictCheck(names: string[]) {
    if (names.length < 2) return;
    try {
      const [ids] = (await this.db.query(`SELECT id, name FROM gangnamunni_treatment_master WHERE name IN (${names.map(() => '?').join(',')})`, names)) as any;
      const byName = new Map<string, number>((ids as any[]).map((r) => [s(r.name), n(r.id)]));
      if (byName.size < 2) return;
      const idList = [...byName.values()];
      const [pairsRows] = (await this.db.query(
        `SELECT tm_a_id, tm_b_id FROM gangnamunni_treatment_conflicts WHERE tm_a_id IN (${idList.map(() => '?').join(',')}) AND tm_b_id IN (${idList.map(() => '?').join(',')})`, [...idList, ...idList])) as any;
      const nameById = new Map<number, string>([...byName.entries()].map(([k, v]) => [v, k]));
      const hit = new Set<number>();
      const pairs: string[][] = [];
      for (const r of pairsRows as any[]) { hit.add(n(r.tm_a_id)); hit.add(n(r.tm_b_id)); pairs.push([nameById.get(n(r.tm_a_id)) ?? '', nameById.get(n(r.tm_b_id)) ?? '']); }
      const conflictNames = [...byName.entries()].filter(([, id]) => hit.has(id)).map(([k]) => k);
      if (conflictNames.length > 1) throw new BookingRefused('같은 날 함께 예약하기 어려운 시술이 있어요.', 'treatment_conflict', { conflict: { names: conflictNames, pairs } });
    } catch (e) {
      if (e instanceof BookingRefused) throw e;
      this.log.warn(`conflict check skipped: ${(e as Error).message}`);
    }
  }

  /** Answers to the clinic's own yes/no questions: only questions that exist for this hospital, one answer each. Best effort. */
  private async saveAnswers(bookingId: number, hospitalId: number, raw: unknown) {
    try {
      let arr: unknown = raw;
      if (typeof raw === 'string') { try { arr = JSON.parse(raw); } catch { arr = null; } }
      if (!Array.isArray(arr) || !arr.length) return;
      const [vq] = (await this.db.query(
        `SELECT q.question FROM gangnamunni_treatment_questions q JOIN gangnamunni_hospital_treatments ht ON ht.id = q.hospital_treatment_id WHERE ht.hospital_id = ?`, [hospitalId])) as any;
      const valid = new Set((vq as any[]).map((r) => s(r.question)));
      const seen = new Set<string>();
      for (const pa of arr.slice(0, 30)) {
        const q = s(pa?.q), a = pa?.a;
        if (!valid.has(q) || seen.has(q) || (a !== 0 && a !== 1)) continue;
        seen.add(q);
        await this.db.query('INSERT INTO gangnamunni_booking_answers (booking_id, question, answer) VALUES (?, ?, ?)', [bookingId, q, a]);
      }
    } catch (e) { this.log.error(`pre_answers save failed: ${(e as Error).message}`); }
  }

  /** HMS appointment for clinics that are linked to HMS. A failure never fails the booking (the cron sync picks it up). */
  private async syncHms(bookingId: number, h: any, name: string, phone: string, date: string, time: string, treatment: string, memo: string): Promise<number | null> {
    if (!s(h.hms_hospital_id)) return null;
    const base = (process.env.HMS_API_URL || '').replace(/\/+$/, '');
    const key = process.env.HMS_API_KEY || '';
    if (!base || !key) { this.log.error(`HMS_API_URL/HMS_API_KEY not set: booking #${bookingId} was not sent to HMS`); return null; }
    try {
      const res = await fetch(`${base}/appointments`, {
        method: 'POST',
        headers: { 'X-API-Key': key, 'content-type': 'application/json', accept: 'application/json' },
        body: JSON.stringify({
          patient_name: name, patient_phone: phone, appointment_datetime: `${date} ${time}:00`,
          problem: `[서울미녀 예약] 시술: ${treatment}` + (memo ? `\n메모: ${memo}` : ''), tenant_id: h.hms_hospital_id,
        }),
        signal: AbortSignal.timeout(30_000),
      });
      const j: any = await res.json().catch(() => null);
      const id = n(j?.data?.id);
      if (j?.success && id) {
        await this.db.query('UPDATE gangnamunni_bookings SET hms_appointment_id = ? WHERE id = ?', [id, bookingId]);
        return id;
      }
      this.log.error(`HMS appointment failed (booking #${bookingId}): ${JSON.stringify(j)?.slice(0, 300)}`);
    } catch (e) { this.log.error(`HMS appointment error (booking #${bookingId}): ${(e as Error).message}`); }
    return null;
  }
}

const isOne = (v: unknown) => v === '1' || v === 1 || v === true;
export const dateStr = (v: unknown) => (v instanceof Date ? new Date(v.getTime() + 9 * 3600_000).toISOString().slice(0, 10) : s(v).slice(0, 10));
