"use client";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { ApiError, api } from "@/lib/api";
import { useMe } from "@/lib/me";
import styles from "./EmailLogin.module.css";

/** 02-03 / 02-04: e-mail sign-in. The session is created by PHP through the Nest bridge. */
export default function EmailLogin() {
  const me = useMe();
  const params = useSearchParams();
  const router = useRouter();
  const url = params.get("url") ?? "";
  const safeUrl = url && url.startsWith("/") && !url.startsWith("//") ? url : "";
  const qs = url ? `?url=${encodeURIComponent(url)}` : "";
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [show, setShow] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const sending = useRef(false);

  useEffect(() => { if (me.data?.loggedIn && !sending.current) window.location.replace(safeUrl || "/next"); }, [me.data, safeUrl]);

  /** ← : previous page (the start page); opened directly → the start page. */
  const goBack = () => { if (window.history.length > 1) router.back(); else router.push(`/login${qs}`); };

  const ready = !!email.trim() && !!password && !busy && !error;
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (sending.current || !email.trim() || !password) return;
    sending.current = true; setBusy(true); setError("");
    try {
      const r = await api.login({ email: email.trim(), password, remember: false, url });
      window.location.assign(r.redirect || "/next"); // full load: every page must see the new PHP session cookie
      return;
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        // 02-04: wrong e-mail / password, with the attempt counter when the API sends it
        const n = Number(err.body?.attempts), max = Number(err.body?.limit);
        setError(`이메일 또는 비밀번호가 맞지 않아요. 다시 확인해주세요${n > 0 && max > 0 ? ` (${n}/${max})` : ""}`);
      } else {
        setError((err instanceof ApiError ? (err.body?.message as string | undefined) : undefined) || "로그인 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.");
      }
    }
    sending.current = false; setBusy(false);
  };

  return (
    <form className={styles.wrap} onSubmit={submit} noValidate>
      <div className={styles.head}>
        <button type="button" className={styles.back} onClick={goBack} aria-label="뒤로 가기">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1A1718" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
      </div>
      <h1 className={styles.title}>이메일로 로그인해주세요</h1>
      <div className={styles.fields}>
        <label className={styles.field}>
          <span className={styles.label}>이메일</span>
          <span className={styles.box}>
            <input type="email" inputMode="email" autoComplete="username" autoCapitalize="off" autoCorrect="off" spellCheck={false} placeholder="이메일을 입력해주세요"
              value={email} onChange={(e) => { setEmail(e.target.value); setError(""); }} />
          </span>
        </label>
        <div className={styles.field}>
          <label className={styles.label} htmlFor="pw">비밀번호</label>
          <span className={styles.box}>
            <input id="pw" type={show ? "text" : "password"} autoComplete="current-password" placeholder="비밀번호를 입력해주세요"
              value={password} onChange={(e) => { setPassword(e.target.value); setError(""); }} />
            <button type="button" className={styles.eye} onClick={() => setShow((v) => !v)} aria-pressed={show}>{show ? "숨김" : "표시"}</button>
          </span>
          {error && <span className={styles.err} role="alert">{error}</span>}
        </div>
      </div>
      <div className={styles.links}>
        <Link href="/password-lost">비밀번호 찾기</Link><i /><Link href="/register">회원가입</Link>
      </div>
      <div className={styles.bottom}>
        <button type="submit" className={`${styles.submit} ${ready ? styles.on : ""}`} disabled={!ready}>{busy ? "로그인 중…" : "로그인"}</button>
      </div>
    </form>
  );
}
