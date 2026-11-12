import { deviceId, duplicateIds, fixedPlatform, kstDay, kstHour } from '../src/maintenance/push-token-cleanup.logic';
import { PushTokenCleanupService } from '../src/maintenance/push-token-cleanup.service';

describe('push token cleanup logic', () => {
  it('device id from JSON, from broken JSON, or none', () => {
    expect(deviceId('{"device_id":" abc ","model":"iPhone"}')).toBe('abc');
    expect(deviceId('{"device_id":123}')).toBe('123');
    expect(deviceId('{"model":"x","device_id":"d1", broken')).toBe('d1');
    expect(deviceId('{"model":"x"}')).toBe('');
    expect(deviceId('{"device_id":{"a":1}}')).toBe('');
    expect(deviceId('')).toBe(''); expect(deviceId(null)).toBe(''); expect(deviceId('plain text')).toBe('');
  });
  it('only the newest active token of a member + platform + device stays', () => {
    const row = (pt_id: number, mb_id: string, pt_platform: string, dev: string | null, extra = '') => ({ pt_id, mb_id, pt_platform, pt_device_info: dev === null ? null : `{"device_id":"${dev}"${extra}}` });
    const rows = [row(9, 'a', 'ios', 'D1'), row(8, 'a', 'ios', 'D1'), row(7, 'a', 'android', 'D1'), row(6, 'b', 'ios', 'D1'), row(5, 'a', 'ios', 'D2'), row(4, 'a', 'ios', null), row(3, 'a', 'ios', null), row(2, 'a', 'ios', 'D1')];
    expect(duplicateIds(rows)).toEqual([8, 2]);
    // an iPhone token still stored as android counts as ios (step 1 would have fixed it)
    expect(duplicateIds([row(3, 'a', 'ios', 'D'), row(2, 'a', 'android', 'D', ',"model":"iPhone15"')])).toEqual([2]);
    expect(fixedPlatform('android', 'Galaxy')).toBe('android');
    expect(duplicateIds([])).toEqual([]);
  });
  it('KST hour and day', () => {
    const t = Date.UTC(2026, 10, 11, 15, 25); // 2026-11-12 00:25 KST
    expect(kstHour(t)).toBe(0); expect(kstDay(t)).toBe('2026-11-12');
  });
});

describe('PushTokenCleanupService', () => {
  const make = (hasTable = true) => {
    const calls: { sql: string; values?: unknown[] }[] = [];
    const db = { query: async (sql: string, values?: unknown[]) => {
      calls.push({ sql, values });
      if (/SHOW TABLES/.test(sql)) return [hasTable ? [{}] : []];
      if (/SELECT COUNT\(\*\) AS c/.test(sql)) return [[{ c: 3 }]];
      if (/SELECT pt_id/.test(sql)) return [[{ pt_id: 2, mb_id: 'a', pt_platform: 'ios', pt_device_info: '{"device_id":"D"}' }, { pt_id: 1, mb_id: 'a', pt_platform: 'ios', pt_device_info: '{"device_id":"D"}' }]];
      if (/GROUP BY/.test(sql)) return [[{ pt_platform: 'ios', pt_use: 1, cnt: 5 }]];
      return [{ affectedRows: 4 }];
    } };
    return { svc: new PushTokenCleanupService(db as any), calls };
  };
  it('dry run only reads', async () => {
    const { svc, calls } = make();
    const r = await svc.run(true);
    expect(r).toMatchObject({ dry: true, skipped: '', fixedPlatform: 3, emptyMember: 3, duplicates: 1, deletedInactive: 3, totals: [{ platform: 'ios', use: 1, count: 5 }] });
    expect(calls.some((c) => /^\s*(UPDATE|DELETE)/i.test(c.sql))).toBe(false);
  });
  it('real run: four steps in the PHP order, duplicates by id', async () => {
    const { svc, calls } = make();
    const r = await svc.run();
    expect(r).toMatchObject({ dry: false, fixedPlatform: 4, emptyMember: 4, duplicates: 4, deletedInactive: 4 });
    const writes = calls.filter((c) => /^\s*(UPDATE|DELETE)/i.test(c.sql)).map((c) => c.sql.replace(/\s+/g, ' ').trim());
    expect(writes).toHaveLength(4);
    expect(writes[0]).toMatch(/^UPDATE g5_push_tokens SET pt_platform = 'ios' WHERE pt_use = 1 AND pt_platform = 'android'/);
    expect(writes[1]).toMatch(/SET pt_use = 0 WHERE pt_use = 1 AND \(mb_id IS NULL OR mb_id = ''\) AND pt_datetime < DATE_SUB\(NOW\(\), INTERVAL 14 DAY\)/);
    expect(writes[2]).toMatch(/SET pt_use = 0 WHERE pt_use = 1 AND pt_id IN \(\?\)/);
    expect(calls.find((c) => /pt_id IN/.test(c.sql))!.values).toEqual([[1]]);
    expect(writes[3]).toBe('DELETE FROM g5_push_tokens WHERE pt_use = 0 AND pt_datetime < DATE_SUB(NOW(), INTERVAL 90 DAY)');
  });
  it('no table: nothing is done', async () => {
    const { svc, calls } = make(false);
    expect((await svc.run()).skipped).toBe('no_table');
    expect(calls).toHaveLength(1);
  });
});
