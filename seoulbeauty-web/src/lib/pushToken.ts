/**
 * FCM token of the patient app → the member's push registration (API: POST /v1/app/push-token and /v1/app/push-token/unlink).
 *
 * The app hands the device token to the page with `window.onTokenReceived(token)` (or `window.receiveFCMToken(token)`):
 * after every full page load (startup_webview_screen.dart onPageFinished) and whenever the page asks for it with the bridge
 * message `requestFcmToken` (webview_bridge.dart). It calls once and never retries, so the two functions are defined by an inline
 * script in <head> (app/layout.tsx), long before React runs: that script keeps the token in `window.__sbPushToken` and fires
 * PUSH_TOKEN_EVENT. This module does the rest: POST it for the signed-in member, once per (member, token) per browser session.
 * In a normal browser nobody calls the functions, so nothing here ever sends a request.
 */
import { api, ApiError } from "./api";

declare global {
  interface Window {
    __sbPushToken?: string;
    onTokenReceived?: (token: unknown) => void;
    receiveFCMToken?: (token: unknown) => void;
  }
}

/** fired on window by the <head> script each time the app hands a token over (keep in step with app/layout.tsx) */
export const PUSH_TOKEN_EVENT = "sb-push-token";
const SENT_KEY = "sb-push-sent";
const DEVICE_KEY = "seoulbeauty_fcm_device_id";

export type PushPlatform = "android" | "ios";

/* ---- pure rules ---- */

/** iOS / Android from the user agent (iPadOS reports "Macintosh" with a touch screen); anything else: unknown, the field is left out. */
export function pushPlatform(ua: string, maxTouchPoints = 0): PushPlatform | undefined {
  if (/iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && maxTouchPoints > 1)) return "ios";
  if (/Android/i.test(ua)) return "android";
  return undefined;
}

/** Same floor as the PHP page (includes/header.php: a string of 32+ characters); the upper bound only keeps junk out. */
export function validPushToken(token: unknown): token is string {
  return typeof token === "string" && token.length >= 32 && token.length <= 4096;
}

/** One registration = one (member, token) pair. */
export function pushPairKey(uid: string, token: string): string {
  return `${uid}\n${token}`;
}

export interface PushSyncState {
  /** signed-in member id; "" = signed out, undefined = not known yet */
  uid: string | undefined;
  token: string | undefined;
  /** the pair already registered in this browser session */
  sent: string;
  /** the pair being sent right now */
  inflight: string;
  /** the endpoint answered 404 on this page load (switch off) */
  off: boolean;
  /** signing out: nothing may be registered any more */
  leaving: boolean;
  /** tokens the API refused as invalid (400) */
  rejected: ReadonlySet<string>;
}

/** Is there something to send now? */
export function shouldRegister(s: PushSyncState): boolean {
  if (!s.uid || !s.token || s.off || s.leaving || s.rejected.has(s.token)) return false;
  const key = pushPairKey(s.uid, s.token);
  return key !== s.sent && key !== s.inflight;
}

/* ---- browser state ---- */

let sentMem = "";
let inflight = "";
let off = false;
let leaving = false;
const rejected = new Set<string>();

function readSent(): string {
  try { return sessionStorage.getItem(SENT_KEY) || sentMem; } catch { return sentMem; }
}
function writeSent(key: string): void {
  sentMem = key;
  try { if (key) sessionStorage.setItem(SENT_KEY, key); else sessionStorage.removeItem(SENT_KEY); } catch { /* storage blocked: memory only */ }
}

/** The token the app handed over on this page load, if any. */
export function currentPushToken(): string | undefined {
  if (typeof window === "undefined") return undefined;
  return validPushToken(window.__sbPushToken) ? window.__sbPushToken : undefined;
}

/** Safety net for a page whose HTML came without the <head> script: define the two functions now (the app can be asked again). */
export function installPushTokenSink(): void {
  if (typeof window === "undefined" || typeof window.onTokenReceived === "function") return;
  const sink = (token: unknown) => {
    if (typeof token !== "string" || !token) return;
    window.__sbPushToken = token;
    try { window.dispatchEvent(new Event(PUSH_TOKEN_EVENT)); } catch { /* ignore */ }
  };
  window.onTokenReceived = sink;
  window.receiveFCMToken = sink;
}

/** Ask the app for the token (it answers through window.onTokenReceived). Outside the app: nothing. */
export function requestPushToken(): void {
  try { window.FlutterBridge?.postMessage(JSON.stringify({ action: "requestFcmToken" })); } catch { /* not inside the app */ }
}

/**
 * This install's id (same localStorage key and shape as the PHP page, includes/header.php). The API keeps one token per device
 * with it; without it a member's second phone would switch the first one's token off. "" when storage is blocked.
 */
export function pushDeviceId(): string {
  try {
    const existing = localStorage.getItem(DEVICE_KEY);
    if (existing) return existing;
    const generated = typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    localStorage.setItem(DEVICE_KEY, generated);
    return generated;
  } catch {
    return "";
  }
}

/** Signed out: the next sign-in registers again, also for the same member. */
export function forgetPushRegistration(): void {
  if (readSent()) writeSent("");
}

/**
 * Register the token for the signed-in member when there is something new to send. Every failure is silent: 401 (the session
 * is gone), 404 (the switch is off: not asked again on this page load), network: the next token, sign-in or return to the
 * foreground tries again.
 */
export async function syncPushToken(uid: string | undefined): Promise<void> {
  const token = currentPushToken();
  if (!shouldRegister({ uid, token, sent: readSent(), inflight, off, leaving, rejected }) || !uid || !token) return;
  const key = pushPairKey(uid, token);
  inflight = key;
  try {
    const device_id = pushDeviceId();
    await api.registerPushToken({ token, platform: pushPlatform(navigator.userAgent, navigator.maxTouchPoints), ...(device_id ? { device_id } : {}) });
    if (!leaving) writeSent(key);
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) off = true;
    else if (e instanceof ApiError && e.status === 400) rejected.add(token);
  } finally {
    if (inflight === key) inflight = "";
  }
}

/**
 * Before a logout ends the session: take the token away from this member. Never throws and never takes longer than 2 seconds;
 * without a token (a normal browser) it sends nothing.
 */
export async function unlinkPushToken(): Promise<void> {
  const token = currentPushToken();
  if (!token) return;
  leaving = true;
  writeSent("");
  await Promise.race([
    api.unlinkPushToken(token).catch(() => undefined),
    new Promise<void>((done) => setTimeout(done, 2000)),
  ]);
}
