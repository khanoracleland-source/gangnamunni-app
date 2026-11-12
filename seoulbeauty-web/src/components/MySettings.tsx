"use client";
import Loading from "./Loading";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import Image from "next/image";
import bellIcon from "@/assets/icons/bell.png";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk, type NotificationSettings, type SettingKey } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import { announceLogout, inApp, onFlutterEvent, openNotificationSettings, requestNotificationStatus } from "@/lib/bridge";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import LoginPrompt from "./LoginPrompt";
import { useToast } from "./Toast";
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

/** 07-01 설정 (notification_settings.php): the eight toggles, 로그아웃 (PHP ends the session, then lands on /next/ and tells the app) and 회원 탈퇴 (07-08 sheet first: a social member leaves right there, the others go on to /mypage/withdraw for the password). */
export default function MySettings({ initial, anon }: { initial?: Data; anon?: boolean }) {
  const php = usePhpBase();
  const qc = useQueryClient();
  const { data, error, refetch } = useQuery({ queryKey: qk.notificationSettings, queryFn: () => api.notificationSettings(), initialData: initial, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  const [busy, setBusy] = useState<SettingKey | null>(null);
  const [sheet, setSheet] = useState(false);
  const sheetRef = useRef<BottomSheetHandle>(null);
  const [leave, setLeave] = useState(false);
  const [leaving, setLeaving] = useState(false);
  const leaveRef = useRef<BottomSheetHandle>(null);
  const router = useRouter();
  const { data: profile } = useQuery({ queryKey: qk.myProfile, queryFn: () => api.myProfile(), retry: false, enabled: !anon });
  const say = useToast();

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
  /* The API's own session first (it may be off or already gone: either way the PHP logout below still runs), then the PHP logout
     that ends the PHP session, the remember cookies and the app's tokens and comes back to the start page. */
  const signOut = async () => {
    try { await api.logout(); } catch { /* the PHP logout removes the same session (extend/sb_nest_session.extend.php) */ }
    window.location.assign(phpUrl(php, "bbs/logout.php?url=%2Fnext%2F"));
  };
  /* 07-08 탈퇴 sheet: social members have no password, so they leave right here; the others go on to the form (reason, password). */
  const leaveNow = async () => {
    if (leaving) return;
    setLeaving(true);
    try {
      /* the profile may still be loading (or have failed): ask for it now, and let the form decide when it cannot be told */
      let me = profile;
      if (!me) { try { me = await qc.fetchQuery({ queryKey: qk.myProfile, queryFn: () => api.myProfile() }); } catch { /* the form handles it */ } }
      if (me?.social !== true) { router.push("/mypage/withdraw"); setLeaving(false); return; }
      await api.withdraw({ reason: "", detail: "", password: "" });
      announceLogout();
      window.location.replace("/next/mypage/withdraw/done?app_logout=1");
    } catch (e) {
      setLeaving(false);
      leaveRef.current?.close();
      say(e instanceof ApiError && e.status !== 401 && typeof e.body?.message === "string" && e.body.message ? e.body.message : "탈퇴 처리에 실패했습니다. 잠시 후 다시 시도해주세요.");
    }
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
                <button type="button" className={styles.row} onClick={() => setLeave(true)}><span>회원 탈퇴</span></button>
              </section>
            </>
          )}
        </div>
      )}
      <BottomSheet ref={sheetRef} open={sheet} onClose={() => setSheet(false)} label="로그아웃">
        <div className={styles.sheet}>
          <h2 className={styles.sheetTitle}>잠시 로그아웃하시겠어요?</h2>
          <div className={styles.sheetBtns}>
            <button type="button" className={styles.sheetBtn} onClick={signOut}>로그아웃</button>
            <button type="button" className={`${styles.sheetBtn} ${styles.sheetBtnPri}`} onClick={() => sheetRef.current?.close()}>아니오</button>
          </div>
        </div>
      </BottomSheet>
      <BottomSheet ref={leaveRef} open={leave} onClose={() => setLeave(false)} label="회원 탈퇴" dismissible={!leaving}>
        <div className={styles.sheet}>
          <div className={styles.sheetHead}>
            <h2 className={styles.sheetTitle}>{profile?.name || profile?.nick || "회원"}님 정말 떠나시겠어요?</h2>
            <p className={styles.sheetDesc}>탈퇴 후에는 일부 정보가 복구되지 않을 수 있어요<br />더 나은 경험을 제공할 수 있도록 계속 노력하겠습니다</p>
          </div>
          <div className={styles.sheetBtns}>
            <button type="button" className={styles.sheetBtn} disabled={leaving} onClick={leaveNow}>{leaving ? "처리 중…" : "탈퇴하기"}</button>
            <button type="button" className={`${styles.sheetBtn} ${styles.sheetBtnPri}`} disabled={leaving} onClick={() => leaveRef.current?.close()}>아니오</button>
          </div>
        </div>
      </BottomSheet>
    </div>
  );
}
