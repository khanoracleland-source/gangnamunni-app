"use client";
import { useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk, type BookingBase, type MyBookingsData } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import LoginPrompt from "./LoginPrompt";
import MyHeader from "./MyHeader";
import styles from "./MyPages.module.css";

type Tab = "active" | "completed" | "cancelled";
const TABS: { key: Tab; label: string; href: string }[] = [
  { key: "active", label: "시술예정", href: "/mypage/bookings" },
  { key: "completed", label: "시술완료", href: "/mypage/bookings?tab=completed" },
  { key: "cancelled", label: "예약취소", href: "/mypage/bookings?tab=cancelled" },
];

function Summary({ b }: { b: BookingBase }) {
  return (
    <div className={styles.bkSum}>
      <div className={styles.bkThumb}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        {b.imageUrl ? <img src={assetUrl(b.imageUrl) ?? ""} alt="" /> : <>SEOUL<br />BEAUTY</>}
      </div>
      <div className={styles.bkCopy}>
        <div className={styles.bkTreat}>{b.treatment || "-"}</div>
        <div className={styles.bkHosp}>{b.hospitalName}</div>
        <div className={styles.bkPrice}>{b.price.toLocaleString("ko-KR")}원</div>
      </div>
    </div>
  );
}

function Progress({ steps, active, title }: { steps: string[]; active: number; title: string }) {
  return (
    <div className={styles.bkProg} aria-label={title}>
      {steps.map((label, i) => (
        <div key={i} className={`${styles.bkStep} ${i + 1 === active ? styles.bkStepOn : i + 1 < active ? styles.bkStepDone : ""}`}>{label}</div>
      ))}
    </div>
  );
}

