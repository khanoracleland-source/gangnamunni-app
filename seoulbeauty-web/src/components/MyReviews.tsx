"use client";
import { useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk, type MyDoneReview, type MyWritableReview } from "@/lib/api";
import LoginPrompt from "./LoginPrompt";
import MyHeader from "./MyHeader";
import styles from "./MyPages.module.css";

type Data = { progressEnabled: boolean; writable: MyWritableReview[]; done: MyDoneReview[] };

const Meta = ({ r }: { r: { district: string; address: string; doctorName: string | null; bookingDate: string } }) => (
  <>
    {r.address && <div className={styles.rmeta}>{r.district && <><span>{r.district}</span><span className={styles.hsep}>|</span></>}{r.address}</div>}
    <div className={styles.rmeta}>{r.doctorName && <><span>{r.doctorName}</span><span className={styles.hsep}>|</span></>}{r.bookingDate ? `시술일 ${r.bookingDate}` : ""}</div>
  </>
);

/** my_reviews.php: 작성가능 (completed bookings without a review) / 작성완료, with the delete sheet. */
export default function MyReviews({ initial, anon }: { initial?: Data; anon?: boolean }) {
  const qc = useQueryClient();
  const [confirm, setConfirm] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [toast, setToast] = useState("");
  const tab = useSearchParams().get("tab") === "done" ? "done" : "writable";
  const { data, error, refetch } = useQuery({ queryKey: qk.myReviews, queryFn: () => api.myReviews(), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  const remove = async () => {
    if (confirm === null || busy) return;
    setBusy(true);
    try {
      await api.deleteReview(confirm);
      setConfirm(null);
      void qc.invalidateQueries({ queryKey: qk.mypage });
      await refetch();
    } catch (err) {
      setToast(err instanceof ApiError && err.status === 404 ? "이미 삭제된 후기예요." : "삭제하지 못했습니다. 다시 시도해주세요.");
      setConfirm(null);
      window.setTimeout(() => setToast(""), 2500);
    } finally {
      setBusy(false);
    }
  };
  const thumb = (src: string | null) => (src
    // eslint-disable-next-line @next/next/no-img-element
    ? <img className={styles.rimg} src={assetUrl(src) ?? ""} alt="" />
    : <div className={styles.rimg} />);
  return (
    <div className={styles.wrap}>
      <MyHeader title="내가 작성한 후기" />
      {loggedOut ? <LoginPrompt /> : (
        <>
          <div className={styles.tabs}>
            <Link href="/mypage/reviews" replace className={`${styles.tab} ${tab === "writable" ? styles.tabOn : ""}`}>작성가능</Link>
            <Link href="/mypage/reviews?tab=done" replace className={`${styles.tab} ${tab === "done" ? styles.tabOn : ""}`}>작성완료</Link>
            {data?.progressEnabled && <Link href="/mypage/progress" className={styles.tab}>경과 후기</Link>}
          </div>
          {!data ? (
            <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
          ) : tab === "writable" ? (
            data.writable.length === 0 ? <div className={styles.empty}>작성 가능한 후기가 없습니다<br />시술 완료 후 후기를 작성할 수 있어요</div> : (
              <div className={styles.rlist}>
                {data.writable.map((w) => (
                  <div key={w.bookingId} className={styles.rcard}>
                    {thumb(w.imageUrl)}
                    <div className={styles.rcopy}>
                      <div className={styles.rhosp}>{w.hospitalName}</div>
                      <div className={styles.rtreat}>{w.treatment}</div>
                      <Meta r={w} />
                      <div className={styles.ractions}><Link className={`${styles.rbtn} ${styles.rbtnPri}`} href={`/mypage/reviews/write?hospital_id=${w.hospitalId}&booking_id=${w.bookingId}`}>후기를 작성해볼까요?</Link></div>
                    </div>
                  </div>
                ))}
              </div>
            )
          ) : data.done.length === 0 ? (
            <div className={styles.empty}>작성한 후기가 없습니다<br /><Link className={styles.retry} href="/mypage/reviews/write">후기 작성하기</Link></div>
          ) : (
            <div className={styles.rlist}>
              {data.done.map((r) => (
                <article key={r.id} className={styles.rcard}>
                  {thumb(r.imageUrl)}
                  <div className={styles.rcopy}>
                    <div className={styles.rhosp}>{r.hospitalName} <span aria-label={`평점 ${r.rating}점`}><span className={styles.stars}>{"★".repeat(r.rating)}{"☆".repeat(5 - r.rating)}</span>{r.ratingValue.toFixed(1)}</span></div>
                    <div className={styles.rtreat}>{r.treatment}</div>
                    <Meta r={r} />
                    <div className={styles.ractions}>
                      {r.pending ? <span className={`${styles.rbtn} ${styles.rbtnPending}`}>승인 대기중</span> : (
                        <>
                          <Link className={`${styles.rbtn} ${styles.rbtnPri}`} href={`/reviews/${r.id}`}>보기</Link>
                          <button type="button" className={styles.rbtn} onClick={() => setConfirm(r.id)}>삭제</button>
                        </>
                      )}
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}
        </>
      )}
      {toast && <div className={styles.ntToast} role="status">{toast}</div>}
      {confirm !== null && (
        <div className={styles.pfModal} role="presentation" onClick={() => !busy && setConfirm(null)}>
          <div className={styles.pfModalBox} role="dialog" aria-modal="true" aria-labelledby="rv-del" onClick={(e) => e.stopPropagation()}>
            <h2 id="rv-del">후기를 삭제할까요?</h2>
            <p>삭제한 후기는 다시 복구할 수 없어요.</p>
            <button type="button" className={styles.pfDiscard} disabled={busy} onClick={() => setConfirm(null)}>취소</button>
            <button type="button" className={styles.pfDanger} disabled={busy} onClick={remove}>{busy ? "삭제 중…" : "삭제할게요"}</button>
          </div>
        </div>
      )}
    </div>
  );
}
