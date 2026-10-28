import { BadRequestException, ConflictException, ForbiddenException, HttpException, Inject, Injectable, InternalServerErrorException, Logger, NotFoundException } from '@nestjs/common';
import { promises as fs } from 'node:fs';
import { join } from 'node:path';
import { randomBytes } from 'node:crypto';
import { DB, Db } from '../db/db.module';
import { kstNow } from '../bookings/bookings.logic';
import { profileFileId } from '../mypage/mypage.logic';
import {
  BIRTH_CHANGE_DAYS, birthToInput, checkProfile, isSettingField, PROFILE_PHOTO_MAX_BYTES, photoKind, SETTING_DEFAULTS, SETTING_FIELDS,
} from './account.logic';

const s = (v: unknown) => (v == null ? '' : String(v));
const n = (v: unknown) => Number(v ?? 0) || 0;
const EXTS = ['jpg', 'jpeg', 'png', 'webp'];

export interface ProfileInput { nick?: unknown; name?: unknown; hp?: unknown; birth?: unknown }
export interface PhotoFile { buffer: Buffer; size: number }

@Injectable()
export class AccountService {
  private readonly log = new Logger(AccountService.name);
  private attempts = new Map<string, number[]>();
  constructor(@Inject(DB) private readonly db: Db) {}

