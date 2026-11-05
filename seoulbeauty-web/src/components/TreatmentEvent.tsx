"use client";
import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, qk } from "@/lib/api";
import styles from "./TreatmentEvent.module.css";

/**
 * design 05-08 (detail.php?treatment_id=): the 홈 tab opens with the chosen treatment's "이벤트" — its price options,
 * what the treatment is, and the detail images the hospital registered for it — before the usual hospital sections.
 * Rendered only when the detail was reached with a treatment context (the 05-01 list); nothing when the hospital
 * does not offer that treatment.
 */
export default function TreatmentEvent({ hospitalId, treatmentId }: { hospitalId: number; treatmentId: number }) {
  const treat = useQuery({ queryKey: qk.treatments(hospitalId), queryFn: () => api.treatments(hospitalId) });
  const imgs = useQuery({ queryKey: qk.treatmentImages(hospitalId, treatmentId), queryFn: () => api.treatmentImages(hospitalId, treatmentId) });
  const [more, setMore] = useState(false);
  const t = treat.data?.items.find((x) => x.treatmentId === treatmentId);
  if (treat.isPending) return <div className={styles.skWrap} aria-busy="true"><div className={`skeleton ${styles.sk}`} /><div className={`skeleton ${styles.sk}`} /></div>;
  if (!t) return null;
  const images = imgs.data?.items ?? [];
  const shown = more ? images : images.slice(0, 1);
  return (
    <section className={styles.wrap} aria-labelledby="ev-h">
      <h2 id="ev-h" className={styles.h2}>어떤 이벤트를 예약하시겠어요?</h2>
      <p className={styles.note}>시술에 대한 정보는 참고 사항이며, 병원 방문 시 개인의 상태에 따라 달라질 수 있어요</p>
      {t.options.map((o) => (
        <Link key={o.id} href={`/booking?id=${hospitalId}&treatment_id=${t.treatmentId}&option_id=${o.id}&treatment=${encodeURIComponent(t.name)}&from=hospital`} className={styles.card}>
          <div className={styles.name}>{o.displayName || t.name}</div>
          <div className={styles.priceRow}>
            {o.regularPrice > o.price && <span className={styles.regular}><s>{o.regularPrice.toLocaleString("ko-KR")}</s></span>}
            <span className={styles.price}>{o.price.toLocaleString("ko-KR")}</span><span className={styles.won}>원</span>
          </div>
        </Link>
      ))}
      {t.options.length === 0 && <p className={styles.empty}>등록된 가격이 없습니다. 상담으로 문의해주세요.</p>}

      <div className={styles.gap} />
      {t.description && (
        <>
          <h2 className={styles.h2b}>시술에 대해 안내드릴게요</h2>
          <p className={styles.desc}>{t.description}</p>
        </>
      )}

      <h2 className={styles.h2c}>상세 정보를 확인해주세요</h2>
      {images.length > 0 ? (
        <div className={styles.detail}>
          {shown.map((im, i) => (
            // eslint-disable-next-line @next/next/no-img-element
            <img key={i} src={assetUrl(im.imageUrl) || ""} alt={im.caption ?? ""} className={`${styles.detailImg} ${!more && images.length > 1 ? styles.detailClip : ""}`} loading="lazy" decoding="async" />
          ))}
        </div>
      ) : (
        <div className={styles.placeholder}>
          <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#9A9395" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 15l5-5 4 4 3-3 6 6" /><circle cx="16" cy="9" r="1.5" /></svg>
          <div className={styles.phTitle}>{t.name} 상세 정보</div>
          <div className={styles.phText}>병원이 등록한 상세 이미지 · 내용이<br />준비되면 여기에 노출돼요</div>
        </div>
      )}
      {images.length > 1 && !more && (
        <div className={styles.moreWrap}><button type="button" className={styles.moreBtn} onClick={() => setMore(true)}>상세 정보 더보기</button></div>
      )}
      <div className={styles.gap} />
    </section>
  );
}
