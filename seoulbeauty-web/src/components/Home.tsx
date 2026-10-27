"use client";
import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, formatWon, qk, type HomeAd, type HomeData } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import { observeImpressions, trackClick } from "@/lib/adTracking";
import { useLoginUrl, useMe } from "@/lib/me";
import Carousel from "./Carousel";
import SearchOverlay, { addRecentSearch } from "./SearchOverlay";
import RegionPicker from "./RegionPicker";
import CategoryTable from "./CategoryTable";
import styles from "./Home.module.css";

const REGION_KEY = "sb_selected_regions"; // same localStorage key as the PHP header

function readRegionsRaw(): string {
  try { return localStorage.getItem(REGION_KEY) || "[]"; } catch { return "[]"; }
}
const listeners = new Set<() => void>();
function subscribeRegions(cb: () => void) { listeners.add(cb); return () => { listeners.delete(cb); }; }
function writeRegions(r: string[]) {
  try { localStorage.setItem(REGION_KEY, JSON.stringify(r)); } catch { /* ignore */ }
  listeners.forEach((cb) => cb());
}
function parseRegions(raw: string): string[] {
  try { const v = JSON.parse(raw); return Array.isArray(v) ? v.map(String) : []; } catch { return []; }
}

const PIN = <svg className={styles.pin} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 21.5s7-6.1 7-11.8A7 7 0 0 0 5 9.7c0 5.7 7 11.8 7 11.8z" /><circle cx="12" cy="9.7" r="2.6" /></svg>;

/** includes/global_nav.php share button: copies "이거 완전 괜찮은 앱이야!\n서울미녀\n<url>" and shows a toast. */
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

