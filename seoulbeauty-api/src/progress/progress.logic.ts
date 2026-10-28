/** Pure helpers for 경과 후기 (ports of my_progress_reviews.php, progress_review.php, includes/progress_review_common.php). */
import { PointsService } from '../points/points.service';

export type Kind = 'procedure' | 'surgery';
export const STATUS_LABEL: Record<string, string> = { writing: '작성 중', submitted: '검수 중', approved: '적립 완료', rejected: '반려', deleted: '삭제' };
export const kindOf = (v: unknown): Kind => (v === 'surgery' ? 'surgery' : 'procedure');
export const kindLabel = (k: Kind) => (k === 'surgery' ? '수술' : '시술');
export const MAX_ENTRY_PHOTOS = 5;
export const PHOTO_MAX_BYTES = 5 * 1024 * 1024;
export const ENTRY_MIN = 20;
export const ENTRY_MAX = 3000;

/** points_v2_progress_required_entries() */
export function requiredEntries(s: Record<string, string>, kind: Kind): number {
  return kind === 'surgery' ? PointsService.int(s, 'v2_progress_surgery_entries', 10) : PointsService.int(s, 'v2_progress_procedure_entries', 3);
}
export function awardPoint(s: Record<string, string>, kind: Kind): number {
  return kind === 'surgery' ? PointsService.int(s, 'v2_progress_surgery_point', 15000) : PointsService.int(s, 'v2_progress_procedure_point', 6000);
}

/** 'YYYY-MM-DD' that is a real calendar date and not after `today`; '' otherwise. */
export function validDate(v: unknown, today: string): string {
  const d = typeof v === 'string' ? v.trim() : '';
  if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return '';
  const [y, m, dd] = d.split('-').map(Number);
  const t = new Date(Date.UTC(y, m - 1, dd));
  if (t.getUTCFullYear() !== y || t.getUTCMonth() !== m - 1 || t.getUTCDate() !== dd) return '';
  return d > today ? '' : d;
}

/** PHP mb_substr(trim(...), 0, max) — trims then cuts by characters. */
export const cut = (v: unknown, max: number) => Array.from(typeof v === 'string' ? v.trim() : '').slice(0, max).join('');

/** pr_editable(): writing, or rejected within v2_progress_resubmit_days of creation. */
export function editable(status: string, createdAt: string, resubmitDays: number, nowMs = Date.now()): boolean {
  if (status === 'writing') return true;
  if (status !== 'rejected') return false;
  const created = Date.parse(createdAt.replace(' ', 'T') + '+09:00');
  return Number.isFinite(created) && created >= nowMs - resubmitDays * 86400_000;
}

export const dPlus = (entryDate: string, procedureDate: string): number | null => {
  if (!procedureDate) return null;
  const a = Date.parse(entryDate + 'T00:00:00Z'); const b = Date.parse(procedureDate + 'T00:00:00Z');
  return Number.isFinite(a) && Number.isFinite(b) ? Math.max(0, Math.floor((a - b) / 86400_000)) : null;
};

export const dot = (d: string) => (d ? d.slice(0, 10).replace(/-/g, '.') : '');

export function photoList(raw: unknown): string[] {
  try { const v = typeof raw === 'string' ? JSON.parse(raw) : raw; return Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : []; } catch { return []; }
}

/** progress_photo.php path rule: only files inside uploads/progress/<entry>/ with an image extension. */
export function photoFile(rel: string, entryId: number): string | null {
  const m = rel.match(new RegExp(`^uploads/progress/${entryId}/([A-Za-z0-9_\\-]+\\.(jpg|jpeg|png|gif|webp))$`));
  return m ? m[1] : null;
}
