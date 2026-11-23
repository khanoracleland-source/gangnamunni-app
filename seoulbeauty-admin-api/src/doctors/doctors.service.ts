import { Inject, Injectable } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { canSee, Query, Scope, seesNothing } from '../hospitals/hospitals.logic';
import {
  assignedTreatmentsQuery, buildDetail, buildList, countQuery, detailQuery, DoctorDetail, DoctorList, DoctorListQuery, emptyList, hospitalOptionsQuery,
  hospitalTreatmentOptionsQuery, listQuery, orderColumnQuery, statsQuery, toHospitalOption, toTreatmentOption, treatmentNamesQuery,
} from './doctors.logic';
import { DoctorOrderBoard, orderBoardQuery, toOrderBoard } from './doctors.order.logic';

type Row = Record<string, unknown>;

/** Read-only access to the doctors of every hospital the caller may see (read-only pool). */
@Injectable()
export class DoctorsService {
  private orderColumn: boolean | null = null;

  constructor(@Inject(DB) private readonly db: Db) {}

  private async rows(q: Query): Promise<Row[]> {
    const [rows] = (await this.db.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  }

  /** The display order column is added by the old page the first time it is opened; without it the order is by name. A "yes" is remembered. */
  private async hasOrder(): Promise<boolean> {
    if (this.orderColumn) return true;
    try {
      this.orderColumn = (await this.rows(orderColumnQuery())).length > 0;
    } catch {
      this.orderColumn = false;
    }
    return this.orderColumn;
  }

  async list(scope: Scope, q: DoctorListQuery): Promise<DoctorList> {
    if (seesNothing(scope) || (q.hospitalId > 0 && !canSee(scope, q.hospitalId))) return emptyList(scope, q);
    const withOrder = await this.hasOrder();
    const [stats, count, items] = await Promise.all([this.rows(statsQuery(scope)), this.rows(countQuery(scope, q)), this.rows(listQuery(scope, q, withOrder))]);
    const names = items.length ? await this.rows(treatmentNamesQuery(items.map((r) => Number(r.id)))) : [];
    return buildList(scope, q, stats[0], Number(count[0]?.cnt) || 0, items, names);
  }

  /** null = not found or outside the caller's scope (the two are indistinguishable on purpose). */
  async detail(scope: Scope, id: number): Promise<DoctorDetail | null> {
    if (id <= 0 || seesNothing(scope)) return null;
    const doctor = (await this.rows(detailQuery(scope, id, await this.hasOrder())))[0];
    if (!doctor) return null;
    return buildDetail(doctor, await this.rows(assignedTreatmentsQuery(id, Number(doctor.hospital_id) || 0)));
  }

  async hospitals(scope: Scope) {
    if (seesNothing(scope)) return { items: [] };
    return { items: (await this.rows(hospitalOptionsQuery(scope))).map(toHospitalOption) };
  }

  /** Treatments a hospital offers (null = the hospital is outside the caller's scope). */
  async treatmentOptions(scope: Scope, hospitalId: number) {
    if (hospitalId <= 0 || seesNothing(scope) || !canSee(scope, hospitalId)) return null;
    return { items: (await this.rows(hospitalTreatmentOptionsQuery(hospitalId))).map(toTreatmentOption) };
  }

  /** Active doctors of one hospital in the app's order (null = the hospital is outside the caller's scope). */
  async orderBoard(scope: Scope, hospitalId: number): Promise<DoctorOrderBoard | null> {
    if (hospitalId <= 0 || seesNothing(scope) || !canSee(scope, hospitalId)) return null;
    const withOrder = await this.hasOrder();
    return toOrderBoard(hospitalId, withOrder, await this.rows(orderBoardQuery(hospitalId, withOrder)));
  }
}
