import { BadRequestException, ConflictException, HttpException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB_RW, RwConnection, RwDb, RwDbProvider } from '../db/db.module';
import { MemberDetail, statusOf } from './members.logic';
import { AuditAction, hashPassword, planEdit, planPassword, RESET_LIMIT_PER_HOUR } from './members.write.logic';
import { MembersService } from './members.service';

type Row = Record<string, unknown>;
export interface WriteActor {
  id: number;
  username: string;
  role: string;
  ip: string;
}

/** Database problems that mean "the database user or its columns cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_BAD_FIELD_ERROR', 'ER_NO_SUCH_TABLE',
  'ER_CON_COUNT_ERROR', 'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1054, 1142, 1143, 1146, 1040];
const s = (v: unknown): string => (v == null ? '' : String(v));

/**
 * Writes to g5_member through the separate write pool. The only change is the e-mail of one member, made in one short transaction:
 * lock the member, refuse an e-mail another member already has, update, and write the audit row. If the audit row cannot be
 * written the e-mail is not changed either.
 */
@Injectable()
export class MembersWriteService {
  private readonly log = new Logger('MembersWrite');

  constructor(@Inject(DB_RW) private readonly rw: RwDbProvider, private readonly reads: MembersService) {}

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  private async transaction<T>(fn: (conn: RwConnection) => Promise<T>): Promise<T> {
    const conn = await this.pool().getConnection();
    try {
      await conn.beginTransaction();
      const out = await fn(conn);
      await conn.commit();
      return out;
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      throw e;
    } finally {
      conn.release();
    }
  }

  private async guarded<T>(fn: () => Promise<T>): Promise<T> {
    try {
      return await fn();
    } catch (e) {
      if (e instanceof HttpException) throw e;
      const err = e as { code?: string; errno?: number; message?: string };
      if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
        this.log.warn(`write database unavailable: ${err.message}`);
        throw new ServiceUnavailableException({ error: 'write_unavailable' });
      }
      if (['ER_LOCK_DEADLOCK', 'ER_LOCK_WAIT_TIMEOUT'].includes(err?.code || '') || [1205, 1213].includes(err?.errno || 0)) {
        this.log.warn(`member write busy: ${err.message}`);
        throw new ServiceUnavailableException({ error: 'busy' });
      }
      if (err?.code === 'ER_DUP_ENTRY' || err?.errno === 1062) throw new ConflictException({ error: 'email_taken' });
      throw e;
    }
  }

  private async journal(conn: RwConnection, actor: WriteActor, member: Row, action: AuditAction, before: string | null, after: string | null): Promise<void> {
    await conn.query(
      `INSERT INTO gangnamunni_member_audit (admin_id, admin_username, admin_role, member_no, member_id, action, before_value, after_value, ip)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [actor.id, actor.username.slice(0, 191), actor.role.slice(0, 50), Number(member.mb_no), s(member.mb_id).slice(0, 30), action, before, after, actor.ip.slice(0, 45)],
    );
  }

  /** Changes the e-mail of one member. 400 invalid, 404 no such member, 409 another member has this e-mail, 503 write side unavailable. */
  async changeEmail(actor: WriteActor, id: number, body: unknown): Promise<MemberDetail> {
    const planned = planEdit(body);
    if (!planned.ok) throw new BadRequestException({ error: 'invalid', fields: planned.errors });
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const changed = await this.guarded(() =>
      this.transaction(async (conn) => {
        const [rows] = (await conn.query('SELECT mb_no, mb_id, mb_email FROM g5_member WHERE mb_no = ? LIMIT 1 FOR UPDATE', [id])) as any;
        const member = (Array.isArray(rows) ? rows[0] : undefined) as Row | undefined;
        if (!member) throw new NotFoundException({ error: 'not_found' });
        const before = s(member.mb_email).trim();
        // Same address (any letter case): nothing to write, nothing to journal.
        if (before.toLowerCase() === planned.email) return false;
        const [taken] = (await conn.query('SELECT mb_no FROM g5_member WHERE LOWER(mb_email) = ? AND mb_no <> ? LIMIT 1', [planned.email, id])) as any;
        if (Array.isArray(taken) && taken.length > 0) throw new ConflictException({ error: 'email_taken' });
        await conn.query('UPDATE g5_member SET mb_email = ? WHERE mb_no = ?', [planned.email, id]);
        await this.journal(conn, actor, member, 'email_change', before || null, planned.email);
        return true;
      }),
    );
    // Field names only, never a value.
    this.log.log(`member write: admin=${actor.id} target=${id} action=email_change changed=${changed}`);
    const fresh = await this.reads.detail(id);
    if (!fresh) throw new NotFoundException({ error: 'not_found' });
    return fresh;
  }

  /**
   * Sets a new password for one active member and ends every sign-in of that member (the credential epoch moves, the remembered / app tokens are
   * deleted), in one transaction with the audit row. The password itself is never written to the journal or the log.
   * 400 invalid, 404 no such member, 409 member_blocked / member_left, 429 too_many (more than the hourly limit), 503 write side unavailable.
   */
  async resetPassword(actor: WriteActor, id: number, body: unknown): Promise<{ ok: true }> {
    const planned = planPassword(body);
    if (!planned.ok) throw new BadRequestException({ error: 'invalid', fields: planned.errors });
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const hash = hashPassword(planned.password);
    await this.guarded(() =>
      this.transaction(async (conn) => {
        const [rows] = (await conn.query('SELECT mb_no, mb_id, mb_leave_date, mb_intercept_date FROM g5_member WHERE mb_no = ? LIMIT 1 FOR UPDATE', [id])) as any;
        const member = (Array.isArray(rows) ? rows[0] : undefined) as Row | undefined;
        if (!member) throw new NotFoundException({ error: 'not_found' });
        const state = statusOf(member);
        if (state === 'left') throw new ConflictException({ error: 'member_left' });
        if (state === 'blocked') throw new ConflictException({ error: 'member_blocked' });
        // The member row is locked, so two resets of the same member cannot both pass this count.
        const [counted] = (await conn.query(
          "SELECT COUNT(*) AS cnt FROM gangnamunni_member_audit WHERE member_no = ? AND action = 'password_reset' AND created_at >= NOW() - INTERVAL 1 HOUR", [id],
        )) as any;
        if ((Number(Array.isArray(counted) ? counted[0]?.cnt : 0) || 0) >= RESET_LIMIT_PER_HOUR) throw new HttpException({ error: 'too_many' }, 429);
        const [updated] = (await conn.query("UPDATE g5_member SET mb_password = ?, mb_cred_epoch = mb_cred_epoch + 1, mb_lost_certify = '' WHERE mb_no = ?", [hash, id])) as any;
        if (Number(updated?.affectedRows) !== 1) throw new ServiceUnavailableException({ error: 'write_unavailable' });
        await conn.query('DELETE FROM sso_tokens WHERE g5_mb_id = ?', [s(member.mb_id)]);
        await this.journal(conn, actor, member, 'password_reset', null, null);
      }),
    );
    // Field names only, never a value.
    this.log.log(`member write: admin=${actor.id} target=${id} action=password_reset`);
    return { ok: true };
  }
}
