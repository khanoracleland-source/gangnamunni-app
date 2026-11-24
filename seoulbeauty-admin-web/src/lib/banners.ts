// Pure rules of the banners page: the list address, the limits of the form and its checks. No DOM.

import type { BannerInput, BannerView } from "./api";
import { mediaUrl } from "./hospitals";

export const BANNER_SEARCH_MAX = 100;
export const BANNER_PER_PAGE = 20;
export const BANNER_TEXT_MAX = 255;
export const BANNER_LINK_MAX = 500;
export const BANNER_ORDER_MAX = 99999;
export const BANNER_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
export const BANNER_IMAGE_ACCEPT = ".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp";
export const SCROLL_MIN = 1;
export const SCROLL_MAX = 60;
export const BANNER_VIEWS: BannerView[] = ["all", "visible", "hidden"];

export type BannerListView = { view: BannerView; search: string; page: number };

const positive = (v: string | null): number => (v !== null && /^[1-9]\d{0,9}$/.test(v) ? Number(v) : 0);

/** The state of the page from the address; anything missing or wrong becomes the default. */
export function parseBannerView(params: { get(name: string): string | null }): BannerListView {
  const view = params.get("view");
  return {
    view: view === "visible" || view === "hidden" ? view : "all",
    search: (params.get("search") ?? "").trim().slice(0, BANNER_SEARCH_MAX),
    page: Math.min(100000, positive(params.get("page")) || 1),
  };
}

export function bannerHref(v: BannerListView): string {
  const params = new URLSearchParams();
  if (v.view !== "all") params.set("view", v.view);
  if (v.search !== "") params.set("search", v.search);
  if (v.page > 1) params.set("page", String(v.page));
  const s = params.toString();
  return s === "" ? "/banners" : `/banners?${s}`;
}

export const hasBannerFilters = (v: BannerListView): boolean => v.search !== "";

/** A picture address that is safe to show; a backslash (read as "/" by browsers) is refused. */
export function bannerImageHref(stored: string): string {
  const url = mediaUrl(stored);
  return url === "" || /[\\\s]/.test(url) ? "" : url;
}

/** A link worth showing as a link: http(s) or a path of this site. Anything else is drawn as plain text. */
export const bannerLinkHref = (v: string): string => (/^https?:\/\/[^/?#\s]/i.test(v) || (v.startsWith("/") && !v.startsWith("//") && !/[\s\\]/.test(v)) ? v : "");

const cps = (s: string): number => Array.from(s).length;

/** The same checks the server makes, so a mistake is shown before anything is sent. Codes are the server's (required, too_long, bad_url, range, too_large, invalid_image). */
export function checkBannerInput(i: BannerInput): Record<string, string> {
  const e: Record<string, string> = {};
  const title = i.title.trim();
  if (title === "") e.title = "required";
  else if (cps(title) > BANNER_TEXT_MAX) e.title = "too_long";
  if (cps(i.subtitle.trim()) > BANNER_TEXT_MAX) e.subtitle = "too_long";
  const link = i.linkUrl.trim();
  if (link !== "") {
    if (cps(link) > BANNER_LINK_MAX) e.linkUrl = "too_long";
    else if (!isLinkOk(link)) e.linkUrl = "bad_url";
  }
  const order = i.displayOrder.trim();
  if (order !== "" && !(/^\d{1,5}$/.test(order) && Number(order) <= BANNER_ORDER_MAX)) e.displayOrder = "range";
  if (!i.image && i.imageUrl === "") e.image = "required";
  for (const [k, f] of [["image", i.image], ["mypageImage", i.mypageImage]] as const) {
    if (f && f.size > BANNER_IMAGE_MAX_BYTES) e[k] = "too_large";
    else if (f && !/\.(jpe?g|png|gif|webp)$/i.test(f.name)) e[k] = "invalid_image";
  }
  return e;
}

function isLinkOk(s: string): boolean {
  if (/[\s"'<>\\\u0000-\u001f\u007f]/.test(s)) return false;
  if (/^https?:\/\/[^/?#]/i.test(s)) return true;
  if (s.startsWith("/")) return !s.startsWith("//");
  return !/^[a-z][a-z0-9+.-]*:/i.test(s) && !s.startsWith(".");
}
