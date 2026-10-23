"use client";
import Link from "next/link";
import { useQueryClient } from "@tanstack/react-query";
import { api, assetUrl, formatWon, PLACEHOLDER, qk, type Hospital } from "@/lib/api";
import styles from "./HospitalCard.module.css";

export default function HospitalCard({ h }: { h: Hospital }) {
  const qc = useQueryClient();
  const img = assetUrl(h.thumbnailUrl) || PLACEHOLDER;
  const price = formatWon(h.minPrice);
  // Warm the detail cache on touch/hover so the detail page paints instantly.
  const prefetch = () => {
    qc.prefetchQuery({ queryKey: qk.hospital(h.id), queryFn: () => api.hospital(h.id) });
    qc.prefetchQuery({ queryKey: qk.reviews(h.id), queryFn: () => api.reviews(h.id) });
  };
  return (
    <Link
      href={`/hospitals/${h.id}`}
      className={styles.card}
      onTouchStart={prefetch}
      onMouseEnter={prefetch}
      onFocus={prefetch}
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- images live on the PHP site */}
      <img src={img} alt="" className={styles.thumb} loading="lazy" decoding="async"
        onError={(e) => { if (e.currentTarget.src !== PLACEHOLDER) e.currentTarget.src = PLACEHOLDER; }} />
      <div className={styles.info}>
        <div className={styles.name}>{h.name}</div>
        {h.address && <div className={styles.addr}>{h.address}</div>}
        <div className={styles.meta}>
          {h.showRating && h.rating != null && h.rating > 0 && (
            <span className={styles.rating}>★ {h.rating.toFixed(1)}</span>
          )}
          {h.reviewCount > 0 && <span>후기 {h.reviewCount}</span>}
          {price && <span className={styles.price}>{price}~</span>}
        </div>
        {h.treatmentTags.length > 0 && (
          <div className={styles.tags}>
            {h.treatmentTags.slice(0, 3).map((t) => (
              <span key={t} className={styles.tag}>{t}</span>
            ))}
          </div>
        )}
      </div>
    </Link>
  );
}
