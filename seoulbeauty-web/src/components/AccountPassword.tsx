"use client";
import { useState } from "react";
import { api, ApiError, type MyProfile } from "@/lib/api";
import { announceLogout } from "@/lib/bridge";
import { phpUrl, usePhpBase } from "@/lib/php";
import AccountGate from "./AccountGate";
import styles from "./MyPages.module.css";

type Err = { field: string; message: string } | null;

function Form({ profile }: { profile: MyProfile }) {
  const php = usePhpBase();
  const [cur, setCur] = useState("");
  const [next, setNext] = useState("");
  const [again, setAgain] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<Err>(null);
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
      window.location.assign(phpUrl(php, "auth/login.php?pw_changed=1"));
    } catch (e) {
      setErr(e instanceof ApiError && e.status !== 401 ? { field: e.field ?? "form", message: e.message } : { field: "form", message: "변경하지 못했어요. 잠시 후 다시 시도해주세요." });
      setBusy(false);
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
      </div>
    </>
  );
}

/** profile_edit.php action=pw: current password, new password twice. */
export default function AccountPassword({ initial, anon }: { initial?: MyProfile; anon?: boolean }) {
  return <AccountGate title="비밀번호 변경" initial={initial} anon={anon}>{(p) => <Form profile={p} />}</AccountGate>;
}
