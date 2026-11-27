import { BadRequestException, ConflictException, Inject, Injectable, InternalServerErrorException, Logger, ServiceUnavailableException } from '@nestjs/common';
import { FcmClient } from '../bookings/notify/fcm.client';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { chunk, usesTopic } from './notifications.logic';
import { NotificationsService, isMissing } from './notifications.service';
import {
  absoluteLink, ICONS, inferPlatform, isApnsAuthError, memberPushStatus, numEnv, parseSendBody, pushData, runPool, SENDING, sendWithRetry, tally, targetLabel, tokenMessage,
  SendInput, TokenOutcome, topicMessage,
} from './notifications.send.logic';

export interface Actor { id: number; username: string; role: string; ip: string }
export interface SendResult {
  sendId: number;
  duplicate: boolean;
  test: boolean;
  resolved: number;
  inApp: number;
  inAppFailed: number;
  pushSent: number;
  pushFailed: number;
  pushSkipped: number;
  nightSkipped: number;
  deadTokens: number;
  pushError: string;
}

type Row = Record<string, unknown>;
const num = (v: unknown): number => Number(v ?? 0) || 0;
const realSleep = (ms: number): Promise<void> => new Promise((r) => setTimeout(r, ms));
/** Database problems that mean "the write user or its tables are not ready", not a bug. */
const NOT_READY = ['ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_BAD_FIELD_ERROR', 'ER_NO_SUCH_TABLE', 'ECONNREFUSED', 'ETIMEDOUT'];
const notReady = (e: unknown): boolean => NOT_READY.includes(String((e as { code?: string })?.code));

/**
 * Sends one notification to members: the in-app rows, the push to every device token (bounded parallel, retried, dead tokens
 * switched off) and, for a general / system notice to everybody, the Android topic. One send at a time per process. The history
 * row is written first (its request id is unique, so a repeated request never sends twice) and completed at the end.
 */
@Injectable()
export class NotificationsSendService {
  private readonly log = new Logger('NotificationsSend');
  private busy = false;
  private readonly base = (process.env.PHP_PUBLIC_SITE_URL || 'https://seoulbeauty.app/').replace(/\/+$/, '');

  constructor(@Inject(DB) private readonly db: Db, @Inject(DB_RW) private readonly rw: RwDbProvider, private readonly reads: NotificationsService, private readonly fcm: FcmClient) {}

  async send(actor: Actor, body: unknown, now = Date.now(), sleep: (ms: number) => Promise<void> = realSleep): Promise<SendResult> {
    const parsed = parseSendBody(body);
    if (!parsed.ok) throw new BadRequestException({ error: parsed.error });
    const pool = this.rw.get();
    if (!pool) throw new ServiceUnavailableException({ error: 'write_unavailable' });
    if (this.busy) throw new ConflictException({ error: 'busy' });
    this.busy = true;
    try {
      return await this.run(pool, actor, parsed.input, now, sleep);
    } finally {
      this.busy = false;
    }
  }

  private async run(pool: RwDb, actor: Actor, input: SendInput, now: number, sleep: (ms: number) => Promise<void>): Promise<SendResult> {
    const a = input.audience;
    const plan = await this.reads.plan(a, now);
    const { resolved, split } = plan;
    if (resolved.length === 0) throw new BadRequestException({ error: 'no_recipients' });
    if (!input.test && resolved.length !== input.expectedResolved) throw new ConflictException({ error: 'audience_changed', resolved: resolved.length });
    if (split.inApp.length === 0 && split.push.length === 0) throw new BadRequestException({ error: 'nobody_allowed' });

    const link = absoluteLink(input.link, this.base);
    const claim = await this.claim(pool, actor, input, targetLabel(a, resolved.length), link, resolved.length);
    if ('duplicate' in claim) return claim.duplicate;
    const sendId = claim.id;

    try {
      return await this.deliver(pool, sendId, input, plan, link, sleep);
    } catch (e) {
      this.log.error(`send #${sendId}: ${(e as Error).message}`);
      await pool.query('UPDATE gangnamunni_notification_sends SET push_error = ? WHERE id = ?', [`failed: ${(e as Error).message}`.slice(0, 480), sendId]).catch(() => undefined);
      throw new InternalServerErrorException({ error: 'send_failed', sendId });
    }
  }

