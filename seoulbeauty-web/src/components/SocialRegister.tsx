"use client";
import { useEffect, useRef, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, api, assetUrl } from "@/lib/api";
import { meKey, useMe } from "@/lib/me";
import { phpUrl, usePhpBase } from "@/lib/php";
import styles from "./AuthPages.module.css";

const errText = (e: unknown, fallback: string) => (e instanceof ApiError ? (e.body?.message as string | undefined) : undefined) || fallback;

/** plugin/social/register_member.php (Google): the profile comes from the signed cookie set by the callback; terms + name + e-mail → account. */
export default function SocialRegister() {
  const qc = useQueryClient();
  const php = usePhpBase();
  const me = useMe();
  const pending = useQuery({ queryKey: ["social-pending"], queryFn: () => api.socialPending(), staleTime: 0, gcTime: 0, retry: false });
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [terms, setTerms] = useState(false);
  const [privacy, setPrivacy] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const sending = useRef(false);
  const filled = useRef(false);

  useEffect(() => {
    if (pending.data?.pending && !filled.current) { filled.current = true; setName(pending.data.name ?? ""); setEmail(pending.data.email ?? ""); }
  }, [pending.data]);
  useEffect(() => { if (me.data?.loggedIn && !sending.current) window.location.replace("/next"); }, [me.data]);

  const login = "/next/login";
  const ready = name.trim().length >= 2 && email.includes("@") && terms && privacy;

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (sending.current || !ready) return;
    sending.current = true; setBusy(true); setError("");
    try {
      const r = await api.socialRegister({ name: name.trim(), email: email.trim(), agreeTerms: terms, agreePrivacy: privacy });
      if (r.loggedIn) {
        await qc.invalidateQueries({ queryKey: meKey });
        window.location.assign(phpUrl(php, "index.php")); // full load: the new PHP session cookie must be used by every page; the home shows the welcome points
      } else {
        window.location.assign(login);
      }
      return;
    } catch (err) {
      if (err instanceof ApiError && err.status === 410) { setError("로그인 시간이 만료되었습니다. 다시 시도해주세요."); }
      else setError(errText(err, "가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요."));
    }
    sending.current = false; setBusy(false);
  };

  return (
    <div className={styles.wrap}>
      <div className={styles.head}><a className={styles.back} href={login} aria-label="뒤로 가기">←</a></div>
      <div className={styles.hero}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={assetUrl("data/logos/pc") ?? ""} alt="서울미녀" />
      </div>
      {pending.isPending ? null : !pending.data?.pending ? (
        <div className={styles.actions}>
          <h1 className={styles.title}>회원가입</h1>
          <div className={styles.err} role="alert">로그인 시간이 만료되었습니다. 다시 시도해주세요.</div>
          <a className={styles.btn} href={login} style={{ textAlign: "center", textDecoration: "none" }}>로그인으로 돌아가기</a>
        </div>
      ) : (
        <form className={styles.actions} onSubmit={submit}>
          <h1 className={styles.title}>회원가입</h1>
          <div className={styles.hint}>{pending.data.provider === "google" ? "Google" : "소셜"} 계정으로 가입합니다. 아래 정보를 확인해주세요.</div>
          {error && <div className={styles.err} role="alert">{error}</div>}
          <div className={styles.field}><input type="text" placeholder="이름" required minLength={2} maxLength={50} autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} /></div>
          <div className={styles.field}><input type="email" placeholder="이메일" required autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
          <label className={styles.check}><input type="checkbox" checked={terms} onChange={(e) => setTerms(e.target.checked)} /> <span>(필수) 회원가입 약관에 동의합니다. <a href="/next/register-terms" target="_blank" rel="noopener">내용 보기</a></span></label>
          <label className={styles.check}><input type="checkbox" checked={privacy} onChange={(e) => setPrivacy(e.target.checked)} /> <span>(필수) 개인정보 수집 및 이용에 동의합니다. <a href="/next/privacy" target="_blank" rel="noopener">내용 보기</a></span></label>
          <button type="submit" className={styles.btn} disabled={busy || !ready}>{busy ? "가입 중…" : "가입하기"}</button>
          <div className={styles.foot}><a href={login}>취소</a></div>
        </form>
      )}
    </div>
  );
}
