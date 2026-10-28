import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { mkdtempSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { ProgressModule } from '../src/progress/progress.module';
import { cut, dPlus, editable, photoFile, requiredEntries, validDate } from '../src/progress/progress.logic';
import { PointsService } from '../src/points/points.service';

const JPG = Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(100)]);
const TODAY = '2026-10-28';

describe('progress logic', () => {
  it('dates, cut, dPlus', () => {
    expect(validDate('2026-10-28', TODAY)).toBe('2026-10-28');
    expect(validDate('2026-10-29', TODAY)).toBe('');
    expect(validDate('2026-02-30', TODAY)).toBe('');
    expect(validDate('26-1-1', TODAY)).toBe('');
    expect(cut('  가나다라  ', 3)).toBe('가나다');
    expect(dPlus('2026-10-28', '2026-10-01')).toBe(27);
    expect(dPlus('2026-09-28', '2026-10-01')).toBe(0);
    expect(dPlus('2026-10-28', '')).toBeNull();
  });
  it('editable: writing, or rejected within the resubmit window', () => {
    const now = Date.parse('2026-10-28T12:00:00+09:00');
    expect(editable('writing', '2020-01-01 00:00:00', 30, now)).toBe(true);
    expect(editable('submitted', '2026-10-28 00:00:00', 30, now)).toBe(false);
    expect(editable('rejected', '2026-10-01 00:00:00', 30, now)).toBe(true);
    expect(editable('rejected', '2026-09-01 00:00:00', 30, now)).toBe(false);
  });
  it('settings: v2On chain and defaults', () => {
    const on = { schema_version: '3', v2_schema_version: '1', enabled: '1', v2_policy_enabled: '1', v2_progress_enabled: '1' };
    expect(PointsService.v2On(on, 'v2_progress_enabled')).toBe(true);
    expect(PointsService.v2On({ ...on, v2_policy_enabled: '0' }, 'v2_progress_enabled')).toBe(false);
    expect(PointsService.v2On({ ...on, enabled: '0' }, 'v2_progress_enabled')).toBe(false);
    expect(PointsService.v2On({ ...on, v2_point_use_enabled: '1' }, 'v2_point_use_enabled')).toBe(false); // lots_ready missing
    expect(requiredEntries({}, 'surgery')).toBe(10);
    expect(requiredEntries({ v2_progress_procedure_entries: '2' }, 'procedure')).toBe(2);
    expect(photoFile('uploads/progress/7/1700000000_1234_0.jpg', 7)).toBe('1700000000_1234_0.jpg');
    expect(photoFile('uploads/progress/8/x.jpg', 7)).toBeNull();
    expect(photoFile('uploads/progress/7/../etc/passwd', 7)).toBeNull();
  });
});