  private rateLimit(key: string, max: number) {
    const now = Date.now();
    const l = (this.attempts.get(key) ?? []).filter((t) => t > now - 600_000);
    if (l.length >= max) throw new HttpException({ success: false, message: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' }, 429);
    l.push(now);
    this.attempts.set(key, l);
    if (this.attempts.size > 10_000) this.attempts.clear();
  }

  private async avatar(mbId: string): Promise<string | null> {
    const dir = process.env.PROFILE_PHOTO_DIR;
    const id = profileFileId(mbId);
    if (!dir || !id) return null;
    const prefix = (process.env.PROFILE_PHOTO_URL || '/hairwang/gangnamunni/uploads/profile/').replace(/\/?$/, '/');
    for (const ext of EXTS) {
      try {
        const st = await fs.stat(join(dir, `${id}.${ext}`));
        if (st.isFile()) return `${prefix}${encodeURIComponent(id)}.${ext}?v=${Math.floor(st.mtimeMs / 1000)}`;
      } catch { /* next extension */ }
    }
    return null;
  }

  private async member(mbId: string) {
    const [rows] = (await this.db.query('SELECT mb_nick, mb_name, mb_hp, mb_birth, mb_email FROM g5_member WHERE mb_id = ? LIMIT 1', [mbId])) as any;
    const r = (rows as any[])[0];
    if (!r) throw new NotFoundException('회원 정보를 찾을 수 없습니다.');
    return r;
  }

  /** profile_edit.php (GET): the editable fields; email and password change stay in PHP. */
  async profile(mbId: string) {
    const [m, photoUrl] = await Promise.all([this.member(mbId), this.avatar(mbId)]);
    return { nick: s(m.mb_nick), name: s(m.mb_name), hp: s(m.mb_hp), birth: birthToInput(m.mb_birth), email: s(m.mb_email), photoUrl, birthChangeDays: BIRTH_CHANGE_DAYS };
  }

  /** profile_edit.php action=save_all: validate everything first, save the member row, then the photo. */
  async saveProfile(mbId: string, input: ProfileInput, file: PhotoFile | undefined, ip: string) {
    this.rateLimit(`profile:${mbId}`, 20);
    const nick = s(input.nick).trim();
    const name = s(input.name).trim();
    const hp = s(input.hp).trim();
    const birth = s(input.birth).trim();
    const bad = checkProfile(name, nick, hp, birth, kstNow().slice(0, 10));
    if (bad) throw new BadRequestException({ success: false, field: bad.field, message: bad.message });

    const [dup] = (await this.db.query('SELECT mb_id FROM g5_member WHERE mb_nick = ? AND mb_id != ? LIMIT 1', [nick, mbId])) as any;
    if ((dup as any[]).length) throw new ConflictException({ success: false, field: 'nick', message: '이미 사용 중인 닉네임입니다.' });

    let kind: { ext: 'jpg' | 'png' | 'webp' } | null = null;
    if (file) {
      if (file.size > PROFILE_PHOTO_MAX_BYTES) throw new BadRequestException({ success: false, field: 'photo', message: '파일 크기는 5MB 이하여야 합니다.' });
      kind = photoKind(file.buffer);
      if (!kind) throw new BadRequestException({ success: false, field: 'photo', message: '이미지 파일만 업로드 가능합니다.' });
    }

    const old = await this.member(mbId);
    const oldBirth = s(old.mb_birth).replace(/\D+/g, '');
    const newBirth = birth.replace(/-/g, '');
    const hadBirth = birthToInput(old.mb_birth) !== '';
    const birthChanged = !hadBirth || oldBirth !== newBirth;
    if (hadBirth && birthChanged) {
      // The age limit for bookings depends on it: one change per 30 days. If the history can't be read, don't allow it (like PHP).
      let recent = 1;
      try {
        const [r] = (await this.db.query(
          `SELECT COUNT(*) AS c FROM gangnamunni_member_birth_log WHERE mb_id = ? AND old_birth <> '' AND created_at > (NOW() - INTERVAL ${BIRTH_CHANGE_DAYS} DAY)`, [mbId])) as any;
        recent = n((r as any[])[0]?.c);
      } catch (e) { this.log.error(`birth log: ${(e as Error).message}`); }
      if (recent >= 1) throw new ConflictException({ success: false, field: 'birth', message: `생년월일은 ${BIRTH_CHANGE_DAYS}일에 한 번만 변경할 수 있어요. 변경이 필요하면 고객센터로 문의해주세요.` });
    }

    try {
      await this.db.query('UPDATE g5_member SET mb_nick = ?, mb_name = ?, mb_hp = ?, mb_birth = ? WHERE mb_id = ?', [nick, name, hp, newBirth, mbId]);
    } catch (e) {
      this.log.error(`profile update: ${(e as Error).message}`);
      throw new InternalServerErrorException({ success: false, message: '저장하지 못했어요. 잠시 후 다시 시도해주세요.' });
    }
    if (birthChanged) {
      try {
        await this.db.query('INSERT INTO gangnamunni_member_birth_log (mb_id, old_birth, new_birth, ip) VALUES (?, ?, ?, ?)', [mbId, hadBirth ? oldBirth : '', newBirth, ip.slice(0, 45)]);
      } catch (e) { this.log.error(`birth log insert: ${(e as Error).message}`); }
    }
    let photoError: string | null = null;
    let photoUrl: string | null = null;
    if (file && kind) {
      try { photoUrl = await this.storePhoto(mbId, file.buffer, kind.ext); } catch (e) {
        this.log.error(`profile photo: ${(e as Error).message}`);
        photoError = '정보는 저장되었지만 사진은 올리지 못했어요. 다시 시도해주세요.';
      }
    }
    return { success: true as const, photoError, photoUrl };
  }

  /** New file first (temp + rename, so a file owned by PHP can be replaced), then the other extensions go. */
  private async storePhoto(mbId: string, buf: Buffer, ext: string): Promise<string> {
    const dir = process.env.PROFILE_PHOTO_DIR;
    const id = profileFileId(mbId);
    if (!dir || !id) throw new Error('PROFILE_PHOTO_DIR / id missing');
    await fs.mkdir(dir, { recursive: true, mode: 0o775 });
    const tmp = join(dir, `.${id}.${randomBytes(4).toString('hex')}.tmp`);
    try {
      await fs.writeFile(tmp, buf, { mode: 0o664, flag: 'wx' });
      await fs.rename(tmp, join(dir, `${id}.${ext}`));
    } catch (e) { await fs.unlink(tmp).catch(() => undefined); throw e; }
    for (const other of EXTS) if (other !== ext) await fs.unlink(join(dir, `${id}.${other}`)).catch(() => undefined);
    return (await this.avatar(mbId)) ?? '';
  }

  /** notification_settings.php: stored values over the defaults. A missing table reads as the defaults. */
  async settings(mbId: string) {
    const out: Record<string, number> = { ...SETTING_DEFAULTS };
    try {
      const [rows] = (await this.db.query(`SELECT ${SETTING_FIELDS.join(', ')} FROM gangnamunni_notification_settings WHERE member_id = ? LIMIT 1`, [mbId])) as any;
      const r = (rows as any[])[0];
      if (r) for (const f of SETTING_FIELDS) if (r[f] != null) out[f] = n(r[f]) ? 1 : 0;
    } catch (e) { this.log.error(`settings: ${(e as Error).message}`); }
    return { settings: out };
  }

  /** One toggle. The column name comes from the whitelist only. */
  async setSetting(mbId: string, field: unknown, value: unknown) {
    if (!isSettingField(field)) throw new HttpException({ success: false, message: '허용되지 않은 설정입니다.' }, 422);
    const v = value === true || value === 1 || value === '1' ? 1 : 0;
    try {
      await this.db.query('INSERT IGNORE INTO gangnamunni_notification_settings (member_id) VALUES (?)', [mbId]);
      await this.db.query(`UPDATE gangnamunni_notification_settings SET ${field} = ? WHERE member_id = ?`, [v, mbId]);
    } catch (e) {
      this.log.error(`setting ${field}: ${(e as Error).message}`);
      throw new InternalServerErrorException({ success: false, message: '설정 저장에 실패했습니다.' });
    }
    return { success: true, field, value: v };
  }
}
