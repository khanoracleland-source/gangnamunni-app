import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db, DB_RW, RwConnection, RwDb, RwDbProvider } from '../db/db.module';
import { Query } from '../hospitals/hospitals.logic';
import { TreatmentsService } from './treatments.service';
import { TreatmentConflict, TreatmentItem, TreatmentsAnswer } from './treatments.logic';
import {
  activeManyQuery, allActiveIdsQuery, activeQuery, categoryExistsQuery, createdByOf, deleteAliasesQuery, deleteAliasQuery, deleteConflictsOfQuery,
  deleteTreatmentsQuery, insertAliasQuery, insertQuery, isFieldError, parseActive, parseAlias, parseBulk, parseOrder, parseTreatmentBody,
  replaceConflictsQueries, sortOrderQuery, splitDeletable, TreatmentDraft, updateQuery,
} from './treatments.write.logic';

export interface WriteActor {
  id: number;
  name: string;
}
export interface ItemAnswer {
  item: TreatmentItem;
  conflicts: TreatmentConflict[];
}

/** Database problems that mean "the write user cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040];
/** A row that another table still points at (packages): MySQL 1451. */
const FK_ROW_REFERENCED = 1451;
const DUPLICATE = 1062;

/**
 * Writes of the treatment master: treatments (whitelisted columns), their aliases and their conflict pairs, through the
 * separate write pool. The current state (existence, hospital assignments, which optional columns exist) is read with the
 * read-only pool from the same statements the page uses, so every answer carries the row as the page will show it.
 */
