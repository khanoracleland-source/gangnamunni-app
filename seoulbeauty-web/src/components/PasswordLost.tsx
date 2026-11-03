"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, api } from "@/lib/api";
import { useMe } from "@/lib/me";
import BottomSheet from "./BottomSheet";
import sentIcon from "@/assets/icons/login-check.png";
import styles from "./PasswordLost.module.css";

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** 02-05 / 02-06: e-mail → reset link mailed (the same answer whether or not the address is a member). */
export default function PasswordLost() {
  const router = useRouter();
  const me = useMe();
  const [email, setEmail] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [sentTo, setSentTo] = useState<string | null>(null);
  const sending = useRef(false);

  /** A signed-in member goes home (as the PHP page does). */
  useEffect(() => { if (me.data?.loggedIn) router.replace("/"); }, [me.data, router]);

  const valid = EMAIL_RE.test(email.trim());
  const ready = valid && !busy && !error; // after an error the button waits until the e-mail is edited (no tap-tap-tap on a rate limit)
  const goBack = () => { if (window.history.length > 1) router.back(); else router.push("/login/email"); };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (sending.current || !valid) return;
    sending.current = true; setBusy(true); setError("");
    try {
      await api.passwordLost(email.trim());
      try { sessionStorage.setItem("pw-reset-email", email.trim()); } catch { /* private mode: the next page asks for it */ }
      setSentTo(email.trim());
    } catch (err) {
      const m = err instanceof ApiError ? (err.body?.message as string | undefined) : undefined;
      setError(m || "요청을 처리하지 못했습니다. 잠시 후 다시 시도해주세요.");
    } finally { sending.current = false; setBusy(false); }
  };

  return (
    <form className={styles.wrap} onSubmit={submit} noValidate>
      <div className={styles.head}>
        <button type="button" className={styles.back} onClick={goBack} aria-label="뒤로 가기">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1A1718" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
      </div>
      <h1 className={styles.title}>비밀번호를 잊으셨나요?</h1>
      <p className={styles.desc}>가입한 이메일을 입력하시면<br />비밀번호를 다시 설정할 수 있는 링크를 보내드려요</p>
      <label className={styles.field}>
        <span className={styles.label}>이메일</span>
        <span className={styles.box}>
          <input type="email" name="mb_email" inputMode="email" autoComplete="email" autoCapitalize="off" autoCorrect="off" spellCheck={false} placeholder="가입한 이메일을 입력해주세요"
            value={email} onChange={(e) => { setEmail(e.target.value); setError(""); }} />
        </span>
        {error && <span className={styles.err} role="alert">{error}</span>}
      </label>
      <div className={styles.note}>카카오 · 구글 · Apple로 가입하셨다면 해당 서비스에서 로그인해주세요</div>
      <div className={styles.bottom}>
        <button type="submit" className={`${styles.submit} ${ready ? styles.on : ""}`} disabled={!ready}>{busy ? "보내는 중…" : "재설정 메일 보내기"}</button>
      </div>

      <BottomSheet open={sentTo !== null} onClose={() => router.replace("/password-reset")} label="메일 발송 안내">
        <div className={styles.sent}>
          <div className={styles.sentIco}>{/* eslint-disable-line @next/next/no-img-element */}<img src={sentIcon.src} alt="" /></div>
          <h2 className={styles.sentTitle}>메일을 보냈어요</h2>
          <p className={styles.sentText}>{sentTo} 으로<br />비밀번호 재설정 링크를 보냈어요.<br />메일이 안 보이면 스팸함도 확인해주세요</p>
          <button type="button" className={styles.ok} onClick={() => router.replace("/password-reset")}>확인했어요</button>
        </div>
      </BottomSheet>
    </form>
  );
}
