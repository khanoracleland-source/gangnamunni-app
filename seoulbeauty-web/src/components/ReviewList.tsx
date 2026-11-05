"use client";
import { useEffect, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, qk } from "@/lib/api";
import starIcon from "@/assets/icons/star.png";
import styles from "./ReviewList.module.css";

/** 26.09.02 (design 04-04) */
function fmtDate(iso: string | null) {
  if (!iso) return "";
  const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[1].slice(2)}.${m[2]}.${m[3]}` : iso;
}
/** 0..5 integer; "★".repeat() throws on NaN or negative counts. */
const stars = (n: number) => Math.max(0, Math.min(5, Math.round(Number(n) || 0)));

/** Body text clamped to 3 lines; "…더보기" appears only when the clamp actually hides something (measured, so it is right at any width / font scale). */
function ReviewBody({ text, expanded, onToggle, date }: { text: string | null; expanded: boolean; onToggle: () => void; date: string }) {
  const ref = useRef<HTMLParagraphElement>(null);
  const [overflow, setOverflow] = useState(false);
  useEffect(() => {
    const el = ref.current; if (!el) { setOverflow(false); return; }
    const check = () => setOverflow(el.scrollHeight > el.clientHeight + 1);
    check();
    const ro = new ResizeObserver(check); ro.observe(el);
    return () => ro.disconnect();
  }, [text, expanded]);
  const showMore = !!text && (expanded || overflow);
  return (
    <>
      {text && <p ref={ref} className={`${styles.content} ${expanded ? "" : styles.clamp}`}>{text}</p>}
      <div className={styles.foot}>
        {showMore ? <button type="button" className={styles.more} onClick={onToggle} aria-expanded={expanded}>{expanded ? "접기" : "…더보기"}</button> : <span />}
        <span className={styles.date}>{date}</span>
      </div>
    </>
  );
}

/** design 04-04 시술후기 탭: rounded cards — avatar + name, treatment chip, 5 stars + score, title, 3-line body with 더보기, date, photo, 좋아요 · 댓글. */
export default function ReviewList({ hospitalId }: { hospitalId: number }) {
  const { data, isPending, isError } = useQuery({ queryKey: qk.reviews(hospitalId), queryFn: () => api.reviews(hospitalId) });
  const [open, setOpen] = useState<Set<number>>(() => new Set());
  const toggle = (id: number) => setOpen((s) => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n; });
  return (
    <section aria-labelledby="reviews-h">
      <h2 id="reviews-h" className={styles.h2}>시술후기</h2>
      {isPending && <div className={styles.skWrap} aria-busy="true"><div className={`skeleton ${styles.sk}`} /><div className={`skeleton ${styles.sk}`} /></div>}
      {isError && <p className={styles.muted}>후기를 불러오지 못했습니다.</p>}
      {data && data.items.length === 0 && <p className={styles.muted}>아직 후기가 없습니다.</p>}
      {data && data.items.length > 0 && (
        <ul className={styles.list}>
          {data.items.map((r) => {
            const avatar = assetUrl(r.userAvatar);
            const photo = r.images.length ? assetUrl(r.images[0]) : null;
            const n = stars(r.rating);
            const expanded = open.has(r.id);
            return (
              <li key={r.id} className={styles.card}>
                <div className={styles.head}>
                  <span className={styles.user}>
                    {avatar ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={avatar} alt="" className={styles.avatar} loading="lazy" />
                    ) : <span className={`${styles.avatar} ${styles.avatarEmpty}`} aria-hidden="true" />}
                    {r.userName}
                    {r.verified && <span className={styles.verified}>인증</span>}
                  </span>
                </div>
                <div className={styles.sub}>
                  {r.treatments.length > 0 && <span className={styles.chip}>{r.treatments[0]}</span>}
                  <span className={styles.stars} aria-label={`별점 ${r.rating}`}>
                    {[0, 1, 2, 3, 4].map((i) => (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img key={i} src={starIcon.src} alt="" width={15} height={15} className={i < n ? undefined : styles.starOff} />
                    ))}
                    <span className={styles.score}>{Number(r.rating || 0).toFixed(1)}</span>
                  </span>
                </div>
                {r.title && <div className={styles.title}>{r.title}</div>}
                <ReviewBody text={r.content} expanded={expanded} onToggle={() => toggle(r.id)} date={fmtDate(r.createdAt)} />
                {photo && (
                  <div className={styles.photoWrap}>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={photo} alt="" className={styles.photo} loading="lazy" decoding="async" />
                    {r.images.length > 1 && <span className={styles.photoCount}>+{r.images.length - 1}</span>}
                  </div>
                )}
                <div className={styles.stats}>
                  <span className={styles.stat}>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M20.8 8.6a5.3 5.3 0 0 0-8.8-2.9A5.3 5.3 0 0 0 3.2 8.6c0 5 8.8 10.6 8.8 10.6s8.8-5.6 8.8-10.6z" /></svg>
                    좋아요 {r.likeCount.toLocaleString("ko-KR")}
                  </span>
                  <span className={styles.stat}>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z" /></svg>
                    댓글 {r.commentCount.toLocaleString("ko-KR")}
                  </span>
                </div>
              </li>
            );
          })}
          {data.total > data.items.length && <li className={styles.muted}>후기 {data.total}개 중 {data.items.length}개 표시</li>}
        </ul>
      )}
    </section>
  );
}
