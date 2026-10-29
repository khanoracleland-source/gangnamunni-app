"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk, type CancelWarn } from "@/lib/api";
import { formatDateTime } from "@/lib/booking";
import LoginPrompt from "./LoginPrompt";
import { CANCEL_REASONS } from "./MyBookings";
import styles from "./Booking.module.css";

const won = (n: number) => n.toLocaleString("ko-KR");

/** booking_cancel_request.php: the reason (chosen on 내 예약), the booking, the money, the rules, then the request. */
export default function BookingCancel({ id, reason }: { id: number; reason: string }) {
  const router = useRouter();
  const qc = useQueryClient();
  const { data, error, refetch } = useQuery({ queryKey: qk.bookingCancelInfo(id), queryFn: () => api.bookingCancelInfo(id), staleTime: 0, gcTime: 0, retry: false, refetchOnWindowFocus: false, refetchOnReconnect: false });
  const [choice, custom0] = reason.split(": ", 2);
  const [custom, setCustom] = useState(custom0 ?? "");
  const [agree, setAgree] = useState(false);
  const [policy, setPolicy] = useState(false);
  const [warn, setWarn] = useState<CancelWarn | null>(null);
  const [acked, setAcked] = useState(false);
  const [err, setErr] = useState("");
  const [busy, setBusy] = useState(false);
  const loggedOut = error instanceof ApiError && error.status === 401;
  const gone = error instanceof ApiError && (error.status === 404 || error.status === 409);

  const finalize = async (ack = acked) => {
    if (!data || busy) return;
    if (!agree) { setErr("취소 및 환불 규정에 동의해주세요."); return; }
    if (data.cancelWarn && !ack) { setErr(""); setWarn(data.cancelWarn); return; }
    setErr(""); setBusy(true);
    const full = custom.trim() ? `${choice}${choice ? ": " : ""}${custom.trim()}` : choice;
    try {
      await api.bookingCancel(id, full);
      void qc.invalidateQueries({ queryKey: qk.myBookings });
      router.replace(`/mypage/bookings/${id}/cancel/done`);
    } catch (e) {
      setErr(e instanceof ApiError && e.status !== 500 && !/^\d{3} /.test(e.message) ? e.message : "취소 처리 중 오류가 발생했습니다. 다시 시도해주세요.");
      setBusy(false);
    }
  };

  if (!reason) return <div className={styles.wrap}><div className={styles.empty}>취소 사유를 먼저 선택해주세요. <Link className={styles.retry} href="/mypage/bookings">내 예약으로</Link></div></div>;
  return (
    <div className={styles.wrap}>
      {loggedOut ? <LoginPrompt text="로그인하면 예약을 취소할 수 있습니다." /> : gone ? <div className={styles.empty}>이미 처리된 예약입니다.</div>
        : !data ? <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div> : (
        <>
          <section className={styles.cxSheet}>
            <div className={styles.handle} />
            <h1 className={styles.cxTitle}>예약을 취소하시는 이유가 있으신가요?</h1>
            <p className={styles.cxSub}>보다 나은 서비스를 위해 의견을 들려주세요</p>
            <div className={styles.cxReasons}>
              {CANCEL_REASONS.map((r) => <label key={r} className={styles.cxReason}><input type="radio" checked={choice === r} disabled readOnly /><span>{r}</span></label>)}
            </div>
            <input className={styles.cxCustom} type="text" maxLength={255} value={custom} onChange={(e) => setCustom(e.target.value)} placeholder="직접 입력할게요 (선택)" />
          </section>
          <main className={styles.cxContent}>
            <div className={styles.cxRows}>
              <div className={styles.cxRow}><span className={styles.cxLabel}>예약번호</span><span className={styles.cxValue}>{data.id}</span></div>
              <div className={styles.cxRow}><span className={styles.cxLabel}>날짜/시간</span><span className={styles.cxValue}>{data.dateText}</span></div>
              <div className={styles.cxRow}><span className={styles.cxLabel}>선택시술</span><span className={styles.cxValue}>{data.treatment}</span></div>
              <div className={styles.cxRow}><span className={styles.cxLabel}>병원</span><span className={styles.cxValue}>{data.hospitalName}</span></div>
              <div className={styles.cxRow}><span className={styles.cxLabel}>담당의사</span><span className={styles.cxValue}>{data.doctorName}</span></div>
            </div>
            <div className={styles.cxPerson}><span className={styles.cxLabel}>예약자</span><b>{data.bookerName}</b><span className={styles.cxLabel}>연락처</span><b>{data.phone}</b></div>
            <h2 className={styles.cxSec}>결제 금액을 확인해보세요</h2>
            <div className={styles.money}><span>시술가격</span><span>{won(data.amount)}</span></div>
            <div className={`${styles.money} ${styles.moneyTotal}`}><span>총 결제금액</span><span>{won(data.amount)}</span></div>
            <h2 className={styles.cxSec}>취소/환불 정보를 확인해보세요</h2>
            <div className={styles.money}><span>취소날짜/시간</span><span>{formatDateTime(data.now.slice(0, 10), data.now.slice(11, 16))}</span></div>
            <div className={styles.money}><span>취소 수수료</span><span>0</span></div>
            <div className={`${styles.money} ${styles.moneyRefund}`}><span>총 금액(취소/환불)</span><span>{won(data.refund)}</span></div>
            <section className={styles.cxWarn}>
              <h2>고객님 꼭 알아두세요!</h2>
              <ul>
                <li>예약이 확정된 후 취소 시, 병원 정책에 따라 위약금이 발생할 수 있어요</li>
                <li>포인트 할인 / 이벤트가 적용된 예약은 취소 시 할인 금액이 환수될 수 있어요</li>
                <li>당일 예약 또는 예약 시간 임박 시 취소가 제한될 수 있어요</li>
                <li>예약 취소 후 재예약을 원하시면 다시 시술과 시간을 선택해 주세요</li>
              </ul>
            </section>
            <button type="button" className={styles.cxAgreeTitle} onClick={() => setPolicy(true)}><span>취소 및 환불 규정을 확인해주세요</span><span aria-hidden="true">⌄</span></button>
            <div className={styles.cxAgreeBox}>
              {data.windowClosed ? <strong className={styles.cxClosed}>방문 하루 전 오후 5시가 지나 앱에서 취소할 수 없어요. 병원에 직접 문의해 주세요.</strong> : (
                <>
                  방문 하루 전 오후 5시까지 앱에서 취소하면 100% 환불 대상이에요 (환불은 관리자 확인 후 처리돼요)<br />당일 취소는 병원에 직접 연락해 주세요
                  <label className={styles.cxAgree}><input type="checkbox" checked={agree} onChange={(e) => setAgree(e.target.checked)} /><span>위 내용을 모두 확인하였으며, 취소에 동의해요</span></label>
                </>
              )}
            </div>
            <p className={styles.cxLegal}>서비스 이용에 관한 개인정보 수집 동의<br />서울미녀는 고객 편의를 위해 예약·앱에서 결제 기능을 제공하며, 위 내용에 동의합니다.</p>
            <div className={styles.cxFinal}><span>환불 검토 대상 금액</span><strong>{won(data.refund)}원</strong></div>
            {!data.windowClosed && <button type="button" className={styles.cxBtn} disabled={busy} onClick={() => finalize()}>{busy ? "요청 중..." : "예약 취소 요청"}</button>}
            {err && <p className={styles.cxErr} role="alert">{err}</p>}
          </main>
          {warn && (
            <div className={styles.overlay} onClick={() => setWarn(null)}>
              <section className={styles.sheet} role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
                <div className={styles.handle} />
                <div className={styles.sheetBody}>
                  <span className={styles.sheetIcon} aria-hidden="true">!</span>
                  <h2 className={styles.sheetTitle}>{warn.will_block ? "이번 취소로 예약이 제한돼요" : "취소 횟수를 확인해 주세요"}</h2>
                  <p className={styles.sheetDesc}>
                    {warn.will_block
                      ? `최근 ${warn.months}개월 동안 방문하지 않았거나 취소한 예약이 ${warn.after}회가 돼요. ${warn.available_from}부터 다시 예약할 수 있어요.`
                      : `최근 ${warn.months}개월 동안 방문하지 않았거나 취소한 예약이 ${warn.after}회가 돼요. ${warn.limit}회가 되면 ${warn.months}개월 동안 새 예약을 할 수 없어요.`}
                  </p>
                </div>
                <div className={styles.actions}>
                  <button type="button" className={styles.act} onClick={() => { setAcked(true); setWarn(null); void finalize(true); }}>계속 취소</button>
                  <button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => setWarn(null)}>돌아가기</button>
                </div>
              </section>
            </div>
          )}
          {policy && (
            <div className={styles.overlay} onClick={() => setPolicy(false)}>
              <section className={styles.sheet} role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
                <div className={styles.handle} />
                <div className={styles.sheetBody}>
                  <h2 className={styles.sheetTitle}>취소 및 환불 규정을 확인해주세요</h2>
                  <div className={styles.policyBody}>
                    <p>결제된 예약은 취소 완료 후 관리자 확인을 거쳐 환불 처리됩니다.</p>
                    <ul><li>환불 가능 금액은 실제 결제 내역 기준으로 계산됩니다.</li><li>환불 완료 전까지 관리자 화면에는 환불 안됨 상태로 표시됩니다.</li><li>결제 없이 계속한 예약에는 환불할 결제 금액이 없습니다.</li></ul>
                  </div>
                </div>
                <div className={styles.actions}><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => setPolicy(false)}>확인</button></div>
              </section>
            </div>
          )}
        </>
      )}
    </div>
  );
}
