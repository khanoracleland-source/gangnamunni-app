/** Pure parts of editing and retiring a doctor: input checks and SQL (every value is a placeholder). */
import * as path from 'path';
import { positiveInt, Query } from '../hospitals/hospitals.logic';
import { INACTIVE_STORED } from './doctors.logic';

export const SPECIALTY_CHOICES = ['피부과 전문의', '성형외과 전문의', '마취통증의학과 전문의', '가정의학과 전문의', '일반의'];
export const MAX_NAME = 100;
export const MAX_SPECIALTY = 100;
export const MAX_QUALIFICATION = 2000;
export const MAX_TREATMENTS = 500;
/** The text field of the multipart edit body ("doctor", JSON) may not be larger than this. */
export const MAX_DOCTOR_FIELD_BYTES = 64 * 1024;

export interface DoctorEdit {
  name: string;
  hospitalId: number;
  specialty: string;
  qualification: string;
  treatmentIds: number[];
}

export type EditParse = { ok: true; edit: DoctorEdit } | { ok: false; body: { error: string; field?: string } };

const bad = (field: string, error = 'invalid_field'): EditParse => ({ ok: false, body: { error, field } });
// control characters other than tab/newline are never part of a name or a membership text
// eslint-disable-next-line no-control-regex
const CONTROL = /[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/;

/** The edit form (JSON text): name, hospital, specialty, memberships and the treatment ids. `currentSpecialty` is the value stored now, which stays acceptable. */
export function parseEdit(raw: unknown, currentSpecialty: string): EditParse {
  let src: unknown = raw;
  if (typeof raw === 'string') {
    if (Buffer.byteLength(raw, 'utf8') > MAX_DOCTOR_FIELD_BYTES) return bad('doctor');
    try {
      src = JSON.parse(raw);
    } catch {
      return bad('doctor');
    }
  }
  if (!src || typeof src !== 'object' || Array.isArray(src)) return bad('doctor');
  const o = src as Record<string, unknown>;

  const name = typeof o.name === 'string' ? o.name.trim() : '';
  if (!name) return bad('name', 'name_required');
  if ([...name].length > MAX_NAME || CONTROL.test(name)) return bad('name');

  const hospitalId = typeof o.hospitalId === 'number' && Number.isInteger(o.hospitalId) ? positiveInt(o.hospitalId) : 0;
  if (hospitalId <= 0) return bad('hospitalId', 'hospital_required');

  const specialty = typeof o.specialty === 'string' ? o.specialty.trim() : '';
  if ([...specialty].length > MAX_SPECIALTY || (specialty !== currentSpecialty && !SPECIALTY_CHOICES.includes(specialty))) return bad('specialty');

  const qualification = typeof o.qualification === 'string' ? o.qualification.trim() : '';
  if ([...qualification].length > MAX_QUALIFICATION || CONTROL.test(qualification)) return bad('qualification');

  const list = o.treatmentIds === undefined ? [] : o.treatmentIds;
  if (!Array.isArray(list) || list.length > MAX_TREATMENTS) return bad('treatmentIds');
  const ids = new Set<number>();
  for (const v of list) {
    if (typeof v !== 'number' || !Number.isInteger(v) || v <= 0 || v > 2_147_483_647) return bad('treatmentIds');
    ids.add(v);
  }
  return { ok: true, edit: { name, hospitalId, specialty, qualification, treatmentIds: [...ids] } };
}

/** The doctor row, locked for the rest of the transaction. */
export const lockDoctorQuery = (id: number): Query => ({
  sql: `SELECT id, hospital_id, name, specialty, photo_url, status FROM gangnamunni_doctors WHERE id = ? LIMIT 1 FOR UPDATE`,
  values: [id],
});

/** An approved hospital, locked: a doctor can only belong to one. */
export const lockApprovedHospitalQuery = (id: number): Query => ({
  sql: `SELECT id FROM gangnamunni_hospitals WHERE id = ? AND status = 'approved' LIMIT 1 FOR UPDATE`,
  values: [id],
});

/** `photoUrl` null leaves the stored photo alone; `resetOrder` puts the doctor last at the new hospital (only when the order column exists). */
export function updateDoctorQuery(id: number, e: DoctorEdit, photoUrl: string | null, resetOrder: boolean): Query {
  const sets = ['name = ?', 'hospital_id = ?', 'specialty = ?', 'qualification = ?'];
  const values: unknown[] = [e.name, e.hospitalId, e.specialty, e.qualification];
  if (photoUrl !== null) {
    sets.push('photo_url = ?');
    values.push(photoUrl);
  }
  if (resetOrder) sets.push('display_order = NULL');
  sets.push('updated_at = NOW()');
  return { sql: `UPDATE gangnamunni_doctors SET ${sets.join(', ')} WHERE id = ? LIMIT 1`, values: [...values, id] };
}

/** Of the wanted treatments, the ones the hospital offers right now (active and approved); the rest is ignored like the old page does. */
export const offeredTreatmentsQuery = (hospitalId: number, ids: number[]): Query => ({
  sql: `SELECT DISTINCT treatment_id FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND is_active = 1 AND status = 'active' AND treatment_id IN (?)`,
  values: [hospitalId, ids],
});

/** Switches off every assignment of the doctor that is not in the new list (all of them when the list is empty). */
export function deactivateAssignmentsQuery(doctorId: number, keep: number[]): Query {
  if (!keep.length) return { sql: `UPDATE gangnamunni_doctor_treatments SET is_active = 0 WHERE doctor_id = ? AND is_active = 1`, values: [doctorId] };
  return { sql: `UPDATE gangnamunni_doctor_treatments SET is_active = 0 WHERE doctor_id = ? AND is_active = 1 AND treatment_id NOT IN (?)`, values: [doctorId, keep] };
}

/** Assigns (or re-activates) the treatments at the doctor's hospital; a row of another hospital is moved over. */
export function assignTreatmentsQuery(doctorId: number, hospitalId: number, ids: number[]): Query {
  const rows = ids.map(() => '(?, ?, ?, 0, ?, 1)').join(', ');
  const values: unknown[] = [];
  for (const t of ids) values.push(doctorId, hospitalId, t, 'basic');
  return {
    sql: `INSERT INTO gangnamunni_doctor_treatments (doctor_id, hospital_id, treatment_id, case_count, speciality_level, is_active) VALUES ${rows}
          ON DUPLICATE KEY UPDATE hospital_id = VALUES(hospital_id), is_active = 1`,
    values,
  };
}

/** Switches a doctor between active and inactive; going inactive also takes the doctor out of the display order. Assignments stay as they are. */
export const setActiveQuery = (id: number, active: boolean, clearOrder: boolean): Query => ({
  sql: `UPDATE gangnamunni_doctors SET status = ?${!active && clearOrder ? ', display_order = NULL' : ''}, updated_at = NOW() WHERE id = ? LIMIT 1`,
  values: [active ? 'active' : INACTIVE_STORED, id],
});

/** Retire: status resigned and out of the display order. */
export const retireDoctorQuery = (id: number, clearOrder: boolean): Query => ({
  sql: `UPDATE gangnamunni_doctors SET status = 'resigned'${clearOrder ? ', display_order = NULL' : ''}, updated_at = NOW() WHERE id = ? LIMIT 1`,
  values: [id],
});

export const deactivateAllAssignmentsQuery = (doctorId: number): Query => ({
  sql: `UPDATE gangnamunni_doctor_treatments SET is_active = 0 WHERE doctor_id = ? AND is_active = 1`,
  values: [doctorId],
});

/**
 * A stored photo address this panel (or the old page) wrote itself: only then may the file be removed.
 * Anything else (an https address, a path with "..", another folder) is left alone.
 */
export function removablePhoto(root: string, url: string, doctorId: number): string | null {
  const m = /^uploads\/(?:hospitals\/\d{1,10}|doctors)\/doctor_(\d{1,10})_[0-9a-f]{8,64}\.(?:jpg|jpeg|png|webp|gif)$/.exec(url);
  // the file name carries the id of the doctor it was written for: another doctor's picture is never removed
  if (!m || Number(m[1]) !== doctorId) return null;
  const base = path.resolve(root);
  const abs = path.resolve(base, url);
  return abs.startsWith(base + path.sep) ? abs : null;
}
