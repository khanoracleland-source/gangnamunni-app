"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, qk } from "@/lib/api";
import { doctorView } from "@/lib/doctor";
import styles from "./DoctorList.module.css";

/** design 04-01 의료진 소개: rounded cards (64 round photo, name 15/500, specialty with a badge icon, grey tag chips). */
export default function DoctorList({ hospitalId }: { hospitalId: number }) {
  const { data, isPending, isError } = useQuery({ queryKey: qk.doctors(hospitalId), queryFn: () => api.doctors(hospitalId) });
  const treat = useQuery({ queryKey: qk.treatments(hospitalId), queryFn: () => api.treatments(hospitalId) });
  const fallback = treat.data?.items.slice(0, 5).map((t) => t.name) ?? [];
  return (
    <section aria-labelledby="doctors-h">
      <h2 id="doctors-h" className={styles.h2}>의료진 소개</h2>
      {isPending && <div className={styles.skWrap} aria-busy="true"><div className={`skeleton ${styles.sk}`} /><div className={`skeleton ${styles.sk}`} /></div>}
      {isError && <p className={styles.muted}>의료진 정보를 불러오지 못했습니다.</p>}
      {data && data.items.length === 0 && <p className={styles.muted}>등록된 의료진이 없습니다.</p>}
      {data && data.items.length > 0 && (
        <ul className={styles.list}>
          {data.items.map((d) => {
            const photo = assetUrl(d.photoUrl);
            const { specialty: spec, tags } = doctorView(d, fallback);
            return (
              <li key={d.id}>
                <Link href={`/hospitals/${hospitalId}/doctors/${d.id}`} className={styles.card}>
                {photo ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={photo} alt="" className={styles.photo} loading="lazy" decoding="async" />
                ) : (
                  <div className={`${styles.photo} ${styles.photoEmpty}`} aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#9A9395" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
                  </div>
                )}
                <div className={styles.info}>
                  <div className={styles.name}>{d.name}</div>
                  {spec && (
                    <div className={styles.spec}>
                      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 3l2.5 2.2 3.3-.4.4 3.3L20.4 10.6 18.2 13l.4 3.3-3.3.4L12 19.2 9.5 16.7l-3.3-.4.4-3.3L4.4 10.6l2.2-2.5-.4-3.3 3.3.4z" /><path d="M9.5 11.2l1.7 1.7 3.4-3.6" /></svg>
                      {spec}
                    </div>
                  )}
                  <div className={styles.tags}>
                    {tags.map((t) => (
                      <span key={t} className={styles.tag}>{t}</span>
                    ))}
                  </div>
                </div>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

/** design 05-04 홈 탭 의료진 소개: compact cards (64 round photo + name), each opening 04-02. */
export function DoctorMiniList({ hospitalId }: { hospitalId: number }) {
  const { data } = useQuery({ queryKey: qk.doctors(hospitalId), queryFn: () => api.doctors(hospitalId) });
  if (!data || data.items.length === 0) return null;
  return (
    <section aria-labelledby="doctors-mini-h">
      <h2 id="doctors-mini-h" className={styles.h2}>의료진 소개</h2>
      <ul className={styles.list}>
        {data.items.slice(0, 6).map((d) => {
          const photo = assetUrl(d.photoUrl);
          return (
            <li key={d.id}>
              <Link href={`/hospitals/${hospitalId}/doctors/${d.id}`} className={`${styles.card} ${styles.cardMini}`}>
                {photo ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={photo} alt="" className={styles.photo} loading="lazy" decoding="async" />
                ) : (
                  <div className={`${styles.photo} ${styles.photoEmpty}`} aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#9A9395" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
                  </div>
                )}
                <span className={styles.name}>{d.name}</span>
              </Link>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
