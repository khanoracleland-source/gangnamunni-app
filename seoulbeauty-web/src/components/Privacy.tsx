"use client";
import { useState } from "react";
import { PRIVACY_EFFECTIVE, PRIVACY_HTML } from "@/content/legal";
import LegalDoc from "./LegalDoc";
import SupportHeader from "./SupportHeader";
import styles from "./Legal.module.css";

/** privacy_policy.php: the whole document is bilingual; the toggle shows one language at a time. */
export default function Privacy() {
  const [lang, setLang] = useState<"ko" | "en">("ko");
  return (
    <div className={styles.wrap}>
      <SupportHeader title="개인정보처리방침" fallback="/mypage/settings" />
      <div className={styles.tabs}>
        <button type="button" className={styles.langBtn} onClick={() => setLang(lang === "ko" ? "en" : "ko")}>{lang === "ko" ? "English" : "한국어"}</button>
      </div>
      <div className={styles.pHeader}>
        <h1>{lang === "ko" ? "개인정보처리방침" : "Privacy Policy"}</h1>
        <p>{PRIVACY_EFFECTIVE[lang]}</p>
      </div>
      <div className={styles.body}><LegalDoc html={PRIVACY_HTML} lang={lang} /></div>
    </div>
  );
}
