import { HttpException, Inject, Injectable, Logger } from '@nestjs/common';
import { createHash as nodeSha } from 'crypto';
import { createHash, escapeHtml, RateWindow } from '../password-lost/password-lost.logic';
import { DB, DbConn, DbPool } from '../db/db.module';
import { MailService } from '../mail/mail.service';
import { isEmail } from '../account/account-security.logic';
import { SessionBridgeService } from '../session-bridge/session-bridge.service';
import { codeMail, makeNick, newCode, newMbId, NAME_RE, OtpStore, parseBirth, PASSWORD_RE, PHONE_RE, prohibitedMail } from './register.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const bad = (message: string, status = 400): never => { throw new HttpException({ success: false, message }, status); };

export interface RegisterInput {
  email?: unknown; code?: unknown; password?: unknown; password2?: unknown; name?: unknown; birthDate?: unknown; phone?: unknown;
  agreeTerms?: unknown; agreePrivacy?: unknown; agreeMarketing?: unknown;
}

/**
 * gangnamunni/auth/register.php. Same checks in the same order, same g5_member row; the Gnuboard session is minted by PHP through the
 * bridge (api/session_login.php) because only PHP can create it. If the bridge is unavailable the account still exists and the caller
 * is told to sign in (loggedIn=false).
 */
@Injectable()
export class RegisterService {
  private readonly log = new Logger(RegisterService.name);
  private readonly otp = new OtpStore();
  private readonly codeIp = new RateWindow(5, 600_000);
  private readonly codeEmail = new RateWindow(3, 600_000);
  private readonly codeCooldown = new RateWindow(1, 60_000);
  private readonly submitIp = new RateWindow(20, 600_000);
  constructor(@Inject(DB) private readonly db: DbPool, private readonly mail: MailService, private readonly bridge: SessionBridgeService) {}

  private async config(): Promise<Record<string, any>> {
    const [r] = (await this.db.query('SELECT cf_title, cf_register_level, cf_prohibit_email, cf_prohibit_id, cf_email_mb_member, cf_email_mb_super_admin, cf_admin_email FROM g5_config LIMIT 1')) as any;
    return (r as any[])[0] ?? {};
  }

  async sendCode(emailRaw: unknown, ip: string): Promise<{ success: true; message: string }> {
    const email = s(emailRaw).trim().toLowerCase();
    if (!isEmail(email)) bad('올바른 이메일 주소를 입력해주세요.');
    const cfg = await this.config();
    const policy = prohibitedMail(email, s(cfg.cf_prohibit_email));
    if (policy) bad(policy);
    const ipRate = this.codeIp.hit(ip);
    if (!ipRate.allowed) bad(`인증 메일 요청이 너무 많습니다. ${ipRate.retry}초 후 다시 시도해주세요.`, 429);
    const cd = this.codeCooldown.hit(`${email}`);
    const er = cd.allowed ? this.codeEmail.hit(email) : cd;
    if (!er.allowed) bad(`잠시 후 다시 요청해주세요. (${er.retry}초)`, 429);
    // The same path runs whether or not the address is already a member (no timing/answer difference).
    const code = newCode();
    this.otp.clear(email);
    const { subject, html } = codeMail(code);
    const sent = await this.mail.send(email, subject, html);
    if (!sent.success) { this.log.error(`code mail failed: ${sent.error}`); bad('인증코드 발송에 실패했습니다. 잠시 후 다시 시도해주세요.', 502); }
    this.otp.issue(email, code);
    return { success: true, message: '인증코드를 이메일로 발송했습니다. (10분 내 입력)' };
  }

  async register(b: RegisterInput, ip: string, cookie: string, userAgent: string): Promise<{ success: true; loggedIn: boolean; cookies: string[] }> {
    if (!this.submitIp.hit(ip).allowed) bad('잠시 후 다시 시도해주세요.', 429);
    const email = s(b.email).trim().toLowerCase();
    const name = s(b.name).replace(/[\x00-\x1F\x7F]/g, '').trim();
    const birth = parseBirth(s(b.birthDate).trim());
    const password = s(b.password);
    const phone = s(b.phone).replace(/[^0-9]/g, '');
    const marketing = s(b.agreeMarketing) === '1' || b.agreeMarketing === true ? 1 : 0;
    const yes = (v: unknown) => v === true || s(v) === '1';
    const cfg = await this.config();

    if (!yes(b.agreeTerms) || !yes(b.agreePrivacy)) bad('회원가입 약관과 개인정보 수집·이용에 동의해주세요.');
    if (!isEmail(email)) bad('올바른 이메일 주소를 입력해주세요.');
    if (!NAME_RE.test(name)) bad('이름을 2~50자의 올바른 형식으로 입력해주세요.');
    if (!birth) bad('올바른 생년월일을 입력해주세요.');
    const policy = prohibitedMail(email, s(cfg.cf_prohibit_email));
    if (policy) bad(policy);
    const otpErr = this.otp.check(email, s(b.code).trim());
    if (otpErr) bad(otpErr);
    if (!PASSWORD_RE.test(password)) bad('비밀번호는 영문, 숫자, 특수문자를 각각 포함한 9~20자로 입력해주세요.');
    if (password !== s(b.password2)) bad('비밀번호가 일치하지 않습니다.');
    if (!PHONE_RE.test(phone)) bad('휴대폰 번호를 정확히 입력해주세요. (예: 01012345678)');

    // HMS sync and the sign-up bonus run inside the PHP bridge: without it an account would be created half-set-up.
    if (!this.bridge.configured()) { this.log.error('SESSION_BRIDGE_SECRET is not set'); bad('지금은 회원가입을 처리할 수 없습니다. 잠시 후 다시 시도해주세요.', 503); }
    const mbId = await this.create(email, name, password, phone, birth!, marketing, ip, cfg);
    this.otp.clear(email);
    this.afterMails(email, mbId, cfg).catch(() => undefined);
    const minted = await this.bridge.mint(mbId, 'register', false, cookie, userAgent);
    return { success: true, loggedIn: minted.ok, cookies: minted.setCookies };
  }

