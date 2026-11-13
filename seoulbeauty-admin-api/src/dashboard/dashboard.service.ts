import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import {
  bookingsQuery, buildSummary, consultationsQuery, DashboardSummary, doctorsQuery, hospitalsQuery, membersQuery, paymentsQuery, Query, treatmentsQuery,
} from './dashboard.logic';

/** How long one computed summary is reused (all admins share it: the figures are platform-wide). */
export const SUMMARY_CACHE_MS = 10_000;

@Injectable()
export class DashboardService {
  private readonly log = new Logger('Dashboard');
  private cached: { at: number; v: DashboardSummary } | null = null;
  private inflight: Promise<DashboardSummary> | null = null;

  constructor(@Inject(DB) private readonly db: Db) {}

  /** First row of a SELECT. A table that is not installed (e.g. platform payments) counts as "no data", not an error. */
  private async row(q: Query): Promise<Record<string, unknown> | null> {
    try {
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return (Array.isArray(rows) && rows[0]) || null;
    } catch (e) {
      const err = e as { code?: string; errno?: number; message?: string };
      if (err?.code === 'ER_NO_SUCH_TABLE' || err?.errno === 1146) {
        this.log.warn(`table missing, reported as 0: ${err.message}`);
        return null;
      }
      throw e;
    }
  }

  private async compute(): Promise<DashboardSummary> {
    const [members, bookings7, bookings30, payments7, payments30, consultations, hospitals, doctors, treatments] = await Promise.all([
      this.row(membersQuery()),
      this.row(bookingsQuery(7)),
      this.row(bookingsQuery(30)),
      this.row(paymentsQuery(7)),
      this.row(paymentsQuery(30)),
      this.row(consultationsQuery()),
      this.row(hospitalsQuery()),
      this.row(doctorsQuery()),
      this.row(treatmentsQuery()),
    ]);
    return buildSummary({ members, bookings7, bookings30, payments7, payments30, consultations, hospitals, doctors, treatments });
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
