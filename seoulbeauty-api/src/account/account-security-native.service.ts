import { HttpException, Inject, Injectable, Logger } from '@nestjs/common';
import { randomInt, timingSafeEqual } from 'crypto';
import { promises as fs } from 'fs';
import { join } from 'path';
import { DB, DbConn, DbPool } from '../db/db.module';
import { MailService } from '../mail/mail.service';
import { passwordMatches } from '../login/login.logic';
import { createHash } from '../password-lost/password-lost.logic';
import { PasswordLostService } from '../password-lost/password-lost.service';
import { EMAIL_CODE_MAX_TRIES, EMAIL_CODE_TTL_MS, emailCodeMail, WITHDRAW_BY_MB_ID, WITHDRAW_BY_MB_NO, WITHDRAW_STAFF_ROLES } from './account-security.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const fail = (status: number, message: string, field?: string) => new HttpException({ success: false, message, ...(field ? { field } : {}) }, status);
interface EmailCode { code: string; email: string; expire: number; tries: number }

/**
 * Password, e-mail and 회원 탈퇴 done by Nest itself (no PHP call): the same rules and side effects as api/account_security.php,
 * api/withdraw.php and notification_settings.php action=withdraw_social. Which of the three run here is chosen by
 * ACCOUNT_NEST_ACTIONS (see AccountSecurityService); the callers have already validated the input shape and the rate limit.
 */
@Injectable()
export class AccountSecurityNativeService {
  private readonly log = new Logger(AccountSecurityNativeService.name);
  /** the PHP session held this; one process, three minutes: memory is enough (a restart only means "send the code again") */
  private readonly codes = new Map<string, EmailCode>();
  constructor(@Inject(DB) private readonly db: DbPool, private readonly mail: MailService, private readonly lost: PasswordLostService) {}

  private async conn(): Promise<DbConn> {
    if (!this.db.getConnection) throw new Error('pool connection needed');
    return this.db.getConnection();
  }

  /** account_security.php action=password: the Gnuboard hash, the linked HMS user's hash, then every sign-in is revoked (this one too). */
  async changePassword(mbId: string, current: string, next: string) {
    const conn = await this.conn();
    let committed = false;
    try {
      await conn.query('START TRANSACTION');
      const [rows] = (await conn.query('SELECT mb_no, mb_password FROM g5_member WHERE mb_id = ? FOR UPDATE', [mbId])) as any;
      const mb = (rows as any[])[0];
      if (!mb) throw fail(404, '회원 정보를 찾을 수 없습니다.');
      if (!passwordMatches(current, s(mb.mb_password))) throw fail(422, '현재 비밀번호가 올바르지 않습니다. 비밀번호를 재설정했다면 이메일로 받은 12자리 임시 비밀번호를 입력해주세요.', 'current_pw');
      const [up] = (await conn.query('UPDATE g5_member SET mb_password = ? WHERE mb_no = ?', [createHash(next), mb.mb_no])) as any;
      if (Number(up?.affectedRows) !== 1) throw new Error('password row not updated');
      await this.lost.syncHmsPassword(conn, mbId, next);
      await conn.query('COMMIT');
      committed = true;
      await this.lost.revokeAll(conn, mbId);
    } catch (e) {
      if (!committed) await conn.query('ROLLBACK').catch(() => undefined);
      if (e instanceof HttpException) throw e;
      this.log.error(`password change: ${(e as Error).message}`);
      throw fail(500, '비밀번호를 저장하지 못했습니다.');
    } finally { conn.release(); }
    return { success: true, message: '비밀번호가 변경되었습니다. 새 비밀번호로 다시 로그인해주세요.' };
  }

  private async emailTaken(email: string, mbId: string): Promise<boolean> {
    const [rows] = (await this.db.query('SELECT mb_id FROM g5_member WHERE mb_email = ? AND mb_id != ? LIMIT 1', [email, mbId])) as any;
    return (rows as any[]).length > 0;
  }

  /** action=email_send. Unlike PHP (which ignored a failed mail) the member is told when the code could not be mailed. */
  async sendEmailCode(mbId: string, email: string) {
    if (await this.emailTaken(email, mbId)) throw fail(409, '이미 사용 중인 이메일입니다.', 'new_email');
    const code = String(randomInt(0, 1_000_000)).padStart(6, '0');
    const { subject, html } = emailCodeMail(code);
    const sent = await this.mail.send(email, subject, html);
    if (!sent.success) {
      this.log.error(`email code mail failed: ${sent.error}`);
      throw fail(503, '인증 코드를 발송하지 못했습니다. 잠시 후 다시 시도해주세요.', 'new_email');
    }
    const now = Date.now();
    if (this.codes.size > 5_000) for (const [k, v] of this.codes) if (v.expire < now) this.codes.delete(k);
    this.codes.set(mbId, { code, email, expire: now + EMAIL_CODE_TTL_MS, tries: 0 });
    return { success: true, email, expiresIn: EMAIL_CODE_TTL_MS / 1000 };
  }