export default function Home({ initial }: { initial?: HomeData }) {
  const router = useRouter();
  const php = usePhpBase();
  const { data, isError, refetch } = useQuery({ queryKey: qk.home, queryFn: () => api.home(), initialData: initial, staleTime: 60_000 });
  const [q, setQ] = useState("");
  const [searchOpen, setSearchOpen] = useState(false);
  const [toast, setToast] = useState("");
  const [logoBroken, setLogoBroken] = useState(false);
  const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const me = useMe();
  const loginUrl = useLoginUrl();
  const showToast = (msg: string) => {
    setToast(msg);
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(""), 1800);
  };
  // Per-viewer convenience only (same localStorage key the PHP pages use); "[]" during SSR.
  const regionsRaw = useSyncExternalStore(subscribeRegions, readRegionsRaw, () => "[]");
  const regions = parseRegions(regionsRaw);
  const [pickerOpen, setPickerOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!rootRef.current || !data) return;
    return observeImpressions(php, rootRef.current);
  }, [data, php]);

  const go = (params: Record<string, string>) => {
    const u = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) if (v) u.set(k, v);
    router.push(`/hospitals${u.toString() ? `?${u}` : ""}`);
  };
  // header.php applyRegionFilter(): keyword + regions -> hospitals list (keyword saved as recent search).
  const chooseRegions = (r: string[]) => {
    writeRegions(r);
    setPickerOpen(false);
    const kw = q.trim();
    if (kw) addRecentSearch(kw);
    if (r.length || kw) go({ q: kw, region: r.join(",") });
  };
  const regionLabel = regions.length ? `${regions[0]}${regions.length > 1 ? ` 외 ${regions.length - 1}` : ""}` : "지역 선택";

  if (!data) {
    return (
      <div className={styles.empty}>
        {isError ? <>불러오지 못했습니다. <button className={styles.link} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}
      </div>
    );
  }
  const { ads, promos, events, categories, settings } = data;
  const showAds = settings.adShowIndex && ads.length > 0;
  const adSlides = (list: HomeAd[]) => list.map((ad) => {
    const img = assetUrl(ad.imageUrl);
    const href = ad.linkUrl.startsWith("http") || ad.linkUrl.startsWith("/") || ad.linkUrl === "#" ? ad.linkUrl : phpUrl(php, ad.linkUrl);
    return (
      <a key={ad.id} href={href} className={styles.ad} data-ad-id={ad.id} onClick={() => trackClick(php, ad.id, href)}>
        {img ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={img} alt={ad.title} className={styles.adImg} />
        ) : (
          <div className={styles.adText}><span className={styles.adBadge}>AD</span><div className={styles.adTitle}>{ad.title}</div>{ad.subtitle && <div className={styles.adSub}>{ad.subtitle}</div>}</div>
        )}
      </a>
    );
  });

  return (
    <div ref={rootRef} className={styles.home}>
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
          {me.data && !me.data.loggedIn && <a href={loginUrl("/next/")} className={styles.loginBtn}>로그인 / 회원가입</a>}
        </div>
      </header>
      {toast && <div className={styles.toast} role="status" aria-live="polite">{toast}</div>}

      <div className={styles.searchWrap}>
        <div className={styles.searchRow} role="search">
          {/* header.php: the field is readonly and opens the search overlay (search_overlay.php). */}
          <input className={styles.search} type="text" readOnly placeholder="시술, 병원 이름을 검색해보세요" value={q} onClick={() => setSearchOpen(true)} onFocus={() => setSearchOpen(true)} aria-label="검색" />
          <button type="button" className={`${styles.regionBtn} ${regions.length ? styles.hasRegion : ""}`} onClick={() => setPickerOpen(true)}>{PIN}{regionLabel}</button>
        </div>
        <RegionPicker open={pickerOpen} groups={data.regions} selected={regions} onClose={() => setPickerOpen(false)} onApply={chooseRegions} />
      </div>
      {regions.length > 0 && (
        <div className={styles.regionBar}>
          <button type="button" className={styles.resetBar} onClick={() => chooseRegions([])}>↺ 초기화</button>
          <div className={styles.regionTags}>
            {regions.map((r) => <span key={r} className={styles.regionTag}>{r}<span role="button" aria-label={`${r} 제거`} onClick={() => chooseRegions(regions.filter((x) => x !== r))}>×</span></span>)}
          </div>
        </div>
      )}
      <SearchOverlay open={searchOpen} initial={q} regionLabel={regionLabel} popular={data.popularKeywords} onClose={() => setSearchOpen(false)}
        onSubmit={(w) => { setQ(w); setSearchOpen(false); go({ q: w, region: regions.join(",") }); }}
        onRegion={(w) => { setQ(w); setSearchOpen(false); setPickerOpen(true); }} />

      {showAds && settings.adAfterSearch && <section className={styles.section}><Carousel auto={settings.adAutoScroll} label="광고">{adSlides(ads)}</Carousel></section>}

      <section className={styles.section}>
        <CategoryTable categories={categories} mode={settings.tableMode} />
      </section>

      {showAds && !settings.adAfterSearch && <section className={styles.section}><Carousel auto={settings.adAutoScroll} label="광고">{adSlides(ads)}</Carousel></section>}

      {promos.length > 0 && settings.promoShowIndex && settings.blocks.promos && (
        <section className={styles.section}>
          <Carousel auto={settings.promoAutoScroll} label="프로모션">
            {promos.map((p) => (
              <a key={p.id} href={p.linkUrl.startsWith("http") || p.linkUrl.startsWith("/") || p.linkUrl === "#" ? p.linkUrl : phpUrl(php, p.linkUrl)} className={styles.promo} style={{ background: p.background }}>
                <div className={styles.promoTitle}>{p.icon ? `${p.icon} ` : ""}{p.title}</div>
                {p.subtitle && <div className={styles.promoSub}>{p.subtitle}</div>}
              </a>
            ))}
          </Carousel>
        </section>
      )}

      {events.length > 0 && settings.blocks.events && (
        <section className={styles.section}>
          <div className={styles.sectionHead}><div className={styles.sectionTitle}>이벤트</div><a href={phpUrl(php, "events.php")} className={styles.more} aria-label="이벤트 더보기">›</a></div>
          <div className={styles.eventsScroll}>
            {events.map((ev) => {
              const img = assetUrl(ev.imageUrl);
              return (
                <a key={ev.id} href={phpUrl(php, `event_detail.php?id=${ev.id}`)} className={styles.event}>
                  <div className={styles.eventImg}>
                    {img ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={img} alt="" loading="lazy" onError={(e) => { e.currentTarget.style.display = "none"; }} />
                    ) : "🎁"}
                    {ev.discountRate != null && ev.discountRate > 0 && <span className={styles.badge}>{Math.trunc(ev.discountRate)}%</span>}
                    {ev.hasRaffle && <span className={styles.raffle}>응모</span>}
                  </div>
                  <div className={styles.eventBody}>
                    {ev.category && <div className={styles.eventCat}>{ev.category}</div>}
                    <div className={styles.eventTitle}>{ev.title}</div>
                    {ev.hospitalAddress && <div className={styles.eventHosp}>{ev.hospitalAddress}</div>}
                    {ev.discountPrice != null && ev.discountPrice > 0 && (
                      <div className={styles.eventPrice}>
                        {ev.originalPrice != null && ev.originalPrice > 0 && <s className={styles.orig}>{formatWon(ev.originalPrice)}</s>}
                        <span className={styles.disc}>{formatWon(ev.discountPrice)}</span>
                      </div>
                    )}
                  </div>
                </a>
              );
            })}
          </div>
        </section>
      )}

      {showAds && settings.adAfterEvents && <section className={styles.section}><Carousel auto={settings.adAutoScroll} label="광고">{adSlides(ads)}</Carousel></section>}

      <footer className={styles.company} aria-label="회사 정보">
        <div className={styles.companyLogo}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={phpUrl(php, "assets/images/logo_of_footer.png")} alt="서울미녀" />
        </div>
        <p>셀럽몰 주식회사 | 대표자: 최서진</p>
        <p>서울특별시 강남구 선릉로 612, 4층(삼성동, 한일빌딩)</p>
        <p>사업자등록번호: 308-81-44202</p>
        <p>통신판매업신고: 2018-서울강남-01913호</p>
        <p>고객센터 E-mail: <a href="mailto:info@seoulbeauty.app">info@seoulbeauty.app</a></p>
        <nav className={styles.companyLinks} aria-label="회사 정책 및 고객센터">
          <a className={styles.linkStrong} href={phpUrl(php, "qna.php")}>고객센터 문의하기</a>
          <a href={phpUrl(php, "terms_of_service.php")}>이용약관</a>
          <a href={phpUrl(php, "terms_of_service.php")}>위치기반서비스 이용약관</a>
          <a className={styles.linkStrong} href={phpUrl(php, "privacy_policy.php")}>개인정보처리방침</a>
        </nav>
        <div className={styles.notice}>
          <p>셀럽몰 주식회사는 통신판매의 당사자가 아니므로,</p>
          <p>의료기관이 등록한 시/수술 정보 및 거래 등에 대해 책임을 지지 않습니다.</p>
        </div>
        <p className={styles.copyright}>©CELEBMALL Co., Ltd. All rights reserved.</p>
      </footer>
      <Link href="/hospitals" prefetch className="srOnly">병원 목록</Link>
    </div>
  );
}
