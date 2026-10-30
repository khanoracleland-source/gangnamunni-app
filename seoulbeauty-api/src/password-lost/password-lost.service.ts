import { HttpException, Inject, Injectable, Logger } from '@nestjs/common';
import { randomBytes } from 'crypto';
import * as bcrypt from 'bcryptjs';
import { DB, DbConn, DbPool } from '../db/db.module';
import { MailService } from '../mail/mail.service';
import { isEmail } from '../account/account-security.logic';
import { createHash, RateWindow, resetMail, temporaryPassword } from './password-lost.logic';

const s = (v: unknown) => (v == null ? '' : String(v));

/**
 * gangnamunni/auth/password_lost.php: the e-mail is looked up, a temporary password replaces the Gnuboard hash (and the linked HMS
 * user's bcrypt hash), the same password is mailed, and only when the mail was accepted is everything committed. The answer is the
 * same whether or not the address is a member (no enumeration). All the member's other sign-ins are revoked afterwards.
 */
@Injectable()
export class PasswordLostService {
  private readonly log = new Logger(PasswordLostService.name);
  private readonly byIp = new RateWindow(3, 600_000);
  private readonly byEmailIp = new RateWindow(3, 600_000);
  private readonly cooldown = new RateWindow(1, 60_000);
  constructor(@Inject(DB) private readonly db: DbPool, private readonly mail: MailService) {}

  private tooMany(): never { throw new HttpException({ success: false, message: '잠시 후 다시 시도해주세요.' }, 429); }

  async request(emailRaw: unknown, ip: string): Promise<{ success: true; requestId: string }> {
    const email = s(emailRaw).trim().toLowerCase();
    if (!isEmail(email)) throw new HttpException({ success: false, message: '올바른 이메일 주소를 입력해주세요.' }, 400);
    if (!this.cooldown.hit(`${email}|${ip}`).allowed) this.tooMany();
    if (!this.byIp.hit(ip).allowed || !this.byEmailIp.hit(`${email}|${ip}`).allowed) this.tooMany();
    const requestId = randomBytes(6).toString('hex');
    try {
      await this.process(email, requestId);
    } catch (e) {
      this.log.error(`[${requestId}] ${(e as Error).message}`);
    }
    return { success: true, requestId };
  }

  private async process(email: string, requestId: string): Promise<void> {
    const [rows] = (await this.db.query(
      'SELECT mb_no, mb_id, mb_name, mb_nick, mb_email, mb_leave_date FROM g5_member WHERE LOWER(mb_email) = ?', [email])) as any;
    const list = rows as any[];
    const [cfg] = (await this.db.query('SELECT cf_title, cf_admin FROM g5_config LIMIT 1')) as any;
    const config = (cfg as any[])[0] ?? {};
    // exactly one member, not left, not the super admin; anything else does nothing (and says nothing)
    if (list.length !== 1 || s(list[0].mb_leave_date) !== '' || s(config.cf_admin) === s(list[0].mb_id)) {
      this.log.log(`[${requestId}] no target or excluded account`);
      return;
    }
    const mb = list[0];
    const plain = temporaryPassword(12);
    const { subject, html } = resetMail(s(config.cf_title) || '서울미녀', s(mb.mb_name), plain);

    if (!this.db.getConnection) throw new Error('pool connection needed');
    const conn: DbConn = await this.db.getConnection();
    let committed = false;
    try {
      await conn.query('START TRANSACTION');
      const [up] = (await conn.query("UPDATE g5_member SET mb_password = ?, mb_lost_certify = '' WHERE mb_no = ?", [createHash(plain), mb.mb_no])) as any;
      if (Number(up?.affectedRows) !== 1) { await conn.query('ROLLBACK'); this.log.error(`[${requestId}] password could not be saved`); return; }
      await this.syncHmsPassword(conn, s(mb.mb_id), plain);
      const sent = await this.mail.send(s(mb.mb_email), subject, html);
      if (!sent.success) { await conn.query('ROLLBACK'); this.log.error(`[${requestId}] mail failed: ${sent.error}`); return; }
      await conn.query('COMMIT');
      committed = true;
      this.log.log(`[${requestId}] SMTP accepted`);
      await this.revokeAll(conn, s(mb.mb_id));
    } catch (e) {
      if (!committed) await conn.query('ROLLBACK').catch(() => undefined);
      throw e;
    } finally { conn.release(); }
  }

  /** sync_hms_password_by_mapping(): only through member_sync (never by e-mail: a staff account may share the address). */
  private async syncHmsPassword(conn: DbConn, mbId: string, plain: string): Promise<void> {
    try {
      const [r] = (await conn.query("SELECT hms_user_id FROM member_sync WHERE mb_id = ? AND hms_user_id IS NOT NULL AND sync_status = 'synced' LIMIT 1", [mbId])) as any;
      const id = Number((r as any[])[0]?.hms_user_id) || 0;
      if (id <= 0) return;
      // Laravel/PHP write $2y$; bcryptjs writes $2b$ (same algorithm)
      const hash = bcrypt.hashSync(plain, 10).replace(/^\$2[ab]\$/, '$2y$');
      await conn.query('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?', [hash, id]);
    } catch (e) { this.log.error(`hms password sync failed: ${(e as Error).message}`); throw e; }
  }

  /** revoke_all_auth_credentials(): every session, remember/app token and HMS API token of the member stops working. */
  private async revokeAll(conn: DbConn, mbId: string): Promise<void> {
    if (!/^[0-9a-z_]+$/i.test(mbId)) return;
    try { await conn.query("UPDATE g5_member SET mb_cred_epoch = mb_cred_epoch + 1, mb_lost_certify = '' WHERE mb_id = ?", [mbId]); }
    catch (e) {
      this.log.error(`mb_cred_epoch update failed: ${(e as Error).message}`);
      await conn.query("UPDATE g5_member SET mb_lost_certify = '' WHERE mb_id = ?", [mbId]).catch(() => undefined);
    }
    try { await conn.query('DELETE FROM sso_tokens WHERE g5_mb_id = ?', [mbId]); } catch (e) { this.log.error(`sso_tokens delete failed: ${(e as Error).message}`); }
    try {
      const [r] = (await conn.query('SELECT hms_user_id FROM member_sync WHERE mb_id = ? AND hms_user_id IS NOT NULL ORDER BY hms_user_id DESC LIMIT 1', [mbId])) as any;
      const id = Number((r as any[])[0]?.hms_user_id) || 0;
      if (id > 0) await conn.query('DELETE FROM personal_access_tokens WHERE tokenable_type = ? AND tokenable_id = ?', ['App\\Models\\User', id]);
    } catch (e) { this.log.error(`hms token revoke failed: ${(e as Error).message}`); }
  }
}
