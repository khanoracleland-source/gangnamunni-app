import { randomBytes } from 'node:crypto';
import { BadRequestException, ConflictException, Inject, Injectable, Logger, NotFoundException, UnprocessableEntityException } from '@nestjs/common';
import { AdminScope } from '../admin-auth/admin.guard';
import { DB_RW, RwConnection, RwDbProvider } from '../db/db.module';
import { BookingNotifyService } from './notify/booking-notify.service';
import { BookingEvent } from './notify/notify.logic';
import { BookingsRwBase, WriteActor } from './bookings.rw';
import { BookingAction, deadlineHours, isFail, merchantUid, parseAssignBody, parseStatusBody, parsePositiveInt, scopeClause, transitionOf } from './bookings.logic';

const EVENT_OF: Record<string, BookingEvent> = { hospital_checking: 'hospital_checking', confirmed: 'confirmed', completed: 'completed', cancelled: 'cancelled' };
const hasDoctor = (r: { doctor_id?: unknown; doctor_name?: unknown }): boolean => Number(r.doctor_id) > 0 || String(r.doctor_name ?? '').trim() !== '';

/** Changes the hospital makes to a booking: the doctor, and the next step of the flow. */
@Injectable()
export class BookingsWriteService extends BookingsRwBase {
  protected readonly log = new Logger('BookingsWrite');

  constructor(@Inject(DB_RW) protected readonly rw: RwDbProvider, private readonly notify: BookingNotifyService) {
    super();
  }

  async assignDoctor(actor: WriteActor, scope: AdminScope, rawId: string, body: unknown): Promise<{ doctorId: number; doctorName: string }> {
    const id = parsePositiveInt(rawId);
    if (id === null) throw new NotFoundException({ error: 'not_found' });
    const b = parseAssignBody(body);
    if (isFail(b)) throw new BadRequestException(b);
    const sc = scopeClause(scope, 0, 'hospital_id');

    const out = await this.guarded(async () => {
      const pool = this.pool();
      const [rows] = (await pool.query(`SELECT id, hospital_id, doctor_id, doctor_name FROM gangnamunni_bookings WHERE id = ? AND ${sc.sql} LIMIT 1`, [id, ...sc.values])) as any;
      const cur = (rows as any[])[0];
      if (!cur) throw new NotFoundException({ error: 'not_found' });
      if (b.doctorId === 0) {
        if (!hasDoctor(cur)) return { doctorId: 0, doctorName: '', changed: false };
        await pool.query(`UPDATE gangnamunni_bookings SET doctor_id = NULL, doctor_name = NULL WHERE id = ? AND ${sc.sql}`, [id, ...sc.values]);
        return { doctorId: 0, doctorName: '', changed: true };
      }
      const [docs] = (await pool.query("SELECT id, name, hospital_id FROM gangnamunni_doctors WHERE id = ? AND status = 'active' LIMIT 1", [b.doctorId])) as any;
      const doc = (docs as any[])[0];
      // A doctor of another hospital looks the same as a missing one: nothing is revealed about it.
      if (!doc || Number(doc.hospital_id) !== Number(cur.hospital_id)) throw new UnprocessableEntityException({ error: 'doctor_not_found' });
      const name = String(doc.name ?? '');
      if (Number(cur.doctor_id) === b.doctorId && String(cur.doctor_name ?? '') === name) return { doctorId: b.doctorId, doctorName: name, changed: false };
      await pool.query(`UPDATE gangnamunni_bookings SET doctor_id = ?, doctor_name = ? WHERE id = ? AND ${sc.sql}`, [b.doctorId, name, id, ...sc.values]);
      return { doctorId: b.doctorId, doctorName: name, changed: true };
    });

    this.audit(actor, b.doctorId === 0 ? 'unassign_doctor' : 'assign_doctor', id);
    if (out.changed) this.notify.later(id, b.doctorId === 0 ? 'doctor_unassigned' : 'doctor_assigned', { doctorName: out.doctorName });
    return { doctorId: out.doctorId, doctorName: out.doctorName };
  }

