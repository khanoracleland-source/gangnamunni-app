import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB_RW, RwDbProvider } from '../db/db.module';
import { TreatmentCategory, TreatmentConflict, TableMode } from './treatments.logic';
import { TreatmentsService } from './treatments.service';
import { TreatmentsRwBase, WriteActor } from './treatments.rw';
import {
  categoryActiveQuery, categorySortQuery, deleteConflictQuery, deleteEmptyCategoryQuery, fallbackSlug, insertCategoryQuery, insertConflictQuery,
  insertSettingQuery, parseCategoryBody, parseCategoryOrder, parsePair, parseTableModeBody, settingExistsQuery, slugFromName, updateCategoryQuery,
  updateSettingQuery,
} from './treatments.master.logic';
import { parseActive } from './treatments.write.logic';

export interface CategoryAnswer {
  category: TreatmentCategory;
  categories: TreatmentCategory[];
}

/** Writes of the categories, the home table mode and the pairs of treatments that cannot be booked together. */
@Injectable()
export class TreatmentsMasterService extends TreatmentsRwBase {
  protected readonly log = new Logger('TreatmentsMaster');

  constructor(@Inject(DB_RW) protected readonly rw: RwDbProvider, private readonly reads: TreatmentsService) {
    super();
  }

  private async categoryAnswer(id: number): Promise<CategoryAnswer> {
    const snap = await this.reads.list();
    const category = snap.categories.find((c) => c.id === id);
    if (!category) throw new NotFoundException({ error: 'not_found' });
    return { category, categories: snap.categories };
  }

  private async existing(id: number): Promise<TreatmentCategory> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const c = (await this.reads.list()).categories.find((x) => x.id === id);
    if (!c) throw new NotFoundException({ error: 'not_found' });
    return c;
  }

  async createCategory(actor: WriteActor, body: unknown): Promise<CategoryAnswer> {
    const parsed = parseCategoryBody(body, 'create');
    if (!('draft' in parsed)) this.fail(parsed);
    const d = parsed.draft;
    const slug = slugFromName(d.name as string) || fallbackSlug(Date.now());
    const q = insertCategoryQuery(d.name as string, slug, d.icon as string, d.sortOrder as number);
    let id = 0;
    await this.guarded(async () => {
      const [res] = (await this.pool().query(q.sql, q.values)) as any;
      id = Number(res?.insertId || 0);
    });
    this.audit(actor, 'category_create', [id]);
    return this.categoryAnswer(id);
  }

  async updateCategory(actor: WriteActor, id: number, body: unknown): Promise<CategoryAnswer> {
    const current = await this.existing(id);
    const parsed = parseCategoryBody(body, 'patch');
    if (!('draft' in parsed)) this.fail(parsed);
    const d: { name?: string; icon?: string; sortOrder?: number; slug?: string } = { ...parsed.draft };
    // The slug follows the name like on the PHP page, but a name with no ASCII letters keeps the slug it has.
    if (d.name !== undefined && d.name !== current.name) d.slug = slugFromName(d.name) || current.slug || fallbackSlug(Date.now());
    const q = updateCategoryQuery(id, d);
    if (q) await this.guarded(() => this.pool().query(q.sql, q.values));
    this.audit(actor, 'category_update', [id]);
    return this.categoryAnswer(id);
  }

  async setCategoryActive(actor: WriteActor, id: number, body: unknown): Promise<CategoryAnswer> {
    await this.existing(id);
    const active = parseActive(body);
    if (typeof active !== 'boolean') this.fail(active);
    const q = categoryActiveQuery(id, active);
    await this.guarded(() => this.pool().query(q.sql, q.values));
    this.audit(actor, active ? 'category_activate' : 'category_deactivate', [id]);
    return this.categoryAnswer(id);
  }

  async removeCategory(actor: WriteActor, id: number): Promise<{ ok: true; categories: TreatmentCategory[] }> {
    const current = await this.existing(id);
    if (current.treatmentCount > 0) throw new ConflictException({ error: 'in_use', count: current.treatmentCount });
    const q = deleteEmptyCategoryQuery(id);
    let affected = 0;
    await this.guarded(async () => {
      const [res] = (await this.pool().query(q.sql, q.values)) as any;
      affected = Number(res?.affectedRows || 0);
    });
    // Nothing deleted although the category existed a moment ago: a treatment was added in between.
    if (affected === 0) throw new ConflictException({ error: 'in_use', count: Math.max(current.treatmentCount, 1) });
    this.audit(actor, 'category_delete', [id]);
    return { ok: true, categories: (await this.reads.list()).categories };
  }

  async reorderCategories(actor: WriteActor, body: unknown): Promise<{ categories: TreatmentCategory[] }> {
    const ids = parseCategoryOrder(body);
    if (!Array.isArray(ids)) this.fail(ids);
    await this.guarded(() =>
      this.tx(async (conn) => {
        for (let i = 0; i < ids.length; i++) {
          const q = categorySortQuery(ids[i], i + 1);
          await conn.query(q.sql, q.values);
        }
      }),
    );
    this.audit(actor, 'category_reorder', ids);
    return { categories: (await this.reads.list()).categories };
  }

  async setTableMode(actor: WriteActor, body: unknown): Promise<{ tableMode: TableMode }> {
    const mode = parseTableModeBody(body);
    if (typeof mode !== 'string') this.fail(mode);
    await this.guarded(() =>
      this.tx(async (conn) => {
        const e = settingExistsQuery();
        const [rows] = (await conn.query(e.sql, e.values)) as any;
        const q = Array.isArray(rows) && rows.length > 0 ? updateSettingQuery(mode) : insertSettingQuery(mode);
        await conn.query(q.sql, q.values);
      }),
    );
    this.audit(actor, 'table_mode', []);
    return { tableMode: mode };
  }

  async addConflict(actor: WriteActor, body: unknown): Promise<{ conflicts: TreatmentConflict[]; existed: boolean }> {
    const pair = parsePair(body);
    if (!('a' in pair)) this.fail(pair);
    const snap = await this.reads.list();
    if (!snap.schema.conflicts) throw new ServiceUnavailableException({ error: 'conflicts_unavailable' });
    const known = new Set(snap.items.map((i) => i.id));
    if (!known.has(pair.a)) throw new BadRequestException({ error: 'invalid_field', field: 'a', reason: 'bad_format' });
    if (!known.has(pair.b)) throw new BadRequestException({ error: 'invalid_field', field: 'b', reason: 'bad_format' });
    const lo = Math.min(pair.a, pair.b);
    const hi = Math.max(pair.a, pair.b);
    const existed = snap.conflicts.some((c) => c.a === lo && c.b === hi);
    if (!existed) {
      const q = insertConflictQuery(pair.a, pair.b);
      await this.guarded(() => this.pool().query(q.sql, q.values));
    }
    this.audit(actor, 'conflict_add', [lo, hi]);
    return { conflicts: (await this.reads.list()).conflicts, existed };
  }

  async removeConflict(actor: WriteActor, id: number): Promise<{ conflicts: TreatmentConflict[] }> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const snap = await this.reads.list();
    if (!snap.schema.conflicts) throw new ServiceUnavailableException({ error: 'conflicts_unavailable' });
    if (!snap.conflicts.some((c) => c.id === id)) throw new NotFoundException({ error: 'not_found' });
    const q = deleteConflictQuery(id);
    await this.guarded(() => this.pool().query(q.sql, q.values));
    this.audit(actor, 'conflict_delete', [id]);
    return { conflicts: (await this.reads.list()).conflicts };
  }
}
