"use client";
import { useRouter } from "next/navigation";
import styles from "./MyPages.module.css";

/** Sub-page header: back goes to 마이페이지 (the PHP pages' back button does `location.href = mypage.php`). */
export default function MyHeader({ title }: { title: string }) {
  const router = useRouter();
  return (
    <div className={styles.head}>
      <button type="button" className={styles.back} onClick={() => router.push("/mypage")} aria-label="뒤로 가기">←</button>
      <span className={styles.headTitle} style={{ fontSize: 18, fontWeight: 500 }}>{title}</span>
    </div>
  );
}
