"use client";
import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk, type ProgressCard, type ProgressList as Data } from "@/lib/api";
import LoginPrompt from "./LoginPrompt";
import MyHeader from "./MyHeader";
import my from "./MyPages.module.css";
import styles from "./Progress.module.css";

export function Card({ p }: { p: ProgressCard }) {
  return (
    <Link href={`/mypage/progress/${p.id}`} className={styles.card}>
      <div><span className={`${styles.tag} ${p.status === "approved" ? styles.tagPri : ""}`}>{p.statusLabel}</span><span className={styles.tag}>{p.kindLabel}</span></div>
      <div className={styles.cardT}>{p.title}</div>
      <div className={styles.cardM}>{[p.hospitalName, p.treatment].filter(Boolean).join(" · ")} · 경과 {p.entryCount}/{p.need}회</div>
      <div className={styles.bar}><i style={{ width: `${p.percent}%` }} /></div>
    </Link>
  );
}

/** my_progress_reviews.php: my 경과 후기 list and the "start a new one" form. */
export default function ProgressList({ initial, anon }: { initial?: Data; anon?: boolean }) {
  const router = useRouter();
  const qc = useQueryClient();
  const { data, error, refetch } = useQuery({ queryKey: qk.myProgress, queryFn: () => api.myProgress(), initialData: initial, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  const [bookingId, setBookingId] = useState(0);
  const [kind, setKind] = useState<"procedure" | "surgery">("procedure");
  const [treatment, setTreatment] = useState("");
  const [pdate, setPdate] = useState("");
  const [title, setTitle] = useState("");
  const [err, setErr] = useState("");
  const [busy, setBusy] = useState(false);

  const start = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;
    setBusy(true); setErr("");
    try {
      const r = await api.createProgress({ booking_id: bookingId, kind, treatment_name: treatment.trim(), procedure_date: pdate, title: title.trim() });
      void qc.invalidateQueries({ queryKey: qk.myProgress });
      router.push(`/mypage/progress/${r.id}`);
    } catch (e2) {
      setErr(e2 instanceof ApiError ? e2.message : "저장하지 못했습니다. 잠시 후 다시 시도해주세요.");
    } finally { setBusy(false); }
  };

  return (
    <div className={styles.wrap}>
      <MyHeader title="경과 후기" />
      {loggedOut ? <LoginPrompt /> : !data ? (
        <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={my.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : !data.enabled ? (
        <div className={styles.empty}><p>경과 후기는 준비 중입니다.</p></div>
      ) : (
        <>
          <div className={styles.sec}>
            <div className={styles.alert}>
              시술 후기는 경과를 <b>{data.rules.procedure.need}회</b> 이상, 수술 후기는 <b>{data.rules.surgery.need}회</b> 이상 기록하고 작성 완료하면
              검수 후 <b>{data.rules.procedure.point.toLocaleString("ko-KR")}P / {data.rules.surgery.point.toLocaleString("ko-KR")}P</b>가 적립됩니다.{" "}
              <Link href="/points-policy/progress">자세히</Link>
            </div>
            {data.items.length === 0 ? <div className={styles.empty}><p>아직 작성한 경과 후기가 없습니다.</p></div> : data.items.map((p) => <Card key={p.id} p={p} />)}
          </div>
          <div className={styles.band} />
          <form className={styles.sec} onSubmit={start}>
            <h2 className={styles.h2}>새 경과 후기 시작</h2>
            {data.bookings.length > 0 && (
              <div className={styles.field}><label htmlFor="pr-booking">시술받은 예약</label>
                <select id="pr-booking" value={bookingId} onChange={(e) => setBookingId(Number(e.target.value))}>
                  <option value={0}>선택 안 함(직접 입력)</option>
                  {data.bookings.map((b) => <option key={b.id} value={b.id}>{b.label}</option>)}
                </select>
              </div>
            )}
            <div className={styles.field}><label htmlFor="pr-kind">구분</label>
              <select id="pr-kind" value={kind} onChange={(e) => setKind(e.target.value === "surgery" ? "surgery" : "procedure")}>
                <option value="procedure">시술 (경과 {data.rules.procedure.need}회)</option>
                <option value="surgery">수술 (경과 {data.rules.surgery.need}회)</option>
              </select>
            </div>
            <div className={styles.field}><label htmlFor="pr-treat">시술·수술명</label><input id="pr-treat" value={treatment} maxLength={100} onChange={(e) => setTreatment(e.target.value)} placeholder="예약을 선택하면 비워두어도 됩니다" /></div>
            <div className={styles.field}><label htmlFor="pr-date">시술일</label><input id="pr-date" type="date" value={pdate} max={data.today} onChange={(e) => setPdate(e.target.value)} /></div>
            <div className={styles.field}><label htmlFor="pr-title">제목 (선택)</label><input id="pr-title" value={title} maxLength={100} onChange={(e) => setTitle(e.target.value)} /></div>
            {err && <div className={`${styles.alert} ${styles.err}`} role="alert">{err}</div>}
            <button type="submit" className={styles.btn} disabled={busy}>{busy ? "저장 중…" : "시작하기"}</button>
          </form>
        </>
      )}
    </div>
  );
}