  /** action=email_verify: three minutes, five wrong tries, and the address is checked again before it is saved. */
  async verifyEmailCode(mbId: string, input: string) {
    const c = this.codes.get(mbId);
    if (!c) throw fail(422, '인증 코드를 먼저 발송해주세요.', 'code');
    if (Date.now() > c.expire) { this.codes.delete(mbId); throw fail(422, '인증 코드가 만료되었습니다. 다시 발송해주세요.', 'code'); }
    const a = Buffer.from(c.code), b = Buffer.from(input);
    if (a.length !== b.length || !timingSafeEqual(a, b)) {
      c.tries++;
      if (c.tries >= EMAIL_CODE_MAX_TRIES) { this.codes.delete(mbId); throw fail(429, '인증 시도 횟수를 초과했습니다. 코드를 다시 발송해주세요.', 'code'); }
      throw fail(422, '인증 코드가 올바르지 않습니다.', 'code');
    }
    this.codes.delete(mbId); // one use, whatever happens next
    if (await this.emailTaken(c.email, mbId)) throw fail(409, '이미 사용 중인 이메일입니다.', 'code');
    try {
      const [up] = (await this.db.query('UPDATE g5_member SET mb_email = ? WHERE mb_id = ?', [c.email, mbId])) as any;
      if (Number(up?.affectedRows) < 1 && Number(up?.changedRows ?? 0) < 1) {
        const [still] = (await this.db.query('SELECT 1 FROM g5_member WHERE mb_id = ? LIMIT 1', [mbId])) as any;
        if (!(still as any[]).length) throw new Error('member not found');
      }
    } catch (e) {
      this.log.error(`email change: ${(e as Error).message}`);
      throw fail(500, '이메일을 저장하지 못했습니다.');
    }
    return { success: true, email: c.email };
  }

  /**
   * unlinkKakaoAccountForWithdrawal(): Kakao consent is withdrawn (/v1/user/unlink with the admin key) before anything local is deleted.
   * -101 = Kakao no longer knows this user for our app (an earlier attempt already unlinked it): that is the wanted end state.
   */
  private async unlinkKakao(kakaoUserId: string): Promise<boolean> {
    const key = process.env.KAKAO_ADMIN_KEY || '';
    if (!key || !/^\d+$/.test(kakaoUserId)) { this.log.error('[kakao unlink] KAKAO_ADMIN_KEY or the Kakao user id is missing'); return false; }
    try {
      const res = await fetch('https://kapi.kakao.com/v1/user/unlink', {
        method: 'POST', signal: AbortSignal.timeout(10_000),
        headers: { Authorization: `KakaoAK ${key}`, 'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8' },
        body: new URLSearchParams({ target_id_type: 'user_id', target_id: kakaoUserId }).toString(),
      });
      const d = (await res.json().catch(() => null)) as { id?: unknown; code?: unknown } | null;
      if (res.status === 200 && s(d?.id) === kakaoUserId) return true;
      if (Number(d?.code) === -101) { this.log.warn('[kakao unlink] already unlinked'); return true; }
      this.log.error(`[kakao unlink] HTTP ${res.status} code=${s(d?.code)}`);
      return false;
    } catch (e) {
      this.log.error(`[kakao unlink] ${(e as Error).message}`);
      return false;
    }
  }

