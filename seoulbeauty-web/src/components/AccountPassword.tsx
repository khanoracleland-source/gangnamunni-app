"use client";
import { useRef, useState } from "react";
import { api, ApiError, type MyProfile } from "@/lib/api";
import { announceLogout } from "@/lib/bridge";
import { BASE_PATH } from "@/lib/phpLink";
import AccountGate from "./AccountGate";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import styles from "./MyPages.module.css";

type Err = { field: string; message: string } | null;

function Form({ profile }: { profile: MyProfile }) {
  const [cur, setCur] = useState("");
  const [next, setNext] = useState("");
  const [again, setAgain] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<Err>(null);
  const [forgot, setForgot] = useState(false);
  const [sending, setSending] = useState(false);
  const [sendErr, setSendErr] = useState("");
  const sheetRef = useRef<BottomSheetHandle>(null);
  const fieldErr = (f: string) => (err && err.field === f ? <div className={styles.pfErr} role="alert">{err.message}</div> : null);

  if (profile.social) return <div className={styles.empty}>소셜 로그인으로 가입한 계정은 서비스 비밀번호가 없어요.</div>;
  const save = async () => {
    if (busy) return;
    if (next !== again) { setErr({ field: "new_pw_confirm", message: "새 비밀번호가 일치하지 않습니다." }); return; }
    if (next.length < 6) { setErr({ field: "new_pw", message: "비밀번호는 6자 이상이어야 합니다." }); return; }
    setBusy(true); setErr(null);
    try {
      await api.changePassword({ current_pw: cur, new_pw: next, new_pw_confirm: again });
      // The server ended every session of this member (this one too): drop the app's tokens and sign in again.
      announceLogout();
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- full load on purpose
      window.location.assign(`${BASE_PATH}/login?pw_changed=1`);
    } catch (e) {
      setErr(e instanceof ApiError && e.status !== 401 ? { field: e.field ?? "form", message: e.message } : { field: "form", message: "변경하지 못했어요. 잠시 후 다시 시도해주세요." });
      setBusy(false);
    }
  };
  /** Forgot the current password: the same flow as the login page's "비밀번호 찾기" — a 12-character temporary password is mailed to the
   *  member's e-mail (this also ends every sign-in), then it is entered on /password-reset together with a new password. */
  const sendTemp = async () => {
    if (sending || !profile.email) return;
    setSending(true); setSendErr("");
    try {
      await api.sendTempPassword(); // resolves only when the mail was really sent (and every sign-in revoked)
      try { sessionStorage.setItem("pw-reset-email", profile.email); } catch { /* the next page asks for it */ }
      announceLogout(); // the request revoked every sign-in of this member, this one too
      // full load on purpose: the cached "logged in" state of this page must not send /password-reset back to the home page
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination
      window.location.assign(`${process.env.NEXT_PUBLIC_BASE_PATH ?? "/next"}/password-reset`);
    } catch (e) {
      setSendErr(e instanceof ApiError && e.status !== 401 ? e.message : "보내지 못했어요. 잠시 후 다시 시도해주세요."); // still signed in, nothing changed
      setSending(false);
    }
  };
  return (
    <>
      <p className={styles.acNote}>변경하면 모든 기기에서 로그아웃되며 새 비밀번호로 다시 로그인해야 해요. 비밀번호를 재설정했다면 이메일로 받은 12자리 임시 비밀번호를 현재 비밀번호에 입력해주세요.</p>
      <div className={styles.pfForm} style={{ paddingBottom: 24 }}>
        <div className={styles.pfField}>
          <label htmlFor="pw-cur">현재 비밀번호</label>
          <input id="pw-cur" type="password" value={cur} onChange={(e) => setCur(e.target.value)} placeholder="현재 비밀번호 입력" autoComplete="current-password" maxLength={128} />
          {fieldErr("current_pw")}
        </div>
        <div className={styles.pfField}>
          <label htmlFor="pw-new">새 비밀번호</label>
          <input id="pw-new" type="password" value={next} onChange={(e) => setNext(e.target.value)} placeholder="6자 이상 입력" autoComplete="new-password" maxLength={128} />
          {fieldErr("new_pw")}
        </div>
        <div className={styles.pfField}>
          <label htmlFor="pw-again">새 비밀번호 확인</label>
          <input id="pw-again" type="password" value={again} onChange={(e) => setAgain(e.target.value)} placeholder="새 비밀번호 재입력" autoComplete="new-password" maxLength={128} />
          {fieldErr("new_pw_confirm")}
        </div>
        {err && err.field === "form" && <div className={styles.pfErr} role="alert">{err.message}</div>}
      </div>
      <div className={styles.acBar}>
        <button type="button" className={styles.pfSave} disabled={busy || !cur || !next || !again} onClick={save}>{busy ? "변경 중…" : "비밀번호 변경"}</button>
        {profile.email && <button type="button" className={styles.fgLink} onClick={() => { setSendErr(""); setForgot(true); }}>현재 비밀번호를 잊으셨나요?</button>}
      </div>
      <BottomSheet ref={sheetRef} open={forgot} onClose={() => setForgot(false)} label="임시 비밀번호 받기" dismissible={!sending}>
        <div className={styles.fgSheet}>
          <h2 className={styles.fgTitle}>임시 비밀번호를 받을까요?</h2>
          <p className={styles.fgDesc}><b>{profile.email}</b>로 12자리 임시 비밀번호를 보내드려요.<br />보내면 모든 기기에서 로그아웃되고, 임시 비밀번호로 새 비밀번호를 설정할 수 있어요.</p>
          {sendErr && <div className={styles.pfErr} role="alert" style={{ textAlign: "center" }}>{sendErr}</div>}
          <div className={styles.fgBtns}>
            <button type="button" className={styles.fgBtn} disabled={sending} onClick={() => sheetRef.current?.close()}>취소</button>
            <button type="button" className={`${styles.fgBtn} ${styles.fgBtnPri}`} disabled={sending} onClick={sendTemp}>{sending ? "보내는 중…" : "보내기"}</button>
          </div>
        </div>
      </BottomSheet>
    </>
  );
}

/** profile_edit.php action=pw: current password, new password twice. */
export default function AccountPassword({ initial, anon }: { initial?: MyProfile; anon?: boolean }) {
  return <AccountGate title="비밀번호 변경" initial={initial} anon={anon}>{(p) => <Form profile={p} />}</AccountGate>;
}
