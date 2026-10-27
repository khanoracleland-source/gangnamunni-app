"use client";
import { phpUrl } from "./php";

/**
 * Home banner impressions/clicks keep going to the PHP tracker (api_ad_tracking.php): billing,
 * bot filtering and duplicate rules stay in one place. Same FormData shape as includes/footer.php.
 */
const tracked = new Set<string>();

function send(phpBase: string, action: "impression" | "click", adId: number, targetUrl = "") {
  const fd = new FormData();
  fd.append("action", action);
  fd.append("ad_id", String(adId));
  fd.append("page_url", window.location.href);
  if (action === "click") fd.append("target_url", targetUrl);
  const url = phpUrl(phpBase, "api_ad_tracking.php");
  try {
    if (action === "click" && navigator.sendBeacon) { navigator.sendBeacon(url, fd); return; }
    void fetch(url, { method: "POST", body: fd, credentials: "same-origin", keepalive: true }).catch(() => {});
  } catch { /* ignore */ }
}

export function trackImpression(phpBase: string, adId: number) {
  const k = `imp_${adId}`;
  if (tracked.has(k)) return;
  tracked.add(k);
  send(phpBase, "impression", adId);
}

export function trackClick(phpBase: string, adId: number, targetUrl: string) {
  send(phpBase, "click", adId, targetUrl);
}

/** Observe banner elements (50% visible, like footer.php) and fire one impression each. */
export function observeImpressions(phpBase: string, root: HTMLElement): () => void {
  const els = Array.from(root.querySelectorAll<HTMLElement>("[data-ad-id]"));
  if (!("IntersectionObserver" in window)) {
    els.forEach((el) => trackImpression(phpBase, Number(el.dataset.adId)));
    return () => {};
  }
  const io = new IntersectionObserver((entries) => {
    for (const e of entries) if (e.isIntersecting) trackImpression(phpBase, Number((e.target as HTMLElement).dataset.adId));
  }, { threshold: 0.5 });
  els.forEach((el) => io.observe(el));
  return () => io.disconnect();
}