  /** The history row, written before anything is sent. A repeated request id returns the first send instead of sending again. */
  private async claim(pool: RwDb, actor: Actor, input: SendInput, label: string, link: string, resolved: number): Promise<{ id: number } | { duplicate: SendResult }> {
    try {
      const [res] = (await pool.query(
        `INSERT INTO gangnamunni_notification_sends
           (admin_id, admin_username, target_type, target_count, notification_type, title, message, link, resolved_count, in_app_count, push_sent, push_failed, push_skipped, push_error, push_details, request_id, is_test)
         VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?, NULL, ?, ?)`,
        [actor.id, actor.username, label, input.audience.type, input.title, input.message, link, resolved, SENDING, input.requestId, input.test ? 1 : 0])) as any;
      return { id: num(res?.insertId) };
    } catch (e) {
      if (String((e as { code?: string }).code) === 'ER_DUP_ENTRY') {
        const [rows] = (await pool.query(
          'SELECT id, resolved_count, in_app_count, push_sent, push_failed, push_skipped, push_error, is_test FROM gangnamunni_notification_sends WHERE request_id = ? LIMIT 1', [input.requestId])) as any;
        const r = (rows as Row[])[0];
        if (!r || r.push_error === SENDING) throw new ConflictException({ error: 'in_progress' });
        return { duplicate: { sendId: num(r.id), duplicate: true, test: num(r.is_test) === 1, resolved: num(r.resolved_count), inApp: num(r.in_app_count), inAppFailed: 0, pushSent: num(r.push_sent), pushFailed: num(r.push_failed), pushSkipped: num(r.push_skipped), nightSkipped: 0, deadTokens: 0, pushError: String(r.push_error ?? '') } };
      }
      if (notReady(e)) { this.log.warn(`notification history not ready: ${(e as Error).message}`); throw new ServiceUnavailableException({ error: 'not_ready' }); }
      throw e;
    }
  }

  private async deliver(pool: RwDb, sendId: number, input: SendInput, plan: Awaited<ReturnType<NotificationsService['plan']>>, link: string, sleep: (ms: number) => Promise<void>): Promise<SendResult> {
    const a = input.audience;
    const { resolved, split, night } = plan;
    const body = input.message !== '' ? input.message : input.title;
    const items: [number, string, string, string, string, string][] = [];
    const item = (member: string, channel: 'in_app' | 'push', platform: string, status: 'sent' | 'failed' | 'skipped', code = '') => items.push([sendId, member, channel, platform, status, code]);

    // 1. in-app rows
    const saved = new Set<string>();
    let inAppFailed = 0;
    for (const part of chunk(split.inApp, 500)) {
      const marks = part.map(() => '(?, ?, ?, ?, ?, ?)').join(', ');
      const values = part.flatMap((id) => [id, a.type, input.title, input.message || null, link || null, ICONS[a.type]]);
      try {
        await pool.query(`INSERT INTO gangnamunni_user_notifications (member_id, type, title, message, link, icon) VALUES ${marks}`, values);
        for (const id of part) { saved.add(id); item(id, 'in_app', '', 'sent'); }
      } catch (e) {
        this.log.error(`send #${sendId} in-app rows: ${(e as Error).message}`);
        inAppFailed += part.length;
        for (const id of part) item(id, 'in_app', '', 'failed', 'in_app_error');
      }
    }
    const inAppSet = new Set(split.inApp);
    for (const id of resolved) if (!inAppSet.has(id)) item(id, 'in_app', '', 'skipped', 'setting');

    // 2. push
    const pushSet = new Set(split.push);
    const nightSet = new Set(split.nightSkippedIds);
    for (const id of resolved) if (!pushSet.has(id)) item(id, 'push', '', 'skipped', nightSet.has(id) ? 'night' : 'setting');

    const topic = usesTopic(a.type, a.target);
    const data = pushData(link);
    let pushError = '';
    let outcomes: TokenOutcome[] = [];
    let deadTokens = 0;
    let topicSent = 0;
    let topicFailed = 0;
    const fcmOn = split.push.length > 0 ? await this.fcmEnabled() : true;
    if (split.push.length > 0 && !fcmOn) {
      pushError = 'FCM disabled';
      for (const id of split.push) item(id, 'push', '', 'skipped', 'fcm_disabled');
    } else if (split.push.length > 0) {
      const tokens = await this.tokens(split.push, topic);
      const sess = await this.fcm.session();
      if (!sess) pushError = 'Firebase credentials unavailable';
      const res: (TokenOutcome | undefined)[] = new Array(tokens.length);
      const dead: string[] = [];
      let apnsBroken = false;
      const deadline = Date.now() + numEnv('NOTIFICATIONS_SEND_DEADLINE_SEC', 25, 5, 600) * 1000;
      if (sess) {
        await runPool(tokens, numEnv('NOTIFICATIONS_FCM_CONCURRENCY', 20, 1, 50), async (t, i) => {
          if (t.platform === 'ios' && apnsBroken) { res[i] = { ...t, status: 'skipped', code: 'apns_auth' }; return; }
          const r = await sendWithRetry(() => this.fcm.sendMessage(sess, tokenMessage(t.token, t.platform, input.title, body, data)), sleep);
          if (r.ok) { res[i] = { ...t, status: 'sent', code: '' }; return; }
          if (isApnsAuthError(t.platform, r)) apnsBroken = true;
          if (r.dead) dead.push(t.token);
          res[i] = { ...t, status: 'failed', code: r.code || (r.status ? `http_${r.status}` : 'network') };
        }, () => Date.now() > deadline);
      }
      outcomes = tokens.map((t, i) => res[i] ?? { ...t, status: sess ? ('skipped' as const) : ('failed' as const), code: sess ? 'deadline' : 'credentials' });
      if (apnsBroken) pushError = pushError || 'APNs authentication failed: the remaining iOS tokens were not sent';
      const late = outcomes.filter((o) => o.code === 'deadline').length;
      if (late > 0) pushError = pushError || `time limit reached: ${late} tokens were not sent`;
      for (const part of chunk(dead, 500)) {
        try { await pool.query(`UPDATE g5_push_tokens SET pt_use = 0 WHERE pt_token IN (${part.map(() => '?').join(',')})`, part); deadTokens += part.length; }
        catch (e) { this.log.warn(`send #${sendId} dead tokens not switched off: ${(e as Error).message}`); }
      }
      if (topic) {
        const r = sess ? await sendWithRetry(() => this.fcm.sendMessage(sess, topicMessage(input.title, body, data)), sleep) : null;
        if (r?.ok) topicSent = 1; else topicFailed = 1;
        if (!r?.ok && !pushError) pushError = `Android topic: ${r && !r.ok ? r.code || `http_${r.status}` : 'credentials'}`;
      }
      const perMember = memberPushStatus(outcomes);
      for (const id of split.push) {
        const m = perMember.get(id);
        if (m) item(id, 'push', m.platform, m.status, m.code);
        else item(id, 'push', '', 'skipped', topic ? 'topic' : 'no_token');
      }
    }

    // 3. totals, history row, per-recipient rows
    const t = tally(outcomes);
    const pushSent = t.sent + topicSent;
    const pushFailed = t.failed + topicFailed;
    const pushSkipped = split.skippedPush + split.nightSkipped;
    const details = {
      target: a.target, type: a.type, test: input.test, night, resolved_users: resolved.length, in_app_saved: saved.size, push_enabled_users: split.push.length,
      push_skipped_users: split.skippedPush, night_skipped_users: split.nightSkipped,
      direct: { sent: t.sent, failed: t.failed, skipped: t.skipped, dead_tokens: deadTokens, platform_stats: t.platforms },
      android_topic: topic ? { sent: topicSent, failed: topicFailed, topic: 'seoulbeauty_android_all' } : null,
      error: pushError,
    };
    await pool.query(
      'UPDATE gangnamunni_notification_sends SET target_count = ?, in_app_count = ?, push_sent = ?, push_failed = ?, push_skipped = ?, push_error = ?, push_details = ? WHERE id = ?',
      [saved.size, saved.size, pushSent, pushFailed, pushSkipped, pushError, JSON.stringify(details), sendId]);
    await this.writeItems(pool, sendId, items);
    return { sendId, duplicate: false, test: input.test, resolved: resolved.length, inApp: saved.size, inAppFailed, pushSent, pushFailed, pushSkipped, nightSkipped: split.nightSkipped, deadTokens, pushError };
  }

