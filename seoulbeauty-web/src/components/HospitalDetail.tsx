"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, assetUrl, formatWon, PLACEHOLDER, qk, type DetailTab, type HospitalDetail as Detail } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import ReviewList from "./ReviewList";
import TreatmentList from "./TreatmentList";
import DoctorList from "./DoctorList";
import styles from "./HospitalDetail.module.css";

const DAYS = ["월", "화", "수", "목", "금", "토", "일"];
const TAB_LABEL: Record<DetailTab, string> = { home: "홈", treatments: "시술목록", reviews: "후기", doctors: "의료진" };

export default function HospitalDetail({ id, initial, initialTab = "home" }: { id: number; initial?: Detail; initialTab?: DetailTab }) {
  const router = useRouter();
  const qc = useQueryClient();
  const php = usePhpBase();
  const [tab, setTab] = useState<DetailTab>(initialTab);
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
  const bh = (h.businessHours && typeof h.businessHours === "object" ? h.businessHours : {}) as Record<string, unknown>;
  const hours = DAYS.filter((d) => typeof bh[d] === "string").map((d) => [d, bh[d] as string] as const);
  const lunch = typeof bh["점심"] === "string" ? (bh["점심"] as string) : null;
  // Korea time on both server and client, so SSR and hydration agree on "today".
  const today = new Intl.DateTimeFormat("ko-KR", { weekday: "short", timeZone: "Asia/Seoul" }).format(new Date());
  const back = () => (window.history.length > 1 ? router.back() : router.push("/hospitals"));
  const selectTab = (t: DetailTab) => {
    setTab(t);
    if (t === "treatments") qc.prefetchQuery({ queryKey: qk.treatments(id), queryFn: () => api.treatments(id) });
    if (t === "doctors") qc.prefetchQuery({ queryKey: qk.doctors(id), queryFn: () => api.doctors(id) });
    try {
      const u = new URL(window.location.href);
      if (t === "home") u.searchParams.delete("tab"); else u.searchParams.set("tab", t);
      window.history.replaceState(window.history.state, "", u);
    } catch { /* ignore */ }
  };

  return (
    <article className={styles.root}>
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
        {hours.length > 0 && (
          <p className={styles.row}>🕒 오늘({today}) {bh[today] as string ?? "휴무"}</p>
        )}
        {tab === "home" && (
          <>
            {h.address && <p className={styles.row}>📍 {h.address}</p>}
            {h.phone && <p className={styles.row}><a href={`tel:${h.phone.replace(/[^\d+]/g, "")}`} className={styles.link}>📞 {h.phone}</a></p>}
            {h.subwayNames.length > 0 && <p className={styles.row}>🚇 {h.subwayNames.join(", ")}</p>}
            {h.features.length > 0 && (
              <div className={styles.features}>
                {h.features.map((f, i) => (
                  <div key={i} className={styles.feature}><span className={styles.featureIcon}>{f.icon}</span><span>{f.label}</span></div>
                ))}
              </div>
            )}
          </>
        )}
      </section>

      <nav className={styles.tabs} role="tablist">
        {(Object.keys(TAB_LABEL) as DetailTab[]).map((t) => (
          <button key={t} role="tab" aria-selected={tab === t} className={`${styles.tab} ${tab === t ? styles.tabActive : ""}`} onClick={() => selectTab(t)}>
            {TAB_LABEL[t]}
          </button>
        ))}
      </nav>

      <section className={styles.body}>
        {tab === "home" && (
          <>
            {h.treatmentTags.length > 0 && (
              <div className={styles.tags}>{h.treatmentTags.map((t) => <span key={t} className={styles.tag}>{t}</span>)}</div>
            )}
            {h.introduction && (<><h2 className={styles.h2}>병원 소개</h2><p className={styles.intro}>{h.introduction}</p></>)}
            {h.images.length > 0 && (
              <>
                <h2 className={styles.h2}>병원 사진</h2>
                <div className={styles.gallery}>
                  {h.images.slice(0, 8).map((src, i) => (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img key={i} src={assetUrl(src) || ""} alt="" loading="lazy" />
                  ))}
                </div>
              </>
            )}
            {hours.length > 0 && (
              <>
                <h2 className={styles.h2}>진료시간</h2>
                <dl className={styles.hours}>
                  {hours.map(([d, v]) => (<div key={d} className={d === today ? styles.todayRow : undefined}><dt>{d}</dt><dd>{v}</dd></div>))}
                  {lunch && <div><dt>점심</dt><dd>{lunch}</dd></div>}
                </dl>
              </>
            )}
          </>
        )}
        {tab === "treatments" && <TreatmentList hospitalId={id} />}
        {tab === "reviews" && <ReviewList hospitalId={id} />}
        {tab === "doctors" && <DoctorList hospitalId={id} />}
      </section>

      {/* Booking still lives in PHP (same origin keeps the app's session). 상담 신청 modal: not ported yet. */}
      <div className={styles.cta}>
        {h.phone && <a href={`tel:${h.phone.replace(/[^\d+]/g, "")}`} className={styles.ctaSecondary}>전화</a>}
        <a href={phpUrl(php, `booking.php?id=${id}`)} className={styles.ctaPrimary}>예약하기</a>
      </div>
    </article>
  );
}
