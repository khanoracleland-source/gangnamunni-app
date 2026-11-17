import { Inject, Injectable, Logger } from '@nestjs/common';
import { hospitalsDocumentsEnabled } from '../admin-auth/admin.guard';
import { DB, Db } from '../db/db.module';
import { canWriteDocuments } from './hospitals.documents.logic';
import {
  buildDetail, buildStats, canSee, countQuery, detailOptionalQuery, detailQuery, doctorsQuery, emptyList, groupTreatments, HospitalDetail, HospitalList,
  imagesQuery, listQuery, ListQuery, num, Query, revisionsQuery, Scope, seesNothing, statsQuery, toListItem, TreatmentGroup, treatmentOptionsQuery,
} from './hospitals.logic';

type Row = Record<string, unknown>;

/** Read-only access to gangnamunni_hospitals and its satellites, always limited to the caller's scope. */
@Injectable()
export class HospitalsService {
  private readonly log = new Logger('Hospitals');

  constructor(@Inject(DB) private readonly db: Db) {}

  /**
   * Rows of a SELECT. With `optional` a table or column that is not installed, or not granted to the read-only user,
   * reads as "no rows" and is logged: those parts of the page stay empty instead of failing it. Every other error,
   * and every error of a required query, is thrown.
   */
  private async rows(q: Query, optional = false): Promise<Row[]> {
    try {
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return Array.isArray(rows) ? rows : [];
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      const tolerated =
        optional &&
        (['ER_NO_SUCH_TABLE', 'ER_BAD_FIELD_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR'].includes(err?.code || '') ||
          [1146, 1054, 1142, 1143].includes(err?.errno || 0));
      if (!tolerated) throw e;
      this.log.warn(`optional table/column missing or not granted, reported as empty: ${err.message}`);
      return [];
    }
  }

  async list(scope: Scope, q: ListQuery): Promise<HospitalList> {
    if (seesNothing(scope)) return emptyList(scope, q);
    const [stats, count, items] = await Promise.all([this.rows(statsQuery(scope)), this.rows(countQuery(scope, q)), this.rows(listQuery(scope, q))]);
    return { scope: scope.scope, stats: buildStats(stats[0]), total: num(count[0]?.cnt), page: q.page, perPage: q.perPage, items: items.map(toListItem) };
  }

  async treatmentOptions(scope: Scope): Promise<{ groups: TreatmentGroup[] }> {
    if (seesNothing(scope)) return { groups: [] };
    return { groups: groupTreatments(await this.rows(treatmentOptionsQuery(scope), true)) };
  }

  /** null = not found, not approved, or outside the caller's scope (the three are indistinguishable on purpose). */
  async detail(scope: Scope, id: number, role = ''): Promise<HospitalDetail | null> {
    if (id <= 0 || seesNothing(scope) || !canSee(scope, id)) return null;
    const hospital = (await this.rows(detailQuery(scope, id)))[0];
    if (!hospital) return null;
    const [optional, images, doctors, revisions] = await Promise.all([
      this.rows(detailOptionalQuery(id), true),
      this.rows(imagesQuery(id), true),
      this.rows(doctorsQuery(id), true),
      num(hospital.has_unreviewed_changes) === 1 ? this.rows(revisionsQuery(id), true) : Promise.resolve([]),
    ]);
    return buildDetail({ hospital, optional: optional[0], images, doctors, revisions, documentsEditable: hospitalsDocumentsEnabled() && canWriteDocuments(role, scope, id) });
  }
}