  /** Per-recipient rows are a detail: when they cannot be written the totals above are still right. */
  private async writeItems(pool: RwDb, sendId: number, items: unknown[][]): Promise<void> {
    try {
      for (const part of chunk(items, 1000)) {
        await pool.query(`INSERT INTO gangnamunni_notification_send_items (send_id, member_id, channel, platform, status, error_code) VALUES ${part.map(() => '(?, ?, ?, ?, ?, ?)').join(', ')}`, part.flat());
      }
    } catch (e) {
      this.log.warn(`send #${sendId} recipient rows not written: ${(e as Error).message}`);
    }
  }

  /** Switch of the PHP admin settings page: a row that says anything but 1 turns every push off. No row = on. */
  private async fcmEnabled(): Promise<boolean> {
    try {
      const [rows] = (await this.db.query("SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = 'fcm_enabled' LIMIT 1")) as any;
      const r = (rows as Row[])[0];
      return !r || String(r.setting_value) === '1';
    } catch (e) {
      if (!isMissing(e)) throw e;
      return true;
    }
  }

  /** Active device tokens of the members, one entry per token. With the Android topic in use the Android tokens are left out (the topic reaches them). */
  private async tokens(memberIds: string[], withoutAndroid: boolean): Promise<{ token: string; memberId: string; platform: string }[]> {
    const seen = new Set<string>();
    const out: { token: string; memberId: string; platform: string }[] = [];
    for (const part of chunk(memberIds)) {
      const [rows] = (await this.db.query(
        `SELECT pt_token, mb_id, pt_platform, pt_device_info FROM g5_push_tokens WHERE pt_use = 1 AND pt_token <> '' AND mb_id IN (${part.map(() => '?').join(',')})`, part)) as any;
      for (const r of rows as Row[]) {
        const token = String(r.pt_token);
        const platform = inferPlatform(r.pt_platform, r.pt_device_info);
        if (seen.has(token) || (withoutAndroid && platform === 'android')) continue;
        seen.add(token);
        out.push({ token, memberId: String(r.mb_id), platform });
      }
    }
    return out;
  }
}
