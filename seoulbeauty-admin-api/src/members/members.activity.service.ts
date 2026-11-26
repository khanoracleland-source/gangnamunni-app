import { Inject, Injectable } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { Query } from '../hospitals/hospitals.logic';
import {
  bookingCountQuery, bookingListQuery, buildBookings, buildConsultations, buildHistory, buildPoints, consultationCountQuery, consultationListQuery,
  historyCountQuery, historyListQuery, Paging, pointBalanceQuery, pointCountQuery, pointListQuery, pointTotalsQuery,
} from './members.activity.logic';

type Row = Record<string, unknown>;

/** Read-only member activity (read-only pool). Every method returns null when the member does not exist. */
@Injectable()
export class MembersActivityService {
  constructor(@Inject(DB) private readonly db: Db) {}

  private async rows(q: Query): Promise<Row[]> {
    const [rows] = (await this.db.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  }
  private async count(q: Query): Promise<number> { return Number((await this.rows(q))[0]?.cnt) || 0; }

  private async member(id: number): Promise<Row | null> {
    if (id <= 0) return null;
    return (await this.rows({ sql: 'SELECT mb_no, mb_id, mb_hp, mb_point FROM g5_member WHERE mb_no = ? LIMIT 1', values: [id] }))[0] || null;
  }

  async consultations(id: number, p: Paging) {
    const m = await this.member(id);
    if (!m) return null;
    const run = async (withMbId: boolean) => {
      const [total, rows] = await Promise.all([
        this.count(consultationCountQuery(String(m.mb_id), m.mb_hp, withMbId)), this.rows(consultationListQuery(String(m.mb_id), m.mb_hp, p, withMbId))]);
      return buildConsultations(p, total, rows);
    };
    try { return await run(true); } catch (e) {
      // No mb_id column, or no right to read it: fall back to the phone number alone.
      const code = (e as { code?: string }).code;
      if (code === 'ER_BAD_FIELD_ERROR' || code === 'ER_COLUMNACCESS_DENIED_ERROR') return run(false);
      throw e;
    }
  }

  async bookings(id: number, p: Paging) {
    const m = await this.member(id);
    if (!m) return null;
    const [total, rows] = await Promise.all([this.count(bookingCountQuery(String(m.mb_id))), this.rows(bookingListQuery(String(m.mb_id), p))]);
    return buildBookings(p, total, rows);
  }

  async points(id: number, p: Paging) {
    const m = await this.member(id);
    if (!m) return null;
    const no = Number(m.mb_no);
    const [total, bal, totals, rows] = await Promise.all([
      this.count(pointCountQuery(no)), this.rows(pointBalanceQuery(no)), this.rows(pointTotalsQuery(no)), this.rows(pointListQuery(no, p))]);
    return buildPoints(p, total, bal, totals, rows, m.mb_point);
  }

  async history(id: number, p: Paging) {
    const m = await this.member(id);
    if (!m) return null;
    const no = Number(m.mb_no);
    const [total, rows] = await Promise.all([this.count(historyCountQuery(no)), this.rows(historyListQuery(no, p))]);
    return buildHistory(p, total, rows);
  }
}
