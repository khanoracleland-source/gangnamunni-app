"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, PLACEHOLDER, qk, type SearchView } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import { addrDistrict } from "./HospitalCard";
import styles from "./HospitalSearchView.module.css";

/**
 * includes/hospital_search_view.php (03-12/03-13): hospitals.php?q=이름&hid=ID[&tcat=][&sort=]
 * Hospital card + "이 병원의 시술 N" + category chips + sort chips + treatment rows.
 * Treatment rows open the PHP detail with treatment context (treatment_id) — not ported to Next yet.
 */
const SORTS = [["price", "낮은가격"], ["rating", "평점"], ["popular", "인기"], ["review", "후기"]] as const;
const label = (v: number | null, u: string | null) => (v && v > 0 && u ? ` ${String(Number(v.toFixed(2)))}${u}` : "");

export default function HospitalSearchView({ hid, q, tcat, sort, initial }: { hid: number; q: string; tcat: number; sort: string; initial?: SearchView }) {
  const php = usePhpBase();
  const { data, isError, refetch } = useQuery({ queryKey: qk.searchView(hid), queryFn: () => api.searchView(hid), initialData: initial });
  if (!data) {
    return <div className={styles.empty}>{isError ? <>불러오지 못했습니다. <button className={styles.link} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>;
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
  const rows = tcat ? data.treatments.filter((t) => t.categoryId === tcat) : data.treatments;
  const img = assetUrl(h.thumbnailUrl) || PLACEHOLDER;
  const district = addrDistrict(h.address);
  const ret = encodeURIComponent(`/next/hospitals?q=${encodeURIComponent(q)}&hid=${hid}`);
  return (
    <section>
      <Link href={`/hospitals/${hid}`} className={styles.card}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={img} alt="" onError={(e) => { if (e.currentTarget.src !== PLACEHOLDER) e.currentTarget.src = PLACEHOLDER; }} />
        <div className={styles.cardBody}>
          <div className={styles.nm}>{h.name}</div>
          <div className={styles.ad}>{district && <>{district}<i>|</i></>}{h.address}</div>
          {h.showRating && h.rating != null && h.rating > 0 && <div className={styles.rt}><b>{h.rating.toFixed(1)}</b></div>}
        </div>
        <span className={styles.go}>병원 보기<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4A4446" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M9 5l7 7-7 7" /></svg></span>
      </Link>
      <div className={styles.t}>이 병원의 시술 <span>{data.total}</span></div>
      <div className={styles.cats}>
        <Link href={base({ tcat: 0 })} replace className={`${styles.chip} ${!tcat ? styles.on : ""}`}>전체{!tcat ? ` ${data.total}` : ""}</Link>
        {data.categories.map((c) => (
          <Link key={c.id} href={base({ tcat: c.id })} replace className={`${styles.chip} ${tcat === c.id ? styles.on : ""}`}>{c.name}{tcat === c.id ? ` ${c.count}` : ""}</Link>
        ))}
      </div>
      <div className={styles.sorts}>
        {SORTS.map(([k, v]) => <Link key={k} href={base({ sort: k })} replace className={`${styles.sort} ${s === k ? styles.sortOn : ""}`}>{v}</Link>)}
      </div>
      {rows.length === 0 ? (
        <div className={styles.none}><div className={styles.noneT}>등록된 시술이 없어요</div></div>
      ) : rows.map((r) => {
        const per = r.price > 0 && r.dosageValue && r.dosageValue > 0 && r.dosageUnit && r.dosageUnit !== "회" ? `1${r.dosageUnit}당 ${Math.round(r.price / r.dosageValue).toLocaleString("ko-KR")}원` : "";
        return (
          <a key={r.id} className={styles.row} href={phpUrl(php, `detail.php?id=${hid}&treatment_id=${r.id}&treatment=${encodeURIComponent(r.name)}&return_url=${ret}`)}>
            <div className={styles.b}>
              <div className={styles.n}>{r.name}{label(r.dosageValue, r.dosageUnit)}</div>
              {r.price > 0 && <div className={styles.p}><b>{r.price.toLocaleString("ko-KR")}<small>원</small></b>{per && <em>{per}</em>}</div>}
            </div>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A9395" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" style={{ flex: "0 0 16px" }}><path d="M9 5l7 7-7 7" /></svg>
          </a>
        );
      })}
    </section>
  );
}
