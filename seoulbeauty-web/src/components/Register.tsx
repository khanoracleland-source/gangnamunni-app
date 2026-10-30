"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import { ApiError, api, assetUrl } from "@/lib/api";
import { meKey, useMe } from "@/lib/me";
import { phpUrl, usePhpBase } from "@/lib/php";
import styles from "./AuthPages.module.css";

const PW_RE = /^(?=.*[A-Za-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s])[\x21-\x7E]{9,20}$/;
const errText = (e: unknown, fallback: string) => (e instanceof ApiError ? (e.body?.message as string | undefined) : undefined) || fallback;
const ageOf = (v: string): string => {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
  if (!m) return "";
  const now = new Date();
  let a = now.getFullYear() - Number(m[1]);
  if (now.getMonth() + 1 < Number(m[2]) || (now.getMonth() + 1 === Number(m[2]) && now.getDate() < Number(m[3]))) a -= 1;
  return a >= 0 && a <= 120 ? String(a) : "";
};

/** auth/register.php: e-mail → code by mail → password, name, birth date, phone, agreements → account created and signed in. */
export default function Register() {
  const router = useRouter();
  const qc = useQueryClient();
  const php = usePhpBase();
  const me = useMe();
  const [f, setF] = useState({ email: "", code: "", password: "", password2: "", name: "", birthDate: "", phone: "", terms: false, privacy: false, marketing: false });
  const [showPw, setShowPw] = useState(false);
  const [codeBusy, setCodeBusy] = useState(false);
  const [codeMsg, setCodeMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const sending = useRef(false);
  const set = <K extends keyof typeof f>(k: K, v: (typeof f)[K]) => setF((p) => ({ ...p, [k]: v }));

  useEffect(() => { if (me.data?.loggedIn && !sending.current) router.replace("/"); }, [me.data, router]);

  const sendCode = async () => {
    if (codeBusy) return;
    setCodeBusy(true); setCodeMsg(null);
    try { const r = await api.registerCode(f.email.trim()); setCodeMsg({ ok: true, text: r.message }); }
    catch (e) { setCodeMsg({ ok: false, text: errText(e, "인증코드 발송에 실패했습니다. 잠시 후 다시 시도해주세요.") }); }
    finally { setCodeBusy(false); }
  };

  const pwMsg = f.password && !PW_RE.test(f.password) ? "영문, 숫자, 특수문자를 각각 포함한 9~20자로 입력해주세요." : f.password2 && f.password !== f.password2 ? "비밀번호가 일치하지 않습니다." : "";
  const ready = f.email && f.code && PW_RE.test(f.password) && f.password === f.password2 && f.name.trim().length >= 2 && f.birthDate && /^01[0-9]{8,9}$/.test(f.phone.replace(/\D/g, "")) && f.terms && f.privacy;

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (sending.current || !ready) return;
    sending.current = true; setBusy(true); setError("");
    try {
      const r = await api.register({
        email: f.email.trim(), code: f.code.trim(), password: f.password, password2: f.password2, name: f.name.trim(),
        birthDate: f.birthDate, phone: f.phone, agreeTerms: f.terms, agreePrivacy: f.privacy, agreeMarketing: f.marketing,
      });
      if (r.loggedIn) {
        await qc.invalidateQueries({ queryKey: meKey });
        window.location.assign(phpUrl(php, "index.php")); // full load: the new PHP session cookie must be used by every page
      } else {
        window.location.assign(phpUrl(php, "auth/login.php"));
      }
      return;
    } catch (err) { setError(errText(err, "가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.")); }
    sending.current = false; setBusy(false);
  };
  const login = phpUrl(php, "auth/login.php");

  return (
    <div className={styles.wrap}>
      <div className={styles.head}><button type="button" className={styles.back} onClick={() => { if (window.history.length > 1) router.back(); else window.location.assign(login); }} aria-label="뒤로 가기">←</button></div>
      <div className={styles.hero}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={assetUrl("data/logos/pc") ?? ""} alt="서울미녀" />
      </div>
      <form className={styles.actions} onSubmit={submit}>
        <h1 className={styles.title}>회원가입</h1>
        {error && <div className={styles.err} role="alert">{error}</div>}
        <div className={styles.fieldRow}>
          <div className={styles.field}><input type="email" placeholder="이메일" required autoComplete="email" value={f.email} onChange={(e) => set("email", e.target.value)} /></div>
          <button type="button" className={styles.btnSide} onClick={sendCode} disabled={codeBusy || !f.email.includes("@")}>{codeBusy ? "발송 중…" : "인증코드 받기"}</button>
        </div>
        {codeMsg && <div className={codeMsg.ok ? styles.hintOk : styles.hintErr} role="status">{codeMsg.text}</div>}
        <div className={styles.field}><input type="text" inputMode="numeric" maxLength={6} placeholder="이메일로 받은 인증코드 6자리" autoComplete="one-time-code" value={f.code} onChange={(e) => set("code", e.target.value.replace(/\D/g, ""))} /></div>
        <div className={styles.fieldRow}>
          <div className={styles.field}><input type={showPw ? "text" : "password"} placeholder="비밀번호 (영문·숫자·특수문자 9~20자)" required minLength={9} maxLength={20} autoComplete="new-password" value={f.password} onChange={(e) => set("password", e.target.value)} /></div>
          <button type="button" className={styles.btnSide} onClick={() => setShowPw((v) => !v)}>{showPw ? "숨김" : "표시"}</button>
        </div>
        <div className={styles.field}><input type={showPw ? "text" : "password"} placeholder="비밀번호 확인" required minLength={9} maxLength={20} autoComplete="new-password" value={f.password2} onChange={(e) => set("password2", e.target.value)} /></div>
        {pwMsg && <div className={styles.hintErr}>{pwMsg}</div>}
        <div className={styles.field}><input type="text" placeholder="이름 (실명)" required minLength={2} maxLength={50} autoComplete="name" value={f.name} onChange={(e) => set("name", e.target.value)} /></div>
        <div className={styles.field}>
          <input type="date" aria-label="생년월일" required autoComplete="bday" value={f.birthDate} onChange={(e) => set("birthDate", e.target.value)} />
          {ageOf(f.birthDate) && <div className={styles.hint}>만 {ageOf(f.birthDate)}세</div>}
        </div>
        <div className={styles.field}><input type="tel" inputMode="numeric" maxLength={11} placeholder="휴대폰 번호 (- 없이, 예: 01012345678)" required autoComplete="tel" value={f.phone} onChange={(e) => set("phone", e.target.value.replace(/\D/g, ""))} /></div>
        <label className={styles.check}><input type="checkbox" checked={f.terms} onChange={(e) => set("terms", e.target.checked)} /> <span>(필수) 회원가입 약관에 동의합니다. <a href="/next/register-terms" target="_blank" rel="noopener">내용 보기</a></span></label>
        <label className={styles.check}><input type="checkbox" checked={f.privacy} onChange={(e) => set("privacy", e.target.checked)} /> <span>(필수) 개인정보 수집 및 이용에 동의합니다. <a href="/next/privacy" target="_blank" rel="noopener">내용 보기</a></span></label>
        <label className={styles.check}><input type="checkbox" checked={f.marketing} onChange={(e) => set("marketing", e.target.checked)} /> <span>(선택) 마케팅 정보 수신에 동의합니다. (이벤트·혜택 알림)</span></label>
        <button type="submit" className={styles.btn} disabled={busy || !ready}>{busy ? "가입 중…" : "가입하기"}</button>
        <div className={styles.foot}>이미 서울미녀 회원이신가요?<a href={login}>로그인</a><span>·</span><a href="/next/password-lost">비밀번호 찾기</a></div>
      </form>
    </div>
  );
}
