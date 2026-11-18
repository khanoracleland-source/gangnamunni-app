import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { Query } from '../hospitals/hospitals.logic';
import { isMissingObject } from '../hospitals/hospitals.treatments.logic';
import { aliasesQuery, buildAnswer, categoriesQuery, conflictsQuery, defaultImagesQuery, revisitQuery, Row, tableModeQuery, treatmentsQuery, TreatmentsAnswer } from './treatments.logic';

/**
 * The treatment master (every treatment, category, alias and conflict pair, plus the home table mode) in one answer, read-only.
 * The three required statements fail the request (500) when they cannot run; the optional columns / table of the PHP migrations
 * (default image, revisit interval, conflicts) and the settings row are each read by their own statement, and a missing
 * table, column or grant there is logged and reported through the schema flags instead of failing the page.
 */
@Injectable()
export class TreatmentsService {
  private readonly log = new Logger('Treatments');

  constructor(@Inject(DB) private readonly db: Db) {}

  private async run(q: Query): Promise<Row[]> {
    const [rows] = (await this.db.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  }

  /** The rows, or null when the table / column is not there (or not readable); any other error is passed on. */
  private async optional(q: Query, what: string): Promise<Row[] | null> {
    try {
      return await this.run(q);
    } catch (e) {
      if (!isMissingObject(e)) throw e;
      this.log.warn(`${what} unavailable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
      return null;
    }
  }

  async list(): Promise<TreatmentsAnswer> {
    const [categories, treatments, aliases] = await Promise.all([this.run(categoriesQuery()), this.run(treatmentsQuery()), this.run(aliasesQuery())]);
    const [defaultImages, revisit, conflicts, settings] = await Promise.all([
      this.optional(defaultImagesQuery(), 'default image column'),
      this.optional(revisitQuery(), 'revisit columns'),
      this.optional(conflictsQuery(), 'conflicts table'),
      this.optional(tableModeQuery(), 'table mode setting'),
    ]);
    return buildAnswer({ categories, treatments, aliases, defaultImages, revisit, conflicts, tableMode: settings?.[0]?.setting_value ?? null });
  }
}
