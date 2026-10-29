import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { BookingModule } from '../src/booking/booking.module';
import { BookingService, fmtDose } from '../src/booking/booking.service';
import { AdminNotifyService } from '../src/admin-notify/admin-notify.service';
import { findActiveSame, memberAge, noshowAvailableFrom, noshowStatus, parseHours, revisitStatus, scheduleError } from '../src/booking/booking.logic';

const NOW = '2026-10-29 12:00:00'; // Thursday
const hours = (o: any) => parseHours(JSON.stringify(o));

describe('booking logic: hours and schedule', () => {
  const h = hours({ 월: '09:00~18:00', 화: '09:00~18:00', 목: '09:00~18:00', 점심: '12:00~13:00', 금: '10:00~14:00#biweekly:even' });
  it('parses weekly hours, lunch and biweekly', () => {
    expect(h.hasWeekly).toBe(true);
    expect(h.weekly[1]).toEqual({ open: 540, close: 1080, biweekly: null });
    expect(h.weekly[0]).toBeNull();
    expect(h.weekly[5]?.biweekly).toBe('even');
    expect([h.lunchStartMin, h.lunchEndMin]).toEqual([720, 780]);
  });
  it('accepts a normal slot', () => { expect(scheduleError(h, '2026-11-02', '10:00', NOW)).toBeNull(); });
  it('refuses a closed weekday, hours, lunch, bad format, far date, past', () => {
    expect(scheduleError(h, '2026-11-01', '10:00', NOW)).toContain('휴무일');
    expect(scheduleError(h, '2026-11-02', '19:00', NOW)).toContain('진료시간');
    expect(scheduleError(h, '2026-11-02', '12:30', NOW)).toContain('점심시간');
    expect(scheduleError(h, '2026-11-02', '10:15', NOW)).toBe('선택할 수 없는 예약 시간입니다.');
    expect(scheduleError(h, '2028-01-01', '10:00', NOW)).toBe('예약 날짜를 확인해주세요.');
    expect(scheduleError(h, '2026-02-30', '10:00', NOW)).toBe('예약 날짜를 확인해주세요.');
    expect(scheduleError(h, '2026-10-29', '09:00', NOW)).toContain('지난 시간');
  });
  it('biweekly: first and third Friday are "even", second and fourth "odd"', () => {
    expect(scheduleError(h, '2026-11-06', '10:00', NOW)).toBeNull();
    expect(scheduleError(h, '2026-11-13', '10:00', NOW)).toContain('휴무일');
    expect(scheduleError(h, '2026-11-20', '10:00', NOW)).toBeNull();
  });
  it('special closed / open dates', () => {
    const sp = hours({ 월: '09:00~18:00', special_closed_dates: ['2026-11-02'], special_open_dates: { '2026-11-01': '10:00~12:00' } });
    expect(scheduleError(sp, '2026-11-02', '10:00', NOW)).toContain('임시 휴무일');
    expect(scheduleError(sp, '2026-11-01', '11:00', NOW)).toBeNull();
    expect(scheduleError(sp, '2026-11-01', '13:00', NOW)).toContain('진료시간');
  });
  it('no hours at all keeps the old 09:00-18:00 rule', () => {
    const none = hours({});
    expect(scheduleError(none, '2026-11-01', '09:00', NOW)).toBeNull();
    expect(scheduleError(none, '2026-11-01', '08:30', NOW)).toBe('선택할 수 없는 예약 시간입니다.');
  });
  it('officeHours (English keys) and no_lunch_break', () => {
    const o = hours({ officeHours: { MON: '09:00-18:00' }, 점심: '12:00~13:00', no_lunch_break: true });
    expect(scheduleError(o, '2026-11-02', '12:00', NOW)).toBeNull();
    expect(scheduleError(o, '2026-11-03', '10:00', NOW)).toContain('휴무일');
  });
  it('broken JSON is "no hours"', () => { expect(parseHours('{oops').hasWeekly).toBe(false); });
});

