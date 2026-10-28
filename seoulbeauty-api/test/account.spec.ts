import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { mkdtempSync, existsSync, readdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { AccountModule } from '../src/account/account.module';
import { birthToInput, checkProfile, isSettingField, photoKind, validBirth } from '../src/account/account.logic';

const TODAY = '2026-10-28';
const JPG = Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(100)]);
const PNG = Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(50)]);

describe('account logic', () => {
  it('birth', () => {
    expect(birthToInput('19900131')).toBe('1990-01-31');
    expect(birthToInput('1990-01-31')).toBe('1990-01-31');
    expect(birthToInput('19900231')).toBe('');
    expect(birthToInput('00000000')).toBe('');
    expect(birthToInput(null)).toBe('');
    expect(validBirth('2026-10-28', TODAY)).toBe(true);
    expect(validBirth('2026-10-29', TODAY)).toBe(false);
    expect(validBirth('1906-10-28', TODAY)).toBe(true);
    expect(validBirth('1906-10-27', TODAY)).toBe(false);
    expect(validBirth('1990-02-30', TODAY)).toBe(false);
    expect(validBirth('abc', TODAY)).toBe(false);
  });
  it('profile checks (PHP order: name, birth, nick)', () => {
    expect(checkProfile('', 'a', '', '1990-01-01', TODAY)?.field).toBe('name');
    expect(checkProfile('홍', 'a', '', '', TODAY)?.field).toBe('birth');
    expect(checkProfile('홍', '한글', '', '1990-01-01', TODAY)?.field).toBe('nick');
    expect(checkProfile('홍', 'a_1', '010-1234-5678', '1990-01-01', TODAY)).toBeNull();
    expect(checkProfile('홍', 'a_1', 'x;drop', '1990-01-01', TODAY)?.field).toBe('hp');
  });
  it('photo sniff and settings whitelist', () => {
    expect(photoKind(JPG)?.ext).toBe('jpg');
    expect(photoKind(PNG)?.ext).toBe('png');
    expect(photoKind(Buffer.from('GIF89a....'))?.ext).toBe('jpg');
    expect(photoKind(Buffer.from('<?php echo 1;'))).toBeNull();
    expect(isSettingField('mkt_sms')).toBe(true);
    expect(isSettingField('noti_chat')).toBe(false);
    expect(isSettingField('id; DROP')).toBe(false);
  });
});

