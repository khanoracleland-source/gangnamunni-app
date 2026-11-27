import {
  deviceIdFromInfo, duplicateTokens, gateFor, isNight, kstHour, normalizeLink, NotificationType, parseAudience, Prefs, recipientsQuery, searchStaticLinks, splitRecipients,
  toPrefs, TYPES, usesTopic,
} from './notifications.logic';

const P = (o: Partial<Prefs> = {}): Prefs => ({ noti_booking: 1, noti_community: 1, noti_review: 1, mkt_push: 0, mkt_night_push: 0, ...o });
const at = (kstHourOfDay: number, minute = 0) => Date.UTC(2026, 10, 27, kstHourOfDay - 9, minute); // KST = UTC+9

describe('notifications: which switch decides a type', () => {
  it.each([
    ['booking', { kind: 'toggle', column: 'noti_booking' }],
    ['community', { kind: 'toggle', column: 'noti_community' }],
    ['review', { kind: 'toggle', column: 'noti_review' }],
    ['promotion', { kind: 'marketing' }],
    ['event', { kind: 'marketing' }],
    ['raffle', { kind: 'none' }],
    ['general', { kind: 'none' }],
    ['system', { kind: 'none' }],
  ])('%s -> %j', (type, gate) => expect(gateFor(type as NotificationType)).toEqual(gate));

  it('knows every type', () => expect(TYPES.every((t) => gateFor(t).kind)).toBe(true));

  it('uses the Android topic only for a notice sent to everybody', () => {
    expect(usesTopic('general', 'all')).toBe(true);
    expect(usesTopic('system', 'all')).toBe(true);
    expect(usesTopic('general', 'selected')).toBe(false);
    for (const t of ['booking', 'community', 'review', 'promotion', 'event', 'raffle'] as const) expect(usesTopic(t, 'all')).toBe(false);
  });
});

describe('notifications: night window (KST)', () => {
  it('is 21:00 up to 08:00', () => {
    expect(isNight(at(20, 59))).toBe(false);
    expect(isNight(at(21))).toBe(true);
    expect(isNight(at(23, 59))).toBe(true);
    expect(isNight(at(0))).toBe(true);
    expect(isNight(at(7, 59))).toBe(true);
    expect(isNight(at(8))).toBe(false);
    expect(isNight(at(12))).toBe(false);
  });
  it('reads the hour in KST, not in the server zone', () => expect(kstHour(Date.UTC(2026, 10, 26, 15, 30))).toBe(0));
});

describe('notifications: splitting the recipients by the member 설정', () => {
  const ids = ['on', 'off', 'mkt', 'mktNight', 'none'];
  const prefs = new Map<string, Prefs>([
    ['on', P()], ['off', P({ noti_booking: 0, noti_community: 0, noti_review: 0 })],
    ['mkt', P({ mkt_push: 1 })], ['mktNight', P({ mkt_push: 1, mkt_night_push: 1 })],
  ]);

  it.each(['booking', 'community', 'review'] as const)('%s: both channels follow the type switch; no 설정 row = on', (type) => {
    const s = splitRecipients(ids, prefs, type, false);
    expect(s.inApp).toEqual(['on', 'mkt', 'mktNight', 'none']);
    expect(s.push).toEqual(['on', 'mkt', 'mktNight', 'none']);
    expect(s).toMatchObject({ skippedInApp: 1, skippedPush: 1, nightSkipped: 0 });
  });

  it.each(['promotion', 'event'] as const)('%s by day: in-app for everybody, push only with 마케팅 푸시', (type) => {
    const s = splitRecipients(ids, prefs, type, false);
    expect(s.inApp).toEqual(ids);
    expect(s.push).toEqual(['mkt', 'mktNight']);
    expect(s).toMatchObject({ skippedInApp: 0, skippedPush: 3, nightSkipped: 0 });
  });

  it.each(['promotion', 'event'] as const)('%s at night: push only with 마케팅 푸시 and 야간 푸시, the rest keep the in-app row', (type) => {
    const s = splitRecipients(ids, prefs, type, true);
    expect(s.inApp).toEqual(ids);
    expect(s.push).toEqual(['mktNight']);
    expect(s).toMatchObject({ skippedPush: 3, nightSkipped: 1 });
  });

  it.each(['raffle', 'general', 'system'] as const)('%s is a notice: everybody, day or night, whatever the switches say', (type) => {
    for (const night of [false, true]) {
      const s = splitRecipients(ids, prefs, type, night);
      expect(s.inApp).toEqual(ids);
      expect(s.push).toEqual(ids);
      expect(s).toMatchObject({ skippedInApp: 0, skippedPush: 0, nightSkipped: 0 });
    }
  });

  it('reads a settings row: null falls back to the default, anything but 1 is off', () => {
    expect(toPrefs(undefined)).toEqual(P());
    expect(toPrefs({ noti_booking: null, mkt_push: '1', mkt_night_push: 2, noti_review: 0 })).toEqual(P({ mkt_push: 1, mkt_night_push: 0, noti_review: 0 }));
  });
});

