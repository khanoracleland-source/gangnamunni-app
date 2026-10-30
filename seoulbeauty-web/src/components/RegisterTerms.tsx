"use client";
import { useQuery } from "@tanstack/react-query";
import { api, qk, type RegisterTermsData } from "@/lib/api";
import SupportHeader from "./SupportHeader";
import styles from "./Legal.module.css";

/** bbs/register.php?view=terms: the two texts the admin maintains (회원가입약관, 개인정보 수집 및 이용정책). */
export default function RegisterTerms({ initial }: { initial?: RegisterTermsData }) {
  const { data, isError, refetch } = useQuery({ queryKey: qk.registerTerms, queryFn: () => api.registerTerms(), initialData: initial });
  return (
    <div className={styles.wrap}>
      <SupportHeader title="회원가입 약관 및 개인정보 정책" fallback="/mypage/settings" />
      {!data ? (
        <div className={styles.state}>{isError ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : (
        <>
          <h2 className={styles.h2}>회원가입약관</h2>
          <div className={styles.body}><pre className={styles.terms}>{data.terms}</pre></div>
          <h2 className={styles.h2}>개인정보 수집 및 이용정책</h2>
          <div className={styles.body}><pre className={styles.terms}>{data.privacy}</pre></div>
        </>
      )}
    </div>
  );
}
