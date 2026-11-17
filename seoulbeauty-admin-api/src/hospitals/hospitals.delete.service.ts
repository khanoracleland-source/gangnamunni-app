import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException, ServiceUnavailableException } from '@nestjs/common';
import { DB, Db, DB_RW, RwDb, RwDbProvider } from '../db/db.module';
import { canSee, Query, Scope, seesNothing } from './hospitals.logic';
import {
  Blocker, buildBlockers, buildPeriod, buildSnapshot, doctorCountQuery, hospitalRowQuery, HospitalSalesStats, imageCountQuery, insertArchiveQuery, lastSettlementQuery,
  money, nameMatches, num, openBookingsQuery, openConsultationsQuery, parseDeleteBody, periodBookingsQuery, periodSalesQuery, settlementsSummaryQuery,
  snapshotDoctorsQuery, snapshotImagesQuery, totalBookingsQuery, totalSalesQuery, treatmentCountQuery, unsettledPaymentsQuery, unsettledRefundsQuery,
} from './hospitals.delete.logic';

type Row = Record<string, unknown>;
type Run = (q: Query) => Promise<Row[]>;
export interface DeleteActor {
  id: number;
  name: string;
}

export interface DeleteCheck {
  canDelete: boolean;
  name: string;
  blockers: Blocker[];
  deletableAfter: string | null;
  stats: { bookings: number; cancelled: number; sales: { paid: number; refunded: number; net: number } };
  willDelete: { images: number; doctors: number; treatments: number };
}

/** Database problems that mean "the write user cannot be used right now" rather than a bug. */
const UNAVAILABLE_CODES = [
  'ER_ACCESS_DENIED_ERROR', 'ER_DBACCESS_DENIED_ERROR', 'ER_TABLEACCESS_DENIED_ERROR', 'ER_COLUMNACCESS_DENIED_ERROR', 'ER_CON_COUNT_ERROR', 'ER_NO_SUCH_TABLE',
  'ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'EHOSTUNREACH', 'PROTOCOL_CONNECTION_LOST',
];
const UNAVAILABLE_ERRNO = [1044, 1045, 1142, 1143, 1040, 1146];

/** Sales card, delete check and the delete itself. Reads use the read-only pool, the delete one transaction of the write pool. */
@Injectable()
export class HospitalsDeleteService {
  private readonly log = new Logger('HospitalsDelete');

  constructor(@Inject(DB) private readonly ro: Db, @Inject(DB_RW) private readonly rw: RwDbProvider) {}

