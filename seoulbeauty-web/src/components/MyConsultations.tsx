"use client";
import { useQuery } from "@tanstack/react-query";
import { api, ApiError, qk, type MyConsultation } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import LoginPrompt from "./LoginPrompt";
import MyHeader from "./MyHeader";
import styles from "./MyPages.module.css";

const CHIP = { danger: styles.chipDanger, muted: styles.chipMuted, dark: styles.chipDark } as const;

/** my_consultations.php: the member's consultation requests (latest 100). Detail / re-request stay in PHP. */
export default function MyConsultations({ initial, anon }: { initial?: { items: MyConsultation[]; anyPhone: string | null }; anon?: boolean }) {
  const php = usePhpBase();
  const { data, error, refetch } = useQuery({ queryKey: qk.myConsultations, queryFn: () => api.myConsultations(), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  return (
    <div className={styles.wrap}>
      <MyHeader title="상담 내역" />
      {loggedOut ? <LoginPrompt /> : !data ? (
        <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : (
        <div className={styles.cwrap}>
          {data.items.length === 0 ? (
            <div className={styles.empty}>상담 내역이 없어요<br />궁금한 병원에 상담을 신청해보세요</div>
          ) : (
            <>
              {data.items.map((it) => {
                const detail = phpUrl(php, `my_consultation_detail.php?id=${it.id}`);
                return (
                  <div key={it.id} className={styles.ccard}>
                    <a href={detail} style={{ display: "block", color: "inherit", textDecoration: "none" }}>
                      <div className={styles.ctop}>
                        <span className={`${styles.chip} ${CHIP[it.statusTone]}`}>{it.statusLabel}</span>
                        <span className={styles.cdate}>{it.dateLabel} 신청</span>
                      </div>
                      <div className={styles.cname}>{it.hospitalName}</div>
                      <div className={styles.csub}>{it.typeLabel}</div>
                      {it.status === "contact_failed" && <div className={styles.cbox}>병원에서 연락드렸지만 받지 않으셨어요.<br />편한 시간을 골라 다시 요청해주세요.</div>}
                      {it.status === "pending" && it.type === "kakao" && <div className={styles.chint}>카카오톡 채널에서 대화를 시작하면 병원이 답변해드려요</div>}
                    </a>
                    {it.status === "contact_failed" && (
                      <div className={styles.cactions}>
                        <a className={`${styles.cbtn} ${styles.cbtnPri}`} href={detail}>다시 요청</a>
                        {it.hospitalPhone && <a className={styles.cbtn} href={`tel:${it.hospitalPhone}`}>병원에 전화</a>}
                      </div>
                    )}
                    {it.status === "completed" && <div className={styles.cactions}><a className={styles.cbtn} href={phpUrl(php, `booking.php?id=${it.hospitalId}`)}>예약하기</a></div>}
                  </div>
                );
              })}
              <div className={styles.notice}>모르는 번호 차단 · 스팸 차단을 켜두셨다면 병원 번호{data.anyPhone ? `(${data.anyPhone})` : ""}를 저장해주세요. 연락이 닿지 않으면 이곳과 앱 알림으로 알려드려요.</div>
            </>
          )}
        </div>
      )}
    </div>
  );
}
