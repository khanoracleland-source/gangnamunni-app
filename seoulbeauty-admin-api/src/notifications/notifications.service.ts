import { BadRequestException, Inject, Injectable } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { Query } from '../hospitals/hospitals.logic';
import {
  Audience, buildPreview, chunk, duplicateTokens, HISTORY_PER_PAGE, isNight, ITEM_STATUSES, ITEMS_PER_PAGE, LinkTarget, linkSearchPattern,
  NIGHT_END, NIGHT_START, NotificationType, parseAudience, parsePushDetails, prefsQuery, Prefs, Preview, recipientsQuery, searchStaticLinks, splitRecipients,
  Split, STATIC_LINKS, TARGETS, tokenCountQuery, toPrefs, TYPE_LABELS, TYPES, gateFor,
} from './notifications.logic';

type Row = Record<string, unknown>;
const num = (v: unknown): number => Number(v ?? 0) || 0;
const text = (v: unknown): string => (v === null || v === undefined ? '' : String(v));

/** A table or column that is not there yet (or not granted yet): the page answers "not available" instead of failing. */
export const isMissing = (e: unknown): boolean =>
  ['ER_NO_SUCH_TABLE', 'ER_BAD_FIELD_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR'].includes(String((e as { code?: string })?.code));

const HISTORY_COLUMNS =
  'id, admin_id, admin_username, target_type, notification_type, title, message, link, resolved_count, in_app_count, push_sent, push_failed, push_skipped, push_error, created_at';

/** Read-only side of the notifications page (read-only pool). Recipients, preview, history, link search, diagnostics. */
@Injectable()
export class NotificationsService {
  private entriesMemberId: boolean | undefined;
  constructor(@Inject(DB) private readonly db: Db) {}

