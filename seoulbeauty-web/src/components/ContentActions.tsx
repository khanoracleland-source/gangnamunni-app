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
import hideContentIcon from "@/assets/icons/menu-hide-content.png";
import hideNoticeIcon from "@/assets/icons/menu-hide-notice.png";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import { useToast } from "./Toast";
import styles from "./ContentActions.module.css";

const REASONS: [string, string][] = [
  ["spam", "영리성 홍보 목적"], ["sexual", "음란성/선정성"], ["harassment", "욕설/인신공격"],
  ["privacy", "개인정보노출"], ["repeat", "같은 내용 반복게시"], ["other", "기타"],
];
const DOTS = <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>;

const LINE = { fill: "none", stroke: "currentColor", strokeWidth: 1.6, strokeLinecap: "round", strokeLinejoin: "round" } as const;
const DELETE_ICON = <svg viewBox="0 0 24 24" {...LINE} aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>;

/** closing leg of a sheet → sheet hand-over: a bit quicker than a plain close (400 ms) so the pair does not drag */
const CHAIN_MS = 280;

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

  const nextRef = useRef<Step | null>(null);
  const liveRef = useRef(false); // a sheet is on screen (false once it has closed, e.g. the user dismissed it while the link was being copied)
  const afterRef = useRef<(() => void) | null>(null); // runs once the sheet has finished sliding out (e.g. the 링크 복사 toast)
  const close = () => {
    liveRef.current = false;
    setStep("closed"); setPicked([]); setDetail(""); setMsg("");
    // "신고" from the ⋯ menu: the menu sheet has just glided down; now a fresh sheet slides up with the report form
    const after = afterRef.current; afterRef.current = null;
    if (after) window.setTimeout(after, 30);
    const next = nextRef.current; nextRef.current = null;
    if (next) window.setTimeout(() => setStep(next), 30);
  };
  /** close the menu sheet with its slide-out, then open `next` as a new sheet */
  const goTo = (next: Step) => { nextRef.current = next; sheetRef.current?.close(CHAIN_MS); };
  const refresh = () => { void qc.invalidateQueries({ queryKey: ["community"] }); void qc.invalidateQueries({ queryKey: ["communityPost"] }); };
  const run = async (fn: () => Promise<unknown>, done: "deleted" | "blocked" | "reported", fallback: string) => {
    if (busy) return;
    setBusy(true); setMsg("");
    try {
      const r = await requireLogin(fn);
      if (r === undefined) return; // redirected to login
      refresh();
      sheetRef.current?.close();
      if (done === "blocked") toast("차단이 완료되었어요."); // here, not in the callers: the screen may change right after (detail page → list)
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
    const say = () => toast(ok ? "링크가 복사되었습니다." : "링크 복사에 실패했습니다.");
    if (!liveRef.current) { say(); return; } // the user already dismissed the sheet while copying
    afterRef.current = say; // after the sheet has glided down, then the toast rises
    sheetRef.current?.close();
  };

  const label = type === "community" ? "게시글" : type === "comment" ? "댓글" : "후기";
  return (
    <>
      <button type="button" className={className} aria-label={`${label} 메뉴`} onClick={(e) => { e.stopPropagation(); afterRef.current = null; nextRef.current = null; liveRef.current = true; setStep("menu"); }}>{DOTS}</button>
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
                      <span className={styles.ico}><Image src={editIcon} alt="" width={22} height={22} /></span>수정하기
                    </button>
                    <button type="button" className={styles.row} onClick={() => goTo("delete")}>
                      <span className={`${styles.ico} ${styles.red}`}>{DELETE_ICON}</span>삭제하기
                    </button>
                  </>
                ) : isMine ? (
                  <div className={styles.note}>내가 작성한 {label}입니다.</div>
                ) : (
                  <>
                    <button type="button" className={styles.row} onClick={() => goTo("block")}>
                      <span className={styles.ico}><Image src={blockIcon} alt="" width={22} height={22} /></span>차단
                    </button>
                    <button type="button" className={styles.row} onClick={() => goTo("report")}>
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
                <h2 className={styles.confirmTitle}>{authorName}님을 차단할까요?</h2>
                <ul className={styles.facts}>
                  <li>
                    <span className={styles.factIco}><Image src={hideContentIcon} alt="" width={24} height={24} /></span>
                    <div><strong>콘텐츠 비공개</strong><p>서로의 후기와 게시글, 프로필을 볼 수 없어요</p></div>
                  </li>
                  <li>
                    <span className={styles.factIco}><Image src={hideNoticeIcon} alt="" width={24} height={24} /></span>
                    <div><strong>차단 사실 비공개</strong><p>상대방에게 차단 사실을 알리지 않아요</p></div>
                  </li>
                </ul>
                {msg && <div className={styles.error} role="alert">{msg}</div>}
                <div className={styles.btnRow}>
                  <button type="button" className={styles.btnGray} disabled={busy} onClick={() => sheetRef.current?.close()}>취소</button>
                  <button type="button" className={styles.btnRed} disabled={busy}
                    onClick={() => run(() => api.blockAuthor(type, id), "blocked", "차단하지 못했습니다.")}>차단</button>
                </div>
              </>
            )}
            {step === "report" && (
              <>
                <h2 className={styles.listTitle}>신고하는 이유</h2>
                {REASONS.map(([key, text]) => (
                  <label key={key} className={`${styles.check} ${picked.includes(key) ? styles.checkOn : ""}`}>
                    <input type="checkbox" checked={picked.includes(key)}
                      onChange={(e) => setPicked((p) => (e.target.checked ? [...p, key] : p.filter((k) => k !== key)))} />{text}
                  </label>
                ))}
                {/* always mounted: the row height eases 0 → input height, so the sheet grows (and shrinks back) smoothly */}
                <div className={`${styles.reveal} ${picked.includes("other") ? styles.revealOn : ""}`} aria-hidden={!picked.includes("other")}>
                  <div className={styles.revealIn}>
                    <input type="text" className={styles.area} maxLength={50} tabIndex={picked.includes("other") ? 0 : -1} placeholder="기타 사유를 입력해주세요. (50자 이내)" value={detail} onChange={(e) => setDetail(e.target.value)} />
                  </div>
                </div>
                {msg && <div className={styles.error} role="alert">{msg}</div>}
                <div className={`${styles.btnRow} ${styles.btnRowList}`}>
                  <button type="button" className={styles.btnGray} disabled={busy} onClick={() => sheetRef.current?.close()}>취소</button>
                  <button type="button" className={styles.btnRed} disabled={busy || picked.length === 0 || (picked.includes("other") && !detail.trim())}
                    onClick={() => run(() => api.reportContent(type, id, picked, detail), "reported", "신고하지 못했습니다.")}>확인</button>
                </div>
              </>
            )}
            {step !== "block" && step !== "report" && msg && <div className={styles.error} role="alert">{msg}</div>}
            {step !== "block" && step !== "report" && <button type="button" className={styles.cancel} onClick={() => sheetRef.current?.close()}>취소</button>}
          </div>
        </div>
      </BottomSheet>
      </div>
    </>
  );
}
