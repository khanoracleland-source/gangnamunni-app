"use client";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, assetUrl, normalize, qk, type HospitalListParams, type HospitalPage, type HospitalSort, type InfeedAd } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
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
import styles from "./HospitalList.module.css";

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

function InfeedCard({ ad, php }: { ad: InfeedAd; php: string }) {
  const img = assetUrl(ad.imageUrl);
  const href = ad.hospitalId ? `/hospitals/${ad.hospitalId}` : ad.linkUrl.startsWith("http") || ad.linkUrl.startsWith("/") || ad.linkUrl === "#" ? ad.linkUrl : phpUrl(php, ad.linkUrl);
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
  return ad.hospitalId
    ? <Link href={href} className={styles.adCard} data-ad-id={ad.id} onClick={onClick}>{body}</Link>
    : <a href={href} className={styles.adCard} data-ad-id={ad.id} onClick={onClick}>{body}</a>;
}

export default function HospitalList({ params, initial }: { params: HospitalListParams; initial?: HospitalPage }) {
  const p = normalize(params);
  const php = usePhpBase();
  const router = useRouter();
  const qc = useQueryClient();
  // Changing the sort / dosage keeps the current list on screen until the new one arrives (no empty flash);
  // a different keyword / category / treatment / region starts clean.
  const { data, isFetching, isError, refetch } = useQuery({
    queryKey: qk.hospitals(p), queryFn: () => api.hospitals(p), initialData: initial,
    placeholderData: (prev, prevQuery) => {
      const o = prevQuery?.queryKey[1] as ReturnType<typeof normalize> | undefined;
      return o && o.q === p.q && o.category === p.category && o.treatment === p.treatment && o.region === p.region ? keepPreviousData(prev) : undefined;
    },
  });
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
  useEffect(() => {
    if (!gridRef.current || !data) return;
    const a = observeSlotImpressions(php, gridRef.current);
    const b = observeImpressions(php, gridRef.current);
    return () => { a(); b(); };
  }, [data, slotTokens, php]);

  const sortApplied: HospitalSort = (p.sort as HospitalSort) || data?.sort || "manual"; // the clicked chip lights up at once
  const eff = data?.effectiveTreatment ?? null;
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
  const sorts: HospitalSort[] = eff ? ["price", "rating", "popular", "review"] : ["rating", "popular", "review"]; // design 03-04: no 추천 chip before a treatment is chosen
  // The sort is done by the API (the page holds only the first 20 of `total`, so sorting them here would be wrong):
  // fetch the other sorts quietly after the list paints, then a chip click is served from the cache.
  const prefetchKey = data && !isFetching ? sorts.join(",") + "|" + JSON.stringify(p) : "";
  useEffect(() => {
    if (!prefetchKey) return;
    for (const s of sorts) {
      if (s === sortApplied) continue;
      const np = { ...p, sort: s };
      void qc.prefetchQuery({ queryKey: qk.hospitals(np), queryFn: () => api.hospitals(np), staleTime: 60_000 });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [prefetchKey]);
  const dosageOptions = data?.dosageOptions ?? [];
  const items = data?.items ?? [];
  const infeed = data?.infeed ?? null;
  const regions = p.region.split(",").map((s) => s.trim()).filter(Boolean);
  const cond = [...(regions.length ? [regions.join(", ")] : []), ...(p.dosage ? [p.dosage + (p.dosage === "4cc" ? " 이상" : "")] : [])];
  const hasCond = cond.length > 0 && !!eff && !!p.q; // 03-11: treatment exists, only region/dosage don't match
  // 03-14 (sr-none-flag): nothing found at all -> no dosage/sort/filter chips, just the empty state + suggestions
  const noneFlag = !!data && items.length === 0 && !hasCond;

  // In-feed ads every `interval` cards (hospital_list_content.php).
  const rows: React.ReactNode[] = [];
  let adIdx = 0;
  items.forEach((h, i) => {
    if (i > 0 && infeed && infeed.interval > 0 && i % infeed.interval === 0 && infeed.ads[adIdx]) {
      rows.push(<InfeedCard key={`ad-${infeed.ads[adIdx].id}`} ad={infeed.ads[adIdx]} php={php} />);
      adIdx++;
    }
    rows.push(<HospitalCard key={h.id} h={h} keyword={p.q || keyword} slotToken={h.adSlot ? slotTokens[String(h.adSlot.bookingId)] : undefined} boxed={!!p.q} />);
  });

  return (
    <section>
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
        {isFetching && <span className={styles.spin} aria-label="업데이트 중" />}
      </div>}
      {!noneFlag && (p.treatment || p.category) && (
        <div className={styles.filters}>
          {p.treatment && <Link href={link({ treatment: "", dosage: "", sort: "" })} className={styles.filter}>{p.treatment} <b>×</b></Link>}
          {p.category && <Link href={link({ category: "" })} className={styles.filter}>{p.category} <b>×</b></Link>}
        </div>
      )}
      {isError && !data && (
        <div className={styles.empty}>
          불러오지 못했습니다. <button onClick={() => refetch()} className={styles.retry}>다시 시도</button>
        </div>
      )}
      {data && items.length === 0 && (
        <div className={styles.srEmpty}>
          <div className={`${styles.srEmptyIco} ${hasCond ? styles.srEmptyIcoGrey : ""}`}><svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#AC1830" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="M16 16l4.5 4.5" /></svg></div>
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
      {items.length > 0 && <div ref={gridRef} className={`${styles.grid} ${p.q ? styles.gridBoxed : ""}`}>{rows}</div>}
    </section>
  );
}
