"use client";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { assetUrl, normalize, type HospitalListParams, type HospitalSort, type InfeedAd } from "@/lib/api";
import { fetchAllHospitals, hospitalsAllKey, type HospitalRow } from "@/lib/hospitalsAll";
import { usePhpBase } from "@/lib/php";
import { siteHref } from "@/lib/phpLink";
import { fetchSlotTokens, observeImpressions, observeSlotImpressions, trackClick } from "@/lib/adTracking";
import HospitalCard, { addrDistrict } from "./HospitalCard";
import priceOn from "@/assets/icons/sort-price.png";
import priceOff from "@/assets/icons/sort-price-off.png";
import ratingOn from "@/assets/icons/sort-rating.png";
import ratingOff from "@/assets/icons/sort-rating-off.png";
import popularOn from "@/assets/icons/sort-popular.png";
import popularOff from "@/assets/icons/sort-popular-off.png";
import reviewOn from "@/assets/icons/sort-review.png";
import reviewOff from "@/assets/icons/sort-review-off.png";
import "./hospital-list.css";

/** hospital-list.css class names (plain, readable in the DOM instead of a CSS-module hash). */
const styles = {
  adAddr: "hl-ad-addr",
  adBadge: "hl-ad-badge",
  adCard: "hl-ad-card",
  adInfo: "hl-ad-info",
  adRounded: "hl-ad-rounded",
  adThumb: "hl-ad-thumb",
  adThumbEmpty: "hl-ad-thumb-empty",
  adTitle: "hl-ad-title",
  chip: "hl-chip",
  chipActive: "hl-chip-active",
  chipPill: "hl-chip-pill",
  chips: "hl-chips",
  district: "hl-district",
  dosage: "hl-dosage",
  dosageT: "hl-dosage-t",
  empty: "hl-empty",
  filter: "hl-filter",
  filters: "hl-filters",
  grid: "hl-grid",
  gridBoxed: "hl-grid-boxed",
  gridRounded: "hl-grid-rounded",
  retry: "hl-retry",
  selChip: "hl-sel-chip",
  selRow: "hl-sel-row",
  selT: "hl-sel-t",
  sep: "hl-sep",
  srChip: "hl-sr-chip",
  srChipActive: "hl-sr-chip-active",
  srEmpty: "hl-sr-empty",
  srEmptyBtn: "hl-sr-empty-btn",
  srEmptyIco: "hl-sr-empty-ico",
  srEmptySub: "hl-sr-empty-sub",
  srEmptyTitle: "hl-sr-empty-title",
  srNone: "hl-sr-none",
  srSugg: "hl-sr-sugg",
  srSuggChip: "hl-sr-sugg-chip",
  srSuggChips: "hl-sr-sugg-chips",
  srSuggT: "hl-sr-sugg-t",
  treat: "hl-treat",
} as const;

/**
 * Same orders as the API (hospital-query.ts). The rows come with the cheapest price line (server sort=price), whose priceMin is the
 * cheapest option or the treatment's average (the API's own fallback), so it is the API's sort key. Ties: rating, then id.
 * Paid slots are pinned on top for every sort except price (hospitals.service.ts, like hospital_list_data.php).
 */
type Row = HospitalRow;
function sortHospitals(list: Row[], sort: HospitalSort): Row[] {
  const r = (h: Row) => h.rating ?? -1;
  // no treatment in the search: the API's price order is the hospital's own lowest price
  const byLine = list.some((h) => h.treatment);
  const price = (h: Row) => { const v = byLine ? (h.treatment?.priceMin ?? 0) : (h.minPrice ?? 0); return v > 0 ? v : 2147483647; };
  const cmp: Record<HospitalSort, (a: Row, b: Row) => number> = {
    manual: () => 0,
    price: (a, b) => price(a) - price(b) || r(b) - r(a),
    rating: (a, b) => r(b) - r(a) || b.reviewCount - a.reviewCount,
    review: (a, b) => b.reviewCount - a.reviewCount || r(b) - r(a),
    popular: (a, b) => b.wishlistCount - a.wishlistCount || r(b) - r(a) || b.reviewCount - a.reviewCount,
  };
  const pin = sort !== "price";
  // a paid slot that is not in the filtered list shows only where the API pins slots (not on price)
  if (!pin) list = list.filter((h) => !h.slotOnly);
  else list = list.map((h) => (h.treatmentAlt ? { ...h, treatment: h.treatmentAlt } : h)); // non-price sorts show the representative price, like the API
  const ads = pin ? list.filter((h) => h.adSlot).sort((a, b) => a.adSlot!.rank - b.adSlot!.rank) : [];
  const rest = pin ? list.filter((h) => !h.adSlot) : [...list];
  if (sort !== "manual") rest.sort((a, b) => cmp[sort](a, b) || a.id - b.id);
  return [...ads, ...rest];
}

