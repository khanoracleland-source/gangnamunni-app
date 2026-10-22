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