describe('progress API', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[];
  let settings: Record<string, string>; let review: any; let entries: any[]; let bookings: any[]; let dupDate: boolean;
  const dir = mkdtempSync(join(tmpdir(), 'prog-'));
  const realFetch = global.fetch;
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/FROM gangnamunni_point_settings/.test(sql)) return [Object.entries(settings).map(([skey, sval]) => ({ skey, sval }))];
    if (/FROM gangnamunni_bookings WHERE mb_id = \? AND status = 'completed'/.test(sql)) return [bookings];
    if (/FROM gangnamunni_bookings WHERE id = \? AND mb_id = \?/.test(sql)) return [bookings.filter((b) => b.id === args[0] && args[1] === 'kim')];
    if (/FROM gangnamunni_progress_reviews p .* WHERE p\.id = \? AND p\.member_id = \?/s.test(sql)) return [review && args[0] === review.id && args[1] === 42 ? [review] : []];
    if (/FROM gangnamunni_progress_reviews p .* WHERE p\.member_id = \?/s.test(sql)) return [review ? [review] : []];
    if (/^INSERT INTO gangnamunni_progress_reviews/.test(sql)) return [{ insertId: 11 }];
    if (/FROM gangnamunni_progress_entries WHERE progress_id = \? ORDER BY/.test(sql)) return [entries];
    if (/COUNT\(\*\) c FROM gangnamunni_progress_entries/.test(sql)) return [[{ c: dupDate ? 1 : 0 }]];
    if (/^INSERT INTO gangnamunni_progress_entries/.test(sql)) return [{ insertId: 501 }];
    if (/SELECT e\.photo_urls, p\.member_id, p\.status/.test(sql)) return [args[0] === 501 ? [{ photo_urls: JSON.stringify(lastPhotos), member_id: 42, status: 'writing' }] : []];
    if (/type = 'earn' LIMIT 1/.test(sql)) return [[]];
    return [{ affectedRows: 1 }];
  };
  let lastPhotos: string[] = [];
  beforeAll(async () => {
    process.env.PROGRESS_UPLOAD_DIR = dir;
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42, nickname: 'n', phone: '010' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: 'x', level: 2, ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => { const r = respond(sql, args); if (/UPDATE gangnamunni_progress_entries SET photo_urls/.test(sql)) lastPhotos = JSON.parse(args[0]); return r; } } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, ProgressModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.PROGRESS_UPLOAD_DIR; await app.close(); });
  beforeEach(() => {
    calls = []; dupDate = false; lastPhotos = [];
    settings = { schema_version: '3', v2_schema_version: '1', enabled: '1', v2_policy_enabled: '1', v2_progress_enabled: '1', v2_progress_procedure_entries: '2' };
    bookings = [{ id: 4, hospital_id: 9, hospital_name: '서울병원', treatment: '쌍꺼풀', booking_date: '2026-10-01' }];
    review = { id: 11, member_id: 42, kind: 'procedure', status: 'writing', title: '쌍꺼풀 경과 후기', treatment_name: '쌍꺼풀', hospital_name: '서울병원', procedure_date: '2026-10-01', created_at: '2026-10-20 10:00:00', entry_count: 1, reject_reason: null, awarded_point: 0 };
    entries = [{ id: 500, entry_date: '2026-10-05', content: 'c'.repeat(25), photo_urls: '["uploads/progress/500/a.jpg"]' }];
  });
  const H = { Cookie: 'sid=kim', 'X-Requested-With': 'XMLHttpRequest' };
  const srv = () => request(app.getHttpServer());

  it('401 / 403 (header)', async () => {
    await srv().get('/v1/mypage/progress').expect(401);
    await srv().post('/v1/mypage/progress').set('Cookie', 'sid=kim').send({}).expect(403);
  });
  it('feature off -> enabled:false, writes refused', async () => {
    settings.v2_progress_enabled = '0';
    expect((await srv().get('/v1/mypage/progress').set('Cookie', 'sid=kim').expect(200)).body).toEqual({ enabled: false });
    expect((await srv().get('/v1/mypage/progress/11').set('Cookie', 'sid=kim').expect(200)).body).toEqual({ enabled: false });
    await srv().post('/v1/mypage/progress').set(H).send({ treatment_name: 'x', procedure_date: '2026-10-01' }).expect(403);
    expect(calls.some((c) => /^INSERT/.test(c.sql))).toBe(false);
  });
  it('list: rules, bookings, cards with progress', async () => {
    const r = await srv().get('/v1/mypage/progress').set('Cookie', 'sid=kim').expect(200);
    expect(r.body.rules).toEqual({ procedure: { need: 2, point: 6000 }, surgery: { need: 10, point: 15000 } });
    expect(r.body.bookings).toEqual([{ id: 4, label: '2026-10-01 서울병원 · 쌍꺼풀' }]);
    expect(r.body.items[0]).toMatchObject({ id: 11, status: 'writing', statusLabel: '작성 중', kindLabel: '시술', entryCount: 1, need: 2, percent: 50, hospitalName: '서울병원' });
  });
  it('create from a booking fills treatment/date/hospital; validation', async () => {
    const r = await srv().post('/v1/mypage/progress').set(H).send({ booking_id: '4', kind: 'weird', title: '' }).expect(201);
    expect(r.body).toEqual({ success: true, id: 11 });
    const ins = calls.find((c) => /^INSERT INTO gangnamunni_progress_reviews/.test(c.sql))!;
    expect(ins.args).toEqual([42, 'kim', 9, 4, '쌍꺼풀', 'procedure', '2026-10-01', '쌍꺼풀 경과 후기']);
    expect((await srv().post('/v1/mypage/progress').set(H).send({ booking_id: '99', treatment_name: 'x' }).expect(400)).body.message).toBe('선택한 예약을 찾을 수 없습니다.');
    expect((await srv().post('/v1/mypage/progress').set(H).send({ procedure_date: '2026-10-01' }).expect(400)).body.message).toBe('시술·수술명을 입력해주세요.');
    expect((await srv().post('/v1/mypage/progress').set(H).send({ treatment_name: '코', procedure_date: '2999-01-01' }).expect(400)).body.message).toBe('시술일을 입력해주세요.');
    await srv().post('/v1/mypage/progress').set(H).send({ treatment_name: '코', kind: 'surgery', procedure_date: '2026-09-01', title: 't' }).expect(201);
    expect(calls.filter((c) => /^INSERT INTO gangnamunni_progress_reviews/.test(c.sql)).at(-1)!.args).toEqual([42, 'kim', null, null, '코', 'surgery', '2026-09-01', 't']);
  });
  it('detail: entries with round/D+/photo urls, editable', async () => {
    const r = await srv().get('/v1/mypage/progress/11').set('Cookie', 'sid=kim').expect(200);
    expect(r.body).toMatchObject({ enabled: true, id: 11, editable: true, need: 2, entryCount: 1, resubmitDays: 30 });
    expect(r.body.entries[0]).toMatchObject({ id: 500, round: 1, dateLabel: '2026.10.05', dPlus: 4, photos: ['/v1/mypage/progress/photo/500/0'] });
    await srv().get('/v1/mypage/progress/12').set('Cookie', 'sid=kim').expect(404);
  });
  it('add entry: rules, then photos stored and recorded', async () => {
    const post = (f: Record<string, string>, photos = 0) => { let r = srv().post('/v1/mypage/progress/11/entries').set(H); for (const [k, v] of Object.entries(f)) r = r.field(k, v); for (let i = 0; i < photos; i++) r = r.attach('photos', JPG, { filename: `p${i}.jpg` }); return r; };
    expect((await post({ content: '짧다', entry_date: '2026-10-10' }).expect(400)).body.message).toBe('경과 내용은 20자 이상 작성해주세요.');
    expect((await post({ content: 'x'.repeat(25), entry_date: '2026-09-10' }).expect(400)).body.message).toBe('기록 날짜는 시술일 이후여야 합니다.');
    expect((await post({ content: 'x'.repeat(25), entry_date: '2999-01-01' }).expect(400)).body.message).toBe('기록 날짜를 확인해주세요.');
    dupDate = true;
    expect((await post({ content: 'x'.repeat(25), entry_date: '2026-10-05' }).expect(400)).body.message).toBe('같은 날짜의 경과가 이미 있습니다.');
    dupDate = false;
    expect(calls.some((c) => /^INSERT INTO gangnamunni_progress_entries/.test(c.sql))).toBe(false);
    const r = await post({ content: '오늘은 붓기가 많이 빠졌습니다 통증 없음', entry_date: '2026-10-10', photo_consent: '1' }, 2).expect(201);
    expect(r.body).toEqual({ success: true, id: 501 });
    const ins = calls.find((c) => /^INSERT INTO gangnamunni_progress_entries/.test(c.sql))!;
    expect(ins.args).toEqual([11, '2026-10-10', '오늘은 붓기가 많이 빠졌습니다 통증 없음']);
    const up = calls.find((c) => /UPDATE gangnamunni_progress_entries SET photo_urls/.test(c.sql))!;
    expect(up.args[1]).toBe(1); expect(up.args[2]).toBe(501);
    expect(JSON.parse(up.args[0]).every((p: string) => /^uploads\/progress\/501\/\d+_\d+_\d\.jpg$/.test(p))).toBe(true);
    expect(readdirSync(join(dir, '501'))).toHaveLength(2);
    // the owner can fetch the photo; another index is 404; sniffed mime
    const ph = await srv().get('/v1/mypage/progress/photo/501/0').set('Cookie', 'sid=kim').expect(200);
    expect(ph.headers['content-type']).toContain('image/jpeg');
    expect(ph.headers['cache-control']).toContain('no-store');
    await srv().get('/v1/mypage/progress/photo/501/5').set('Cookie', 'sid=kim').expect(404);
    await srv().get('/v1/mypage/progress/photo/501/0').expect(401);
  });
  it('not editable (submitted) -> add/delete entry and submit refused', async () => {
    review.status = 'submitted';
    await srv().post('/v1/mypage/progress/11/entries').set(H).field('content', 'x'.repeat(25)).expect(400);
    await srv().delete('/v1/mypage/progress/11/entries/500').set(H).expect(400);
    await srv().post('/v1/mypage/progress/11/submit').set(H).expect(400);
    expect((await srv().delete('/v1/mypage/progress/11').set(H).expect(400)).body.message).toContain('검수 중');
    expect(calls.some((c) => /^(DELETE|UPDATE)/.test(c.sql))).toBe(false);
  });
  it('rejected inside the resubmit window is editable again', async () => {
    review.status = 'rejected'; review.created_at = '2026-10-25 00:00:00';
    await srv().delete('/v1/mypage/progress/11/entries/500').set(H).expect(200);
    expect(calls.find((c) => /^DELETE FROM gangnamunni_progress_entries/.test(c.sql))!.args).toEqual([500, 11]);
  });
  it('submit: needs the required entries, then waits for review', async () => {
    expect((await srv().post('/v1/mypage/progress/11/submit').set(H).expect(400)).body.message).toBe('경과를 2회 이상 기록해야 작성 완료할 수 있습니다.');
    review.entry_count = 2;
    const r = await srv().post('/v1/mypage/progress/11/submit').set(H).expect(201);
    expect(r.body.success).toBe(true);
    const up = calls.find((c) => /^UPDATE gangnamunni_progress_reviews SET status = 'submitted'/.test(c.sql))!;
    expect(up.args).toEqual([11, 42]);
  });
  it('delete: soft delete and the progress earn is looked up for reversal', async () => {
    await srv().delete('/v1/mypage/progress/11').set(H).expect(200);
    expect(calls.find((c) => /SET status = 'deleted'/.test(c.sql))!.args).toEqual([11, 42]);
    expect(calls.find((c) => /type = 'earn' LIMIT 1/.test(c.sql))!.args).toEqual(['progress', 11]);
  });
});
