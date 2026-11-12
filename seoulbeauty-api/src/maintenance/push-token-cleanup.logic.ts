/** cleanup_device_id(): the device id inside pt_device_info (JSON, or a JSON-looking string that no longer parses). */
export function deviceId(info: unknown): string {
  if (typeof info !== 'string' || info === '') return '';
  try {
    const d = JSON.parse(info);
    if (d && typeof d === 'object' && !Array.isArray(d) && d.device_id != null && typeof d.device_id !== 'object') return String(d.device_id).trim();
  } catch { /* not JSON: try the pattern below */ }
  const m = /"device_id"\s*:\s*"([^"]+)"/.exec(info);
  return m ? m[1].trim() : '';
}

const APPLE = /iPhone|iPad|iPod/;
/** The platform after the "iPhone/iPad token saved as android" correction (step 1), so a dry run groups like a real run. */
export const fixedPlatform = (platform: unknown, info: unknown): string =>
  String(platform ?? '') === 'android' && typeof info === 'string' && APPLE.test(info) ? 'ios' : String(platform ?? '');

export interface TokenRow { pt_id: number; mb_id: string; pt_platform: string; pt_device_info: string | null }

/**
 * Active tokens of one member on one device (same platform + device_id): only the newest stays. `rows` must be ordered newest first
 * (pt_datetime DESC, pt_id DESC); tokens without a device id are never treated as duplicates.
 */
export function duplicateIds(rows: TokenRow[]): number[] {
  const seen = new Set<string>(); const out: number[] = [];
  for (const r of rows) {
    const id = deviceId(r.pt_device_info);
    if (id === '') continue;
    const key = `${r.mb_id}\n${fixedPlatform(r.pt_platform, r.pt_device_info)}\n${id}`;
    if (seen.has(key)) out.push(Number(r.pt_id)); else seen.add(key);
  }
  return out;
}

export interface CleanupSummary { dry: boolean; skipped: '' | 'no_table' | 'busy'; fixedPlatform: number; emptyMember: number; duplicates: number; deletedInactive: number; totals: { platform: string; use: number; count: number }[] }
/** KST hour (0–23) and date of an instant: the job runs once a day in a fixed KST hour. */
export const kstHour = (now: number): number => new Date(now + 9 * 3600_000).getUTCHours();
export const kstDay = (now: number): string => new Date(now + 9 * 3600_000).toISOString().slice(0, 10);