  /**
   * The flow is pending -> hospital_checking -> confirmed -> completed, with a cancellation while the hospital is checking.
   * Confirming without a doctor assigns the hospital's first active one. After the change is committed (and only then):
   * the member is told, and a confirmation also opens the payment order and asks the member to pay.
   */
  async changeStatus(actor: WriteActor, scope: AdminScope, rawId: string, body: unknown): Promise<{ id: number; status: string; doctorId: number; doctorName: string }> {
    const id = parsePositiveInt(rawId);
    if (id === null) throw new NotFoundException({ error: 'not_found' });
    const b = parseStatusBody(body);
    if (isFail(b)) throw new BadRequestException(b);
    const sc = scopeClause(scope, 0, 'hospital_id');

    const done = await this.guarded(() =>
      this.tx(async (conn: RwConnection) => {
        const [rows] = (await conn.query(`SELECT status, hospital_id, doctor_id, doctor_name FROM gangnamunni_bookings WHERE id = ? AND ${sc.sql} LIMIT 1 FOR UPDATE`, [id, ...sc.values])) as any;
        const cur = (rows as any[])[0];
        if (!cur) throw new NotFoundException({ error: 'not_found' });
        const current = String(cur.status ?? '');
        const next = transitionOf(current, b.action);
        if (!next) throw new ConflictException({ error: 'invalid_transition', status: current });

        let doctor: { id: number; name: string } | null = null;
        let autoDoctor = false;
        if (b.action === 'confirm' && !hasDoctor(cur)) {
          const [docs] = (await conn.query("SELECT id, name FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active' ORDER BY name ASC, id ASC LIMIT 1", [Number(cur.hospital_id) || 0])) as any;
          const d = (docs as any[])[0];
          if (!d) throw new UnprocessableEntityException({ error: 'no_active_doctor' });
          doctor = { id: Number(d.id), name: String(d.name ?? '') };
          autoDoctor = true;
        }

        const sets = ['status = ?'];
        const values: unknown[] = [next];
        if (doctor) { sets.push('doctor_id = ?', 'doctor_name = ?'); values.push(doctor.id, doctor.name); }
        if (b.action === 'cancel') { sets.push('cancel_reason = ?', 'cancelled_at = NOW()', "cancelled_by = 'hospital'"); values.push(b.cancelReason); }
        // The payment deadline starts the moment the booking is confirmed.
        if (b.action === 'confirm') sets.push('confirmed_at = NOW()', 'expire_notice_seen_at = NULL');
        const [res] = (await conn.query(`UPDATE gangnamunni_bookings SET ${sets.join(', ')} WHERE id = ? AND status = ? AND ${sc.sql}`, [...values, id, current, ...sc.values])) as any;
        if (Number(res?.affectedRows) !== 1) throw new ConflictException({ error: 'invalid_transition', status: current });
        return { next, autoDoctor, doctorId: doctor ? doctor.id : Number(cur.doctor_id) || 0, doctorName: doctor ? doctor.name : String(cur.doctor_name ?? '').trim() };
      }),
    );

    this.audit(actor, b.action, id);
    void this.afterCommit(id, b.action, done.next, done.autoDoctor, done.doctorName);
    return { id, status: done.next, doctorId: done.doctorId, doctorName: done.doctorName };
  }

  /** Everything that follows a committed change. Each part is best effort: the booking change itself already stands. */
  private async afterCommit(id: number, action: BookingAction, status: string, autoDoctor: boolean, doctorName: string): Promise<void> {
    // One after another, so the member receives the notices in the order things happened.
    const send = async (event: BookingEvent, ctx = {}): Promise<void> => {
      try { await this.notify.booking(id, event, ctx); } catch (e) { this.log.error(`booking #${id} ${event}: ${(e as Error).message}`); }
    };
    try {
      if (autoDoctor) await send('doctor_assigned', { doctorName });
      // Points for a completed booking are not connected yet (ADMIN_BOOKINGS_POINTS is reserved for them).
      if (action === 'complete' && process.env.ADMIN_BOOKINGS_POINTS === '1') this.log.warn(`booking #${id}: points are switched on but not connected; nothing awarded`);
      const askToPay = action === 'confirm' ? await this.ensurePaymentOrder(id) : false;
      const event = EVENT_OF[status];
      if (event) await send(event);
      if (askToPay) await send('payment_requested', { deadlineHours: await this.paymentDeadlineHours() });
    } catch (e) {
      this.log.error(`booking #${id} follow-up failed: ${(e as Error).message}`);
    }
  }

  /**
   * The payment order of a confirmed booking: created once, from the server-side price. A booking that already has an order keeps it.
   * Returns true when the member still has to pay (a new order, or the earlier one is still waiting).
   */
  private async ensurePaymentOrder(bookingId: number): Promise<boolean> {
    try {
      return await this.tx(async (conn) => {
        const [rows] = (await conn.query('SELECT id, hospital_id, hospital_name, mb_id, name, phone, treatment, price FROM gangnamunni_bookings WHERE id = ? FOR UPDATE', [bookingId])) as any;
        const b = (rows as any[])[0];
        if (!b || Number(b.hospital_id) <= 0 || Number(b.price) <= 0) throw new Error('booking or server-side price is invalid');
        const [ex] = (await conn.query("SELECT id, status FROM gangnamunni_platform_payments WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1", [bookingId])) as any;
        const existing = (ex as any[])[0];
        if (existing) return String(existing.status) === 'pending';
        await conn.query(
          `INSERT INTO gangnamunni_platform_payments (payment_type, funds_recipient, booking_id, hospital_id, hospital_name, patient_mb_id, patient_name, patient_phone, treatment_name, amount, currency, merchant_uid, status)
           VALUES ('booking', 'hospital', ?, ?, ?, ?, ?, ?, ?, ?, 'KRW', ?, 'pending')`,
          [bookingId, Number(b.hospital_id), String(b.hospital_name ?? ''), String(b.mb_id ?? ''), String(b.name ?? ''), String(b.phone ?? ''), String(b.treatment ?? ''), Number(b.price), merchantUid(bookingId, randomBytes(6).toString('hex'))],
        );
        return true;
      });
    } catch (e) {
      this.log.error(`booking #${bookingId} payment order not created: ${(e as Error).message}`);
      return false;
    }
  }

  private async paymentDeadlineHours(): Promise<number> {
    try {
      const [rows] = (await this.pool().query("SELECT setting_value FROM gangnamunni_settings WHERE setting_key = 'payment_deadline_hours' LIMIT 1")) as any;
      return deadlineHours((rows as any[])[0]?.setting_value);
    } catch { return 24; }
  }
}