const PAGE = 20; // cards added to the screen per step

const SORT_ICON: Partial<Record<HospitalSort, { on: { src: string }; off: { src: string } }>> = {
  price: { on: priceOn, off: priceOff }, rating: { on: ratingOn, off: ratingOff }, popular: { on: popularOn, off: popularOff }, review: { on: reviewOn, off: reviewOff },
};

const SORT_LABEL: Record<HospitalSort, string> = { manual: "추천", price: "낮은가격", rating: "평점", popular: "인기", review: "후기" };
const EMPTY_SUGG = ["텐써마", "울쎄라", "슈링크", "인모드"]; // hospital_list_content.php $_sugg

/** 이/가 particle for the "no results" sentence (sr_josa_i_ga). */
function josa(w: string): string {
  const cp = w.trim().slice(-1).codePointAt(0) ?? 0;
  if (cp >= 0xac00 && cp <= 0xd7a3) return (cp - 0xac00) % 28 !== 0 ? "이" : "가";
  return "이(가)";
}

function InfeedCard({ ad, php, rounded }: { ad: InfeedAd; php: string; rounded?: boolean }) {
  const img = assetUrl(ad.imageUrl);
  const href = ad.hospitalId ? `/hospitals/${ad.hospitalId}` : siteHref(php, ad.linkUrl);
  const district = addrDistrict(ad.subtitle);
  const body = (
    <>
      {img ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={img} alt="광고" className={styles.adThumb} loading="lazy" decoding="async" />
      ) : <div className={`${styles.adThumb} ${styles.adThumbEmpty}`} />}
      <span className={styles.adBadge}>AD</span>
      <div className={styles.adInfo}>
        <div className={styles.adTitle}>{ad.title}</div>
        <div className={styles.adAddr}>{district && <><span className={styles.district}>{district}</span><span className={styles.sep}> | </span></>}{(ad.subtitle || "").slice(0, 15)}</div>
      </div>
    </>
  );
  const onClick = () => trackClick(php, ad.id, href);
  const cls = `${styles.adCard} ${rounded ? styles.adRounded : ""}`;
  return ad.hospitalId
    ? <Link href={href} className={cls} data-ad-id={ad.id} onClick={onClick}>{body}</Link>
    : <a href={href} className={cls} data-ad-id={ad.id} onClick={onClick}>{body}</a>;
}

