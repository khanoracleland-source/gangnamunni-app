import { Inject, Injectable, Logger } from '@nestjs/common';
import { createHash as nodeSha, randomBytes } from 'crypto';
import { DB, DbConn, DbPool } from '../db/db.module';
import { MailService } from '../mail/mail.service';
import { createHash, escapeHtml } from '../password-lost/password-lost.logic';
import { nickReserved, prohibitedMail } from '../register/register.logic';
import { candidates, convertId, socialNick, validEmail } from './social-register.logic';
import { kakaoMemberUpdates, objectSha, Provider, SocialProfile } from './social.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
export type CreateResult = { ok: true; mbId: string } | { ok: false; error: 'invalid_email' | 'prohibited_email' | 'email_exists' | 'failed' | 'busy'; message?: string };

/**
 * Social sign-up (plugin/social/register_member.php + register_member_update.php): same g5_member row, same uniqueness rules, plus the
 * profile link. The sign-up bonus, HMS sync and the Gnuboard session come from the PHP bridge (register mode).
 */
@Injectable()
export class SocialRegisterService {
  private readonly log = new Logger(SocialRegisterService.name);
  constructor(@Inject(DB) private readonly db: DbPool, private readonly mail: MailService) {}

  async config(): Promise<Record<string, any>> {
    const [r] = (await this.db.query('SELECT cf_title, cf_register_level, cf_prohibit_email, cf_prohibit_id, cf_email_mb_super_admin, cf_admin_email FROM g5_config LIMIT 1')) as any;
    return (r as any[])[0] ?? {};
  }

  /** social_before_join_check() with G5_SOCIAL_DELETE_DAY = 0: a leftover profile row without a member is dropped, any other row blocks. */
  async joinCheck(provider: Provider, identifier: string): Promise<boolean> {
    const [rows] = (await this.db.query('SELECT mp_no, mb_id FROM g5_member_social_profiles WHERE provider = ? AND identifier = ?', [provider, identifier])) as any;
    for (const r of rows as any[]) {
      if (s(r.mb_id) !== '') return false;
      await this.db.query('DELETE FROM g5_member_social_profiles WHERE mp_no = ?', [r.mp_no]);
    }
    return true;
  }

  /** Kakao only: a verified e-mail that already belongs to a member (not blocked, no Kakao link yet) links the Kakao identity to it. */
  async linkableByVerifiedEmail(p: SocialProfile): Promise<{ mb_id: string; mb_intercept_date: string; mb_leave_date: string; mb_email_certify: string } | null> {
    const email = p.emailVerified.trim().toLowerCase();
    if (!email || !validEmail(email)) return null;
    const [rows] = (await this.db.query('SELECT mb_id, mb_intercept_date, mb_leave_date, mb_email_certify FROM g5_member WHERE LOWER(mb_email) = ? LIMIT 1', [email])) as any;
    const m = (rows as any[])[0];
    if (!m) return null;
    const today = new Date(Date.now() + 9 * 3600_000).toISOString().slice(0, 10).replace(/-/g, '');
    if ((s(m.mb_intercept_date) !== '' && s(m.mb_intercept_date) <= today) || (s(m.mb_leave_date) !== '' && s(m.mb_leave_date) <= today)) return null;
    const [lk] = (await this.db.query("SELECT identifier FROM g5_member_social_profiles WHERE mb_id = ? AND provider = 'kakao' LIMIT 1", [m.mb_id])) as any;
    return (lk as any[]).length ? null : m;
  }

