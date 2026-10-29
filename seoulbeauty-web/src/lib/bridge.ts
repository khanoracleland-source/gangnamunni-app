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
  try {
    window.FlutterBridge?.postMessage(JSON.stringify({ action: "logout" }));
  } catch {
    /* not inside the app */
  }
  return true;
}
