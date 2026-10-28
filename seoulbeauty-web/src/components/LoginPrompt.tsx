"use client";
import { useLoginUrl } from "@/lib/me";
import styles from "./MyPages.module.css";

/** mypage.php for a visitor: ask to log in (the login page returns here). */
export default function LoginPrompt({ text = "로그인 후 마이페이지를 이용하실 수 있습니다." }: { text?: string }) {
  const loginUrl = useLoginUrl();
  return (
    <div className={styles.login}>
      <p className={styles.loginTitle}>로그인이 필요합니다</p>
      <p className={styles.loginText}>{text}</p>
      <a href={loginUrl()} className={styles.loginBtn}>로그인</a>
    </div>
  );
}
