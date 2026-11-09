import styles from "./WithdrawDone.module.css";

/** 07-09 회원 탈퇴 완료: shown after the withdrawal went through (from the settings sheet or the /mypage/withdraw form). */
export default function WithdrawDone() {
  return (
    <main className={styles.page}>
      <div className={styles.body}>
        <span className={styles.icon} aria-hidden="true">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
        </span>
        <h1 className={styles.title}>회원 탈퇴가 완료되었어요</h1>
        <p className={styles.desc}>그동안 서울미녀를 이용해주셔서 감사해요.<br />더 좋은 서비스로 다시 만나요.</p>
      </div>
      {/* a full load, like the redirect that brought the member here: the app must not restore the removed session (?app_logout=1) */}
      <div className={styles.bar}><a className={styles.btn} href="/next/?app_logout=1">처음으로</a></div>
    </main>
  );
}
