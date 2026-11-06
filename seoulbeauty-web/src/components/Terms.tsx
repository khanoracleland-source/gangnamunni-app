"use client";
import { useEffect, useRef, useState } from "react";
import { TERMS_EFFECTIVE, TERMS_EN, TERMS_KO } from "@/content/legal";
import LegalDoc from "./LegalDoc";
import MyHeader from "./MyHeader";
import styles from "./Legal.module.css";

/** 서비스 이용약관 as in design 07-06: header, the 시행일 row (its ▾ opens the 한국어 / English choice) and the plain text. Nothing else on the page. */
export default function Terms() {
  const [lang, setLang] = useState<"ko" | "en">("ko");
  const [pick, setPick] = useState(false);
  const verRef = useRef<HTMLDivElement>(null);
  const toggleRef = useRef<HTMLButtonElement>(null);
  // the language list closes on a tap outside, on scroll and on Esc (focus returns to the row)
  useEffect(() => {
    if (!pick) return;
    const outside = (e: PointerEvent) => { if (!verRef.current?.contains(e.target as Node)) setPick(false); };
    const esc = (e: KeyboardEvent) => { if (e.key === "Escape") { setPick(false); toggleRef.current?.focus(); } };
    const close = () => setPick(false);
    document.addEventListener("pointerdown", outside);
    document.addEventListener("keydown", esc);
    window.addEventListener("scroll", close, { passive: true });
    return () => { document.removeEventListener("pointerdown", outside); document.removeEventListener("keydown", esc); window.removeEventListener("scroll", close); };
  }, [pick]);
  return (
    <div className={`${styles.wrap} ${styles.wrapFlat}`}>
      <div className={styles.top}><MyHeader title={lang === "ko" ? "서비스 이용약관" : "Terms of Service"} backToCaller right={null} /></div>
      <div className={styles.verWrap} ref={verRef}>
        <button type="button" ref={toggleRef} className={styles.ver} aria-expanded={pick} aria-controls="terms-lang" onClick={() => setPick((v) => !v)}>
          <span>{lang === "ko" ? `시행일 ${TERMS_EFFECTIVE} (현재)` : `Effective ${TERMS_EFFECTIVE} (current)`}</span>
          <svg className={pick ? styles.verUp : ""} width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
        </button>
        {pick && (
          <div id="terms-lang" className={styles.verList} role="group" aria-label="언어 / Language">
            {([["ko", "한국어"], ["en", "English"]] as const).map(([l, label]) => (
              <button key={l} type="button" lang={l} aria-pressed={lang === l} className={lang === l ? styles.verOn : ""} onClick={() => { setLang(l); setPick(false); toggleRef.current?.focus(); }}>{label}</button>
            ))}
          </div>
        )}
      </div>
      <div className={styles.bodyPlain}>
        <LegalDoc plain html={lang === "ko" ? TERMS_KO : TERMS_EN} />
      </div>
    </div>
  );
}
