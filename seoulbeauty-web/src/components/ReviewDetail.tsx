"use client";
import { useState } from "react";
import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk, type ReviewDetail as Detail } from "@/lib/api";
import { useRequireLogin } from "@/lib/me";
import SupportHeader from "./SupportHeader";
import styles from "./Support.module.css";

const ICON = (name: string) => assetUrl(`assets/icons/${name}.png?v=1`) ?? "";
const num = (n: number) => n.toLocaleString("ko-KR");

/** review_detail.php: one review (text, photos, like), the hospital card and — for the author — the edit link (still PHP). */
export default function ReviewDetail({ id, initial }: { id: number; initial?: Detail }) {
  const qc = useQueryClient();
  const requireLogin = useRequireLogin();
  const { data, error, refetch } = useQuery({ queryKey: qk.reviewDetail(id), queryFn: () => api.reviewDetail(id), initialData: initial, staleTime: 15_000, retry: false });
  const [zoom, setZoom] = useState<string | null>(null);
  const [toast, setToast] = useState("");
  const like = useMutation({
    mutationFn: () => api.toggleReviewLike(id),
    onSuccess: (r) => {
      qc.setQueryData<Detail>(qk.reviewDetail(id), (p) => (p ? { ...p, liked: r.liked, likeCount: r.count } : p));
      void qc.invalidateQueries({ queryKey: ["community"] });
    },
  });
  const onLike = () => {
    if (like.isPending) return;
    void requireLogin(() => like.mutateAsync()).catch(() => { setToast("좋아요 처리 중 오류가 발생했습니다."); setTimeout(() => setToast(""), 2500); });
  };
  const missing = error instanceof ApiError && (error.status === 404 || error.status === 400);
  return (
    <div className={styles.wrap}>
      <SupportHeader title="내가 작성한 후기 보기" fallback="/community" />
      {missing ? (
        <div className={styles.empty}>후기를 찾을 수 없습니다.</div>
      ) : !data ? (
        <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : (
        <>
          <div className={styles.rcard}>
            <div>{data.treatments.map((t, i) => <span key={i} className={styles.tag}>{t}</span>)}</div>
            {data.rating > 0 && (
              <div className={styles.rating} aria-label={`평점 ${data.rating}점`}>
                <span className={styles.stars} aria-hidden="true">
                  {[1, 2, 3, 4, 5].map((s) => <span key={s} className={s > data.rating ? styles.starOff : ""}>★</span>)}
                </span>
                <span className={styles.score}>{data.rating.toFixed(1)}</span>
              </div>
            )}
            {data.title && <div className={styles.rtitle}>{data.title}</div>}
            <div className={styles.rbody}>{data.content}</div>
            {data.images.length > 0 && (
              <div className={`${styles.thumbs} ${data.images.length === 1 ? styles.thumbs1 : data.images.length === 2 ? styles.thumbs2 : styles.thumbs3}`}>
                {data.images.map((im, i) => (
                  <button key={i} type="button" className={styles.thumb} onClick={() => setZoom(assetUrl(im.full || im.src))} aria-label="후기 이미지 확대">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={assetUrl(im.src) ?? ""} alt="후기 이미지" loading="lazy" />
                  </button>
                ))}
              </div>
            )}
            <div className={styles.meta}>
              <span className={styles.avatar}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                {data.author.avatar && <img src={assetUrl(data.author.avatar) ?? ""} alt="" />}
              </span>
              <span className={styles.author}>{data.author.name}</span>
              <span className={styles.stats}>
                <button type="button" className={`${styles.stat} ${data.liked ? styles.liked : ""}`} aria-pressed={data.liked} aria-label="좋아요" onClick={onLike}>
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={ICON(data.liked ? "icon-community-heart-on" : "icon-community-heart")} alt="" />{num(data.likeCount)}
                </button>
                <span className={styles.stat}>💬 {num(data.commentCount)}</span>
                <span className={styles.stat}>👁 {num(data.viewCount)}</span>
              </span>
            </div>
          </div>

          {data.hospital && (
            <Link href={`/hospitals/${data.hospital.id}`} className={styles.hosp}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              {data.hospital.imageUrl ? <img className={styles.hthumb} src={assetUrl(data.hospital.imageUrl) ?? ""} alt="" /> : <span className={styles.hthumb} />}
              <span className={styles.hinfo}>
                <span className={styles.hname}>{data.hospital.name}</span>
                <span className={styles.haddr}>{data.hospital.address}</span>
                {data.hospital.rating !== null && (
                  <span className={styles.hrate}>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={assetUrl("assets/icons/categories/rating_2.svg") ?? ""} alt="" />{data.hospital.rating.toFixed(1)} ({num(data.hospital.reviewCount)})
                  </span>
                )}
              </span>
            </Link>
          )}

          {data.isMine && (
            <Link className={styles.edit} href={`/mypage/reviews/write?id=${data.id}`}>수정하기</Link>
          )}
        </>
      )}
      {zoom && (
        <div className={styles.modal} role="dialog" aria-modal="true" onClick={() => setZoom(null)}>
          <button type="button" className={styles.modalClose} aria-label="닫기" onClick={(e) => { e.stopPropagation(); setZoom(null); }}>×</button>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={zoom} alt="" />
        </div>
      )}
      {toast && <div className={styles.toast} role="status">{toast}</div>}
    </div>
  );
}
