import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { DB, Db, DB_RW, RwConnection, RwDbProvider } from '../db/db.module';
import { TreatmentsRwBase, WriteActor } from './treatments.rw';
import { Query } from '../hospitals/hospitals.logic';
import { TreatmentsService } from './treatments.service';
import { TreatmentConflict, TreatmentItem, TreatmentsAnswer } from './treatments.logic';
import {
  activeManyQuery, allActiveIdsQuery, activeQuery, categoryExistsQuery, createdByOf, deleteAliasesQuery, deleteAliasQuery, deleteConflictsOfQuery,
  deleteTreatmentsQuery, insertAliasQuery, insertQuery, isFieldError, parseActive, parseAlias, parseBulk, parseOrder, parseTreatmentBody,
  replaceConflictsQueries, sortOrderQuery, TreatmentDraft, updateQuery,
} from './treatments.write.logic';

export type { WriteActor };
export interface ItemAnswer {
  item: TreatmentItem;
  conflicts: TreatmentConflict[];
}

/**
 * Writes of the treatment master: treatments (whitelisted columns), their aliases and their conflict pairs, through the
 * separate write pool. The current state (existence, hospital assignments, which optional columns exist) is read with the
 * read-only pool from the same statements the page uses, so every answer carries the row as the page will show it.
 */
@Injectable()
export class TreatmentsWriteService extends TreatmentsRwBase {
  protected readonly log = new Logger('TreatmentsWrite');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) protected readonly rw: RwDbProvider, private readonly reads: TreatmentsService) {
    super();
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

  async bulk(actor: WriteActor, body: unknown): Promise<{ done: number; skipped: { id: number; reason: 'not_found' }[]; conflicts: TreatmentConflict[] }> {
    const parsed = parseBulk(body);
    if (!('ids' in parsed)) this.fail(parsed);
    const snap = await this.reads.list();
    const known = new Set(snap.items.map((i) => i.id));
    const ids = parsed.ids.filter((x) => known.has(x));
    const skipped = parsed.ids.filter((x) => !known.has(x)).map((id) => ({ id, reason: 'not_found' as const }));
    if (ids.length > 0) {
      const q = activeManyQuery(ids, parsed.action === 'activate');
      await this.guarded(() => this.pool().query(q.sql, q.values));
    }
    this.audit(actor, `bulk_${parsed.action}`, parsed.ids);
    return { done: ids.length, skipped, conflicts: (await this.reads.list()).conflicts };
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