describe('notifications: links', () => {
  it('accepts no link, the fixed pages and id pages', () => {
    for (const v of ['', undefined, null, '  ']) expect(normalizeLink(v)).toEqual({ ok: true, link: '' });
    for (const v of ['/next', '/next/events', '/next/mypage/notifications', '/next/hospitals/128', '/next/events/412', '/next/community/7', '/next/reviews/9']) {
      expect(normalizeLink(v)).toEqual({ ok: true, link: v });
    }
  });
  it('rejects other hosts, schemes, queries, fragments, other paths and bad ids', () => {
    for (const v of ['https://seoulbeauty.app/next', 'https://evil.example/next/events', '//evil.example', 'javascript:alert(1)', '/next/events?x=1', '/next/events#a', '/next/hospitals/0',
      '/next/hospitals/12/', '/next/hospitals/abc', '/next/api/v1/me', '/admin', 'my_bookings.php', '/next/../admin', 7, {}]) {
      expect(normalizeLink(v)).toEqual({ ok: false });
    }
  });
  it('searches the fixed pages by name or path', () => {
    expect(searchStaticLinks('이벤트').map((l) => l.path)).toEqual(['/next/events']);
    expect(searchStaticLinks('MYPAGE').length).toBeGreaterThan(3);
    expect(searchStaticLinks('zzz')).toEqual([]);
  });
});

describe('notifications: reading the audience of a body', () => {
  const ok = (b: unknown) => { const r = parseAudience(b); if (!r.ok) throw new Error(r.error); return r.audience; };
  const err = (b: unknown) => { const r = parseAudience(b); return r.ok ? '' : r.error; };

  it('parses every target', () => {
    expect(ok({ target: 'all', type: 'general' })).toMatchObject({ target: 'all', type: 'general' });
    expect(ok({ target: 'specific_event', type: 'event', eventId: '12' }).eventId).toBe(12);
    expect(ok({ target: 'individual', type: 'booking', memberId: ' kim01 ' }).memberId).toBe('kim01');
    expect(ok({ target: 'selected', type: 'review', memberIds: ['a', 'b', 'a', '', 7] }).memberIds).toEqual(['a', 'b', '7']);
  });
  it('answers an error code instead of a default', () => {
    expect(err(null)).toBe('bad_target');
    expect(err({ target: 'everybody', type: 'general' })).toBe('bad_target');
    expect(err({ target: 'all', type: 'visit' })).toBe('bad_type');
    expect(err({ target: 'specific_event', type: 'event' })).toBe('event_required');
    expect(err({ target: 'specific_event', type: 'event', eventId: '1 OR 1=1' })).toBe('event_required');
    expect(err({ target: 'individual', type: 'general', memberId: '' })).toBe('member_required');
    expect(err({ target: 'selected', type: 'general' })).toBe('members_required');
    expect(err({ target: 'selected', type: 'general', memberIds: [] })).toBe('members_required');
    expect(err({ target: 'selected', type: 'general', memberIds: Array.from({ length: 501 }, (_, i) => `m${i}`) })).toBe('too_many_members');
  });
});

describe('notifications: recipient SQL', () => {
  const a = (o: object) => { const r = parseAudience({ type: 'general', ...o }); if (!r.ok) throw new Error(r.error); return r.audience; };

  it('never includes members that left and never puts a value into the SQL text', () => {
    const cases = [a({ target: 'all' }), a({ target: 'recent_7days' }), a({ target: 'event_entrants' }), a({ target: 'specific_event', eventId: 5 }),
      a({ target: 'individual', memberId: "x' OR '1'='1" }), a({ target: 'selected', memberIds: ["a'--", 'b'] })];
    for (const c of cases) for (const withMember of [true, false]) {
      const q = recipientsQuery(c, withMember);
      expect(q.sql).toContain('mb_leave_date');
      expect(q.sql).not.toMatch(/OR '1'|a'--/);
      expect((q.sql.match(/\?/g) ?? []).length).toBe(q.values.length);
    }
  });
  it('matches event entries by member id, or by e-mail on an old table', () => {
    expect(recipientsQuery(a({ target: 'event_entrants' }), true).sql).toContain('m.mb_id = e.member_id');
    expect(recipientsQuery(a({ target: 'event_entrants' }), false).sql).toContain('m.mb_email = e.user_email');
    expect(recipientsQuery(a({ target: 'specific_event', eventId: 5 }), true).values).toEqual([5]);
  });
});

describe('notifications: token diagnostics helpers', () => {
  it('finds the device id in JSON or in broken JSON', () => {
    expect(deviceIdFromInfo('{"device_id":" d1 ","os":"ios"}')).toBe('d1');
    expect(deviceIdFromInfo('x "device_id": "d2" y')).toBe('d2');
    expect(deviceIdFromInfo('')).toBe('');
    expect(deviceIdFromInfo(null)).toBe('');
  });
  it('lists the devices with several active tokens, most first', () => {
    const row = (mb: string, dev: string, at: string) => ({ mb_id: mb, pt_platform: 'ios', pt_device_info: JSON.stringify({ device_id: dev }), pt_datetime: at });
    const d = duplicateTokens([row('a', 'd1', '2026-11-27 10:00:00'), row('a', 'd1', '2026-11-27 09:00:00'), row('b', 'd2', '2026-11-27 08:00:00'), row('c', 'd3', '2026-11-27 07:00:00'), row('c', 'd3', '2026-11-27 06:00:00'), row('c', 'd3', '2026-11-27 05:00:00')]);
    expect(d.map((x) => [x.memberId, x.count])).toEqual([['c', 3], ['a', 2]]);
  });
});
