"use client";
import { useEffect, useState, useSyncExternalStore } from "react";
import { useToast } from "./Toast";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api, qk, type HomeData } from "@/lib/api";
import { useLoginUrl, useMe } from "@/lib/me";
import RegionPicker from "./RegionPicker";
import SearchOverlay, { addRecentSearch } from "./SearchOverlay";
import shareIcon from "@/assets/icons/share.png";
import logoWordmark from "@/assets/icons/logo-wordmark.png";
import bellIcon from "@/assets/icons/bell.png";
import bellDotIcon from "@/assets/icons/bell-dot.png";
import pinIcon from "@/assets/icons/pin-white.png";
import resetIcon from "@/assets/icons/reset.png";
import styles from "./SiteHeader.module.css";

/**
 * includes/global_nav.php + includes/header.php (search bar, region dropdown, selected-region bar) + search_overlay.php.
 * mode "results" = hospitals.php?q= (03-09): no logo row, back arrow + keyword bar on top.
 */

export const REGION_KEY = "sb_selected_regions"; // same localStorage key as the PHP header
/** community.php keeps its own selection (`$region_storage_key`), separate from the hospital list. */
export const COMMUNITY_REGION_KEY = "sb_community_regions";
function readRegionsRaw(key: string): string { try { return localStorage.getItem(key) || "[]"; } catch { return "[]"; } }
const listeners = new Set<() => void>();
function subscribeRegions(cb: () => void) { listeners.add(cb); return () => { listeners.delete(cb); }; }
export function writeRegions(r: string[], key: string = REGION_KEY) {
  try { localStorage.setItem(key, JSON.stringify(r)); } catch { /* ignore */ }
  listeners.forEach((cb) => cb());
}
export function parseRegions(raw: string): string[] {
  try { const v = JSON.parse(raw); return Array.isArray(v) ? v.map(String) : []; } catch { return []; }
}
/** Regions the viewer saved on a previous visit ("[]" during SSR). */
export function useSavedRegions(key: string = REGION_KEY): string[] {
  return parseRegions(useSyncExternalStore(subscribeRegions, () => readRegionsRaw(key), () => "[]"));
}

// eslint-disable-next-line @next/next/no-img-element
const PIN = <img className={styles.pin} src={pinIcon.src} alt="" width={20} height={20} />;

/** global_nav.php share button: copies "이거 완전 괜찮은 앱이야!\n서울미녀\n<url>" and shows a toast. */
async function copyShareText(): Promise<boolean> {
  const text = `이거 완전 괜찮은 앱이야!\n서울미녀\n${window.location.href}`;
  try {
    if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(text); return true; }
  } catch { /* fall through */ }
  try {
    const ta = document.createElement("textarea");
    ta.value = text; ta.setAttribute("readonly", ""); ta.style.position = "fixed"; ta.style.opacity = "0";
    document.body.appendChild(ta); ta.select();
    const ok = document.execCommand("copy");
    document.body.removeChild(ta);
    return ok;
  } catch { return false; }
}

