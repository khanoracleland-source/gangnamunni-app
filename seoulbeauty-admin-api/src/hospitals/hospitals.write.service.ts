import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { canSee, Scope, seesNothing } from './hospitals.logic';
import { parseExpectedUpdatedAt, phoneTaken, planPatch } from './hospitals.write.logic';

type Row = Record<string, unknown>;
export interface WriteActor {
  id: number;
  role: string;
}

/** Database problems that mean "the write user cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040];

/**
 * Writes to gangnamunni_hospitals (the whitelisted columns) and gangnamunni_hospital_revisions.reviewed, through the
 * separate write pool. Reads (existence, duplicate phone) use the read-only pool. Every write is one short statement
 * (the review is one short transaction); no locks are taken.
 */
@Injectable()
export class HospitalsWriteService {
  private readonly log = new Logger('HospitalsWrite');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider) {}

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  /** Database errors of the write user -> 503 write_unavailable; anything else is left to the 500 handler. */
  private async guarded<T>(fn: () => Promise<T>): Promise<T> {
    try {
      return await fn();
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
        this.log.warn(`write database unavailable: ${err.message}`);
        throw new ServiceUnavailableException({ error: 'write_unavailable' });
      }
      throw e;
    }
  }

  /** updated_at of an approved hospital, or undefined when there is no such approved hospital. */
  private async approvedUpdatedAt(id: number): Promise<{ updatedAt: string | null } | undefined> {
    const [rows] = (await this.ro.query(`SELECT id, updated_at FROM gangnamunni_hospitals WHERE id = ? AND status = 'approved'`, [id])) as any;
    const r = (Array.isArray(rows) ? rows[0] : undefined) as Row | undefined;
    return r ? { updatedAt: r.updated_at == null ? null : String(r.updated_at) } : undefined;
  }

  private async currentUpdatedAt(pool: RwDb, id: number): Promise<string | null | undefined> {
    const [rows] = (await pool.query('SELECT id, updated_at FROM gangnamunni_hospitals WHERE id = ?', [id])) as any;
    const r = (Array.isArray(rows) ? rows[0] : undefined) as Row | undefined;
    return r ? (r.updated_at == null ? null : String(r.updated_at)) : undefined;
  }

  private audit(actor: WriteActor, hospitalId: number, action: string, names: string[]): void {
    this.log.log(`hospital write: admin=${actor.id} role=${actor.role} hospital=${hospitalId} action=${action} fields=${names.join(',')}`);
  }

  async update(actor: WriteActor, scope: Scope, id: number, body: unknown): Promise<{ updatedAt: string }> {
    if (id <= 0 || seesNothing(scope) || !canSee(scope, id)) throw new NotFoundException({ error: 'not_found' });
    const current = await this.approvedUpdatedAt(id);
    if (!current) throw new NotFoundException({ error: 'not_found' });

    const b = body && typeof body === 'object' && !Array.isArray(body) ? (body as Record<string, unknown>) : {};
    const expected = 'expectedUpdatedAt' in b ? parseExpectedUpdatedAt(b.expectedUpdatedAt) : undefined;
    const planned = planPatch(b.fields);
    const errors: Record<string, string> = planned.ok ? {} : { ...planned.errors };
    if (expected === undefined) errors.expectedUpdatedAt = 'expectedUpdatedAt' in b ? 'bad_format' : 'required';
    if (planned.ok && planned.plan.phone !== null && Object.keys(errors).length === 0) {
      const [rows] = (await this.ro.query(`SELECT id, phone FROM gangnamunni_hospitals WHERE phone IS NOT NULL AND phone <> '' AND id <> ?`, [id])) as any;
      if (phoneTaken(planned.plan.phone, Array.isArray(rows) ? rows : [])) errors.phone = 'duplicate_phone';
    }
    if (!planned.ok || Object.keys(errors).length > 0) throw new BadRequestException({ error: 'invalid', fields: errors });
    if (expected !== current.updatedAt) throw new ConflictException({ error: 'conflict', updatedAt: current.updatedAt });

    const { sets, names } = planned.plan;
    const sql = `UPDATE gangnamunni_hospitals SET ${sets.map((s) => `${s.column} = ?`).join(', ')}, updated_at = NOW() WHERE id = ? AND updated_at <=> ?`;
    const values = [...sets.map((s) => s.value), id, expected];
    return this.guarded(async () => {
      const pool = this.pool();
      const [res] = (await pool.query(sql, values)) as any;
      if (!res || res.affectedRows !== 1) {
        const now = await this.currentUpdatedAt(pool, id);
        if (now === undefined) throw new NotFoundException({ error: 'not_found' });
        throw new ConflictException({ error: 'conflict', updatedAt: now });
      }
      this.audit(actor, id, 'update', names);
      const updatedAt = await this.currentUpdatedAt(pool, id);
      return { updatedAt: updatedAt ?? '' };
    });
  }

  async setActive(actor: WriteActor, id: number, body: unknown): Promise<{ isActive: boolean; updatedAt: string }> {
    if (id <= 0 || !(await this.approvedUpdatedAt(id))) throw new NotFoundException({ error: 'not_found' });
    const active = body && typeof body === 'object' ? (body as Record<string, unknown>).active : undefined;
    if (typeof active !== 'boolean') {
      throw new BadRequestException({ error: 'invalid', fields: { active: active === undefined ? 'required' : 'bad_format' } });
    }
    return this.guarded(async () => {
      const pool = this.pool();
      await pool.query('UPDATE gangnamunni_hospitals SET is_active = ?, updated_at = NOW() WHERE id = ?', [active ? 1 : 0, id]);
      this.audit(actor, id, 'active', ['is_active']);
      return { isActive: active, updatedAt: (await this.currentUpdatedAt(pool, id)) ?? '' };
    });
  }

  async markReviewed(actor: WriteActor, id: number): Promise<{ ok: true }> {
    if (id <= 0 || !(await this.approvedUpdatedAt(id))) throw new NotFoundException({ error: 'not_found' });
    return this.guarded(async () => {
      const conn = await this.pool().getConnection();
      try {
        await conn.beginTransaction();
        await conn.query('UPDATE gangnamunni_hospital_revisions SET reviewed = 1 WHERE hospital_id = ? AND reviewed = 0', [id]);
        await conn.query('UPDATE gangnamunni_hospitals SET has_unreviewed_changes = 0 WHERE id = ?', [id]);
        await conn.commit();
      } catch (e) {
        await conn.rollback().catch(() => undefined);
        throw e;
      } finally {
        conn.release();
      }
      this.audit(actor, id, 'review', ['reviewed', 'has_unreviewed_changes']);
      return { ok: true as const };
    });
  }
}