  private async create(email: string, name: string, password: string, phone: string, birth: { ymd: string; age: number }, marketing: number, ip: string, cfg: Record<string, any>): Promise<string> {
    const [dup] = (await this.db.query('SELECT mb_no FROM g5_member WHERE LOWER(mb_email) = LOWER(?) LIMIT 1', [email])) as any;
    const dupMsg = (id: string) => `이미 가입된 이메일입니다. 로그인하거나 비밀번호 찾기를 이용해주세요. (${id})`;
    const reqId = Math.random().toString(16).slice(2, 14);
    if ((dup as any[]).length) { this.log.warn(`[${reqId}] duplicate precheck`); bad(dupMsg(reqId), 409); }
    const [hosp] = (await this.db.query("SELECT id FROM gangnamunni_hospitals WHERE LOWER(email) = LOWER(?) AND status IN ('pending','approved') LIMIT 1", [email])) as any;
    if ((hosp as any[]).length) bad('이미 병원관리자로 신청/가입된 이메일입니다. 하나의 이메일은 일반회원 또는 병원관리자 중 하나로만 가입할 수 있습니다.', 409);

    const mbId = newMbId(email);
    const nick = makeNick(name, mbId, s(cfg.cf_prohibit_id));
    const level = Math.max(1, Number(cfg.cf_register_level) || 2);
    if (!this.db.getConnection) throw new Error('pool connection needed');
    const conn: DbConn = await this.db.getConnection();
    const lock = 'gnu_reg_' + createHashSha(email);
    let locked = false;
    try {
      const [l] = (await conn.query('SELECT GET_LOCK(?, 5) AS got', [lock])) as any;
      locked = Number((l as any[])[0]?.got) === 1;
      if (!locked) bad('가입 처리가 지연되고 있습니다. 잠시 후 다시 시도해주세요.', 503);
      // one statement: inserts only while no member with this e-mail exists (no unique index on mb_email)
      const [ins] = (await conn.query(
        `INSERT INTO g5_member (mb_id, mb_password, mb_name, mb_nick, mb_nick_date, mb_email, mb_level, mb_datetime, mb_today_login, mb_ip, mb_login_ip, mb_email_certify, mb_open_date, mb_1, mb_recommend, mb_mailling, mb_sms, mb_hp, mb_birth, mb_2)
         SELECT ?, ?, ?, ?, CURDATE(), ?, ?, NOW(), NOW(), ?, ?, NOW(), CURDATE(), 'general', '', ?, ?, ?, ?, ?
         FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM g5_member WHERE LOWER(mb_email) = LOWER(?))`,
        [mbId, createHash(password), name, nick, email, level, ip, ip, marketing, marketing, phone, birth.ymd, String(birth.age), email])) as any;
      if (Number(ins?.affectedRows) !== 1) { this.log.warn(`[${reqId}] duplicate atomic insert`); bad(dupMsg(reqId), 409); }
    } catch (e) {
      if (e instanceof HttpException) throw e;
      this.log.error(`[${reqId}] insert failed: ${(e as Error).message}`);
      bad('가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 500);
    } finally {
      if (locked) await conn.query('SELECT RELEASE_LOCK(?)', [lock]).catch(() => undefined);
      conn.release();
    }
    return mbId;
  }

  /** cf_email_mb_member / cf_email_mb_super_admin: the welcome and admin notices of the Gnuboard mail settings. */
  private async afterMails(email: string, mbId: string, cfg: Record<string, any>): Promise<void> {
    const title = s(cfg.cf_title) || '서울미녀';
    if (Number(cfg.cf_email_mb_member)) {
      await this.mail.send(email, `[${title}] 회원가입을 환영합니다.`, `<div style="font-family:sans-serif;line-height:1.7">${escapeHtml(mbId)}님, 회원가입이 완료되었습니다.</div>`);
    }
    if (Number(cfg.cf_email_mb_super_admin) && s(cfg.cf_admin_email)) {
      await this.mail.send(s(cfg.cf_admin_email), `[${title}] 새 회원이 가입했습니다.`,
        `<div style="font-family:sans-serif;line-height:1.7">회원 ID: ${escapeHtml(mbId)}<br>이메일: ${escapeHtml(email)}</div>`);
    }
  }
}

const createHashSha = (v: string): string => nodeSha('sha256').update(v.toLowerCase()).digest('hex').slice(0, 40);