describe('booking logic: member rules', () => {
  it('member age', () => {
    expect(memberAge('20000115', '2026-10-29')).toBe(26);
    expect(memberAge('2000-12-31', '2026-10-29')).toBe(25);
    expect(memberAge('00000000')).toBeNull();
    expect(memberAge('20271301')).toBeNull();
    expect(memberAge('20300101', '2026-10-29')).toBeNull();
  });
  it('revisit: strict wins, warn is the first recommended one', () => {
    const rules = [{ name: '보톡스', days: 90, strict: true }, { name: '필러', days: 30, strict: false }];
    const done = [{ booking_date: '2026-10-09', treatment: '필러', treatments_json: JSON.stringify([{ name: '필러' }, { name: '보톡스' }]) }];
    const r = revisitStatus(rules, done, '2026-10-29');
    expect(r.strict).toEqual({ treatment: '보톡스', interval_label: '3개월', days_since: 20, available_from: '1월 7일' });
    expect(r.warn?.treatment).toBe('필러');
    expect(revisitStatus(rules, [{ booking_date: '2026-01-01', treatment: '필러', treatments_json: null }], '2026-10-29')).toEqual({ strict: null, warn: null });
  });
  it('revisit falls back to the summary text of old rows', () => {
    const r = revisitStatus([{ name: '필러', days: 30, strict: false }], [{ booking_date: '2026-10-20', treatment: '필러, 보톡스', treatments_json: '' }], '2026-10-29');
    expect(r.warn?.days_since).toBe(9);
  });
  it('active duplicate: skips passed bookings, matches names, old rows by text', () => {
    const rows = [
      { id: 1, hospital_name: 'A', treatment: '필러', treatments_json: '[{"name":"필러"}]', booking_date: '2026-10-29', booking_time: '09:00', status: 'confirmed' },
      { id: 2, hospital_name: 'A', treatment: '보톡스', treatments_json: '', booking_date: '2026-11-03', booking_time: '14:30:00', status: 'pending' },
    ];
    expect(findActiveSame(rows, ['필러'], NOW)).toBeNull();
    expect(findActiveSame(rows, ['보톡스'], NOW)).toEqual({ id: 2, treatment: '보톡스', hospital_name: 'A', schedule: '11월 3일 (화) 오후 2:30', status: '예약 요청 접수' });
    expect(findActiveSame(rows, [], NOW)).toBeNull();
  });
  it('no-show window end-of-month correction and status', () => {
    expect(noshowAvailableFrom('2026-11-30', 3)).toBe('2027-03-01');
    expect(noshowAvailableFrom('2026-10-15', 3)).toBe('2027-01-15');
    const rows = [1, 2, 3].map((i) => ({ booking_date: `2026-10-0${i}`, booking_time: '10:00:00', hospital_name: 'A', status: i === 1 ? 'no_show' : 'cancelled', ev: `2026-10-0${i}` }));
    expect(noshowStatus(3, 3, true, rows)).toMatchObject({ count: 3, available_from: '1월 3일', has_cancel: true });
    expect(noshowStatus(4, 3, true, rows)).toBeNull();
  });
  it('dose formatting', () => { expect(fmtDose('12.50')).toBe('12.5'); expect(fmtDose(3)).toBe('3'); expect(fmtDose(0.25)).toBe('0.25'); });
});

