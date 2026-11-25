import { BadRequestException, Inject, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { AdminScope } from '../admin-auth/admin.guard';
import { BookingsRwBase, WriteActor } from '../bookings/bookings.rw';
import { DB_RW, RwDbProvider } from '../db/db.module';
import { isFail, parseId, parseMessageBody, parseStatusBody, scopeClause } from './consultations.logic';

/** Changes an admin makes to an application: its status, and the message the member sees. Every statement is cut to the admin's hospitals. */
@Injectable()
export class ConsultationsWriteService extends BookingsRwBase {
  protected readonly log = new Logger('ConsultationsWrite');

  constructor(@Inject(DB_RW) protected readonly rw: RwDbProvider) {
    super();
  }

  /** Another hospital's application looks the same as a missing one (404). Asking for the status it already has changes nothing. */
  async changeStatus(actor: WriteActor, scope: AdminScope, rawId: unknown, body: unknown): Promise<{ id: number; status: string; changed: boolean }> {
    const id = parseId(rawId);
    if (id === null) throw new NotFoundException({ error: 'not_found' });
    const b = parseStatusBody(body);
    if (isFail(b)) throw new BadRequestException(b);
    const sc = scopeClause(scope, 0, 'hospital_id');

    const changed = await this.guarded(() =>
      this.tx(async (conn) => {
        const [rows] = (await conn.query(`SELECT id, status FROM gangnamunni_consultations WHERE id = ? AND ${sc.sql} LIMIT 1 FOR UPDATE`, [id, ...sc.values])) as any;
        const cur = (rows as any[])[0];
        if (!cur) throw new NotFoundException({ error: 'not_found' });
        if (String(cur.status) === b.status) return false;
        await conn.query(`UPDATE gangnamunni_consultations SET status = ? WHERE id = ? AND ${sc.sql}`, [b.status, id, ...sc.values]);
        return true;
      }),
    );
    this.log.log(`consultation write: admin=${actor.id} action=status:${b.status} consultation=${id} changed=${changed}`);
    return { id, status: b.status, changed };
  }

  /** Saves the message (an empty one clears it and its time). Needs the history columns of the consultation_history migration. */
  async saveMessage(actor: WriteActor, scope: AdminScope, rawId: unknown, body: unknown): Promise<{ id: number; hospitalMessage: string }> {
    const id = parseId(rawId);
    if (id === null) throw new NotFoundException({ error: 'not_found' });
    const b = parseMessageBody(body);
    if (isFail(b)) throw new BadRequestException(b);
    const sc = scopeClause(scope, 0, 'hospital_id');

    await this.guarded(() =>
      this.tx(async (conn) => {
        const [rows] = (await conn.query(`SELECT id FROM gangnamunni_consultations WHERE id = ? AND ${sc.sql} LIMIT 1 FOR UPDATE`, [id, ...sc.values])) as any;
        if (!(rows as any[])[0]) throw new NotFoundException({ error: 'not_found' });
        await conn.query(`UPDATE gangnamunni_consultations SET hospital_message = ?, hospital_message_at = IF(? = '', NULL, NOW()) WHERE id = ? AND ${sc.sql}`, [b.message, b.message, id, ...sc.values]);
      }),
    );
    this.log.log(`consultation write: admin=${actor.id} action=message consultation=${id} length=${b.message.length}`);
    return { id, hospitalMessage: b.message };
  }
}
