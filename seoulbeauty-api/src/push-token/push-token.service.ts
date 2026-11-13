import { HttpException, Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { deviceLike, PushTokenInput, tokenTail } from './push-token.logic';

const n = (v: unknown) => Number(v ?? 0) || 0;
const errCode = (e: unknown) => `${(e as Error)?.name || 'Error'} ${String((e as { code?: unknown })?.code ?? '')}`.trim();

/**
 * The member's device token in g5_push_tokens (read by member-notify, the booking page and the daily cleanup), written the way PHP
 * seoulbeauty_register_push_token() writes it: the same three statements in the same order, one row per token (UNIQUE pt_token).
 * Logs carry the last 6 characters of a token and the driver's error code only (a MySQL message can quote the whole token).
 */
@Injectable()
export class PushTokenService {
  private readonly log = new Logger(PushTokenService.name);
  constructor(@Inject(DB) private readonly db: Db) {}

  enabled(): boolean { return process.env.APP_PUSH_TOKEN_NEST === '1'; }

  private unavailable(what: string, e: unknown, token: string): never {
    this.log.error(`${what} failed (${tokenTail(token)}): ${errCode(e)}`);
    throw new HttpException({ success: false, error: 'push_token_unavailable' }, 503);
  }

  async register(mbId: string, t: PushTokenInput): Promise<{ success: true; linked: true }> {
    // PHP runs these two with errors ignored (@mysqli_query): a failure here must not stop the registration itself.
    try {
      // the device got a new token: its older tokens are switched off, whoever they belonged to
      if (t.deviceId !== '') await this.db.query('UPDATE g5_push_tokens SET pt_use = 0 WHERE pt_token <> ? AND pt_device_info LIKE ?', [t.token, deviceLike(t.deviceId)]);
      // the member's older tokens of this platform that carry no device id
      await this.db.query(
        `UPDATE g5_push_tokens SET pt_use = 0
          WHERE pt_token <> ? AND mb_id = ? AND pt_platform = ?
            AND (pt_device_info IS NULL OR pt_device_info = '' OR pt_device_info LIKE '%"device_id":""%')`,
        [t.token, mbId, t.platform]);
    } catch (e) { this.log.warn(`register: older tokens not switched off (${tokenTail(t.token)}): ${errCode(e)}`); }

    try {
      // UNIQUE KEY pt_token: a token another member registered on this device moves to this member
      await this.db.query(
        `INSERT INTO g5_push_tokens (mb_id, pt_token, pt_platform, pt_device_info, pt_datetime, pt_use)
         VALUES (?, ?, ?, ?, NOW(), 1)
         ON DUPLICATE KEY UPDATE mb_id = VALUES(mb_id), pt_platform = VALUES(pt_platform), pt_device_info = VALUES(pt_device_info), pt_datetime = NOW(), pt_use = 1`,
        [mbId, t.token, t.platform, t.deviceInfo]);
    } catch (e) { this.unavailable('register', e, t.token); }
    this.log.log(`registered ${t.platform} token ${tokenTail(t.token)}`);
    return { success: true, linked: true };
  }

  /**
   * Before sign-out: the row stays but no longer belongs to the member, exactly the state PHP left a token in when the device opened a
   * page signed out (mb_id = ''). No member push reaches it (every reader filters by mb_id); the next sign-in links it again, and the
   * daily cleanup switches it off after 14 days without one (pt_datetime restarts here). Somebody else's token is left alone.
   */
  async unlink(mbId: string, token: string): Promise<{ success: true }> {
    try {
      const [res] = (await this.db.query("UPDATE g5_push_tokens SET mb_id = '', pt_datetime = NOW() WHERE pt_token = ? AND mb_id = ?", [token, mbId])) as any;
      if (n((res as any)?.affectedRows) > 0) this.log.log(`unlinked token ${tokenTail(token)}`);
    } catch (e) { this.unavailable('unlink', e, token); }
    return { success: true };
  }
}
