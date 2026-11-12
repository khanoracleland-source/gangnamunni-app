import { BadRequestException, HttpException, Inject, Injectable } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { fullReason, isEmail, PW_MAX, PW_MIN, REASONS } from './account-security.logic';
import { AccountSecurityNativeService } from './account-security-native.service';

const s = (v: unknown) => (typeof v === 'string' ? v : '');

/**
 * Password, e-mail and 회원 탈퇴: the browser-facing side (input checks, rate limit). The work itself is AccountSecurityNativeService.
 * Until 2026-11-12 these three were forwarded to PHP (api/account_security.php, api/withdraw.php, notification_settings.php).
 */
@Injectable()
export class AccountSecurityService {
  private readonly attempts = new Map<string, number[]>();
  constructor(@Inject(DB) private readonly db: Db, private readonly native: AccountSecurityNativeService) {}

  private limit(key: string, max: number) {
    const now = Date.now();
    const l = (this.attempts.get(key) ?? []).filter((t) => t > now - 600_000);
    if (l.length >= max) throw new HttpException({ success: false, message: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' }, 429);
    l.push(now);
    this.attempts.set(key, l);
    if (this.attempts.size > 10_000) this.attempts.clear();
  }

  /** On success every sign-in of the member is revoked, this device included. */
  async changePassword(mbId: string, input: { current_pw?: unknown; new_pw?: unknown; new_pw_confirm?: unknown }) {
    this.limit(`pw:${mbId}`, 8);
    const current = s(input.current_pw), next = s(input.new_pw), confirm = s(input.new_pw_confirm);
    if (!current) throw new BadRequestException({ success: false, field: 'current_pw', message: '현재 비밀번호를 입력해주세요.' });
    if (next !== confirm) throw new BadRequestException({ success: false, field: 'new_pw_confirm', message: '새 비밀번호가 일치하지 않습니다.' });
    if (next.length < PW_MIN) throw new BadRequestException({ success: false, field: 'new_pw', message: '비밀번호는 6자 이상이어야 합니다.' });
    if (next.length > PW_MAX || current.length > PW_MAX) throw new BadRequestException({ success: false, field: 'new_pw', message: `비밀번호는 ${PW_MAX}자 이하여야 합니다.` });
    return this.native.changePassword(mbId, current, next);
  }

  async sendEmailCode(mbId: string, input: { new_email?: unknown }) {
    this.limit(`email-send:${mbId}`, 5);
    const email = s(input.new_email).trim();
    if (!isEmail(email)) throw new BadRequestException({ success: false, field: 'new_email', message: '올바른 이메일 주소를 입력해주세요.' });
    return this.native.sendEmailCode(mbId, email);
  }

  async verifyEmailCode(mbId: string, input: { code?: unknown }) {
    this.limit(`email-verify:${mbId}`, 15);
    const code = s(input.code).trim();
    if (!/^\d{6}$/.test(code)) throw new BadRequestException({ success: false, field: 'code', message: '인증 코드 6자리를 입력해주세요.' });
    return this.native.verifyEmailCode(mbId, code);
  }

  /** A social-login member has no password to confirm with, and a different (fuller) clean-up. */
  async isSocial(mbId: string): Promise<boolean> {
    const [rows] = (await this.db.query('SELECT provider FROM g5_member_social_profiles WHERE mb_id = ? LIMIT 1', [mbId])) as any;
    return (rows as any[]).length > 0;
  }

  async withdraw(mbId: string, input: { reason?: unknown; detail?: unknown; password?: unknown }) {
    this.limit(`withdraw:${mbId}`, 5);
    const reason = s(input.reason).trim();
    if (reason && !(REASONS as readonly string[]).includes(reason)) throw new BadRequestException({ success: false, field: 'reason', message: '탈퇴 사유를 다시 선택해주세요.' });
    const social = await this.isSocial(mbId);
    const password = s(input.password);
    if (!social && !password) throw new BadRequestException({ success: false, field: 'password', message: '비밀번호를 입력해주세요.' });
    if (password.length > PW_MAX) throw new BadRequestException({ success: false, field: 'password', message: '비밀번호가 일치하지 않습니다.' });
    return this.native.withdraw(mbId, password, fullReason(input.reason, input.detail));
  }
}
