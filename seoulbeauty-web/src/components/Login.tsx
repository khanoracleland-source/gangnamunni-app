"use client";
import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { ApiError, api, assetUrl } from "@/lib/api";
import { useMe } from "@/lib/me";
import { phpUrl, usePhpBase } from "@/lib/php";
import styles from "./Login.module.css";
import kakaoIcon from "@/assets/icons/login-kakao.png";
import googleIcon from "@/assets/icons/login-google.png";
import appleIcon from "@/assets/icons/login-apple.png";
import mailIcon from "@/assets/icons/login-mail.png";

const subscribeNoop = () => () => {};

/** 02-01/02-02 reCAPTCHA notice + bottom sheet. Turn off until the Next/Nest login actually verifies reCAPTCHA (today only the PHP login does). */
const SHOW_RECAPTCHA_NOTICE = true;

/** auth/login.php: social buttons (still PHP flows) + e-mail sign-in. The session is created by PHP through the Nest bridge. */
export default function Login() {
  const php = usePhpBase();
  const me = useMe();
  const params = useSearchParams();
  const url = params.get("url") ?? "";
  const notice = params.get("pw_changed") === "1" ? "비밀번호가 변경되었습니다. 보안을 위해 모든 기기에서 로그아웃되었습니다. 새 비밀번호로 다시 로그인해주세요." : "";
  const SOCIAL_ERR: Record<string, string> = {
    unavailable: "현재 이 소셜 로그인은 사용할 수 없습니다. 이메일로 로그인해주세요.",
    too_many: "시도가 너무 많습니다. 잠시 후 다시 시도해주세요.",
    state: "로그인 시간이 만료되었습니다. 다시 시도해주세요.",
    denied: "소셜 로그인이 취소되었습니다.",
    unverified: "이메일 인증이 완료되지 않은 계정입니다. 인증 메일을 확인해주세요.",
    exists: "이미 연결 또는 가입된 소셜 계정입니다. 기존 계정으로 로그인해주세요.",
    email_exists: "이미 가입된 이메일입니다. 이메일로 로그인해주세요.",
    blocked: "이용이 제한된 계정입니다. 고객센터에 문의해주세요.",
    failed: "소셜 로그인에 실패했습니다. 잠시 후 다시 시도해주세요.",
  };
  const socialError = SOCIAL_ERR[params.get("social_error") ?? ""] ?? "";
  // Apple sign-in is iOS-only for now: hidden on Android (and until the user agent is known, so it never flashes there)
  const showApple = useSyncExternalStore(subscribeNoop, () => !/Android/i.test(navigator.userAgent), () => false);
  const opts = useQuery({ queryKey: ["login-options"], queryFn: () => api.loginOptions(), staleTime: 60_000 });
  const [open, setOpen] = useState(!!params.get("pw_changed"));
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [sheet, setSheet] = useState(false);
  const sheetRef = useRef<HTMLDivElement>(null);
  const dimRef = useRef<HTMLDivElement>(null);
  const drag = useRef<{ y: number; t: number; dy: number; id: number } | null>(null);

  /** slide the sheet down, then unmount */
  const closeSheet = () => {
    const el = sheetRef.current, dim = dimRef.current;
    if (!el) { setSheet(false); return; }
    el.style.transition = "transform .24s cubic-bezier(.4,0,1,1)";
    el.style.transform = "translateY(100%)";
    if (dim) { dim.style.transition = "opacity .24s"; dim.style.opacity = "0"; }
    window.setTimeout(() => setSheet(false), 230);
  };
  const dragStart = (e: React.PointerEvent) => {
    drag.current = { y: e.clientY, t: e.timeStamp, dy: 0, id: e.pointerId };
    e.currentTarget.setPointerCapture(e.pointerId);
    if (sheetRef.current) { sheetRef.current.style.transition = "none"; sheetRef.current.style.animation = "none"; }
  };
  const dragMove = (e: React.PointerEvent) => {
    const d = drag.current; if (!d || d.id !== e.pointerId) return;
    d.dy = Math.max(0, e.clientY - d.y);
    if (sheetRef.current) sheetRef.current.style.transform = `translateY(${d.dy}px)`;
    if (dimRef.current) dimRef.current.style.opacity = String(Math.max(0.2, 1 - d.dy / 400));
  };
  const dragEnd = (e: React.PointerEvent) => {
    const d = drag.current; if (!d || d.id !== e.pointerId) return;
    drag.current = null;
    const h = sheetRef.current?.offsetHeight ?? 300;
    const v = d.dy / Math.max(1, e.timeStamp - d.t); // px/ms
    if (d.dy > h * 0.3 || (v > 0.5 && d.dy > 24)) { closeSheet(); return; }
    if (sheetRef.current) { sheetRef.current.style.transition = "transform .22s cubic-bezier(.2,.8,.2,1)"; sheetRef.current.style.transform = "translateY(0)"; }
    if (dimRef.current) { dimRef.current.style.transition = "opacity .22s"; dimRef.current.style.opacity = "1"; }
  };
  const sending = useRef(false);
  const router = useRouter();
  const home = "/next"; // the Next.js home (full load after sign-in so every page sees the new PHP session cookie)
  /** ← : back to the page the visitor came from; with no history (opened directly) go to the Next home. */
  const goBack = () => { if (window.history.length > 1) router.back(); else router.push("/"); };

  useEffect(() => {
    if (!sheet) return;
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape") closeSheet(); };
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    window.addEventListener("keydown", onKey);
    return () => { document.body.style.overflow = prev; window.removeEventListener("keydown", onKey); };
  }, [sheet]);

  useEffect(() => { if (me.data?.loggedIn && !sending.current) window.location.replace(url && url.startsWith("/") && !url.startsWith("//") ? url : "/next"); }, [me.data, url]);

  const social = (p: string) => opts.data?.nest?.includes(p)
    ? `/next/api/v1/auth/social/${p}/start${url ? `?url=${encodeURIComponent(url)}` : ""}`
    : phpUrl(php, `auth/login.php?provider=${p}${url ? `&url=${encodeURIComponent(url)}` : ""}`);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (sending.current) return;
    sending.current = true; setBusy(true); setError("");
    try {
      const r = await api.login({ email: email.trim(), password, remember, url });
      window.location.assign(r.redirect || home); // full load: every page must see the new PHP session cookie
      return;
    } catch (err) {
      setError((err instanceof ApiError ? (err.body?.message as string | undefined) : undefined) || "로그인 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.");
    }
    sending.current = false; setBusy(false);
  };

  const ico = (src: string, size = 20) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt="" className={styles.ico} style={{ width: size, height: size }} />
  );

  return (
    <div className={styles.wrap}>
      <button type="button" className={styles.back} onClick={goBack} aria-label="뒤로 가기">←</button>
      <div className={styles.hero}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={assetUrl("data/logos/pc") ?? ""} alt="서울미녀" />
      </div>
      <div className={styles.actions}>
        {socialError && <div className={styles.err} role="alert">{socialError}</div>}
        {notice && <div className={styles.ok} role="status">{notice}</div>}
        {opts.data?.kakao && <a href={social("kakao")} className={`${styles.btn} ${styles.kakao}`}>{ico(kakaoIcon.src, 22)}<span>카카오톡으로 시작하기</span></a>}
        {opts.data?.google && <a href={social("google")} className={`${styles.btn} ${styles.google}`}>{ico(googleIcon.src)}<span>구글로 시작하기</span></a>}
        {showApple && opts.data?.apple && <a href={social("apple")} className={`${styles.btn} ${styles.apple}`}>{ico(appleIcon.src)}<span>Apple로 시작하기</span></a>}
        <button type="button" className={`${styles.btn} ${styles.email}`} aria-expanded={open} onClick={() => setOpen((v) => !v)}>{ico(mailIcon.src)}<span>이메일로 시작하기</span></button>
        {open && (
          <form onSubmit={submit} style={{ display: "contents" }}>
            {error && <div className={styles.err} role="alert">{error}</div>}
            <div className={styles.field}><input type="email" placeholder="이메일" required autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
            <div className={styles.field}><input type="password" placeholder="비밀번호" required autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} /></div>
            <label className={styles.check}><input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} /> <span>자동 로그인</span></label>
            <button type="submit" className={`${styles.btn} ${styles.submit}`} disabled={busy || !email || !password}>{busy ? "로그인 중…" : "로그인"}</button>
          </form>
        )}
      </div>
      <div className={styles.links}>
        <a href="/next/password-lost">계정 찾기</a><i /><a href="/next/qna">문의하기</a><i /><a href="/next/register">회원가입</a>
      </div>
      <div className={styles.notice}>
        {SHOW_RECAPTCHA_NOTICE && (
          <>
            이 페이지는 Google reCAPTCHA의 보호를 받아 사용자가 로봇이 아님을 확인합니다.<br />
            <button type="button" className={styles.more} onClick={() => setSheet(true)}>자세히 보기</button>
          </>
        )}
      </div>
      {sheet && (
        <div ref={dimRef} className={styles.dim} onClick={closeSheet}>
          <div ref={sheetRef} className={styles.sheet} role="dialog" aria-modal="true" aria-label="reCAPTCHA 안내" onClick={(e) => e.stopPropagation()}>
            <div className={styles.gripZone} onPointerDown={dragStart} onPointerMove={dragMove} onPointerUp={dragEnd} onPointerCancel={dragEnd} aria-hidden="true">
              <div className={styles.grip} />
            </div>
            <div className={styles.sheetBody}>
              <p>이 페이지는 Google reCAPTCHA의 보호를 받아 사용자가 로봇이 아님을 확인합니다.<br />Google reCAPTCHA가 수집하는 정보에는 Google 개인정보처리방침과 서비스 약관이 적용되며, 해당 정보는 reCAPTCHA 서비스 제공, 관리 및 개선과 일반적인 보안 유지에 사용됩니다(Google의 개인 맞춤 광고에 사용 안함).</p>
              <a className={styles.pill} href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">개인정보처리방침</a>
              <a className={styles.pill} href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">서비스 약관</a>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
