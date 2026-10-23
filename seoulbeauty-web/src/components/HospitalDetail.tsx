"use client";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, formatWon, PLACEHOLDER, qk, type Hospital } from "@/lib/api";
import ReviewList from "./ReviewList";
import styles from "./HospitalDetail.module.css";

const DAYS = ["월", "화", "수", "목", "금", "토", "일", "점심"];

export default function HospitalDetail({ id, initial }: { id: number; initial?: Hospital }) {
  const router = useRouter();
  const { data: h, isError, refetch } = useQuery({
    queryKey: qk.hospital(id),
    queryFn: () => api.hospital(id),
    initialData: initial,
  });

  if (!h) {
    return (
      <div className={styles.empty}>
        {isError ? <>불러오지 못했습니다. <button className={styles.link} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}
      </div>
    );
  }

  const hero = assetUrl(h.thumbnailUrl || h.mainImageUrl || h.profileImageUrl) || PLACEHOLDER;
  const price = formatWon(h.minPrice);
  const hours = h.businessHours && typeof h.businessHours === "object"
    ? DAYS.filter((d) => typeof h.businessHours![d] === "string").map((d) => [d, h.businessHours![d] as string] as const)
    : [];
  const back = () => (window.history.length > 1 ? router.back() : router.push("/hospitals"));

  return (
    <article>
      <header className={styles.top}>
        <button onClick={back} className={styles.back} aria-label="뒤로">‹</button>
        <h1 className={styles.title}>{h.name}</h1>
      </header>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={hero} alt="" className={styles.hero}
        onError={(e) => { if (e.currentTarget.src !== PLACEHOLDER) e.currentTarget.src = PLACEHOLDER; }} />
      <section className={styles.body}>
        <div className={styles.meta}>
          {h.showRating && h.rating != null && h.rating > 0 && <span>★ {h.rating.toFixed(1)}</span>}
          <span>후기 {h.reviewCount}</span>
          {h.wishlistCount > 0 && <span>찜 {h.wishlistCount}</span>}
          {price && <span className={styles.price}>{price}~</span>}
        </div>
        {h.address && <p className={styles.row}>📍 {h.address}</p>}
        {h.phone && <p className={styles.row}><a href={`tel:${h.phone.replace(/[^\d+]/g, "")}`} className={styles.link}>📞 {h.phone}</a></p>}
        {h.subwayNames.length > 0 && <p className={styles.row}>🚇 {h.subwayNames.join(", ")}</p>}
        {h.treatmentTags.length > 0 && (
          <div className={styles.tags}>{h.treatmentTags.map((t) => <span key={t} className={styles.tag}>{t}</span>)}</div>
        )}
        {h.introduction && (
          <>
            <h2 className={styles.h2}>병원 소개</h2>
            <p className={styles.intro}>{h.introduction}</p>
          </>
        )}
        {hours.length > 0 && (
          <>
            <h2 className={styles.h2}>진료시간</h2>
            <dl className={styles.hours}>
              {hours.map(([d, v]) => (<div key={d}><dt>{d}</dt><dd>{v}</dd></div>))}
            </dl>
          </>
        )}
        <h2 className={styles.h2}>후기</h2>
        <ReviewList hospitalId={h.id} />
      </section>
    </article>
  );
}
