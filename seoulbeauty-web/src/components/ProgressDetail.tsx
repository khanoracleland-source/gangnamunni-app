"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, browserApiBase, qk, type ProgressDetail as Data } from "@/lib/api";
import { shrinkImage } from "@/lib/image";
import LoginPrompt from "./LoginPrompt";
import my from "./MyPages.module.css";
import styles from "./Progress.module.css";

type Pick = { file: File; url: string };
/** Photos are served by the API (private uploads dir); the browser reaches it through the same-origin /api rewrite. */
const photoSrc = (p: string) => `${browserApiBase()}${p}`;

/** progress_review.php: entries by round, add/delete an entry, 작성 완료, delete the whole 경과 후기. */
export default function ProgressDetail({ id, initial, anon }: { id: number; initial?: Data; anon?: boolean }) {
  const router = useRouter();
  const qc = useQueryClient();
  const { data, error, refetch } = useQuery({ queryKey: qk.progress(id), queryFn: () => api.progressDetail(id), initialData: initial, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  const [notice, setNotice] = useState("");
  const [err, setErr] = useState("");
  const [busy, setBusy] = useState(false);
  const [confirm, setConfirm] = useState<{ kind: "entry"; entryId: number } | { kind: "review" } | null>(null);
  const [edate, setEdate] = useState(() => initial && initial.enabled ? initial.today : "");
  const [content, setContent] = useState("");
  const [consent, setConsent] = useState(false);
  const [picks, setPicks] = useState<Pick[]>([]);
  const live = useRef<Pick[]>([]);
  useEffect(() => { live.current = picks; }, [picks]);
  useEffect(() => () => live.current.forEach((p) => URL.revokeObjectURL(p.url)), []);

  if (loggedOut) return <div className={styles.wrap}><Head title="경과 후기" /><LoginPrompt /></div>;
  if (!data) {
    return (
      <div className={styles.wrap}><Head title="경과 후기" />
        <div className={styles.empty}>{error instanceof ApiError && error.status === 404 ? <>경과 후기를 찾을 수 없습니다. <button type="button" className={my.retry} onClick={() => router.replace("/mypage/progress")}>목록으로</button></>
          : error ? <>불러오지 못했습니다. <button type="button" className={my.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      </div>
    );
  }
  if (!data.enabled) return <div className={styles.wrap}><Head title="경과 후기" /><div className={styles.empty}><p>경과 후기는 준비 중입니다.</p></div></div>;

  const d = data;
  const refresh = async () => { await refetch(); void qc.invalidateQueries({ queryKey: qk.myProgress }); };
  const run = async (fn: () => Promise<void>, fallback: string) => {
    if (busy) return;
    setBusy(true); setErr(""); setNotice("");
    try { await fn(); } catch (e) { setErr(e instanceof ApiError ? e.message : fallback); } finally { setBusy(false); }
  };
  const onPick = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files ?? []);
    e.target.value = "";
    const room = Math.max(0, d.limits.photos - picks.length);
    if (files.length > room) setErr(`사진은 최대 ${d.limits.photos}장까지 첨부할 수 있습니다.`);
    const small = await Promise.all(files.slice(0, room).map((f) => shrinkImage(f)));
    if (small.some((f) => f.size > d.limits.photoBytes)) { setErr("각 사진은 5MB 이하만 첨부할 수 있습니다."); return; }
    setPicks((cur) => [...cur, ...small.map((file) => ({ file, url: URL.createObjectURL(file) }))]);
  };
  const addEntry = (e: React.FormEvent) => {
    e.preventDefault();
    void run(async () => {
      if (Array.from(content.trim()).length < d.limits.entryMin) throw new ApiError(400, `경과 내용은 ${d.limits.entryMin}자 이상 작성해주세요.`);
      const fd = new FormData();
      fd.set("entry_date", edate || d.today); fd.set("content", content.trim());
      if (picks.length) { fd.set("photo_consent", consent ? "1" : "0"); picks.forEach((p) => fd.append("photos", p.file)); }
      const r = await api.addProgressEntry(id, fd);
      picks.forEach((p) => URL.revokeObjectURL(p.url));
      setPicks([]); setContent(""); setConsent(false); setEdate(d.today);
      setNotice(r.photoError ? "경과는 기록되었지만 사진은 저장하지 못했습니다." : "경과가 기록되었습니다.");
      await refresh();
    }, "저장하지 못했습니다. 잠시 후 다시 시도해주세요.");
  };
  const doConfirm = () => {
    const c = confirm;
    if (!c) return;
    setConfirm(null);
    void run(async () => {
      if (c.kind === "entry") { await api.deleteProgressEntry(id, c.entryId); await refresh(); }
      else { await api.deleteProgress(id); void qc.invalidateQueries({ queryKey: qk.myProgress }); router.replace("/mypage/progress"); }
    }, "삭제하지 못했습니다. 다시 시도해주세요.");
  };
  const submit = () => run(async () => { const r = await api.submitProgress(id); setNotice(r.message); await refresh(); }, "작성 완료하지 못했습니다. 다시 시도해주세요.");
  const left = d.need - d.entryCount;

  return (
    <div className={styles.wrap}>
      <Head title={d.title} />
      <div className={styles.sec}>
        <div><span className={`${styles.tag} ${d.status === "approved" ? styles.tagPri : ""}`}>{d.statusLabel}</span><span className={styles.tag}>{d.kindLabel}</span></div>
        <div className={styles.meta}>{[d.hospitalName, d.treatment].filter(Boolean).join(" · ")}{d.procedureDate && ` · 시술일 ${d.procedureDate.replace(/-/g, ".")}`}</div>
        <div className={styles.count}>경과 {d.entryCount} / {d.need}회</div>
        <div className={styles.bar}><i style={{ width: `${d.percent}%` }} /></div>
        {notice && <div className={styles.alert} role="status">{notice}</div>}
        {err && <div className={`${styles.alert} ${styles.err}`} role="alert">{err}</div>}
        {d.status === "rejected" ? (
          <div className={`${styles.alert} ${styles.err}`}>반려 사유: {d.rejectReason}{d.editable && <><br />작성일로부터 {d.resubmitDays}일 이내에 보완 후 다시 작성 완료할 수 있습니다.</>}</div>
        ) : d.status === "approved" ? (
          <div className={styles.alert}>{d.awardedPoint.toLocaleString("ko-KR")}P 적립이 완료되었습니다.</div>
        ) : d.status === "submitted" ? (
          <div className={styles.alert}>검수 중입니다. 작성일 기준 3일(영업일) 내 적립됩니다.</div>
        ) : null}
      </div>

      <div className={styles.band} />
      <div className={styles.sec}>
        {d.entries.length === 0 ? <div className={styles.empty}><p>첫 경과를 기록해보세요.</p></div> : d.entries.map((e) => (
          <div key={e.id} className={styles.entry}>
            <div className={styles.entryD}>{e.round}회차 · {e.dateLabel}{e.dPlus !== null && ` (D+${e.dPlus})`}</div>
            <div className={styles.entryC}>{e.content}</div>
            {e.photos.length > 0 && (
              <div className={styles.ph}>
                {e.photos.map((p) => (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img key={p} src={photoSrc(p)} alt="" loading="lazy" />
                ))}
              </div>
            )}
            {d.editable && <button type="button" className={styles.linkBtn} onClick={() => setConfirm({ kind: "entry", entryId: e.id })}>삭제</button>}
          </div>
        ))}
      </div>

      {d.editable && (
        <>
          <div className={styles.band} />
          <form className={styles.sec} onSubmit={addEntry}>
            <h2 className={styles.h2}>경과 기록하기</h2>
            <div className={styles.field}><label htmlFor="pe-date">날짜</label><input id="pe-date" type="date" value={edate} min={d.procedureDate || undefined} max={d.today} onChange={(e) => setEdate(e.target.value)} /></div>
            <div className={styles.field}><label htmlFor="pe-content">경과 내용</label>
              <textarea id="pe-content" value={content} maxLength={d.limits.entryMax} onChange={(e) => setContent(e.target.value)} placeholder={`붓기·통증·변화 등 오늘의 경과를 ${d.limits.entryMin}자 이상 적어주세요.`} />
            </div>
            <div className={styles.field}><label>사진 (최대 {d.limits.photos}장)</label>
              <div className={styles.picks}>
                {picks.map((p) => (
                  <div key={p.url} className={styles.thumb}>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={p.url} alt="" />
                    <button type="button" aria-label="사진 삭제" onClick={() => { URL.revokeObjectURL(p.url); setPicks((c) => c.filter((x) => x !== p)); }}>×</button>
                  </div>
                ))}
              </div>
              {picks.length < d.limits.photos && <input type="file" aria-label="사진 선택" accept="image/jpeg,image/png,image/gif,image/webp" multiple onChange={onPick} />}
              <div className={styles.help}>경과 확인이 가능한 시술·수술 관련 사진을 첨부해주세요. 선정성 기준을 통과하지 못한 사진은 적립에서 제외됩니다.</div>
              <label className={styles.check}><input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} /> 사진을 후기에 공개하는 데 동의합니다.</label>
            </div>
            <button type="submit" className={`${styles.btn} ${styles.btnLine}`} disabled={busy}>기록 추가</button>
          </form>
          <div className={styles.sec} style={{ paddingTop: 0 }}>
            <button type="button" className={styles.btn} disabled={busy || left > 0} onClick={submit}>작성 완료{left > 0 ? ` (${left}회 남음)` : ""}</button>
            <div className={styles.help} style={{ textAlign: "center" }}>작성 완료 후에는 검수가 끝날 때까지 수정할 수 없습니다.</div>
          </div>
        </>
      )}
      {d.status !== "submitted" && (
        <div className={styles.sec}><button type="button" className={styles.del} onClick={() => setConfirm({ kind: "review" })}>경과 후기 삭제</button></div>
      )}

      {confirm && (
        <div className={styles.modal} role="presentation" onClick={() => setConfirm(null)}>
          <div className={styles.modalBox} role="dialog" aria-modal="true" aria-labelledby="pr-confirm" onClick={(e) => e.stopPropagation()}>
            <h2 id="pr-confirm">{confirm.kind === "entry" ? "이 경과를 삭제할까요?" : "경과 후기를 삭제할까요?"}</h2>
            <p>{confirm.kind === "entry" ? "삭제한 경과는 복구할 수 없어요." : "적립된 포인트는 회수됩니다."}</p>
            <button type="button" className={styles.modalGhost} onClick={() => setConfirm(null)}>취소</button>
            <button type="button" className={styles.modalDanger} onClick={doConfirm}>삭제할게요</button>
          </div>
        </div>
      )}
    </div>
  );
}

function Head({ title }: { title: string }) {
  const router = useRouter();
  return (
    <div className={my.head}>
      <button type="button" className={my.back} onClick={() => router.push("/mypage/progress")} aria-label="뒤로 가기">←</button>
      <span className={my.headTitle} style={{ fontSize: 18, fontWeight: 500 }}>{title}</span>
    </div>
  );
}
