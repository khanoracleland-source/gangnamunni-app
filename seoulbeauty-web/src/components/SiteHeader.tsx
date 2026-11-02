"use client";
import { useRef, useState, useSyncExternalStore } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api, qk, type HomeData } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import { useLoginUrl, useMe } from "@/lib/me";
import RegionPicker from "./RegionPicker";
import SearchOverlay, { addRecentSearch } from "./SearchOverlay";
import shareIcon from "@/assets/icons/share.png";
import bellIcon from "@/assets/icons/bell.png";
import bellDotIcon from "@/assets/icons/bell-dot.png";
import pinIcon from "@/assets/icons/pin-white.png";
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
  const php = usePhpBase();
  const me = useMe();
  const loginUrl = useLoginUrl();
  const [searchOpen, setSearchOpen] = useState(false);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [q, setQ] = useState(initialQ);
  const [toast, setToast] = useState("");
  const [logoBroken, setLogoBroken] = useState(false);
  const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const regionKey = target === "community" ? COMMUNITY_REGION_KEY : REGION_KEY;
  const regions = useSavedRegions(regionKey);
  // Region groups + popular keywords come with the home payload (cached 60 s; shared with the home page).
  const homeQ = useQuery({ queryKey: qk.home, queryFn: () => api.home(), initialData: home, enabled: !!home || pickerOpen || searchOpen });

  const showToast = (msg: string) => {
    setToast(msg);
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(""), 1800);
  };

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
    router.push(`${listPath}${u.toString() ? `?${u}` : ""}`);
  };
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
              <img src={phpUrl(php, "assets/images/logo_129.jpg")} alt="서울미녀" onError={() => setLogoBroken(true)} />
            )}
          </Link>
          <div className={styles.topRight}>
            <button type="button" className={styles.iconBtn} aria-label="공유하기" title="공유하기" onClick={async () => showToast((await copyShareText()) ? "링크가 복사되었습니다." : "링크 복사에 실패했습니다.")}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={shareIcon.src} alt="" width={22} height={22} />
            </button>
            {me.data?.loggedIn && (
              <Link href="/mypage/notifications" className={`${styles.iconBtn} ${styles.bell}`} aria-label="알림" title="알림">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={((me.data?.unreadNotifications ?? 0) > 0 ? bellDotIcon : bellIcon).src} alt="" width={24} height={24} />
              </Link>
            )}
            {me.data && !me.data.loggedIn && <a href={loginUrl()} className={styles.loginBtn}>로그인 / 회원가입</a>}
          </div>
        </header>
      )}
      {toast && <div className={styles.toast} role="status" aria-live="polite">{toast}</div>}

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
              <button type="button" className={`${styles.regionBtn} ${regions.length ? styles.hasRegion : ""}`} onClick={() => setPickerOpen(true)}>{PIN}{regionLabel}</button>
            </div>
            <RegionPicker open={pickerOpen && groups.length > 0} groups={groups} selected={regions} onClose={() => setPickerOpen(false)} onApply={chooseRegions} />
          </div>
          {regions.length > 0 && (
            <div className={styles.regionBar}>
              <button type="button" className={styles.resetBar} onClick={() => chooseRegions([])}>↺ 초기화</button>
              <div className={styles.regionTags}>
                {regions.map((r) => <span key={r} className={styles.regionTag}>{r}<span role="button" aria-label={`${r} 제거`} onClick={() => chooseRegions(regions.filter((x) => x !== r))}>×</span></span>)}
              </div>
            </div>
          )}
          <SearchOverlay open={searchOpen} initial={q} regionLabel={regionLabel} popular={popular} onClose={() => setSearchOpen(false)}
            onSubmit={(w) => { setQ(w); setSearchOpen(false); apply(w, regions); }}
            onRegion={(w) => { setQ(w); setSearchOpen(false); setPickerOpen(true); }} />
        </>
      )}
    </>
  );
}
