import { ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException, BadRequestException } from '@nestjs/common';
import { DB_RW, RwDbProvider } from '../db/db.module';
import { TreatmentsService } from '../treatments/treatments.service';
import { TreatmentsRwBase, WriteActor } from '../treatments/treatments.rw';
import { deleteEmptyCategoryQuery } from '../treatments/treatments.master.logic';
import { assignQuery, BoardAnswer, buildBoard, detachAllQuery, detachQuery, parseAssignBody } from './category-board.logic';

/** MySQL 1048: the column does not accept NULL, so "no category" cannot be stored. */
const COLUMN_NOT_NULL = 1048;

/** The home menu categories page: the board read, and the writes that move treatments between categories. */
@Injectable()
export class CategoryBoardService extends TreatmentsRwBase {
  protected readonly log = new Logger('CategoryBoard');

  constructor(@Inject(DB_RW) protected readonly rw: RwDbProvider, private readonly reads: TreatmentsService) {
    super();
  }

  async get(): Promise<BoardAnswer> {
    return buildBoard(await this.reads.list());
  }

  /** Adds existing treatments to a category (a treatment of another category, or of none, is moved); they go to the end of the category's list. */
  async assign(actor: WriteActor, categoryId: number, body: unknown): Promise<BoardAnswer> {
    if (categoryId <= 0) throw new NotFoundException({ error: 'not_found' });
    const ids = parseAssignBody(body);
    if (!Array.isArray(ids)) this.fail(ids);
    const snap = await this.reads.list();
    if (!snap.categories.some((c) => c.id === categoryId)) throw new NotFoundException({ error: 'not_found' });
    const byId = new Map(snap.items.map((i) => [i.id, i]));
    if (ids.some((x) => !byId.has(x))) throw new BadRequestException({ error: 'invalid_field', field: 'treatmentIds', reason: 'bad_format' });
    const moving = ids.filter((x) => byId.get(x)?.categoryId !== categoryId);
    if (moving.length > 0) {
      const last = Math.max(0, ...snap.items.filter((i) => i.categoryId === categoryId).map((i) => i.sortOrder));
      await this.guarded(() =>
        this.tx(async (conn) => {
          for (let i = 0; i < moving.length; i++) {
            const q = assignQuery(moving[i], categoryId, last + i + 1);
            await conn.query(q.sql, q.values);
          }
        }),
      );
      this.audit(actor, 'board_assign', [categoryId, ...moving]);
    }
    return this.get();
  }

  /** Takes a treatment out of a category: it then belongs to none and the app does not show it. */
  async detach(actor: WriteActor, categoryId: number, treatmentId: number): Promise<BoardAnswer> {
    if (categoryId <= 0 || treatmentId <= 0) throw new NotFoundException({ error: 'not_found' });
    const snap = await this.reads.list();
    const item = snap.items.find((i) => i.id === treatmentId);
    if (!item) throw new NotFoundException({ error: 'not_found' });
    if (item.categoryId !== categoryId) throw new ConflictException({ error: 'not_in_category' });
    const q = detachQuery(treatmentId, categoryId);
    let affected = 0;
    await this.guarded(async () => {
      try {
        const [res] = (await this.pool().query(q.sql, q.values)) as any;
        affected = Number(res?.affectedRows || 0);
      } catch (e) {
        if ((e as { errno?: number })?.errno === COLUMN_NOT_NULL) throw new ServiceUnavailableException({ error: 'uncategorized_unsupported' });
        throw e;
      }
    });
    if (affected === 0) throw new ConflictException({ error: 'not_in_category' });
    this.audit(actor, 'board_detach', [categoryId, treatmentId]);
    return this.get();
  }

  /**
   * Deletes a category together with its place in the home menu; its treatments stay and become uncategorized.
   * One transaction: free the treatments, then delete the category with the same "no treatment points at it" check
   * the plain delete uses, so a treatment added in between rolls everything back (409 in_use) instead of being orphaned.
   */
  async removeCategory(actor: WriteActor, categoryId: number): Promise<BoardAnswer & { detached: number }> {
    if (categoryId <= 0) throw new NotFoundException({ error: 'not_found' });
    const snap = await this.reads.list();
    const current = snap.categories.find((c) => c.id === categoryId);
    if (!current) throw new NotFoundException({ error: 'not_found' });
    let detached = 0;
    await this.guarded(() =>
      this.tx(async (conn) => {
        const free = detachAllQuery(categoryId);
        try {
          const [res] = (await conn.query(free.sql, free.values)) as any;
          detached = Number(res?.affectedRows || 0);
        } catch (e) {
          if ((e as { errno?: number })?.errno === COLUMN_NOT_NULL) throw new ServiceUnavailableException({ error: 'uncategorized_unsupported' });
          throw e;
        }
        const del = deleteEmptyCategoryQuery(categoryId);
        const [res] = (await conn.query(del.sql, del.values)) as any;
        if (Number(res?.affectedRows || 0) === 0) throw new ConflictException({ error: 'in_use', count: Math.max(detached, 1) });
      }),
    );
    this.audit(actor, 'board_category_delete', [categoryId]);
    return { ...(await this.get()), detached };
  }
}
