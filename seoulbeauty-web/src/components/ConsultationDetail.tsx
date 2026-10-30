"use client";
import Link from "next/link";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk, type ConsultationDetail as Detail } from "@/lib/api";
import LoginPrompt from "./LoginPrompt";
import SupportHeader from "./SupportHeader";
import styles from "./Support.module.css";

const CHIP = { danger: styles.chipDanger, muted: styles.chipMuted, dark: styles.chipDark } as const;

/** my_consultation_detail.php: status, timeline, hospital message and (after a failed contact) the re-request with a callback slot. */
export default function ConsultationDetail({ id, initial, anon }: { id: number; initial?: Detail; anon?: boolean }) {
  const { data, error, refetch } = useQuery({ queryKey: qk.consultation(id), queryFn: () => api.consultation(id), initialData: initial, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  const missing = error instanceof ApiError && error.status === 404;
  return (
    <div className={styles.wrap}>
      <SupportHeader title="상담 상세" fallback="/mypage/consultations" />
      {loggedOut ? <LoginPrompt text="로그인 후 상담 내역을 확인하실 수 있습니다." /> : missing ? (
        <div className={styles.empty}>상담 내역을 찾을 수 없어요</div>
      ) : !data ? (
        <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : <Body d={data} />}
    </div>
  );
}

function Body({ d }: { d: Detail }) {
  const router = useRouter();
  const qc = useQueryClient();
  const [slot, setSlot] = useState(d.slot);
  const [error, setError] = useState("");
  const rerequest = useMutation({
    mutationFn: () => api.rerequestConsultation(d.id, slot),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["mypage"] });
      router.push("/mypage/consultations");
    },
    onError: (e) => setError(e instanceof ApiError && e.status !== 401 ? e.message : "요청이 만료되었어요. 새로고침 후 다시 시도해주세요."),
  });
  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!d.canRerequest || rerequest.isPending) return;
    if (!slot) { setError("다시 연락받을 시간을 선택해주세요."); return; }
    setError("");
    rerequest.mutate();
  };
  return (
    <form onSubmit={submit}>
      <div className={styles.ctop}>
        <span className={`${styles.chip} ${CHIP[d.statusTone]}`}>{d.statusLabel}</span>
        <div className={styles.cname}>{d.hospitalName}</div>
        <div className={styles.csub}>{d.sub}</div>
      </div>
      {error && <div className={styles.err} role="alert">{error}</div>}

      <div className={styles.timeline}>
        <div className={styles.tl}><span className={styles.dot} /><span><b>상담 신청 완료</b><small>{d.createdLabel}</small></span></div>
        {d.events.map((e, i) => (
          <div key={i} className={styles.tl}><span className={styles.dot} /><span><b>{e.label}</b><small>{e.at}</small></span></div>
        ))}
        <div className={`${styles.tl} ${d.completed ? "" : styles.tlOff}`}><span className={styles.dot} /><span><b>상담 완료</b><small>{d.completed ? d.completedAt : "연락이 닿으면 상담이 진행돼요"}</small></span></div>
      </div>

      {d.hospitalMessage && (
        <>
          <div className={styles.sec}>병원 메시지</div>
          <div className={styles.msg}>{d.hospitalMessage.text}<small>{d.hospitalMessage.meta}</small></div>
        </>
      )}

      {d.showSlots && (
        <>
          <div className={styles.sec}>다시 연락받을 시간</div>
          <div className={styles.slots}>
            {d.slots.map((s) => (
              <button key={s.key} type="button" disabled={!d.canRerequest} tabIndex={d.canRerequest ? 0 : -1}
                className={`${styles.slot} ${slot === s.key ? styles.slotOn : ""} ${d.canRerequest ? "" : styles.slotRo}`}
                aria-pressed={slot === s.key} onClick={() => setSlot(s.key)}>{s.label}</button>
            ))}
          </div>
        </>
      )}

      <div className={styles.foot}>
        {d.hospitalPhone && <a className={styles.btn} href={`tel:${d.hospitalPhone}`}>병원에 전화하기</a>}
        {d.canRerequest ? (
          <button type="submit" className={`${styles.btn} ${styles.btnPri}`} disabled={rerequest.isPending}>다시 요청</button>
        ) : d.completed ? (
          <Link className={`${styles.btn} ${styles.btnPri}`} href={`/booking?id=${d.hospitalId}`}>예약하기</Link>
        ) : d.kakaoUrl ? (
          <a className={`${styles.btn} ${styles.btnPri}`} href={d.kakaoUrl} target="_blank" rel="noopener noreferrer">카카오톡 채널 열기</a>
        ) : null}
      </div>
    </form>
  );
}
