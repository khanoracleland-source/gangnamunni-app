"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import DetailSkeleton from "./DetailSkeleton";
import { api, assetUrl, PLACEHOLDER, qk } from "@/lib/api";
import { addrDistrict } from "./HospitalCard";
import { doctorCredentials, doctorView } from "@/lib/doctor";
import starIcon from "@/assets/icons/star.png";
import trophyIcon from "@/assets/icons/trophy.png";
import styles from "./DoctorDetail.module.css";

const BadgeIcon = () => (
  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 3l2.5 2.2 3.3-.4.4 3.3L20.4 10.6 18.2 13l.4 3.3-3.3.4L12 19.2 9.5 16.7l-3.3-.4.4-3.3L4.4 10.6l2.2-2.5-.4-3.3 3.3.4z" /><path d="M9.5 11.2l1.7 1.7 3.4-3.6" /></svg>
);

/** design 04-02 의료진 상세 */
export default function DoctorDetail({ hospitalId, doctorId }: { hospitalId: number; doctorId: number }) {
  const router = useRouter();
  const docs = useQuery({ queryKey: qk.doctors(hospitalId), queryFn: () => api.doctors(hospitalId) });
  const hosp = useQuery({ queryKey: qk.hospital(hospitalId), queryFn: () => api.hospital(hospitalId) });
  const treat = useQuery({ queryKey: qk.treatments(hospitalId), queryFn: () => api.treatments(hospitalId) });
  const back = () => (window.history.length > 1 ? router.back() : router.push(`/hospitals/${hospitalId}?tab=doctors`));

  if (docs.isPending) return <DetailSkeleton />;
  const d = docs.data?.items.find((x) => x.id === doctorId);
  if (docs.isError || !d) {
    return <div className={styles.empty}>{docs.isError ? "의료진 정보를 불러오지 못했습니다." : "의료진을 찾을 수 없습니다."}<br /><Link href={`/hospitals/${hospitalId}?tab=doctors`} className={styles.link}>의료진 목록으로</Link></div>;
  }
  const photo = assetUrl(d.photoUrl);
  const { specialty: spec, tags } = doctorView(d, treat.data?.items.slice(0, 5).map((t) => t.name) ?? []);
  const creds = doctorCredentials(d);
  const h = hosp.data;
  const thumb = h ? (assetUrl(h.thumbnailUrl || h.mainImageUrl || h.profileImageUrl) || PLACEHOLDER) : PLACEHOLDER;
  const district = h ? addrDistrict(h.address) : "";
  const badge = h?.badges.find((b) => b.includes("인기")) ?? h?.badges.find((b) => b.includes("우수")) ?? null;

  return (
    <article className={styles.root}>
      <header className={styles.top}>
        <button onClick={back} className={styles.back} aria-label="뒤로 가기">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
        <h1 className={styles.title}>{d.name}</h1>
        <span className={styles.topSpacer} aria-hidden="true" />
      </header>

      <section className={styles.card} aria-label="의료진 프로필">
        {photo ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={photo} alt="" className={styles.photo} decoding="async" />
        ) : (
          <div className={`${styles.photo} ${styles.photoEmpty}`} aria-hidden="true">
            <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#9A9395" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
          </div>
        )}
        <div className={styles.profile}>
          <div className={styles.name}>{d.name}</div>
          {spec && <div className={styles.spec}><BadgeIcon />{spec}</div>}
        </div>
      </section>

      {tags.length > 0 && (
        <section className={styles.card2} aria-labelledby="doc-fields">
          <h2 id="doc-fields" className={styles.h2}>주요 진료분야</h2>
          <div className={styles.tags}>{tags.map((t) => <span key={t} className={styles.tag}>{t}</span>)}</div>
        </section>
      )}

      {creds.length > 0 && (
        <ul className={styles.creds} aria-label="경력 및 자격">
          {creds.map((c, i) => <li key={i}>{c}</li>)}
        </ul>
      )}

      {h && (
        <>
          <h2 className={styles.h2Sec}>소속 병원</h2>
          <Link href={`/hospitals/${h.id}`} className={styles.hosp}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={thumb} alt="" className={styles.hospThumb} loading="lazy" decoding="async"
              onError={(e) => { if (e.currentTarget.src !== PLACEHOLDER) e.currentTarget.src = PLACEHOLDER; }} />
            <div className={styles.hospInfo}>
              <div className={styles.hospName}>{h.name}</div>
              {h.address && <div className={styles.hospAddr}>{district && <><span>{district}</span><span className={styles.sep}> | </span></>}{h.address}</div>}
              <div className={styles.hospMeta}>
                {h.showRating && h.rating != null && h.rating > 0 && (
                  <span className={styles.rating}>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={starIcon.src} alt="" width={13} height={13} /><b>{h.rating.toFixed(1)}</b>
                    {h.reviewCount > 0 && <span className={styles.count}>({h.reviewCount.toLocaleString("ko-KR")})</span>}
                  </span>
                )}
                {badge && (
                  <span className={styles.badge}>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={trophyIcon.src} alt="" width={13} height={13} />{badge.includes("인기") ? "인기병원" : "고객평가우수병원"}
                  </span>
                )}
              </div>
            </div>
          </Link>
        </>
      )}
    </article>
  );
}