describe('booking API', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[];
  let st: any;
  let notified: any[];
  const realFetch = global.fetch; let hmsCalls: any[]; let hmsReply: any;
  const baseState = () => ({
    settings: {} as Record<string, string>,
    hospital: { id: 7, name: 'Seoul <Clinic>', profile_image_url: '', business_hours: JSON.stringify({ 월: '09:00~18:00', 화: '09:00~18:00', 수: '09:00~18:00', 목: '09:00~18:00', 금: '09:00~18:00', 점심: '12:00~13:00' }), hms_hospital_id: '' },
    member: { mb_id: 'kim', mb_name: '김민', mb_hp: '010-1234-5678', mb_tel: '', mb_birth: '20000101' },
    treatments: [{ hospital_treatment_id: 1, treatment_id: 11, name: '보톡스', price_min: 50000, price_max: 90000, min_age: null, opt_min: 50000, opt_max: 90000 }],
    options: [{ id: 101, hospital_treatment_id: 1, option_name: '', body_part: '', dosage_value: '50.00', dosage_unit: 'U', price: 50000 }, { id: 102, hospital_treatment_id: 1, option_name: '', body_part: '', dosage_value: '100.00', dosage_unit: 'U', price: 90000 }],
    images: [{ image_url: 'a.jpg' }, { image_url: 'b.jpg' }] as any[],
    unit: null as any, units: {} as Record<string, any>,
    minAgeRequired: 0,
    activeSame: [] as any[], activeOther: [] as any[], noshowRows: [] as any[], done: [] as any[], revisitRules: [] as any[],
    pkg: null as any, pkgItems: [] as any[], masters: [] as any[], conflicts: [] as any[],
    insertId: 555, validQuestions: ['임신 중이신가요?'], failInsert: false,
    cols: { min_age: true, revisit_days: true, option_name: true, body_part: true, cancelled_by: true } as Record<string, boolean>,
  });
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/SHOW COLUMNS/.test(sql)) return [st.cols[args[0]] ? [{ Field: args[0] }] : []];
    if (/FROM gangnamunni_settings/.test(sql)) return [st.settings[args[0]] !== undefined ? [{ setting_value: st.settings[args[0]] }] : []];
    if (/FROM g5_member/.test(sql)) return [st.member && args[0] === st.member.mb_id ? [st.member] : []];
    if (/FROM gangnamunni_hospitals WHERE id/.test(sql)) return [args[0] === 7 ? [st.hospital] : []];
    if (/FROM gangnamunni_hospital_images/.test(sql)) return [st.images];
    if (/FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id IN/.test(sql)) return [st.options];
    if (/GROUP_CONCAT\(DISTINCT tm.name/.test(sql)) return [[]];
    if (/FROM gangnamunni_treatment_packages p\s+WHERE p.id/.test(sql)) return [st.pkg && args[0] === st.pkg.id ? [st.pkg] : []];
    if (/SELECT DISTINCT tm.name, pi.sort_order/.test(sql)) return [st.pkgItems];
    if (/SELECT id, name FROM gangnamunni_treatment_master/.test(sql)) return [st.masters];
    if (/FROM gangnamunni_treatment_conflicts/.test(sql)) return [st.conflicts];
    if (/FROM gangnamunni_treatment_questions q JOIN gangnamunni_hospital_treatments ht ON ht.id = q.hospital_treatment_id WHERE ht.hospital_id = \?\s*$/.test(sql)) return [st.validQuestions.map((q: string) => ({ question: q }))];
    if (/FROM gangnamunni_treatment_questions q/.test(sql)) return [[{ name: '보톡스', question: '임신 중이신가요?' }]];
    if (/AS option_count/.test(sql)) return [st.units[`${args[3]}:${args[0]}`] ? [st.units[`${args[3]}:${args[0]}`]] : []];
    if (/MAX\(ht.min_age\)/.test(sql)) return [[{ max_min_age: st.minAgeRequired }]];
    if (/FROM gangnamunni_hospital_treatments ht JOIN gangnamunni_treatment_master tm/.test(sql)) return [st.treatments];
    if (/revisit_days IS NOT NULL/.test(sql)) return [st.revisitRules];
    if (/status = 'completed'/.test(sql)) return [st.done];
    if (/hospital_id = \? AND status IN/.test(sql)) return [st.activeSame];
    if (/hospital_id <> \? AND status IN/.test(sql)) return [st.activeOther];
    if (/IF\(status = 'no_show'/.test(sql)) return [st.noshowRows];
    if (/INSERT INTO gangnamunni_bookings/.test(sql)) { if (st.failInsert) throw new Error('db down'); return [{ insertId: st.insertId, affectedRows: 1 }]; }
    return [{ affectedRows: 1 }];
  };
  beforeAll(async () => {
    global.fetch = jest.fn(async (url: any, init: any) => {
      if (String(url).includes('hms.test')) { hmsCalls.push({ url: String(url), init }); return new Response(JSON.stringify(hmsReply), { status: 200 }); }
      const who = (init?.headers?.cookie || '').includes('sid=kim');
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { id: 'kim', no: 42, name: 'x', nickname: 'n', level: 2, phone: '' } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args) } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, BookingModule] })
      .overrideProvider(AdminNotifyService).useValue({ bookingCreated: async (...a: any[]) => { notified.push(a); } })
      .compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.HMS_API_URL; delete process.env.HMS_API_KEY; await app.close(); });
  beforeEach(() => {
    calls = []; st = baseState(); notified = []; hmsCalls = []; hmsReply = { success: true, data: { id: 9001 } };
    BookingService.attempts.clear();
    delete process.env.HMS_API_URL; delete process.env.HMS_API_KEY;
    // 보톡스 has two priced options: choosing one is required (option_count 2)
    st.units['보톡스:101'] = { ht_min: 50000, option_id: 101, option_price: 50000, option_name: '', dosage_value: '50.00', dosage_unit: 'U', option_count: 2 };
    st.units['보톡스:0'] = { ht_min: 50000, option_id: 101, option_price: 50000, option_name: '', dosage_value: '50.00', dosage_unit: 'U', option_count: 2 };
    st.units['필러:0'] = { ht_min: 30000, option_id: 201, option_price: 30000, option_name: '', dosage_value: '1.00', dosage_unit: 'cc', option_count: 1 };
  });
  const api = () => request(app.getHttpServer());
  const authed = (r: request.Test) => r.set('Cookie', 'sid=kim').set('X-Requested-With', 'XMLHttpRequest');
  const body = (o: any = {}) => ({ hospital_id: 7, phone: '010-1234-5678', date: '2026-12-01', time: '10:00', memo: '', selection_type: 'treatments', treatments: [{ name: '보톡스', option_id: 101 }], ...o });
  const pre = (b: any) => authed(api().post('/v1/booking/precheck')).send(b);
  const insertCount = () => calls.filter((c) => /INSERT INTO gangnamunni_bookings/.test(c.sql)).length;

  it('context: anonymous is refused while a login is required', async () => {
    await api().get('/v1/booking/context/7').expect(401);
  });
  it('context: anonymous is allowed when phone booking is on', async () => {
    st.settings.booking_phone_auth = '1';
    const r = await api().get('/v1/booking/context/7').expect(200);
    expect(r.body.member).toMatchObject({ loggedIn: false, name: '' });
    expect(r.headers['cache-control']).toBe('private, no-store');
  });
  it('context: member data, treatments with options, hours, questions', async () => {
    const r = await authed(api().get('/v1/booking/context/7')).expect(200);
    expect(r.body.hospital.name).toBe('Seoul <Clinic>');
    expect(r.body.hospital.images).toEqual(['a.jpg', 'b.jpg']);
    expect(r.body.now).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
    expect(r.body.member).toEqual({ loggedIn: true, name: '김민', phone: '010-1234-5678', hasSavedPhone: true, age: expect.any(Number) });
    expect(r.body.treatments[0]).toMatchObject({ name: '보톡스', price_min: 50000, price_max: 90000 });
    expect(r.body.treatments[0].options.map((o: any) => o.name)).toEqual(['50U', '100U']);
    expect(r.body.hours.hasWeekly).toBe(true);
    expect(r.body.preQuestions).toEqual({ 보톡스: ['임신 중이신가요?'] });
    expect(r.body.noshow).toBeNull();
  });
  it('context: unknown hospital 404, bad id 400', async () => {
    await authed(api().get('/v1/booking/context/8')).expect(404);
    await authed(api().get('/v1/booking/context/abc')).expect(400);
  });

  it('create needs the same-origin header and a login', async () => {
    await api().post('/v1/booking').set('Cookie', 'sid=kim').send(body()).expect(403);
    await api().post('/v1/booking').set('X-Requested-With', 'XMLHttpRequest').send(body()).expect(401);
  });
  it('create: saves, prices from the DB, notifies, no HMS without a link', async () => {
    const r = await authed(api().post('/v1/booking')).send(body({ price: 1, name: 'forged' })).expect(201);
    expect(r.body).toEqual({ success: true, booking_id: 555, hms_appointment_id: null, detail_url: '/mypage/bookings/555?new=1' });
    const ins = calls.find((c) => /INSERT INTO gangnamunni_bookings/.test(c.sql))!;
    expect(ins.args.slice(0, 5)).toEqual([7, 'Seoul <Clinic>', '김민', '010-1234-5678', 'kim']);
    expect(ins.args[5]).toBe('보톡스 · 50U');
    expect(JSON.parse(ins.args[6])).toEqual([{ name: '보톡스', qty: 1, option_id: 101, option_name: '50U', dosage: '50U' }]);
    expect(ins.args.slice(7)).toEqual([1, '2026-12-01', '10:00', '', 50000]);
    expect(notified).toEqual([[555, 'Seoul <Clinic>', '김민', '2026-12-01', '10:00']]);
    expect(hmsCalls).toHaveLength(0);
  });
  it('create: two treatments add up', async () => {
    const r = await authed(api().post('/v1/booking')).send(body({ treatments: [{ name: '보톡스', option_id: 101 }, { name: '필러' }] })).expect(201);
    expect(r.body.booking_id).toBe(555);
    const ins = calls.find((c) => /INSERT INTO gangnamunni_bookings/.test(c.sql))!;
    expect(ins.args[5]).toBe('보톡스 · 50U, 필러 · 1cc');
    expect(ins.args[7]).toBe(2);
    expect(ins.args[11]).toBe(80000);
  });
  it('create: a package is priced x 10000 and stored with its items', async () => {
    st.pkg = { id: 5, name: '윤곽 패키지', package_price: 12 };
    st.pkgItems = [{ name: '보톡스' }, { name: '필러' }];
    await authed(api().post('/v1/booking')).send(body({ selection_type: 'package', package_id: 5, treatments: [] })).expect(201);
    const ins = calls.find((c) => /INSERT INTO gangnamunni_bookings/.test(c.sql))!;
    expect(ins.args[5]).toBe('[패키지] 윤곽 패키지');
    expect(ins.args[7]).toBe(1);
    expect(ins.args[11]).toBe(120000);
    expect(JSON.parse(ins.args[6]).map((t: any) => t.name)).toEqual(['보톡스', '필러']);
  });
  it('create: package refusals', async () => {
    expect((await authed(api().post('/v1/booking')).send(body({ selection_type: 'package', package_id: 0, treatments: [] })).expect(422)).body.message).toBe('유효한 패키지를 선택해주세요.');
    expect((await authed(api().post('/v1/booking')).send(body({ selection_type: 'package', package_id: 9, treatments: [] })).expect(422)).body.message).toBe('선택한 패키지는 현재 예약할 수 없습니다.');
    st.pkg = { id: 5, name: 'x', package_price: 1 };
    expect((await authed(api().post('/v1/booking')).send(body({ selection_type: 'package', package_id: 5, treatments: [] })).expect(422)).body.message).toBe('패키지에 예약 가능한 시술이 없습니다.');
    expect(insertCount()).toBe(0);
  });
  it('create: HMS-linked clinic sends the appointment and stores its id', async () => {
    st.hospital.hms_hospital_id = 'T1';
    process.env.HMS_API_URL = 'http://hms.test/api/external/'; process.env.HMS_API_KEY = 'k';
    const r = await authed(api().post('/v1/booking')).send(body({ memo: '메모' })).expect(201);
    expect(r.body.hms_appointment_id).toBe(9001);
    expect(hmsCalls[0].url).toBe('http://hms.test/api/external/appointments');
    expect(hmsCalls[0].init.headers['X-API-Key']).toBe('k');
    expect(JSON.parse(hmsCalls[0].init.body)).toMatchObject({ patient_name: '김민', appointment_datetime: '2026-12-01 10:00:00', tenant_id: 'T1', problem: '[서울미녀 예약] 시술: 보톡스 · 50U\n메모: 메모' });
    expect(calls.some((c) => /UPDATE gangnamunni_bookings SET hms_appointment_id/.test(c.sql) && c.args[0] === 9001 && c.args[1] === 555)).toBe(true);
  });
  it('create: an HMS failure keeps the booking', async () => {
    st.hospital.hms_hospital_id = 'T1';
    process.env.HMS_API_URL = 'http://hms.test/api/external'; process.env.HMS_API_KEY = 'k'; hmsReply = { success: false };
    const r = await authed(api().post('/v1/booking')).send(body()).expect(201);
    expect(r.body).toMatchObject({ success: true, booking_id: 555, hms_appointment_id: null });
  });
  it('create: HMS env missing keeps the booking too', async () => {
    st.hospital.hms_hospital_id = 'T1';
    const r = await authed(api().post('/v1/booking')).send(body()).expect(201);
    expect(r.body.hms_appointment_id).toBeNull();
    expect(hmsCalls).toHaveLength(0);
  });
  it('create: pre-check answers are saved only for real questions, once each', async () => {
    await authed(api().post('/v1/booking')).send(body({ pre_answers: JSON.stringify([{ q: '임신 중이신가요?', a: 0 }, { q: '임신 중이신가요?', a: 1 }, { q: '가짜 질문', a: 1 }]) })).expect(201);
    const ans = calls.filter((c) => /INSERT INTO gangnamunni_booking_answers/.test(c.sql));
    expect(ans.map((c) => c.args)).toEqual([[555, '임신 중이신가요?', 0]]);
  });
  it('precheck saves nothing', async () => {
    const r = await pre(body()).expect(201);
    expect(r.body).toEqual({ success: true, precheck: true });
    expect(calls.some((c) => /INSERT INTO/.test(c.sql))).toBe(false);
  });

  it('refusals use 422 with a message', async () => {
    const post = (b: any) => authed(api().post('/v1/booking')).send(b);
    expect((await post(body({ phone: '' })).expect(422)).body).toMatchObject({ success: false, message: '연락처를 입력해주세요.' });
    expect((await post(body({ phone: 'abc' })).expect(422)).body.message).toBe('연락처 형식을 확인해주세요.');
    expect((await post(body({ date: '2026-12-05' })).expect(422)).body.message).toContain('휴무일');
    expect((await post(body({ time: '12:30' })).expect(422)).body.message).toContain('점심시간');
    expect((await post(body({ treatments: [] })).expect(422)).body.message).toBe('최소 1개 이상의 시술 또는 패키지를 선택해주세요.');
    expect((await post(body({ treatments: Array.from({ length: 21 }, () => ({ name: 'x' })) })).expect(422)).body.message).toContain('최대 20개');
    expect((await post(body({ memo: 'x'.repeat(2001) })).expect(422)).body.message).toBe('입력한 내용이 허용 길이를 초과했습니다.');
    expect(insertCount()).toBe(0);
  });
  it('unknown, duplicated or option-less treatments are refused', async () => {
    const post = (b: any) => authed(api().post('/v1/booking')).send(b);
    const msg = '선택한 시술 중 현재 예약할 수 없는 항목이 있습니다.';
    expect((await post(body({ treatments: [{ name: '없는시술' }] })).expect(422)).body.message).toBe(msg);
    expect((await post(body({ treatments: [{ name: '보톡스', option_id: 101 }, { name: '보톡스', option_id: 101 }] })).expect(422)).body.message).toBe(msg);
    expect((await post(body({ treatments: [{ name: '보톡스' }] })).expect(422)).body.message).toBe(msg); // two priced options and none chosen
    expect((await post(body({ treatments: [{ name: '보톡스', option_id: 999 }] })).expect(422)).body.message).toBe(msg);
  });
  it('age rules', async () => {
    st.minAgeRequired = 19; st.member.mb_birth = '20120101';
    expect((await pre(body()).expect(422)).body).toMatchObject({ code: 'age_restricted', min_age: 19 });
    st.member.mb_birth = '';
    expect((await pre(body()).expect(422)).body.code).toBe('age_unknown');
    st.member.mb_birth = '19900101';
    await pre(body()).expect(201);
  });
  it('no-show block', async () => {
    st.noshowRows = [1, 2, 3].map((i) => ({ booking_date: `2026-10-0${i}`, booking_time: '10:00:00', hospital_name: 'A', status: 'no_show', ev: `2026-10-0${i}` }));
    const r = await pre(body()).expect(422);
    expect(r.body.code).toBe('noshow_blocked');
    expect(r.body.noshow.count).toBe(3);
  });
  it('duplicate: same hospital warns until confirmed; other hospital never passes', async () => {
    const row = { id: 3, hospital_name: 'Seoul', treatment: '보톡스', treatments_json: '[{"name":"보톡스"}]', booking_date: '2099-01-05', booking_time: '10:00:00', status: 'confirmed' };
    st.activeSame = [row];
    expect((await pre(body()).expect(422)).body).toMatchObject({ code: 'duplicate_booking', existing: { id: 3 } });
    await pre(body({ confirm_duplicate: '1' })).expect(201);
    st.activeSame = []; st.activeOther = [row];
    expect((await pre(body({ confirm_duplicate: '1' })).expect(422)).body.code).toBe('other_hospital_duplicate');
  });
  it('revisit: recommended warns until confirmed, strict always blocks', async () => {
    st.done = [{ booking_date: new Date(Date.now() + 9 * 3600_000 - 5 * 86400000).toISOString().slice(0, 10), treatment: '보톡스', treatments_json: '[{"name":"보톡스"}]' }];
    st.revisitRules = [{ name: '보톡스', revisit_days: 90, revisit_strict: 0 }];
    expect((await pre(body()).expect(422)).body).toMatchObject({ code: 'revisit_warning', revisit: { treatment: '보톡스', interval_label: '3개월' } });
    await pre(body({ confirm_revisit: '1' })).expect(201);
    st.revisitRules = [{ name: '보톡스', revisit_days: 90, revisit_strict: 1 }];
    expect((await pre(body({ confirm_revisit: '1' })).expect(422)).body.code).toBe('revisit_blocked');
  });
  it('treatment conflicts', async () => {
    const two = body({ treatments: [{ name: '보톡스', option_id: 101 }, { name: '필러' }] });
    st.masters = [{ id: 11, name: '보톡스' }, { id: 12, name: '필러' }];
    await pre(two).expect(201);
    st.conflicts = [{ tm_a_id: 11, tm_b_id: 12 }];
    expect((await pre(two).expect(422)).body).toMatchObject({ code: 'treatment_conflict', conflict: { names: ['보톡스', '필러'], pairs: [['보톡스', '필러']] } });
  });
  it('a DB failure on INSERT is a clean 500 with no admin notice', async () => {
    st.failInsert = true;
    const r = await authed(api().post('/v1/booking')).send(body());
    expect(r.status).toBe(500);
    expect(notified).toHaveLength(0);
  });
  it('rate limit: 30 requests in ten minutes per member', async () => {
    for (let i = 0; i < 30; i++) await pre(body({ phone: '' })).expect(422);
    await pre(body()).expect(429);
  });
});
