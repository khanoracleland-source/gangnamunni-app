"use client";
import { useQuery } from "@tanstack/react-query";
import { api, formatWon, qk } from "@/lib/api";
import styles from "./TreatmentList.module.css";

export default function TreatmentList({ hospitalId }: { hospitalId: number }) {
  const { data, isPending, isError } = useQuery({ queryKey: qk.treatments(hospitalId), queryFn: () => api.treatments(hospitalId) });
  if (isPending) return <div className={`skeleton ${styles.sk}`} aria-busy="true" />;
  if (isError) return <p className={styles.muted}>시술 목록을 불러오지 못했습니다.</p>;
  if (data.items.length === 0) return <p className={styles.muted}>등록된 시술이 없습니다.</p>;
  return (
    <ul className={styles.list}>
      {data.items.map((t) => (
        <li key={t.treatmentId} className={styles.group}>
          <div className={styles.name}>{t.promoted && <span className={styles.badge}>추천</span>}{t.name}</div>
          {t.description && <p className={styles.desc}>{t.description}</p>}
          <ul className={styles.options}>
            {t.options.map((o) => (
              <li key={o.id} className={styles.option}>
                <span className={styles.optName}>{o.displayName}</span>
                <span className={styles.priceBox}>
                  {o.discountRate > 0 && <span className={styles.rate}>{o.discountRate}%</span>}
                  {o.regularPrice > o.price && <s className={styles.regular}>{formatWon(o.regularPrice)}</s>}
                  <span className={styles.price}>{formatWon(o.price)}</span>
                </span>
              </li>
            ))}
          </ul>
        </li>
      ))}
    </ul>
  );
}
