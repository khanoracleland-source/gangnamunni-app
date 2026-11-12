import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { AuthModule } from '../src/auth/auth.module';
import { AuthService } from '../src/auth/auth.service';
import { clearDomains, cookieDomainOf, hashToken, IDLE_SECONDS, NestSessionService, readSessionCookie, readSessionCookies, REMEMBER_APP_SECONDS, REMEMBER_WEB_SECONDS, sessionCookie } from '../src/auth/nest-session.service';
import { SessionBridgeService } from '../src/session-bridge/session-bridge.service';

const T = 'a'.repeat(64);

describe('nest session helpers', () => {
  it('reads only a well-formed sb_sid', () => {
    expect(readSessionCookie(`x=1; sb_sid=${T}; y=2`)).toBe(T);
    expect(readSessionCookie(`sb_sid=${T}`)).toBe(T);
    expect(readSessionCookie('sb_sid=abc')).toBe('');
    expect(readSessionCookie(`xsb_sid=${T}`)).toBe('');
    expect(readSessionCookie(undefined)).toBe('');
    const U = 'b'.repeat(64);
    expect(readSessionCookies(`sb_sid=${T}; a=1; sb_sid=bad; sb_sid=${U}; sb_sid=${T}`)).toEqual([T, U]);
  });
  it('cookie text, the domain PHP uses, and the domains cleared at sign-out', () => {
    expect(sessionCookie(T, null, '')).toBe(`sb_sid=${T}; Path=/; HttpOnly; Secure; SameSite=Lax`);
    expect(sessionCookie(T, 60, '.seoulbeauty.app')).toBe(`sb_sid=${T}; Path=/; Domain=.seoulbeauty.app; Max-Age=60; HttpOnly; Secure; SameSite=Lax`);
    expect(cookieDomainOf(['PHPSESSID=x; path=/; domain=.seoulbeauty.app; HttpOnly', 'a=b'])).toBe('.seoulbeauty.app');
    expect(cookieDomainOf(['PHPSESSID=x; path=/'])).toBe('');
    expect(cookieDomainOf(['a=b; Domain=evil"x'])).toBe('');
    expect(clearDomains('hms.seoulbeauty.app:443', '')).toEqual(['.seoulbeauty.app']);
    expect(clearDomains('seoulbeauty.app', '.seoulbeauty.app')).toEqual(['.seoulbeauty.app']);
    expect(clearDomains('127.0.0.1', '')).toEqual([]);
    expect(clearDomains('localhost', '')).toEqual([]);
  });
});

