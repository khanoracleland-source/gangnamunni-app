import { HttpException, Inject, Injectable, Logger } from '@nestjs/common';
import { createHash as sha, createHmac, randomBytes } from 'crypto';
import * as bcrypt from 'bcryptjs';
import { DB, DbConn, DbPool } from '../db/db.module';
import { MailService } from '../mail/mail.service';
import { isEmail } from '../account/account-security.logic';
import { createHash, hashFingerprint, resetLimitsSkipped, RateWindow, readResetTicket, resetMail, resetMarker, resetMarkerValid, signResetTicket, temporaryPassword } from './password-lost.logic';
import { kstYmd, passwordMatches } from '../login/login.logic';
import { PASSWORD_RE } from '../register/register.logic';

const DUMMY_HASH = createHash('dummy-password-for-constant-time');
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
  private readonly verifyByEmail = new RateWindow(10, 3_600_000);
  private readonly verifyByIp = new RateWindow(10, 900_000);
  private readonly verifyByEmailIp = new RateWindow(5, 900_000);
  private readonly confirmByIp = new RateWindow(10, 900_000);
  constructor(@Inject(DB) private readonly db: DbPool, private readonly mail: MailService) {}

  private audit(event: string, who: string, ip: string, details = ''): void {
    this.log.log(`[auth][${event}] account=${who ? sha('sha256').update(who).digest('hex').slice(0, 16) : '-'}; ip=${ip}${details ? `; ${details}` : ''}`);
  }

  private tooMany(): never { throw new HttpException({ success: false, message: '잠시 후 다시 시도해주세요.' }, 429); }

  async request(emailRaw: unknown, ip: string): Promise<{ success: true; requestId: string }> {
    const email = s(emailRaw).trim().toLowerCase();
    if (!isEmail(email)) throw new HttpException({ success: false, message: '올바른 이메일 주소를 입력해주세요.' }, 400);
    const skip = resetLimitsSkipped(email, ip);
    if (skip) this.audit('reset_limits_skipped', email, ip, `request; ${skip}`);
    else {
      if (!this.cooldown.hit(`${email}|${ip}`).allowed) this.tooMany();
      if (!this.byIp.hit(ip).allowed || !this.byEmailIp.hit(`${email}|${ip}`).allowed) this.tooMany();
    }
    const requestId = randomBytes(6).toString('hex');
    this.audit('reset_requested', email, ip, `request=${requestId}`);
    try {
      await this.process(email, requestId);
    } catch (e) {
      this.log.error(`[${requestId}] ${(e as Error).message}`);
    }
    return { success: true, requestId };
  }

  private async process(email: string, requestId: string): Promise<void> {
    const [rows] = (await this.db.query(
      'SELECT mb_no, mb_id, mb_name, mb_nick, mb_email, mb_leave_date, mb_intercept_date, mb_email_certify FROM g5_member WHERE LOWER(mb_email) = ?', [email])) as any;
    const list = rows as any[];
    const [cfg] = (await this.db.query('SELECT cf_title, cf_admin, cf_use_email_certify FROM g5_config LIMIT 1')) as any;
    const config = (cfg as any[])[0] ?? {};
    // exactly one member and eligible() (the one rule verify/confirm use too: not left, blocked, unverified, or the super admin);
    // anything else does nothing (and says nothing) - a mail is never sent for an account that could not finish the reset
    if (list.length !== 1 || !this.eligible(list[0], config)) {
      this.log.log(`[${requestId}] no target or excluded account`);
      return;
    }
    const mb = list[0];
    const plain = temporaryPassword(12);
    const link = `${(process.env.PUBLIC_SITE_URL || 'https://hms.seoulbeauty.app/next').replace(/\/+$/, '')}/password-reset`;
    const { subject, html } = resetMail(s(config.cf_title) || '서울미녀', s(mb.mb_name), plain, link);

    if (!this.db.getConnection) throw new Error('pool connection needed');
    const conn: DbConn = await this.db.getConnection();
    let committed = false;
    try {
      await conn.query('START TRANSACTION');
      const [up] = (await conn.query("UPDATE g5_member SET mb_password = ?, mb_lost_certify = ? WHERE mb_no = ?", [createHash(plain), resetMarker(), mb.mb_no])) as any;
      if (Number(up?.affectedRows) !== 1) { await conn.query('ROLLBACK'); this.log.error(`[${requestId}] password could not be saved`); return; }
      await this.syncHmsPassword(conn, s(mb.mb_id), plain);
      const sent = await this.mail.send(s(mb.mb_email), subject, html);
      if (!sent.success) { await conn.query('ROLLBACK'); this.log.error(`[${requestId}] mail failed: ${sent.error}`); return; }
      await conn.query('COMMIT');
      committed = true;
      this.log.log(`[${requestId}] SMTP accepted`);
      await this.revokeAll(conn, s(mb.mb_id), true);
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
  private async revokeAll(conn: DbConn, mbId: string, keepResetMarker = false): Promise<void> {
    if (!/^[0-9a-z_]+$/i.test(mbId)) return;
    // keepResetMarker: the reset request itself (the marker must outlive the revoke); the confirmed change clears it
    try { await conn.query(keepResetMarker ? 'UPDATE g5_member SET mb_cred_epoch = mb_cred_epoch + 1 WHERE mb_id = ?' : "UPDATE g5_member SET mb_cred_epoch = mb_cred_epoch + 1, mb_lost_certify = '' WHERE mb_id = ?", [mbId]); }
    catch (e) {
      this.log.error(`mb_cred_epoch update failed: ${(e as Error).message}`);
      if (!keepResetMarker) await conn.query("UPDATE g5_member SET mb_lost_certify = '' WHERE mb_id = ?", [mbId]).catch(() => undefined);
    }
    try { await conn.query('DELETE FROM sso_tokens WHERE g5_mb_id = ?', [mbId]); } catch (e) { this.log.error(`sso_tokens delete failed: ${(e as Error).message}`); }
    try {
      const [r] = (await conn.query('SELECT hms_user_id FROM member_sync WHERE mb_id = ? AND hms_user_id IS NOT NULL ORDER BY hms_user_id DESC LIMIT 1', [mbId])) as any;
      const id = Number((r as any[])[0]?.hms_user_id) || 0;
      if (id > 0) await conn.query('DELETE FROM personal_access_tokens WHERE tokenable_type = ? AND tokenable_id = ?', ['App\\Models\\User', id]);
    } catch (e) { this.log.error(`hms token revoke failed: ${(e as Error).message}`); }
  }

  private secret(): string {
    const k = process.env.SESSION_BRIDGE_SECRET || '';
    if (k.length < 32) throw new HttpException({ success: false, message: '지금은 비밀번호를 변경할 수 없습니다. 잠시 후 다시 시도해주세요.' }, 503);
    // purpose-bound key: never the raw bridge secret
    return createHmac('sha256', k).update('seoulbeauty:password-reset-ticket:v1').digest('hex');
  }

  /** Step 2: the temporary password mailed by a reset request (marker set, not expired) is checked. Every failure answers the same. */
  async verifyTemporary(emailRaw: unknown, tempRaw: unknown, ip: string): Promise<{ success: true; ticket: string }> {
    const email = s(emailRaw).trim().toLowerCase();
    const temp = s(tempRaw);
    const wrong = (why: string) => { this.audit('reset_verify_failed', email, ip, why); return new HttpException({ success: false, message: '이메일 또는 임시 비밀번호가 맞지 않아요. 다시 확인해주세요' }, 401); };
    if (!isEmail(email) || !temp || temp.length > 128) throw wrong('input');
    const secret = this.secret();
    const skipV = resetLimitsSkipped(email, ip);
    if (skipV) this.audit('reset_limits_skipped', email, ip, `verify; ${skipV}`);
    if (!skipV && (!this.verifyByIp.hit(ip).allowed || !this.verifyByEmailIp.hit(`${email}|${ip}`).allowed || !this.verifyByEmail.hit(email).allowed)) { this.audit('reset_verify_rate_limited', email, ip); this.tooMany(); }
    const [rows] = (await this.db.query('SELECT mb_id, mb_password, mb_leave_date, mb_intercept_date, mb_email_certify, mb_lost_certify FROM g5_member WHERE LOWER(mb_email) = ?', [email])) as any;
    const list = rows as any[];
    const [cfg] = (await this.db.query('SELECT cf_admin, cf_use_email_certify FROM g5_config LIMIT 1')) as any;
    const config = (cfg as any[])[0] ?? {};
    const mb = list.length === 1 ? list[0] : null;
    // one PBKDF2 whatever the account looks like, so the answer time does not tell whether a reset request is open
    const hash = mb ? s(mb.mb_password) : DUMMY_HASH;
    const matches = passwordMatches(temp, hash);
    if (!mb || !this.eligible(mb, config)) throw wrong('no_target');
    if (!resetMarkerValid(s(mb.mb_lost_certify))) throw wrong('no_open_request'); // only an account with a live reset request
    if (!matches) throw wrong('password');
    this.audit('reset_verified', email, ip);
    return { success: true, ticket: signResetTicket(secret, s(mb.mb_id), s(mb.mb_password)) };
  }

  /** same exclusions as the login (left, blocked, unverified e-mail) plus the super admin */
  private eligible(mb: any, config: any): boolean {
    const today = kstYmd();
    const blocked = s(mb.mb_intercept_date) !== '' && s(mb.mb_intercept_date) <= today;
    const left = s(mb.mb_leave_date) !== '' && s(mb.mb_leave_date) <= today;
    const unverified = Number(config.cf_use_email_certify) > 0 && !/[1-9]/.test(s(mb.mb_email_certify));
    return !blocked && !left && !unverified && s(mb.mb_id) !== s(config.cf_admin);
  }

  /** Step 3: the new password replaces the temporary one (Gnuboard hash + linked HMS user); every other sign-in is revoked. */
  async setNewPassword(ticketRaw: unknown, pwRaw: unknown, ip: string): Promise<{ success: true }> {
    const bad = (message: string, status = 400) => new HttpException({ success: false, message }, status);
    const secret = this.secret();
    if (!this.confirmByIp.hit(ip).allowed) this.tooMany();
    const t = readResetTicket(secret, s(ticketRaw));
    if (!t) throw bad('인증이 만료되었어요. 처음부터 다시 진행해주세요.', 401);
    const pw = s(pwRaw);
    if (!PASSWORD_RE.test(pw)) throw bad('비밀번호는 영문, 숫자, 특수문자를 각각 포함한 9~20자로 입력해주세요.');
    if (!this.db.getConnection) throw new Error('pool connection needed');
    const conn: DbConn = await this.db.getConnection();
    let committed = false;
    try {
      await conn.query('START TRANSACTION');
      const [rows] = (await conn.query('SELECT mb_no, mb_id, mb_password, mb_leave_date, mb_intercept_date, mb_email_certify, mb_lost_certify FROM g5_member WHERE mb_id = ? FOR UPDATE', [t.mbId])) as any;
      const mb = (rows as any[])[0];
      const [cfg] = (await conn.query('SELECT cf_admin, cf_use_email_certify FROM g5_config LIMIT 1')) as any;
      // the ticket is bound to the hash it was issued for: used once (or the password changed meanwhile) = expired
      if (!mb || !this.eligible(mb, (cfg as any[])[0] ?? {}) || !resetMarkerValid(s(mb.mb_lost_certify)) || hashFingerprint(s(mb.mb_password)) !== t.fp) { await conn.query('ROLLBACK'); this.audit('reset_confirm_refused', t.mbId, ip); throw bad('인증이 만료되었어요. 처음부터 다시 진행해주세요.', 401); }
      if (passwordMatches(pw, s(mb.mb_password))) { await conn.query('ROLLBACK'); throw bad('임시 비밀번호와 다른 비밀번호를 입력해주세요.'); }
      await conn.query("UPDATE g5_member SET mb_password = ?, mb_lost_certify = '' WHERE mb_no = ?", [createHash(pw), mb.mb_no]);
      await this.syncHmsPassword(conn, s(mb.mb_id), pw);
      await conn.query('COMMIT');
      committed = true;
      this.audit('reset_password_changed', t.mbId, ip);
      await this.revokeAll(conn, s(mb.mb_id));
    } catch (e) {
      if (!committed) await conn.query('ROLLBACK').catch(() => undefined);
      if (e instanceof HttpException) throw e;
      this.log.error(`set new password: ${(e as Error).message}`);
      throw bad('비밀번호를 변경하지 못했어요. 잠시 후 다시 시도해주세요.', 500);
    } finally { conn.release(); }
    return { success: true };
  }
}
