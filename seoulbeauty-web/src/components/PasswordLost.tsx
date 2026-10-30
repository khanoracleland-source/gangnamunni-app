"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, api, assetUrl } from "@/lib/api";
import { useMe } from "@/lib/me";
import { phpUrl, usePhpBase } from "@/lib/php";
import styles from "./AuthPages.module.css";

/** auth/password_lost.php: e-mail → temporary password mailed (the same answer whether or not the address is a member). */
export default function PasswordLost() {
  const router = useRouter();
  const php = usePhpBase();
  const me = useMe();
  const [email, setEmail] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [done, setDone] = useState<string | null>(null);
  const sending = useRef(false);

  /** A signed-in member goes home (as the PHP page does). */
  useEffect(() => { if (me.data?.loggedIn) router.replace("/"); }, [me.data, router]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (sending.current) return;
    sending.current = true; setBusy(true); setError("");
    try {
      const r = await api.passwordLost(email.trim());
      setDone(r.requestId ?? "");
    } catch (err) {
      const m = err instanceof ApiError ? (err.body?.message as string | undefined) : undefined;
      setError(m || "요청을 처리하지 못했습니다. 잠시 후 다시 시도해주세요.");
    } finally { sending.current = false; setBusy(false); }
  };
  const login = phpUrl(php, "auth/login.php");

  return (
    <div className={styles.wrap}>
      <div className={styles.head}><button type="button" className={styles.back} onClick={() => { if (window.history.length > 1) router.back(); else window.location.assign(login); }} aria-label="뒤로 가기">←</button></div>
      <div className={styles.hero}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={assetUrl("data/logos/pc") ?? ""} alt="서울미녀" />
      </div>
      <div className={styles.actions}>
        {done !== null ? (
          <>
            <div className={styles.ok} role="status">
              입력하신 이메일이 가입되어 있다면<br />비밀번호 재설정 안내 메일을 보내드렸습니다.<br />메일함(스팸함 포함)을 확인해주세요.
              {done && <><br /><small>요청 번호: {done}</small></>}
            </div>
            <a href={login} className={styles.btn}>로그인으로 돌아가기</a>
          </>
        ) : (
          <>
            <h1 className={styles.title}>비밀번호 찾기</h1>
            <p className={styles.desc}>가입하신 이메일을 입력하시면<br />비밀번호를 재설정할 수 있는 링크를 보내드립니다.</p>
            {error && <div className={styles.err} role="alert">{error}</div>}
            <form onSubmit={submit} style={{ display: "contents" }}>
              <div className={styles.field}><input type="email" name="mb_email" placeholder="이메일" required autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
              <button type="submit" className={styles.btn} disabled={busy}>{busy ? "보내는 중…" : "재설정 메일 받기"}</button>
            </form>
          </>
        )}
        <div className={styles.foot}><a href={login}>로그인</a><span>|</span><a href={phpUrl(php, "auth/register.php")}>회원가입</a></div>
      </div>
    </div>
  );
}
