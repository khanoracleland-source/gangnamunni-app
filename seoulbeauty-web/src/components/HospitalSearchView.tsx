"use client";
import Loading from "./Loading";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, PLACEHOLDER, qk, type SearchView } from "@/lib/api";
import { addrDistrict } from "./HospitalCard";
import starIcon from "@/assets/icons/star.png";
import trophyIcon from "@/assets/icons/trophy.png";
import priceOn from "@/assets/icons/sort-price.png";
import priceOff from "@/assets/icons/sort-price-off.png";
import ratingOn from "@/assets/icons/sort-rating.png";
import ratingOff from "@/assets/icons/sort-rating-off.png";
import popularOn from "@/assets/icons/sort-popular.png";
import popularOff from "@/assets/icons/sort-popular-off.png";
import reviewOn from "@/assets/icons/sort-review.png";
import reviewOff from "@/assets/icons/sort-review-off.png";
import styles from "./HospitalSearchView.module.css";

const SORT_ICON = { price: [priceOn, priceOff], rating: [ratingOn, ratingOff], popular: [popularOn, popularOff], review: [reviewOn, reviewOff] } as const;

/**
 * includes/hospital_search_view.php (03-12/03-13): hospitals.php?q=이름&hid=ID[&tcat=][&sort=]
 * Hospital card + "이 병원의 시술 N" + category chips + sort chips + treatment rows.
 * Treatment rows open the PHP detail with treatment context (treatment_id) — not ported to Next yet.
 */
const SORTS = [["price", "낮은가격"], ["rating", "평점"], ["popular", "인기"], ["review", "후기"]] as const;
const label = (v: number | null, u: string | null) => (v && v > 0 && u ? ` ${String(Number(v.toFixed(2)))}${u}` : "");

export default function HospitalSearchView({ hid, q, tcat, sort, initial }: { hid: number; q: string; tcat: number; sort: string; initial?: SearchView }) {
  const { data, isError, refetch } = useQuery({ queryKey: qk.searchView(hid), queryFn: () => api.searchView(hid), initialData: initial });
  if (!data) {
    return <div className={styles.empty}>{isError ? <>불러오지 못했습니다. <button className={styles.link} onClick={() => refetch()}>다시 시도</button></> : <Loading />}</div>;
  }
  const h = data.hospital;
  const s = (["price", "rating", "popular", "review"] as const).includes(sort as "price") ? sort : "price";
  const base = (patch: { tcat?: number; sort?: string }) => {
    const u = new URLSearchParams({ q, hid: String(hid) });
    const t = patch.tcat ?? tcat;
    if (t) u.set("tcat", String(t));
    u.set("sort", patch.sort ?? s);
    return `/hospitals?${u}`;
  };
  // chips only change the query string: swap it in place (no route request / flash)
  const swap = (patch: { tcat?: number; sort?: string }) => (e: React.MouseEvent) => {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    window.history.replaceState(null, "", base(patch).replace("/hospitals", ""));
  };
  const filtered = tcat ? data.treatments.filter((t) => t.categoryId === tcat) : data.treatments;
  // the whole treatment list of one hospital is here, so 낮은가격 sorts locally; the other sorts keep the API order
  const rows = s === "price" ? [...filtered].sort((a, b) => (a.price > 0 ? a.price : Infinity) - (b.price > 0 ? b.price : Infinity)) : filtered;
  const img = assetUrl(h.thumbnailUrl) || PLACEHOLDER;
  const district = addrDistrict(h.address);
  return (
    <section>
      <Link href={`/hospitals/${hid}`} className={styles.card}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={img} alt="" className={styles.thumb} onError={(e) => { if (e.currentTarget.src !== PLACEHOLDER) e.currentTarget.src = PLACEHOLDER; }} />
        <div className={styles.cardBody}>
          <div className={styles.nm}>{h.name}</div>
          <div className={styles.ad}>{district && <>{district}<i>|</i></>}{h.address}</div>
          <div className={styles.rt}>
            {h.showRating && h.rating != null && h.rating > 0 && <span>{/* eslint-disable-line @next/next/no-img-element */}<img src={starIcon.src} alt="" width={13} height={13} /><b>{h.rating.toFixed(1)}</b></span>}
            {h.badges.some((b) => b.includes("우수")) && <span className={styles.best}>{/* eslint-disable-line @next/next/no-img-element */}<img src={trophyIcon.src} alt="" width={13} height={13} />고객평가우수병원</span>}
          </div>
        </div>
        <span className={styles.go}>병원 보기<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4A4446" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M9 5l7 7-7 7" /></svg></span>
      </Link>
      <div className={styles.t}>이 병원의 시술 <span>{data.total}</span></div>
      <div className={styles.cats}>
        <Link href={base({ tcat: 0 })} replace onClick={swap({ tcat: 0 })} className={`${styles.chip} ${!tcat ? styles.on : ""}`}>전체{!tcat ? ` ${data.total}` : ""}</Link>
        {data.categories.map((c) => (
          <Link key={c.id} href={base({ tcat: c.id })} replace onClick={swap({ tcat: c.id })} className={`${styles.chip} ${tcat === c.id ? styles.on : ""}`}>{c.name}{tcat === c.id ? ` ${c.count}` : ""}</Link>
        ))}
      </div>
      <div className={styles.sorts}>
        {SORTS.map(([k, v]) => (
          <Link key={k} href={base({ sort: k })} replace onClick={swap({ sort: k })} className={`${styles.sort} ${s === k ? styles.sortOn : ""}`}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={SORT_ICON[k][s === k ? 0 : 1].src} alt="" width={16} height={16} />{v}
          </Link>
        ))}
      </div>
      {rows.length === 0 ? (
        <div className={styles.none}><div className={styles.noneT}>등록된 시술이 없어요</div></div>
      ) : rows.map((r) => {
        const per = r.price > 0 && r.dosageValue && r.dosageValue > 0 && r.dosageUnit && r.dosageUnit !== "회" ? `1${r.dosageUnit}당 ${Math.round(r.price / r.dosageValue).toLocaleString("ko-KR")}원` : "";
        return (
          <Link key={r.id} className={styles.row} href={`/hospitals/${hid}?treatment_id=${r.id}`}>
            <div className={styles.b}>
              <div className={styles.n}>{r.name}{label(r.dosageValue, r.dosageUnit)}</div>
              {r.price > 0 && <div className={styles.p}><b>{r.price.toLocaleString("ko-KR")}<small>원</small></b>{per && <em>{per}</em>}</div>}
            </div>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1A1718" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" style={{ flex: "0 0 16px" }}><path d="M9 5l7 7-7 7" /></svg>
          </Link>
        );
      })}
    </section>
  );
}