  async createMember(provider: Provider, p: SocialProfile, input: { name: string; email: string; ip: string }, cfg: Record<string, any>): Promise<CreateResult> {
    const email = input.email.trim().toLowerCase();
    if (!validEmail(email)) return { ok: false, error: 'invalid_email' };
    const policy = prohibitedMail(email, s(cfg.cf_prohibit_email));
    if (policy) return { ok: false, error: 'prohibited_email', message: policy };
    if (!this.db.getConnection) throw new Error('pool connection needed');

    const baseId = convertId(p.identifier, provider);
    const hash = baseId.split('_')[1] ?? '';
    let nickBase = Array.from(socialNick(p.displayName)).slice(0, 30).join('') || hash;
    if (nickReserved(nickBase, s(cfg.cf_prohibit_id))) nickBase = 'member' + nodeSha('sha256').update(baseId).digest('hex').slice(0, 10);
    const upd = provider === 'kakao' ? kakaoMemberUpdates(p, new Date().getFullYear()) : {};
    const level = Math.max(1, Number(cfg.cf_register_level) || 2);

    const conn: DbConn = await this.db.getConnection();
    const lock = 'gnu_reg_' + nodeSha('sha256').update(email).digest('hex').slice(0, 40);
    let locked = false;
    try {
      const [l] = (await conn.query('SELECT GET_LOCK(?, 5) AS got', [lock])) as any;
      locked = Number((l as any[])[0]?.got) === 1;
      if (!locked) return { ok: false, error: 'busy' };
      for (let attempt = 0; attempt < 4; attempt++) {
        const [dup] = (await conn.query('SELECT mb_no FROM g5_member WHERE LOWER(mb_email) = ? LIMIT 1', [email])) as any;
        if ((dup as any[]).length) return { ok: false, error: 'email_exists' };
        const [hosp] = (await conn.query("SELECT id FROM gangnamunni_hospitals WHERE LOWER(email) = ? AND status IN ('pending','approved') LIMIT 1", [email])) as any;
        if ((hosp as any[]).length) return { ok: false, error: 'email_exists' };
        const idC = candidates(baseId); const nickC = candidates(nickBase);
        const [ids] = (await conn.query('SELECT mb_id FROM g5_member WHERE mb_id IN (?)', [idC])) as any;
        const [nicks] = (await conn.query('SELECT mb_nick FROM g5_member WHERE mb_nick IN (?)', [nickC])) as any;
        const usedId = new Set((ids as any[]).map((r) => s(r.mb_id).toLowerCase())); const usedNick = new Set((nicks as any[]).map((r) => s(r.mb_nick).toLowerCase()));
        const mbId = idC.find((c) => !usedId.has(c.toLowerCase())); const nick = nickC.find((c) => !usedNick.has(c.toLowerCase()));
        if (!mbId || !nick) return { ok: false, error: 'failed' };
        const [ins] = (await conn.query(
          `INSERT INTO g5_member (mb_id, mb_password, mb_name, mb_nick, mb_nick_date, mb_email, mb_hp, mb_birth, mb_2, mb_email_certify, mb_today_login, mb_datetime, mb_ip, mb_level, mb_login_ip, mb_mailling, mb_sms, mb_open_date)
           SELECT ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, NOW(), NOW(), NOW(), ?, ?, ?, 0, 0, CURDATE()
           FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM g5_member WHERE mb_id = ? OR mb_nick = ? OR LOWER(mb_email) = ?)`,
          [mbId, createHash(randomBytes(16).toString('hex')), input.name, nick, email, upd.mb_hp ?? '', upd.mb_birth ?? '', upd.mb_2 ?? '', input.ip, level, input.ip, mbId, nick, email])) as any;
        if (Number(ins?.affectedRows) === 1) { this.adminMail(email, mbId, cfg).catch(() => undefined); return { ok: true, mbId }; }
      }
      return { ok: false, error: 'failed' };
    } catch (e) {
      this.log.error(`social sign-up insert failed: ${(e as Error).message}`);
      return { ok: false, error: 'failed' };
    } finally {
      if (locked) await conn.query('SELECT RELEASE_LOCK(?)', [lock]).catch(() => undefined);
      conn.release();
    }
  }

  /** cf_email_mb_super_admin only: the social sign-up never sends the member mail (the e-mail is not certified by a code). */
  private async adminMail(email: string, mbId: string, cfg: Record<string, any>): Promise<void> {
    if (!Number(cfg.cf_email_mb_super_admin) || !s(cfg.cf_admin_email)) return;
    await this.mail.send(s(cfg.cf_admin_email), `[${s(cfg.cf_title) || '서울미녀'}] 새 회원이 가입했습니다.`,
      `<div style="font-family:sans-serif;line-height:1.7">회원 ID: ${escapeHtml(mbId)}<br>이메일: ${escapeHtml(email)}</div>`);
  }

  /** social_user_profile_replace() + (Kakao) social_sync_kakao_member_profile(). Best effort: the member is already created / signed in. */
  async saveProfile(mbId: string, provider: Provider, p: SocialProfile): Promise<void> {
    if (!this.db.getConnection) return;
    const conn: DbConn = await this.db.getConnection();
    try {
      await conn.query('DELETE FROM g5_member_social_profiles WHERE provider = ? AND identifier = ? AND mb_id <> ?', [provider, p.identifier, mbId]);
      const [ex] = (await conn.query('SELECT mp_no FROM g5_member_social_profiles WHERE mb_id = ? AND provider = ? AND identifier = ? LIMIT 1', [mbId, provider, p.identifier])) as any;
      const row = (ex as any[])[0];
      const vals = [objectSha(p), p.profileURL.slice(0, 255), p.photoURL.slice(0, 255), p.displayName.slice(0, 150)];
      if (row) await conn.query('UPDATE g5_member_social_profiles SET object_sha = ?, profileurl = ?, photourl = ?, displayname = ?, mp_latest_day = NOW() WHERE mp_no = ?', [...vals, row.mp_no]);
      else await conn.query('INSERT INTO g5_member_social_profiles (mb_id, provider, identifier, object_sha, profileurl, photourl, displayname, mp_register_day, mp_latest_day) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [mbId, provider, p.identifier, ...vals]);
      if (provider === 'kakao') {
        const u = kakaoMemberUpdates(p, new Date().getFullYear());
        const cols = Object.keys(u);
        if (cols.length) await conn.query(`UPDATE g5_member SET ${cols.map((c) => `${c} = ?`).join(', ')} WHERE mb_id = ?`, [...cols.map((c) => u[c]), mbId]);
      }
    } finally { conn.release(); }
  }
}
