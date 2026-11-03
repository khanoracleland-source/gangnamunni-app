"use client";
import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { useRouter } from "next/navigation";
import { ApiError, api } from "@/lib/api";
import { useMe } from "@/lib/me";
import BottomSheet from "./BottomSheet";
import checkIcon from "@/assets/icons/login-check.png";
import styles from "./PasswordReset.module.css";

const PW_RE = /^(?=.*[A-Za-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s])[\x21-\x7E]{9,20}$/;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const MAIL_KEY = "pw-reset-email";
const noop = () => () => {};
const storedMail = () => { try { return sessionStorage.getItem(MAIL_KEY) ?? ""; } catch { return ""; } };

/**
 * 비밀번호 재설정 (after 02-06): step 1 the temporary password from the mail, step 2 = 02-07 new password, then the 02-08 sheet.
 * The ticket from step 1 lives only in memory (reloading starts over).
 */
export default function PasswordReset() {
  const router = useRouter();
  const me = useMe();
  const [ticket, setTicket] = useState<string | null>(null);
  const stored = useSyncExternalStore(noop, storedMail, () => ""); // e-mail typed on the previous page (not in the URL)
  const [typed, setTyped] = useState<string | null>(null);
  const email = typed ?? stored;
  const setEmail = setTyped;
  const [temp, setTemp] = useState("");
  const [pw, setPw] = useState("");
  const [pw2, setPw2] = useState("");
  const [showTemp, setShowTemp] = useState(false);
  const [show, setShow] = useState(false);
  const [show2, setShow2] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [done, setDone] = useState(false);
  const sending = useRef(false);

  useEffect(() => { if (me.data?.loggedIn) router.replace("/"); }, [me.data, router]);
  const goBack = () => { if (ticket) { setTicket(null); setPw(""); setPw2(""); setError(""); } else if (window.history.length > 1) router.back(); else router.push("/password-lost"); };

  const run = async (fn: () => Promise<void>, fallback: string) => {
    if (sending.current) return;
    sending.current = true; setBusy(true); setError("");
    try { await fn(); }
    catch (err) { setError((err instanceof ApiError ? (err.body?.message as string | undefined) : undefined) || fallback); }
    finally { sending.current = false; setBusy(false); }
  };

  const verifyReady = EMAIL_RE.test(email.trim()) && !!temp && !busy && !error;
  const verify = (e: React.FormEvent) => { e.preventDefault(); if (!verifyReady) return;
    void run(async () => { const r = await api.passwordResetVerify(email.trim(), temp); setTicket(r.ticket); setTemp(""); }, "확인하지 못했어요. 잠시 후 다시 시도해주세요."); };

  const rule = PW_RE.test(pw);
  const same = !!pw2 && pw === pw2;
  const saveReady = rule && same && !busy && !error;
  const save = (e: React.FormEvent) => { e.preventDefault(); if (!saveReady || !ticket) return;
    void run(async () => { await api.passwordResetConfirm(ticket, pw); try { sessionStorage.removeItem(MAIL_KEY); } catch { /* ignore */ } setDone(true); }, "비밀번호를 변경하지 못했어요. 잠시 후 다시 시도해주세요."); };

  const eye = (on: boolean, toggle: () => void) => <button type="button" className={styles.eye} onClick={toggle} aria-pressed={on}>{on ? "숨김" : "표시"}</button>;
  const back = (
    <div className={styles.head}>
      <button type="button" className={styles.back} onClick={goBack} aria-label="뒤로 가기">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1A1718" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
      </button>
    </div>
  );

  if (!ticket) {
    return (
      <form className={styles.wrap} onSubmit={verify} noValidate>
        {back}
        <h1 className={styles.title}>임시 비밀번호를 입력해주세요</h1>
        <p className={styles.desc}>메일로 보내드린 임시 비밀번호를<br />그대로 입력해주세요</p>
        <div className={styles.fields}>
          <label className={styles.field}>
            <span className={styles.label}>이메일</span>
            <span className={styles.box}>
              <input type="email" inputMode="email" autoComplete="username" autoCapitalize="off" autoCorrect="off" spellCheck={false} placeholder="가입한 이메일을 입력해주세요"
                value={email} onChange={(e) => { setEmail(e.target.value); setError(""); }} />
            </span>
          </label>
          <div className={styles.field}>
            <label className={styles.label} htmlFor="tmp">임시 비밀번호</label>
            <span className={styles.box}>
              <input id="tmp" type={showTemp ? "text" : "password"} autoComplete="one-time-code" autoCapitalize="off" autoCorrect="off" spellCheck={false} placeholder="메일의 임시 비밀번호"
                value={temp} onChange={(e) => { setTemp(e.target.value); setError(""); }} />
              {eye(showTemp, () => setShowTemp((v) => !v))}
            </span>
            {error && <span className={styles.err} role="alert">{error}</span>}
          </div>
        </div>
        <div className={styles.bottom}>
          <button type="submit" className={`${styles.submit} ${verifyReady ? styles.on : ""}`} disabled={!verifyReady}>{busy ? "확인 중…" : "확인"}</button>
        </div>
      </form>
    );
  }

  return (
    <form className={styles.wrap} onSubmit={save} noValidate>
      {back}
      <h1 className={styles.title}>새 비밀번호를 입력해주세요</h1>
      <div className={styles.fields22}>
        <div className={styles.field}>
          <label className={styles.label} htmlFor="np">새 비밀번호</label>
          <span className={styles.box}>
            <input id="np" type={show ? "text" : "password"} autoComplete="new-password" maxLength={20} value={pw} onChange={(e) => { setPw(e.target.value); setError(""); }} />
            {eye(show, () => setShow((v) => !v))}
          </span>
          <span className={`${styles.hint} ${pw && !rule ? styles.bad : ""}`}>숫자, 영문자, 특수문자 포함 9~20자</span>
        </div>
        <div className={styles.field}>
          <label className={styles.label} htmlFor="np2">새 비밀번호 확인</label>
          <span className={styles.box}>
            <input id="np2" type={show2 ? "text" : "password"} autoComplete="new-password" maxLength={20} value={pw2} onChange={(e) => { setPw2(e.target.value); setError(""); }} />
            {eye(show2, () => setShow2((v) => !v))}
          </span>
          {pw2 && <span className={`${styles.hint} ${same ? "" : styles.bad}`}>{same ? "비밀번호가 일치해요" : "비밀번호가 일치하지 않아요"}</span>}
          {error && <span className={`${styles.hint} ${styles.bad}`} role="alert">{error}</span>}
        </div>
      </div>
      <div className={styles.bottom}>
        <button type="submit" className={`${styles.submit} ${saveReady ? styles.on : ""}`} disabled={!saveReady}>{busy ? "변경 중…" : "변경하기"}</button>
      </div>

      <BottomSheet open={done} onClose={() => router.replace("/login")} label="비밀번호 변경 완료">
        <div className={styles.sent}>
          <div className={styles.sentIco}>{/* eslint-disable-line @next/next/no-img-element */}<img src={checkIcon.src} alt="" /></div>
          <h2 className={styles.sentTitle}>비밀번호가 변경되었어요</h2>
          <p className={styles.sentText}>새 비밀번호로 다시 로그인해주세요</p>
          <button type="button" className={styles.ok} onClick={() => router.replace("/login")}>로그인하기</button>
        </div>
      </BottomSheet>
    </form>
  );
}
