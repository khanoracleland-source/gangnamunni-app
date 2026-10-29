"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api, ApiError, qk, type BookingCancelDetail, type BookingDetail as Detail, type BookingRequestDetail } from "@/lib/api";
import LoginPrompt from "./LoginPrompt";
import SupportHeader from "./SupportHeader";
import support from "./Support.module.css";
import styles from "./MyPages.module.css";

const won = (n: number) => n.toLocaleString("ko-KR");
const Row = ({ label, children, total }: { label: string; children: React.ReactNode; total?: boolean }) => (
  <div className={styles.bdRow}><span className={styles.bdLabel}>{label}</span><span className={`${styles.bdValue} ${total ? styles.bdTotal : ""}`}>{children}</span></div>
);

/** booking_request_detail.php (active / completed) and booking_cancel_detail.php (cancelled): read-only. */
export default function BookingDetail({ id, initial, anon }: { id: number; initial?: Detail; anon?: boolean }) {
  const { data, error, refetch } = useQuery({ queryKey: qk.bookingDetail(id), queryFn: () => api.bookingDetail(id), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  const missing = error instanceof ApiError && error.status === 404;
  // booking_request_detail.php?changed=1 (05-43): the "변경 요청 접수" sheet once, then the parameter is dropped from the URL
  const changedParam = useSearchParams().get("changed") === "1";
  const [changed, setChanged] = useState(changedParam);
  useEffect(() => { if (changedParam) { try { window.history.replaceState(null, "", window.location.pathname); } catch { /* ignore */ } } }, [changedParam]);
  const title = !data ? "예약 상세" : data.kind === "cancelled" ? "취소·환불 내역을 확인해보세요" : data.isActive ? "예약 내용을 확인해보세요" : data.isCompleted ? "시술완료 내역" : "예약 상세";
  return (
    <div className={support.wrap}>
      <SupportHeader title={title} fallback={data?.kind === "cancelled" ? "/mypage/bookings?tab=cancelled" : "/mypage/bookings"} />
      {loggedOut ? <LoginPrompt text="로그인 후 예약 상세를 확인하실 수 있습니다." /> : missing ? (
        <div className={support.empty}>예약을 찾을 수 없어요</div>
      ) : !data ? (
        <div className={support.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={support.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : data.kind === "cancelled" ? <Cancelled d={data} /> : <Request d={data} />}
      {changed && data && data.kind === "request" && data.isActive && (
        <div className={styles.bkDim} role="dialog" aria-modal="true" aria-labelledby="bdChgTitle" onClick={() => setChanged(false)}>
          <div className={styles.bkSheet} onClick={(e) => e.stopPropagation()}>
            <div className={styles.bkSheetIcon} aria-hidden="true">✓</div>
            <div className={styles.bkSheetTitle} id="bdChgTitle">예약 변경 요청이 접수되었어요!</div>
            <div className={styles.bkSheetDesc}>원하시는 일정으로<br />병원에 예약 가능 여부를 확인하고 있어요</div>
            <div className={styles.bkSheetDesc}><b>예상 확인 시간</b><br />평균 1~3시간 이내<br />(병원 상황에 따라 달라질 수 있어요)</div>
            <div className={styles.bkSheetDesc}><b>알림을 꼭 확인해주세요!</b><br />예약 진행 상황은 알림(푸시)으로 안내해드려요<br />알림을 허용해두시면 빠르게 확인하실 수 있어요</div>
            <div className={styles.bkSheetActions}>
              <Link className={`${styles.bkBtn} ${styles.bkBtnPri}`} href="/mypage/bookings" replace>확인했어요</Link>
              <Link className={styles.bkBtn} href="/mypage/bookings" replace>내 예약 현황</Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function Request({ d }: { d: BookingRequestDetail }) {
  return (
    <>
      <div className={`${styles.bdBadge} ${d.isCompleted ? styles.bdBadgeDone : ""}`}>
        <span>{d.isActive ? "시술예정" : d.statusLabel}</span>{d.dday && <span>{d.dday}</span>}
      </div>
      <div className={styles.bdRows} style={{ marginTop: 8 }}>
        <Row label="예약번호">{d.id}</Row>
        <Row label="날짜/시간">{d.dateText}</Row>
        <Row label="선택시술">{d.treatment}</Row>
        <Row label="병원">{d.hospitalName}</Row>
        {!d.isActive && <Row label="담당의사">{d.doctorName}</Row>}
      </div>
      <div className={styles.bdPerson}><span className={styles.bdLabel}>예약자</span><span>{d.bookerName}</span><span className={styles.bdLabel}>연락처</span><span>{d.phone}</span></div>

      {d.notifications.length > 0 && (
        <section className={styles.bdAudit} aria-labelledby="bdAuditTitle">
          <h2 className={styles.bdSec} style={{ margin: 0 }} id="bdAuditTitle">알림 이력</h2>
          {d.notifications.map((n, i) => (
            <article key={i} className={styles.bdAuditItem}>
              <div className={styles.bdAuditMeta}><span>{n.at}</span>{n.event && <span>{n.event}</span>}</div>
              <div className={styles.bdAuditName}>{n.title}</div>
              {n.body && <div className={styles.bdAuditBody}>{n.body}</div>}
              <div>
                <span className={styles.bdTag}>인앱 {n.inApp}</span>
                {n.pushSent > 0 && <span className={styles.bdTag}>푸시 성공 {n.pushSent}</span>}
                {n.pushFailed > 0 && <span className={`${styles.bdTag} ${styles.bdTagFail}`}>푸시 실패 {n.pushFailed}</span>}
                {n.pushSkipped && <span className={styles.bdTag}>푸시 제외</span>}
              </div>
            </article>
          ))}
        </section>
      )}

      {d.summary && (
        <section aria-labelledby="bdPayTitle">
          <h2 className={styles.bdSec} id="bdPayTitle">결제 금액을 확인해보세요</h2>
          <div className={styles.bdRows}>
            <Row label="시술 금액">{won(d.summary.gross)}원</Row>
            <Row label="· 할인"><span className={styles.bdMuted}>0% -0원</span></Row>
            <Row label="· 포인트 사용 금액"><span className={styles.bdMuted}>-{won(d.summary.points)}원</span></Row>
            <Row label="총 결제 금액 (VAT포함)" total>{won(d.summary.final)}원</Row>
            <Row label="예상 적립 포인트">{d.summary.expectedPoints === null ? "준비 중" : `${won(d.summary.expectedPoints)} P`}</Row>
          </div>
        </section>
      )}

      {d.isActive ? (
        <>
          <div className={styles.bdNotice}>
            <b>고객님 꼭 알아두세요!</b>
            <ul>
              <li>예약 가능 여부는 병원 상황에 따라 달라질 수 있어요</li>
              <li>예약이 불가능한 경우, 상담원이 다른 일정으로 안내해드려요</li>
              <li>예약 확정 전까지는 결제가 진행되지 않아요</li>
              <li>결제는 예약이 가능하다고 확인된 후에 진행됩니다</li>
              <li>예약 현황 / 예약 변경 / 취소는 <em>내 예약 결제내역</em>에서 직접 관리 할 수 있어요</li>
            </ul>
          </div>
          <div className={styles.bdFoot}><Link className={`${support.btn} ${support.btnPri}`} href="/mypage/bookings">확인했어요</Link></div>
        </>
      ) : (
        <>
          <section aria-labelledby="bdInfoTitle">
            <h2 className={styles.bdSec} id="bdInfoTitle">결제정보</h2>
            <div className={styles.bdRows}><Row label="시술가격">{won(d.treatmentPrice)}</Row><Row label="총 결제금액" total>{won(d.totalPayment)}</Row></div>
          </section>
          {d.isCompleted && (
            <div className={styles.bdFoot}>
              <Link className={`${support.btn} ${support.btnPri}`} href={`/booking?id=${d.hospitalId}&rebook=1&source_booking_id=${d.id}&treatment=${encodeURIComponent(d.treatment === "-" ? "" : d.treatment)}`}>다시예약</Link>
              {d.review?.state === "none" && <Link className={support.btn} href={`/mypage/reviews/write?booking_id=${d.id}`}>후기작성하기</Link>}
              {d.review?.state === "rejected" && d.review.reviewId && <Link className={support.btn} href={`/mypage/reviews/write?id=${d.review.reviewId}`}>후기 다시 작성</Link>}
              {d.review?.state === "published" && d.review.reviewId && <Link className={support.btn} href={`/reviews/${d.review.reviewId}`}>후기보기</Link>}
              {d.review?.state === "pending" && <span className={support.btn} aria-disabled="true" style={{ opacity: 0.6 }}>{d.review.label}</span>}
            </div>
          )}
        </>
      )}
    </>
  );
}

function Cancelled({ d }: { d: BookingCancelDetail }) {
  return (
    <>
      <div className={styles.bdBadge}>{d.badge}</div>
      <div className={styles.bdRows} style={{ marginTop: 8 }}>
        <Row label="예약번호">{d.id}</Row>
        <Row label="날짜/시간">{d.dateText}</Row>
        <Row label="선택시술">{d.treatment}</Row>
        {d.reason && <Row label="취소사유">{d.reason}</Row>}
        <Row label="병원">{d.hospitalName}</Row>
      </div>
      <div className={styles.bdPerson}><span className={styles.bdLabel}>예약자</span><span>{d.bookerName}</span><span className={styles.bdLabel}>연락처</span><span>{d.phone}</span></div>
      {d.paid && (
        <>
          <h2 className={styles.bdSec}>결제 금액을 확인해보세요</h2>
          <div className={styles.bdRows}><Row label="시술가격">{won(d.price)}</Row><Row label="총 결제금액" total>{won(d.paid.total)}</Row></div>
          <h2 className={styles.bdSec}>취소/환불 정보를 확인해보세요</h2>
          <div className={styles.bdRows}>
            {d.paid.cancelledAt && <Row label="취소날짜/시간">{d.paid.cancelledAt}</Row>}
            <Row label="취소 수수료">0</Row>
            <Row label="총 금액(취소/환불)" total>{d.paid.refunded !== null ? won(d.paid.refunded) : "환불 확인 중"}</Row>
          </div>
          <ul className={styles.bdNotes}>
            <li>결제된 예약은 취소 후 관리자 확인을 거쳐 환불 처리됩니다.</li>
            <li>환불이 완료되면 이 화면과 결제 내역에 환불 금액이 반영됩니다.</li>
            <li>정확한 환불기간은 해당 카드사로 문의해 주시기 바랍니다.</li>
          </ul>
          <div className={styles.bdNotice}>
            <b>고객님 꼭 알아두세요!</b>
            <ul>
              <li>예약이 확정된 후 취소 시, 병원 정책에 따라 위약금이 발생할 수 있어요</li>
              <li>포인트 할인 / 이벤트가 적용된 예약은 취소 시 할인 금액이 환수될 수 있어요</li>
              <li>당일 예약 또는 예약 시간 임박 시 취소가 제한될 수 있어요</li>
              <li>예약 취소 후 재예약을 원하시면 다시 시술과 시간을 선택해 주세요</li>
            </ul>
          </div>
        </>
      )}
      <div className={styles.bdFoot}><Link className={`${support.btn} ${support.btnPri}`} href="/mypage/bookings?tab=cancelled">확인했어요</Link></div>
    </>
  );
}
