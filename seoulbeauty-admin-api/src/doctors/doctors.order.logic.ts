/** Pure parts of the doctors' display order inside one hospital and of the permanent delete: SQL (placeholders only) and the shape of the answers. */
import { num, Query, str } from '../hospitals/hospitals.logic';

/** Only active doctors are shown in the app, so only they have a place in the order of their hospital. */
export const DOCTOR_ORDER_BY = `CASE WHEN display_order IS NULL OR display_order < 1 THEN 1 ELSE 0 END, display_order ASC, name COLLATE utf8mb4_unicode_ci ASC, id ASC`;

/** The active doctors of one hospital in the order the app shows them, locked until the transaction ends so two moves can not interleave. */
export const lockDoctorOrderQuery = (hospitalId: number): Query => ({
  sql: `SELECT id, display_order FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active' ORDER BY ${DOCTOR_ORDER_BY} FOR UPDATE`,
  values: [hospitalId],
});

export const setDoctorOrderQuery = (id: number, position: number): Query => ({
  sql: 'UPDATE gangnamunni_doctors SET display_order = ? WHERE id = ?',
  values: [position, id],
});

/** The reorder screen's list (`withOrder` false = the column does not exist yet: by name, and the screen can not save). */
export const orderBoardQuery = (hospitalId: number, withOrder: boolean): Query => ({
  sql: `SELECT id, name, specialty, photo_url FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active'
        ORDER BY ${withOrder ? DOCTOR_ORDER_BY : 'name COLLATE utf8mb4_unicode_ci ASC, id ASC'}`,
  values: [hospitalId],
});

export interface DoctorOrderBoard {
  hospitalId: number;
  orderable: boolean;
  total: number;
  items: { id: number; name: string; specialty: string; photoUrl: string; position: number }[];
}

/** Places are the rows' positions 1..N: what the first move would store, so what is shown is what a move works on. */
export function toOrderBoard(hospitalId: number, orderable: boolean, rows: Record<string, unknown>[]): DoctorOrderBoard {
  const items = rows.map((r, i) => ({ id: num(r.id), name: str(r.name), specialty: str(r.specialty), photoUrl: str(r.photo_url), position: i + 1 }));
  return { hospitalId, orderable, total: items.length, items };
}

/** The tables that point at a doctor by `doctor_id`: bookings and reviews keep their rows (the reference is cleared), the others are deleted with the doctor. */
export const HISTORY_TABLES = ['gangnamunni_bookings', 'hospital_reviews'] as const;
export const RELATION_TABLES = ['gangnamunni_doctor_treatments', 'gangnamunni_doctor_schedules'] as const;

/** Does the table exist with a doctor_id column? (the names come from the lists above, never from the client) */
export const hasDoctorIdQuery = (table: string): Query => ({
  sql: `SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'doctor_id' LIMIT 1`,
  values: [table],
});

export const clearDoctorRefQuery = (table: (typeof HISTORY_TABLES)[number], doctorId: number): Query => {
  if (!(HISTORY_TABLES as readonly string[]).includes(table)) throw new Error('table not allowed');
  return { sql: `UPDATE \`${table}\` SET doctor_id = NULL WHERE doctor_id = ?`, values: [doctorId] };
};

export const deleteRelationRowsQuery = (table: (typeof RELATION_TABLES)[number], doctorId: number): Query => {
  if (!(RELATION_TABLES as readonly string[]).includes(table)) throw new Error('table not allowed');
  return { sql: `DELETE FROM \`${table}\` WHERE doctor_id = ?`, values: [doctorId] };
};

export const deleteDoctorQuery = (id: number): Query => ({ sql: 'DELETE FROM gangnamunni_doctors WHERE id = ? LIMIT 1', values: [id] });

/** Body of the permanent delete: { confirm: true }, nothing else counts. */
export const isDeleteConfirmed = (body: unknown): boolean =>
  !!body && typeof body === 'object' && !Array.isArray(body) && (body as Record<string, unknown>).confirm === true;
