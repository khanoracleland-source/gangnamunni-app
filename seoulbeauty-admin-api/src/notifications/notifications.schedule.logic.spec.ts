import { dbToMs, kstToMs, msToKst, nextMorning, parseScheduleBody, payloadToBody, requestIdOf, shouldDelay, summarize } from './notifications.schedule.logic';

const NOW = Date.UTC(2026, 10, 27, 3, 0); // 2026-11-27 12:00 KST
const base = { target: 'all', type: 'general', title: '공지', message: '내용', link: '/next/events', runAt: '2026-11-28T09:30' };

describe('notifications: planned sends (logic)', () => {
  it('reads Korean wall time and writes it back', () => {
    expect(kstToMs('2026-11-28T09:30')).toBe(Date.UTC(2026, 10, 28, 0, 30));
    expect(msToKst(Date.UTC(2026, 10, 28, 0, 30))).toBe('2026-11-28 09:30:00');
    expect(kstToMs('2026-11-28 09:30')).toBe(kstToMs('2026-11-28T09:30'));
    for (const bad of ['2026-02-30T10:00', '2026-11-28T25:00', '2026-11-28', 'tomorrow', '']) expect(kstToMs(bad)).toBeNull();
    expect(dbToMs('2026-11-28 09:30:00')).toBe(kstToMs('2026-11-28 09:30'));
    expect(dbToMs(null)).toBeNull();
  });

  it('finds the next 08:00 KST', () => {
    expect(msToKst(nextMorning(Date.UTC(2026, 10, 27, 13, 0)))).toBe('2026-11-28 08:00:00'); // 22:00 KST
    expect(msToKst(nextMorning(Date.UTC(2026, 10, 27, 16, 0)))).toBe('2026-11-28 08:00:00'); // 01:00 KST
    expect(msToKst(nextMorning(Date.UTC(2026, 10, 27, 23, 0)))).toBe('2026-11-28 08:00:00'); // 08:00 KST exactly
  });

  it('accepts a good plan and stores only the known fields', () => {
    const r = parseScheduleBody({ ...base, junk: 'x', requestId: 'ignored', expectedResolved: 9 }, NOW);
    expect(r).toMatchObject({ ok: true, runAt: '2026-11-28 09:30:00' });
    if (r.ok) expect(r.payload).toEqual({ target: 'all', type: 'general', eventId: 0, memberId: '', memberIds: [], title: '공지', message: '내용', link: '/next/events', nightPolicy: 'skip' });
  });

  it('refuses a bad plan with a code', () => {
    const code = (patch: object) => { const r = parseScheduleBody({ ...base, ...patch }, NOW); return r.ok ? 'ok' : r.error; };
    expect(code({ runAt: '2026-11-27T12:00' })).toBe('run_at_past');
    expect(code({ runAt: '2026-11-27T12:00:30' })).toBe('bad_run_at');
    expect(code({ runAt: '2027-03-01T10:00' })).toBe('run_at_too_far');
    expect(code({ runAt: undefined })).toBe('bad_run_at');
    expect(code({ test: true, target: 'individual', memberId: 'a' })).toBe('test_not_schedulable');
    expect(code({ link: 'https://evil.example' })).toBe('bad_link');
    expect(code({ title: '' })).toBe('title_required');
    expect(code({ target: 'nobody' })).toBe('bad_target');
    expect(code({ nightPolicy: 'later' })).toBe('bad_night_policy');
  });

  it('keeps the night choice', () => {
    const r = parseScheduleBody({ ...base, type: 'promotion', nightPolicy: 'delay' }, NOW);
    expect(r.ok && r.payload.nightPolicy).toBe('delay');
    expect(shouldDelay('promotion', 'delay', Date.UTC(2026, 10, 27, 14, 0))).toBe(true); // 23:00 KST
    expect(shouldDelay('promotion', 'delay', NOW)).toBe(false); // noon
    expect(shouldDelay('promotion', 'skip', Date.UTC(2026, 10, 27, 14, 0))).toBe(false);
    expect(shouldDelay('general', 'delay', Date.UTC(2026, 10, 27, 14, 0))).toBe(false); // a notice is not marketing
  });

  it('turns a stored plan back into a send body with its own request id', () => {
    const r = parseScheduleBody(base, NOW);
    const body = r.ok ? payloadToBody(JSON.stringify(r.payload), 41) : null;
    expect(body).toMatchObject({ requestId: 'sched_000000000041', expectedResolved: 0, test: false, title: '공지' });
    expect(requestIdOf(41)).toMatch(/^[A-Za-z0-9_-]{16,64}$/);
    expect(payloadToBody('{"target":"all"}', 1)).toBeNull();
    expect(payloadToBody('not json', 1)).toBeNull();
    expect(payloadToBody(JSON.stringify({ ...(r.ok ? r.payload : {}), link: 'javascript:alert(1)' }), 1)).toBeNull();
  });

  it('summarizes a row for the list, even a broken one', () => {
    expect(summarize({ id: 3, admin_id: 7, admin_username: 'boss', payload: JSON.stringify({ target: 'selected', type: 'event', title: 'T', memberIds: ['a', 'b'], nightPolicy: 'delay' }), run_at: '2026-11-28 09:30:00', status: 'pending', send_id: null, error: '' }))
      .toMatchObject({ id: 3, runAt: '2026-11-28 09:30', status: 'pending', title: 'T', memberCount: 2, nightPolicy: 'delay', sendId: null });
    expect(summarize({ id: 4, payload: 'broken', run_at: 'x', status: 'failed' })).toMatchObject({ id: 4, title: '', runAt: '', status: 'failed' });
  });
});
