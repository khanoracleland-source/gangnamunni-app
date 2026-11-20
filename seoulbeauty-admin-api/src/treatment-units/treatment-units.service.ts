import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { isMissingObject } from '../hospitals/hospitals.treatments.logic';
import { buildItems, listQuery, UnitsAnswer } from './treatment-units.logic';

/** The active units, read-only. A missing or unreadable table is reported by tableReady=false instead of failing the page. */
@Injectable()
export class TreatmentUnitsService {
  private readonly log = new Logger('TreatmentUnits');

  constructor(@Inject(DB) private readonly db: Db) {}

  async list(): Promise<UnitsAnswer> {
    try {
      const q = listQuery();
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return { tableReady: true, items: buildItems(Array.isArray(rows) ? rows : []) };
    } catch (e) {
      if (!isMissingObject(e)) throw e;
      this.log.warn(`units table unavailable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
      return { tableReady: false, items: [] };
    }
  }
}
