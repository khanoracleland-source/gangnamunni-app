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

/* ---------- Keyword ad slots (hospitals list) — footer.php "Keyword slot CPC/CPM tracking" ---------- */

const slotImpressed = new Set<string>();

/** Tokens are signed by PHP (api/ad_slot_tokens.php) — the secret never leaves PHP; no token → no billing event. */
export async function fetchSlotTokens(phpBase: string, bookingIds: number[]): Promise<Record<string, string>> {
  const ids = [...new Set(bookingIds.filter((n) => n > 0))];
  if (!ids.length) return {};
  try {
    const r = await fetch(phpUrl(phpBase, `api/ad_slot_tokens.php?booking_ids=${ids.join(",")}`), { credentials: "same-origin" });
    if (!r.ok) return {};
    const d = await r.json();
    return d && typeof d.tokens === "object" && d.tokens ? d.tokens : {};
  } catch {
    return {};
  }
}

function sendSlotEvent(phpBase: string, action: "impression" | "click", el: HTMLElement, targetUrl = "") {
  const bookingId = el.dataset.adSlotBooking;
  const token = el.dataset.adSlotToken || "";
  if (!bookingId || !token) return; // PHP refuses untokened events anyway; don't even send them
  const fd = new FormData();
  fd.append("action", action);
  fd.append("booking_id", bookingId);
  fd.append("token", token);
  fd.append("keyword", el.dataset.adSlotKeyword || "");
  fd.append("page_url", window.location.href);
  fd.append("target_url", targetUrl);
  const url = phpUrl(phpBase, "api_ad_slot_tracking.php");
  try {
    if (navigator.sendBeacon) { navigator.sendBeacon(url, fd); return; }
    void fetch(url, { method: "POST", body: fd, credentials: "same-origin", keepalive: true }).catch(() => {});
  } catch { /* ignore */ }
}

export function trackSlotClick(phpBase: string, el: HTMLElement, targetUrl: string) {
  sendSlotEvent(phpBase, "click", el, targetUrl);
}

/** Impression = card ≥50% visible for 1 s, once per booking+token (same rules as footer.php). */
export function observeSlotImpressions(phpBase: string, root: HTMLElement): () => void {
  const els = Array.from(root.querySelectorAll<HTMLElement>("[data-ad-slot-booking]"));
  const timers = new Map<Element, ReturnType<typeof setTimeout>>();
  const schedule = (el: HTMLElement) => {
    const key = `${el.dataset.adSlotBooking}:${el.dataset.adSlotToken || ""}`;
    if (!el.dataset.adSlotToken || slotImpressed.has(key) || timers.has(el)) return;
    timers.set(el, setTimeout(() => { timers.delete(el); slotImpressed.add(key); sendSlotEvent(phpBase, "impression", el); }, 1000));
  };
  const cancel = (el: Element) => { const t = timers.get(el); if (t) { clearTimeout(t); timers.delete(el); } };
  if (!("IntersectionObserver" in window)) { els.forEach(schedule); return () => {}; }
  const io = new IntersectionObserver((entries) => {
    for (const e of entries) {
      if (e.isIntersecting && e.intersectionRatio >= 0.5) schedule(e.target as HTMLElement);
      else cancel(e.target);
    }
  }, { threshold: [0, 0.5] });
  els.forEach((el) => io.observe(el));
  return () => { io.disconnect(); timers.forEach((t) => clearTimeout(t)); };
}
