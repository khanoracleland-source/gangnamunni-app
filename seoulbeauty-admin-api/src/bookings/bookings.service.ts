import { BadRequestException, Inject, Injectable, Logger, NotFoundException, PayloadTooLargeException, ServiceUnavailableException } from '@nestjs/common';
import { AdminScope } from '../admin-auth/admin.guard';
import { DB, Db } from '../db/db.module';
import { isMissingObject } from '../hospitals/hospitals.treatments.logic';
import {
  actionsFor, answersQuery, BookingItem, countQuery, detailQuery, doctorsQuery, EXPORT_HEADERS, EXPORT_MAX_ROWS, exportFileName, exportQuery, exportRow, hospitalOptionsQuery,
  isFail, listQuery, parseExportQuery, parseListQuery, parsePhotoUrls, parsePositiveInt, PER_PAGE, sheetName, Stats, statsQuery, toItem, toStats,
} from './bookings.logic';
import { buildXlsx, Sheet } from './xlsx';

export interface ListAnswer { items: BookingItem[]; total: number; page: number; perPage: number; totalPages: number; stats: Stats }
export interface HospitalOption { id: number; name: string; bookingCount: number; pendingCount: number; checkingCount: number; todayCount: number }

const rowsOf = (r: unknown): Record<string, unknown>[] => (Array.isArray(r) ? (r as Record<string, unknown>[]) : []);

/** Reading bookings: the list, the hospital choices, one booking's details and the Excel export. Everything is cut to the admin's scope. */
@Injectable()
export class BookingsService {
  private readonly log = new Logger('Bookings');
  constructor(@Inject(DB) private readonly db: Db) {}

  private async run(q: { sql: string; values: unknown[] }): Promise<Record<string, unknown>[]> {
    try {
      const [rows] = (await this.db.query(q.sql, q.values)) as any;
      return rowsOf(rows);
    } catch (e) {
      if (isMissingObject(e)) {
        this.log.warn(`bookings tables unavailable: ${(e as { code?: string })?.code || (e as Error)?.message}`);
        throw new ServiceUnavailableException({ error: 'bookings_unavailable' });
      }
      throw e;
    }
  }

  async list(scope: AdminScope, raw: Record<string, unknown>): Promise<ListAnswer> {
    const f = parseListQuery(raw);
    if (isFail(f)) throw new BadRequestException(f);
    const [countRows, statRows] = await Promise.all([this.run(countQuery(scope, f)), this.run(statsQuery(scope, f.hospitalId))]);
    const total = Number(countRows[0]?.cnt) || 0;
    const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    const page = Math.min(f.page, totalPages);
    const rows = await this.run(listQuery(scope, { ...f, page }));
    return { items: rows.map(toItem), total, page, perPage: PER_PAGE, totalPages, stats: toStats(statRows) };
  }

  async hospitals(scope: AdminScope): Promise<{ items: HospitalOption[] }> {
    const rows = await this.run(hospitalOptionsQuery(scope));
    return {
      items: rows.map((r) => ({
        id: Number(r.id), name: String(r.name ?? ''), bookingCount: Number(r.booking_count) || 0, pendingCount: Number(r.pending_count) || 0,
        checkingCount: Number(r.checking_count) || 0, todayCount: Number(r.today_count) || 0,
      })),
    };
  }

  /** The booking, what the member answered before the treatment, the photos and the hospital's active doctors (for assigning). */
  async detail(scope: AdminScope, rawId: string) {
    const id = parsePositiveInt(rawId);
    if (id === null) throw new NotFoundException({ error: 'not_found' });
    const rows = await this.run(detailQuery(scope, id));
    const r = rows[0];
    if (!r) throw new NotFoundException({ error: 'not_found' });
    const [answers, doctors] = await Promise.all([
      this.run(answersQuery(id)).catch((e) => { if (e instanceof ServiceUnavailableException) return []; throw e; }),
      this.run(doctorsQuery(Number(r.hospital_id) || 0)),
    ]);
    return {
      booking: { ...toItem(r), memo: String(r.memo ?? ''), cancelReason: String(r.cancel_reason ?? '') },
      photos: parsePhotoUrls(r.photo_urls),
      answers: answers.map((a) => ({ question: String(a.question ?? ''), answer: Number(a.answer) || 0 })),
      doctors: doctors.map((d) => ({ id: Number(d.id), name: String(d.name ?? '') })),
      actions: actionsFor(String(r.status)),
    };
  }

  /** One workbook; with more than one hospital every hospital gets its own sheet. Over EXPORT_MAX_ROWS rows is refused (413). */
  async exportXlsx(scope: AdminScope, raw: Record<string, unknown>): Promise<{ file: Buffer; name: string }> {
    const f = parseExportQuery(raw);
    if (isFail(f)) throw new BadRequestException(f);
    const rows = await this.run(exportQuery(scope, f));
    if (rows.length === 0) throw new NotFoundException({ error: 'no_rows' });
    if (rows.length > EXPORT_MAX_ROWS) throw new PayloadTooLargeException({ error: 'too_many_rows', max: EXPORT_MAX_ROWS });
    const groups = new Map<number, { name: string; rows: (string | number)[][] }>();
    for (const r of rows) {
      const hid = Number(r.hospital_id) || 0;
      const g = groups.get(hid) ?? { name: String(r.hospital_name ?? ''), rows: [] };
      g.rows.push(exportRow(r));
      groups.set(hid, g);
    }
    const taken = new Set<string>();
    const sheets: Sheet[] = [...groups.entries()].map(([hid, g]) => ({ name: sheetName(g.name, hid, taken), headers: EXPORT_HEADERS, rows: g.rows }));
    return { file: buildXlsx(sheets), name: exportFileName(f) };
  }
}
