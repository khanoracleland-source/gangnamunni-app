import {
  absoluteLink, ANDROID_TOPIC, inferPlatform, isApnsAuthError, memberPushStatus, parseSendBody, pushData, retryable, runPool, sendWithRetry, tally, targetLabel, topicMessage,
  TokenOutcome,
} from './notifications.send.logic';

const REQ = 'req_0123456789abcdef';
const ok = (b: object) => { const r = parseSendBody({ target: 'all', type: 'general', title: '안내', requestId: REQ, expectedResolved: 3, ...b }); if (!r.ok) throw new Error(r.error); return r.input; };
const err = (b: object) => { const r = parseSendBody({ target: 'all', type: 'general', title: '안내', requestId: REQ, expectedResolved: 3, ...b }); return r.ok ? '' : r.error; };

describe('send: reading the body', () => {
  it('trims and keeps a valid send', () => {
    const i = ok({ title: '  제목  ', message: ' 내용 ', link: ' /next/events/4 ' });
    expect(i).toMatchObject({ title: '제목', message: '내용', link: '/next/events/4', test: false, expectedResolved: 3, requestId: REQ });
  });
  it('a real send needs the recipient count the admin saw; a test needs one member and no count', () => {
    expect(err({ expectedResolved: undefined })).toBe('expected_required');
    expect(err({ expectedResolved: '3' })).toBe('expected_required');
    expect(err({ expectedResolved: -1 })).toBe('expected_required');
    expect(err({ expectedResolved: 1.5 })).toBe('expected_required');
    expect(err({ test: true })).toBe('test_needs_individual');
    expect(ok({ test: true, target: 'individual', memberId: 'kim01', expectedResolved: undefined })).toMatchObject({ test: true, expectedResolved: null });
    expect(err({ test: 'true', target: 'all' })).toBe(''); // only the boolean true makes a test: a string is an ordinary send
  });
  it('answers an error code for a bad title, text, link or request id', () => {
    expect(err({ title: '   ' })).toBe('title_required');
    expect(err({ title: 'a'.repeat(201) })).toBe('title_too_long');
    expect(err({ title: '가'.repeat(200) })).toBe('');
    expect(err({ message: 'a'.repeat(1001) })).toBe('message_too_long');
    expect(err({ link: 'https://evil.example/' })).toBe('bad_link');
    expect(err({ link: '/next/events?x=1' })).toBe('bad_link');
    for (const id of ['', 'short', 'has space 0123456789', 'a'.repeat(65), "x'; DROP TABLE a;--0123456789"]) expect(err({ requestId: id })).toBe('bad_request_id');
    expect(err({ target: 'nobody' })).toBe('bad_target');
    expect(err({ type: 'visit' })).toBe('bad_type');
  });
  it('labels the audience for the history', () => {
    const a = (o: object) => { const r = parseSendBody({ type: 'general', title: 't', requestId: REQ, expectedResolved: 1, target: 'all', ...o }); if (!r.ok) throw new Error(r.error); return r.input.audience; };
    expect(targetLabel(a({}), 9)).toBe('전체 회원');
    expect(targetLabel(a({ target: 'specific_event', eventId: 5 }), 9)).toBe('이벤트 #5 응모자');
    expect(targetLabel(a({ target: 'individual', memberId: 'kim01' }), 1)).toBe('개별 회원: kim01');
    expect(targetLabel(a({ target: 'selected', memberIds: ['a', 'b'] }), 2)).toBe('선택 회원 2명');
  });
});

describe('send: messages', () => {
  it('makes the link absolute and gives the app the keys it reads', () => {
    expect(absoluteLink('/next/events/4', 'https://seoulbeauty.app/')).toBe('https://seoulbeauty.app/next/events/4');
    expect(absoluteLink('', 'https://seoulbeauty.app')).toBe('');
    expect(pushData('https://seoulbeauty.app/next')).toEqual({ type: 'member_notification', source: 'gangnamunni_admin', click_action: 'FLUTTER_NOTIFICATION_CLICK', url: 'https://seoulbeauty.app/next', webview_url: 'https://seoulbeauty.app/next' });
    expect(pushData('')).not.toHaveProperty('url');
  });
  it('builds the Android topic message', () => {
    expect(topicMessage('제목', '내용', { type: 'member_notification' })).toEqual({
      message: { topic: ANDROID_TOPIC, notification: { title: '제목', body: '내용' }, android: { priority: 'high', notification: { sound: 'default', channel_id: 'notification' } }, data: { type: 'member_notification' } },
    });
  });
  it('treats a token saved as android from an iPhone as ios', () => {
    expect(inferPlatform('android', '{"model":"iPhone15"}')).toBe('ios');
    expect(inferPlatform('ios', '')).toBe('ios');
    expect(inferPlatform('web', '')).toBe('web');
    expect(inferPlatform('', '')).toBe('android');
  });
});

