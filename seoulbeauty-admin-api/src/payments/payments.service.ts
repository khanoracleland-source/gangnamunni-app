import { BadRequestException, Inject, Injectable, Logger, ServiceUnavailableException } from '@nestjs/common';
import { AdminScope, paymentsFeesEnabled } from '../admin-auth/admin.guard';
import { isFail } from '../bookings/bookings.logic';
import { DB, Db } from '../db/db.module';
import { isMissingObject } from '../hospitals/hospitals.treatments.logic';
import {
  byHospitalQuery, byMethodQuery, orderedHeldQuery, byTreatmentQuery, capped, toFeeSums, weeklyQuery, FeeSums, countQuery, hospitalOptionsQuery, listQuery, monthCompare, parseCompareQuery, parsePaymentQuery, parseRangeQuery, PAY_PER_PAGE, periodsOf,
  seoulToday, seriesQuery, summaryQuery, Sums, toItem, toSums, weekCompare, PaymentItem,
} from './payments.logic';

export interface ListAnswer {
  items: PaymentItem[]; total: number; page: number; perPage: number; totalPages: number; summary: Sums & Partial<FeeSums>; from: string; to: string;
  /** Only with ADMIN_PAYMENTS_FEES=1: per payment method and per payout Sunday (the hospital's week). */
  methods?: (Sums & FeeSums & { method: string })[];
  /** Treatment booked, payment awaited (a current stock, no fee). */
  orderedHeld?: { count: number; amount: number };
  weeks?: { payoutDate: string; payments: number; fee: number; settlement: number }[];
}

const rowsOf = (r: unknown): Record<string, unknown>[] => (Array.isArray(r) ? (r as Record<string, unknown>[]) : []);

/** Reading booking payments (the ledger). Nothing here writes, and no payment gateway is called. Everything is cut to the admin's scope. */
@Injectable()
export class PaymentsService {
  private readonly log = new Logger('Payments');
  constructor(@Inject(DB) private readonly db: Db) {}

  private async run(q: { sql: string; values: unknown[] }): Promise<Record<string, unknown>[]> {
    try {
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return rowsOf(rows);
    } catch (e) {
      if (isMissingObject(e)) {
        this.log.warn(`payments tables unavailable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
        throw new ServiceUnavailableException({ error: 'payments_unavailable' });
      }
      throw e;
    }
  }

  async list(scope: AdminScope, raw: Record<string, unknown>): Promise<ListAnswer> {
    const f = parsePaymentQuery(raw);
    if (isFail(f)) throw new BadRequestException(f);
    const today = paymentsFeesEnabled() ? seoulToday() : undefined;
    const [countRows, sumRows] = await Promise.all([this.run(countQuery(scope, f)), this.run(summaryQuery(scope, f, today))]);
    const total = Number(countRows[0]?.cnt) || 0;
    const totalPages = Math.max(1, Math.ceil(total / PAY_PER_PAGE));
    const page = Math.min(f.page, totalPages);
    const rows = await this.run(listQuery(scope, { ...f, page }, !!today));
    const answer: ListAnswer = { items: rows.map((r) => toItem(r, today)), total, page, perPage: PAY_PER_PAGE, totalPages, summary: toSums(sumRows[0]), from: f.from, to: f.to };
    if (today) {
      Object.assign(answer.summary, toFeeSums(sumRows[0]));
      const [methodRows, weekRows, heldRows] = await Promise.all([this.run(byMethodQuery(scope, f, today)), this.run(weeklyQuery(scope, f)), this.run(orderedHeldQuery(scope, f.hospitalId))]);
      answer.orderedHeld = { count: Number(heldRows[0]?.cnt) || 0, amount: Number(heldRows[0]?.amount) || 0 };
      answer.methods = methodRows.map((r) => ({ method: String(r.method ?? ''), ...toSums(r), ...toFeeSums(r) }));
      answer.weeks = weekRows.map((r) => ({ payoutDate: String(r.payout_date ?? ''), payments: Number(r.payments) || 0, fee: Number(r.fee) || 0, settlement: Number(r.settlement) || 0 }));
    }
    return answer;
  }

  async hospitals(scope: AdminScope): Promise<{ items: { id: number; name: string }[] }> {
    const rows = await this.run(hospitalOptionsQuery(scope));
    return { items: rows.map((r) => ({ id: Number(r.id), name: String(r.name ?? '') })) };
  }

  async byHospital(scope: AdminScope, raw: Record<string, unknown>) {
    const f = parseRangeQuery(raw, seoulToday());
    if (isFail(f)) throw new BadRequestException(f);
    const today = paymentsFeesEnabled() ? seoulToday() : undefined;
    const { rows, truncated } = capped(await this.run(byHospitalQuery(scope, f, today)));
    return { from: f.from, to: f.to, truncated, items: rows.map((r) => ({ hospitalId: Number(r.hospital_id), hospitalName: String(r.hospital_name ?? ''), ...toSums(r), ...(today ? toFeeSums(r) : {}) })) };
  }

  async byTreatment(scope: AdminScope, raw: Record<string, unknown>) {
    const f = parseRangeQuery(raw, seoulToday());
    if (isFail(f)) throw new BadRequestException(f);
    const today = paymentsFeesEnabled() ? seoulToday() : undefined;
    const { rows, truncated } = capped(await this.run(byTreatmentQuery(scope, f, today)));
    return { from: f.from, to: f.to, truncated, items: rows.map((r) => ({ treatmentName: String(r.treatment_name ?? ''), ...toSums(r), ...(today ? toFeeSums(r) : {}) })) };
  }

  /** This week against last week (day by day) and this month against last month (running total), for one money measure. */
  async compare(scope: AdminScope, raw: Record<string, unknown>) {
    const f = parseCompareQuery(raw);
    if (isFail(f)) throw new BadRequestException(f);
    const p = periodsOf(seoulToday());
    const [weekRows, monthRows] = await Promise.all([
      this.run(seriesQuery(scope, f.hospitalId, f.metric, p.week.lastStart, p.today)),
      this.run(seriesQuery(scope, f.hospitalId, f.metric, p.month.lastStart, p.today)),
    ]);
    const norm = (rows: Record<string, unknown>[]) => rows.map((r) => ({ day: String(r.pay_day ?? ''), value: Number(r.value) || 0 }));
    return { metric: f.metric, today: p.today, week: weekCompare(norm(weekRows), p), month: monthCompare(norm(monthRows), p) };
  }
}
