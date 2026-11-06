import { Test } from '@nestjs/testing';
import { Global, Module } from '@nestjs/common';
import { DB } from '../src/db/db.module';
import { MemberNotifyModule } from '../src/member-notify/member-notify.module';
import { MemberNotifyService } from '../src/member-notify/member-notify.service';
import { VisitReminderService } from '../src/member-notify/visit-reminder.service';
import { kstDate, kstHour, windowOpen } from '../src/member-notify/visit-reminder.logic';

// 2026-11-06 01:30 UTC = 10:30 KST
const T_1030 = Date.UTC(2026, 10, 6, 1, 30);
// 2026-11-06 00:30 UTC = 09:30 KST
const T_0930 = Date.UTC(2026, 10, 6, 0, 30);

describe('visit reminder logic', () => {
  it('KST date / hour / window', () => {
    expect(kstDate(T_1030)).toBe('2026-11-06');
    expect(kstDate(T_1030, 1)).toBe('2026-11-07');
    expect(kstDate(Date.UTC(2026, 10, 6, 16, 0), 1)).toBe('2026-11-08'); // 01:00 KST next day
    expect(kstHour(T_1030)).toBe(10);
    expect(windowOpen(T_0930)).toBe(false);
    expect(windowOpen(T_1030)).toBe(true);
  });
});

describe('VisitReminderService (mock DB)', () => {
  let svc: VisitReminderService;
  let sql: { q: string; v: any[] }[] = [];
  let targets = [11, 12, 13];
  let claimOk: Record<number, number> = {};
  let hasCol = true;
  let notified: number[] = [];
  let notifyResult: any = { inApp: true, pushSent: 1, pushFailed: 0, reason: '' };
  const member = { booking: async (id: number) => { notified.push(id); if (id === 13) throw new Error('boom'); return notifyResult; } };
  const db = { query: async (q: string, v: any[] = []) => {
    sql.push({ q, v });
    if (q.startsWith('SHOW COLUMNS')) return [hasCol ? [{ Field: 'visit_reminder_sent_at' }] : []];
    if (q.startsWith('SELECT b.id FROM gangnamunni_bookings')) return [targets.map((id) => ({ id }))];
    if (q.startsWith('UPDATE gangnamunni_bookings SET visit_reminder_sent_at')) return [{ affectedRows: claimOk[v[0]] ?? 1 }];
    return [{ affectedRows: 1 }];
  } };
  beforeAll(async () => {
    @Global() @Module({ providers: [{ provide: DB, useValue: db }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, MemberNotifyModule] }).overrideProvider(MemberNotifyService).useValue(member).compile();
    svc = mod.get(VisitReminderService);
  });
  beforeEach(() => { sql = []; targets = [11, 12, 13]; claimOk = {}; hasCol = true; notified = []; notifyResult = { inApp: true, pushSent: 1, pushFailed: 0, reason: '' }; });

  it('before 10:00 KST nothing happens', async () => {
    expect(await svc.run(false, T_0930)).toMatchObject({ skippedWindow: true, targets: [] });
    expect(sql).toHaveLength(0);
  });
  it('selects tomorrow (KST), claims each booking first, then notifies; a failure counts and does not stop the run', async () => {
    const r = await svc.run(false, T_1030);
    expect(sql[1]).toEqual({ q: expect.stringContaining("b.status = 'confirmed' AND b.visit_reminder_sent_at IS NULL AND b.booking_date = ?"), v: ['2026-11-07'] });
    expect(sql[1].q).toContain("p.status NOT IN ('paid', 'partially_refunded')"); // an unpaid order = no reminder
    const claims = sql.filter((x) => x.q.startsWith('UPDATE')).map((x) => x.v[0]);
    expect(claims).toEqual([11, 12, 13]);
    expect(sql.find((x) => x.q.startsWith('UPDATE'))!.q).toContain("status = 'confirmed' AND visit_reminder_sent_at IS NULL");
    expect(notified).toEqual([11, 12, 13]);
    expect(r).toMatchObject({ date: '2026-11-07', reminded: 2, skipped: 0, failed: 1, targets: [11, 12, 13] });
  });
  it('a booking another run already claimed is skipped without a message', async () => {
    claimOk = { 12: 0 };
    const r = await svc.run(false, T_1030);
    expect(notified).toEqual([11, 13]);
    expect(r).toMatchObject({ reminded: 1, skipped: 1, failed: 1 });
  });
  it('switch off (noti_visit) counts as skipped; the booking stays claimed so it is not retried', async () => {
    targets = [11]; notifyResult = { inApp: false, pushSent: 0, pushFailed: 0, reason: 'visit_notifications_disabled' };
    expect(await svc.run(false, T_1030)).toMatchObject({ reminded: 0, skipped: 1 });
    expect(sql.some((x) => x.q.startsWith('UPDATE') && x.v[0] === 11)).toBe(true);
  });
  it('dry run lists the targets and writes nothing', async () => {
    const r = await svc.run(true, T_1030);
    expect(r.targets).toEqual([11, 12, 13]);
    expect(sql.some((x) => x.q.startsWith('UPDATE'))).toBe(false);
    expect(notified).toEqual([]);
  });
  it('missing column: nothing is done', async () => {
    hasCol = false;
    expect(await svc.run(false, T_1030)).toMatchObject({ targets: [], reminded: 0 });
    expect(sql).toHaveLength(1);
  });
});
