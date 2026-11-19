import { BadRequestException, ConflictException, ForbiddenException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { AdminItem, num, str, Viewer } from './admins.logic';
import { AdminsService } from './admins.service';
import { parseActive, permissionsJson, planActive, planEdit } from './admins.write.logic';

type Row = Record<string, unknown>;
export interface WriteActor {
  id: number;
  role: string;
}

/** Database problems that mean "the write user or its columns cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_BAD_FIELD_ERROR', 'ER_CON_COUNT_ERROR',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1054, 1142, 1143, 1040];

/**
 * Writes to gangnamunni_admins through the separate write pool: name, phone and the notification choice of one account,
 * and its active state. Each write is one short statement; the active state is one short transaction that locks the
 * active super admins so the last one can never be switched off by two requests at once.
 */
@Injectable()
export class AdminsWriteService {
  private readonly log = new Logger('AdminsWrite');

  constructor(
    @Inject(DB) private readonly ro: Db,
    @Inject(DB_RW) private readonly rw: RwDbProvider,
    private readonly reads: AdminsService,
  ) {}

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  private async guarded<T>(fn: () => Promise<T>): Promise<T> {
    try {
      return await fn();
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
        this.log.warn(`write database unavailable: ${err.message}`);
        throw new ServiceUnavailableException({ error: 'write_unavailable' });
      }
      // Another request holds the same rows (deadlock / lock wait): nothing was changed, the caller may simply try again.
      if (['ER_LOCK_DEADLOCK', 'ER_LOCK_WAIT_TIMEOUT'].includes(err?.code || '') || [1205, 1213].includes(err?.errno || 0)) {
        this.log.warn(`admin write busy: ${err.message}`);
        throw new ServiceUnavailableException({ error: 'busy' });
      }
      throw e;
    }
  }

  /** Field names only: a value (a phone number, a notification list) is never logged. */
  private audit(actor: WriteActor, id: number, action: string, names: string[]): void {
    this.log.log(`admin write: admin=${actor.id} role=${actor.role} target=${id} action=${action} fields=${names.join(',')}`);
  }

  private async current(id: number): Promise<Row | undefined> {
    const [rows] = (await this.ro.query('SELECT id, role, is_active, permissions FROM gangnamunni_admins WHERE id = ? LIMIT 1', [id])) as any;
    return Array.isArray(rows) ? (rows[0] as Row | undefined) : undefined;
  }

  /** Name, phone and notifications of one account. The caller has already checked that the actor may edit `id`. */
  async edit(actor: WriteActor, viewer: Viewer, id: number, body: unknown): Promise<{ item: AdminItem }> {
    const planned = planEdit(body);
    if (!planned.ok) throw new BadRequestException({ error: 'invalid', fields: planned.errors });
    const { plan } = planned;
    const target = await this.current(id);
    if (!target) throw new NotFoundException({ error: 'not_found' });

    const sets: string[] = [];
    const values: unknown[] = [];
    const names: string[] = [];
    if (plan.name !== undefined) { sets.push('name = ?'); values.push(plan.name); names.push('name'); }
    if (plan.phone !== undefined) { sets.push('phone = ?'); values.push(plan.phone); names.push('phone'); }
    // The stored text is compared as read, so two edits of the same account at once cannot overwrite each other silently.
    const before = target.permissions === null || target.permissions === undefined ? null : str(target.permissions);
    if (plan.notifications !== undefined) { sets.push('permissions = ?'); values.push(permissionsJson(plan.notifications)); names.push('permissions'); }
    const sql = `UPDATE gangnamunni_admins SET ${sets.join(', ')} WHERE id = ?${plan.notifications !== undefined ? ' AND permissions <=> ?' : ''}`;
    const args = plan.notifications !== undefined ? [...values, id, before] : [...values, id];

    await this.guarded(async () => {
      const [res] = (await this.pool().query(sql, args)) as any;
      if (!res || res.affectedRows !== 1) {
        if (!(await this.current(id))) throw new NotFoundException({ error: 'not_found' });
        throw new ConflictException({ error: 'conflict' });
      }
      this.audit(actor, id, 'edit', names);
    });
    const item = await this.reads.detail(viewer, id);
    if (!item) throw new NotFoundException({ error: 'not_found' });
    return { item };
  }

  /** Super admin only (the controller checks). Sets the exact state asked for. */
  async setActive(actor: WriteActor, viewer: Viewer, id: number, body: unknown): Promise<{ item: AdminItem }> {
    const active = parseActive(body);
    if (active === null) {
      const given = body && typeof body === 'object' ? (body as Record<string, unknown>).active : undefined;
      throw new BadRequestException({ error: 'invalid', fields: { active: given === undefined ? 'required' : 'bad_format' } });
    }
    await this.guarded(async () => {
      const conn = await this.pool().getConnection();
      try {
        await conn.beginTransaction();
        const [rows] = (await conn.query(
          "SELECT id, role, is_active FROM gangnamunni_admins WHERE id = ? OR (role = 'super_admin' AND is_active = 1) FOR UPDATE",
          [id],
        )) as any;
        const list = (Array.isArray(rows) ? rows : []) as Row[];
        const row = list.find((r) => num(r.id) === id);
        const target = row ? { id, role: str(row.role), isActive: num(row.is_active) === 1 } : undefined;
        const supers = list.filter((r) => str(r.role) === 'super_admin' && num(r.is_active) === 1).map((r) => num(r.id));
        const plan = planActive(actor.id, target, supers, active);
        if (!plan.ok) {
          await conn.rollback();
          if (plan.error === 'not_found') throw new NotFoundException({ error: 'not_found' });
          if (plan.error === 'self_forbidden') throw new ForbiddenException({ error: 'self_forbidden' });
          throw new ConflictException({ error: 'last_super_admin' });
        }
        // Switching on clears the mark; switching off starts the clock (a repeat keeps the first mark, or starts it when there is none).
        if (active) await conn.query('UPDATE gangnamunni_admins SET is_active = 1, deactivated_at = NULL WHERE id = ?', [id]);
        else if (target?.isActive) await conn.query('UPDATE gangnamunni_admins SET is_active = 0, deactivated_at = NOW() WHERE id = ?', [id]);
        else await conn.query('UPDATE gangnamunni_admins SET is_active = 0, deactivated_at = COALESCE(deactivated_at, NOW()) WHERE id = ?', [id]);
        await conn.commit();
        this.audit(actor, id, active ? 'activate' : 'deactivate', ['is_active', 'deactivated_at']);
      } catch (e) {
        await conn.rollback().catch(() => undefined);
        throw e;
      } finally {
        conn.release();
      }
    });
    const item = await this.reads.detail(viewer, id);
    if (!item) throw new NotFoundException({ error: 'not_found' });
    return { item };
  }
}
