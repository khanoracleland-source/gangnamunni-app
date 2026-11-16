import { DEFAULT_PER_PAGE, PER_PAGE_OPTIONS } from "./api";

/** Page numbers and sizes of the archive list, kept in the address. */
export type ArchiveQuery = { page: number; perPage: number };

export function parseArchiveQuery(params: URLSearchParams): ArchiveQuery {
  const page = Number(params.get("page"));
  const perPage = Number(params.get("perPage"));
  return {
    page: Number.isInteger(page) && page >= 1 && page <= 100000 ? page : 1,
    perPage: (PER_PAGE_OPTIONS as readonly number[]).includes(perPage) ? perPage : DEFAULT_PER_PAGE,
  };
}

export function archiveHref(query: ArchiveQuery): string {
  const params = new URLSearchParams();
  if (query.page > 1) params.set("page", String(query.page));
  if (query.perPage !== DEFAULT_PER_PAGE) params.set("perPage", String(query.perPage));
  const qs = params.toString();
  return qs ? `/hospital-archive?${qs}` : "/hospital-archive";
}

/** Fired by the archive pages so the sidebar badge follows what they just learned (or did). */
export const ARCHIVE_DUE_EVENT = "admin:archive-due";

export function announceArchiveDue(count: number): void {
  window.dispatchEvent(new CustomEvent<number>(ARCHIVE_DUE_EVENT, { detail: count }));
}

/** "2026-12-12 04:00:00" -> "2026-12-12 04:00" (as stored, no conversion). */
export function minutePrecision(value: string): string {
  const m = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/.exec(value);
  return m ? `${m[1]} ${m[2]}` : value;
}

/** Is "YYYY-MM-DD HH:MM:SS" (Korean time as stored) still ahead of now? Read with +09:00, so the browser's time zone plays no part. */
export function isFutureKst(value: string, now: number = Date.now()): boolean {
  const m = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::(\d{2}))?/.exec(value);
  if (!m) return false;
  const at = Date.parse(`${m[1]}T${m[2]}:${m[3] ?? "00"}+09:00`);
  return Number.isFinite(at) && at > now;
}