/** showSearch=false is header.php's `$show_search = false` (events pages): logo row only, no search bar / region bar. */
/** target="community" is `$search_target_page = 'community.php'`: search and region apply to /community and use its own region storage. */
export default function SiteHeader({ mode = "nav", q: initialQ = "", home, showSearch = true, target = "hospitals" }: { mode?: "nav" | "results"; q?: string; home?: HomeData; showSearch?: boolean; target?: "hospitals" | "community" }) {
  const router = useRouter();
  const pathname = usePathname();
  const sp = useSearchParams();
  const me = useMe();
  const loginUrl = useLoginUrl();
  // The overlay is open only while the URL is the one it was opened on, so it closes by itself once the next screen's URL is in.
  const urlKey = `${pathname}?${sp.toString()}`;
  const [openedAt, setOpenedAt] = useState<string | null>(null);
  const searchOpen = openedAt === urlKey;
  const setSearchOpen = (v: boolean) => { setOpenedAt(v ? urlKey : null); if (v) setPickerOpen(false); }; // one panel at a time: the search screen has its own region panel
  const [pickerOpen, setPickerOpen] = useState(false);
  const [q, setQ] = useState(initialQ);
  const [logoBroken, setLogoBroken] = useState(false);
  const regionKey = target === "community" ? COMMUNITY_REGION_KEY : REGION_KEY;
  const regions = useSavedRegions(regionKey);
  // Region groups + popular keywords come with the home payload (cached 60 s; shared with the home page).
  // Loaded as soon as the header mounts (shared with the home page / WarmUp cache, no refetch for 5 min): the panel then opens with its data at once.
  const homeQ = useQuery({ queryKey: qk.home, queryFn: () => api.home(), initialData: home, staleTime: 5 * 60_000, refetchOnMount: false });

  const showToast = useToast();

  /** header.php applyRegionFilter(): on the list page keep the other filters; keyword change drops dosage; page resets. */
  const apply = (keyword: string, regs: string[]) => {
    const listPath = target === "community" ? "/community" : "/hospitals";
    const onList = pathname === listPath;
    const u = new URLSearchParams(onList ? sp.toString() : "");
    const oldKeyword = u.get("q") || "";
    if (regs.length) u.set("region", regs.join(",")); else u.delete("region");
    if (keyword) u.set("q", keyword); else u.delete("q");
    if (keyword !== oldKeyword) { u.delete("dosage"); u.delete("hid"); u.delete("tcat"); }
    u.delete("page");
    if (keyword) addRecentSearch(keyword);
    nav(`${listPath}${u.toString() ? `?${u}` : ""}`);
  };
  // Same URL as now (e.g. the same word searched again): it won't change, so close the overlay here instead.
  const nav = (href: string) => {
    const i = href.indexOf("?");
    const key = i < 0 ? `${href}?` : `${href.slice(0, i)}?${new URLSearchParams(href.slice(i + 1))}`;
    if (key === urlKey) setSearchOpen(false);
    router.push(href);
  };
  // Show the searched word in the field only where the list itself is the current page. From the home / other pages the
  // header stays mounted in the history cache, so a word written here would still be there after going back.
  const keepQ = (w: string) => { if (pathname === (target === "community" ? "/community" : "/hospitals")) setQ(w); };
  // Closing it first would flash the page underneath (the home page when searching from there), so it stays open while the
  // next screen loads. A header left behind in the history cache must not come back with the overlay open.
  useEffect(() => () => setOpenedAt(null), []);
  const chooseRegions = (r: string[]) => {
    writeRegions(r, regionKey);
    setPickerOpen(false);
    apply(q.trim(), r);
  };
  const regionLabel = regions.length ? `${regions[0]}${regions.length > 1 ? ` 외 ${regions.length - 1}` : ""}` : "지역 선택";
  const groups = homeQ.data?.regions ?? [];
  const popular = homeQ.data?.popularKeywords ?? [];

  const back = () => {
    if (typeof window !== "undefined" && window.history.length > 1 && document.referrer) window.history.back();
    else router.push("/");
  };

  return (
    <>
      {mode === "nav" && (
        <header className={styles.top}>
          <Link href="/" className={styles.logo} aria-label="서울미녀 홈">
            {logoBroken ? <span>서울미녀</span> : (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={logoWordmark.src} alt="서울미녀" onError={() => setLogoBroken(true)} />
            )}
          </Link>
          <div className={styles.topRight}>
            <button type="button" className={styles.iconBtn} aria-label="공유하기" title="공유하기" onClick={async () => showToast((await copyShareText()) ? "링크가 복사되었습니다." : "링크 복사에 실패했습니다.")}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={shareIcon.src} alt="" width={22} height={22} />
            </button>
            {me.data?.loggedIn && (
              <Link href="/mypage/notifications" className={`${styles.iconBtn} ${styles.bell}`} aria-label={(me.data?.unreadNotifications ?? 0) > 0 ? "알림 (읽지 않은 알림 있음)" : "알림"} title="알림">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={((me.data?.unreadNotifications ?? 0) > 0 ? bellDotIcon : bellIcon).src} alt="" width={24} height={24} />
              </Link>
            )}
            {me.data && !me.data.loggedIn && <a href={loginUrl()} className={styles.loginBtn}>로그인 / 회원가입</a>}
          </div>
        </header>
      )}

      {showSearch && (
        <>
          <div className={styles.searchWrap}>
            <div className={`${styles.searchRow} ${mode === "results" ? styles.results : ""}`} role="search">
              {mode === "results" && (
                <button type="button" className={styles.back} aria-label="뒤로 가기" onClick={back}>
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
                </button>
              )}
              {/* header.php: the field is readonly and opens the search overlay (search_overlay.php). */}
              <input className={styles.search} type="text" readOnly placeholder="시술, 병원 이름을 검색해보세요" value={q} onClick={() => setSearchOpen(true)} onFocus={() => setSearchOpen(true)} aria-label="검색" />
              <button type="button" className={styles.regionBtn} onClick={() => setPickerOpen((v) => !v)} aria-expanded={pickerOpen}>{PIN}{regionLabel}</button>
            </div>
            {/* unmounted (no exit animation) while the search screen is open: it has its own region panel */}
            {!searchOpen && <RegionPicker open={pickerOpen} groups={groups} selected={regions} onClose={() => setPickerOpen(false)} onApply={chooseRegions} />}
          </div>
          {regions.length > 0 && (
            <div className={styles.regionBar}>
              <span className={styles.regionLabel}>지역</span>
              {regions.map((r) => (
                <span key={r} className={styles.regionTag}>{r}
                  <button type="button" aria-label={`${r} 제거`} onClick={() => chooseRegions(regions.filter((x) => x !== r))}>
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                  </button>
                </span>
              ))}
              <button type="button" className={styles.resetBar} onClick={() => chooseRegions([])}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={resetIcon.src} alt="" width={14} height={14} />초기화
              </button>
            </div>
          )}
          <SearchOverlay open={searchOpen} initial={q} regionLabel={regionLabel} regionGroups={groups} regions={regions} popular={popular} onClose={() => setSearchOpen(false)}
            onSubmit={(w) => { keepQ(w); apply(w, regions); }}
            onApplyRegions={(w, r) => { writeRegions(r, regionKey); keepQ(w); apply(w, r); }}
            onHospital={target === "hospitals" ? (w, id) => { keepQ(w); nav(`/hospitals?q=${encodeURIComponent(w)}&hid=${id}`); } : undefined} />
        </>
      )}
    </>
  );
}
