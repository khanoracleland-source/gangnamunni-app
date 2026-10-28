"use client";
import { useRouter } from "next/navigation";
import styles from "./Support.module.css";

/** Page header with a back button: history back when there is one, else `fallback`. */
export default function SupportHeader({ title, fallback = "/mypage" }: { title: string; fallback?: string }) {
  const router = useRouter();
  const back = () => { if (typeof window !== "undefined" && window.history.length > 1) router.back(); else router.push(fallback); };
  return (
    <div className={styles.head}>
      <button type="button" className={styles.back} onClick={back} aria-label="뒤로 가기">←</button>
      <span className={styles.title}>{title}</span>
    </div>
  );
}
