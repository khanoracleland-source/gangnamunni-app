"use client";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk, type MyProfile } from "@/lib/api";
import { meKey } from "@/lib/me";
import AccountGate from "./AccountGate";
import styles from "./MyPages.module.css";

type Err = { field: string; message: string } | null;
const fmt = (s: number) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, "0")}`;

function Form({ profile }: { profile: MyProfile }) {
  const router = useRouter();
  const qc = useQueryClient();
  const [email, setEmail] = useState("");
  const [code, setCode] = useState("");
  const [sentTo, setSentTo] = useState("");
  const [left, setLeft] = useState(0);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<Err>(null);
  const [done, setDone] = useState("");
  const [expireAt, setExpireAt] = useState(0);
  useEffect(() => {
    if (!expireAt) return;
    const t = setInterval(() => {
      const s = Math.max(0, Math.round((expireAt - Date.now()) / 1000));
      setLeft(s);
      if (s <= 0) clearInterval(t);
    }, 1000);
    return () => clearInterval(t);
  }, [expireAt]);
  const fail = (e: unknown, fallback: string): Err => (e instanceof ApiError && e.status !== 401 ? { field: e.field ?? "form", message: e.message } : { field: "form", message: fallback });
  const send = async () => {
    if (busy) return;
    setBusy(true); setErr(null);
    try {
      const r = await api.sendEmailCode(email.trim());
      setSentTo(r.email); setCode("");
      setLeft(r.expiresIn);
      setExpireAt(Date.now() + r.expiresIn * 1000);
    } catch (e) { setErr(fail(e, "발송하지 못했어요. 잠시 후 다시 시도해주세요.")); } finally { setBusy(false); }
  };
  const verify = async () => {
    if (busy) return;
    setBusy(true); setErr(null);
    try {
      const r = await api.verifyEmailCode(code.trim());
      qc.invalidateQueries({ queryKey: qk.myProfile });
      qc.invalidateQueries({ queryKey: meKey });
      setDone(r.email || sentTo);
      setTimeout(() => router.push("/mypage/profile"), 1200);
    } catch (e) {
      const f = fail(e, "확인하지 못했어요. 잠시 후 다시 시도해주세요.");
      setErr(f);
      if (e instanceof ApiError && (e.status === 429 || /만료|먼저 발송/.test(e.message))) setSentTo(""); // the code is gone: back to step 1
    } finally { setBusy(false); }
  };
  const fieldErr = (f: string) => (err && err.field === f ? <div className={styles.pfErr} role="alert">{err.message}</div> : null);

  if (done) return <div className={styles.acOk} role="status">이메일이 변경되었습니다 ({done})</div>;
  return (
    <>
      <div className={styles.pfForm} style={{ paddingBottom: 24 }}>
        <div className={styles.pfField}>
          <label>현재 이메일</label>
          <div className={styles.pfRow}><span>{profile.email || "-"}</span></div>
        </div>
        {!sentTo ? (
          <div className={styles.pfField}>
            <label htmlFor="ac-email">새 이메일</label>
            <input id="ac-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="새 이메일 입력" autoComplete="email" autoCapitalize="none" maxLength={100} />
            {fieldErr("new_email")}
          </div>
        ) : (
          <div className={styles.pfField}>
            <label htmlFor="ac-code">인증 코드 <span className={styles.acTimer}>{left > 0 ? fmt(left) : "만료됨"}</span></label>
            <div className={styles.acNote} style={{ margin: "0 0 8px" }}><b>{sentTo}</b> 으로 인증 코드를 발송했습니다</div>
            <input id="ac-code" inputMode="numeric" autoComplete="one-time-code" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, "").slice(0, 6))} placeholder="6자리 코드" maxLength={6} />
            {fieldErr("code")}
          </div>
        )}
        {err && err.field === "form" && <div className={styles.pfErr} role="alert">{err.message}</div>}
      </div>
      <div className={styles.acBar}>
        {!sentTo ? (
          <button type="button" className={styles.pfSave} disabled={busy || !email.trim()} onClick={send}>{busy ? "발송 중…" : "인증 코드 발송"}</button>
        ) : (
          <>
            <button type="button" className={styles.pfSave} disabled={busy || code.length !== 6 || left === 0} onClick={verify}>{busy ? "확인 중…" : "확인"}</button>
            <button type="button" className={styles.acLink} disabled={busy} onClick={() => { setSentTo(""); setErr(null); }}>이메일 다시 입력</button>
          </>
        )}
      </div>
    </>
  );
}

/** profile_edit.php: change the e-mail address — a code is sent to the new one (valid 3 minutes). */
export default function AccountEmail({ initial, anon }: { initial?: MyProfile; anon?: boolean }) {
  return <AccountGate title="이메일 변경" initial={initial} anon={anon}>{(p) => <Form profile={p} />}</AccountGate>;
}
