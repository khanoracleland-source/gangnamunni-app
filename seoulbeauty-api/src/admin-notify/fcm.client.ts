import { Injectable, Logger } from '@nestjs/common';
import { createSign } from 'node:crypto';
import { promises as fs } from 'node:fs';
import { b64url, buildFcmMessage, isDeadToken } from './admin-notify.logic';

interface ServiceAccount { project_id: string; private_key: string; client_email: string }
export type FcmResult = { ok: true } | { ok: false; dead: boolean };

/** HTTP v1 sender (port of includes/admin_fcm.php). The legacy server-key fallback is intentionally not ported. */
@Injectable()
export class FcmClient {
  private readonly log = new Logger(FcmClient.name);
  private sa: ServiceAccount | null | undefined;
  private tokenCache: { value: string; exp: number } | null = null;

  private async account(): Promise<ServiceAccount | null> {
    if (this.sa !== undefined) return this.sa;
    this.sa = null;
    const path = process.env.FCM_SERVICE_ACCOUNT_PATH;
    if (!path) { this.log.warn('FCM_SERVICE_ACCOUNT_PATH is not set; admin push is disabled'); return null; }
    try {
      const j = JSON.parse(await fs.readFile(path, 'utf8'));
      if (!j?.project_id || !j?.private_key || !j?.client_email) { this.log.error('FCM service account JSON is invalid'); return null; }
      const expected = process.env.FCM_EXPECTED_PROJECT_ID || 'hospital-8425b';
      if (String(j.project_id) !== expected) { this.log.error(`FCM project mismatch: expected ${expected}, got ${j.project_id}`); return null; }
      this.sa = { project_id: String(j.project_id), private_key: String(j.private_key), client_email: String(j.client_email) };
    } catch (err) {
      this.log.error(`FCM service account unreadable: ${(err as Error).message}`);
    }
    return this.sa;
  }

  private async accessToken(sa: ServiceAccount): Promise<string | null> {
    const now = Math.floor(Date.now() / 1000);
    if (this.tokenCache && this.tokenCache.exp > now + 60) return this.tokenCache.value;
    const head = b64url(JSON.stringify({ alg: 'RS256', typ: 'JWT' }));
    const claims = b64url(JSON.stringify({
      iss: sa.client_email, scope: 'https://www.googleapis.com/auth/firebase.messaging',
      aud: 'https://oauth2.googleapis.com/token', iat: now, exp: now + 3600,
    }));
    let jwt: string;
    try {
      const sig = createSign('RSA-SHA256').update(`${head}.${claims}`).sign(sa.private_key);
      jwt = `${head}.${claims}.${b64url(sig)}`;
    } catch { this.log.error('FCM private key could not be parsed'); return null; }
    try {
      const r = await fetch('https://oauth2.googleapis.com/token', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer', assertion: jwt }),
        signal: AbortSignal.timeout(10_000),
      });
      if (!r.ok) { this.log.error(`FCM OAuth2 exchange failed: HTTP ${r.status}`); return null; }
      const j: any = await r.json();
      if (!j?.access_token) return null;
      this.tokenCache = { value: String(j.access_token), exp: now + Math.min(3000, Number(j.expires_in) || 3000) };
      return this.tokenCache.value;
    } catch (err) { this.log.error(`FCM OAuth2 exchange error: ${(err as Error).message}`); return null; }
  }

  /** Whether sends can work at all (service account loads and the token exchange succeeds). */
  async session(): Promise<{ project: string; access: string } | null> {
    const sa = await this.account();
    if (!sa) return null;
    const access = await this.accessToken(sa);
    return access ? { project: sa.project_id, access } : null;
  }

  async send(sess: { project: string; access: string }, token: string, platform: unknown, title: string, body: string, link: string | null, adminId: number): Promise<FcmResult> {
    try {
      const r = await fetch(`https://fcm.googleapis.com/v1/projects/${sess.project}/messages:send`, {
        method: 'POST', headers: { Authorization: `Bearer ${sess.access}`, 'Content-Type': 'application/json' },
        body: JSON.stringify(buildFcmMessage(token, platform, title, body, link, adminId)), signal: AbortSignal.timeout(10_000),
      });
      if (r.status === 200) return { ok: true };
      const j = await r.json().catch(() => null);
      return { ok: false, dead: isDeadToken(r.status, j) };
    } catch (err) {
      this.log.warn(`FCM send error: ${(err as Error).message}`);
      return { ok: false, dead: false };
    }
  }
}
