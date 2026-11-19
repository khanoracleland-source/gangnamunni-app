import { BadRequestException, ConflictException, ForbiddenException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import * as bcrypt from 'bcryptjs';
import { DB, Db, DB_RW, RwConnection, RwDb, RwDbProvider } from '../db/db.module';
import { AdminItem, DELETE_AFTER_DAYS, num, readNotifications, str, Viewer } from './admins.logic';
import { AdminsService } from './admins.service';
import { parseActive, parseConfirm, permissionsJson, planActive, planCreate, planDelete, planEdit } from './admins.write.logic';

type Row = Record<string, unknown>;
export interface WriteActor {
  id: number;
  role: string;
}

/** Database problems that mean "the database user or its columns cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_BAD_FIELD_ERROR', 'ER_CON_COUNT_ERROR',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1054, 1142, 1143, 1040];

/** The row being changed and every active super admin, locked together: the last super admin can never be lost to two requests at once. */
const LOCK_SQL =
  "SELECT id, username, role, is_active, hospital_id, permissions, deactivated_at, " +
  "(deactivated_at IS NOT NULL AND deactivated_at <= NOW() - INTERVAL ? DAY) AS ready, TIMESTAMPDIFF(DAY, deactivated_at, NOW()) AS inactive_days " +
  "FROM gangnamunni_admins WHERE id = ? OR (role = 'super_admin' AND is_active = 1) FOR UPDATE";

const ROLE_LABELS: Record<string, string> = { super_admin: '최고관리자', hospital_admin: '병원 관리자' };

/**
 * Writes to gangnamunni_admins through the separate write pool: add, edit (name, phone, notifications, role, hospital),
 * active state and delete. Every write is one short transaction that first locks the row and the active super admins.
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
      // A unique key (login name / email) was taken by a request that ran at the same moment.
      if (err?.code === 'ER_DUP_ENTRY' || err?.errno === 1062) throw new ConflictException({ error: 'conflict' });
      // Something still refers to the account (a foreign key).
      if (err?.code === 'ER_ROW_IS_REFERENCED_2' || err?.errno === 1451) throw new ConflictException({ error: 'in_use' });
      throw e;
    }
  }

  /** Field names only: a value (a phone number, a password, a notification list) is never logged. */
  private audit(actor: WriteActor, id: number, action: string, names: string[]): void {
    this.log.log(`admin write: admin=${actor.id} role=${actor.role} target=${id} action=${action} fields=${names.join(',')}`);
  }

  /** Runs `fn` in one transaction on the write pool; rolls back on any error and always releases the connection. */
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

  private async lock(conn: RwConnection, id: number): Promise<{ target: Row | undefined; supers: number[] }> {
    const [rows] = (await conn.query(LOCK_SQL, [DELETE_AFTER_DAYS, id])) as any;
    const list = (Array.isArray(rows) ? rows : []) as Row[];
    return {
      target: list.find((r) => num(r.id) === id),
      supers: list.filter((r) => str(r.role) === 'super_admin' && num(r.is_active) === 1).map((r) => num(r.id)),
    };
  }

  private async hospital(id: number): Promise<{ id: number; name: string } | undefined> {
    const [rows] = (await this.ro.query('SELECT id, name FROM gangnamunni_hospitals WHERE id = ? LIMIT 1', [id])) as any;
    const r = (Array.isArray(rows) ? rows[0] : undefined) as Row | undefined;
    return r ? { id: num(r.id), name: str(r.name) } : undefined;
  }

  private async item(viewer: Viewer, id: number): Promise<AdminItem> {
    const item = await this.reads.detail(viewer, id);
    if (!item) throw new NotFoundException({ error: 'not_found' });
    return item;
  }

  /** Is this email already a login of another account, member or user? The old panel asks all three tables. */
  private async emailTaken(email: string): Promise<boolean> {
    const checks: [string, string][] = [['users', 'email'], ['g5_member', 'mb_email'], ['gangnamunni_admins', 'email']];
    for (const [table, column] of checks) {
      try {
        const [rows] = (await this.ro.query(`SELECT 1 AS hit FROM ${table} WHERE LOWER(${column}) = LOWER(?) LIMIT 1`, [email])) as any;
        if (Array.isArray(rows) && rows.length > 0) return true;
      } catch (e) {
        // A table this site does not have counts as "nobody there"; a refused grant is not guessed around.
        if ((e as { code?: string; errno?: number })?.code === 'ER_NO_SUCH_TABLE' || (e as { errno?: number })?.errno === 1146) continue;
        throw e;
      }
    }
    return false;
  }

  /** Add an admin. Super admin only (the controller checks). */
  async create(actor: WriteActor, viewer: Viewer, body: unknown): Promise<{ item: AdminItem }> {
    const planned = planCreate(body);
    if (!planned.ok) throw new BadRequestException({ error: 'invalid', fields: planned.errors });
    const p = planned.plan;

    const hospital = p.hospitalId === null ? undefined : await this.guarded(() => this.hospital(p.hospitalId as number));
    if (p.hospitalId !== null && !hospital) throw new BadRequestException({ error: 'invalid', fields: { hospitalId: 'not_found' } });
    if (p.email !== '' && (await this.guarded(() => this.emailTaken(p.email)))) throw new ConflictException({ error: 'conflict', field: 'email' });

    // $2y$ is the same algorithm as the $2b$ bcryptjs writes; it is the prefix PHP's password_hash uses.
    const hash = (await bcrypt.hash(p.password, 10)).replace(/^\$2[ab]\$/, '$2y$');
    const id = await this.guarded(() =>
      this.transaction(async (conn) => {
        const [taken] = (await conn.query('SELECT id FROM gangnamunni_admins WHERE username = ? FOR UPDATE', [p.username])) as any;
        if (Array.isArray(taken) && taken.length > 0) throw new ConflictException({ error: 'conflict', field: 'username' });
        const [res] = (await conn.query(
          'INSERT INTO gangnamunni_admins (username, password, name, email, phone, role, hospital_id, hospital_name, parent_admin_id, permissions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [p.username, hash, p.name, p.email, p.phone, p.role, hospital ? hospital.id : null, hospital ? hospital.name : null, actor.id, permissionsJson(p.notifications)],
        )) as any;
        const newId = num(res?.insertId);
        if (newId <= 0) throw new Error('insert gave no id');
        return newId;
      }),
    );
    this.audit(actor, id, 'create', ['username', 'password', 'name', 'email', 'phone', 'role', 'hospital_id', 'permissions']);
    await this.notifyCreated(p.name, p.role, hospital?.name ?? null);
    return { item: await this.item(viewer, id) };
  }

  /** The "new admin" notice for every active admin who receives it. Best effort: the account is already saved, so a failure is only logged. */
  private async notifyCreated(name: string, role: string, hospitalName: string | null): Promise<void> {
    try {
      const [rows] = (await this.ro.query('SELECT id, permissions FROM gangnamunni_admins WHERE is_active = 1', [])) as any;
      const title = `새 관리자 등록: ${name}`;
      const message = `역할: ${ROLE_LABELS[role] ?? role}${hospitalName ? ` / 병원: ${hospitalName}` : ''}`;
      const values = ((Array.isArray(rows) ? rows : []) as Row[])
        .filter((r) => readNotifications(r.permissions).list.includes('admin_created'))
        .map((r) => [num(r.id), 'admin_created', title, message, 'admin_admins.php', 'bi-person-badge']);
      if (values.length === 0) return;
      await this.pool().query('INSERT INTO gangnamunni_notifications (admin_id, type, title, message, link, icon) VALUES ?', [values]);
    } catch (e) {
      this.log.warn(`new-admin notice not saved: ${(e as Error)?.message}`);
    }
  }

  /** Name, phone, notifications (any role) and role / hospital (super admin only). The caller has already checked that the actor may edit `id`. */
  async edit(actor: WriteActor, viewer: Viewer, id: number, body: unknown): Promise<{ item: AdminItem }> {
    const planned = planEdit(body, viewer.superAdmin);
    if (!planned.ok) throw new BadRequestException({ error: 'invalid', fields: planned.errors });
    const { plan } = planned;

    await this.guarded(() =>
      this.transaction(async (conn) => {
        const { target, supers } = await this.lock(conn, id);
        if (!target) throw new NotFoundException({ error: 'not_found' });
        const sets: string[] = [];
        const values: unknown[] = [];
        const names: string[] = [];
        if (plan.name !== undefined) { sets.push('name = ?'); values.push(plan.name); names.push('name'); }
        if (plan.phone !== undefined) { sets.push('phone = ?'); values.push(plan.phone); names.push('phone'); }
        if (plan.notifications !== undefined) { sets.push('permissions = ?'); values.push(permissionsJson(plan.notifications)); names.push('permissions'); }

        if (plan.role !== undefined || plan.hospitalId !== undefined) {
          const currentRole = str(target.role);
          const role = plan.role ?? currentRole;
          if (id === actor.id && role !== currentRole) throw new ForbiddenException({ error: 'self_forbidden' });
          if (role !== currentRole && currentRole === 'super_admin' && num(target.is_active) === 1 && supers.filter((s) => s !== id).length === 0) {
            throw new ConflictException({ error: 'last_super_admin' });
          }
          if (role === 'super_admin') {
            if (plan.hospitalId !== undefined && plan.hospitalId !== null) {
              throw new BadRequestException({ error: 'invalid', fields: { hospitalId: 'not_for_super_admin' } });
            }
            sets.push('role = ?', 'hospital_id = ?', 'hospital_name = ?');
            values.push(role, null, null);
          } else {
            const hid = plan.hospitalId !== undefined ? plan.hospitalId : num(target.hospital_id) > 0 ? num(target.hospital_id) : null;
            if (hid === null) throw new BadRequestException({ error: 'invalid', fields: { hospitalId: 'required' } });
            const hospital = await this.hospital(hid);
            if (!hospital) throw new BadRequestException({ error: 'invalid', fields: { hospitalId: 'not_found' } });
            sets.push('role = ?', 'hospital_id = ?', 'hospital_name = ?');
            values.push(role, hospital.id, hospital.name);
          }
          names.push('role', 'hospital_id', 'hospital_name');
        }

        await conn.query(`UPDATE gangnamunni_admins SET ${sets.join(', ')} WHERE id = ?`, [...values, id]);
        this.audit(actor, id, 'edit', names);
      }),
    );
    return { item: await this.item(viewer, id) };
  }

  /** Super admin only (the controller checks). Sets the exact state asked for. */
  async setActive(actor: WriteActor, viewer: Viewer, id: number, body: unknown): Promise<{ item: AdminItem }> {
    const active = parseActive(body);
    if (active === null) {
      const given = body && typeof body === 'object' ? (body as Record<string, unknown>).active : undefined;
      throw new BadRequestException({ error: 'invalid', fields: { active: given === undefined ? 'required' : 'bad_format' } });
    }
    await this.guarded(() =>
      this.transaction(async (conn) => {
        const { target: row, supers } = await this.lock(conn, id);
        const target = row ? { id, role: str(row.role), isActive: num(row.is_active) === 1 } : undefined;
        const plan = planActive(actor.id, target, supers, active);
        if (!plan.ok) {
          if (plan.error === 'not_found') throw new NotFoundException({ error: 'not_found' });
          if (plan.error === 'self_forbidden') throw new ForbiddenException({ error: 'self_forbidden' });
          throw new ConflictException({ error: 'last_super_admin' });
        }
        // Switching on clears the mark; switching off starts the clock (a repeat keeps the first mark, or starts it when there is none).
        if (active) await conn.query('UPDATE gangnamunni_admins SET is_active = 1, deactivated_at = NULL WHERE id = ?', [id]);
        else if (target?.isActive) await conn.query('UPDATE gangnamunni_admins SET is_active = 0, deactivated_at = NOW() WHERE id = ?', [id]);
        else await conn.query('UPDATE gangnamunni_admins SET is_active = 0, deactivated_at = COALESCE(deactivated_at, NOW()) WHERE id = ?', [id]);
        this.audit(actor, id, active ? 'activate' : 'deactivate', ['is_active', 'deactivated_at']);
      }),
    );
    return { item: await this.item(viewer, id) };
  }

  /**
   * Delete an account for good (super admin only). Only an account that has been switched off for DELETE_AFTER_DAYS days, and only
   * when its login name is typed as confirmation.
   */
  async remove(actor: WriteActor, id: number, body: unknown): Promise<{ ok: true }> {
    const confirm = parseConfirm(body);
    if (confirm === null) throw new BadRequestException({ error: 'invalid', fields: { confirm: 'required' } });
    await this.guarded(() =>
      this.transaction(async (conn) => {
        const { target: row } = await this.lock(conn, id);
        const target = row
          ? {
              id,
              isActive: num(row.is_active) === 1,
              deactivatedAt: row.deactivated_at === null || row.deactivated_at === undefined ? null : str(row.deactivated_at),
              ready: num(row.ready) === 1,
              inactiveDays: row.inactive_days === null || row.inactive_days === undefined ? null : num(row.inactive_days),
            }
          : undefined;
        const plan = planDelete(actor.id, target, DELETE_AFTER_DAYS);
        if (!plan.ok) {
          if (plan.error === 'not_found') throw new NotFoundException({ error: 'not_found' });
          if (plan.error === 'self_forbidden') throw new ForbiddenException({ error: 'self_forbidden' });
          throw new ConflictException(plan.daysLeft ? { error: plan.error, daysLeft: plan.daysLeft } : { error: plan.error });
        }
        if (confirm !== str(row?.username)) throw new BadRequestException({ error: 'invalid', fields: { confirm: 'mismatch' } });
        await conn.query('DELETE FROM gangnamunni_admins WHERE id = ?', [id]);
        this.audit(actor, id, 'delete', ['row']);
      }),
    );
    return { ok: true };
  }
}
