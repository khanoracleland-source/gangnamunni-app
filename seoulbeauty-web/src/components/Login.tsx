"use client";
import { useEffect, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { ApiError, api, assetUrl } from "@/lib/api";
import { useMe } from "@/lib/me";
import { phpUrl, usePhpBase } from "@/lib/php";
import styles from "./AuthPages.module.css";

/** auth/login.php: social buttons (still PHP flows) + e-mail sign-in. The session is created by PHP through the Nest bridge. */
export default function Login() {
  const php = usePhpBase();
  const me = useMe();
  const params = useSearchParams();
  const url = params.get("url") ?? "";
  const notice = params.get("pw_changed") === "1" ? "비밀번호가 변경되었습니다. 보안을 위해 모든 기기에서 로그아웃되었습니다. 새 비밀번호로 다시 로그인해주세요." : "";
  const SOCIAL_ERR: Record<string, string> = {
    unavailable: "현재 이 소셜 로그인은 사용할 수 없습니다. 이메일로 로그인해주세요.",
    too_many: "시도가 너무 많습니다. 잠시 후 다시 시도해주세요.",
    state: "로그인 시간이 만료되었습니다. 다시 시도해주세요.",
    denied: "소셜 로그인이 취소되었습니다.",
    unverified: "이메일 인증이 완료되지 않은 계정입니다. 인증 메일을 확인해주세요.",
    exists: "이미 연결 또는 가입된 소셜 계정입니다. 기존 계정으로 로그인해주세요.",
    email_exists: "이미 가입된 이메일입니다. 이메일로 로그인해주세요.",
    blocked: "이용이 제한된 계정입니다. 고객센터에 문의해주세요.",
    failed: "소셜 로그인에 실패했습니다. 잠시 후 다시 시도해주세요.",
  };
  const socialError = SOCIAL_ERR[params.get("social_error") ?? ""] ?? "";
  const opts = useQuery({ queryKey: ["login-options"], queryFn: () => api.loginOptions(), staleTime: 60_000 });
  const [open, setOpen] = useState(!!params.get("pw_changed"));
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const sending = useRef(false);
  const home = phpUrl(php, "index.php");

  useEffect(() => { if (me.data?.loggedIn && !sending.current) window.location.replace(url && url.startsWith("/") && !url.startsWith("//") ? url : "/next"); }, [me.data, url]);

  const social = (p: string) => opts.data?.nest?.includes(p)
    ? `/next/api/v1/auth/social/${p}/start${url ? `?url=${encodeURIComponent(url)}` : ""}`
    : phpUrl(php, `auth/login.php?provider=${p}${url ? `&url=${encodeURIComponent(url)}` : ""}`);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (sending.current) return;
    sending.current = true; setBusy(true); setError("");
    try {
      const r = await api.login({ email: email.trim(), password, remember, url });
      window.location.assign(r.redirect || home); // full load: every page must see the new PHP session cookie
      return;
    } catch (err) {
      setError((err instanceof ApiError ? (err.body?.message as string | undefined) : undefined) || "로그인 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.");
    }
    sending.current = false; setBusy(false);
  };

  return (
    <div className={styles.wrap}>
      <div className={styles.head}><a className={styles.back} href={home} aria-label="홈으로" style={{ display: "flex", alignItems: "center", justifyContent: "center", textDecoration: "none" }}>←</a></div>
      <div className={styles.hero}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={assetUrl("data/logos/pc") ?? ""} alt="서울미녀" />
      </div>
      <div className={styles.actions}>
        {socialError && <div className={styles.err} role="alert">{socialError}</div>}
        {notice && <div className={styles.ok} role="status">{notice}</div>}
        {opts.data?.kakao && <a href={social("kakao")} className={`${styles.btn} ${styles.kakao}`}>카카오톡으로 시작하기</a>}
        {opts.data?.google && <a href={social("google")} className={`${styles.btn} ${styles.google}`}>Google로 시작하기</a>}
        {opts.data?.apple && <a href={social("apple")} className={`${styles.btn} ${styles.apple}`}>Apple로 시작하기</a>}
        <button type="button" className={`${styles.btn} ${styles.emailBtn}`} aria-expanded={open} onClick={() => setOpen((v) => !v)}>이메일로 시작하기</button>
        {open && (
          <form onSubmit={submit} style={{ display: "contents" }}>
            {error && <div className={styles.err} role="alert">{error}</div>}
            <div className={styles.field}><input type="email" placeholder="이메일" required autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
            <div className={styles.field}><input type="password" placeholder="비밀번호" required autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} /></div>
            <label className={styles.check}><input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} /> <span>자동 로그인</span></label>
            <button type="submit" className={styles.btn} disabled={busy || !email || !password}>{busy ? "로그인 중…" : "로그인"}</button>
          </form>
        )}
        <div className={styles.foot}>
          <a href="/next/password-lost">비밀번호 찾기</a><span>|</span><a href="/next/register">회원가입</a><span>|</span><a href="/next/qna">문의하기</a>
        </div>
      </div>
    </div>
  );
}
