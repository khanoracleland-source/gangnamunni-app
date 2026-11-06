"use client";
/** Flutter WebView bridge: same message the Turbo layer sends, so the app's page-change hooks keep working. */
declare global {
  interface Window {
    FlutterBridge?: { postMessage: (msg: string) => void };
  }
}

export function notifyPageChanged(): void {
  try {
    window.FlutterBridge?.postMessage(JSON.stringify({ action: "pageChanged", source: "next" }));
  } catch {
    /* not inside the app */
  }
}

/**
 * After bbs/logout.php the PHP site lands on the `url` it was given with `?app_logout=1` appended (only for the app's
 * WebView). PHP pages answer with a script that tells the app to drop its native credentials; the Next.js pages do
 * the same here. The parameter stays in the URL on purpose: the app skips session recovery while it is present.
 */
export function announceLogoutFromUrl(search: string = typeof window !== "undefined" ? window.location.search : ""): boolean {
  if (!new URLSearchParams(search).get("app_logout")) return false;
  announceLogout();
  return true;
}

/** Tell the app to drop its native credentials (the server side has just ended the member's session or account). */
export function announceLogout(): void {
  try {
    window.FlutterBridge?.postMessage(JSON.stringify({ action: "logout" }));
  } catch {
    /* not inside the app */
  }
}

/* ---- Flutter → web events: window.onFlutterEvent(name, data) (webview_bridge.dart sendToWebView) ---- */
type FlutterEventHandler = (data: unknown) => void;
const flutterListeners = new Map<string, Set<FlutterEventHandler>>();

declare global {
  interface Window {
    onFlutterEvent?: (name: string, data: unknown) => void;
  }
}

function installFlutterEventSink(): void {
  if (typeof window === "undefined" || window.onFlutterEvent) return;
  window.onFlutterEvent = (name, data) => {
    flutterListeners.get(name)?.forEach((fn) => { try { fn(data); } catch { /* one listener must not break the rest */ } });
  };
}

/** Listen for one Flutter event; returns the unsubscribe. */
export function onFlutterEvent(name: string, fn: FlutterEventHandler): () => void {
  installFlutterEventSink();
  if (!flutterListeners.has(name)) flutterListeners.set(name, new Set());
  flutterListeners.get(name)!.add(fn);
  return () => { flutterListeners.get(name)?.delete(fn); };
}

/** True inside the patient app's WebView (the FlutterBridge channel exists). */
export function inApp(): boolean {
  return typeof window !== "undefined" && !!window.FlutterBridge;
}

/** Ask the app for the OS notification permission; the answer arrives as the 'notificationStatus' event ({enabled, status}). */
export function requestNotificationStatus(): void {
  try { window.FlutterBridge?.postMessage(JSON.stringify({ action: "getNotificationStatus" })); } catch { /* not inside the app */ }
}

/** "설정 열기": the app asks for the permission or opens its OS settings screen. */
export function openNotificationSettings(): void {
  try { window.FlutterBridge?.postMessage(JSON.stringify({ action: "openNotificationSettings" })); } catch { /* not inside the app */ }
}
