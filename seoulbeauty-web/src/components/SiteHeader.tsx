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
import styles from "./SiteHeader.module.css";

/**
 * includes/global_nav.php + includes/header.php (search bar, region dropdown, selected-region bar) + search_overlay.php.
 * mode "results" = hospitals.php?q= (03-09): no logo row, back arrow + keyword bar on top.
 */

const REGION_KEY = "sb_selected_regions"; // same localStorage key as the PHP header
function readRegionsRaw(): string { try { return localStorage.getItem(REGION_KEY) || "[]"; } catch { return "[]"; } }
const listeners = new Set<() => void>();
function subscribeRegions(cb: () => void) { listeners.add(cb); return () => { listeners.delete(cb); }; }
export function writeRegions(r: string[]) {
  try { localStorage.setItem(REGION_KEY, JSON.stringify(r)); } catch { /* ignore */ }
  listeners.forEach((cb) => cb());
}
export function parseRegions(raw: string): string[] {
  try { const v = JSON.parse(raw); return Array.isArray(v) ? v.map(String) : []; } catch { return []; }
}
/** Regions the viewer saved on a previous visit ("[]" during SSR). */
export function useSavedRegions(): string[] {
  return parseRegions(useSyncExternalStore(subscribeRegions, readRegionsRaw, () => "[]"));
}

const PIN = <svg className={styles.pin} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 21.5s7-6.1 7-11.8A7 7 0 0 0 5 9.7c0 5.7 7 11.8 7 11.8z" /><circle cx="12" cy="9.7" r="2.6" /></svg>;

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
export default function SiteHeader({ mode = "nav", q: initialQ = "", home, showSearch = true }: { mode?: "nav" | "results"; q?: string; home?: HomeData; showSearch?: boolean }) {
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
  const regions = useSavedRegions();
  // Region groups + popular keywords come with the home payload (cached 60 s; shared with the home page).
  const homeQ = useQuery({ queryKey: qk.home, queryFn: () => api.home(), initialData: home, staleTime: 60_000, enabled: !!home || pickerOpen || searchOpen });

  const showToast = (msg: string) => {
    setToast(msg);
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(""), 1800);
  };

  /** header.php applyRegionFilter(): on the list page keep the other filters; keyword change drops dosage; page resets. */
  const apply = (keyword: string, regs: string[]) => {
    const onList = pathname === "/hospitals";
    const u = new URLSearchParams(onList ? sp.toString() : "");
    const oldKeyword = u.get("q") || "";
    if (regs.length) u.set("region", regs.join(",")); else u.delete("region");
    if (keyword) u.set("q", keyword); else u.delete("q");
    if (keyword !== oldKeyword) { u.delete("dosage"); u.delete("hid"); u.delete("tcat"); }
    u.delete("page");
    if (keyword) addRecentSearch(keyword);
    router.push(`/hospitals${u.toString() ? `?${u}` : ""}`);
  };
  const chooseRegions = (r: string[]) => {
    writeRegions(r);
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
              <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fillRule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z" /><path fillRule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z" /></svg>
            </button>
            <a href={phpUrl(php, "my_notifications.php")} className={styles.iconBtn} aria-label="알림" title="알림">
              <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zM8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6z" /></svg>
              {(me.data?.unreadNotifications ?? 0) > 0 && <span className={styles.dot} aria-hidden="true" />}
            </a>
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
