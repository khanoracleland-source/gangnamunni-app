"use client";
import { useEffect, useState, useSyncExternalStore } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { api, assetUrl } from "@/lib/api";
import { useMe } from "@/lib/me";
import { phpUrl, usePhpBase } from "@/lib/php";
import styles from "./Login.module.css";
import BottomSheet from "./BottomSheet";
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
  const [sheet, setSheet] = useState(false);
  const router = useRouter();
  /** ← : back to the page the visitor came from; with no history (opened directly) go to the Next home. */
  const goBack = () => { if (window.history.length > 1) router.back(); else router.push("/"); };

  useEffect(() => { if (me.data?.loggedIn) window.location.replace(url && url.startsWith("/") && !url.startsWith("//") ? url : "/next"); }, [me.data, url]);

  const social = (p: string) => opts.data?.nest?.includes(p)
    ? `/next/api/v1/auth/social/${p}/start${url ? `?url=${encodeURIComponent(url)}` : ""}`
    : phpUrl(php, `auth/login.php?provider=${p}${url ? `&url=${encodeURIComponent(url)}` : ""}`);

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
        {/* the options arrive a moment after the page: hold the social buttons' places so the e-mail button does not appear alone and then jump down */}
        {opts.isPending && Array.from({ length: showApple ? 3 : 2 }, (_, i) => <div key={i} className={`${styles.ph} skeleton`} aria-hidden="true" />)}
        {opts.data?.kakao && <a href={social("kakao")} className={`${styles.btn} ${styles.kakao}`}>{ico(kakaoIcon.src, 22)}<span>카카오톡으로 시작하기</span></a>}
        {opts.data?.google && <a href={social("google")} className={`${styles.btn} ${styles.google}`}>{ico(googleIcon.src)}<span>구글로 시작하기</span></a>}
        {showApple && opts.data?.apple && <a href={social("apple")} className={`${styles.btn} ${styles.apple}`}>{ico(appleIcon.src)}<span>Apple로 시작하기</span></a>}
        <Link href={`/login/email${url ? `?url=${encodeURIComponent(url)}` : ""}`} className={`${styles.btn} ${styles.email}`}>{ico(mailIcon.src)}<span>이메일로 시작하기</span></Link>
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
      <BottomSheet open={sheet} onClose={() => setSheet(false)} label="reCAPTCHA 안내">
        <div className={styles.sheetBody}>
          <p>이 페이지는 Google reCAPTCHA의 보호를 받아 사용자가 로봇이 아님을 확인합니다.<br />Google reCAPTCHA가 수집하는 정보에는 Google 개인정보처리방침과 서비스 약관이 적용되며, 해당 정보는 reCAPTCHA 서비스 제공, 관리 및 개선과 일반적인 보안 유지에 사용됩니다(Google의 개인 맞춤 광고에 사용 안함).</p>
          <a className={styles.pill} href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">개인정보처리방침</a>
          <a className={styles.pill} href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">서비스 약관</a>
        </div>
      </BottomSheet>
    </div>
  );
}
