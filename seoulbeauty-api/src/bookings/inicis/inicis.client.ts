import { chkFake, IdcCode, InicisConfig, isIdc } from './inicis.config';

/** Thrown when the outcome of a call to Inicis is unknown (timeout, network, unparsable answer). */
export class InicisUnknownOutcome extends Error {}

export type InicisFetch = (url: string, init: { method: string; headers: Record<string, string>; body: string; signal: AbortSignal; redirect: 'error' }) => Promise<{ ok: boolean; status: number; text(): Promise<string> }>;

const TIMEOUT_MS = 10_000;

/** The approval and net-cancel hosts are `{fc|ks|stg}paypro.inicis.com`: the code is checked, never interpolated unchecked. */
export function inicisHost(idc: string): string {
  if (!isIdc(idc)) throw new Error('Unknown Inicis IDC code.');
  return `${idc}paypro.inicis.com`;
}

/** Inicis answers `KEY=VALUE&KEY=VALUE`; values are raw (not URL-encoded) and may contain `=`. */
export function parseInicisBody(text: string): Record<string, string> {
  const out: Record<string, string> = {};
  for (const part of text.split('&')) {
    const i = part.indexOf('=');
    if (i <= 0) continue;
    out[part.slice(0, i)] = part.slice(i + 1);
  }
  return out;
}

const form = (o: Record<string, string>) => Object.entries(o).map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&');

async function post(path: string, idc: IdcCode, fields: Record<string, string>, f: InicisFetch): Promise<Record<string, string>> {
  let text: string;
  try {
    const res = await f(`https://${inicisHost(idc)}${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8' },
      body: form(fields),
      signal: AbortSignal.timeout(TIMEOUT_MS),
      redirect: 'error',
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    text = await res.text();
  } catch (e) {
    throw new InicisUnknownOutcome((e as Error).message);
  }
  const parsed = parseInicisBody(text);
  if (!parsed.P_STATUS) throw new InicisUnknownOutcome('Unparsable Inicis answer.');
  return parsed;
}

/** payAppl.ini: the actual approval (승인) after a successful authentication (P_STATUS=00). */
export function approve(cfg: InicisConfig, a: { idc: IdcCode; authTid: string; amt: number }, f: InicisFetch = fetch as unknown as InicisFetch) {
  return post('/payment/v1/rest/payAppl.ini', a.idc, { P_MID: cfg.mid, P_AUTH_TID: a.authTid, P_AMT: String(a.amt), P_CHARSET: 'UTF-8' }, f);
}

/**
 * payNetCancel.ini (망취소): only when handling an approval result failed after Inicis may have approved
 * (within 10 min of the auth TID / 1 min of the approval TID). Never a normal cancellation.
 * NOTE: P_CHKFAKE uses the same formula as the payment request (manual p. "망취소"); confirm against INIpayTest.
 */
export function netCancel(cfg: InicisConfig, a: { idc: IdcCode; authTid: string; amt: number; oid: string; reason: string }, f: InicisFetch = fetch as unknown as InicisFetch, now: () => number = Date.now) {
  const ts = String(now());
  return post('/payment/v1/rest/payNetCancel.ini', a.idc, {
    P_MID: cfg.mid, P_AUTH_TID: a.authTid, P_AMT: String(a.amt), P_OID: a.oid, P_CANCEL_MSG: a.reason.slice(0, 100),
    P_TIMESTAMP: ts, P_CHKFAKE: chkFake(a.amt, a.oid, ts, cfg.hashkey), P_CHARSET: 'UTF-8',
  }, f);
}
