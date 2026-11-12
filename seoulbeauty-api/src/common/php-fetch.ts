import * as http from 'node:http';
import * as https from 'node:https';

export interface PhpFetchInit {
  method?: string;
  headers?: Record<string, string>;
  body?: string;
  signal?: AbortSignal;
}

/**
 * fetch() for the PHP side (api/session_me.php, session_login.php).
 *
 * The Fetch standard lists `Host` as a forbidden request header, and Node's fetch (undici) silently drops it. With
 * PHP_SESSION_URL on loopback (http://127.0.0.1/…) PHP then sees HTTP_HOST=127.0.0.1: Apache may pick another vhost and
 * Gnuboard derives the session cookie name/domain from the host, so the session Nest minted (G5…PHPSESSID, host-only)
 * is not the one the browser's own PHP requests use (SBSESSID, .seoulbeauty.app) — the member looked signed out on every
 * Next.js page while PHP pages were signed in (2026-11-03). When a Host header is given, the request goes through
 * node:http so the header is sent verbatim; otherwise plain fetch() (what the tests mock) is used. Redirects are never
 * followed, like fetch(..., {redirect: 'manual'}).
 */
export function phpFetch(url: string, init: PhpFetchInit = {}): Promise<Response> {
  const headers = init.headers ?? {};
  const hostKey = Object.keys(headers).find((k) => k.toLowerCase() === 'host');
  if (!hostKey || !headers[hostKey]) {
    return fetch(url, { method: init.method, headers, body: init.body, redirect: 'manual', signal: init.signal });
  }
  const u = new URL(url);
  const lib = u.protocol === 'https:' ? https : http;
  return new Promise<Response>((resolve, reject) => {
    const req = lib.request(
      u,
      { method: init.method ?? 'GET', headers, signal: init.signal },
      (res) => {
        const chunks: Buffer[] = [];
        res.on('data', (c: Buffer) => chunks.push(c));
        res.on('error', reject);
        res.on('end', () => {
          const h: [string, string][] = [];
          for (const [k, v] of Object.entries(res.headers)) {
            if (v === undefined) continue;
            if (Array.isArray(v)) for (const x of v) h.push([k, x]);
            else h.push([k, v]);
          }
          const status = res.statusCode ?? 502;
          const body = Buffer.concat(chunks);
          // Response() refuses a body for 204/304 and statuses outside 200–599.
          const bodyAllowed = status >= 200 && status <= 599 && status !== 204 && status !== 205 && status !== 304;
          try {
            resolve(new Response(bodyAllowed ? body : null, { status: status >= 200 && status <= 599 ? status : 502, headers: h }));
          } catch (e) {
            reject(e);
          }
        });
      },
    );
    req.on('error', reject);
    if (init.body !== undefined) req.write(init.body);
    req.end();
  });
}
