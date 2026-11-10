"use client";
import { useRef, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import { api, ApiError, type ContentType } from "@/lib/api";
import { copyText } from "@/lib/clipboard";
import { useRequireLogin } from "@/lib/me";
import linkIcon from "@/assets/icons/menu-link.png";
import blockIcon from "@/assets/icons/menu-block.png";
import reportIcon from "@/assets/icons/menu-report.png";
import editIcon from "@/assets/icons/menu-edit.png";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import { useToast } from "./Toast";
import styles from "./ContentActions.module.css";

const REASONS: [string, string][] = [
  ["spam", "영리성 홍보 목적"], ["sexual", "음란성/선정성"], ["harassment", "욕설/인신공격"],
  ["privacy", "개인정보노출"], ["repeat", "같은 내용 반복게시"], ["other", "기타"],
];
const DOTS = <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>;

const LINE = { fill: "none", stroke: "currentColor", strokeWidth: 1.6, strokeLinecap: "round", strokeLinejoin: "round" } as const;
const DELETE_ICON = <svg viewBox="0 0 24 24" {...LINE} aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M10 11v5M14 11v5" /></svg>;

type Step = "closed" | "menu" | "report" | "block" | "delete";

/** ⋯ button + action sheet (the shared BottomSheet, portal above the tab bar): own post → edit/delete; anyone else's post/comment/review → report/block. */
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
  const sheetRef = useRef<BottomSheetHandle>(null);
  const toast = useToast();

  const close = () => { setStep("closed"); setPicked([]); setDetail(""); setMsg(""); };
  const refresh = () => { void qc.invalidateQueries({ queryKey: ["community"] }); void qc.invalidateQueries({ queryKey: ["communityPost"] }); };
  const run = async (fn: () => Promise<unknown>, done: "deleted" | "blocked" | "reported", fallback: string) => {
    if (busy) return;
    setBusy(true); setMsg("");
    try {
      const r = await requireLogin(fn);
      if (r === undefined) return; // redirected to login
      refresh();
      sheetRef.current?.close();
      onDone?.(done);
    } catch (e) {
      setMsg(e instanceof ApiError && e.status !== 401 ? e.message : fallback);
    } finally {
      setBusy(false);
    }
  };

  /** "링크 복사": the page of this post/review (comments have no page of their own). */
  const copyLink = async () => {
    const url = `${window.location.origin}${process.env.NEXT_PUBLIC_BASE_PATH ?? "/next"}/${type === "review" ? "reviews" : "community"}/${id}`;
    const ok = await copyText(url);
    sheetRef.current?.close();
    toast(ok ? "링크가 복사되었습니다." : "링크 복사에 실패했습니다.");
  };

  const label = type === "community" ? "게시글" : type === "comment" ? "댓글" : "후기";
  return (
    <>
      <button type="button" className={className} aria-label={`${label} 메뉴`} onClick={(e) => { e.stopPropagation(); setStep("menu"); }}>{DOTS}</button>
      {/* the sheet is a portal, but React still bubbles its clicks (dim tap included) to the parent card's onClick: stop them here */}
      <div style={{ display: "contents" }} onClick={(e) => e.stopPropagation()}>
      <BottomSheet ref={sheetRef} open={step !== "closed"} onClose={close} label={`${label} 메뉴`} dismissible={!busy}>
        <div className={styles.body} onClick={(e) => e.stopPropagation()}>
          <div>
            {step === "menu" && (
              <div className={styles.list}>
                {type !== "comment" && (
                  <button type="button" className={styles.row} onClick={copyLink}>
                    <span className={styles.ico}><Image src={linkIcon} alt="" width={22} height={22} /></span>링크 복사
                  </button>
                )}
                {isMine && type === "community" ? (
                  <>
                    <button type="button" className={styles.row} onClick={() => router.push(`/community/write?id=${id}`)}>
                      <span className={styles.ico}><Image src={editIcon} alt="" width={22} height={22} /></span>수정
                    </button>
                    <button type="button" className={`${styles.row} ${styles.red}`} onClick={() => setStep("delete")}>
                      <span className={styles.ico}>{DELETE_ICON}</span>삭제
                    </button>
                  </>
                ) : isMine ? (
                  <div className={styles.note}>내가 작성한 {label}입니다.</div>
                ) : (
                  <>
                    <button type="button" className={styles.row} onClick={() => setStep("block")}>
                      <span className={styles.ico}><Image src={blockIcon} alt="" width={22} height={22} /></span>차단
                    </button>
                    <button type="button" className={styles.row} onClick={() => setStep("report")}>
                      <span className={styles.ico}><Image src={reportIcon} alt="" width={22} height={22} /></span>신고
                    </button>
                  </>
                )}
              </div>
            )}
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
            <button type="button" className={styles.cancel} onClick={() => sheetRef.current?.close()}>취소</button>
          </div>
        </div>
      </BottomSheet>
      </div>
    </>
  );
}
