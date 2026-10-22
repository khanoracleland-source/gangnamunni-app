"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, normalize, qk, type Hospital, type HospitalListParams, type HospitalSort, type Page } from "@/lib/api";
import HospitalCard from "./HospitalCard";
import styles from "./HospitalList.module.css";

const SORT_LABEL: Record<HospitalSort, string> = {
  manual: "추천", price: "낮은가격", rating: "평점", popular: "인기", review: "후기",
};

export default function HospitalList({ params, initial }: { params: HospitalListParams; initial?: Page<Hospital> }) {
  const p = normalize(params);
  const { data, isFetching, isError, refetch } = useQuery({
    queryKey: qk.hospitals(p),
    queryFn: () => api.hospitals(p),
    initialData: initial,
  });

  const sorts: HospitalSort[] = p.treatment ? ["price", "rating", "popular", "review"] : ["manual", "rating", "popular", "review"];
  const linkFor = (sort: HospitalSort) => {
    const u = new URLSearchParams();
    for (const [k, v] of Object.entries({ ...p, sort })) if (v) u.set(k, v);
    return `/hospitals?${u}`;
  };

  return (
    <section>
      <div className={styles.chips} role="tablist" aria-label="정렬">
        {sorts.map((s) => (
          <Link key={s} href={linkFor(s)} replace scroll={false} role="tab" aria-selected={p.sort === s}
            className={`${styles.chip} ${p.sort === s ? styles.chipActive : ""}`}>
            {SORT_LABEL[s]}
          </Link>
        ))}
        {isFetching && <span className={styles.spin} aria-label="업데이트 중" />}
      </div>
      {(p.q || p.treatment || p.category) && (
        <div className={styles.filters}>
          {p.q && <span className={styles.filter}>검색: {p.q}</span>}
          {p.treatment && <span className={styles.filter}>{p.treatment}</span>}
          {p.category && <span className={styles.filter}>{p.category}</span>}
          <Link href="/hospitals" className={styles.clear}>초기화</Link>
        </div>
      )}
      {isError && !data && (
        <div className={styles.empty}>
          불러오지 못했습니다. <button onClick={() => refetch()} className={styles.retry}>다시 시도</button>
        </div>
      )}
      {data && data.items.length === 0 && <div className={styles.empty}>검색 결과가 없습니다.</div>}
      {data && data.items.length > 0 && (
        <div className={styles.grid}>
          {data.items.map((h) => <HospitalCard key={h.id} h={h} />)}
        </div>
      )}
    </section>
  );
}
