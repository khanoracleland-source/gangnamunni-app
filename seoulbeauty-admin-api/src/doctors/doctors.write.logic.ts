/** Pure parts of editing and retiring a doctor: input checks and SQL (every value is a placeholder). */
import * as path from 'path';
import { positiveInt, Query } from '../hospitals/hospitals.logic';
import { INACTIVE_STORED } from './doctors.logic';

export const SPECIALTY_CHOICES = ['피부과 전문의', '성형외과 전문의', '마취통증의학과 전문의', '가정의학과 전문의', '일반의'];
export const MAX_NAME = 100;
export const MAX_SPECIALTY = 100;
export const MAX_QUALIFICATION = 2000;
export const MAX_TREATMENTS = 500;
export const LEVELS = ['basic', 'advanced', 'expert'] as const;
export type Level = (typeof LEVELS)[number];
export const MAX_CERTIFICATION = 500;
export const MAX_CASE_COUNT = 2_147_483_647;
/** The text field of the multipart edit body ("doctor", JSON) may not be larger than this. */
export const MAX_DOCTOR_FIELD_BYTES = 64 * 1024;

/** Level, number of cases and certification of one assigned treatment. */
export interface TreatmentDetail {
  id: number;
  level: Level;
  caseCount: number;
  certification: string;
}

export interface DoctorEdit {
  name: string;
  hospitalId: number;
  specialty: string;
  qualification: string;
  /** Every wanted treatment: the ones listed in `treatments` too. */
  treatmentIds: number[];
  /** Treatments sent with level, cases and certification. Others in `treatmentIds` keep what is stored (new ones start as basic, 0 cases). */
  treatmentDetails: TreatmentDetail[];
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

  const details: TreatmentDetail[] = [];
  if (o.treatments !== undefined) {
    const items = o.treatments;
    if (!Array.isArray(items) || items.length > MAX_TREATMENTS) return bad('treatments');
    const seen = new Set<number>();
    for (const item of items) {
      if (!item || typeof item !== 'object' || Array.isArray(item)) return bad('treatments');
      const r = item as Record<string, unknown>;
      const id = r.id;
      if (typeof id !== 'number' || !Number.isInteger(id) || id <= 0 || id > 2_147_483_647 || seen.has(id)) return bad('treatments');
      seen.add(id);
      const level = r.level;
      if (typeof level !== 'string' || !(LEVELS as readonly string[]).includes(level)) return bad('treatments', 'invalid_level');
      const caseCount = r.caseCount;
      if (typeof caseCount !== 'number' || !Number.isInteger(caseCount) || caseCount < 0 || caseCount > MAX_CASE_COUNT) return bad('treatments', 'invalid_case_count');
      const certification = r.certification === undefined || r.certification === null ? '' : r.certification;
      if (typeof certification !== 'string') return bad('treatments', 'invalid_certification');
      const cert = certification.trim();
      if ([...cert].length > MAX_CERTIFICATION || CONTROL.test(cert)) return bad('treatments', 'invalid_certification');
      details.push({ id, level: level as Level, caseCount, certification: cert });
      ids.add(id);
    }
  }
  if (ids.size > MAX_TREATMENTS) return bad('treatments');
  return { ok: true, edit: { name, hospitalId, specialty, qualification, treatmentIds: [...ids], treatmentDetails: details } };
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

/** Assigns (or re-activates) treatments with the level, cases and certification the form sent; an empty certification is stored as NULL. */
export function assignDetailedTreatmentsQuery(doctorId: number, hospitalId: number, items: TreatmentDetail[]): Query {
  const rows = items.map(() => '(?, ?, ?, ?, ?, ?, 1)').join(', ');
  const values: unknown[] = [];
  for (const t of items) values.push(doctorId, hospitalId, t.id, t.caseCount, t.level, t.certification === '' ? null : t.certification);
  return {
    sql: `INSERT INTO gangnamunni_doctor_treatments (doctor_id, hospital_id, treatment_id, case_count, speciality_level, certification, is_active) VALUES ${rows}
          ON DUPLICATE KEY UPDATE hospital_id = VALUES(hospital_id), case_count = VALUES(case_count), speciality_level = VALUES(speciality_level),
                                  certification = VALUES(certification), is_active = 1, updated_at = NOW()`,
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