describe('send: retry', () => {
  const sleeps: number[] = [];
  const sleep = async (ms: number) => { sleeps.push(ms); };
  beforeEach(() => { sleeps.length = 0; });

  it('retries only what may work the next time', () => {
    expect(retryable({ ok: false, dead: false, status: 0, code: 'network' })).toBe(true);
    expect(retryable({ ok: false, dead: false, status: 429 })).toBe(true);
    expect(retryable({ ok: false, dead: false, status: 503 })).toBe(true);
    expect(retryable({ ok: false, dead: false, status: 400 })).toBe(false);
    expect(retryable({ ok: false, dead: true, status: 404 })).toBe(false);
    expect(retryable({ ok: true })).toBe(false);
  });
  it('tries at most three times with growing waits and stops at the first success', async () => {
    let n = 0;
    const r = await sendWithRetry(async () => (++n < 3 ? { ok: false, dead: false, status: 503 } : { ok: true }), sleep);
    expect(r).toMatchObject({ ok: true, attempts: 3 });
    expect(sleeps).toEqual([300, 1000]);
    n = 0; sleeps.length = 0;
    const bad = await sendWithRetry(async () => { n++; return { ok: false, dead: false, status: 500 }; }, sleep);
    expect(bad).toMatchObject({ ok: false, attempts: 3 });
    expect(n).toBe(3);
  });
  it('does not retry a dead token or a rejected message', async () => {
    let n = 0;
    const r = await sendWithRetry(async () => { n++; return { ok: false, dead: true, status: 404, code: 'UNREGISTERED' }; }, sleep);
    expect(n).toBe(1);
    expect(r).toMatchObject({ ok: false, attempts: 1 });
    expect(sleeps).toEqual([]);
  });
  it('recognises the APNs key error on iOS only', () => {
    const r = { ok: false as const, dead: false, status: 401, code: 'THIRD_PARTY_AUTH_ERROR' };
    expect(isApnsAuthError('ios', r)).toBe(true);
    expect(isApnsAuthError('android', r)).toBe(false);
    expect(isApnsAuthError('ios', { ...r, status: 403 })).toBe(false);
  });
});

describe('send: bounded parallel loop', () => {
  it('never runs more than the limit at once and handles every item', async () => {
    let live = 0;
    let peak = 0;
    const items = Array.from({ length: 50 }, (_, i) => i);
    const done: number[] = [];
    const handled = await runPool(items, 7, async (i) => { live++; peak = Math.max(peak, live); await new Promise((r) => setTimeout(r, 2)); live--; done.push(i); });
    expect(handled).toBe(50);
    expect(done.sort((a, b) => a - b)).toEqual(items);
    expect(peak).toBeLessThanOrEqual(7);
    expect(peak).toBeGreaterThan(1);
  });
  it('stops starting items once told to', async () => {
    let started = 0;
    const handled = await runPool(Array.from({ length: 100 }, (_, i) => i), 5, async () => { started++; await new Promise((r) => setTimeout(r, 1)); }, () => started >= 20);
    expect(handled).toBeLessThan(100);
    expect(handled).toBeGreaterThanOrEqual(20);
  });
  it('copes with no items', async () => expect(await runPool([], 20, async () => undefined)).toBe(0));
});

describe('send: results per member', () => {
  const o = (memberId: string, platform: string, status: TokenOutcome['status'], code = ''): TokenOutcome => ({ token: `${memberId}${platform}${status}`, memberId, platform, status, code });
  it('is sent when any device got it, failed (with the first error) when every try failed, skipped when none was tried', () => {
    const m = memberPushStatus([o('a', 'ios', 'failed', 'UNREGISTERED'), o('a', 'android', 'sent'), o('b', 'ios', 'failed', 'http_500'), o('b', 'android', 'failed', 'network'), o('c', 'ios', 'skipped', 'apns_auth')]);
    expect(m.get('a')).toEqual({ status: 'sent', platform: 'android', code: '' });
    expect(m.get('b')).toMatchObject({ status: 'failed', code: 'http_500' });
    expect(m.get('c')).toMatchObject({ status: 'skipped', code: 'apns_auth' });
  });
  it('counts by platform', () => {
    const t = tally([o('a', 'ios', 'sent'), o('b', 'ios', 'failed', 'x'), o('c', 'android', 'sent'), o('d', 'android', 'skipped', 'deadline')]);
    expect(t).toMatchObject({ sent: 2, failed: 1, skipped: 1 });
    expect(t.platforms).toEqual({ ios: { tokens: 2, sent: 1, failed: 1, skipped: 0 }, android: { tokens: 2, sent: 1, failed: 0, skipped: 1 } });
  });
});