/** my_bookings.php: 시술예정 / 시술완료 / 예약취소. Paying, changing and cancelling stay in PHP (links). */
export default function MyBookings({ initial, anon }: { initial?: MyBookingsData; anon?: boolean }) {
  const php = usePhpBase();
  const sp = useSearchParams();
  const raw = sp.get("tab");
  // my_bookings.php?action=cancel&booking_id=N (push / PHP links): open the reason sheet for that booking right away
  const askedCancel = sp.get("action") === "cancel" ? Number(sp.get("booking_id")) : 0;
  const tab: Tab = raw === "completed" || raw === "cancelled" ? raw : "active";
  const { data, error, refetch } = useQuery({ queryKey: qk.myBookings, queryFn: () => api.myBookings(), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  const router = useRouter();
  const [changing, setChanging] = useState<{ id: number; left: number } | null>(null);
  const [cancelling, setCancelling] = useState<number | null>(Number.isInteger(askedCancel) && askedCancel > 0 ? askedCancel : null);
  const rebook = (b: BookingBase) => `/booking?id=${b.hospitalId}&rebook=1&source_booking_id=${b.id}&treatment=${encodeURIComponent(b.treatment)}`;
  const total = data ? data.upcoming.length + data.completed.length + data.cancelled.length : 0;

  return (
    <div className={styles.wrap}>
      <MyHeader title="내 예약/결제 내역" />
      {loggedOut ? <LoginPrompt text="로그인하면 예약 내역을 자동으로 조회합니다." /> : !data ? (
        <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : total === 0 ? (
        <div className={styles.bkEmpty}>예약 내역이 없습니다.<br /><Link className={styles.retry} href="/hospitals">병원 찾아보기</Link></div>
      ) : (
        <>
          <div className={styles.bkTabs} role="tablist">
            {TABS.map((t) => (
              <Link key={t.key} href={t.href} replace role="tab" aria-selected={tab === t.key} className={`${styles.bkTab} ${tab === t.key ? styles.bkTabOn : ""}`}>
                {t.label}{t.key === "active" && data.upcoming.length > 0 && <span className={styles.bkCount}>{data.upcoming.length}</span>}
              </Link>
            ))}
          </div>
          <div className={styles.bkList}>
            {tab === "active" && (data.upcoming.length === 0 ? <div className={styles.bkEmpty}>진행중인 예약이 없습니다.</div> : data.upcoming.map((b) => (
              <article key={b.id} className={styles.bkCard}>
                <div className={styles.bkTop}>
                  <span className={styles.bkChip}>{b.chip}</span>
                  <Link className={styles.bkLink} href={`/mypage/bookings/${b.id}`}>예약상세</Link>
                </div>
                <div className={styles.bkDate}>{b.dateText}</div>
                <Summary b={b} />
                <Progress steps={b.steps} active={b.activeStep} title="예약 진행 상태" />
                <div className={styles.bkActions}>
                  {b.action === "pay" && <a className={`${styles.bkBtn} ${styles.bkBtnPri}`} href={phpUrl(php, `booking_payment.php?booking_id=${b.id}`)}>결제하기</a>}
                  {b.action === "change" && (b.editsLeft > 0
                    ? <button type="button" className={`${styles.bkBtn} ${styles.bkBtnPri}`} onClick={() => setChanging({ id: b.id, left: b.editsLeft })}>예약변경</button>
                    : <span className={`${styles.bkBtn} ${styles.bkBtnPri} ${styles.bkBtnOff}`} aria-disabled="true">예약변경</span>)}
                  <button type="button" className={styles.bkBtn} onClick={() => setCancelling(b.id)}>예약취소</button>
                </div>
              </article>
            )))}
            {tab === "completed" && (data.completed.length === 0 ? <div className={styles.bkEmpty}>해당 항목이 없습니다.</div> : data.completed.map((b) => (
              <article key={b.id} className={styles.bkCard}>
                <div className={styles.bkTop}>
                  <span className={`${styles.bkChip} ${styles.bkChipDone}`}>{b.chip}</span>
                  {b.isDone && <Link className={styles.bkLink} href={`/mypage/bookings/${b.id}`}>예약상세</Link>}
                </div>
                <div className={styles.bkDate}>{b.dateText}</div>
                <Summary b={b} />
                <div className={styles.bkActions}>
                  <a className={`${styles.bkBtn} ${styles.bkBtnPri}`} href={rebook(b)}>다시예약</a>
                  {b.review?.state === "none" && <Link className={styles.bkBtn} href={`/mypage/reviews/write?booking_id=${b.id}`}>후기작성</Link>}
                  {b.review?.state === "published" && b.review.reviewId && <Link className={styles.bkBtn} href={`/reviews/${b.review.reviewId}`}>{b.review.label}</Link>}
                  {b.review?.state === "rejected" && b.review.reviewId && <Link className={styles.bkBtn} href={`/mypage/reviews/write?id=${b.review.reviewId}`}>후기 다시 작성</Link>}
                  {b.review?.state === "pending" && <span className={`${styles.bkBtn} ${styles.bkBtnOff}`} aria-disabled="true">{b.review.label}</span>}
                </div>
              </article>
            )))}
            {tab === "cancelled" && (data.cancelled.length === 0 ? <div className={styles.bkEmpty}>해당 항목이 없습니다.</div> : data.cancelled.map((b) => (
              <article key={b.id} className={styles.bkCard}>
                <div className={styles.bkTop}>
                  <span className={`${styles.bkChip} ${b.refundComplete ? styles.bkChipDone : styles.bkChipCancel}`}>{b.chip}</span>
                  <Link className={styles.bkLink} href={`/mypage/bookings/${b.id}`}>예약상세</Link>
                </div>
                <div className={styles.bkDate}>{b.dateText}</div>
                <Summary b={b} />
                <Progress steps={b.steps} active={b.activeStep} title="예약 취소 진행 상태" />
                <div className={styles.bkActions}><a className={`${styles.bkBtn} ${styles.bkBtnPri}`} href={rebook(b)}>다시예약</a></div>
              </article>
            )))}
            {data.showPushHint && (tab === "active" ? data.upcoming.length > 0 : tab === "completed" && data.completed.length > 0) && (
              <a className={styles.bkHint} href={phpUrl(php, "notification_settings.php")}>
                <span aria-hidden="true">🔔</span>
                <span>기기 알림을 켜면 예약현황을 푸시로도 알려드려요<small>마이페이지 &gt; 설정 &gt; 알림 설정에서 예약 알림·푸시 알림을 켜주세요</small></span>
              </a>
            )}
          </div>
          {changing && <ChangeConfirmSheet left={changing.left} onClose={() => setChanging(null)} onGo={() => router.push(`/booking?change=${changing.id}`)} />}
          {cancelling && <CancelReasonSheet onClose={() => setCancelling(null)} onGo={(reason) => router.push(`/mypage/bookings/${cancelling}/cancel?reason=${encodeURIComponent(reason)}`)} />}
          {data.expiredNotice && <ExpiredSheet n={data.expiredNotice} rebookHref={`/booking?id=${data.expiredNotice.hospitalId}&rebook=1&source_booking_id=${data.expiredNotice.bookingId}&treatment=${encodeURIComponent(data.expiredNotice.treatment)}`} />}
        </>
      )}
    </div>
  );
}

export const CANCEL_REASONS = ["일정 변경", "개인 사정", "다른 병원 예약", "금액 / 시술 변경", "기타"];

/** my_bookings.php cancelModal: the reason, then the cancel request page. */
function CancelReasonSheet({ onClose, onGo }: { onClose: () => void; onGo: (reason: string) => void }) {
  const [choice, setChoice] = useState(CANCEL_REASONS[0]);
  const [custom, setCustom] = useState("");
  return (
    <div className={styles.bkDim} role="dialog" aria-modal="true" aria-labelledby="bkCancelTitle" onClick={onClose}>
      <div className={`${styles.bkSheet} ${styles.bkSheetLeft}`} onClick={(e) => e.stopPropagation()}>
        <div className={styles.bkHandle} aria-hidden="true" />
        <div className={styles.bkSheetTitle} id="bkCancelTitle">예약을 취소하시는 이유가 있으신가요?</div>
        <div className={styles.bkSheetDesc}>보다 나은 서비스를 위해 의견을 들려주세요</div>
        <div className={styles.bkReasons}>
          {CANCEL_REASONS.map((r) => (
            <label key={r} className={styles.bkReason}><input type="radio" name="cancel_reason" checked={choice === r} onChange={() => setChoice(r)} /><span>{r}</span></label>
          ))}
        </div>
        <input className={styles.bkReasonInput} type="text" maxLength={255} placeholder="직접 입력할게요 (선택)" value={custom} onChange={(e) => setCustom(e.target.value)} />
        <button type="button" className={`${styles.bkBtn} ${styles.bkBtnPri} ${styles.bkBtnWide}`} onClick={() => onGo(custom.trim() ? `${choice}: ${custom.trim()}` : choice)}>예약 취소 요청</button>
      </div>
    </div>
  );
}

/** my_bookings.php changeConfirmModal. */
function ChangeConfirmSheet({ left, onClose, onGo }: { left: number; onClose: () => void; onGo: () => void }) {
  return (
    <div className={styles.bkDim} role="dialog" aria-modal="true" aria-labelledby="bkChangeTitle" onClick={onClose}>
      <div className={styles.bkSheet} onClick={(e) => e.stopPropagation()}>
        <div className={styles.bkSheetIcon} aria-hidden="true">!</div>
        <div className={styles.bkSheetTitle} id="bkChangeTitle">병원의 예약 현황에 따라<br />일정 변경이 불가할 수도 있어요</div>
        <div className={styles.bkSheetDesc}>변경하시겠어요? (변경 가능 {left}회 남음)</div>
        <div className={styles.bkSheetActions}>
          <button type="button" className={`${styles.bkBtn} ${styles.bkBtnPri}`} onClick={onClose}>취소</button>
          <button type="button" className={styles.bkBtn} onClick={onGo}>변경</button>
        </div>
      </div>
    </div>
  );
}

/** Auto-cancel (payment deadline) notice; closing or re-booking marks it as seen. */
function ExpiredSheet({ n, rebookHref }: { n: NonNullable<MyBookingsData["expiredNotice"]>; rebookHref: string }) {
  const qc = useQueryClient();
  const [open, setOpen] = useState(true);
  const seen = useMutation({
    mutationFn: () => api.bookingExpireSeen(n.bookingId),
    onSettled: () => { void qc.invalidateQueries({ queryKey: qk.myBookings }); },
  });
  const close = () => { setOpen(false); seen.mutate(); };
  if (!open) return null;
  return (
    <div className={styles.bkDim} role="dialog" aria-modal="true" aria-labelledby="bkExpTitle" onClick={close}>
      <div className={styles.bkSheet} onClick={(e) => e.stopPropagation()}>
        <div className={styles.bkSheetIcon} aria-hidden="true">!</div>
        <div className={styles.bkSheetTitle} id="bkExpTitle">결제 기한이 지나<br />예약이 취소되었어요</div>
        <div className={styles.bkSheetDesc}>병원 확인 후 {n.deadlineHours}시간 안에 결제하지 않으면<br />예약이 자동으로 취소돼요. 다시 예약해주세요.</div>
        <div className={styles.bkSheetActions}>
          <button type="button" className={styles.bkBtn} onClick={close}>확인했어요</button>
          <a className={`${styles.bkBtn} ${styles.bkBtnPri}`} href={rebookHref} onClick={() => seen.mutate()}>다시 예약하기</a>
        </div>
      </div>
    </div>
  );
}
