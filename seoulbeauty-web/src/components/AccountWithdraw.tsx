"use client";
import { useState } from "react";
import { api, ApiError, type MyProfile } from "@/lib/api";
import { announceLogout } from "@/lib/bridge";
import AccountGate from "./AccountGate";
import styles from "./MyPages.module.css";

const REASONS = ["서비스 불만", "사용 빈도 낮음", "다른 서비스 이용", "개인정보 보호", "기타"];
type Err = { field: string; message: string } | null;

function Form({ profile }: { profile: MyProfile }) {
  const social = profile.social === true;
  const [reason, setReason] = useState("");
  const [detail, setDetail] = useState("");
  const [password, setPassword] = useState("");
  const [agree, setAgree] = useState(false);
  const [ask, setAsk] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<Err>(null);

  const open = () => {
    if (!social && !password) { setErr({ field: "password", message: "비밀번호를 입력해주세요." }); return; }
    setErr(null); setAsk(true);
  };
  const submit = async () => {
    if (busy) return;
    setBusy(true); setErr(null);
    try {
      await api.withdraw({ reason, detail, password });
      announceLogout();
      // Same as the PHP pages: back to the start page. The ?app_logout=1 marker keeps the app from restoring the removed session.
      window.location.replace("/next/?app_logout=1");
    } catch (e) {
      setAsk(false);
      setErr(e instanceof ApiError && e.status !== 401 ? { field: e.field ?? "form", message: e.message } : { field: "form", message: "탈퇴 처리에 실패했습니다. 잠시 후 다시 시도해주세요." });
      setBusy(false);
    }
  };
  return (
    <>
      <div className={styles.acWarn}>
        <h2>탈퇴 시 유의사항</h2>
        <ul>
          <li>탈퇴 후 계정 복구가 불가능합니다.</li>
          <li>작성한 후기, 상담 내역은 삭제되지 않습니다.</li>
          <li>진행 중인 예약이 있는 경우 먼저 취소해주세요.</li>
          <li>동일 아이디로 재가입이 제한될 수 있습니다.</li>
        </ul>
      </div>
      <div className={styles.pfForm} style={{ paddingBottom: 24 }}>
        <div className={styles.pfField}>
          <label htmlFor="wd-reason">탈퇴 사유</label>
          <select id="wd-reason" value={reason} onChange={(e) => setReason(e.target.value)}>
            <option value="">사유를 선택해주세요</option>
            {REASONS.map((r) => <option key={r} value={r}>{r}</option>)}
          </select>
          {err && err.field === "reason" && <div className={styles.pfErr} role="alert">{err.message}</div>}
        </div>
        <div className={styles.pfField}>
          <label htmlFor="wd-detail">추가 의견 (선택)</label>
          <textarea id="wd-detail" value={detail} onChange={(e) => setDetail(e.target.value)} placeholder="서비스 개선을 위해 의견을 남겨주세요." maxLength={500} />
        </div>
        {!social && (
          <div className={styles.pfField}>
            <label htmlFor="wd-pw">비밀번호 확인</label>
            <input id="wd-pw" type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="현재 비밀번호를 입력해주세요" autoComplete="current-password" maxLength={128} />
            {err && err.field === "password" && <div className={styles.pfErr} role="alert">{err.message}</div>}
          </div>
        )}
        <label className={styles.pfCheck}><input type="checkbox" checked={agree} onChange={(e) => setAgree(e.target.checked)} /><span>위 유의사항을 모두 확인했으며, 탈퇴에 동의합니다.</span></label>
        {err && err.field === "form" && <div className={styles.pfErr} role="alert">{err.message}</div>}
      </div>
      <div className={styles.acBar}>
        <button type="button" className={styles.pfDanger} style={{ marginTop: 0 }} disabled={!agree || busy} onClick={open}>회원 탈퇴</button>
      </div>
      {ask && (
        <div className={styles.pfModal} role="presentation" onClick={() => !busy && setAsk(false)}>
          <div className={styles.pfModalBox} role="dialog" aria-modal="true" aria-labelledby="wd-ask" onClick={(e) => e.stopPropagation()}>
            <h2 id="wd-ask">정말 탈퇴하시겠습니까?</h2>
            <p>이 작업은 되돌릴 수 없습니다.</p>
            <button type="button" className={styles.pfDanger} disabled={busy} onClick={submit}>{busy ? "처리 중…" : "탈퇴하기"}</button>
            <button type="button" className={styles.pfDiscard} disabled={busy} onClick={() => setAsk(false)}>취소</button>
          </div>
        </div>
      )}
    </>
  );
}

/** withdraw.php: reason, password (not for social-login members), confirmation. The clean-up itself runs in PHP. */
export default function AccountWithdraw({ initial, anon }: { initial?: MyProfile; anon?: boolean }) {
  return <AccountGate title="회원 탈퇴" initial={initial} anon={anon}>{(p) => <Form profile={p} />}</AccountGate>;
}
