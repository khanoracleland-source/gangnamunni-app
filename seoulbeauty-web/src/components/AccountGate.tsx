"use client";
import { useQuery } from "@tanstack/react-query";
import { api, ApiError, qk, type MyProfile } from "@/lib/api";
import LoginPrompt from "./LoginPrompt";
import SupportHeader from "./SupportHeader";
import styles from "./MyPages.module.css";

/** Shared frame of the account pages: header with back, the member's profile (email, social) and the login prompt for a visitor. */
export default function AccountGate({ title, initial, anon, children }: {
  title: string; initial?: MyProfile; anon?: boolean; children: (profile: MyProfile) => React.ReactNode;
}) {
  const { data, error, refetch } = useQuery({ queryKey: qk.myProfile, queryFn: () => api.myProfile(), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  return (
    <div className={styles.wrap}>
      <SupportHeader title={title} fallback="/mypage/profile" />
      {loggedOut ? <LoginPrompt text="로그인하면 이용할 수 있습니다." /> : !data ? (
        <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : children(data)}
    </div>
  );
}
