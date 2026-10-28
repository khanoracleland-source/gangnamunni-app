"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import { api, ApiError, type ContentType } from "@/lib/api";
import { useRequireLogin } from "@/lib/me";
import styles from "./ContentActions.module.css";

const REASONS: [string, string][] = [
  ["spam", "영리성 홍보 목적"], ["sexual", "음란성/선정성"], ["harassment", "욕설/인신공격"],
  ["privacy", "개인정보노출"], ["repeat", "같은 내용 반복게시"], ["other", "기타"],
];
const DOTS = <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>;

type Step = "closed" | "menu" | "report" | "block" | "delete";

/** ⋯ button + action sheet: own post → edit/delete; anyone else's post/comment/review → report/block. Functional skeleton, design comes later. */
export default function ContentActions({ type, id, isMine, authorName, className, onDone }: {
  type: ContentType; id: number; isMine: boolean; authorName: string; className?: string;
  /** Called after a successful action. `leave` = the content is gone for this viewer (deleted/blocked). */
  onDone?: (what: "deleted" | "blocked" | "reported") => void;
}) {
  const router = useRouter();
  const qc = useQueryClient();
  const requireLogin = useRequireLogin();
  const [step, setStep] = useState<Step>("closed");
  const [picked, setPicked] = useState<string[]>([]);
  const [detail, setDetail] = useState("");
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState("");

  const close = () => { setStep("closed"); setPicked([]); setDetail(""); setMsg(""); };
  const refresh = () => { void qc.invalidateQueries({ queryKey: ["community"] }); void qc.invalidateQueries({ queryKey: ["communityPost"] }); };
  const run = async (fn: () => Promise<unknown>, done: "deleted" | "blocked" | "reported", fallback: string) => {
    if (busy) return;
    setBusy(true); setMsg("");
    try {
      const r = await requireLogin(fn);
      if (r === undefined) return; // redirected to login
      refresh();
      close();
      onDone?.(done);
    } catch (e) {
      setMsg(e instanceof ApiError && e.status !== 401 ? e.message : fallback);
    } finally {
      setBusy(false);
    }
  };

  const label = type === "community" ? "게시글" : type === "comment" ? "댓글" : "후기";
  return (
    <>
      <button type="button" className={className} aria-label={`${label} 메뉴`} onClick={(e) => { e.stopPropagation(); setStep("menu"); }}>{DOTS}</button>
      {step !== "closed" && (
        <div className={styles.overlay} role="dialog" aria-modal="true" onClick={(e) => { e.stopPropagation(); close(); }}>
          <div className={styles.sheet} onClick={(e) => e.stopPropagation()}>
            {step === "menu" && (isMine && type === "community" ? (
              <>
                <button type="button" className={styles.item} onClick={() => router.push(`/community/write?id=${id}`)}>수정</button>
                <button type="button" className={`${styles.item} ${styles.danger}`} onClick={() => setStep("delete")}>삭제</button>
              </>
            ) : isMine ? (
              <div className={styles.note}>내가 작성한 {label}입니다.</div>
            ) : (
              <>
                <button type="button" className={styles.item} onClick={() => setStep("report")}>신고하기</button>
                <button type="button" className={styles.item} onClick={() => setStep("block")}>{authorName} 차단하기</button>
              </>
            ))}
            {step === "delete" && (
              <>
                <p className={styles.text}>게시글을 삭제할까요?</p>
                <button type="button" className={`${styles.item} ${styles.danger}`} disabled={busy}
                  onClick={() => run(() => api.deleteCommunityPost(id), "deleted", "삭제하지 못했습니다.")}>삭제</button>
              </>
            )}
            {step === "block" && (
              <>
                <p className={styles.text}>{authorName}님을 차단하면 서로의 글과 댓글이 보이지 않습니다.</p>
                <button type="button" className={`${styles.item} ${styles.danger}`} disabled={busy}
                  onClick={() => run(() => api.blockAuthor(type, id), "blocked", "차단하지 못했습니다.")}>차단하기</button>
              </>
            )}
            {step === "report" && (
              <>
                <p className={styles.text}>신고 사유를 선택해주세요.</p>
                {REASONS.map(([key, text]) => (
                  <label key={key} className={styles.check}>
                    <input type="checkbox" checked={picked.includes(key)}
                      onChange={(e) => setPicked((p) => (e.target.checked ? [...p, key] : p.filter((k) => k !== key)))} />{text}
                  </label>
                ))}
                {picked.includes("other") && (
                  <textarea className={styles.area} maxLength={1000} placeholder="기타 사유를 입력해주세요." value={detail} onChange={(e) => setDetail(e.target.value)} />
                )}
                <button type="button" className={`${styles.item} ${styles.danger}`} disabled={busy || picked.length === 0 || (picked.includes("other") && !detail.trim())}
                  onClick={() => run(() => api.reportContent(type, id, picked, detail), "reported", "신고하지 못했습니다.")}>신고 접수</button>
              </>
            )}
            {msg && <div className={styles.error} role="alert">{msg}</div>}
            <button type="button" className={styles.item} onClick={close}>취소</button>
          </div>
        </div>
      )}
    </>
  );
}