  /**
   * 회원 탈퇴. A password member: api/withdraw.php (password check, reason row, the account). A social member:
   * notification_settings.php withdraw_social (Kakao unlink, the member's service data, the account, the profile photo).
   */
  async withdraw(mbId: string, password: string, reason: string) {
    const [mrows] = (await this.db.query('SELECT mb_no, mb_id, mb_password, mb_nick, mb_email FROM g5_member WHERE mb_id = ? LIMIT 1', [mbId])) as any;
    const mb = (mrows as any[])[0];
    if (!mb) throw fail(404, '회원 정보를 찾을 수 없습니다.');
    const [cfg] = (await this.db.query('SELECT cf_admin FROM g5_config LIMIT 1')) as any;
    if (s((cfg as any[])[0]?.cf_admin) !== '' && s((cfg as any[])[0]?.cf_admin) === s(mb.mb_id)) throw fail(403, '관리자 계정은 탈퇴할 수 없습니다.'); // not in PHP: the site owner's row must never go this way
    const [prows] = (await this.db.query('SELECT provider, identifier FROM g5_member_social_profiles WHERE mb_id = ? LIMIT 1', [mbId])) as any;
    const profile = (prows as any[])[0] as { provider?: unknown; identifier?: unknown } | undefined;
    if (!profile && !passwordMatches(password, s(mb.mb_password))) throw fail(422, '비밀번호가 일치하지 않습니다.', 'password');
    if (s(profile?.provider).toLowerCase() === 'kakao' && !(await this.unlinkKakao(s(profile?.identifier)))) throw fail(502, '카카오 계정 연결 해제에 실패했습니다. 다시 로그인한 후 재시도해주세요.');

    const mbNo = Number(mb.mb_no), id = s(mb.mb_id);
    const conn = await this.conn();
    let committed = false;
    try {
      await conn.query('START TRANSACTION');
      const tables = [...new Set([...WITHDRAW_BY_MB_NO, ...WITHDRAW_BY_MB_ID].map(([t]) => t)), 'gangnamunni_admins'];
      const [crows] = (await conn.query('SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?)', [tables])) as any;
      const has = new Set((crows as any[]).map((r) => `${s(r.t)}.${s(r.c)}`));
      // table and column names come only from the constant lists
      const del = async (t: string, c: string, v: string | number) => { if (has.has(`${t}.${c}`)) await conn.query(`DELETE FROM \`${t}\` WHERE \`${c}\` = ?`, [v]); };

      if (!profile) await conn.query('INSERT INTO gangnamunni_withdrawals (mb_id, mb_nick, mb_email, reason, withdrawn_at) VALUES (?, ?, ?, ?, NOW())', [id, s(mb.mb_nick), s(mb.mb_email), reason]);
      // only staff accounts explicitly linked to this member (g5_mb_id); never by a matching e-mail or id
      if (has.has('gangnamunni_admins.g5_mb_id')) await conn.query('DELETE FROM gangnamunni_admins WHERE g5_mb_id = ? AND role IN (?)', [id, [...WITHDRAW_STAFF_ROLES]]);
      const [srows] = (await conn.query('SELECT hms_user_id FROM member_sync WHERE mb_no = ? OR mb_id = ? LIMIT 1', [mbNo, id])) as any;
      const hmsId = Number((srows as any[])[0]?.hms_user_id) || 0;
      if (hmsId > 0) await conn.query('UPDATE users SET status = 0 WHERE id = ?', [hmsId]);
      if (profile) {
        for (const [t, c] of WITHDRAW_BY_MB_NO) await del(t, c, mbNo);
        for (const [t, c] of WITHDRAW_BY_MB_ID) await del(t, c, id);
      }
      await conn.query('DELETE FROM member_sync WHERE mb_no = ? OR mb_id = ?', [mbNo, id]); // a mapping left behind would re-create the member at the next sync
      await conn.query('DELETE FROM sso_tokens WHERE g5_mb_id = ?', [id]);
      await conn.query('DELETE FROM g5_member_social_profiles WHERE mb_id = ?', [id]);
      const [gone] = (await conn.query('DELETE FROM g5_member WHERE mb_id = ?', [id])) as any;
      if (Number(gone?.affectedRows) !== 1) throw new Error('member_not_deleted');
      const [left] = (await conn.query('SELECT (SELECT COUNT(*) FROM g5_member WHERE mb_id = ?) + (SELECT COUNT(*) FROM g5_member_social_profiles WHERE mb_id = ?) + (SELECT COUNT(*) FROM sso_tokens WHERE g5_mb_id = ?) AS n', [id, id, id])) as any;
      if (Number((left as any[])[0]?.n) !== 0) throw new Error('withdrawal_verification_failed');
      await conn.query('COMMIT');
      committed = true;
    } catch (e) {
      if (!committed) await conn.query('ROLLBACK').catch(() => undefined);
      this.log.error(`withdraw: ${(e as Error).message}`);
      throw fail(500, '탈퇴 처리 중 오류가 발생했습니다.');
    } finally { conn.release(); }

    if (profile) await this.removePhoto(id);
    this.codes.delete(mbId);
    this.log.log(`[auth][withdrawn] kind=${profile ? s(profile.provider).toLowerCase() || 'social' : 'password'}`);
    return { success: true, message: profile ? '회원 탈퇴가 완료되었습니다.' : '회원 탈퇴가 완료되었습니다. 이용해주셔서 감사합니다.' };
  }

  /** the photo file is named after mb_id: left behind, a later sign-up with the same social id would show the old face */
  private async removePhoto(mbId: string): Promise<void> {
    const dir = process.env.PROFILE_PHOTO_DIR, name = mbId.replace(/[^A-Za-z0-9_-]/g, '');
    if (!dir || !name) return;
    for (const ext of ['jpg', 'jpeg', 'png', 'webp']) await fs.unlink(join(dir, `${name}.${ext}`)).catch(() => undefined);
  }
}
