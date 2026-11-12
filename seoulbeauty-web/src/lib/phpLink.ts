import { phpUrl } from "./php";

/** Next.js is served under this prefix; plain `<a href>` / window.location need it, `<Link>` adds it itself. */
export const BASE_PATH = process.env.NEXT_PUBLIC_BASE_PATH ?? "/next";

const posInt = (v: string | null): number | null => (v !== null && /^[1-9]\d{0,9}$/.test(v) ? Number(v) : null);
const query = (p: URLSearchParams, keys: string[], skip: (k: string, v: string) => boolean = () => false): string => {
  const out = new URLSearchParams();
  for (const k of keys) { const v = p.get(k) ?? ""; if (v !== "" && !skip(k, v)) out.set(k, v); }
  const s = out.toString();
  return s ? `?${s}` : "";
};
const only = (p: URLSearchParams, allowed: string[]): boolean => [...p.keys()].every((k) => allowed.includes(k));

/**
 * The Next.js route (without basePath) of a PHP page link such as `detail.php?id=5` or `/events.php?filter=raffle`, or null when the
 * page (or one of its parameters) has no Next.js counterpart — the link then stays on PHP. Admin-entered ad / promo links and the
 * search suggestions still use PHP file names. Same rules as the app's NextPilot (mobile/patient_app/lib/services/next_pilot.dart).
 */
export function nextPathFor(link: string): string | null {
  const m = /^\/?([a-z_]+\.php)(?:\?([^#]*))?$/i.exec(link.trim());
  if (!m) return null;
  const p = new URLSearchParams(m[2] ?? "");
  p.delete("app");
  switch (m[1].toLowerCase()) {
    case "index.php": return p.size === 0 ? "/" : null;
    case "hospitals.php": {
      const keys = ["q", "category", "treatment", "region", "sort", "dosage", "hid", "tcat"];
      return only(p, keys) ? `/hospitals${query(p, keys)}` : null;
    }
    case "detail.php": {
      const id = posInt(p.get("id")); const tab = p.get("tab") ?? "";
      // treatment_id: the 홈 tab opens with that treatment's price options; `treatment` (its name) and `return_url` are PHP-only helpers
      const tid = p.get("treatment_id"); const treatment = tid === null ? null : posInt(tid);
      if (!id || !only(p, ["id", "tab", "from", "treatment_id", "treatment", "return_url"]) || (tab !== "" && !["home", "events", "reviews", "doctors"].includes(tab))) return null;
      if (tid !== null ? !treatment : p.has("treatment")) return null; // a bad id, or a name without an id (PHP matches by name), stays on PHP
      const q = new URLSearchParams();
      if (tab !== "" && tab !== "home") q.set("tab", tab);
      if (treatment) q.set("treatment_id", String(treatment));
      return `/hospitals/${id}${q.size ? `?${q}` : ""}`;
    }
    case "events.php": {
      const filter = p.get("filter") ?? "";
      if (!only(p, ["category", "filter"]) || (filter !== "" && filter !== "raffle")) return null;
      return `/events${query(p, ["category", "filter"])}`;
    }
    case "event_detail.php": { const id = posInt(p.get("id")); return id && only(p, ["id"]) ? `/events/${id}` : null; }
    case "community.php": {
      const tab = p.get("tab") ?? ""; const page = p.get("page") ?? "";
      if (!only(p, ["tab", "category", "region", "q", "page"]) || (tab !== "" && !["latest", "popular", "review"].includes(tab)) || (page !== "" && !posInt(page))) return null;
      return `/community${query(p, ["tab", "category", "region", "q", "page"], (k, v) => (k === "tab" && v === "latest") || (k === "page" && v === "1"))}`;
    }
    case "detail_community.php": { const id = posInt(p.get("id")); return id && only(p, ["id"]) ? `/community/${id}` : null; }
    case "faq.php": { const cat = p.get("cat") ?? ""; return only(p, ["cat"]) && cat.length <= 50 ? `/faq${query(p, ["cat"])}` : null; }
    case "qna.php": return p.size === 0 ? "/qna" : null;
    case "mypage.php": return p.size === 0 ? "/mypage" : null;
    case "my_bookings.php": {
      const tab = p.get("tab") ?? "";
      if (!only(p, ["tab"]) || (tab !== "" && !["active", "completed", "cancelled"].includes(tab))) return null;
      return `/mypage/bookings${tab !== "" && tab !== "active" ? `?tab=${tab}` : ""}`;
    }
    default: return null;
  }
}

/**
 * href of an admin-entered link (ad banner, promotion) for a plain `<a>`: external URLs and "#" as they are, a PHP page that exists
 * in Next.js as its Next.js URL, anything else on the PHP site as before.
 */
export function siteHref(php: string, link: string): string {
  if (link === "#" || link.startsWith("http")) return link;
  const next = nextPathFor(link);
  if (next !== null) return `${BASE_PATH}${next === "/" ? "" : next}` || "/";
  return link.startsWith("/") ? link : phpUrl(php, link);
}