  private readonly roRun: Run = async (q) => {
    const [rows] = (await this.ro.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  };

  /** The hospital row as the detail page sees it (approved, inside the scope), or null. */
  private async visibleApproved(scope: Scope, id: number): Promise<boolean> {
    if (id <= 0 || seesNothing(scope) || !canSee(scope, id)) return false;
    const rows = await this.roRun({ sql: `SELECT id FROM gangnamunni_hospitals WHERE id = ? AND status = 'approved' LIMIT 1`, values: [id] });
    return rows.length > 0;
  }

  /** Bookings and sales of this week (from Monday) and this month (from the 1st), KST. A missing table or column is an error, never zeros. */
  async stats(scope: Scope, id: number): Promise<HospitalSalesStats | null> {
    if (!(await this.visibleApproved(scope, id))) return null;
    const [wb, ws, mb, ms] = await Promise.all([
      this.roRun(periodBookingsQuery(id, 'week')), this.roRun(periodSalesQuery(id, 'week')),
      this.roRun(periodBookingsQuery(id, 'month')), this.roRun(periodSalesQuery(id, 'month')),
    ]);
    return { week: buildPeriod(wb[0], ws[0]), month: buildPeriod(mb[0], ms[0]) };
  }

  private async blockersOf(run: Run, id: number) {
    const [bookings, consultations, settlements, last] = await Promise.all([
      run(openBookingsQuery(id)), run(openConsultationsQuery(id)), run(settlementsSummaryQuery(id)), run(lastSettlementQuery(id)),
    ]);
    const [payments, refunds] = await Promise.all([run(unsettledPaymentsQuery(id)), run(unsettledRefundsQuery(id))]);
    return buildBlockers({ bookings: bookings[0], consultations: consultations[0], settlements: settlements[0], last: last[0], payments: payments[0], refunds: refunds[0] });
  }

  /** null = no such approved hospital (a pending / rejected application is not deleted from here). */
  async check(id: number): Promise<DeleteCheck | null> {
    if (id <= 0) return null;
    const hospital = (await this.roRun({ sql: `SELECT id, name FROM gangnamunni_hospitals WHERE id = ? AND status = 'approved' LIMIT 1`, values: [id] }))[0];
    if (!hospital) return null;
    const [blockers, tb, ts, images, doctors, treatments] = await Promise.all([
      this.blockersOf(this.roRun, id), this.roRun(totalBookingsQuery(id)), this.roRun(totalSalesQuery(id)),
      this.roRun(imageCountQuery(id)), this.roRun(doctorCountQuery(id)), this.roRun(treatmentCountQuery(id)),
    ]);
    return {
      canDelete: blockers.blockers.length === 0,
      name: String(hospital.name ?? ''),
      blockers: blockers.blockers,
      deletableAfter: blockers.deletableAfter,
      stats: { bookings: num(tb[0]?.total), cancelled: num(tb[0]?.cancelled), sales: money(ts[0]) },
      willDelete: { images: num(images[0]?.cnt), doctors: num(doctors[0]?.cnt), treatments: num(treatments[0]?.cnt) },
    };
  }

  private pool(): RwDb {
    const pool = this.rw.get();
    if (!pool) {
      this.log.warn('write pool not configured (DB_RW_USER is empty)');
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
    return pool;
  }

  async delete(actor: DeleteActor, id: number, body: unknown): Promise<{ archiveId: number }> {
    if (id <= 0) throw new NotFoundException({ error: 'not_found' });
    const hospital = (await this.roRun({ sql: `SELECT id, name, updated_at FROM gangnamunni_hospitals WHERE id = ? AND status = 'approved' LIMIT 1`, values: [id] }))[0];
    if (!hospital) throw new NotFoundException({ error: 'not_found' });
    const parsed = parseDeleteBody(body);
    if (!parsed.ok) throw new BadRequestException({ error: 'invalid', fields: parsed.fields });
    if (!nameMatches(parsed.confirmName, hospital.name)) throw new BadRequestException({ error: 'invalid', fields: { confirmName: 'mismatch' } });
    const pre = await this.blockersOf(this.roRun, id);
    if (pre.blockers.length > 0) throw new ConflictException({ error: 'blocked', blockers: pre.blockers, deletableAfter: pre.deletableAfter });
    const current = hospital.updated_at == null ? null : String(hospital.updated_at);
    if (parsed.expectedUpdatedAt !== current) throw new ConflictException({ error: 'conflict', updatedAt: current });

    const pool = this.pool();
    let conn;
    try {
      conn = await pool.getConnection();
    } catch (e) {
      this.unavailable(e);
      throw e;
    }
    try {
      await conn.beginTransaction();
      const run: Run = async (q) => {
        const [rows] = (await conn.query(q.sql, q.values)) as any;
        return Array.isArray(rows) ? rows : [];
      };
      // The row is locked first, so nothing can edit it while its data is copied and removed.
      const locked = (await run(hospitalRowQuery(id, true)))[0];
      if (!locked) throw new NotFoundException({ error: 'not_found' });
      const lockedAt = locked.updated_at == null ? null : String(locked.updated_at);
      if (lockedAt !== parsed.expectedUpdatedAt) throw new ConflictException({ error: 'conflict', updatedAt: lockedAt });
      const inTx = await this.blockersOf(run, id);
      if (inTx.blockers.length > 0) throw new ConflictException({ error: 'blocked', blockers: inTx.blockers, deletableAfter: inTx.deletableAfter });

      const [doctors, images, treatments, tb, ts] = await Promise.all([
        run(snapshotDoctorsQuery(id)), run(snapshotImagesQuery(id)), run(treatmentCountQuery(id)), run(totalBookingsQuery(id)), run(totalSalesQuery(id)),
      ]);
      const snapshot = buildSnapshot(locked, doctors, images, num(treatments[0]?.cnt));
      const sales = money(ts[0]);
      const insert = insertArchiveQuery({
        hospitalId: id, name: String(locked.name ?? ''), snapshot: JSON.stringify(snapshot), bookingsTotal: num(tb[0]?.total), bookingsCancelled: num(tb[0]?.cancelled),
        paid: sales.paid, refunded: sales.refunded, net: sales.net, adminId: actor.id, adminName: actor.name,
      });
      const [ins] = (await conn.query(insert.sql, insert.values)) as any;
      const archiveId = num(ins?.insertId);
      if (archiveId <= 0) throw new Error('archive insert returned no id');

      await conn.query(`DELETE dt FROM gangnamunni_doctor_treatments dt JOIN gangnamunni_doctors d ON d.id = dt.doctor_id WHERE d.hospital_id = ?`, [id]);
      await conn.query('DELETE FROM gangnamunni_doctors WHERE hospital_id = ?', [id]);
      await conn.query('DELETE FROM gangnamunni_hospital_treatments WHERE hospital_id = ?', [id]);
      await conn.query('DELETE FROM hospital_images WHERE hospital_id = ?', [id]);
      await conn.query('DELETE FROM gangnamunni_hospital_revisions WHERE hospital_id = ?', [id]);
      const [res] = (await conn.query('DELETE FROM gangnamunni_hospitals WHERE id = ? AND updated_at <=> ?', [id, parsed.expectedUpdatedAt])) as any;
      if (!res || res.affectedRows !== 1) throw new ConflictException({ error: 'conflict', updatedAt: lockedAt });
      await conn.commit();
      this.log.log(`hospital delete: admin=${actor.id} hospital=${id} archive=${archiveId}`);
      return { archiveId };
    } catch (e) {
      await conn.rollback().catch(() => undefined);
      this.unavailable(e);
      throw e;
    } finally {
      conn.release();
    }
  }

  /** Database errors of the write user -> 503 write_unavailable; anything else is left to the caller. */
  private unavailable(e: unknown): void {
    const err = e as { code?: string; errno?: number; message?: string };
    if (UNAVAILABLE_CODES.includes(err?.code || '') || UNAVAILABLE_ERRNO.includes(err?.errno || 0)) {
      this.log.warn(`write database unavailable: ${err.message}`);
      throw new ServiceUnavailableException({ error: 'write_unavailable' });
    }
  }
}
