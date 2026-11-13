import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import {
  adminMembersQuery, adminsQuery, bookingsQuery, buildSummary, consultationsQuery, DashboardSummary, doctorsQuery, hospitalsQuery, membersQuery, paymentsQuery, Query, treatmentsQuery,
} from './dashboard.logic';

/** How long one computed summary is reused (all admins share it: the figures are platform-wide). */
export const SUMMARY_CACHE_MS = 10_000;

@Injectable()
export class DashboardService {
  private readonly log = new Logger('Dashboard');
  private cached: { at: number; v: DashboardSummary } | null = null;
  private inflight: Promise<DashboardSummary> | null = null;

  constructor(@Inject(DB) private readonly db: Db) {}

  /**
   * First row of a SELECT. A table that is not installed (e.g. platform payments) counts as "no data", not an error.
   * `lenient` also covers a missing column or a missing grant (optional figures): reported as 0 and logged.
   */
  private async row(q: Query, lenient = false): Promise<Record<string, unknown> | null> {
    try {
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return (Array.isArray(rows) && rows[0]) || null;
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      const missing = err?.code === 'ER_NO_SUCH_TABLE' || err?.errno === 1146;
      const optional =
        lenient &&
        (['ER_BAD_FIELD_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR'].includes(err?.code || '') ||
          [1054, 1142, 1143].includes(err?.errno || 0));
      if (missing || optional) {
        this.log.warn(`table/column missing or not granted, reported as 0: ${err.message}`);
        return null;
      }
      throw e;
    }
  }

  private async compute(): Promise<DashboardSummary> {
    const [members, admins, adminMembers, bookings7, bookings30, payments7, payments30, consultations, hospitals, doctors, treatments] = await Promise.all([
      this.row(membersQuery()),
      this.row(adminsQuery(), true),
      this.row(adminMembersQuery(), true),
      this.row(bookingsQuery(7)),
      this.row(bookingsQuery(30)),
      this.row(paymentsQuery(7)),
      this.row(paymentsQuery(30)),
      this.row(consultationsQuery()),
      this.row(hospitalsQuery()),
      this.row(doctorsQuery()),
      this.row(treatmentsQuery()),
    ]);
    return buildSummary({ members, admins, adminMembers, bookings7, bookings30, payments7, payments30, consultations, hospitals, doctors, treatments });
  }

  async summary(): Promise<DashboardSummary> {
    const now = Date.now();
    if (this.cached && now - this.cached.at < SUMMARY_CACHE_MS) return this.cached.v;
    // Concurrent callers share one round of queries.
    if (!this.inflight) {
      this.inflight = this.compute()
        .then((v) => {
          this.cached = { at: Date.now(), v };
          return v;
        })
        .finally(() => {
          this.inflight = null;
        });
    }
    return this.inflight;
  }
}
