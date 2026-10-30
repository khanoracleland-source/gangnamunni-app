"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import LoginPrompt from "./LoginPrompt";
import styles from "./BookingPayment.module.css";

const won = (n: number) => Math.max(0, n).toLocaleString("ko-KR");
const PAY_NOTICES: Record<string, string> = {
  PAY_PROCESS_CANCELED: "결제를 취소했습니다. 청구된 금액은 없습니다.",
  USER_CANCEL: "결제를 취소했습니다. 청구된 금액은 없습니다.",
  PAY_PROCESS_ABORTED: "결제가 중단되었습니다. 청구된 금액은 없으니 다시 시도해주세요.",
  REJECT_CARD_COMPANY: "카드사에서 결제를 거절했습니다. 다른 카드나 결제 수단을 이용해주세요.",
  NOT_FOUND_PAYMENT_SESSION: "결제 시간이 초과되었습니다. 청구된 금액은 없으니 다시 결제해주세요.",
  AMOUNT_MISMATCH: "결제 금액이 변경되어 결제를 진행하지 않았습니다. 청구된 금액은 없으니 금액을 확인한 뒤 다시 결제해주세요.",
  POINT_HOLD_EXPIRED: "포인트 적용 시간이 지나 결제를 진행하지 않았습니다. 청구된 금액은 없으니 다시 결제해주세요.",
  BOOKING_NOT_CONFIRMED: "예약 상태가 변경되어 결제를 진행하지 않았습니다. 청구된 금액은 없습니다.",
  ORDER_STATE: "이 주문은 지금 결제할 수 없습니다. 청구된 금액은 없습니다.",
  UNRESOLVED: "결제 결과를 확인하고 있습니다. 다시 결제하지 말고 잠시 후 ‘이미 결제했다면 상태 확인’을 눌러주세요.",
};
const METHODS: [string, string][] = [["bank", "계좌이체"], ["card", "신용/체크카드"], ["naverpay", "네이버페이"], ["kakaopay", "카카오페이"], ["tosspay", "토스페이"]];

/**
 * booking_payment.php. The live PG checkout (Toss) is not ported: while PG is on, "결제하기" opens the PHP page;
 * what works here is the order, the summary and "결제 없이 계속하기".
 */
