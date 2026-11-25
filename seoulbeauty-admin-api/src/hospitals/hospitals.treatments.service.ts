import { BadRequestException, ForbiddenException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { hospitalsTreatmentsImagesEnabled, hospitalsTreatmentsPricesEnabled, hospitalsTreatmentsQuestionsEnabled, hospitalsTreatmentsRemoveEnabled, HOSPITAL_ROLES } from '../admin-auth/admin.guard';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { canSee, num, Query, Scope, seesNothing, str } from './hospitals.logic';
import { lockHospitalQuery } from './hospitals.images.logic';
import {
  activeMasterQuery, assignedQuery, attachQuestions, deleteQuestionsQuery, insertQuestionQuery, parseQuestionsBody, questionTextsQuery, availableQuery, buildAvailable, buildItems, buildBodyParts, bodyPartsQuery, BodyPartGroupItem, buildUnits, canManageTreatments, clearPriceSummaryQuery, DEFAULT_PRICE_UNITS, insertPriceQuery, parsePricesBody, sortUnits, unitsQuery, DEFAULT_MGMT_MODE, deleteImagesQuery, deletePriceOptionsQuery,
  existingAssignmentsQuery, hospitalExistsQuery, imageCountsQuery, insertAssignmentQuery, isMinAgeProblem, isMissingObject, lockAssignmentQuery, mgmtModeQuery,
  parseAssignBody, parsePatchBody, priceOptionsQuery, questionCountsQuery, reactivateQuery, removeAssignmentQuery, setMinAgeQuery, setPromotedQuery, TreatmentItem, TreatmentsView,
} from './hospitals.treatments.logic';
import { rethrowIfWriteUnavailable } from './hospitals.upload.shared';

type Row = Record<string, unknown>;
type Run = (q: Query) => Promise<Row[]>;
type Exec = (q: Query) => Promise<{ affectedRows?: number; insertId?: number }>;

export interface Actor {
  id: number;
  role: string;
}
type ListAnswer = Pick<TreatmentsView, 'items' | 'count' | 'promotedCount' | 'available'>;
const isNoSuchTable = (e: unknown): boolean => (e as { errno?: number })?.errno === 1146 || (e as { code?: string })?.code === 'ER_NO_SUCH_TABLE';

/** Treatments assigned to one hospital: list, assign, promotion, minimum age and (behind its own switch) removal. Reads use the read-only pool, writes one transaction of the write pool. */
@Injectable()
export class HospitalsTreatmentsService {
  private readonly log = new Logger('HospitalsTreatments');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider) {}

  private readonly roRun: Run = async (q) => {
    const [rows] = (await this.ro.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  };

  /** A hospital inside the scope (and, for adding, an approved one)? */
  private async visible(scope: Scope, id: number, approvedOnly: boolean): Promise<boolean> {
    if (id <= 0 || seesNothing(scope) || !canSee(scope, id)) return false;
    return (await this.roRun(hospitalExistsQuery(id, approvedOnly))).length > 0;
  }

  /** A super admin always may; a hospital account depends on the PHP setting. If the setting cannot be read, a hospital account may not (fail closed). */
  async canManage(role: string): Promise<boolean> {
    if (canManageTreatments(role, '')) return true;
    let mode = DEFAULT_MGMT_MODE;
    try {
      const rows = await this.roRun(mgmtModeQuery());
      if (rows.length > 0) mode = str(rows[0].value);
    } catch (e) {
      this.log.warn(`could not read the treatment management setting: ${(e as { code?: string; message?: string })?.code || (e as Error)?.message}`);
      return HOSPITAL_ROLES.includes(role) ? false : canManageTreatments(role, DEFAULT_MGMT_MODE);
    }
    return canManageTreatments(role, mode);
  }

  /** An optional table or column that is missing (or not readable) leaves that part empty instead of failing the page. */
  private async optional(q: Query, what: string): Promise<Row[]> {
    try {
      return await this.roRun(q);
    } catch (e) {
      if (!isMissingObject(e)) throw e;
      this.log.warn(`${what} unavailable: ${(e as { code?: string; message?: string })?.code || (e as Error)?.message}`);
      return [];
    }
  }

  /** Items of a hospital with their optional parts; `minAgeSupported` is false when the min_age column is missing or not readable. */
  private async readItems(hospitalId: number, withQuestions = false): Promise<{ items: TreatmentItem[]; minAgeSupported: boolean; questionsReadable: boolean }> {
    let rows: Row[];
    let minAgeSupported = true;
    try {
      rows = await this.roRun(assignedQuery(hospitalId, true));
    } catch (e) {
      if (!isMinAgeProblem(e)) throw e;
      this.log.warn(`min_age unavailable: ${(e as { code?: string; message?: string })?.code}`);
      minAgeSupported = false;
      rows = await this.roRun(assignedQuery(hospitalId, false));
    }
    const ids = rows.map((r) => num(r.id)).filter((n) => n > 0);
    if (ids.length === 0) return { items: [], minAgeSupported, questionsReadable: true };
    const [prices, images, questions] = await Promise.all([
      this.optional(priceOptionsQuery(ids), 'price options'),
      this.optional(imageCountsQuery(ids), 'treatment images'),
      this.optional(questionCountsQuery(ids), 'treatment questions'),
    ]);
    const items = buildItems({ rows, prices, images, questions });
    if (!withQuestions) return { items, minAgeSupported, questionsReadable: true };
    // the texts are only attached when they could really be read: showing an empty list for questions that exist would let a save wipe them
    try {
      return { items: attachQuestions(items, await this.roRun(questionTextsQuery(ids))), minAgeSupported, questionsReadable: true };
    } catch (e) {
      if (!isMissingObject(e)) throw e;
      this.log.warn(`treatment question texts unavailable: ${(e as { code?: string; message?: string })?.code || (e as Error)?.message}`);
      return { items, minAgeSupported, questionsReadable: false };
    }
  }

  private async view(hospitalId: number, canManage: boolean): Promise<TreatmentsView> {
    const questionsOn = canManage && hospitalsTreatmentsQuestionsEnabled();
    const { items, minAgeSupported, questionsReadable } = await this.readItems(hospitalId, questionsOn);
    const pricesEnabled = hospitalsTreatmentsPricesEnabled();
    const available = canManage ? buildAvailable(await this.roRun(availableQuery(hospitalId))) : [];
    return {
      items, count: items.length, promotedCount: items.filter((i) => i.isPromoted).length,
      canManage, minAgeSupported, removeEnabled: hospitalsTreatmentsRemoveEnabled(),
      pricesEnabled, imagesEnabled: hospitalsTreatmentsImagesEnabled(), questionsEnabled: hospitalsTreatmentsQuestionsEnabled() && (!canManage || questionsReadable), priceUnits: canManage && pricesEnabled ? await this.units() : [], priceBodyParts: canManage && pricesEnabled ? await this.bodyParts() : [], available,
    };
  }

  /** Units of the price form; a missing table or any read problem gives the standard list (the form must stay usable). */
  private async units(): Promise<string[]> {
    try {
      return buildUnits(await this.roRun(unitsQuery()));
    } catch (e) {
      this.log.warn(`units unavailable, using the standard list: ${(e as { code?: string; message?: string })?.code || (e as Error)?.message}`);
      return sortUnits(DEFAULT_PRICE_UNITS);
    }
  }

  /** Body part groups of the price form; a missing table or any read problem gives an empty list (the form then uses its built-in list). */
  private async bodyParts(): Promise<BodyPartGroupItem[]> {
    try {
      return buildBodyParts(await this.roRun(bodyPartsQuery()));
    } catch (e) {
      this.log.warn(`body parts unavailable, the form uses its own list: ${(e as { code?: string; message?: string })?.code || (e as Error)?.message}`);
      return [];
    }
  }

  private listAnswer(v: TreatmentsView): ListAnswer {
    return { items: v.items, count: v.count, promotedCount: v.promotedCount, available: v.available };
  }

  async list(scope: Scope, actor: Actor, id: number): Promise<TreatmentsView> {
    if (!(await this.visible(scope, id, false))) throw new NotFoundException({ error: 'not_found' });
    return this.view(id, await this.canManage(actor.role));
  }

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  /** One transaction of the write pool: commit when `fn` returns, full rollback when it throws. */
  private async tx<T>(fn: (run: Run, exec: Exec) => Promise<T>): Promise<T> {
    const pool = this.pool();
    let conn;
    try {
      conn = await pool.getConnection();
    } catch (e) {
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    }
    try {
      await conn.beginTransaction();
      const run: Run = async (q) => {
        const [rows] = (await conn.query(q.sql, q.values)) as any;
        return Array.isArray(rows) ? rows : [];
      };
      const exec: Exec = async (q) => ((await conn.query(q.sql, q.values)) as any)[0] ?? {};
      const out = await fn(run, exec);
      await conn.commit();
      return out;
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      rethrowIfWriteUnavailable(e, this.log);
      throw e;
    } finally {
      conn.release();
    }
  }

  /** Guard of every write: the hospital is visible and approved (404; a pending / rejected application is never edited here), the actor may manage treatments (403). */
  private async allowWrite(scope: Scope, actor: Actor, id: number, approvedOnly: boolean): Promise<void> {
    if (!(await this.visible(scope, id, approvedOnly))) throw new NotFoundException({ error: 'not_found' });
    if (!(await this.canManage(actor.role))) throw new ForbiddenException({ error: 'forbidden_treatments' });
  }

  async assign(scope: Scope, actor: Actor, id: number, body: unknown): Promise<ListAnswer & { added: number; skipped: number }> {
    await this.allowWrite(scope, actor, id, true);
    const parsed = parseAssignBody(body);
    if (!parsed.ok) throw new BadRequestException(parsed.body);
    const ids = parsed.ids;
    const registeredBy = HOSPITAL_ROLES.includes(actor.role) ? 'hospital' : 'admin';
    const clearLeftovers = hospitalsTreatmentsRemoveEnabled();
    const result = await this.tx(async (run, exec) => {
      // the hospital row is locked (not changed) so two assignments of one hospital never run side by side
      if ((await run(lockHospitalQuery(id))).length === 0) throw new NotFoundException({ error: 'not_found' });
      if ((await run(activeMasterQuery(ids))).length !== ids.length) throw new BadRequestException({ error: 'invalid_treatment' });
      const existing = new Map<number, Row>();
      for (const r of await run(existingAssignmentsQuery(id, ids))) if (!existing.has(num(r.treatment_id))) existing.set(num(r.treatment_id), r);
      let added = 0;
      let skipped = 0;
      for (const tid of ids) {
        const row = existing.get(tid);
        if (!row) {
          await exec(insertAssignmentQuery(id, tid, registeredBy));
          added += 1;
        } else if (num(row.is_active) === 0 || ['inactive', 'rejected'].includes(str(row.status))) {
          const htId = num(row.id);
          // leftover price options / detail images of an old assignment are cleared only where removal is switched on (it holds the DELETE rights)
          if (clearLeftovers) {
            await exec(deletePriceOptionsQuery(htId)).catch((e) => { if (!isMissingObject(e) || (e as { errno?: number }).errno !== 1146) throw e; });
            await exec(deleteImagesQuery(htId)).catch((e) => { if (!isMissingObject(e) || (e as { errno?: number }).errno !== 1146) throw e; });
          }
          await exec(reactivateQuery(id, htId));
          added += 1;
        } else {
          skipped += 1;
        }
      }
      return { added, skipped };
    });
    this.log.log(`hospital treatments: admin=${actor.id} hospital=${id} assign added=${result.added} skipped=${result.skipped}`);
    return { ...result, ...this.listAnswer(await this.view(id, true)) };
  }

  async patch(scope: Scope, actor: Actor, id: number, htId: number, body: unknown): Promise<{ item: TreatmentItem; promotedCount: number }> {
    await this.allowWrite(scope, actor, id, true);
    const parsed = parsePatchBody(body);
    if (!parsed.ok) throw new BadRequestException(parsed.body);
    const patch = parsed.patch;
    await this.tx(async (run, exec) => {
      const rows = htId > 0 ? await run(lockAssignmentQuery(id, htId)) : [];
      if (rows.length === 0 || num(rows[0].is_active) !== 1) throw new NotFoundException({ error: 'not_found' });
      if (patch.isPromoted !== undefined) await exec(setPromotedQuery(id, htId, patch.isPromoted));
      if (patch.minAge !== undefined) {
        try {
          await exec(setMinAgeQuery(id, htId, patch.minAge));
        } catch (e) {
          if (isMinAgeProblem(e)) {
            this.log.warn('min_age column missing or not writable');
            throw new ServiceUnavailableException({ error: 'min_age_unavailable' });
          }
          throw e;
        }
      }
    });
    this.log.log(`hospital treatments: admin=${actor.id} hospital=${id} update treatment=${htId}`);
    const { items } = await this.readItems(id, hospitalsTreatmentsQuestionsEnabled());
    const item = items.find((i) => i.id === htId);
    if (!item) throw new NotFoundException({ error: 'not_found' });
    return { item, promotedCount: items.filter((i) => i.isPromoted).length };
  }

  async remove(scope: Scope, actor: Actor, id: number, htId: number): Promise<ListAnswer & { removed: boolean; deleted: { priceOptions: number; images: number } }> {
    await this.allowWrite(scope, actor, id, true);
    const out = await this.tx(async (run, exec) => {
      const rows = htId > 0 ? await run(lockAssignmentQuery(id, htId)) : [];
      if (rows.length === 0) throw new NotFoundException({ error: 'not_found' });
      if (num(rows[0].is_active) !== 1) return { removed: false, deleted: { priceOptions: 0, images: 0 } };
      // a table that does not exist has nothing to delete; any other problem (for example a missing right) fails the whole request
      const count = async (q: Query): Promise<number> => {
        try {
          return num((await exec(q)).affectedRows);
        } catch (e) {
          if ((e as { errno?: number })?.errno === 1146 || (e as { code?: string })?.code === 'ER_NO_SUCH_TABLE') return 0;
          throw e;
        }
      };
      const priceOptions = await count(deletePriceOptionsQuery(htId));
      const images = await count(deleteImagesQuery(htId));
      await exec(removeAssignmentQuery(id, htId));
      return { removed: true, deleted: { priceOptions, images } };
    });
    this.log.log(`hospital treatments: admin=${actor.id} hospital=${id} remove treatment=${htId} removed=${out.removed}`);
    return { ...out, ...this.listAnswer(await this.view(id, true)) };
  }
  /** Replaces every price option of one assignment (delete + insert in one transaction); the answer is re-read from the read-only pool. */
  async savePrices(scope: Scope, actor: Actor, id: number, htId: number, body: unknown): Promise<{ item: TreatmentItem; count: number; promotedCount: number }> {
    await this.allowWrite(scope, actor, id, true);
    const parsed = parsePricesBody(body);
    if (!parsed.ok) throw new BadRequestException(parsed.body);
    const options = parsed.options;
    const columnsMissing = (e: unknown): boolean => (e as { errno?: number })?.errno === 1054 || (e as { code?: string })?.code === 'ER_BAD_FIELD_ERROR';
    try {
      await this.tx(async (run, exec) => {
        const rows = htId > 0 ? await run(lockAssignmentQuery(id, htId)) : [];
        if (rows.length === 0 || num(rows[0].is_active) !== 1) throw new NotFoundException({ error: 'not_found' });
        await exec(deletePriceOptionsQuery(htId));
        for (let i = 0; i < options.length; i++) await exec(insertPriceQuery(htId, options[i], i));
        await exec(clearPriceSummaryQuery(id, htId));
      });
    } catch (e) {
      if (columnsMissing(e)) {
        this.log.warn(`price option columns missing: ${(e as Error)?.message}`);
        throw new ServiceUnavailableException({ error: 'price_columns_unavailable' });
      }
      throw e;
    }
    this.log.log(`hospital treatments: admin=${actor.id} hospital=${id} prices treatment=${htId} options=${options.length}`);
    const { items } = await this.readItems(id, hospitalsTreatmentsQuestionsEnabled());
    const item = items.find((i) => i.id === htId);
    if (!item) throw new NotFoundException({ error: 'not_found' });
    return { item, count: items.length, promotedCount: items.filter((i) => i.isPromoted).length };
  }
  /**
   * Replaces every "before treatment" question of one assignment (delete + insert in one transaction; the assignment row is locked, not changed).
   * Only the assignment's ownership is checked, not is_active (like the PHP page). The answer is re-read from the read-only pool.
   */
  async saveQuestions(scope: Scope, actor: Actor, id: number, htId: number, body: unknown): Promise<{ item: TreatmentItem; count: number }> {
    await this.allowWrite(scope, actor, id, true);
    const parsed = parseQuestionsBody(body);
    if (!parsed.ok) throw new BadRequestException(parsed.body);
    const questions = parsed.questions;
    // a table that does not exist is its own answer; a missing right stays write_unavailable (handled by the transaction)
    const table = async <T>(fn: () => Promise<T>): Promise<T> => {
      try {
        return await fn();
      } catch (e) {
        if (isNoSuchTable(e)) {
          this.log.warn(`treatment questions table missing: ${(e as Error)?.message}`);
          throw new ServiceUnavailableException({ error: 'questions_table_unavailable' });
        }
        throw e;
      }
    };
    await this.tx(async (run, exec) => {
      const rows = htId > 0 ? await run(lockAssignmentQuery(id, htId)) : [];
      if (rows.length === 0) throw new NotFoundException({ error: 'not_found' });
      await table(() => exec(deleteQuestionsQuery(htId)));
      for (let i = 0; i < questions.length; i++) await table(() => exec(insertQuestionQuery(htId, questions[i], i)));
    });
    this.log.log(`hospital treatments: admin=${actor.id} hospital=${id} questions treatment=${htId} count=${questions.length}`);
    const { items } = await this.readItems(id, true);
    const item = items.find((i) => i.id === htId);
    if (!item) throw new NotFoundException({ error: 'not_found' });
    return { item, count: questions.length };
  }
}
