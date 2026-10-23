"use client";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, qk } from "@/lib/api";
import styles from "./DoctorList.module.css";

// detail.php: doctor_treatments → hospital's first 5 treatments → default labels
const DEFAULT_TAGS = ["보톡스", "필러", "리프팅", "스킨부스터"];

export default function DoctorList({ hospitalId }: { hospitalId: number }) {
  const { data, isPending, isError } = useQuery({ queryKey: qk.doctors(hospitalId), queryFn: () => api.doctors(hospitalId) });
  const treat = useQuery({ queryKey: qk.treatments(hospitalId), queryFn: () => api.treatments(hospitalId) });
  const fallback = treat.data?.items.slice(0, 5).map((t) => t.name) ?? [];
  if (isPending) return <div className={`skeleton ${styles.sk}`} aria-busy="true" />;
  if (isError) return <p className={styles.muted}>의료진 정보를 불러오지 못했습니다.</p>;
  if (data.items.length === 0) return <p className={styles.muted}>등록된 의료진이 없습니다.</p>;
  return (
    <ul className={styles.list}>
      {data.items.map((d) => {
        const photo = assetUrl(d.photoUrl);
        return (
          <li key={d.id} className={styles.item}>
            {photo ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={photo} alt="" className={styles.photo} loading="lazy" />
            ) : (
              <div className={`${styles.photo} ${styles.photoEmpty}`} aria-hidden>👤</div>
            )}
            <div className={styles.info}>
              <div className={styles.name}>{d.name}{d.specialty && <span className={styles.spec}>{d.specialty}</span>}</div>
              {d.description && <p className={styles.desc}>{d.description}</p>}
              <div className={styles.tags}>
                {(d.treatments.length ? d.treatments : fallback.length ? fallback : DEFAULT_TAGS).map((t) => (
                  <span key={t} className={styles.tagChip}>{t}</span>
                ))}
              </div>
            </div>
          </li>
        );
      })}
    </ul>
  );
}
