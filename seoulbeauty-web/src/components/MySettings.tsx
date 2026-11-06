"use client";
import Loading from "./Loading";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import Image from "next/image";
import bellIcon from "@/assets/icons/bell.png";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk, type NotificationSettings, type SettingKey } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import { inApp, onFlutterEvent, openNotificationSettings, requestNotificationStatus } from "@/lib/bridge";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import LoginPrompt from "./LoginPrompt";
import MyHeader from "./MyHeader";
import styles from "./MySettings.module.css";

const NOTI: [SettingKey, string, string][] = [
  ["noti_booking", "예약 · 결제 알림", "예약 접수 · 병원 확인 · 결제 요청 · 확정 · 취소"],
  ["noti_visit", "방문 전 알림", "시술 전날 방문 일정 안내"],
  ["noti_community", "커뮤니티 알림", "내 글에 달린 댓글 · 좋아요"],
  ["noti_review", "포인트 · 후기 알림", "후기 작성 요청 · 포인트 적립 · 소멸 예정"],
];
const MKT: [SettingKey, string, string?][] = [["mkt_push", "푸시"], ["mkt_sms", "SMS"], ["mkt_email", "이메일"], ["mkt_night_push", "야간 푸시(오후 9시~오전 8시)"]];
/** the name inside "마케팅 정보(…) 수신에 동의했어요" (07-03 / 07-04) */
const MKT_NAME: Partial<Record<SettingKey, string>> = { mkt_push: "푸시", mkt_sms: "SMS", mkt_email: "이메일", mkt_night_push: "야간 푸시" };
type Data = { settings: NotificationSettings };

/** "2026.10.19" in Korea time, whatever the device clock says */
const today = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Seoul", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date()).replaceAll("-", ".");

/** 07-01 설정 (notification_settings.php): the eight toggles, 로그아웃 (PHP ends the session, then lands on /next/ and tells the app) and 회원 탈퇴 (own page). */
export default function MySettings({ initial, anon }: { initial?: Data; anon?: boolean }) {
  const php = usePhpBase();
  const qc = useQueryClient();
  const { data, error, refetch } = useQuery({ queryKey: qk.notificationSettings, queryFn: () => api.notificationSettings(), initialData: initial, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  const [busy, setBusy] = useState<SettingKey | null>(null);
  const [note, setNote] = useState("");
  const [sheet, setSheet] = useState(false);
  const sheetRef = useRef<BottomSheetHandle>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const say = (m: string) => { setNote(m); if (timer.current) clearTimeout(timer.current); timer.current = setTimeout(() => setNote(""), 3000); };
  useEffect(() => () => { if (timer.current) clearTimeout(timer.current); }, []);

  /* 07-01 "기기 알림이 꺼져 있어요": only inside the app, when the OS permission is off. Re-asked whenever the page comes back
     into view (the app also re-sends it on resume), so the banner goes away after the member turns notifications on. */
  const [deviceOff, setDeviceOff] = useState(false);
  useEffect(() => {
    if (!inApp()) return;
    const off = onFlutterEvent("notificationStatus", (d) => setDeviceOff(typeof d === "object" && d !== null && (d as { enabled?: unknown }).enabled === false));
    const ask = () => { if (document.visibilityState === "visible") requestNotificationStatus(); };
    ask();
    document.addEventListener("visibilitychange", ask);
    return () => { off(); document.removeEventListener("visibilitychange", ask); };
  }, []);

  const toggle = async (key: SettingKey, on: boolean) => {
    if (busy) return;
    setBusy(key);
    const set = (v: boolean) => qc.setQueryData<Data>(qk.notificationSettings, (old) => (old ? { settings: { ...old.settings, [key]: v ? 1 : 0 } } : old));
    set(on);
    try {
      await api.setNotificationSetting(key, on);
      const name = MKT_NAME[key];
      if (name) say(`${today()} 마케팅 정보(${name}) 수신${on ? "에 동의했어요" : "을 거부했어요"}`);
    } catch (e) { set(!on); say(e instanceof ApiError && e.status === 503 && typeof e.body?.message === "string" && e.body.message ? e.body.message : "설정 저장에 실패했어요. 다시 시도해주세요"); } finally { setBusy(null); }
  };
  const group = (title: string, items: [SettingKey, string, string?][]) => data && (
    <section className={styles.card}>
      <div className={styles.label}>{title}</div>
      {items.map(([key, label, sub]) => (
        <div key={key} className={styles.row}>
          <span className={styles.txt}><span id={`st-${key}`}>{label}</span>{sub && <span className={styles.sub}>{sub}</span>}</span>
          <input type="checkbox" role="switch" className={styles.switch} aria-labelledby={`st-${key}`} checked={data.settings[key] === 1} disabled={busy === key} onChange={(e) => toggle(key, e.target.checked)} />
        </div>
      ))}
    </section>
  );

  return (
    <div className={styles.page}>
      <div className={styles.top}><MyHeader title="설정" right={null} /></div>
      {loggedOut ? <LoginPrompt text="로그인하면 설정을 변경할 수 있습니다." /> : (
        <div className={styles.body}>
          {deviceOff && (
            <div className={styles.device} role="status">
              <Image src={bellIcon} alt="" width={24} height={24} />
              <div className={styles.deviceTxt}>
                <strong>기기 알림이 꺼져 있어요</strong>
                <span>휴대폰 설정에서 알림을 켜야 예약 알림을 받을 수 있어요</span>
              </div>
              <button type="button" className={styles.deviceBtn} onClick={openNotificationSettings}>설정 열기</button>
            </div>
          )}
          <section className={styles.card}>
            <div className={styles.label}>서비스 안내</div>
            <Link className={`${styles.row} ${styles.red}`} href="/qna"><span>제휴 및 광고문의</span></Link>
            <Link className={styles.row} href="/terms"><span>이용약관</span></Link>
            <Link className={styles.row} href="/privacy"><span>개인정보처리 방침</span></Link>
          </section>
          {!data ? (
            <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : <Loading />}</div>
          ) : (
            <>
              {group("알림설정", NOTI)}
              <p className={styles.hint}>끄면 결제 요청을 놓쳐 예약이 자동 취소될 수 있어요</p>
              {group("마케팅 정보 수신", MKT)}
              <section className={styles.card}>
                <div className={styles.label}>계정관리</div>
                <button type="button" className={styles.row} onClick={() => setSheet(true)}><span>로그아웃</span></button>
                <Link className={styles.row} href="/mypage/withdraw"><span>회원 탈퇴</span></Link>
              </section>
            </>
          )}
        </div>
      )}
      <BottomSheet ref={sheetRef} open={sheet} onClose={() => setSheet(false)} label="로그아웃">
        <div className={styles.sheet}>
          <h2 className={styles.sheetTitle}>잠시 로그아웃하시겠어요?</h2>
          <div className={styles.sheetBtns}>
            <button type="button" className={styles.sheetBtn} onClick={() => window.location.assign(phpUrl(php, "bbs/logout.php?url=%2Fnext%2F"))}>로그아웃</button>
            <button type="button" className={`${styles.sheetBtn} ${styles.sheetBtnPri}`} onClick={() => sheetRef.current?.close()}>아니오</button>
          </div>
        </div>
      </BottomSheet>
      {note && (
        <div className={styles.note} role="status" aria-live="polite">
          <span>{note}</span>
          <button type="button" className={styles.noteX} onClick={() => setNote("")} aria-label="닫기">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>
      )}
    </div>
  );
}
