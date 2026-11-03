"use client";
import { useRouter } from "next/navigation";
import { hasSpaHistory } from "@/lib/nav";
import styles from "./MyPages.module.css";

/**
 * Sub-page header: back goes to 마이페이지 (the PHP pages' back button does `location.href = mypage.php`).
 * `backToCaller`: for pages reachable from anywhere (the bell on the home page / hospital list ...) back returns to the page the
 * visitor came from; opened directly (no history, or the first page of the visit came from another site) it falls back to 마이페이지.
 */
export default function MyHeader({ title, backToCaller = false, right }: { title: string; backToCaller?: boolean; right?: React.ReactNode }) {
  const router = useRouter();
  const goBack = () => {
    // the previous entry is in-app when we navigated inside the site since load, or this load itself came from a page of the site
    let fromSite = hasSpaHistory();
    if (!fromSite) { try { fromSite = !!document.referrer && new URL(document.referrer).origin === window.location.origin; } catch { /* external / unknown */ } }
    if (backToCaller && window.history.length > 1 && fromSite) router.back(); else router.push("/mypage");
  };
  if (right !== undefined) {
    // design header (03-15..17): 52px, chevron back, centered title, action slot on the right
    return (
      <div className={styles.hd}>
        <button type="button" className={styles.hdBack} onClick={goBack} aria-label="뒤로 가기">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
        <span className={styles.hdTitle}>{title}</span>
        <div className={styles.hdRight}>{right}</div>
      </div>
    );
  }
  return (
    <div className={styles.head}>
      <button type="button" className={styles.back} onClick={goBack} aria-label="뒤로 가기">←</button>
      <span className={styles.headTitle} style={{ fontSize: 18, fontWeight: 500 }}>{title}</span>
    </div>
  );
}
