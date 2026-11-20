import { Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB_RW, RwDbProvider } from '../db/db.module';
import { isMissingObject } from '../hospitals/hospitals.treatments.logic';
import { TreatmentsRwBase, WriteActor } from '../treatments/treatments.rw';
import { activateQuery, AddAnswer, deactivateQuery, findByIdQuery, findByNameQuery, insertQuery, parseUnitName, toRecord, UnitRecord } from './treatment-units.logic';
import { Query } from '../hospitals/hospitals.logic';

const DUPLICATE = 1062;

/** Adding and removing units. The removal is a soft delete (is_active = 0); nothing here touches prices. */
@Injectable()
export class TreatmentUnitsWriteService extends TreatmentsRwBase {
  protected readonly log = new Logger('TreatmentUnitsWrite');

  constructor(@Inject(DB_RW) protected readonly rw: RwDbProvider) {
    super();
  }

  protected audit(actor: WriteActor, action: string, ids: number[]): void {
    this.log.log(`treatment unit write: admin=${actor.id} action=${action} ids=${ids.join(',')}`);
  }

  /** The write user's errors as usual (503 write_unavailable); a missing table or column -> 503 table_missing. */
  private async run<T>(fn: () => Promise<T>): Promise<T> {
    try {
      return await this.guarded(fn);
    } catch (e) {
      if (isMissingObject(e)) {
        this.log.warn(`units table unavailable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
        throw new ServiceUnavailableException({ error: 'table_missing' });
      }
      throw e;
    }
  }

  private async find(q: Query): Promise<UnitRecord | null> {
    const [rows] = (await this.pool().query(q.sql, q.values)) as any;
    return toRecord(Array.isArray(rows) ? rows[0] : undefined);
  }

  /**
   * created: new row. restored: the name existed but was removed, it is active again (the stored spelling is kept, the key ignores case).
   * exists: already active, nothing written. A parallel insert of the same name (duplicate key) is read again once.
   */
  async add(actor: WriteActor, body: unknown): Promise<AddAnswer> {
    const parsed = parseUnitName(body);
    if (!('name' in parsed)) this.fail(parsed);
    const name = parsed.name;
    const answer = await this.run<AddAnswer>(async () => {
      for (let attempt = 0; attempt < 2; attempt++) {
        const current = await this.find(findByNameQuery(name));
        if (current) {
          if (current.isActive) return { unit: { id: current.id, name: current.name }, status: 'exists' };
          const q = activateQuery(current.id);
          await this.pool().query(q.sql, q.values);
          return { unit: { id: current.id, name: current.name }, status: 'restored' };
        }
        try {
          const q = insertQuery(name);
          const [res] = (await this.pool().query(q.sql, q.values)) as any;
          return { unit: { id: Number(res?.insertId || 0), name }, status: 'created' };
        } catch (e) {
          if ((e as { errno?: number })?.errno !== DUPLICATE || attempt === 1) throw e;
        }
      }
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    });
    if (answer.status !== 'exists') this.audit(actor, answer.status === 'created' ? 'unit_add' : 'unit_restore', [answer.unit.id]);
    return answer;
  }

  /** Repeating the removal of a removed unit is fine; an id that was never there -> 404. */
  async remove(actor: WriteActor, id: number): Promise<{ ok: true }> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    await this.run(async () => {
      if (!(await this.find(findByIdQuery(id)))) throw new NotFoundException({ error: 'not_found' });
      const q = deactivateQuery(id);
      await this.pool().query(q.sql, q.values);
    });
    this.audit(actor, 'unit_remove', [id]);
    return { ok: true };
  }
}
