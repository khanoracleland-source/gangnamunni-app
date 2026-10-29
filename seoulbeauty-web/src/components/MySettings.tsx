"use client";
import { useRef, useState } from "react";
import Link from "next/link";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk, type NotificationSettings, type SettingKey } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import LoginPrompt from "./LoginPrompt";
import MyHeader from "./MyHeader";
import styles from "./MyPages.module.css";

const NOTI: [SettingKey, string][] = [
  ["noti_general", "일반 알림"], ["noti_booking", "예약 알림"], ["noti_event", "이벤트/추첨 알림"], ["noti_promo", "프로모션 알림"], ["noti_push", "푸시 알림"], ["noti_message", "쪽지 알림"],
];
const MKT: [SettingKey, string][] = [["mkt_push", "푸시"], ["mkt_sms", "SMS"], ["mkt_email", "이메일"], ["mkt_night_push", "야간 푸시 (오후 9시 - 오전 8시)"]];
type Data = { settings: NotificationSettings };

/** notification_settings.php: the ten toggles and 로그아웃. 회원 탈퇴 (Kakao unlink + data removal) stays in PHP. */
export default function MySettings({ initial, anon }: { initial?: Data; anon?: boolean }) {
  const php = usePhpBase();
  const qc = useQueryClient();
  const { data, error, refetch } = useQuery({ queryKey: qk.notificationSettings, queryFn: () => api.notificationSettings(), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  const [busy, setBusy] = useState<SettingKey | null>(null);
  const [toast, setToast] = useState("");
  const [sheet, setSheet] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const say = (m: string) => { setToast(m); if (timer.current) clearTimeout(timer.current); timer.current = setTimeout(() => setToast(""), 2200); };

  const toggle = async (key: SettingKey, on: boolean) => {
    if (busy) return;
    setBusy(key);
    const set = (v: boolean) => qc.setQueryData<Data>(qk.notificationSettings, (old) => (old ? { settings: { ...old.settings, [key]: v ? 1 : 0 } } : old));
    set(on);
    try { await api.setNotificationSetting(key, on); say("저장되었습니다"); } catch { set(!on); say("설정 저장에 실패했습니다. 다시 시도해주세요."); } finally { setBusy(null); }
  };
  const group = (title: string, items: [SettingKey, string][]) => data && (
    <section className={styles.stSection}>
      <div className={styles.stLabel}>{title}</div>
      {items.map(([key, label]) => (
        <div key={key} className={styles.stItem}>
          <span id={`st-${key}`}>{label}</span>
          <input type="checkbox" role="switch" className={styles.stSwitch} aria-labelledby={`st-${key}`} checked={data.settings[key] === 1} disabled={busy === key} onChange={(e) => toggle(key, e.target.checked)} />
        </div>
      ))}
    </section>
  );

  return (
    <div className={styles.wrap}>
      <MyHeader title="알림" />
      {loggedOut ? <LoginPrompt text="로그인하면 알림 설정을 변경할 수 있습니다." /> : (
        <>
          <section className={styles.stSection}>
            <div className={styles.stLabel}>서비스 안내</div>
            <Link className={`${styles.stItem} ${styles.stLink}`} href="/qna"><span style={{ color: "#AC1830" }}>제휴 및 광고문의</span></Link>
            <Link className={`${styles.stItem} ${styles.stLink}`} href="/register-terms"><span>이용약관 및 개인정보처리 방침</span></Link>
          </section>
          {!data ? (
            <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
          ) : (
            <>
              {group("알림 설정", NOTI)}
              {group("마케팅 정보 수신", MKT)}
              <section className={styles.stSection}>
                <div className={styles.stLabel}>계정 관리</div>
                <button type="button" className={`${styles.stItem} ${styles.stLink}`} onClick={() => setSheet(true)}><span>로그아웃</span><span aria-hidden="true">›</span></button>
                <a className={`${styles.stItem} ${styles.stLink}`} style={{ color: "#AC1830" }} href={phpUrl(php, "notification_settings.php?php=1#withdraw")}><span>회원 탈퇴</span><span aria-hidden="true">›</span></a>
              </section>
            </>
          )}
        </>
      )}
      {sheet && (
        <div className={styles.pfModal} role="presentation" onClick={() => setSheet(false)}>
          <div className={styles.pfModalBox} role="dialog" aria-modal="true" aria-labelledby="st-logout" onClick={(e) => e.stopPropagation()}>
            <h2 id="st-logout">잠시 로그아웃하시겠어요?</h2>
            <button type="button" className={styles.pfSave} onClick={() => window.location.assign(phpUrl(php, "bbs/logout.php?url=%2Findex.php"))}>로그아웃</button>
            <button type="button" className={styles.pfDiscard} onClick={() => setSheet(false)}>아니오</button>
          </div>
        </div>
      )}
      {toast && <div className={styles.ntToast} role="status" aria-live="polite">{toast}</div>}
    </div>
  );
}
