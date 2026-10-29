import { BadRequestException, HttpException, Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { fullReason, isEmail, parsePhpJson, PW_MAX, PW_MIN, REASONS } from './account-security.logic';

const s = (v: unknown) => (typeof v === 'string' ? v : '');
interface PhpReply { status: number; json: Record<string, any> | null }

/**
 * Password, e-mail and 회원 탈퇴 stay PHP code (Gnuboard hashing, credential revocation, PHP-session cert code, SMTP, Kakao unlink,
 * the table cleanup): this class is the browser-facing side, it forwards the member's own cookie to PHP and relays the answer.
 */
@Injectable()
export class AccountSecurityService {
  private readonly log = new Logger(AccountSecurityService.name);
  private readonly sessionUrl = process.env.PHP_SESSION_URL || 'https://hms.seoulbeauty.app/api/session_me.php';
  private readonly host = process.env.PHP_SESSION_HOST || '';
  private readonly attempts = new Map<string, number[]>();
  constructor(@Inject(DB) private readonly db: Db) {}

  private apiUrl(file: string) { return new URL(file, new URL('./', this.sessionUrl)).href; }
  private siteUrl(file: string) { return new URL(file, new URL('../', this.sessionUrl)).href; }

  private limit(key: string, max: number) {
    const now = Date.now();
    const l = (this.attempts.get(key) ?? []).filter((t) => t > now - 600_000);
    if (l.length >= max) throw new HttpException({ success: false, message: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' }, 429);
    l.push(now);
    this.attempts.set(key, l);
    if (this.attempts.size > 10_000) this.attempts.clear();
  }

  private async php(url: string, cookie: string, init: { method: 'GET' | 'POST'; body?: string; type?: string; headers?: Record<string, string> }): Promise<PhpReply> {
    const headers: Record<string, string> = { accept: 'application/json', 'user-agent': 'seoulbeauty-api', 'x-requested-with': 'XMLHttpRequest', cookie, ...init.headers };
    if (this.host) headers.host = this.host;
    if (init.type) headers['content-type'] = init.type;
    try {
      const res = await fetch(url, { method: init.method, headers, body: init.body, redirect: 'manual', signal: AbortSignal.timeout(15_000) });
      return { status: res.status, json: parsePhpJson(await res.text()) };
    } catch (e) {
      this.log.error(`php call failed (${new URL(url).pathname}): ${(e as Error).message}`);
      throw new HttpException({ success: false, message: '요청을 처리하지 못했습니다. 잠시 후 다시 시도해주세요.' }, 502);
    }
  }

  /** PHP answers {success:false, message, field?}; a client error is passed through, anything else is a 502. */
  private fail(r: PhpReply, fallback: string): never {
    const message = typeof r.json?.message === 'string' && r.json.message ? r.json.message : fallback;
    const status = r.status >= 400 && r.status < 500 ? r.status : 502;
    const field = typeof r.json?.field === 'string' ? r.json.field : undefined;
    throw new HttpException({ success: false, message, ...(field ? { field } : {}) }, status);
  }

  private async security(cookie: string, body: Record<string, unknown>): Promise<PhpReply> {
    return this.php(this.apiUrl('account_security.php'), cookie, { method: 'POST', type: 'application/json', body: JSON.stringify(body) });
  }

  /** profile_edit.php action=pw. On success PHP has revoked every credential, this device included. */
  async changePassword(mbId: string, cookie: string, input: { current_pw?: unknown; new_pw?: unknown; new_pw_confirm?: unknown }) {
    this.limit(`pw:${mbId}`, 8);
    const current = s(input.current_pw), next = s(input.new_pw), confirm = s(input.new_pw_confirm);
    if (!current) throw new BadRequestException({ success: false, field: 'current_pw', message: '현재 비밀번호를 입력해주세요.' });
    if (next !== confirm) throw new BadRequestException({ success: false, field: 'new_pw_confirm', message: '새 비밀번호가 일치하지 않습니다.' });
    if (next.length < PW_MIN) throw new BadRequestException({ success: false, field: 'new_pw', message: '비밀번호는 6자 이상이어야 합니다.' });
    if (next.length > PW_MAX || current.length > PW_MAX) throw new BadRequestException({ success: false, field: 'new_pw', message: `비밀번호는 ${PW_MAX}자 이하여야 합니다.` });
    const r = await this.security(cookie, { action: 'password', current_pw: current, new_pw: next, new_pw_confirm: confirm });
    if (!r.json?.success) this.fail(r, '비밀번호를 변경하지 못했습니다.');
    return { success: true, message: String(r.json.message ?? '') };
  }

  /** profile_edit.php action=send_email_cert. */
  async sendEmailCode(mbId: string, cookie: string, input: { new_email?: unknown }) {
    this.limit(`email-send:${mbId}`, 5);
    const email = s(input.new_email).trim();
    if (!isEmail(email)) throw new BadRequestException({ success: false, field: 'new_email', message: '올바른 이메일 주소를 입력해주세요.' });
    const r = await this.security(cookie, { action: 'email_send', new_email: email });
    if (!r.json?.success) this.fail(r, '인증 코드를 발송하지 못했습니다.');
    return { success: true, email: String(r.json.email ?? email), expiresIn: Number(r.json.expiresIn ?? 180) };
  }

  /** profile_edit.php action=verify_email_cert. */
  async verifyEmailCode(mbId: string, cookie: string, input: { code?: unknown }) {
    this.limit(`email-verify:${mbId}`, 15);
    const code = s(input.code).trim();
    if (!/^\d{6}$/.test(code)) throw new BadRequestException({ success: false, field: 'code', message: '인증 코드 6자리를 입력해주세요.' });
    const r = await this.security(cookie, { action: 'email_verify', code });
    if (!r.json?.success) this.fail(r, '이메일을 변경하지 못했습니다.');
    return { success: true, email: String(r.json.email ?? '') };
  }

  /** withdraw.php / notification_settings.php lists social-login members apart: no password, and a different (fuller) clean-up. */
  async isSocial(mbId: string): Promise<boolean> {
    const [rows] = (await this.db.query('SELECT provider FROM g5_member_social_profiles WHERE mb_id = ? LIMIT 1', [mbId])) as any;
    return (rows as any[]).length > 0;
  }

  private async csrf(cookie: string): Promise<string> {
    const r = await this.php(`${this.apiUrl('account_security.php')}?action=csrf`, cookie, { method: 'GET' });
    const token = r.json?.token;
    if (typeof token !== 'string' || !token) this.fail(r, '요청을 처리하지 못했습니다.');
    return token;
  }

  /**
   * 회원 탈퇴 exactly as the PHP pages do it (decision: keep both behaviours as they are):
   *  - social member: notification_settings.php action=withdraw_social (Kakao unlink, ~20 tables, photo, verification)
   *  - password member: api/withdraw.php (password check, reason row, account removal)
   */
  async withdraw(mbId: string, cookie: string, input: { reason?: unknown; detail?: unknown; password?: unknown }) {
    this.limit(`withdraw:${mbId}`, 5);
    const reason = s(input.reason).trim();
    if (reason && !(REASONS as readonly string[]).includes(reason)) throw new BadRequestException({ success: false, field: 'reason', message: '탈퇴 사유를 다시 선택해주세요.' });
    const social = await this.isSocial(mbId);
    const password = s(input.password);
    if (!social && !password) throw new BadRequestException({ success: false, field: 'password', message: '비밀번호를 입력해주세요.' });
    if (password.length > PW_MAX) throw new BadRequestException({ success: false, field: 'password', message: '비밀번호가 일치하지 않습니다.' });
    const token = await this.csrf(cookie);
    if (social) {
      const r = await this.php(this.siteUrl('notification_settings.php'), cookie, {
        method: 'POST', type: 'application/x-www-form-urlencoded',
        body: new URLSearchParams({ action: 'withdraw_social', csrf_token: token }).toString(),
      });
      if (r.json?.ok !== true) this.fail(r, '탈퇴 처리에 실패했습니다.');
      return { success: true, message: String(r.json.message ?? '회원 탈퇴가 완료되었습니다.') };
    }
    const r = await this.php(this.apiUrl('withdraw.php'), cookie, {
      method: 'POST', type: 'application/json', headers: { 'x-csrf-token': token },
      body: JSON.stringify({ reason: fullReason(input.reason, input.detail), password }),
    });
    if (!r.json?.success) {
      // withdraw.php answers a wrong password with 400; keep that as a field error
      if (r.status === 400 && /비밀번호/.test(String(r.json?.message ?? ''))) throw new HttpException({ success: false, field: 'password', message: String(r.json!.message) }, 422);
      this.fail(r, '탈퇴 처리에 실패했습니다.');
    }
    return { success: true, message: String(r.json.message ?? '회원 탈퇴가 완료되었습니다.') };
  }
}