  private async rows(q: Query): Promise<Row[]> {
    const [rows] = (await this.db.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  }

  private async entriesHaveMemberId(): Promise<boolean> {
    if (this.entriesMemberId !== undefined) return this.entriesMemberId;
    try {
      this.entriesMemberId = (await this.rows({ sql: "SHOW COLUMNS FROM gangnamunni_event_entries LIKE 'member_id'", values: [] })).length > 0;
    } catch (e) {
      if (!isMissing(e)) throw e;
      this.entriesMemberId = false;
    }
    return this.entriesMemberId;
  }

  /** The member ids of an audience (members that left are not included). */
  async resolve(a: Audience): Promise<string[]> {
    const rows = await this.rows(recipientsQuery(a, await this.entriesHaveMemberId()));
    return [...new Set(rows.map((r) => text(r.mb_id)).filter((s) => s !== ''))];
  }

  /** The 설정 switches of the members; `available` = false when the settings table cannot be read (everybody gets the page defaults). */
  async loadPrefs(ids: string[]): Promise<{ prefs: Map<string, Prefs>; available: boolean }> {
    const prefs = new Map<string, Prefs>();
    try {
      for (const part of chunk(ids)) for (const r of await this.rows(prefsQuery(part))) prefs.set(text(r.member_id), toPrefs(r));
    } catch (e) {
      if (!isMissing(e)) throw e;
      return { prefs: new Map(), available: false };
    }
    return { prefs, available: true };
  }

  async tokenCounts(ids: string[]): Promise<{ members: number; tokens: number }> {
    let members = 0;
    let tokens = 0;
    try {
      for (const part of chunk(ids)) {
        const r = (await this.rows(tokenCountQuery(part)))[0];
        members += num(r?.members_with_tokens);
        tokens += num(r?.tokens);
      }
    } catch (e) {
      if (!isMissing(e)) throw e;
    }
    return { members, tokens };
  }

  /** Recipients, their switches and the split by channel: shared by the preview and (later) the sender. */
  async plan(a: Audience, now = Date.now()): Promise<{ resolved: string[]; split: Split; night: boolean; settingsAvailable: boolean }> {
    const resolved = await this.resolve(a);
    const { prefs, available } = await this.loadPrefs(resolved);
    const night = isNight(now);
    return { resolved, split: splitRecipients(resolved, prefs, a.type, night), night, settingsAvailable: available };
  }

  async preview(body: unknown, now = Date.now()): Promise<Preview> {
    const parsed = parseAudience(body);
    if (!parsed.ok) throw new BadRequestException({ error: parsed.error });
    const a = parsed.audience;
    const { resolved, split, night, settingsAvailable } = await this.plan(a, now);
    const tokens = split.push.length ? await this.tokenCounts(split.push) : { members: 0, tokens: 0 };
    return buildPreview(a, resolved.length, split, tokens, night, settingsAvailable);
  }

  async options(now = Date.now()) {
    let events: { id: number; title: string }[] = [];
    try {
      events = (await this.rows({ sql: 'SELECT id, title FROM gangnamunni_events WHERE is_active = 1 ORDER BY id DESC LIMIT 50', values: [] })).map((r) => ({ id: num(r.id), title: text(r.title) }));
    } catch (e) {
      if (!isMissing(e)) throw e;
    }
    return {
      types: TYPES.map((value) => ({ value, label: TYPE_LABELS[value], gate: gateFor(value as NotificationType).kind })),
      targets: [...TARGETS],
      events,
      links: STATIC_LINKS,
      night: isNight(now),
      nightWindow: { start: NIGHT_START, end: NIGHT_END },
      fcmConfigured: !!process.env.FCM_SERVICE_ACCOUNT_PATH,
      sendEnabled: process.env.ADMIN_NOTIFICATIONS_SEND === '1',
      scheduleEnabled: process.env.ADMIN_NOTIFICATIONS_SCHEDULE === '1',
    };
  }

  async history(page: number) {
    const offset = (page - 1) * HISTORY_PER_PAGE;
    let total = 0;
    let items: Row[] = [];
    let available = true;
    try {
      total = num((await this.rows({ sql: 'SELECT COUNT(*) AS cnt FROM gangnamunni_notification_sends', values: [] }))[0]?.cnt);
      const limit = [HISTORY_PER_PAGE, offset];
      try {
        items = await this.rows({ sql: `SELECT ${HISTORY_COLUMNS}, request_id, is_test FROM gangnamunni_notification_sends ORDER BY id DESC LIMIT ? OFFSET ?`, values: limit });
      } catch (e) {
        if (!isMissing(e)) throw e;
        items = await this.rows({ sql: `SELECT ${HISTORY_COLUMNS} FROM gangnamunni_notification_sends ORDER BY id DESC LIMIT ? OFFSET ?`, values: limit });
      }
    } catch (e) {
      if (!isMissing(e)) throw e;
      available = false;
    }
    return { available, total, page, perPage: HISTORY_PER_PAGE, items: items.map(toHistoryItem) };
  }

  /** null = no such send. */
  async historyDetail(id: number, page: number, status: string) {
    if (id <= 0) return null;
    let row: Row | undefined;
    try {
      row = (await this.rows({ sql: `SELECT ${HISTORY_COLUMNS}, push_details, request_id, is_test FROM gangnamunni_notification_sends WHERE id = ? LIMIT 1`, values: [id] }))[0];
    } catch (e) {
      if (!isMissing(e)) throw e;
      row = (await this.rows({ sql: `SELECT ${HISTORY_COLUMNS}, push_details FROM gangnamunni_notification_sends WHERE id = ? LIMIT 1`, values: [id] }))[0];
    }
    if (!row) return null;
    const filter = (ITEM_STATUSES as readonly string[]).includes(status) ? status : '';
    const where = filter ? 'send_id = ? AND status = ?' : 'send_id = ?';
    const values: unknown[] = filter ? [id, filter] : [id];
    let itemsAvailable = true;
    let total = 0;
    let items: Row[] = [];
    try {
      total = num((await this.rows({ sql: `SELECT COUNT(*) AS cnt FROM gangnamunni_notification_send_items WHERE ${where}`, values }))[0]?.cnt);
      items = await this.rows({
        sql: `SELECT member_id, channel, platform, status, error_code, created_at FROM gangnamunni_notification_send_items WHERE ${where} ORDER BY id LIMIT ? OFFSET ?`,
        values: [...values, ITEMS_PER_PAGE, (page - 1) * ITEMS_PER_PAGE],
      });
    } catch (e) {
      if (!isMissing(e)) throw e;
      itemsAvailable = false;
    }
    return {
      ...toHistoryItem(row),
      details: parsePushDetails(row.push_details),
      itemsAvailable,
      items: { total, page, perPage: ITEMS_PER_PAGE, status: filter, rows: items.map((r) => ({ memberId: text(r.member_id), channel: text(r.channel), platform: text(r.platform), status: text(r.status), errorCode: text(r.error_code), at: text(r.created_at) })) },
    };
  }

  async linkTargets(q: string, kind: string): Promise<LinkTarget[]> {
    const out: LinkTarget[] = [];
    const all = kind === '';
    if (all || kind === 'page') out.push(...searchStaticLinks(q));
    const like = linkSearchPattern(q);
    try {
      if (all || kind === 'hospital') {
        const r = await this.rows({ sql: `SELECT id, name FROM gangnamunni_hospitals WHERE status = 'approved' AND name LIKE ? ORDER BY name ASC LIMIT 10`, values: [like] });
        out.push(...r.map((h): LinkTarget => ({ kind: 'hospital', path: `/next/hospitals/${num(h.id)}`, label: text(h.name) })));
      }
      if (all || kind === 'event') {
        const r = await this.rows({ sql: `SELECT id, title FROM gangnamunni_events WHERE is_active = 1 AND title LIKE ? ORDER BY id DESC LIMIT 10`, values: [like] });
        out.push(...r.map((e): LinkTarget => ({ kind: 'event', path: `/next/events/${num(e.id)}`, label: text(e.title) })));
      }
    } catch (e) {
      if (!isMissing(e)) throw e;
    }
    return out;
  }

  /** Push token health (port of token_diagnostics, without the HMS-era iOS counters). The token itself is never returned, only its first 6 characters. */
  async diagnostics() {
    const summary = (await this.rows({
      sql: `SELECT COUNT(*) AS total_tokens, SUM(pt_use = 1) AS active_tokens, SUM(pt_use = 0) AS dead_tokens,
              SUM(pt_use = 1 AND pt_platform = 'android') AS active_android, SUM(pt_use = 1 AND pt_platform = 'ios') AS active_ios,
              SUM(pt_use = 1 AND pt_platform = 'web') AS active_web, SUM(pt_use = 1 AND (mb_id IS NULL OR mb_id = '')) AS active_without_member,
              SUM(pt_use = 1 AND pt_platform = 'android' AND (pt_device_info LIKE '%iPhone%' OR pt_device_info LIKE '%iPad%' OR pt_device_info LIKE '%iPod%')) AS ios_marked_android
            FROM g5_push_tokens`, values: [],
    }))[0] ?? {};
    const [active, multi, recent] = await Promise.all([
      this.rows({ sql: `SELECT mb_id, pt_platform, pt_device_info, pt_datetime FROM g5_push_tokens WHERE pt_use = 1 AND mb_id IS NOT NULL AND mb_id <> '' ORDER BY pt_datetime DESC, pt_id DESC LIMIT 2000`, values: [] }),
      this.rows({ sql: `SELECT mb_id, pt_platform, COUNT(*) AS cnt, MAX(pt_datetime) AS last_seen FROM g5_push_tokens WHERE pt_use = 1 AND mb_id IS NOT NULL AND mb_id <> '' GROUP BY mb_id, pt_platform HAVING cnt > 1 ORDER BY cnt DESC, last_seen DESC LIMIT 10`, values: [] }),
      this.rows({ sql: `SELECT mb_id, pt_platform, pt_use, LEFT(pt_token, 6) AS token_start, pt_datetime FROM g5_push_tokens ORDER BY pt_datetime DESC LIMIT 8`, values: [] }),
    ]);
    return {
      summary: {
        total: num(summary.total_tokens), active: num(summary.active_tokens), dead: num(summary.dead_tokens), android: num(summary.active_android), ios: num(summary.active_ios),
        web: num(summary.active_web), withoutMember: num(summary.active_without_member), iosMarkedAndroid: num(summary.ios_marked_android),
      },
      duplicates: duplicateTokens(active),
      multiDevices: multi.map((r) => ({ memberId: text(r.mb_id), platform: text(r.pt_platform), count: num(r.cnt), lastSeen: text(r.last_seen) })),
      recent: recent.map((r) => ({ memberId: text(r.mb_id), platform: text(r.pt_platform), active: num(r.pt_use) === 1, tokenStart: text(r.token_start), at: text(r.pt_datetime) })),
    };
  }

  /** 방문 전 알림 is sent by the member API itself (10:00 KST on); here only what it has sent. The switch lives in that API's env, so it is not reported. */
  async automatic() {
    try {
      const r = (await this.rows({
        sql: `SELECT SUM(visit_reminder_sent_at >= CURDATE()) AS today, SUM(visit_reminder_sent_at >= CURDATE() - INTERVAL 1 DAY AND visit_reminder_sent_at < CURDATE()) AS yesterday,
                MAX(visit_reminder_sent_at) AS last_sent FROM gangnamunni_bookings WHERE visit_reminder_sent_at >= CURDATE() - INTERVAL 7 DAY`, values: [],
      }))[0] ?? {};
      return { visitReminder: { available: true, sentToday: num(r.today), sentYesterday: num(r.yesterday), lastSentAt: text(r.last_sent) || null, startsAt: '10:00' } };
    } catch (e) {
      if (!isMissing(e)) throw e;
      return { visitReminder: { available: false, sentToday: 0, sentYesterday: 0, lastSentAt: null, startsAt: '10:00' } };
    }
  }
}

function toHistoryItem(r: Row) {
  return {
    id: num(r.id), adminId: num(r.admin_id), adminUsername: text(r.admin_username), target: text(r.target_type), type: text(r.notification_type), title: text(r.title),
    message: text(r.message), link: text(r.link), resolved: num(r.resolved_count), inApp: num(r.in_app_count), pushSent: num(r.push_sent), pushFailed: num(r.push_failed),
    pushSkipped: num(r.push_skipped), pushError: text(r.push_error), isTest: num(r.is_test) === 1, requestId: text(r.request_id) || null, createdAt: text(r.created_at),
  };
}