describe('account API', () => {
  let app: INestApplication;
  let calls: { sql: string; args: any[] }[];
  let member: any; let nickTaken: boolean; let recentBirth: number; let failBirthLog: boolean;
  const dir = mkdtempSync(join(tmpdir(), 'prof-'));
  const realFetch = global.fetch;
  const respond = (sql: string, args: any[]): any => {
    calls.push({ sql, args });
    if (/FROM g5_member WHERE mb_id = \?/.test(sql)) return [[member]];
    if (/mb_nick = \? AND mb_id != \?/.test(sql)) return [nickTaken ? [{ mb_id: 'other' }] : []];
    if (/gangnamunni_member_birth_log WHERE/.test(sql)) { if (failBirthLog) throw new Error('no table'); return [[{ c: recentBirth }]]; }
    if (/FROM gangnamunni_notification_settings/.test(sql)) return [[{ noti_general: 0, noti_booking: 1, noti_event: 1, noti_promo: 1, noti_push: 1, noti_message: 1, mkt_push: 1, mkt_sms: 0, mkt_email: 0, mkt_night_push: 0 }]];
    return [{ affectedRows: 1 }];
  };
  beforeAll(async () => {
    process.env.PROFILE_PHOTO_DIR = dir;
    global.fetch = jest.fn(async (_u: any, init: any) => {
      const c: string = init?.headers?.cookie || '';
      const who = c.includes('sid=kim') ? { id: 'kim', no: 42, nickname: 'n', phone: '010-1234-5678' } : null;
      return new Response(JSON.stringify(who ? { loggedIn: true, user: { name: 'x', level: 2, ...who } } : { loggedIn: false, user: null }), { status: 200 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: { query: async (sql: string, args: any[] = []) => respond(sql, args) } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule, AccountModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.PROFILE_PHOTO_DIR; await app.close(); });
  beforeEach(() => { calls = []; member = { mb_nick: 'old', mb_name: '기존', mb_hp: '010', mb_birth: '19900101', mb_email: 'a@b.c' }; nickTaken = false; recentBirth = 0; failBirthLog = false; });
  const H = { Cookie: 'sid=kim', 'X-Requested-With': 'XMLHttpRequest' };
  const save = (fields: Record<string, string>, file?: Buffer) => {
    let r = request(app.getHttpServer()).post('/v1/mypage/profile').set(H);
    for (const [k, v] of Object.entries(fields)) r = r.field(k, v);
    if (file) r = r.attach('photo', file, { filename: 'p.jpg' });
    return r;
  };
  const ok = { nick: 'new_nick', name: '새이름', hp: '010-9999-0000', birth: '1990-01-01' };

  it('401 for visitors, 403 without the header', async () => {
    await request(app.getHttpServer()).get('/v1/mypage/profile').expect(401);
    await request(app.getHttpServer()).post('/v1/mypage/profile').set('Cookie', 'sid=kim').field('nick', 'x').expect(403);
  });
  it('GET profile', async () => {
    const r = await request(app.getHttpServer()).get('/v1/mypage/profile').set('Cookie', 'sid=kim').expect(200);
    expect(r.headers['cache-control']).toContain('no-store');
    expect(r.body).toMatchObject({ nick: 'old', name: '기존', birth: '1990-01-01', email: 'a@b.c', photoUrl: null, birthChangeDays: 30 });
  });
  it('saves; unchanged birth writes no birth log', async () => {
    const r = await save(ok).expect(201);
    expect(r.body).toMatchObject({ success: true, photoError: null });
    const upd = calls.find((c) => /^UPDATE g5_member/.test(c.sql))!;
    expect(upd.args).toEqual(['new_nick', '새이름', '010-9999-0000', '19900101', 'kim']);
    expect(calls.some((c) => /INSERT INTO gangnamunni_member_birth_log/.test(c.sql))).toBe(false);
    expect(calls.some((c) => /birth_log WHERE/.test(c.sql))).toBe(false);
  });
  it('a save drops the cached session, so the next request sees the new nickname', async () => {
    await request(app.getHttpServer()).get('/v1/mypage/profile').set('Cookie', 'sid=kim').expect(200);
    const f = global.fetch as jest.Mock;
    const before = f.mock.calls.length;
    await request(app.getHttpServer()).get('/v1/mypage/profile').set('Cookie', 'sid=kim').expect(200);
    expect(f.mock.calls.length).toBe(before); // cached
    await save(ok).expect(201);
    const afterSave = f.mock.calls.length;
    await request(app.getHttpServer()).get('/v1/mypage/profile').set('Cookie', 'sid=kim').expect(200);
    expect(f.mock.calls.length).toBe(afterSave + 1);
  });
  it('validation errors carry the field and write nothing', async () => {
    const r = await save({ ...ok, nick: '한글' }).expect(400);
    expect(r.body).toMatchObject({ field: 'nick' });
    expect((await save({ ...ok, birth: '2999-01-01' }).expect(400)).body.field).toBe('birth');
    expect(calls.some((c) => /^UPDATE/.test(c.sql))).toBe(false);
  });
  it('taken nick -> 409', async () => {
    nickTaken = true;
    expect((await save(ok).expect(409)).body).toMatchObject({ field: 'nick', message: '이미 사용 중인 닉네임입니다.' });
    expect(calls.some((c) => /^UPDATE/.test(c.sql))).toBe(false);
  });
  it('birth change: once per 30 days, logged when allowed', async () => {
    recentBirth = 1;
    expect((await save({ ...ok, birth: '1991-02-02' }).expect(409)).body.field).toBe('birth');
    expect(calls.some((c) => /^UPDATE/.test(c.sql))).toBe(false);
    recentBirth = 0;
    await save({ ...ok, birth: '1991-02-02' }).expect(201);
    const log = calls.find((c) => /INSERT INTO gangnamunni_member_birth_log/.test(c.sql))!;
    expect(log.args.slice(0, 3)).toEqual(['kim', '19900101', '19910202']);
  });
  it('birth change is denied when the history cannot be read', async () => {
    failBirthLog = true;
    await save({ ...ok, birth: '1991-02-02' }).expect(409);
  });
  it('first birth (none stored) needs no history check', async () => {
    member.mb_birth = '';
    failBirthLog = true;
    await save(ok).expect(201);
    expect(calls.some((c) => /birth_log WHERE/.test(c.sql))).toBe(false);
  });
  it('photo: stored by member id, other extensions removed; fake image rejected before saving', async () => {
    writeFileSync(join(dir, 'kim.png'), PNG);
    expect((await save(ok, JPG).expect(201)).body.photoError).toBeNull();
    expect(readdirSync(dir).sort()).toEqual(['kim.jpg']);
    const r = await save(ok, Buffer.from('<?php system($_GET[1]);')).expect(400);
    expect(r.body).toMatchObject({ field: 'photo' });
    expect(calls.filter((c) => /^UPDATE g5_member/.test(c.sql)).length).toBe(1); // only the first, valid save
    expect(existsSync(join(dir, 'kim.jpg'))).toBe(true);
  });
  it('settings: read with stored values, write one whitelisted field', async () => {
    const g = await request(app.getHttpServer()).get('/v1/mypage/notification-settings').set('Cookie', 'sid=kim').expect(200);
    expect(g.body.settings).toMatchObject({ noti_general: 0, mkt_push: 1, mkt_sms: 0 });
    calls = [];
    await request(app.getHttpServer()).post('/v1/mypage/notification-settings').set(H).send({ field: 'mkt_sms', value: '1' }).expect(201);
    expect(calls.map((c) => c.sql)).toEqual(['INSERT IGNORE INTO gangnamunni_notification_settings (member_id) VALUES (?)', 'UPDATE gangnamunni_notification_settings SET mkt_sms = ? WHERE member_id = ?']);
    expect(calls[1].args).toEqual([1, 'kim']);
    calls = [];
    await request(app.getHttpServer()).post('/v1/mypage/notification-settings').set(H).send({ field: 'mb_password', value: 1 }).expect(422);
    expect(calls.length).toBe(0);
  });
});
