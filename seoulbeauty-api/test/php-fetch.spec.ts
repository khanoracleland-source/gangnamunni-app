import * as http from 'node:http';
import { AddressInfo } from 'node:net';
import { phpFetch } from '../src/common/php-fetch';

/** The Host header must reach PHP verbatim (fetch() drops it); Set-Cookie headers must survive one by one. */
describe('phpFetch', () => {
  let server: http.Server;
  let url: string;
  let seen: http.IncomingMessage[] = [];

  beforeAll(async () => {
    server = http.createServer((req, res) => {
      seen.push(req);
      let body = '';
      req.on('data', (c) => (body += c));
      req.on('end', () => {
        res.setHeader('Set-Cookie', ['A=1; Path=/', 'B=2; Path=/; HttpOnly']);
        res.setHeader('Content-Type', 'application/json');
        res.statusCode = req.url?.endsWith('/redir') ? 302 : 200;
        if (req.url?.endsWith('/redir')) res.setHeader('Location', '/x');
        res.end(JSON.stringify({ host: req.headers.host, cookie: req.headers.cookie ?? '', method: req.method, body }));
      });
    });
    await new Promise<void>((r) => server.listen(0, '127.0.0.1', r));
    url = `http://127.0.0.1:${(server.address() as AddressInfo).port}/api/session_me.php`;
  });
  afterAll(async () => { await new Promise<void>((r) => server.close(() => r())); });
  beforeEach(() => { seen = []; });

  it('sends the Host header verbatim and relays every Set-Cookie', async () => {
    const res = await phpFetch(url, { headers: { host: 'hms.seoulbeauty.app', cookie: 'SBSESSID=abc' } });
    expect(res.status).toBe(200);
    expect(res.ok).toBe(true);
    const j = await res.json();
    expect(j).toMatchObject({ host: 'hms.seoulbeauty.app', cookie: 'SBSESSID=abc', method: 'GET' });
    expect(res.headers.getSetCookie()).toEqual(['A=1; Path=/', 'B=2; Path=/; HttpOnly']);
  });

  it('POSTs a body and does not follow redirects', async () => {
    const res = await phpFetch(url.replace('session_me.php', 'redir'), { method: 'POST', headers: { Host: 'hms.seoulbeauty.app', 'content-type': 'application/json' }, body: '{"a":1}' });
    expect(res.status).toBe(302);
    expect(res.ok).toBe(false);
    expect(await res.json()).toMatchObject({ method: 'POST', body: '{"a":1}', host: 'hms.seoulbeauty.app' });
  });

  it('falls back to fetch() when no Host header is given', async () => {
    const res = await phpFetch(url, { headers: { cookie: 'x=1' } });
    expect((await res.json()).host).toBe(new URL(url).host);
  });

  it('rejects on a connection error', async () => {
    await expect(phpFetch('http://127.0.0.1:1/api/session_me.php', { headers: { host: 'h' }, signal: AbortSignal.timeout(2000) })).rejects.toBeTruthy();
  });
});
