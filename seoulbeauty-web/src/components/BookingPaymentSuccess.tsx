"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, ApiError, qk } from "@/lib/api";
import LoginPrompt from "./LoginPrompt";
import styles from "./BookingPayment.module.css";

/** booking_payment_success.php: the state of the order after a payment or a "결제 없이 계속하기". */
export default function BookingPaymentSuccess({ id }: { id: number }) {
  const { data, error, refetch } = useQuery({ queryKey: qk.bookingPaymentSuccess(id), queryFn: () => api.bookingPaymentSuccess(id), staleTime: 0, gcTime: 0, retry: false });
  const loggedOut = error instanceof ApiError && error.status === 401;
  const missing = error instanceof ApiError && error.status === 404;
  return (
    <div className={`${styles.page} ${styles.pageDark}`}>
      <header className={styles.header}><Link className={styles.back} href="/mypage/bookings" aria-label="뒤로" /><h1 className={styles.title}>예약 확정</h1></header>
      <main className={styles.content}>
        {loggedOut ? <LoginPrompt text="로그인하면 결제 결과를 확인할 수 있습니다." /> : missing ? <div className={`${styles.alert} ${styles.alertErr}`}>예약을 찾을 수 없거나 접근 권한이 없습니다.</div>
          : !data ? <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div> : (
          <>
            <section className={styles.doneHero}>
              <span className={`${styles.doneIcon} ${data.paidFinal ? styles.doneIconOk : ""}`} aria-hidden="true">{data.paidFinal ? "✓" : "!"}</span>
              <h2 className={styles.doneTitle}>{data.title}</h2>
              <p className={styles.doneDesc}>{data.description}</p>
            </section>
            <section className={styles.section}>
              <h2 className={styles.sectionTitle}>예약 정보</h2>
              <div className={styles.infoRow}><div className={styles.label}>병원</div><div className={styles.value}>{data.hospitalName || "-"}</div></div>
              <div className={styles.infoRow}><div className={styles.label}>시술</div><div className={styles.value}>{data.treatment}</div></div>
              <div className={styles.infoRow}><div className={styles.label}>날짜/시간</div><div className={styles.value}>{data.dateText}</div></div>
              <div className={styles.infoRow}><div className={styles.label}>예약자</div><div className={styles.value}>{data.booker || "-"}</div></div>
              <div className={styles.infoRow}><div className={styles.label}>예약번호</div><div className={styles.value}>{data.bookingNo}</div></div>
            </section>
            <div className={styles.doneActions}>
              {!data.paidFinal && !data.preview && <Link className={styles.previewBtn} href={`/mypage/bookings/${id}/payment`}>결제 상태 확인</Link>}
              <Link className={`${styles.previewBtn} ${styles.previewPri}`} href="/mypage/bookings" replace>예약 내역 보기</Link>
            </div>
          </>
        )}
      </main>
    </div>
  );
}