@Injectable()
export class TreatmentsWriteService {
  private readonly log = new Logger('TreatmentsWrite');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider, private readonly reads: TreatmentsService) {}

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  /** Database errors of the write user -> 503 write_unavailable; a referenced row -> 409 in_use; anything else is left to the 500 handler. */
  private async guarded<T>(fn: () => Promise<T>): Promise<T> {
    try {
      return await fn();
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
        this.log.warn(`write database unavailable: ${err.message}`);
        throw new ServiceUnavailableException({ error: 'write_unavailable' });
      }
      if (err?.errno === FK_ROW_REFERENCED) throw new ConflictException({ error: 'in_use', hospitalCount: 0 });
      if (err?.errno === DUPLICATE) throw new ConflictException({ error: 'duplicate' });
      throw e;
    }
  }

  private async tx(fn: (conn: RwConnection) => Promise<void>): Promise<void> {
    const conn = await this.pool().getConnection();
    try {
      await conn.beginTransaction();
      await fn(conn);
      await conn.commit();
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      throw e;
    } finally {
      conn.release();
    }
  }

  private audit(actor: WriteActor, action: string, ids: number[]): void {
    this.log.log(`treatment write: admin=${actor.id} action=${action} ids=${ids.join(',')}`);
  }

  private fail(r: unknown): never {
    if (isFieldError(r)) throw new BadRequestException(r);
    const e = (r as { error: string }).error;
    if (e === 'invalid_body') throw new BadRequestException({ error: e });
    throw new ServiceUnavailableException({ error: e });
  }

  private item(snap: TreatmentsAnswer, id: number): TreatmentItem {
    const it = snap.items.find((i) => i.id === id);
    if (!it) throw new NotFoundException({ error: 'not_found' });
    return it;
  }

  private async answer(id: number): Promise<ItemAnswer> {
    const snap = await this.reads.list();
    return { item: this.item(snap, id), conflicts: snap.conflicts };
  }

  private async categoryExists(id: number): Promise<boolean> {
    const [rows] = (await this.ro.query(categoryExistsQuery(id).sql, categoryExistsQuery(id).values)) as any;
    return Array.isArray(rows) && rows.length > 0;
  }

  /** The pairs of one treatment with the active treatments are replaced by the picked ones (inactive or unknown picks are dropped, like the PHP page). */
  private async applyConflicts(conn: RwConnection, id: number, picked: number[]): Promise<void> {
    const q = allActiveIdsQuery();
    const [rows] = (await conn.query(q.sql, q.values)) as any;
    const activeIds = (Array.isArray(rows) ? rows : []).map((r: { id: number }) => Number(r.id)).filter((x: number) => x !== id);
    const keep = picked.filter((p) => activeIds.includes(p));
    for (const s of replaceConflictsQueries(id, activeIds, keep)) await conn.query(s.sql, s.values);
  }

  async create(actor: WriteActor, body: unknown): Promise<ItemAnswer> {
    const snap = await this.reads.list();
    const parsed = parseTreatmentBody(body, 'create', snap.schema, null);
    if (!('draft' in parsed)) this.fail(parsed);
    const d = parsed.draft;
    if (!(await this.categoryExists(d.categoryId as number))) throw new BadRequestException({ error: 'invalid_field', field: 'categoryId', reason: 'bad_format' });
    if (d.hashtag === undefined) d.hashtag = `#${(d.name as string).replace(/ /g, '')}`;
    const q = insertQuery(d, createdByOf(actor.name));
    let newId = 0;
    await this.guarded(() =>
      this.tx(async (conn) => {
        const [res] = (await conn.query(q.sql, q.values)) as any;
        newId = Number(res?.insertId || 0);
        if (d.conflictIds && d.conflictIds.length > 0) await this.applyConflicts(conn, newId, d.conflictIds);
      }),
    );
    this.audit(actor, 'create', [newId]);
    return this.answer(newId);
  }

  async update(actor: WriteActor, id: number, body: unknown): Promise<ItemAnswer> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const snap = await this.reads.list();
    const current = this.item(snap, id);
    const parsed = parseTreatmentBody(body, 'patch', snap.schema, current, id);
    if (!('draft' in parsed)) this.fail(parsed);
    const d: TreatmentDraft = parsed.draft;
    if (d.categoryId !== undefined && d.categoryId !== current.categoryId && !(await this.categoryExists(d.categoryId))) {
      throw new BadRequestException({ error: 'invalid_field', field: 'categoryId', reason: 'bad_format' });
    }
    const q = updateQuery(id, d);
    await this.guarded(() =>
      this.tx(async (conn) => {
        if (q) await conn.query(q.sql, q.values);
        if (d.conflictIds !== undefined) await this.applyConflicts(conn, id, d.conflictIds);
      }),
    );
    this.audit(actor, 'update', [id]);
    return this.answer(id);
  }

  async setActive(actor: WriteActor, id: number, body: unknown): Promise<ItemAnswer> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    this.item(await this.reads.list(), id);
    const active = parseActive(body);
    if (typeof active !== 'boolean') this.fail(active);
    const q = activeQuery(id, active);
    await this.guarded(() => this.pool().query(q.sql, q.values));
    this.audit(actor, active ? 'activate' : 'deactivate', [id]);
    return this.answer(id);
  }

  private async deleteRows(conn: RwConnection, ids: number[], conflictsTable: boolean): Promise<void> {
    const qs: Query[] = [deleteAliasesQuery(ids)];
    if (conflictsTable) qs.push(deleteConflictsOfQuery(ids));
    qs.push(deleteTreatmentsQuery(ids));
    for (const s of qs) await conn.query(s.sql, s.values);
  }

  async remove(actor: WriteActor, id: number): Promise<{ ok: true; conflicts: TreatmentConflict[] }> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const snap = await this.reads.list();
    const it = this.item(snap, id);
    if (it.hospitalCount > 0) throw new ConflictException({ error: 'in_use', hospitalCount: it.hospitalCount });
    await this.guarded(() => this.tx((conn) => this.deleteRows(conn, [id], snap.schema.conflicts)));
    this.audit(actor, 'delete', [id]);
    return { ok: true, conflicts: (await this.reads.list()).conflicts };
  }

  async bulk(actor: WriteActor, body: unknown): Promise<{ done: number; skipped: { id: number; reason: 'in_use' | 'not_found' }[]; conflicts: TreatmentConflict[] }> {
    const parsed = parseBulk(body);
    if (!('ids' in parsed)) this.fail(parsed);
    const snap = await this.reads.list();
    const known = new Set(snap.items.map((i) => i.id));
    let done = 0;
    let skipped: { id: number; reason: 'in_use' | 'not_found' }[] = [];
    if (parsed.action === 'delete') {
      const split = splitDeletable(parsed.ids, snap.items);
      skipped = split.skipped;
      if (split.deletable.length > 0) await this.guarded(() => this.tx((conn) => this.deleteRows(conn, split.deletable, snap.schema.conflicts)));
      done = split.deletable.length;
    } else {
      const ids = parsed.ids.filter((x) => known.has(x));
      skipped = parsed.ids.filter((x) => !known.has(x)).map((id) => ({ id, reason: 'not_found' as const }));
      if (ids.length > 0) {
        const q = activeManyQuery(ids, parsed.action === 'activate');
        await this.guarded(() => this.pool().query(q.sql, q.values));
      }
      done = ids.length;
    }
    this.audit(actor, `bulk_${parsed.action}`, parsed.ids);
    return { done, skipped, conflicts: (await this.reads.list()).conflicts };
  }

  async reorder(actor: WriteActor, body: unknown): Promise<{ ok: true }> {
    const ids = parseOrder(body);
    if (!Array.isArray(ids)) this.fail(ids);
    await this.guarded(() =>
      this.tx(async (conn) => {
        for (let i = 0; i < ids.length; i++) {
          const q = sortOrderQuery(ids[i], i + 1);
          await conn.query(q.sql, q.values);
        }
      }),
    );
    this.audit(actor, 'reorder', ids);
    return { ok: true };
  }

  async addAlias(actor: WriteActor, id: number, body: unknown): Promise<ItemAnswer> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const it = this.item(await this.reads.list(), id);
    const parsed = parseAlias(body);
    if (!('alias' in parsed)) this.fail(parsed);
    if (it.aliases.some((a) => a.lang === parsed.lang && a.alias === parsed.alias)) throw new ConflictException({ error: 'duplicate' });
    const q = insertAliasQuery(id, parsed.alias, parsed.lang);
    await this.guarded(() => this.pool().query(q.sql, q.values));
    this.audit(actor, 'alias_add', [id]);
    return this.answer(id);
  }

  async removeAlias(actor: WriteActor, id: number, aliasId: number): Promise<ItemAnswer> {
    if (id <= 0 || aliasId <= 0) throw new NotFoundException({ error: 'not_found' });
    const it = this.item(await this.reads.list(), id);
    if (!it.aliases.some((a) => a.id === aliasId)) throw new NotFoundException({ error: 'not_found' });
    const q = deleteAliasQuery(id, aliasId);
    await this.guarded(() => this.pool().query(q.sql, q.values));
    this.audit(actor, 'alias_delete', [id]);
    return this.answer(id);
  }
}
