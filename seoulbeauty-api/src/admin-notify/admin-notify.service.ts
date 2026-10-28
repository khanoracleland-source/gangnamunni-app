import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { escapeHtml, shouldReceive } from './admin-notify.logic';
import { FcmClient } from './fcm.client';

/**
 * notifyAdmins() (includes/notification_helper.php): in-app notification for every admin, FCM push for the admins
 * subscribed to the type, then a row in the send log. Best effort: each step logs and never throws, so a notification
 * problem can never fail the member's request.
 */
@Injectable()
export class AdminNotifyService {
  private readonly log = new Logger(AdminNotifyService.name);
  constructor(@Inject(DB) private readonly db: Db, private readonly fcm: FcmClient) {}

  async notifyAdmins(type: string, title: string, message: string, link: string | null): Promise<void> {
    await this.inApp(type, title, message, link);
    await this.push(type, title, message, link);
    try {
      await this.db.query(
        "INSERT INTO gangnamunni_notification_sends (admin_id, admin_username, target_type, notification_type, title, message, link, created_at) VALUES (0, 'system', 'all_admins', ?, ?, ?, ?, NOW())",
        [type, title, message, link]);
    } catch (err) { this.log.error(`notification log failed: ${(err as Error).message}`); }
  }

  private async inApp(type: string, title: string, message: string, link: string | null) {
    try {
      const [rows] = (await this.db.query("SELECT id FROM gangnamunni_admins WHERE role IN ('super_admin','admin','editor')")) as any;
      for (const a of rows as any[]) {
        try {
          // $USER_NOTIFICATION_TYPES has no 'review' entry, so the icon is the default bell.
          await this.db.query('INSERT INTO gangnamunni_user_notifications (member_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)',
            [String(a.id), type, title, message, link, 'bi-bell']);
        } catch (err) { this.log.error(`admin in-app notification failed: ${(err as Error).message}`); }
      }
    } catch (err) { this.log.error(`admin list failed: ${(err as Error).message}`); }
  }

  private async push(type: string, title: string, message: string, link: string | null) {
    try {
      const [en] = (await this.db.query("SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = 'fcm_enabled' LIMIT 1")) as any;
      if ((en as any[])[0] && String((en as any[])[0].setting_value) !== '1') return;
      const [admins] = (await this.db.query('SELECT id, permissions FROM gangnamunni_admins WHERE is_active = 1')) as any;
      const targets = (admins as any[]).filter((a) => shouldReceive(a.permissions, type));
      if (!targets.length) return;
      const sess = await this.fcm.session();
      if (!sess) return;
      for (const a of targets) {
        const [toks] = (await this.db.query('SELECT fcm_token, platform FROM gangnamunni_admin_fcm_tokens WHERE admin_id = ? AND is_active = 1', [a.id])) as any;
        for (const t of toks as any[]) {
          const r = await this.fcm.send(sess, String(t.fcm_token), t.platform, title, message, link, Number(a.id));
          if (!r.ok && r.dead) {
            await this.db.query('UPDATE gangnamunni_admin_fcm_tokens SET is_active = 0 WHERE fcm_token = ?', [t.fcm_token]).catch(() => undefined);
          }
        }
      }
    } catch (err) { this.log.error(`admin push failed: ${(err as Error).message}`); }
  }

  /** The "새 후기 등록" notice for a newly created review (hospital name and nickname escaped like the PHP page did). */
  async reviewCreated(hospitalName: string, userName: string, rating: number): Promise<void> {
    await this.notifyAdmins('review', '새 후기 등록', `${escapeHtml(hospitalName)} — ${escapeHtml(userName)} (★${rating})`, 'admin_reviews.php');
  }
}