export default function BookingPayment({ id, payError }: { id: number; payError: string }) {
  const router = useRouter();
  const php = usePhpBase();
  const qc = useQueryClient();
  const { data, error, refetch, isFetching } = useQuery({ queryKey: qk.bookingPayment(id), queryFn: () => api.bookingPayment(id), staleTime: 0, gcTime: 0, retry: false, refetchOnWindowFocus: false, refetchOnReconnect: false });
  const [agree, setAgree] = useState(false);
  const [agreeErr, setAgreeErr] = useState(false);
  const [visitorOther, setVisitorOther] = useState(true);
  const [visitorName, setVisitorName] = useState("");
  const [visitorPhone, setVisitorPhone] = useState("");
  const [visitorDone, setVisitorDone] = useState("");
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState("");
  const loggedOut = error instanceof ApiError && error.status === 401;
  const missing = error instanceof ApiError && error.status === 404;
  const notice = payError ? PAY_NOTICES[payError] ?? "결제가 승인되지 않았습니다. 청구된 금액은 없으니 다시 시도하거나 다른 결제 수단을 이용해주세요." : "";

  const preview = async () => {
    if (!data || busy) return;
    if (!agree) { setAgreeErr(true); return; }
    setAgreeErr(false); setBusy(true); setResult("");
    try {
      const r = await api.bookingPaymentPreview(id);
      void qc.invalidateQueries({ queryKey: ["mypage"] });
      router.replace(r.redirect);
      return;
    } catch (e) { setResult(e instanceof ApiError && !/^\d{3} /.test(e.message) ? e.message : "임시 결제 완료 처리에 실패했습니다. 잠시 후 다시 시도해주세요."); }
    setBusy(false);
  };
  const check = async () => {
    const r = await refetch();
    const o = r.data?.order;
    setResult(!o ? "결제 주문이 아직 없습니다." : ["paid", "partially_refunded"].includes(o.status) ? "결제가 확인되었습니다." : o.status === "pending" ? "아직 결제가 확인되지 않았습니다." : `주문 상태: ${o.status}`);
  };
  const toMy = <Link className={styles.statusLink} href="/mypage/bookings">내 예약으로</Link>;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <button type="button" className={styles.back} aria-label="뒤로" onClick={() => router.push("/mypage/bookings")} />
        <h1 className={styles.title}>결제를 진행해볼까요?</h1>
      </header>
      <main className={styles.content}>
        {loggedOut ? <LoginPrompt text="로그인하면 결제를 진행할 수 있습니다." /> : missing ? <><div className={`${styles.alert} ${styles.alertErr}`}>예약을 찾을 수 없거나 접근 권한이 없습니다.</div>{toMy}</>
          : !data ? <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div> : (
          <>
            <section className={styles.section}>
              <h2 className={styles.sectionTitle}>예약 정보를 확인해보세요</h2>
              <div className={styles.summary}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <div className={styles.logo}>{data.booking.logo ? <img src={data.booking.logo} alt={data.booking.hospitalName} /> : <span>toxnfill</span>}</div>
                <div>
                  <div className={styles.hospital}>{data.booking.hospitalName || "병원명"}</div>
                  <div className={styles.address}>{data.booking.hospitalArea && <><span>{data.booking.hospitalArea}</span><span className={styles.bar}>|</span></>}<span>{data.booking.hospitalAddress || "주소 정보 없음"}</span></div>
                  <div className={styles.treatment}>{data.booking.treatment}</div>
                  <div className={styles.vat}>VAT 포함 <span className={styles.old}>{won(data.gross)}원</span></div>
                  <div className={styles.price}>{won(data.gross)}<small>원</small></div>
                </div>
              </div>
              <hr className={styles.line} />
              <div className={styles.infoRow}><div className={styles.label}>예약자</div><div className={styles.value}>{[data.booking.bookerName, data.booking.phone].filter(Boolean).join(" / ")}</div></div>
              <label className={styles.checkRow}><input type="checkbox" className={styles.checkbox} checked={visitorOther} onChange={(e) => { setVisitorOther(e.target.checked); setVisitorDone(""); }} /><span>다른 사람이 방문해요</span></label>
              {visitorOther && !visitorDone && (
                <div className={styles.visitorFields}>
                  <div className={styles.field}><label htmlFor="bpVisitorName">방문자 성함이 어떻게 되시나요?</label><input id="bpVisitorName" type="text" placeholder="방문자 이름을 입력해주세요" autoComplete="name" value={visitorName} onChange={(e) => setVisitorName(e.target.value)} /></div>
                  <div className={styles.field}><label htmlFor="bpVisitorPhone">방문하실 분의 휴대폰 번호를 알려주세요</label><input id="bpVisitorPhone" type="tel" inputMode="numeric" maxLength={13} placeholder="병원과 연락 가능한 번호를 입력해주세요" autoComplete="tel" value={visitorPhone} onChange={(e) => setVisitorPhone(e.target.value)} /></div>
                  <button type="button" className={styles.visitorConfirm} onClick={() => { if (visitorName.trim() && visitorPhone.replace(/\D/g, "").length >= 10) setVisitorDone(`${visitorName.trim()} / ${visitorPhone.trim()}`); }}>선택 완료</button>
                </div>
              )}
              {visitorOther && visitorDone && <div className={styles.infoRow}><div className={styles.label}>방문자</div><div className={styles.value}>{visitorDone}</div></div>}
              <div className={`${styles.infoRow} ${styles.scheduleRow}`}><div className={styles.label}>예약 일정</div><div><div className={styles.value}>{data.booking.dateText}</div><div className={styles.redNote}>병원에서 확인 후 일정이 확정돼요</div></div></div>
            </section>

            {data.isPaid ? <><div className={`${styles.alert} ${styles.alertDone}`}>{data.order?.isTest ? "테스트 결제가 완료되었습니다. 실제 결제가 아닙니다." : "결제가 완료된 예약입니다."}</div>{toMy}</>
              : data.order && ["refunded", "cancelled", "failed"].includes(data.order.status) ? <><div className={`${styles.alert} ${styles.alertWarn}`}>{data.order.status === "refunded" ? "환불이 완료된 결제입니다." : "이 주문으로 결제를 진행할 수 없습니다."}</div>{toMy}</>
              : !data.isConfirmed ? <><div className={`${styles.alert} ${styles.alertWarn}`}>병원이 예약을 확정한 뒤에 결제할 수 있습니다. 확정되면 다시 결제를 진행해주세요.</div>{toMy}</>
              : !data.ledgerReady || data.orderError || !data.order ? <><div className={`${styles.alert} ${styles.alertErr}`}>{data.orderError || "결제 준비가 완료되지 않았습니다. 잠시 후 다시 시도하거나 병원에 문의해주세요."}</div>{toMy}</>
              : (
                <>
                  <section className={styles.section}>
                    <h2 className={styles.sectionTitle}>적용 가능한 할인을 확인해보세요</h2>
                    <div className={styles.pointLabel}>포인트</div>
                    <div className={styles.pointBox}><input className={styles.pointInput} disabled inputMode="numeric" value={won(data.order.pointsUsed)} readOnly aria-label="사용할 포인트" /><span className={styles.pointUnit}>P</span><button type="button" className={styles.pointMax} disabled>최대적용</button></div>
                    <div className={styles.pointHelp}>사용가능 <strong>{won(data.points.max)}P</strong> / 보유 {won(data.points.balance)}P</div>
                    <div className={styles.pointNote}>포인트 사용 및 결제 적립은 준비 중입니다. 현재 결제 금액에는 적용되지 않습니다.</div>
                  </section>
                  <section className={styles.section}>
                    <h2 className={styles.sectionTitle}>어떤 결제 수단을 이용하시겠어요?</h2>
                    {!data.liveAvailable && (
                      <div className={styles.methodNotice}>
                        <p>실제 결제는 준비 중입니다. 현재 결제 수단을 선택할 수 없습니다.</p>
                        {data.previewAvailable && <p>결제 수단을 선택하지 않아도 아래 ‘결제 없이 계속하기’로 진행할 수 있어요.</p>}
                        <p>결제 관련 문의는 병원으로 연락해주세요.</p>
                      </div>
                    )}
                    <div className={styles.methodGrid}>
                      {METHODS.map(([k, label]) => <button type="button" key={k} className={styles.method} disabled>{label}</button>)}
                    </div>
                  </section>
                  <section className={styles.section}>
                    <h2 className={styles.sectionTitle}>결제 금액을 확인해보세요</h2>
                    <div className={styles.amountRow}><span>시술 금액</span><span className={styles.right}>{won(data.order.gross)}원</span></div>
                    <div className={styles.amountRow}><span>할인 <span className={styles.badge}>0%</span></span><span className={styles.right}>-0원</span></div>
                    <div className={styles.amountRow}><span>포인트 사용 금액</span><span className={styles.right}>-{won(data.order.pointsUsed)}원</span></div>
                    <div className={styles.totalRow}><span className={styles.left}>총 결제 금액 <small>(VAT포함)</small></span><span className={styles.right}>{won(data.order.amount)}원</span></div>
                    {data.points.v2 && <div className={styles.amountRow}><span>예상 적립 포인트</span><span className={styles.right}>{won(data.points.expected)} P</span></div>}
                  </section>
                  <section className={`${styles.section} ${styles.rules}`}>
                    <h2 className={styles.sectionTitle}>취소 및 환불 규정을 확인해주세요</h2>
                    <div className={styles.rulesBody}>
                      <ul>
                        <li>예약 취소와 결제 환불은 별도로 처리됩니다.</li>
                        <li>예약 변경·취소는 예약 내역에서 요청할 수 있습니다.</li>
                        <li>이미 결제한 예약의 환불은 운영자가 결제 내역과 병원의 환불 조건을 확인한 후 처리합니다.</li>
                        <li>실제 환불이 확인되면 결제 내역에 환불 금액과 상태가 반영됩니다.</li>
                        <li>결제 없이 계속한 예약은 실제 청구가 없어 환불 대상 금액이 없습니다.</li>
                      </ul>
                    </div>
                    <label className={styles.agree}><input type="checkbox" className={styles.checkbox} checked={agree} onChange={(e) => { setAgree(e.target.checked); if (e.target.checked) setAgreeErr(false); }} /> 위 내용을 모두 확인했어요</label>
                    {agreeErr && <p className={styles.agreeErr} role="alert">취소 및 환불 규정을 확인하고 동의해주세요.</p>}
                    <div className={styles.legal}>서비스 이용에 관한 개인정보 수집 동의<br />개인정보 및 민감정보 수집 이용, 개인정보 제3자 제공 동의<br /><br />서울미녀는 고객 편의를 위해 예약·앱에서 결제 기능을 제공합니다. 이 기능에 대해 의료기관으로부터 대가를 받지 않습니다.<br />거래의 당사자는 고객과 병원이며, 서울미녀는 이를 중개하지 않습니다. 위 내용을 확인하였으며, 결제에 동의합니다.</div>
                  </section>
                  {data.preview && <div className={`${styles.alert} ${styles.alertWarn}`}>결제 없이 계속한 예약입니다. 실제 결제 내역은 없습니다.</div>}
                  {notice && <div className={`${styles.alert} ${styles.alertWarn}`} role="alert">{notice}</div>}
                  {result && <div className={styles.result} role="status">{result}</div>}
                  <button type="button" className={styles.visitorConfirm} onClick={check} disabled={isFetching}>이미 결제했다면 상태 확인</button>
                  <div className={styles.sticky}>
                    <div className={styles.stickyTotal}><div className={styles.left}>총 결제 금액 <small>(VAT포함)</small></div><div className={styles.right}><span className={styles.old}>{won(data.order.gross)}원</span><span className={styles.new}>{won(data.order.amount)}원</span></div></div>
                    {(data.liveAvailable || !data.previewAvailable) && (data.liveAvailable
                      ? <a className={styles.payBtn} href={phpUrl(php, `booking_payment.php?booking_id=${id}&php=1`)}>결제하기</a>
                      : <button type="button" className={styles.payBtn} disabled>온라인 결제 준비 중</button>)}
                    {data.previewAvailable && (
                      <>
                        <button type="button" className={`${styles.previewBtn} ${data.liveAvailable ? "" : styles.previewPri}`} disabled={busy} onClick={preview}>{busy ? "처리 중…" : "결제 없이 계속하기"}</button>
                        <p className={styles.checkoutNote}>실제 청구 없이 진행하며, 결제·정산 내역에 포함되지 않습니다.</p>
                      </>
                    )}
                  </div>
                </>
              )}
          </>
        )}
      </main>
    </div>
  );
}
