"use client";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { api, ApiError } from "@/lib/api";
import { meKey, useMe } from "@/lib/me";
import { TERMS_EN, TERMS_KO } from "@/content/legal";
import LegalDoc from "./LegalDoc";
import SupportHeader from "./SupportHeader";
import styles from "./Legal.module.css";

/** terms_of_service.php: 한국어 / English tabs; a signed-in member can record the consent to all three documents. */
export default function Terms() {
  const [lang, setLang] = useState<"ko" | "en">("ko");
  const [checked, setChecked] = useState(false);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [err, setErr] = useState("");
  const me = useMe();
  const qc = useQueryClient();
  const canAgree = !!me.data?.loggedIn && !done;
  const agree = async () => {
    if (busy || !checked) return;
    setBusy(true); setErr("");
    try { await api.agreeTerms(); setDone(true); }
    catch (e) {
      if (e instanceof ApiError && e.status === 401) { qc.setQueryData(meKey, { loggedIn: false, user: null, unreadNotifications: 0 }); setErr("로그인이 필요합니다."); }
      else setErr("저장하지 못했어요. 잠시 후 다시 시도해주세요.");
    } finally { setBusy(false); }
  };
  return (
    <div className={styles.wrap}>
      <SupportHeader title="이용약관" fallback="/mypage/settings" />
      <div className={styles.tabs} role="tablist">
        <button type="button" role="tab" aria-selected={lang === "ko"} className={`${styles.tab} ${lang === "ko" ? styles.tabOn : ""}`} onClick={() => setLang("ko")}>한국어</button>
        <button type="button" role="tab" aria-selected={lang === "en"} className={`${styles.tab} ${lang === "en" ? styles.tabOn : ""}`} onClick={() => setLang("en")}>English</button>
      </div>
      <div className={styles.body}>
        <LegalDoc html={`<div class="header"><h1>${lang === "ko" ? "이용약관" : "Terms of Service"}</h1><p>${lang === "ko" ? "Terms of Service" : "이용약관"}</p></div>${lang === "ko" ? TERMS_KO : TERMS_EN}`} />
      </div>
      {done && <div className={styles.agreed} role="status">동의가 저장되었습니다.</div>}
      {canAgree && (
        <div className={styles.agree}>
          {err && <p className={styles.agreeErr} role="alert">{err}</p>}
          <label><input type="checkbox" checked={checked} onChange={(e) => setChecked(e.target.checked)} />이용약관, 개인정보처리방침, 커뮤니티 가이드라인에 모두 동의합니다</label>
          <button type="button" className={styles.agreeBtn} disabled={!checked || busy} onClick={agree}>{busy ? "저장 중…" : "동의하기"}</button>
        </div>
      )}
    </div>
  );
}