export default function HospitalList({ params }: { params: HospitalListParams }) {
  const p = normalize(params);
  const php = usePhpBase();
  const router = useRouter();
  // ALL hospitals of the filter are loaded once (one cache entry, whatever the sort) and sorted here: a sort chip never calls the API.
  // Changing the dosage keeps the current list on screen until the new one arrives (no empty flash);
  // a different keyword / category / treatment / region starts clean.
  const { data: raw, isError, refetch } = useQuery({
    queryKey: hospitalsAllKey(p),
    queryFn: () => fetchAllHospitals(p),
    staleTime: 60_000, refetchOnMount: true, // the full list is heavy: painted from the cache, asked again only when a minute old
    placeholderData: (prev, prevQuery) => {
      const o = prevQuery?.queryKey[1] as ReturnType<typeof normalize> | undefined;
      return o && o.q === p.q && o.category === p.category && o.treatment === p.treatment && o.region === p.region ? keepPreviousData(prev) : undefined;
    },
  });
  // No sort in the URL: the API's default (price when a treatment is effective, else its manual order), known at once for a picked 시술.
  const sortKey: HospitalSort = (p.sort as HospitalSort) || raw?.sort || (p.treatment && !p.q ? "price" : "manual");
  const data = raw ? { ...raw, items: sortHospitals(raw.items, sortKey), sort: sortKey } : raw;
  const gridRef = useRef<HTMLDivElement>(null);
  const [slotTokens, setSlotTokens] = useState<Record<string, string>>({});

  // Paid slot cards: ask PHP for tracking tokens (IP-bound, signed there), then observe impressions like footer.php.
  const bookingKey = (data?.items ?? []).filter((h) => h.adSlot).map((h) => h.adSlot!.bookingId).join(",");
  useEffect(() => {
    if (!bookingKey) return;
    let alive = true;
    fetchSlotTokens(php, bookingKey.split(",").map(Number)).then((t) => { if (alive) setSlotTokens(t); });
    return () => { alive = false; };
  }, [bookingKey, php]);

  const sortApplied = sortKey; // the clicked chip lights up at once
  const eff = data?.effectiveTreatment ?? null;
  // The 시술 comes from the URL, so the 05-01 layout is chosen at once (not after the answer arrives, which showed the old layout first).
  const treatPending = !data && !!p.treatment && !p.q;
  const keyword = eff ? eff.name : p.treatment;
  const link = (patch: Record<string, string>) => {
    const u = new URLSearchParams();
    for (const [k, v] of Object.entries({ ...p, ...patch })) if (v) u.set(k, v);
    return `/hospitals${u.toString() ? `?${u}` : ""}`;
  };
  // Filter chips change only the query string: swap it in place (history.replaceState — Next syncs useSearchParams)
  // instead of a router navigation, which re-requests the route and flashes the page.
  const swap = (patch: Record<string, string>) => (e: React.MouseEvent) => {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    const u = new URLSearchParams();
    for (const [k, v] of Object.entries({ ...p, ...patch })) if (v) u.set(k, v);
    window.history.replaceState(null, "", u.toString() ? `?${u}` : window.location.pathname);
  };
  // hospitals.php: 낮은가격 only when a treatment is effective; 추천(manual) is the default otherwise.
  const sorts: HospitalSort[] = eff || treatPending ? ["price", "rating", "popular", "review"] : ["rating", "popular", "review"]; // design 03-04: no 추천 chip before a treatment is chosen
  // The whole list is in the cache, so a sort chip only re-orders it here (no API call). Cards are put on screen 20 at a time
  // as the end of the list nears, so a long list does not cost a long first paint.
  const dosageOptions = data?.dosageOptions ?? [];
  const listKey = JSON.stringify({ ...p, sort: undefined }) + "|" + sortApplied;
  const [shown, setShown] = useState<{ key: string; n: number }>({ key: "", n: PAGE });
  const count = shown.key === listKey ? shown.n : PAGE;
  const allItems = data?.items ?? [];
  const items = allItems.slice(0, count);
  useEffect(() => {
    if (!gridRef.current || !data) return;
    const a = observeSlotImpressions(php, gridRef.current);
    const b = observeImpressions(php, gridRef.current);
    return () => { a(); b(); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [raw, sortKey, slotTokens, php, items.length]);
  const hasMore = count < allItems.length;
  const sentinelRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const el = sentinelRef.current;
    if (!el || !hasMore) return;
    const io = new IntersectionObserver((es) => {
      if (es[0].isIntersecting) setShown({ key: listKey, n: count + PAGE });
    }, { rootMargin: "600px 0px" });
    io.observe(el);
    return () => io.disconnect();
  }, [hasMore, listKey, count]);
  const infeed = data?.infeed ?? null;
  const regions = p.region.split(",").map((s) => s.trim()).filter(Boolean);
  const cond = [...(regions.length ? [regions.join(", ")] : []), ...(p.dosage ? [p.dosage + (p.dosage === "4cc" ? " 이상" : "")] : [])];
  // design 05-01: a treatment picked from the home 시술 list (no keyword): "선택한 시술" row, dosage / sort pills, rounded price cards
  const treatMode = (!!eff || treatPending) && !p.q;
  // Rounded (bordered) cards for every browse list, including the plain /hospitals list; only a keyword search keeps the divided rows (03-09).
  const roundedCards = !p.q;
  const treatName = eff?.name ?? p.treatment;
  const hasCond = cond.length > 0 && !!eff && !!p.q; // 03-11: treatment exists, only region/dosage don't match
  // 03-14 (sr-none-flag): nothing found at all -> no dosage/sort/filter chips, just the empty state + suggestions
  const noneFlag = !!data && items.length === 0 && !hasCond;

  // In-feed ads every `interval` cards (hospital_list_content.php).
  const rows: React.ReactNode[] = [];
  let adIdx = 0;
  items.forEach((h, i) => {
    if (i > 0 && infeed && infeed.interval > 0 && i % infeed.interval === 0 && infeed.ads[adIdx]) {
      rows.push(<InfeedCard key={`ad-${infeed.ads[adIdx].id}`} ad={infeed.ads[adIdx]} php={php} rounded={roundedCards} />);
      adIdx++;
    }
    rows.push(<HospitalCard key={h.id} h={h} keyword={p.q || keyword} slotToken={h.adSlot ? slotTokens[String(h.adSlot.bookingId)] : undefined} boxed={!!p.q} rounded={roundedCards} treatmentId={eff?.id} />);
  });

  return (
    <section className={treatMode ? styles.treat : undefined}>
      {!noneFlag && treatMode && treatName && (
        <div className={styles.selRow}>
          <span className={styles.selT}>선택한 시술</span>
          <Link href={link({ treatment: "", dosage: "", sort: "" })} className={styles.selChip} aria-label={`${treatName} 선택 해제`}>
            {treatName}
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </Link>
        </div>
      )}
      {!noneFlag && dosageOptions.length > 1 && (
        <div className={styles.dosage} role="group" aria-label="용량 선택">
          <span className={styles.dosageT}>용량</span>
          <Link href={link({ dosage: "" })} replace scroll={false} onClick={swap({ dosage: "" })} className={`${styles.srChip} ${!p.dosage ? styles.srChipActive : ""}`}>전체</Link>
          {dosageOptions.map((d) => (
            <Link key={d} href={link({ dosage: d })} replace scroll={false} onClick={swap({ dosage: d })} className={`${styles.srChip} ${p.dosage === d ? styles.srChipActive : ""}`}>{d}</Link>
          ))}
        </div>
      )}
      {!noneFlag && <div className={styles.chips} role="tablist" aria-label="정렬">
        {sorts.map((s) => (
          <Link key={s} href={link({ sort: s })} replace scroll={false} onClick={swap({ sort: s })} role="tab" aria-selected={sortApplied === s}
            className={`${styles.chip} ${p.q ? styles.chipPill : ""} ${sortApplied === s ? styles.chipActive : ""}`}>
            {SORT_ICON[s] && (/* eslint-disable-line @next/next/no-img-element */ <img src={(sortApplied === s ? SORT_ICON[s]!.on : SORT_ICON[s]!.off).src} alt="" width={16} height={16} />)}
            {SORT_LABEL[s]}
          </Link>
        ))}
      </div>}
      {!noneFlag && ((p.treatment && !treatMode) || p.category) && (
        <div className={styles.filters}>
          {p.treatment && !treatMode && <Link href={link({ treatment: "", dosage: "", sort: "" })} className={styles.filter}>{p.treatment} <b>×</b></Link>}
          {p.category && <Link href={link({ category: "" })} className={styles.filter}>{p.category} <b>×</b></Link>}
        </div>
      )}
      {isError && !data && (
        <div className={styles.empty}>
          불러오지 못했습니다. <button onClick={() => refetch()} className={styles.retry}>다시 시도</button>
        </div>
      )}
      {data && items.length === 0 && (
        <div className={`${styles.srEmpty} ${hasCond ? "" : styles.srNone}`}>
          <div className={styles.srEmptyIco}><svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#AC1830" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="M16 16l4.5 4.5" /></svg></div>
          {hasCond ? (
            <>
              <div className={styles.srEmptyTitle}>조건에 맞는 병원이 없어요</div>
              <div className={styles.srEmptySub}>&lsquo;{cond.join(" · ")}&rsquo; 조건의 {p.q}{josa(p.q)} 없어요<br />지역을 넓히거나 용량을 바꿔보세요</div>
              <button type="button" className={styles.srEmptyBtn} onClick={() => { try { localStorage.setItem("sb_selected_regions", "[]"); } catch { /* ignore */ } router.push(link({ region: "", dosage: "" })); }}>필터 초기화</button>
            </>
          ) : (
            <>
              <div className={styles.srEmptyTitle}>{p.q ? `'${p.q}' 검색 결과가 없어요` : "검색 결과가 없어요"}</div>
              <div className={styles.srEmptySub}>철자를 확인하거나 다른 단어로 검색해보세요</div>
            </>
          )}
        </div>
      )}
      {data && items.length === 0 && !hasCond && (
        <div className={styles.srSugg}>
          <div className={styles.srSuggT}>이런 시술은 어떠세요?</div>
          <div className={styles.srSuggChips}>{EMPTY_SUGG.map((w) => <Link key={w} href={`/hospitals?q=${encodeURIComponent(w)}`} className={styles.srSuggChip}>{w}</Link>)}</div>
        </div>
      )}
      {items.length > 0 && <div ref={gridRef} className={`${styles.grid} ${p.q ? styles.gridBoxed : styles.gridRounded}`}>{rows}</div>}
      {hasMore && <div ref={sentinelRef} style={{ height: 48 }} aria-hidden="true" />}
    </section>
  );
}
