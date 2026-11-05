import { createHash } from 'node:crypto';

/** KG Inicis INIPayPro (PayPro v2). Secrets come only from the server environment (Nest .env), never from the repo. */
export type InicisMode = 'test' | 'live';

export interface InicisConfig {
  mid: string;
  /** "모바일 금액위변조 Hash Key" (merchant admin > 상점정보 > 계약정보 > KEY 정보). */
  hashkey: string;
  mode: InicisMode;
  /** Public base the browser reaches this site on, including the Next basePath (no trailing slash). */
  publicBase: string;
  /** Native app URL scheme the card apps return to on iOS. */
  appScheme: string;
  /** Timeout of one call to Inicis (approval / net cancel), ms. INICIS_TIMEOUT_MS, clamped to 3 000..30 000, default 10 000. */
  timeoutMs: number;
}

/** P_IDCNAME values the manual lists. Anything else is refused (it becomes part of the approval host). */
export const IDC_CODES = ['fc', 'ks', 'stg'] as const;
export type IdcCode = (typeof IDC_CODES)[number];
export const isIdc = (v: unknown): v is IdcCode => typeof v === 'string' && (IDC_CODES as readonly string[]).includes(v);

/** Inicis' public test MIDs: they approve for real but are cancelled at midnight, so they can never be the live mode. */
const TEST_MID = /^(INIpayTest|INIBillTst|iniescrow0)$/;
/** INICIS_EXTRA_TEST_MIDS (comma separated) lets a new public test MID be added without a deploy of code. */
const isTestMid = (mid: string, env: NodeJS.ProcessEnv) => TEST_MID.test(mid) || (env.INICIS_EXTRA_TEST_MIDS ?? '').split(',').map((v) => v.trim()).filter(Boolean).includes(mid);

/** null = Inicis is not configured (checkout stays unavailable). INICIS_MODE=live needs a non-test MID. */
export function inicisConfig(env: NodeJS.ProcessEnv = process.env): InicisConfig | null {
  const mid = (env.INICIS_MID ?? '').trim();
  const hashkey = (env.INICIS_HASHKEY ?? '').trim();
  if (!mid || !hashkey) return null;
  const mode: InicisMode = (env.INICIS_MODE ?? 'test').trim() === 'live' ? 'live' : 'test';
  if (mode === 'live' && isTestMid(mid, env)) return null;
  if (mode === 'test' && !isTestMid(mid, env)) return null; // a real MID in test mode would charge real money but be booked as a test row
  const publicBase = (env.INICIS_PUBLIC_BASE ?? 'https://hms.seoulbeauty.app/next').trim().replace(/\/+$/, '');
  if (!/^https:\/\//.test(publicBase)) return null; // Android blocks an http P_NEXT_URL, and card data must not cross plain http
  const timeoutMs = Math.min(30_000, Math.max(3_000, Number(env.INICIS_TIMEOUT_MS) || 10_000));
  return { mid, hashkey, mode, publicBase, appScheme: (env.INICIS_APP_SCHEME ?? 'wellness').trim(), timeoutMs };
}

/** Ledger provider value (gangnamunni_platform_payments.provider): test rows are excluded from settlement. */
export const inicisProvider = (cfg: Pick<InicisConfig, 'mode'>): 'inicis' | 'inicis_test' => (cfg.mode === 'live' ? 'inicis' : 'inicis_test');

/** P_CHKFAKE: BASE64(SHA-512(P_AMT + P_OID + P_TIMESTAMP + HashKey)). */
export function chkFake(amt: string | number, oid: string, timestamp: string | number, hashkey: string): string {
  return createHash('sha512').update(`${amt}${oid}${timestamp}${hashkey}`).digest('base64');
}

/**
 * Who may use checkout. Both modes charge real cards, so both are allow-listed by member id (mb_id, comma separated):
 *   test -> INICIS_TEST_MEMBERS, live -> INICIS_LIVE_MEMBERS.
 * An empty list means nobody. In live mode the single value `*` opens checkout to everyone (public launch).
 */
export const inicisAllowedFor = (cfg: Pick<InicisConfig, 'mode'>, memberId: string, env: NodeJS.ProcessEnv = process.env): boolean => {
  const list = (cfg.mode === 'live' ? env.INICIS_LIVE_MEMBERS : env.INICIS_TEST_MEMBERS) ?? '';
  const ids = list.split(',').map((v) => v.trim()).filter(Boolean);
  if (memberId === '') return false; // never for an anonymous caller
  return (cfg.mode === 'live' && ids.includes('*')) || ids.includes(memberId);
};
