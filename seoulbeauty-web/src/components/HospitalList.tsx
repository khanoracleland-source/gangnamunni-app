"use client";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl, type Hospital, normalize, qk, type HospitalListParams, type HospitalPage, type HospitalSort, type InfeedAd } from "@/lib/api";
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
type Row = Hospital & { slotOnly?: boolean };
function sortHospitals(list: Row[], sort: HospitalSort): Row[] {
  const r = (h: Row) => h.rating ?? -1;
  const price = (h: Row) => (h.treatment && h.treatment.priceMin > 0 ? h.treatment.priceMin : 2147483647);
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

/**
 * Every page of the list for the treatment (sort=price: rows carry the cheapest price line), fetched in parallel, plus the paid slots:
 * the API pins them only on page 1 of a non-price sort, so one extra call (sort=rating) supplies them and they are marked on the rows here.
 */
async function fetchAllHospitals(p: HospitalListParams): Promise<HospitalPage> {
  // Old API (limit max 100, no `both`) must keep working while the new one is rolling out: fall back to 100 per page.
  const run = async (size: number, maxOffset: number) => {
    const base = { ...p, sort: "price" as const, limit: size, both: "1" as const };
    const first = await api.hospitals({ ...base, offset: 0 });
    const offs: number[] = [];
    for (let o = first.items.length; o < Math.min(first.total, maxOffset + 1); o += size) offs.push(o);
    const rest = await Promise.all(offs.map((offset) => api.hospitals({ ...base, offset })));
    return [first, ...rest];
  };
  const [pages, pinned] = await Promise.all([
    run(300, 5000).catch((e) => { if (e instanceof ApiError && e.status === 400) return run(100, 1000); throw e; }), // only the old API's "limit > 100 / offset > 1000" => 400
    api.hospitals({ ...p, sort: "rating", limit: 20, offset: 0 }).catch(() => null), // slots are optional: never fail the list
  ]);
  const first = pages[0];
  const slots = new Map((pinned?.items ?? []).filter((h) => h.adSlot).map((h) => [h.id, h]));
  const seen = new Set<number>();
  const items = pages.flatMap((r) => r.items).filter((h) => !seen.has(h.id) && !!seen.add(h.id)).map((h) => (slots.has(h.id) ? { ...h, adSlot: slots.get(h.id)!.adSlot } : h));
  for (const [id, h] of slots) if (!seen.has(id)) items.push({ ...h, slotOnly: true } as Row); // a paid slot bypasses the filters, like the API
  return { ...first, items };
}

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

export default function HospitalList({ params, initial }: { params: HospitalListParams; initial?: HospitalPage }) {
  const p = normalize(params);
  const php = usePhpBase();
  const router = useRouter();
  const qc = useQueryClient();
  // Changing the sort / dosage keeps the current list on screen until the new one arrives (no empty flash);
  // a different keyword / category / treatment / region starts clean.
  // A 시술 picked from the home table: load ALL its hospitals once (any sort) and sort them here, so a sort chip never calls the API.
  const allMode = !!p.treatment && !p.q;
  const { data: raw, isFetching, isError, refetch, isPlaceholderData } = useQuery({
    queryKey: allMode ? ([...qk.hospitals({ ...p, sort: "" }), "all"] as const) : qk.hospitals(p),
    queryFn: () => (allMode ? fetchAllHospitals(p) : api.hospitals(p)),
    initialData: allMode ? undefined : initial,
    staleTime: allMode ? 60_000 : undefined, refetchOnMount: allMode ? true : "always", // all-list is heavy: no refetch on every mount
    placeholderData: (prev, prevQuery) => {
      const o = prevQuery?.queryKey[1] as ReturnType<typeof normalize> | undefined;
      if (o && o.q === p.q && o.category === p.category && o.treatment === p.treatment && o.region === p.region) return keepPreviousData(prev);
      return allMode ? initial : undefined; // first 20 (already in server order) while the full list loads
    },
  });
  const sortKey: HospitalSort = (p.sort as HospitalSort) || (allMode ? "price" : raw?.sort) || "manual";
  const data = raw && allMode && !isPlaceholderData ? { ...raw, items: sortHospitals(raw.items, sortKey), sort: sortKey } : raw;
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

  const sortApplied: HospitalSort = (p.sort as HospitalSort) || data?.sort || "manual"; // the clicked chip lights up at once
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
  // The sort is done by the API (the page holds only the first 20 of `total`, so sorting them here would be wrong):
  // fetch the other sorts quietly after the list paints, then a chip click is served from the cache.
  const prefetchKey = data && !isFetching && !allMode ? sorts.join(",") + "|" + JSON.stringify(p) : "";
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
  // The API serves 20 at a time: the rest load by themselves as the end of the list nears (offset paging).
  const listKey = JSON.stringify({ ...p, sort: undefined, dosage: p.dosage }) + "|" + sortApplied;
  const [extra, setExtra] = useState<{ key: string; items: HospitalPage["items"]; done: boolean }>({ key: "", items: [], done: false });
  const loadingMore = useRef(false);
  const extraItems = extra.key === listKey ? extra.items : [];
  const baseItems = data?.items ?? [];
  const seen = new Set(baseItems.map((h) => h.id));
  const items = [...baseItems, ...extraItems.filter((h) => !seen.has(h.id))];
  useEffect(() => {
    if (!gridRef.current || !data) return;
    const a = observeSlotImpressions(php, gridRef.current);
    const b = observeImpressions(php, gridRef.current);
    return () => { a(); b(); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [raw, sortKey, slotTokens, php, items.length]);
  const hasMore = !!data && extra.key !== listKey ? baseItems.length < data.total : !!data && !extra.done && items.length < data.total;
  const sentinelRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const el = sentinelRef.current;
    if (!el || !hasMore || isFetching) return;
    const io = new IntersectionObserver((es) => {
      if (!es[0].isIntersecting || loadingMore.current) return;
      loadingMore.current = true;
      const key = listKey, have = items.length;
      api.hospitals({ ...p, sort: sortApplied === "manual" ? p.sort : sortApplied, offset: have })
        .then((r) => setExtra((e) => {
          const prev = e.key === key ? e.items : [];
          const ids = new Set([...baseItems, ...prev].map((h) => h.id));
          const add = r.items.filter((h) => !ids.has(h.id));
          return { key, items: [...prev, ...add], done: add.length === 0 };
        }))
        .catch(() => setExtra((e) => ({ key, items: e.key === key ? e.items : [], done: true })))
        .finally(() => { loadingMore.current = false; });
    }, { rootMargin: "600px 0px" });
    io.observe(el);
    return () => io.disconnect();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [hasMore, isFetching, listKey, items.length]);
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
