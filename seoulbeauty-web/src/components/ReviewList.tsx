"use client";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, qk } from "@/lib/api";
import styles from "./ReviewList.module.css";

function fmtDate(iso: string | null) {
  if (!iso) return "";
  const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[1]}.${m[2]}.${m[3]}` : iso;
}

/** 0..5 integer; "★".repeat() throws on NaN or negative counts. */
const stars = (n: number) => Math.max(0, Math.min(5, Math.round(Number(n) || 0)));

export default function ReviewList({ hospitalId }: { hospitalId: number }) {
  const { data, isPending, isError } = useQuery({
    queryKey: qk.reviews(hospitalId),
    queryFn: () => api.reviews(hospitalId),
  });
  if (isPending) return <div className={`skeleton ${styles.sk}`} aria-busy="true" />;
  if (isError) return <p className={styles.muted}>후기를 불러오지 못했습니다.</p>;
  if (data.items.length === 0) return <p className={styles.muted}>아직 후기가 없습니다.</p>;
  return (
    <ul className={styles.list}>
      {data.items.map((r) => (
        <li key={r.id} className={styles.item}>
          <div className={styles.head}>
            <span className={styles.user}>{r.userName}</span>
            <span className={styles.stars} aria-label={`별점 ${r.rating}`}>{"★".repeat(stars(r.rating))}{"☆".repeat(5 - stars(r.rating))}</span>
            <span className={styles.date}>{fmtDate(r.createdAt)}</span>
            {r.verified && <span className={styles.badge}>인증</span>}
          </div>
          {r.treatments.length > 0 && <div className={styles.treat}>{r.treatments.join(" · ")}</div>}
          {r.content && <p className={styles.content}>{r.content}</p>}
          {r.images.length > 0 && (
            <div className={styles.imgs}>
              {r.images.slice(0, 4).map((src, i) => (
                // eslint-disable-next-line @next/next/no-img-element
                <img key={i} src={assetUrl(src) || ""} alt="" loading="lazy" />
              ))}
            </div>
          )}
        </li>
      ))}
      {data.total > data.items.length && <li className={styles.muted}>후기 {data.total}개 중 {data.items.length}개 표시</li>}
    </ul>
  );
}
