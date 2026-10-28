import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { kstNow } from '../bookings/bookings.logic';
import { displayIcon, extractBookingId, filterTypes, NOTI_LIMIT, NotiFilter, openUrl, timeLabel } from './notifications.logic';

const n = (v: unknown) => Number(v ?? 0) || 0;
const s = (v: unknown) => (v == null ? '' : String(v));

@Injectable()
export class NotificationsService {
  private readonly log = new Logger(NotificationsService.name);
  constructor(@Inject(DB) private readonly db: Db) {}

  /** my_notifications.php: one page (20) of the member's notifications + unread total. A missing table reads as empty. */
  async list(memberId: string, filter: NotiFilter, page: number) {
    try {
      const types = filterTypes(filter);
      const where = types.length ? ` AND type IN (${types.map(() => '?').join(',')})` : '';
      const args = [memberId, ...types];
      const [rows] = (await this.db.query(
        `SELECT id, type, title, message, link, icon, is_read, created_at FROM gangnamunni_user_notifications
         WHERE member_id = ?${where} ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?`, [...args, NOTI_LIMIT, (page - 1) * NOTI_LIMIT])) as any;
      const [[cnt]] = (await this.db.query(`SELECT COUNT(*) AS cnt FROM gangnamunni_user_notifications WHERE member_id = ?${where}`, args)) as any;
      const [[unread]] = (await this.db.query('SELECT COUNT(*) AS cnt FROM gangnamunni_user_notifications WHERE member_id = ? AND is_read = 0', [memberId])) as any;
      const list = rows as any[];
      const ids = [...new Set(list.filter((r) => s(r.type) === 'booking').map((r) => extractBookingId(r.link)).filter((v) => v > 0))];
      const bookings = new Map<number, any>();
      if (ids.length) {
        const [bs] = (await this.db.query(`SELECT id, status, booking_date, booking_time FROM gangnamunni_bookings WHERE id IN (${ids.map(() => '?').join(',')})`, ids)) as any;
        for (const b of bs as any[]) bookings.set(n(b.id), b);
      }
      const now = kstNow();
      const total = n(cnt?.cnt);
      return {
        items: list.map((r) => ({
          id: n(r.id), type: s(r.type), title: s(r.title), message: s(r.message), isRead: n(r.is_read) !== 0,
          icon: displayIcon(s(r.type), s(r.title), s(r.message), s(r.icon)), time: timeLabel(r.created_at, now),
          openUrl: openUrl(s(r.type), r.link, bookings.get(extractBookingId(r.link)), now),
        })),
        total, page, hasMore: page * NOTI_LIMIT < total, unread: n(unread?.cnt),
      };
    } catch (e) {
      this.log.error(`list: ${(e as Error).message}`);
      return { items: [], total: 0, page, hasMore: false, unread: 0 };
    }
  }

  async read(memberId: string, id: number) {
    await this.db.query('UPDATE gangnamunni_user_notifications SET is_read = 1 WHERE id = ? AND member_id = ?', [id, memberId]);
    return { success: true };
  }

  async readAll(memberId: string) {
    await this.db.query('UPDATE gangnamunni_user_notifications SET is_read = 1 WHERE member_id = ? AND is_read = 0', [memberId]);
    return { success: true };
  }

  async remove(memberId: string, id: number) {
    const [res] = (await this.db.query('DELETE FROM gangnamunni_user_notifications WHERE id = ? AND member_id = ?', [id, memberId])) as any;
    return { success: n(res?.affectedRows) > 0 };
  }
}
