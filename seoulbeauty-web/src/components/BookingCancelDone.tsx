"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import styles from "./Booking.module.css";

/** booking_cancel_complete.php: the request went through; the cancellation itself is confirmed later. */
export default function BookingCancelDone() {
  const router = useRouter();
  return (
    <div className={styles.wrap}>
      <div className={styles.head}>
        <button type="button" className={styles.back} aria-label="뒤로" onClick={() => router.replace("/mypage/bookings?tab=cancelled")}>‹</button>
        <span className={styles.title}>취소 완료</span>
      </div>
      <div className={styles.doneBody}>
        <span className={styles.sheetIcon} aria-hidden="true">✓</span>
        <h1 className={styles.doneTitle}>예약 취소 요청이 접수되었어요!</h1>
        <p className={styles.sheetDesc}>취소가 완료되면 푸시로 알려드릴게요</p>
        <div className={styles.waitBox}>
          <div className={styles.waitTitle}>예상 확인 시간</div>
          <div className={styles.waitTime}>평균 1~3시간 이내<br />(병원 상황에 따라 달라질 수 있어요)</div>
          <div className={styles.alertBox}>
            <div className={styles.alertTitle}>알림을 꼭 확인해주세요!</div>
            <div className={styles.alertCopy}>예약 진행 상황은 알림(푸시)으로 안내해드려요<br />알림을 허용해두시면 빠르게 확인하실 수 있어요</div>
          </div>
        </div>
        <Link href="/mypage/bookings?tab=cancelled" replace className={`${styles.act} ${styles.actPri}`}>예약 내역 보기</Link>
      </div>
    </div>
  );
}