describe('nest sessions (NEST_SESSIONS)', () => {
  let app: INestApplication;
  let rows: Map<string, { mb_id: string; cred_epoch: number; remember: number; left_s: number }>;
  let member: Record<string, any> | null;
  let sql: string[];
  let phpCalls: number;
  let dbDown: boolean;
  const realFetch = global.fetch;
  const query = async (q: string, p: any[] = []) => {
    sql.push(q);
    if (dbDown) throw new Error('db down');
    if (/^SELECT mb_cred_epoch FROM g5_member/.test(q)) return [member && member.mb_id === p[0] ? [{ mb_cred_epoch: member.mb_cred_epoch }] : []];
    if (/^INSERT INTO sb_sessions/.test(q)) { rows.set(p[0], { mb_id: p[1], cred_epoch: p[2], remember: p[3], left_s: p[6] }); return [{ affectedRows: 1 }]; }
    if (/^SELECT mb_id, cred_epoch, remember/.test(q)) { const r = rows.get(p[0]); return [r ? [r] : []]; }
    if (/^SELECT mb_no, mb_id, mb_name/.test(q)) return [member && member.mb_id === p[0] ? [member] : []];
    if (/^DELETE FROM sb_sessions WHERE token_hash/.test(q)) { rows.delete(p[0]); return [{ affectedRows: 1 }]; }
    if (/^UPDATE sb_sessions SET last_seen_at/.test(q)) { const r = rows.get(p[1]); if (r) r.left_s = p[0]; return [{ affectedRows: 1 }]; }
    return [[]];
  };
  const svc = () => app.get(NestSessionService);
  const token = (setCookie: string) => /sb_sid=([a-f0-9]{64})/.exec(setCookie)![1];

  beforeAll(async () => {
    global.fetch = jest.fn(async (u: any, init: any = {}) => {
      const url = new URL(String(u));
      if (url.pathname.endsWith('/session_me.php')) {
        phpCalls++;
        const ok = (init.headers?.cookie || '').includes('PHPSESSID=php-kim');
        return new Response(JSON.stringify(ok ? { loggedIn: true, user: { id: 'phpkim', no: 7, name: 'p', nickname: 'pn', level: 2, phone: '' } } : { loggedIn: false, user: null }), { status: 200 });
      }
      if (url.pathname.endsWith('/session_login.php')) return new Response(JSON.stringify({ ok: true }), { status: 200, headers: [['set-cookie', 'PHPSESSID=new; path=/; domain=.seoulbeauty.app; HttpOnly'], ['set-cookie', 'ck_auto=t; path=/']] as any });
      return new Response('{}', { status: 404 });
    }) as any;
    @Global() @Module({ providers: [{ provide: DB, useValue: { query } }], exports: [DB] }) class FakeDb {}
    const mod = await Test.createTestingModule({ imports: [FakeDb, AuthModule], providers: [SessionBridgeService] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(async () => { global.fetch = realFetch; delete process.env.NEST_SESSIONS; delete process.env.NEST_SESSION_ADOPT; delete process.env.SESSION_BRIDGE_SECRET; await app.close(); });
  beforeEach(() => {
    process.env.NEST_SESSIONS = '1'; delete process.env.NEST_SESSION_ADOPT;
    rows = new Map(); sql = []; phpCalls = 0; dbDown = false;
    member = { mb_no: 42, mb_id: 'kim', mb_name: '김', mb_nick: 'n', mb_level: 2, mb_hp: '010', mb_cred_epoch: 3, mb_leave_date: '', mb_intercept_date: '' };
    (svc() as any).cache.clear(); (app.get(AuthService) as any).cache.clear();
  });

  it('off: nothing is created, nothing is read, PHP is asked as before', async () => {
    process.env.NEST_SESSIONS = '0';
    expect(await svc().create('kim', { remember: true, app: false, source: 'login' })).toBeNull();
    expect(await svc().verify(`sb_sid=${T}`)).toEqual({ kind: 'none' });
    expect(sql).toEqual([]);
    expect((await app.get(AuthService).fromCookie(`sb_sid=${T}; PHPSESSID=php-kim`)).user?.id).toBe('phpkim');
    expect(phpCalls).toBe(1);
  });

  it('create: only the hash is stored; lifetimes follow remember / app', async () => {
    const plain = (await svc().create('kim', { remember: false, app: false, source: 'login', userAgent: 'UA' }))!;
    expect(plain).not.toMatch(/Max-Age/);
    const r1 = rows.get(hashToken(token(plain)))!;
    expect(r1).toMatchObject({ mb_id: 'kim', cred_epoch: 3, remember: 0, left_s: IDLE_SECONDS });
    expect([...rows.keys()]).not.toContain(token(plain));
    expect(await svc().create('kim', { remember: true, app: false, source: 'login' })).toContain(`Max-Age=${REMEMBER_WEB_SECONDS}`);
    expect(await svc().create('kim', { remember: true, app: true, source: 'kakao', domain: '.seoulbeauty.app' })).toMatch(new RegExp(`Domain=\\.seoulbeauty\\.app; Max-Age=${REMEMBER_APP_SECONDS}`));
    expect(await svc().create('nobody', { remember: false, app: false, source: 'login' })).toBeNull();
    expect(await svc().create('bad id!', { remember: false, app: false, source: 'login' })).toBeNull();
  });

  it('create replaces the session this browser had', async () => {
    const first = token((await svc().create('kim', { remember: false, app: false, source: 'login' }))!);
    await svc().create('kim', { remember: false, app: false, source: 'login', replaces: `sb_sid=${first}` });
    expect(rows.has(hashToken(first))).toBe(false);
    expect(rows.size).toBe(1);
  });

  it('a valid session answers without PHP', async () => {
    const t = token((await svc().create('kim', { remember: true, app: false, source: 'login' }))!);
    const s = await app.get(AuthService).fromCookie(`PHPSESSID=dead; sb_sid=${t}`);
    expect(s).toEqual({ loggedIn: true, user: { id: 'kim', no: 42, name: '김', nickname: 'n', level: 2, phone: '010' } });
    expect(phpCalls).toBe(0);
    const me = await request(app.getHttpServer()).get('/v1/me').set('Cookie', `sb_sid=${t}`).expect(200);
    expect(me.body.loggedIn).toBe(true);
    expect(phpCalls).toBe(0);
  });

  it('ends on: expiry, a moved epoch (password change), a block, a leave date, a deleted member; then PHP decides', async () => {
    const fresh = async () => token((await svc().create('kim', { remember: true, app: false, source: 'login' }))!);
    let t = await fresh();
    rows.get(hashToken(t))!.left_s = 0;
    expect(await svc().verify(`sb_sid=${t}`)).toEqual({ kind: 'invalid' });
    expect(rows.has(hashToken(t))).toBe(false);
    t = await fresh(); member!.mb_cred_epoch = 4;
    expect((await svc().verify(`sb_sid=${t}`)).kind).toBe('invalid');
    member!.mb_cred_epoch = 3;
    t = await fresh(); member!.mb_intercept_date = '20200101';
    expect((await svc().verify(`sb_sid=${t}`)).kind).toBe('invalid');
    member!.mb_intercept_date = '29990101'; // a block date in the future does not count yet (same as PHP)
    t = await fresh();
    expect((await svc().verify(`sb_sid=${t}`)).kind).toBe('ok');
    member!.mb_intercept_date = ''; member!.mb_leave_date = '20200101';
    t = await fresh();
    expect((await svc().verify(`sb_sid=${t}`)).kind).toBe('invalid');
    member = null;
    expect((await svc().verify(`sb_sid=${T}`)).kind).toBe('invalid');
    // an invalid Nest session does not sign the browser out of a PHP session that is alive
    expect((await app.get(AuthService).fromCookie(`sb_sid=${T}; PHPSESSID=php-kim`)).user?.id).toBe('phpkim');
    expect((await app.get(AuthService).fromCookie(`sb_sid=${T}`)).loggedIn).toBe(false);
  });

  it('three idle hours slide for a session without remember; a remembered one keeps its end', async () => {
    const t = token((await svc().create('kim', { remember: false, app: false, source: 'login' }))!);
    rows.get(hashToken(t))!.left_s = IDLE_SECONDS - 60;
    await svc().verify(`sb_sid=${t}`, true);
    expect(sql.some((q) => /^UPDATE sb_sessions/.test(q))).toBe(false); // touched less than half an hour ago
    rows.get(hashToken(t))!.left_s = 600;
    await svc().verify(`sb_sid=${t}`, true);
    expect(rows.get(hashToken(t))!.left_s).toBe(IDLE_SECONDS);
    const r = token((await svc().create('kim', { remember: true, app: false, source: 'login' }))!);
    rows.get(hashToken(r))!.left_s = 600; sql = [];
    await svc().verify(`sb_sid=${r}`, true);
    expect(sql.some((q) => /^UPDATE sb_sessions/.test(q))).toBe(false);
  });

  it('a database error is not cached and falls back to PHP', async () => {
    const t = token((await svc().create('kim', { remember: true, app: false, source: 'login' }))!);
    dbDown = true;
    expect((await app.get(AuthService).fromCookie(`sb_sid=${t}; PHPSESSID=php-kim`)).user?.id).toBe('phpkim');
    dbDown = false;
    expect((await app.get(AuthService).fromCookie(`sb_sid=${t}; PHPSESSID=dead`)).user?.id).toBe('kim');
  });

  it('logout: needs the same-origin header, removes the row and both cookie forms, twice is fine', async () => {
    const t = token((await svc().create('kim', { remember: true, app: false, source: 'login' }))!);
    await request(app.getHttpServer()).post('/v1/auth/logout').set('Cookie', `sb_sid=${t}`).expect(403);
    expect(rows.size).toBe(1);
    const r = await request(app.getHttpServer()).post('/v1/auth/logout').set({ Cookie: `sb_sid=${t}`, 'X-Requested-With': 'XMLHttpRequest', Host: 'hms.seoulbeauty.app' }).expect(200);
    expect(rows.size).toBe(0);
    const set = r.headers['set-cookie'] as unknown as string[];
    expect(set).toEqual(['sb_sid=; Path=/; Max-Age=0; HttpOnly; Secure; SameSite=Lax', 'sb_noadopt=1; Path=/; Max-Age=60; HttpOnly; Secure; SameSite=Lax', 'sb_sid=; Path=/; Domain=.seoulbeauty.app; Max-Age=0; HttpOnly; Secure; SameSite=Lax']);
    expect((await app.get(AuthService).fromCookie(`sb_sid=${t}`)).loggedIn).toBe(false);
    await request(app.getHttpServer()).post('/v1/auth/logout').set('X-Requested-With', 'XMLHttpRequest').expect(200);
  });

  it('two sb_sid cookies: a stale one does not hide the valid one; sign-out removes both rows', async () => {
    const good = token((await svc().create('kim', { remember: true, app: false, source: 'login' }))!);
    const stale = token((await svc().create('kim', { remember: true, app: false, source: 'login' }))!);
    rows.get(hashToken(stale))!.left_s = 0;
    expect((await app.get(AuthService).fromCookie(`sb_sid=${stale}; sb_sid=${good}`)).user?.id).toBe('kim');
    expect(phpCalls).toBe(0);
    await svc().destroy(`sb_sid=${stale}; sb_sid=${good}`);
    expect(rows.size).toBe(0);
  });

  it('adoption: /v1/me gives a PHP-born sign-in its Nest session once, only to a same-origin browser fetch', async () => {
    const me = (h: Record<string, string>) => request(app.getHttpServer()).get('/v1/me').set({ Cookie: 'PHPSESSID=php-kim', ...h });
    member = { ...member!, mb_id: 'phpkim', mb_no: 7 };
    // its own switch: NEST_SESSIONS alone adopts nothing
    expect((await me({ 'Sec-Fetch-Site': 'same-origin' }).expect(200)).headers['set-cookie']).toBeUndefined();
    process.env.NEST_SESSION_ADOPT = '1';
    // just signed out here (the PHP logout has not run yet): not adopted back
    expect((await request(app.getHttpServer()).get('/v1/me').set({ Cookie: 'PHPSESSID=php-kim; sb_noadopt=1', 'Sec-Fetch-Site': 'same-origin' }).expect(200)).headers['set-cookie']).toBeUndefined();
    // a server-side call (no Sec-Fetch-Site) or a cross-site one: answered, nothing created
    expect((await me({}).expect(200)).headers['set-cookie']).toBeUndefined();
    expect((await me({ 'Sec-Fetch-Site': 'cross-site' }).expect(200)).headers['set-cookie']).toBeUndefined();
    expect(rows.size).toBe(0);
    const r = await me({ 'Sec-Fetch-Site': 'same-origin' }).expect(200);
    expect(r.body.loggedIn).toBe(true);
    const set = (r.headers['set-cookie'] as unknown as string[])[0];
    expect(set).toMatch(/^sb_sid=[a-f0-9]{64}; Path=\/; HttpOnly; Secure; SameSite=Lax$/); // never "remember"
    expect([...rows.values()]).toEqual([expect.objectContaining({ mb_id: 'phpkim', remember: 0, left_s: IDLE_SECONDS })]);
    // from now on the cookie answers; PHP is not asked and nothing more is created
    phpCalls = 0;
    const again = await request(app.getHttpServer()).get('/v1/me').set({ Cookie: `PHPSESSID=php-kim; sb_sid=${token(set)}`, 'Sec-Fetch-Site': 'same-origin' }).expect(200);
    expect(again.headers['set-cookie']).toBeUndefined();
    expect(phpCalls).toBe(0);
    expect(rows.size).toBe(1);
    // a visitor is never given a session; switched off, nobody is
    expect((await request(app.getHttpServer()).get('/v1/me').set('Sec-Fetch-Site', 'same-origin').expect(200)).headers['set-cookie']).toBeUndefined();
    process.env.NEST_SESSIONS = '0'; (app.get(AuthService) as any).cache.clear();
    expect((await me({ 'Sec-Fetch-Site': 'same-origin' }).expect(200)).headers['set-cookie']).toBeUndefined();
    expect(rows.size).toBe(1);
  });

  it('the session bridge opens a Nest session after the PHP one, on the same cookie domain, last in the list', async () => {
    process.env.SESSION_BRIDGE_SECRET = 'x'.repeat(40);
    const bridge = app.get(SessionBridgeService);
    const old = token((await svc().create('kim', { remember: false, app: false, source: 'login' }))!);
    const r = await bridge.mint('kim', 'login', true, `sb_patient_app=1; sb_sid=${old}`, 'UA', 'Kakao');
    expect(r.ok).toBe(true);
    expect(r.setCookies).toHaveLength(3);
    expect(r.setCookies[2]).toMatch(new RegExp(`^sb_sid=[a-f0-9]{64}; Path=/; Domain=\\.seoulbeauty\\.app; Max-Age=${REMEMBER_APP_SECONDS}; HttpOnly; Secure; SameSite=Lax$`));
    expect(rows.has(hashToken(old))).toBe(false);
    expect([...rows.values()][0]).toMatchObject({ mb_id: 'kim', remember: 1 });
    // sign-up: never "remember"; switched off: the PHP cookies only
    const reg = await bridge.mint('kim', 'register', true, '', 'UA');
    expect(reg.setCookies[2]).not.toMatch(/Max-Age/);
    process.env.NEST_SESSIONS = '0';
    expect((await bridge.mint('kim', 'login', true, '', 'UA')).setCookies).toHaveLength(2);
  });
});
